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

use Composer\Composer;
use Composer\Package\PackageInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackageDataManager implements PackageDataManagerInterface
{
    const PACKAGE_DATA_FILENAME = 'packages.json';

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
     */
    public function __construct(Composer $composer)
    {
        $this->composer  = $composer;
    }

    /**
     * @param string $vendorDir
     */
    public function setVendorDir($vendorDir)
    {
        $this->vendorDir = $vendorDir;
    }

    /**
     * @param PackageInterface $package
     * @param array            $packageData
     */
    protected function updatePackageUsageFile(PackageInterface $package, array $packageData)
    {
        $packageKey = $package->getPrettyName() . '/' . $package->getPrettyVersion();

        // Remove the line if there is no data anymore
        if (!isset($packageData[0])) {
            if (isset($this->packagesData[$packageKey])) {
                unset($this->packagesData[$packageKey]);
            }
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
            $this->vendorDir . DIRECTORY_SEPARATOR . self::PACKAGE_DATA_FILENAME,
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
        return $this->getPackageDataKey($package, 'project-usage', array());
    }

    /**
     * Initialize the package data array if not set
     */
    protected function initializePackageData()
    {
        $filePath = $this->vendorDir . DIRECTORY_SEPARATOR . self::PACKAGE_DATA_FILENAME;
        if (!is_file($filePath)) {
            $this->packagesData = array();
        } else {
            $this->packagesData = json_decode(file_get_contents($filePath), true);
        }
    }

    /**
     * @param PackageInterface $package
     *
     * @return string|null
     */
    protected function getPackageInstallationSource(PackageInterface $package)
    {
        return $this->getPackageDataKey($package, 'installation-source');
    }

    /**
     * @param PackageInterface $package
     * @param string           $key
     * @param mixed            $defaultValue
     *
     * @return mixed
     */
    protected function getPackageDataKey(PackageInterface $package, $key, $defaultValue = null)
    {
        if (!isset($this->packagesData)) {
            $this->initializePackageData();
        }

        $packageKey = $package->getPrettyName() . '/' . $package->getPrettyVersion();
        if (!isset($this->packagesData[$packageKey])) {
            return $defaultValue;
        }

        return $this->packagesData[$packageKey][$key];
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
