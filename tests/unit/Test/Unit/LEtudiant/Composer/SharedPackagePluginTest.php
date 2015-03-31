<?php

namespace Test\Unit\LEtudiant\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\SharedPackagePlugin;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\SharedPackagePlugin
 */
class SharedPackagePluginTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $io;

    /**
     * @var InstallationManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $im;


    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->composer = new Composer();

        $config = new Config();
        $this->composer->setConfig($config);

        /** @var RootPackageInterface|\PHPUnit_Framework_MockObject_MockObject $package */
        $package = $this->getMock('Composer\Package\RootPackageInterface');
        $package
            ->expects($this->any())
            ->method('getExtra')
            ->willReturn(array(
                SharedPackageInstaller::PACKAGE_TYPE => array(
                    'vendor-dir'  => sys_get_temp_dir() . '/composer-test-vendor-shared'
                )
            ))
        ;
        $this->composer->setPackage($package);

        $this->im = $this->getMock('Composer\Installer\InstallationManager');
        $this->composer->setInstallationManager($this->im);

        $this->io = $this->getMock('Composer\IO\IOInterface');
    }

    /**
     * @test
     */
    public function active()
    {
        $this->im->expects($this->once())
            ->method('addInstaller')
        ;

        $plugin = new SharedPackagePlugin();
        $plugin->activate($this->composer, $this->io);
    }
}
