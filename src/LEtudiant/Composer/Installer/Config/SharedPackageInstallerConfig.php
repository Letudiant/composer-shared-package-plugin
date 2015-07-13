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

use LEtudiant\Composer\Installer\SharedPackageInstaller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @see https://github.com/Letudiant/composer-shared-package-plugin/blob/master/docs/all-available-configurations.md
 */
class SharedPackageInstallerConfig
{
    /**
     * @var string
     */
    protected $originalVendorDir;

    /**
     * @var string
     */
    protected $symlinkDir;

    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var string|null
     */
    protected $symlinkBasePath;

    /**
     * @var bool
     */
    protected $isSymlinkEnabled = true;

    /**
     * @var array
     */
    protected $packageList = array();


    /**
     * @param string     $originalRelativeVendorDir
     * @param string     $originalAbsoluteVendorDir
     * @param array|null $extraConfigs
     */
    public function __construct($originalRelativeVendorDir, $originalAbsoluteVendorDir, $extraConfigs)
    {
        if (!isset($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'])) {
            throw new \InvalidArgumentException(
                'The "vendor-dir" parameter for "' . SharedPackageInstaller::PACKAGE_TYPE . '" configuration '
                . 'should be provided in your project composer.json ("extra" key)'
            );
        }

        $this->originalVendorDir = $originalRelativeVendorDir;

        $baseDir = substr($originalAbsoluteVendorDir, 0, -strlen($this->originalVendorDir));

        $this->setVendorDir($baseDir, $extraConfigs);
        $this->setSymlinkDirectory($baseDir, $extraConfigs);
        $this->setSymlinkBasePath($extraConfigs);
        $this->setIsSymlinkEnabled($extraConfigs);
        $this->setPackageList($extraConfigs);
    }

    /**
     * @param string $baseDir
     * @param array  $extraConfigs
     */
    protected function setSymlinkDirectory($baseDir, array $extraConfigs)
    {
        $this->symlinkDir = $baseDir . 'vendor-shared';

        if (isset($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-dir'])) {
            $this->symlinkDir = $extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-dir'];

            if ('/' != $this->symlinkDir[0]) {
                $this->symlinkDir = $baseDir . $this->symlinkDir;
            }
        }
    }

    /**
     * @param string $baseDir
     * @param array  $extraConfigs
     *
     * @throws \InvalidArgumentException
     */
    protected function setVendorDir($baseDir, array $extraConfigs)
    {
        $this->vendorDir = $extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'];
        if ('/' != $this->vendorDir[0]) {
            $this->vendorDir = $baseDir . $this->vendorDir;
        }
    }

    /**
     * Allow to override symlinks base path.
     * This is useful for a Virtual Machine environment, where directories can be different
     * on the host machine and the guest machine.
     *
     * @param array $extraConfigs
     */
    protected function setSymlinkBasePath(array $extraConfigs)
    {
        if (isset($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-base-path'])) {
            $this->symlinkBasePath = $extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-base-path'];

            // Remove the ending slash if exists
            if ('/' === $this->symlinkBasePath[strlen($this->symlinkBasePath) - 1]) {
                $this->symlinkBasePath = substr($this->symlinkBasePath, 0, -1);
            }
        } elseif (0 < strpos($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'], '/')) {
            $this->symlinkBasePath = $extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'];
        }

        // Up to the project root directory
        if (0 < strpos($this->symlinkBasePath, '/')) {
            $this->symlinkBasePath = '../../' . $this->symlinkBasePath;
        }
    }

    /**
     * The symlink directory creation process can be disabled.
     * This may mean that you work directly with the sources directory so the symlink directory is useless.
     *
     * @param array $extraConfigs
     */
    protected function setIsSymlinkEnabled(array $extraConfigs)
    {
        if (isset($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-enabled'])) {
            if (!is_bool($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-enabled'])) {
                throw new \UnexpectedValueException('The configuration "symlink-enabled" should be a boolean');
            }

            $this->isSymlinkEnabled = $extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['symlink-enabled'];
        }
    }

    /**
     * @return array
     */
    public function getPackageList()
    {
        return $this->packageList;
    }

    /**
     * @param array $extraConfigs
     */
    public function setPackageList(array $extraConfigs)
    {
        if (isset($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['package-list'])) {
            $packageList = $extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['package-list'];

            if (!is_array($packageList)) {
                throw new \UnexpectedValueException('The configuration "package-list" should be a JSON object');
            }

            $this->packageList = $packageList;
        }
    }

    /**
     * @return bool
     */
    public function isSymlinkEnabled()
    {
        return $this->isSymlinkEnabled;
    }

    /**
     * @return string
     */
    public function getVendorDir()
    {
        return $this->vendorDir;
    }

    /**
     * @return string
     */
    public function getSymlinkDir()
    {
        return $this->symlinkDir;
    }

    /**
     * @param bool $endingSlash
     *
     * @return string
     */
    public function getOriginalVendorDir($endingSlash = false)
    {
        if ($endingSlash && null != $this->originalVendorDir) {
            return $this->originalVendorDir . '/';
        }

        return $this->originalVendorDir;
    }

    /**
     * @return string|null
     */
    public function getSymlinkBasePath()
    {
        return $this->symlinkBasePath;
    }
}
