<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LEtudiant\Composer\Installer\Solver;

use Composer\Config;
use Composer\Downloader\FilesystemException;
use Composer\Installer\InstallerInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\Util\SymlinkFilesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackageInstallerSolver implements InstallerInterface
{
    /**
     * @var SymlinkFilesystem
     */
    protected $filesystem;

    /**
     * @var SharedPackageInstaller
     */
    protected $symlinkInstaller;

    /**
     * @var LibraryInstaller
     */
    protected $defaultInstaller;


    /**
     * @param SharedPackageInstaller $symlinkInstaller
     * @param LibraryInstaller       $defaultInstaller
     */
    public function __construct(SharedPackageInstaller $symlinkInstaller, LibraryInstaller $defaultInstaller)
    {
        $this->symlinkInstaller = $symlinkInstaller;
        $this->defaultInstaller = $defaultInstaller;
    }

    /**
     * Returns the installation path of a package
     *
     * @param  PackageInterface $package
     *
     * @return string           path
     */
    public function getInstallPath(PackageInterface $package)
    {
        return $this->symlinkInstaller->getInstallPath($package);
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!$package->isDev()) {
            $this->defaultInstaller->install($repo, $package);
        } else {
            $this->symlinkInstaller->install($repo, $package);
        }
    }

    /**
     * @param InstalledRepositoryInterface $repo
     * @param PackageInterface             $package
     *
     * @return bool
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($package->isDev()) {
            return $this->symlinkInstaller->isInstalled($repo, $package);
        }

        return $this->defaultInstaller->isInstalled($repo, $package);
    }

    /**
     * Behaviors :
     *
     * New (version replacement, Stable to Dev or Dev to Stable) :
     *  - Stable : > vendor directory
     *  - Dev : > shared dependencies directory
     *
     * Update (if package name & target directory are the same) :
     *  - Stable : checkout new sources
     *  - Dev : checkout new sources
     *
     * In case of replacement (see "New" part above) :
     *  - The old package is completely deleted ("composer remove" process) before installing the new version
     *
     *
     * {@inheritdoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        // If both packages are stable version (tag)
        if (!$target->isDev() && !$initial->isDev()) {
            $this->defaultInstaller->update($repo, $initial, $target);
        } else {

            if (!$repo->hasPackage($initial)) {
                throw new \InvalidArgumentException('Package is not installed : ' . $initial->getPrettyName());
            }

            $this->symlinkInstaller->update($repo, $initial, $target);
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
            $this->defaultInstaller->uninstall($repo, $package);
        }

        if (!$repo->hasPackage($package)) {
            throw new \InvalidArgumentException('Package is not installed : ' . $package->getPrettyName());
        }

        $this->symlinkInstaller->uninstall($repo, $package);
    }

    /**
     * @param string $packageType
     *
     * @return bool
     */
    public function supports($packageType)
    {
        return SharedPackageInstaller::PACKAGE_TYPE === $packageType;
    }
}
