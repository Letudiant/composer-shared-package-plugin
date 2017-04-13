<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Unit\LEtudiant\Composer\Data\Package;

use Composer\Composer;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use LEtudiant\Composer\Data\Package\SharedPackageDataManager;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\Data\Package\SharedPackageDataManager
 */
class SharedPackageDataManagerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var RootPackageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $rootPackage;

    /**
     * @var Composer
     */
    protected $composer;


    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->vendorDir = sys_get_temp_dir() . '/composer-test-vendor-shared';
        if (!is_dir($this->vendorDir)) {
            if (!mkdir($this->vendorDir)) {
                throw new \RuntimeException('Cannot create the temporary vendor dir');
            }
        } else {
            $this->clearPackageData();
        }

        $this->composer = new Composer();

        /** @var RootPackageInterface|\PHPUnit_Framework_MockObject_MockObject $rootPackage */
        $this->rootPackage = $this->getMock('Composer\Package\RootPackageInterface');
        $this->composer->setPackage($this->rootPackage);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        $this->clearPackageData();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function getPackageUsageWithoutFile()
    {
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);

        $this->assertEquals(array(), $dataManager->getPackageUsage($this->createPackage()));
    }

    /**
     * @test
     */
    public function getPackageUsageWithFile()
    {
        $this->initializePackageData();
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);

        $this->assertEquals(array(
            'letudiant/root-package'
        ), $dataManager->getPackageUsage($this->createPackage()));
    }

    /**
     * @test
     */
    public function addPackageUsageWithoutData()
    {
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);

        $this->rootPackage
            ->expects($this->once())
            ->method('getName')
            ->willReturn('letudiant/root-package')
        ;

        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->exactly(2))
            ->method('getPrettyName')
            ->willReturn('letudiant/foo-bar')
        ;

        $package
            ->expects($this->exactly(2))
            ->method('getPrettyVersion')
            ->willReturn('dev-develop')
        ;

        $package
            ->expects($this->never())
            ->method('getInstallationSource')
        ;

        $dataManager->addPackageUsage($package);

        $this->assertFileExists($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);

        $content = file_get_contents($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);
        $this->assertJson($content);
        $this->assertEquals(array(
            'letudiant/foo-bar/dev-develop' => array(
                'project-usage' => array(
                    'letudiant/root-package'
                )
            )
        ), json_decode($content, true));
    }

    /**
     * @test
     */
    public function addPackageUsageWithHasData()
    {
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);

        $this->initializePackageData();

        $this->rootPackage
            ->expects($this->once())
            ->method('getName')
            ->willReturn('letudiant/root-package2')
        ;

        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->exactly(2))
            ->method('getPrettyName')
            ->willReturn('letudiant/foo-bar')
        ;

        $package
            ->expects($this->exactly(2))
            ->method('getPrettyVersion')
            ->willReturn('dev-develop')
        ;

        $package
            ->expects($this->exactly(0))
            ->method('getInstallationSource')
        ;

        $dataManager->addPackageUsage($package);

        $this->assertFileExists($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);

        $content = file_get_contents($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);
        $this->assertJson($content);
        $this->assertEquals(array(
            'letudiant/foo-bar/dev-develop' => array(
                'installation-source' => SharedPackageDataManager::PACKAGE_INSTALLATION_SOURCE,
                'project-usage'       => array(
                    'letudiant/root-package',
                    'letudiant/root-package2'
                )
            )
        ), json_decode($content, true));
    }

    /**
     * @test
     */
    public function removePackageUsageWithoutData()
    {
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);

        $this->rootPackage
            ->expects($this->once())
            ->method('getName')
            ->willReturn('letudiant/root-package')
        ;

        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->exactly(2))
            ->method('getPrettyName')
            ->willReturn('letudiant/foo-bar')
        ;

        $package
            ->expects($this->exactly(2))
            ->method('getPrettyVersion')
            ->willReturn('dev-develop')
        ;

        $package
            ->expects($this->exactly(0))
            ->method('getInstallationSource')
        ;

        $dataManager->removePackageUsage($package);

        $this->assertFileExists($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);

        $content = file_get_contents($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);
        $this->assertJson($content);
        $this->assertEquals(array(), json_decode($content, true));
    }

    /**
     * @test
     */
    public function removePackageUsageWithData()
    {
        $this->initializePackageData();

        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);

        $this->rootPackage
            ->expects($this->exactly(2))
            ->method('getName')
            ->will($this->onConsecutiveCalls(
                'letudiant/root-package',
                'letudiant/root-package2'
            ))
        ;

        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->exactly(4))
            ->method('getPrettyName')
            ->willReturn('letudiant/foo-bar')
        ;

        $package
            ->expects($this->exactly(4))
            ->method('getPrettyVersion')
            ->willReturn('dev-develop')
        ;

        $package
            ->expects($this->exactly(0))
            ->method('getInstallationSource')
        ;

        // Remove the right package
        $this->initializePackageData();

        $dataManager->removePackageUsage($package);

        $this->assertFileExists($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);

        $content = file_get_contents($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);
        $this->assertJson($content);
        $this->assertEquals(array(), json_decode($content, true));

        // Remove another package, should not remove the initial package
        $this->initializePackageData();

        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);
        $dataManager->removePackageUsage($package);

        $this->assertFileExists($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);

        $content = file_get_contents($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);
        $this->assertJson($content);
        $this->assertEquals(array(
            'letudiant/foo-bar/dev-develop' => array(
                'installation-source' => SharedPackageDataManager::PACKAGE_INSTALLATION_SOURCE,
                'project-usage'       => array(
                    'letudiant/root-package'
                )
            )
        ), json_decode($content, true));
    }

    /**
     * @test
     */
    public function setPackageInstallationSourceWhenNotNull()
    {
        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->once())
            ->method('setInstallationSource')
        ;

        // With already provided installation source
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);
        $dataManager->setPackageInstallationSource($package);
    }

    /**
     * @test
     */
    public function setPackageInstallationSourceWhenNull()
    {
        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->once())
            ->method('setInstallationSource')
            ->with(SharedPackageDataManager::PACKAGE_INSTALLATION_SOURCE)
        ;

        $this->initializePackageData();
        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);
        $dataManager->setPackageInstallationSource($package);
    }

    /**
     * @test
     */
    public function setPackageInstallationSourceWhenNullAndNoData()
    {
        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->once())
            ->method('setInstallationSource')
            ->with(SharedPackageDataManager::PACKAGE_INSTALLATION_SOURCE)
        ;

        $dataManager = new SharedPackageDataManager($this->composer);
        $dataManager->setVendorDir($this->vendorDir);
        $dataManager->setPackageInstallationSource($package);
    }

    /**
     * Initialize fake data file
     */
    protected function initializePackageData()
    {
        file_put_contents($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME, json_encode(array(
            'letudiant/foo-bar/dev-develop' => array(
                'installation-source' => SharedPackageDataManager::PACKAGE_INSTALLATION_SOURCE,
                'project-usage'       => array(
                    'letudiant/root-package'
                )
            )
        )));

        $this->assertFileExists($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME);
    }

    /**
     * Remove the fake data file
     */
    protected function clearPackageData()
    {
        if (is_file($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME)
            && !@unlink($this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME)) {
            throw new \RuntimeException('Cannot delete the file "' . $this->vendorDir . '/' . SharedPackageDataManager::PACKAGE_DATA_FILENAME . '"');
        }
    }

    /**
     * @return PackageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createPackage()
    {
        $package = $this->getMock('Composer\Package\PackageInterface');
        $package
            ->expects($this->once())
            ->method('getPrettyName')
            ->willReturn('letudiant/foo-bar')
        ;

        $package
            ->expects($this->once())
            ->method('getPrettyVersion')
            ->willReturn('dev-develop')
        ;

        return $package;
    }
}
