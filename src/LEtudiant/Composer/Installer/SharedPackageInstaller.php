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
use LEtudiant\Composer\Data\Package\PackageDataManagerInterface;
use LEtudiant\Composer\Data\Package\SharedPackageDataManager;
use LEtudiant\Composer\Util\SymlinkFilesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackageInstaller extends LibraryInstaller
{
    const PACKAGE_TYPE = 'shared-package';

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
     * @var PackageDataManagerInterface
     */
    protected $packageDataManager;

    /**
     * @var SymlinkFilesystem
     */
    protected $filesystem;


    /**
     * @inheritdoc
     */
    public function __construct(
        IOInterface $io,
        Composer $composer,
        SymlinkFilesystem $filesystem = null,
        PackageDataManagerInterface $dataManager = null,
        $type = 'library'
    )
    {
        $this->setFilesystem($filesystem);

        parent::__construct($io, $composer, $type, $filesystem);

        $this->setDataManager($dataManager);
        $config = $this->composer->getConfig();
        $this->originalVendorDir = $config->get('vendor-dir');
        $baseDir = substr($this->originalVendorDir, 0, -strlen($config->get('vendor-dir', 1)));

        $extra = $this->composer->getPackage()->getExtra();
        $this->setSymlinkDirectory($baseDir, $extra);
        $this->setVendorDir($baseDir, $extra);
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

        $this->createPackageVendorSymlink($package);
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
            $this->createPackageVendorSymlink($target);

            parent::update($repo, $initial, $target);
        } else {
            // If the initial package sources folder exists, uninstall it
            $this->uninstall($repo, $initial);

            // Install the target package
            $this->install($repo, $target);
        }
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

        if ($this->isSourceDirUnused($package) && $this->io->askConfirmation(
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

        $this->packageDataManager->removePackageUsage($package);
        $this->removePackageVendorSymlink($package);
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
     * @param string $baseDir
     * @param array  $extra
     */
    protected function setSymlinkDirectory($baseDir, $extra)
    {
        $this->symlinkDir = $baseDir . 'vendor-shared';

        if (isset($extra[self::PACKAGE_TYPE]['symlink-dir'])) {
            $this->symlinkDir = $extra[self::PACKAGE_TYPE]['symlink-dir'];
            if ('/' != $this->symlinkDir[0]) {
                $this->symlinkDir = $baseDir . $this->symlinkDir;
            }
        }
    }

    /**
     * @param string $baseDir
     * @param array  $extra
     *
     * @throws \InvalidArgumentException
     */
    protected function setVendorDir($baseDir, $extra)
    {
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
    }

    /**
     * @param PackageInterface $package
     */
    protected function createPackageVendorSymlink(PackageInterface $package)
    {
        if ($this->filesystem->ensureSymlinkExists(
            $this->getInstallPath($package),
            $this->getPackageVendorSymlink($package)
        )) {
            $this->io->write(array(
                '  - Creating symlink for <info>' . $package->getPrettyName()
                . '</info> (<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
                ''
            ));
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @throws FilesystemException
     */
    protected function removePackageVendorSymlink(PackageInterface $package)
    {
        if ($this->filesystem->removeSymlink($this->getPackageVendorSymlink($package))) {
            $this->io->write(array(
                '  - Deleting symlink for <info>' . $package->getPrettyName() . '</info> '
                . '(<fg=yellow>' . $package->getPrettyVersion() . '</fg=yellow>)',
                ''
            ));

            $symlinkParentDirectory = dirname($this->getPackageVendorSymlink($package));
            $this->filesystem->removeEmptyDirectory($symlinkParentDirectory);
        }
    }

    /**
     * @param SymlinkFilesystem $filesystem
     */
    protected function setFilesystem(SymlinkFilesystem $filesystem = null)
    {
        if (null == $filesystem) {
            $this->filesystem = new SymlinkFilesystem();
        }
    }

    /**
     * @param PackageDataManagerInterface $dataManager
     */
    protected function setDataManager(PackageDataManagerInterface $dataManager = null)
    {
        if (null == $dataManager) {
            $this->packageDataManager = new SharedPackageDataManager($this->composer, $this->vendorDir);
        }
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
