<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LEtudiant\Composer\Usage;

use Composer\Composer;
use Composer\Package\PackageInterface;
use LEtudiant\Composer\Installer\SharedPackageInstaller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackageDataManager
{
    /**
     * @var Composer
     */
    protected $composer;
    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var array
     */
    protected $packagesData;


    /**
     * @param Composer $composer
     * @param string   $vendorDir
     */
    public function __construct(Composer $composer, $vendorDir)
    {
        $this->composer  = $composer;
        $this->vendorDir = $vendorDir;
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
            $this->vendorDir . DIRECTORY_SEPARATOR . SharedPackageInstaller::PACKAGE_USAGE_FILENAME,
            json_encode($this->packagesData)
        );
    }

    /**
     * Add a row in the "packages.json" file, with the project name for the "package/version" key
     *
     * @param PackageInterface $package
     */
    public function addPackageUsage(PackageInterface $package)
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
    public function removePackageUsage(PackageInterface $package)
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
    public function getPackageUsage(PackageInterface $package)
    {
        $packageKey = $package->getPrettyName() . '/' . $package->getPrettyVersion();
        if (!isset($this->packagesData)) {
            $filePath = $this->vendorDir . DIRECTORY_SEPARATOR . SharedPackageInstaller::PACKAGE_USAGE_FILENAME;
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
     */
    public function setPackageInstallationSource(PackageInterface $package)
    {
        if (null == $package->getInstallationSource()) {
            $package->setInstallationSource($this->getPackageInstallationSource($package));
        }
    }
}
