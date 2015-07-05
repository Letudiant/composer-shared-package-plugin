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

use Composer\Package\PackageInterface;
use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\SharedPackageInstaller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackageSolver
{
    /**
     * @var array
     */
    protected $packageList;

    /**
     * @var bool
     */
    protected $areAllShared = false;


    /**
     * @param SharedPackageInstallerConfig $config
     */
    public function __construct(SharedPackageInstallerConfig $config)
    {
        $packageList = $config->getPackageList();
        if (isset($packageList['all'])) {
            $this->areAllShared = true;
        } else {
            $this->packageList = $packageList;
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool
     */
    public function isSharedPackage(PackageInterface $package)
    {
        if ($this->areAllShared) {
            return true;
        }

        if (SharedPackageInstaller::PACKAGE_TYPE === $package->getType()) {
            return true;
        }

        foreach ($this->packageList as $packageName) {
            if (
                false !== strpos($packageName, '*')
                && preg_match('/' . str_replace('*', '[a-zA-Z0-9-_]+',
                    str_replace('/', '\/', $packageName)
                ) . '/', $package->getPrettyName())
                || $packageName === $package->getPrettyName()
            ) {
                return true;
            }
        }

        return false;
    }
}
