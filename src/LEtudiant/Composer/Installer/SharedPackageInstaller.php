<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LEtudiant\Composer\Installer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackageInstaller extends LibraryInstaller
{
    const PACKAGE_TYPE           = 'shared-package';
    const PACKAGE_USAGE_FILENAME = 'packages.json';

    /**
     * @var string
     */
    protected $distDir;

    /**
     * @var string
     */
    protected $sourcesDir;

    /**
     * @var string
     */
    protected $originalVendorDir;

    /**
     * @var array
     */
    protected $packagesData;


    /**
     * @inheritdoc
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);

        $baseDir = substr($this->composer->getConfig()->get('vendor-dir'), 0, -strlen($this->composer->getConfig()->get('vendor-dir', 1)));
        $extra = $this->composer->getPackage()->getExtra();

        $this->distDir = $baseDir . 'dist';
        $this->vendorDir = $baseDir . '../dependencies';

        if (isset($extra[self::PACKAGE_TYPE]['dist-dir'])) {
            $this->distDir = $extra[self::PACKAGE_TYPE]['dist-dir'];
            if ('/' != $this->distDir[0]) {
                $this->distDir = $baseDir . $this->distDir;
            }
        }

        if (isset($extra[self::PACKAGE_TYPE]['vendor-dir'])) {
            $this->vendorDir = $baseDir . $extra[self::PACKAGE_TYPE]['vendor-dir'];
            if ('/' != $this->vendorDir[0]) {
                $this->vendorDir = $baseDir . $this->vendorDir;
            }
        }

        $this->originalVendorDir = $this->composer->getConfig()->get('vendor-dir');
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    protected function getPackageBasePath(PackageInterface $package)
    {
        // For stable version (tag), let it in the normal vendor directory, as a folder (not symlink)
        if (!$package->isDev()) {
            $this->filesystem->ensureDirectoryExists($this->originalVendorDir);

            return ($this->originalVendorDir ? $this->originalVendorDir . '/' : '') . $package->getPrettyName();
        }

        $this->filesystem->ensureDirectoryExists($this->vendorDir);

        return $this->vendorDir . DIRECTORY_SEPARATOR . $package->getPrettyName() . DIRECTORY_SEPARATOR . $package->getPrettyVersion();
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    protected function getPackageDistDir(PackageInterface $package)
    {
        return $this->distDir . DIRECTORY_SEPARATOR . $package->getPrettyName();
    }

    /**
     * Create the symlink
     *
     * @param PackageInterface $package
     */
    protected function initializeVendorSymlink(PackageInterface $package)
    {
        $distDir = $this->getPackageDistDir($package);

        if (!is_link($distDir)) {
            $prettyName = $package->getPrettyName();
            $packageParams = explode('/', $prettyName);
            $packageNamespace = substr($prettyName, 0, -strlen($packageParams[sizeof($packageParams) - 1]));

            $this->filesystem->ensureDirectoryExists($this->distDir . DIRECTORY_SEPARATOR . $packageNamespace);
            $this->io->write(array(
                '  - Creating symlink for <info>' . $package->getPrettyName()
                    . '</info> (<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
                ''
            ));

            symlink($this->getPackageBasePath($package), $distDir);
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$package->isDev()) {
            return parent::install($repo, $package);
        }

        if (!is_readable($this->getInstallPath($package))) {
            parent::install($repo, $package);
        } elseif (!$repo->hasPackage($package)) {
            $this->installBinaries($package);
            $repo->addPackage(clone $package);
        }

        $this->initializeVendorSymlink($package);

        $this->addPackageUsage($package);
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // In the case of symlink, just check if the sources folder and the link exist
        if ($package->isDev()) {
            return is_readable($this->getInstallPath($package)) && is_link($this->getPackageDistDir($package));
        }

        return parent::isInstalled($repo, $package);
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        // If both packages are stable version (tag)
        if (!$target->isDev() && !$initial->isDev()) {
            parent::update($repo, $initial, $target);

            return;
        }

        if (!$repo->hasPackage($initial)) {
            throw new \InvalidArgumentException('Package is not installed: ' . $initial);
        }

        if (null == $initial->getInstallationSource()) {
            $initial->setInstallationSource($this->getPackageInstallationSource($initial));
        }

        if (null == $target->getInstallationSource()) {
            $target->setInstallationSource($this->getPackageInstallationSource($target));
        }

        // The package need only a code update because the version is the same
        if ($this->getInstallPath($initial) === $this->getInstallPath($target)) {
            if (!$this->isInstalled($repo, $initial)) {
                $this->initializeVendorSymlink($initial);
            }

            parent::update($repo, $initial, $target);

            return;
        }

        // If the initial package sources folder exists, uninstall it
        if (is_readable($this->getInstallPath($initial))) {
            $this->uninstall($repo, $initial);
        }

        $targetDownloadPath = $this->getInstallPath($target);
        // If the target package sources folder already exists, simply override the binaries, in case of update
        if (is_readable($targetDownloadPath)) {
            $this->installBinaries($target);
            $repo->addPackage(clone $target);
        } else {
            parent::install($repo, $target);
        }

        if ($target->isDev()) {
            $this->initializeVendorSymlink($target);
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($package->isDev()) {
            if (!$repo->hasPackage($package)) {
                throw new \InvalidArgumentException('Package is not installed: ' . $package);
            }

            $this->removePackageUsage($package);
            if ($this->io->isInteractive() && $this->isSourceDirUnuse($package) && $this->io->askConfirmation(
                "The package <info>" . $package->getPrettyName() . "</info> (<fg=yellow>" . $package->getPrettyVersion() . "</fg=yellow>) seems to be unused."
                . PHP_EOL
                . 'Do you want to <fg=red>delete the source folder</fg=red> ? [y/n] (default: no) : ',
                false
            )) {
                if (null == $package->getInstallationSource()) {
                    $package->setInstallationSource($this->getPackageInstallationSource($package));
                }

                parent::uninstall($repo, $package);
            } else {
                $this->removeBinaries($package);
                $repo->removePackage($package);
            }

            $this->removeVendorSymlink($package);

            // Delete vendor prefix folder in empty
            if (strpos($package->getName(), '/')) {
                $packageVendorDir = dirname($this->getPackageDistDir($package));
                if (is_dir($packageVendorDir) && $this->filesystem->isDirEmpty($packageVendorDir)) {
                    @rmdir($packageVendorDir);
                }
            }
        } else {
            parent::uninstall($repo, $package);
        }
    }

    /**
     * @param PackageInterface $package
     */
    protected function removeVendorSymlink(PackageInterface $package)
    {
        $this->io->write(array(
            '  - Deleting symlink for <info>' . $package->getPrettyName() . '</info> (<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
            ''
        ));

        @unlink($this->getPackageDistDir($package));
    }

    /**
     * Detect if other project use the dependency by using the "packages.json" file
     *
     * @param PackageInterface $package
     *
     * @return bool
     */
    protected function isSourceDirUnuse(PackageInterface $package)
    {
        $usageData = $this->getPackageUsage($package);

        return 0 == sizeof($usageData);
    }

    /**
     * Add a row in the "packages.json" file, with the project name for the "package/version" key
     *
     * @param PackageInterface $package
     */
    protected function addPackageUsage(PackageInterface $package)
    {
        $usageData = $this->getPackageUsage($package);
        $packageName = $this->composer->getPackage()->getName();

        if (!in_array($packageName, $usageData)) {
            $usageData[] = $packageName;
        }

        $this->updatePackageUsageFile($package, $usageData);
    }

    /**
     * Remove the row in the "packages.json" file
     *
     * @param PackageInterface $package
     */
    protected function removePackageUsage(PackageInterface $package)
    {
        $usageData = $this->getPackageUsage($package);
        $newUsageData = array();
        $projectName = $this->composer->getPackage()->getName();

        foreach ($usageData as $usage) {
            if ($projectName !== $usage) {
                $newUsageData[] = $usage;
            }
        }

        $this->updatePackageUsageFile($package, $newUsageData);
    }

    /**
     * Return usage of the current package
     *
     * @param PackageInterface $package
     *
     * @return array
     */
    protected function getPackageUsage(PackageInterface $package)
    {
        $packageKey = $package->getPrettyName() . '/' . $package->getPrettyVersion();
        if (!isset($this->packagesData)) {
            $filePath = $this->vendorDir . DIRECTORY_SEPARATOR . self::PACKAGE_USAGE_FILENAME;
            if (!is_file($filePath)) {
                $this->packagesData = array();
            } else {
                $this->packagesData = json_decode(file_get_contents($filePath), true);
            }
        }

        if (!isset($this->packagesData[$packageKey])) {
            return array();
        }

        return $this->packagesData[$packageKey]['project-usage'];
    }

    /**
     * @param PackageInterface $package
     *
     * @return string|null
     */
    protected function getPackageInstallationSource(PackageInterface $package)
    {
        $packageKey = $package->getPrettyName() . '/' . $package->getPrettyVersion();
        if (!isset($this->packagesData[$packageKey])) {
            return null;
        }

        return $this->packagesData[$packageKey]['installation-source'];
    }

    /**
     * @param PackageInterface $package
     * @param array            $packageData
     */
    protected function updatePackageUsageFile(PackageInterface $package, array $packageData)
    {
        $packageKey = $package->getPrettyName() . '/' . $package->getPrettyVersion();
        if (!isset($packageData[0]) && isset($this->packagesData[$packageKey])) {
            unset($this->packagesData[$packageKey]);
        } elseif (!isset($this->packagesData[$packageKey])) {
            if (null == $package->getInstallationSource()) {
                throw new \RuntimeException(
                    'Unknown installation source for package "' . $package->getPrettyName()
                    . '" ("' . $package->getPrettyVersion() . '")'
                );
            }

            $this->packagesData[$packageKey] = array(
                'installation-source' => $package->getInstallationSource(),
                'project-usage'       => $packageData
            );
        } else {
            $this->packagesData[$packageKey]['project-usage'] = $packageData;
        }

        file_put_contents(
            $this->vendorDir . DIRECTORY_SEPARATOR . self::PACKAGE_USAGE_FILENAME,
            json_encode($this->packagesData)
        );
    }

    /**
     * @param string $packageType
     *
     * @return bool
     */
    public function supports($packageType)
    {
        return self::PACKAGE_TYPE === $packageType;
    }
}
