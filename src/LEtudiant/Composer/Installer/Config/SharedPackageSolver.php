<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LEtudiant\Composer\Installer\Config;

use Composer\Package\PackageInterface;
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
     * @param array $packageList
     */
    public function __construct(array $packageList)
    {
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

        foreach ($this->packageList as $packageName) {
            if (
                false !== strpos($package, '*')
                && preg_match('/' . str_replace('*', '[a-zA-Z0-9-]+', $packageName) . '/', $package)
                || $packageName === $package->getPrettyName()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param PackageInterface $package
     *
     * @return bool
     */
    public function support(PackageInterface $package)
    {
        return isset($this->packageList) || SharedPackageInstaller::PACKAGE_TYPE === $package->getType();
    }
}
