<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Unit\LEtudiant\Composer\Installer\Config;

use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\SharedPackageInstaller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig
 */
class SharedPackageInstallerConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "vendor-dir" parameter for "shared-package" configuration should be provided in your composer.json (extra part)
     */
    public function noVendorDirConfigured()
    {
        $this->createInstallerConfig(array());
    }

    /**
     * @test
     *
     * @dataProvider getVendorDirDataProvider
     *
     * @param string $vendorDirPath
     */
    public function getVendorDir($vendorDirPath)
    {
        $this->assertEquals(sys_get_temp_dir() . '/composer-test-dependencies-dir', $this->createInstallerConfig(array(
            'vendor-dir' => $vendorDirPath
        ))->getVendorDir());
    }

    /**
     * @return array
     */
    public function getVendorDirDataProvider()
    {
        return array(
            array(
                sys_get_temp_dir() . '/composer-test-dependencies-dir'
            ),
            array(
                'composer-test-dependencies-dir'
            )
        );
    }

    /**
     * @test
     *
     * @dataProvider getSymlinkDirDataProvider
     *
     * @param string $symlinkDirPath
     */
    public function getSymlinkDir($symlinkDirPath)
    {
        $this->assertEquals(sys_get_temp_dir() . '/composer-test-vendor-shared-dir', $this->createInstallerConfig(array(
            'vendor-dir'  => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-dir' => $symlinkDirPath
        ))->getSymlinkDir());
    }

    /**
     * @return array
     */
    public function getSymlinkDirDataProvider()
    {
        return array(
            array(
                sys_get_temp_dir() . '/composer-test-vendor-shared-dir'
            ),
            array(
                'composer-test-vendor-shared-dir'
            )
        );
    }

    /**
     * @test
     */
    public function getSymlinkDirWithEmptyConfiguration()
    {
        $this->assertEquals(sys_get_temp_dir() . '/vendor-shared', $this->createInstallerConfig(array(
            'vendor-dir'  => sys_get_temp_dir() . '/composer-test-dependencies-dir'
        ))->getSymlinkDir());
    }

    /**
     * @test
     *
     * @dataProvider getOriginalVendorDirDataProvider
     */
    public function getOriginalVendorDir($expectedValue, $nullable)
    {
        $this->assertEquals($expectedValue, $this->createInstallerConfig(array(
            'vendor-dir'  => sys_get_temp_dir() . '/composer-test-dependencies-dir'
        ))->getOriginalVendorDir($nullable));
    }

    public function getOriginalVendorDirDataProvider()
    {
        return array(
            array(
                'composer-test-vendor-dir/',
                true
            ),
            array(
                'composer-test-vendor-dir',
                false
            )
        );
    }

    /**
     * @param array       $extra
     * @param string      $relativeDir
     * @param null|string $absoluteDir
     *
     * @return SharedPackageInstallerConfig
     */
    protected function createInstallerConfig(array $extra, $relativeDir = 'composer-test-vendor-dir', $absoluteDir = null)
    {
        if (null == $absoluteDir) {
            $absoluteDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $relativeDir;
        }

        return new SharedPackageInstallerConfig(
            $relativeDir,
            $absoluteDir,
            array(
                SharedPackageInstaller::PACKAGE_TYPE => $extra
            )
        );
    }
}
