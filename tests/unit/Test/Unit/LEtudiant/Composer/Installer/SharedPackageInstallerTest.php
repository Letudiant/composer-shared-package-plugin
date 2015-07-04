<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Unit\LEtudiant\Composer\Installer;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\TestCase;
use LEtudiant\Composer\Data\Package\PackageDataManagerInterface;
use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\Util\SymlinkFilesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\Installer\SharedPackageInstaller
 */
class SharedPackageInstallerTest extends TestCase
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var SharedPackageInstallerConfig
     */
    protected $config;

    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var string
     */
    protected $binDir;

    /**
     * @var string
     */
    protected $symlinkDir;

    /**
     * @var string
     */
    protected $dependenciesDir;

    /**
     * @var DownloadManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $dm;

    /**
     * @var InstalledRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $repository;

    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $io;

    /**
     * @var SymlinkFilesystem
     */
    protected $fs;

    /**
     * @var PackageDataManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $dataManager;


    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->fs = new SymlinkFilesystem();
        $this->composer = new Composer();
        $composerConfig = new Config();

        $this->composer->setConfig($composerConfig);

        $this->vendorDir = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'composer-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);
        $this->binDir = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'composer-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);
        $this->dependenciesDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-dependencies';
        $this->ensureDirectoryExistsAndClear($this->dependenciesDir);
        $this->symlinkDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-vendor-shared';

        $composerConfig->merge(array(
            'config' => array(
                'vendor-dir' => $this->vendorDir,
                'bin-dir'    => $this->binDir,
            ),
        ));

        $this->dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $this->composer->setDownloadManager($this->dm);

        $extraConfig = array(
            SharedPackageInstaller::PACKAGE_TYPE => array(
                'vendor-dir'  => $this->dependenciesDir,
                'symlink-dir' => $this->symlinkDir
            )
        );

        /** @var RootPackage|\PHPUnit_Framework_MockObject_MockObject $package */
        $package = $this->getMock('Composer\Package\RootPackageInterface');
        $package
            ->expects($this->any())
            ->method('getExtra')
            ->willReturn($extraConfig)
        ;
        $this->composer->setPackage($package);

        $this->repository = $this->getMock('Composer\Repository\InstalledRepositoryInterface');
        $this->io = $this->getMock('Composer\IO\IOInterface');

        $this->dataManager = $this->getMockBuilder('LEtudiant\Composer\Data\Package\SharedPackageDataManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $vendorDirParams = explode(DIRECTORY_SEPARATOR, $this->vendorDir);
        $this->config = new SharedPackageInstallerConfig(
            end($vendorDirParams),
            $this->vendorDir,
            $extraConfig
        );
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        $this->fs->removeDirectory($this->vendorDir);
        $this->fs->removeDirectory($this->binDir);
        $this->fs->removeDirectory($this->symlinkDir);
        $this->fs->removeDirectory($this->dependenciesDir);

        parent::tearDown();
    }

    /**
     * @test
     */
    public function testInstallerCreationShouldNotCreateVendorDirectory()
    {
        $this->fs->removeDirectory($this->vendorDir);
        new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);
        $this->assertFileNotExists($this->vendorDir);
    }

    /**
     * @test
     */
    public function testInstallerCreationShouldNotCreateBinDirectory()
    {
        $this->fs->removeDirectory($this->binDir);
        new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);
        $this->assertFileNotExists($this->binDir);
    }

    /**
     * @test
     */
    public function isInstalledDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);
        $package = $this->createDevelopmentPackageMock();
        $this->repository
            ->expects($this->exactly(2))
            ->method('hasPackage')
            ->with($package)
            ->will($this->onConsecutiveCalls(false, true));

        $this->assertFalse($library->isInstalled($this->repository, $package));

        $this->fs->ensureDirectoryExists($library->getInstallPath($package));
        $reflection = new \ReflectionObject($library);
        $method = $reflection->getMethod('createPackageVendorSymlink');
        $method->setAccessible(true);
        $method->invokeArgs($library, array($package));

        $this->assertTrue($library->isInstalled($this->repository, $package));
    }

    /**
     * @test
     */
    public function installDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);
        $package = $this->createDevelopmentPackageMock();

        $this->dm
            ->expects($this->exactly(1))
            ->method('download')
            ->with($package, $this->dependenciesDir . '/letudiant/foo-bar/dev-develop')
        ;

        $this->repository
            ->expects($this->exactly(2))
            ->method('addPackage')
            ->with($package)
        ;

        $this->dataManager
            ->expects($this->exactly(2))
            ->method('addPackageUsage')
            ->willReturn($package)
        ;

        $library->install($this->repository, $package);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');
        $this->assertFileExists($this->symlinkDir . '/letudiant', 'Symlink package prefix dir should be created');
        $this->assertTrue(is_link($this->symlinkDir . '/letudiant/foo-bar'), 'Symlink should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');

        // Install another time with already created directory
        $this->fs->ensureDirectoryExists($library->getInstallPath($package));
        $this->repository
            ->expects($this->once())
            ->method('hasPackage')
            ->with($package)
            ->willReturn(false)
        ;

        $library->install($this->repository, $package);
    }

    /**
     * @test
     *
     * @depends installDevelopment
     */
    public function installDevelopmentWithSymlinkBasePath()
    {
        $symlinkBasePath = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-symlink-base-path';

        $config = $this->createConfigMock(
            $this->dependenciesDir,
            $this->symlinkDir,
            $symlinkBasePath
        );

        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $config);
        $package = $this->createDevelopmentPackageMock();

        $library->install($this->repository, $package);

        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');
        $this->assertFileExists($this->symlinkDir . '/letudiant', 'Symlink package prefix dir should be created');
        $this->assertTrue(is_link($this->symlinkDir . '/letudiant/foo-bar'), 'Symlink should be created');

        $this->assertEquals($symlinkBasePath . '/letudiant/foo-bar/dev-develop', readlink($this->symlinkDir . '/letudiant/foo-bar'), 'Symlink should have a custom base path');
    }

    /**
     * @test
     *
     * @depends installDevelopment
     */
    public function installDevelopmentWithSymlinkBasePathAndTargetDir()
    {
        $symlinkBasePath = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-symlink-base-path';

        $config = $this->createConfigMock(
            $this->dependenciesDir,
            $this->symlinkDir,
            $symlinkBasePath
        );

        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $config);
        $package = $this->createDevelopmentPackageMock();
        $package
            ->expects($this->exactly(4))
            ->method('getTargetDir')
            ->willReturn('target-dir')
        ;

        $library->install($this->repository, $package);

        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');
        $this->assertFileExists($this->symlinkDir . '/letudiant', 'Symlink package prefix dir should be created');
        $this->assertTrue(is_link($this->symlinkDir . '/letudiant/foo-bar'), 'Symlink should be created');

        $this->assertEquals($symlinkBasePath . '/letudiant/foo-bar/dev-develop/target-dir', readlink($this->symlinkDir . '/letudiant/foo-bar'), 'Symlink should have a custom base path');
    }

    /**
     * @test
     *
     * @depends installDevelopment
     */
    public function installDevelopmentAndSymlinkDisabled()
    {
        $config = $this->createConfigMock(
            $this->dependenciesDir,
            $this->symlinkDir,
            null,
            false
        );

        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $config);
        $package = $this->createDevelopmentPackageMock();

        $library->install($this->repository, $package);

        $this->assertFileNotExists($this->symlinkDir, 'Symlink dir should be created');
    }

    /**
     * @test
     *
     * @depends testInstallerCreationShouldNotCreateVendorDirectory
     * @depends testInstallerCreationShouldNotCreateBinDirectory
     */
    public function updateDevelopmentToDevelopment()
    {
        $installer = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);

        $initial = $this->createDevelopmentPackageMock();
        $target  = $this->createDevelopmentPackageMock();

        $initial
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('initial-package'))
        ;

        $this->dm
            ->expects($this->never())
            ->method('download')
        ;

        $this->repository
            ->expects($this->exactly(2))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false))
        ;

        $this->dataManager
            ->expects($this->never())
            ->method('addPackageUsage')
        ;

        $this->dataManager
            ->expects($this->never())
            ->method('removePackageUsage')
        ;

        $installer->update($this->repository, $initial, $target);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');
        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');

        $this->assertTrue(is_link($this->symlinkDir . '/letudiant/foo-bar'));
    }

    /**
     * @test
     */
    public function uninstallDevelopment()
    {
        $this->io
            ->expects($this->once())
            ->method('askConfirmation')
            ->willReturn(true);

        /** @var SymlinkFilesystem|\PHPUnit_Framework_MockObject_MockObject $filesystem */
        $filesystem = $this->getMock('\LEtudiant\Composer\Util\SymlinkFilesystem');
        $filesystem
            ->expects($this->once())
            ->method('removeSymlink')
            ->willReturn(true)
        ;

        $library = new SharedPackageInstaller($this->io, $this->composer, $filesystem, $this->dataManager, $this->config);
        $package = $this->createDevelopmentPackageMock();

        $this->repository
            ->expects($this->exactly(1))
            ->method('hasPackage')
            ->with($package)
            ->will($this->onConsecutiveCalls(true, true))
        ;

        $this->repository
            ->expects($this->once())
            ->method('removePackage')
            ->with($package)
        ;

        $this->dm
            ->expects($this->once())
            ->method('remove')
            ->with($package, $this->dependenciesDir . '/letudiant/foo-bar/dev-develop')
        ;

        $this->dataManager
            ->expects($this->once())
            ->method('removePackageUsage')
            ->with($package)
        ;

        $library->uninstall($this->repository, $package);
    }

    /**
     * @test
     */
    public function getInstallPathDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);
        $package = $this->createDevelopmentPackageMock();
        $package
            ->expects($this->once())
            ->method('getTargetDir')
            ->will($this->returnValue(null));
        $this->assertEquals($this->dependenciesDir . '/letudiant/foo-bar/dev-develop', $library->getInstallPath($package));
    }

    /**
     * @test
     */
    public function getInstallPathWithTargetDirDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);
        $package = $this->createDevelopmentPackageMock();
        $package
            ->expects($this->once())
            ->method('getTargetDir')
            ->will($this->returnValue('Some/Namespace'))
        ;

        $package
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('foo/bar'))
        ;

        $this->assertEquals($this->dependenciesDir . '/letudiant/foo-bar/dev-develop/Some/Namespace', $library->getInstallPath($package));
    }

    /**
     * @test
     */
    public function supports()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager, $this->config);

        $this->assertTrue($library->supports('library'));
        $this->assertTrue($library->supports(SharedPackageInstaller::PACKAGE_TYPE));
    }

    /**
     * @return Package|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createStablePackageMock()
    {
        return $this->getMockBuilder('Composer\Package\Package')
            ->setConstructorArgs(array(md5(mt_rand()), '1.0.0.0', '1.0.0'))
            ->getMock()
        ;
    }

    /**
     * @param string|null $vendorDir
     * @param string|null $symlinkDir
     * @param string|null $symlinkBasePath
     * @param bool        $isSymlinkEnabled
     *
     * @return SharedPackageInstallerConfig|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createConfigMock($vendorDir = null, $symlinkDir = null, $symlinkBasePath = null, $isSymlinkEnabled = true)
    {
        if (null == $vendorDir) {
            $vendorDir = $this->dependenciesDir;
        }

        if (null == $symlinkDir) {
            $symlinkDir = $this->symlinkDir;
        }

        $config = $this->getMockBuilder('LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $config
            ->expects($this->any())
            ->method('getVendorDir')
            ->willReturn($vendorDir)
        ;

        $config
            ->expects($this->any())
            ->method('getSymlinkDir')
            ->willReturn($symlinkDir)
        ;

        $config
            ->expects($this->any())
            ->method('getSymlinkBasePath')
            ->willReturn($symlinkBasePath)
        ;

        $config
            ->expects($this->any())
            ->method('isSymlinkEnabled')
            ->willReturn($isSymlinkEnabled)
        ;

        $config
            ->expects($this->any())
            ->method('getOriginalVendorDir')
            ->with(array(true))
            ->willReturn($this->vendorDir . '/')
        ;

        $config
            ->expects($this->any())
            ->method('getOriginalVendorDir')
            ->with(array(false))
            ->willReturn($this->vendorDir)
        ;

        return $config;
    }

    /**
     * @return Package|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createDevelopmentPackageMock()
    {
        /** @var Package|\PHPUnit_Framework_MockObject_MockObject $package */
        $package = $this->getMockBuilder('Composer\Package\Package')
            ->setConstructorArgs(array(md5(mt_rand()), 'dev-develop', 'dev-develop'))
            ->getMock()
        ;

        $package
            ->expects($this->any())
            ->method('getType')
            ->willReturn(SharedPackageInstaller::PACKAGE_TYPE)
        ;

        $package
            ->expects($this->any())
            ->method('isDev')
            ->willReturn(true)
        ;

        $package
            ->expects($this->any())
            ->method('getPrettyName')
            ->willReturn('letudiant/foo-bar')
        ;

        $package
            ->expects($this->any())
            ->method('getPrettyVersion')
            ->willReturn('dev-develop')
        ;

        $package
            ->expects($this->any())
            ->method('getVersion')
            ->willReturn('dev-develop')
        ;

        $package
            ->expects($this->any())
            ->method('getInstallationSource')
            ->willReturn('source')
        ;

        return $package;
    }
}
