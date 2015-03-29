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
use Composer\Downloader\FilesystemException;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use LEtudiant\Composer\Data\Package\SharedPackageDataManager;

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
    protected $symlinkDir;

    /**
     * @var string
     */
    protected $sourcesDir;

    /**
     * @var string
     */
    protected $originalVendorDir;

    /**
     * @var SharedPackageDataManager
     */
    protected $packageDataManager;


    /**
     * @inheritdoc
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null)
    {
        parent::__construct($io, $composer, $type, $filesystem);

        $extra = $this->composer->getPackage()->getExtra();
        $baseDir = substr(
            $this->composer->getConfig()->get('vendor-dir'),
            0,
            -strlen($this->composer->getConfig()->get('vendor-dir', 1))
        );
        $this->symlinkDir = $baseDir . 'vendor-shared';

        if (isset($extra[self::PACKAGE_TYPE]['symlink-dir'])) {
            $this->symlinkDir = $extra[self::PACKAGE_TYPE]['symlink-dir'];
            if ('/' != $this->symlinkDir[0]) {
                $this->symlinkDir = $baseDir . $this->symlinkDir;
            }
        }

        if (!isset($extra[self::PACKAGE_TYPE]['vendor-dir'])) {
            throw new \InvalidArgumentException(
                'The "vendor-dir" parameter for "' . self::PACKAGE_TYPE . '" configuration should be provided in your '
                . 'composer.json (extra part)'
            );
        }

        $this->vendorDir = $baseDir . $extra[self::PACKAGE_TYPE]['vendor-dir'];
        if ('/' != $this->vendorDir[0]) {
            $this->vendorDir = $baseDir . $this->vendorDir;
        }

        $this->originalVendorDir  = $this->composer->getConfig()->get('vendor-dir');
        $this->packageDataManager = new SharedPackageDataManager($composer, $this->vendorDir);
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

        return
            $this->vendorDir . DIRECTORY_SEPARATOR
            . $package->getPrettyName() . DIRECTORY_SEPARATOR
            . $package->getPrettyVersion()
        ;
    }

    /**
     * @param PackageInterface $package
     *
     * @return string
     */
    protected function getPackageVendorSymlink(PackageInterface $package)
    {
        return $this->symlinkDir . DIRECTORY_SEPARATOR . $package->getPrettyName();
    }

    /**
     * Create the symlink
     *
     * @param PackageInterface $package
     */
    protected function initializeVendorSymlink(PackageInterface $package)
    {
        $symlink = $this->getPackageVendorSymlink($package);

        if (!is_link($symlink)) {
            $prettyName = $package->getPrettyName();
            $packageParams = explode('/', $prettyName);
            $packageNamespace = substr($prettyName, 0, -strlen($packageParams[sizeof($packageParams) - 1]));

            $this->filesystem->ensureDirectoryExists($this->symlinkDir . DIRECTORY_SEPARATOR . $packageNamespace);
            $this->io->write(array(
                '  - Creating symlink for <info>' . $package->getPrettyName()
                . '</info> (<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
                ''
            ));

            symlink($this->getPackageBasePath($package), $symlink);
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$package->isDev()) {
            parent::install($repo, $package);

            return;
        }

        if (!is_readable($this->getInstallPath($package))) {
            parent::install($repo, $package);
        } elseif (!$repo->hasPackage($package)) {
            $this->installBinaries($package);
            $repo->addPackage(clone $package);
        }

        $this->initializeVendorSymlink($package);

        $this->packageDataManager->addPackageUsage($package);
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
            return
                $repo->hasPackage($package)
                && is_readable($this->getInstallPath($package))
                && is_link($this->getPackageVendorSymlink($package))
            ;
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
            throw new \InvalidArgumentException('Package is not installed : ' . $initial->getPrettyName());
        }

        $this->packageDataManager->setPackageInstallationSource($initial);
        $this->packageDataManager->setPackageInstallationSource($target);

        // The package need only a code update because the version is the same
        if ($this->getInstallPath($initial) === $this->getInstallPath($target)) {
            $this->initializeVendorSymlink($initial);
            parent::update($repo, $initial, $target);

            return;
        }

        // If the initial package sources folder exists, uninstall it
        $this->uninstall($repo, $initial);

        // Install the target package
        $this->install($repo, $target);
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @throws FilesystemException
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$package->isDev()) {
            parent::uninstall($repo, $package);

            return;
        }

        if (!$repo->hasPackage($package)) {
            throw new \InvalidArgumentException('Package is not installed : ' . $package->getPrettyName());
        }

        $this->packageDataManager->removePackageUsage($package);
        if ($this->io->isInteractive() && $this->isSourceDirUnused($package) && $this->io->askConfirmation(
                "The package version <info>" . $package->getPrettyName() . "</info> "
                . "(<fg=yellow>" . $package->getPrettyVersion() . "</fg=yellow>) seems to be unused."
                . PHP_EOL
                . 'Do you want to <fg=red>delete the source folder</fg=red> ? [y/n] (default: no) : ',
                false
            )) {
            $this->packageDataManager->setPackageInstallationSource($package);

            parent::uninstall($repo, $package);
        } else {
            $this->removeBinaries($package);
            $repo->removePackage($package);
        }

        $this->removeVendorSymlink($package);
    }

    /**
     * @param PackageInterface $package
     *
     * @throws FilesystemException
     */
    protected function removeVendorSymlink(PackageInterface $package)
    {
        $this->io->write(array(
            '  - Deleting symlink for <info>' . $package->getPrettyName() . '</info> '
            . '(<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
            ''
        ));

        $packageVendorSymlink = $this->getPackageVendorSymlink($package);
        if (
            is_link($packageVendorSymlink)
            && !unlink($this->getPackageVendorSymlink($package))
        ) {
            // @codeCoverageIgnoreStart
            throw new FilesystemException('Unable to remove the symlink : ' . $packageVendorSymlink);
            // @codeCoverageIgnoreEnd
        }

        // Delete symlink vendor prefix folder if empty
        $packageVendorDir = dirname($this->getPackageVendorSymlink($package));
        if (
            is_dir($packageVendorDir) && $this->filesystem->isDirEmpty($packageVendorDir)
            && !rmdir($packageVendorDir)
        ) {
            // @codeCoverageIgnoreStart
            throw new FilesystemException('Unable to remove the directory : ' . $packageVendorDir);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Detect if other project use the dependency by using the "packages.json" file
     *
     * @param PackageInterface $package
     *
     * @return bool
     */
    protected function isSourceDirUnused(PackageInterface $package)
    {
        $usageData = $this->packageDataManager->getPackageUsage($package);

        return 0 == sizeof($usageData);
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
