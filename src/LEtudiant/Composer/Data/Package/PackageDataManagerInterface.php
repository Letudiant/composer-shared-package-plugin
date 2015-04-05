<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LEtudiant\Composer\Data\Package;

use Composer\Package\PackageInterface;


/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
interface PackageDataManagerInterface
{
    /**
     * Add a row in the "packages.json" file, with the project name for the "package/version" key
     *
     * @param PackageInterface $package
     */
    public function addPackageUsage(PackageInterface $package);

    /**
     * Remove the row in the "packages.json" file
     *
     * @param PackageInterface $package
     */
    public function removePackageUsage(PackageInterface $package);

    /**
     * Return usage of the current package
     *
     * @param PackageInterface $package
     *
     * @return array
     */
    public function getPackageUsage(PackageInterface $package);

    /**
     * @param PackageInterface $package
     */
    public function setPackageInstallationSource(PackageInterface $package);
}
