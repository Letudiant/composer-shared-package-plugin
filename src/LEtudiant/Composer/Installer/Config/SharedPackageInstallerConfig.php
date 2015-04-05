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
     * @param string     $originalRelativeVendorDir
     * @param string     $originalAbsoluteVendorDir
     * @param array|null $extraConfigs
     */
    public function __construct($originalRelativeVendorDir, $originalAbsoluteVendorDir, $extraConfigs)
    {
        $this->originalVendorDir = $originalRelativeVendorDir;

        $baseDir = substr($originalAbsoluteVendorDir, 0, -strlen($this->originalVendorDir));

        $this->setSymlinkDirectory($baseDir, $extraConfigs);
        $this->setVendorDir($baseDir, $extraConfigs);

    }

    /**
     * @param string     $baseDir
     * @param null|array $extra
     */
    protected function setSymlinkDirectory($baseDir, $extra)
    {
        $this->symlinkDir = $baseDir . 'vendor-shared';

        if (isset($extra[SharedPackageInstaller::PACKAGE_TYPE]['symlink-dir'])) {
            $this->symlinkDir = $extra[SharedPackageInstaller::PACKAGE_TYPE]['symlink-dir'];

            if ('/' != $this->symlinkDir[0]) {
                $this->symlinkDir = $baseDir . $this->symlinkDir;
            }
        }
    }

    /**
     * @param string     $baseDir
     * @param null|array $extra
     *
     * @throws \InvalidArgumentException
     */
    protected function setVendorDir($baseDir, $extra)
    {
        if (!isset($extra[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'])) {
            throw new \InvalidArgumentException(
                'The "vendor-dir" parameter for "' . SharedPackageInstaller::PACKAGE_TYPE . '" configuration should be provided in your '
                . 'composer.json (extra part)'
            );
        }

        $this->vendorDir = $extra[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'];
        if ('/' != $this->vendorDir[0]) {
            $this->vendorDir = $baseDir . $this->vendorDir;
        }
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
}
