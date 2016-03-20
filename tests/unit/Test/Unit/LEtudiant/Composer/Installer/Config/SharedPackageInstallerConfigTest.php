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
     * Delete both env vars
     */
    protected function tearDown()
    {
        putenv(SharedPackageInstallerConfig::ENV_PARAMETER_VENDOR_DIR);
        putenv(SharedPackageInstallerConfig::ENV_PARAMETER_SYMLINK_BASE_PATH);

        parent::tearDown();
    }

    /**
     * @test
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "vendor-dir" parameter for "shared-package" configuration should be provided in your project composer.json ("extra" key)
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
     * @test
     *
     * @dataProvider getVendorDirFromEnvVarDataProvider
     *
     * @param string $vendorDirPath
     * @param string $envVar
     */
    public function getVendorDirFromEnvVar($vendorDirPath, $envVar)
    {
        putenv(SharedPackageInstallerConfig::ENV_PARAMETER_VENDOR_DIR . '=' . $envVar);

        $this->assertEquals(sys_get_temp_dir() . '/composer-test-dependencies-dir-env-var', $this->createInstallerConfig(array(
            'vendor-dir' => $vendorDirPath
        ))->getVendorDir());
    }

    /**
     * @return array
     */
    public function getVendorDirFromEnvVarDataProvider()
    {
        return array(
            array(
                sys_get_temp_dir() . '/composer-test-dependencies-dir',
                sys_get_temp_dir() . '/composer-test-dependencies-dir-env-var'
            ),
            array(
                'composer-test-dependencies-dir',
                'composer-test-dependencies-dir-env-var'
            )
        );
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

    /**
     * @return array
     */
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
     * @test
     */
    public function getSymlinkBasePathWhenNull()
    {
        $this->assertNull($this->createInstallerConfig(array(
            'vendor-dir'  => sys_get_temp_dir() . '/composer-test-dependencies-dir'
        ))->getSymlinkBasePath());
    }

    /**
     * @test
     */
    public function getSymlinkBasePathWhenNotNull()
    {
        $this->assertEquals('/foo/bar', $this->createInstallerConfig(array(
            'vendor-dir'        => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-base-path' => '/foo/bar'
        ))->getSymlinkBasePath());
    }

    /**
     * @test
     */
    public function getSymlinkBasePathWhenNotNullAndEndingSlash()
    {
        $this->assertEquals('/foo/bar', $this->createInstallerConfig(array(
            'vendor-dir'        => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-base-path' => '/foo/bar/'
        ))->getSymlinkBasePath());
    }

    /**
     * @test
     */
    public function getSymlinkBasePathWhenVendorDirIsRelative()
    {
        $this->assertEquals('../../../composer-test-dependencies-dir', $this->createInstallerConfig(array(
            'vendor-dir' => '../composer-test-dependencies-dir'
        ))->getSymlinkBasePath());
    }

    /**
     * @test
     */
    public function getSymlinkBasePathWhenRelative()
    {
        $this->assertEquals('../../../composer-test-dependencies-dir', $this->createInstallerConfig(array(
            'vendor-dir'        => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-base-path' => '../composer-test-dependencies-dir'
        ))->getSymlinkBasePath());
    }

    /**
     * @test
     */
    public function getSymlinkBasePathFromEnvVar()
    {
        putenv(SharedPackageInstallerConfig::ENV_PARAMETER_SYMLINK_BASE_PATH . '=/composer-test-dependencies-dir-env-var');

        $this->assertEquals('/composer-test-dependencies-dir-env-var', $this->createInstallerConfig(array(
            'vendor-dir'        => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-base-path' => '../composer-test-dependencies-dir'
        ))->getSymlinkBasePath());
    }

    /**
     * @test
     */
    public function isSymlinkEnabledDefaultValue()
    {
        $this->assertTrue($this->createInstallerConfig(array(
            'vendor-dir' => sys_get_temp_dir() . '/composer-test-dependencies-dir',
        ))->isSymlinkEnabled());
    }

    /**
     * @test
     */
    public function setIsSymlinkEnabled()
    {
        $this->assertFalse($this->createInstallerConfig(array(
            'vendor-dir'      => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-enabled' => false
        ))->isSymlinkEnabled());
    }

    /**
     * @test
     *
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage The configuration "symlink-enabled" should be a boolean
     */
    public function setIsSymlinkEnabledWithString()
    {
        $this->assertTrue($this->createInstallerConfig(array(
            'vendor-dir'      => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'symlink-enabled' => 'foo'
        ))->isSymlinkEnabled());
    }

    /**
     * @test
     *
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage The configuration "package-list" should be a JSON object
     */
    public function setPackageListWrongTypeException()
    {
        $this->createInstallerConfig(array(
            'vendor-dir'   => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'package-list' => 'foo'
        ));
    }

    /**
     * @test
     */
    public function setPackageList()
    {
        $this->assertEquals(array('foo'), $this->createInstallerConfig(array(
            'vendor-dir'   => sys_get_temp_dir() . '/composer-test-dependencies-dir',
            'package-list' => array(
                'foo'
            )
        ))->getPackageList());
    }

    /**
     * @test
     */
    public function setPackageListEmpty()
    {
        $this->assertEquals(array(), $this->createInstallerConfig(array(
            'vendor-dir' => sys_get_temp_dir() . '/composer-test-dependencies-dir'
        ))->getPackageList());
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
