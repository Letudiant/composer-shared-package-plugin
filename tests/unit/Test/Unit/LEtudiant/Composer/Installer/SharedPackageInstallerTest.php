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
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\TestCase;
use LEtudiant\Composer\Data\Package\PackageDataManagerInterface;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\Installer\Solver\SharedPackageInstallerSolver;
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
     * @var Config
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
        $this->config = new Config();

        $this->composer->setConfig($this->config);

        $this->vendorDir = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'composer-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);
        $this->binDir = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'composer-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);
        $this->dependenciesDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-dependencies';
        $this->ensureDirectoryExistsAndClear($this->dependenciesDir);
        $this->symlinkDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-vendor-shared';

        $this->config->merge(array(
            'config' => array(
                'vendor-dir' => $this->vendorDir,
                'bin-dir'    => $this->binDir,
            ),
        ));

        $this->dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->composer->setDownloadManager($this->dm);

        /** @var RootPackage|\PHPUnit_Framework_MockObject_MockObject $package */
        $package = $this->getMock('Composer\Package\RootPackageInterface');
        $package
            ->expects($this->any())
            ->method('getExtra')
            ->willReturn(array(
                SharedPackageInstaller::PACKAGE_TYPE => array(
                    'vendor-dir'  => $this->dependenciesDir,
                    'symlink-dir' => $this->symlinkDir
                )
            ))
        ;
        $this->composer->setPackage($package);

        $this->repository = $this->getMock('Composer\Repository\InstalledRepositoryInterface');
        $this->io = $this->getMock('Composer\IO\IOInterface');

        $this->dataManager = $this->getMockBuilder('LEtudiant\Composer\Data\Package\SharedPackageDataManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;
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
        new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
        $this->assertFileNotExists($this->vendorDir);
    }

    /**
     * @test
     */
    public function testInstallerCreationShouldNotCreateBinDirectory()
    {
        $this->fs->removeDirectory($this->binDir);
        new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
        $this->assertFileNotExists($this->binDir);
    }

    /**
     * @test
     */
    public function isInstalledDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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

        /** @var RootPackage|\PHPUnit_Framework_MockObject_MockObject $package */
        $package = $this->getMock('Composer\Package\RootPackageInterface');
        $package
            ->expects($this->any())
            ->method('getExtra')
            ->willReturn(array(
                SharedPackageInstaller::PACKAGE_TYPE => array(
                    'vendor-dir'  => $this->dependenciesDir,
                    'symlink-dir' => $this->symlinkDir,
                    'symlink-base-path' => $symlinkBasePath
                )
            ))
        ;
        $this->composer->setPackage($package);

        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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

        /** @var RootPackage|\PHPUnit_Framework_MockObject_MockObject $rootPackage */
        $rootPackage = $this->getMock('Composer\Package\RootPackageInterface');
        $rootPackage
            ->expects($this->any())
            ->method('getExtra')
            ->willReturn(array(
                SharedPackageInstaller::PACKAGE_TYPE => array(
                    'vendor-dir'  => $this->dependenciesDir,
                    'symlink-dir' => $this->symlinkDir,
                    'symlink-base-path' => $symlinkBasePath
                )
            ))
        ;
        $this->composer->setPackage($rootPackage);

        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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
        /** @var RootPackage|\PHPUnit_Framework_MockObject_MockObject $rootPackage */
        $rootPackage = $this->getMock('Composer\Package\RootPackageInterface');
        $rootPackage
            ->expects($this->any())
            ->method('getExtra')
            ->willReturn(array(
                SharedPackageInstaller::PACKAGE_TYPE => array(
                    'vendor-dir'      => $this->dependenciesDir,
                    'symlink-dir'     => $this->symlinkDir,
                    'symlink-enabled' => false
                )
            ))
        ;
        $this->composer->setPackage($rootPackage);

        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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
    public function updateStableToDevelopment()
    {
        $installer = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
        $defaultInstaller = $this->getMockBuilder('Composer\Installer\LibraryInstaller')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $im = new InstallationManager();
        $im->addInstaller(new SharedPackageInstallerSolver($installer, $defaultInstaller));
        $this->composer->setInstallationManager($im);

        $initial = $this->createStablePackageMock();
        $target  = $this->createDevelopmentPackageMock();

        $this->fs->ensureDirectoryExists($installer->getInstallPath($initial));

        $initial
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('initial-package'))
        ;

        $initial
            ->expects($this->any())
            ->method('getTargetDir')
            ->will($this->returnValue('oldtarget'))
        ;

        $initial
            ->expects($this->once())
            ->method('getType')
            ->willReturn('shared-package')
        ;

        $target
            ->expects($this->once())
            ->method('getType')
            ->willReturn('shared-package')
        ;

        $target
            ->expects($this->any())
            ->method('getTargetDir')
            ->will($this->returnValue('newtarget'));

        $this->dm
            ->expects($this->once())
            ->method('download')
            ->with($target, $this->dependenciesDir . '/letudiant/foo-bar/dev-develop/newtarget');

        $this->repository
            ->expects($this->exactly(3))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false, false));

        $installer->update($this->repository, $initial, $target);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');
        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');

        $this->assertFileNotExists($installer->getInstallPath($initial));
        $this->assertTrue(is_link($this->symlinkDir . '/letudiant/foo-bar'));
    }

    /**
     * @test
     *
     * @depends testInstallerCreationShouldNotCreateVendorDirectory
     * @depends testInstallerCreationShouldNotCreateBinDirectory
     */
    public function updateDevelopmentToDevelopment()
    {
        $installer = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);

        $initial = $this->createDevelopmentPackageMock();
        $target  = $this->createDevelopmentPackageMock();

        $initial
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('initial-package'));

        $this->dm
            ->expects($this->never())
            ->method('download');

        $this->repository
            ->expects($this->exactly(2))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false));

        $installer->update($this->repository, $initial, $target);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');
        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');

        $this->assertTrue(is_link($this->symlinkDir . '/letudiant/foo-bar'));
    }

    /**
     * @test
     *
     * @depends testInstallerCreationShouldNotCreateVendorDirectory
     * @depends testInstallerCreationShouldNotCreateBinDirectory
     */
    public function updateDevelopmentToStable()
    {
        $installer = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
        $defaultInstaller = $this->getMockBuilder('Composer\Installer\LibraryInstaller')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $im = new InstallationManager();
        $im->addInstaller(new SharedPackageInstallerSolver($installer, $defaultInstaller));
        $this->composer->setInstallationManager($im);

        $initial = $this->createDevelopmentPackageMock();
        $target  = $this->createStablePackageMock();

        $initial
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('initial-package'))
        ;

        $initial
            ->expects($this->once())
            ->method('getType')
            ->willReturn('shared-package')
        ;

        $target
            ->expects($this->once())
            ->method('getType')
            ->willReturn('shared-package')
        ;

        $target
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('package1'));
        $target
            ->expects($this->any())
            ->method('getTargetDir')
            ->will($this->returnValue('newtarget'));

        $this->repository
            ->expects($this->exactly(1))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false, false));

        $installer->update($this->repository, $initial, $target);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');
        $this->assertFileNotExists($this->symlinkDir, 'Symlink dir should be created');

        $this->assertFalse(is_link($this->symlinkDir . '/letudiant/foo-bar'));
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

        $library = new SharedPackageInstaller($this->io, $this->composer, $filesystem, $this->dataManager);
        $package = $this->createDevelopmentPackageMock();

        $this->repository
            ->expects($this->exactly(1))
            ->method('hasPackage')
            ->with($package)
            ->will($this->onConsecutiveCalls(true, true));

        $this->repository
            ->expects($this->once())
            ->method('removePackage')
            ->with($package);

        $this->dm
            ->expects($this->once())
            ->method('remove')
            ->with($package, $this->dependenciesDir . '/letudiant/foo-bar/dev-develop');

        $library->uninstall($this->repository, $package);
    }

    /**
     * @test
     */
    public function getInstallPathDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);
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
        $library = new SharedPackageInstaller($this->io, $this->composer, $this->fs, $this->dataManager);

        $this->assertFalse($library->supports('foo'));
        $this->assertFalse($library->supports('library'));
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
