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
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->fs = new SymlinkFilesystem();
        $this->composer = new Composer();
        $this->config = new Config();

        $this->composer->setConfig($this->config);

        $this->vendorDir = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'composer-test-vendor';
        $this->ensureDirectoryExistsAndClear($this->vendorDir);
        $this->binDir = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'composer-test-bin';
        $this->ensureDirectoryExistsAndClear($this->binDir);
        $this->symlinkDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-vendor-shared';
        $this->ensureDirectoryExistsAndClear($this->symlinkDir);
        $this->dependenciesDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-dependencies';
        $this->ensureDirectoryExistsAndClear($this->dependenciesDir);

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
    }

    /**
     * @test
     */
    public function testInstallerCreationShouldNotCreateVendorDirectory()
    {
        $this->fs->removeDirectory($this->vendorDir);
        new SharedPackageInstaller($this->io, $this->composer);
        $this->assertFileNotExists($this->vendorDir);
    }

    /**
     * @test
     */
    public function testInstallerCreationShouldNotCreateBinDirectory()
    {
        $this->fs->removeDirectory($this->binDir);
        new SharedPackageInstaller($this->io, $this->composer);
        $this->assertFileNotExists($this->binDir);
    }

    /**
     * @test
     */
    public function isInstalledStable()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
        $package = $this->createStablePackageMock();
        $this->repository
            ->expects($this->exactly(2))
            ->method('hasPackage')
            ->with($package)
            ->will($this->onConsecutiveCalls(true, false));

        $this->assertTrue($library->isInstalled($this->repository, $package));
        $this->assertFalse($library->isInstalled($this->repository, $package));
    }

    /**
     * @test
     */
    public function isInstalledDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
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
     *
     * @depends testInstallerCreationShouldNotCreateVendorDirectory
     * @depends testInstallerCreationShouldNotCreateBinDirectory
     */
    public function installStable()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
        $package = $this->createStablePackageMock();
        $package
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('some/package'));

        $this->dm
            ->expects($this->once())
            ->method('download')
            ->with($package, $this->vendorDir . '/some/package');

        $this->repository
            ->expects($this->once())
            ->method('addPackage')
            ->with($package);

        $library->install($this->repository, $package);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
    }

    /**
     * @test
     *
     * @depends installStable
     */
    public function installDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
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

        $library = new SharedPackageInstaller($this->io, $this->composer);
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

        $library = new SharedPackageInstaller($this->io, $this->composer);
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
     * @depends testInstallerCreationShouldNotCreateVendorDirectory
     * @depends testInstallerCreationShouldNotCreateBinDirectory
     */
    public function updateStableToStable()
    {
        $filesystem = $this->getMockBuilder('LEtudiant\Composer\Util\SymlinkFilesystem')
            ->getMock();
        $filesystem
            ->expects($this->once())
            ->method('rename')
            ->with($this->vendorDir.'/package1/oldtarget', $this->vendorDir.'/package1/newtarget');
        $initial = $this->createStablePackageMock();
        $target  = $this->createStablePackageMock();
        $initial
            ->expects($this->once())
            ->method('getPrettyName')
            ->will($this->returnValue('package1'));
        $initial
            ->expects($this->once())
            ->method('getTargetDir')
            ->will($this->returnValue('oldtarget'));
        $target
            ->expects($this->once())
            ->method('getPrettyName')
            ->will($this->returnValue('package1'));
        $target
            ->expects($this->once())
            ->method('getTargetDir')
            ->will($this->returnValue('newtarget'));
        $this->repository
            ->expects($this->exactly(3))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, false, false));
        $this->dm
            ->expects($this->once())
            ->method('update')
            ->with($initial, $target, $this->vendorDir.'/package1/newtarget');
        $this->repository
            ->expects($this->once())
            ->method('removePackage')
            ->with($initial);
        $this->repository
            ->expects($this->once())
            ->method('addPackage')
            ->with($target);
        $library = new SharedPackageInstaller($this->io, $this->composer, $filesystem);
        $library->update($this->repository, $initial, $target);
        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->setExpectedException('InvalidArgumentException');
        $library->update($this->repository, $initial, $target);
    }

    /**
     * @test
     *
     * @depends testInstallerCreationShouldNotCreateVendorDirectory
     * @depends testInstallerCreationShouldNotCreateBinDirectory
     */
    public function updateStableToDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);

        $initial = $this->createStablePackageMock();
        $target  = $this->createDevelopmentPackageMock();

        $this->fs->ensureDirectoryExists($library->getInstallPath($initial));

        $initial
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('initial-package'));
        $initial
            ->expects($this->any())
            ->method('getTargetDir')
            ->will($this->returnValue('oldtarget'));
        $target
            ->expects($this->any())
            ->method('getTargetDir')
            ->will($this->returnValue('newtarget'));

        $this->dm
            ->expects($this->once())
            ->method('download')
            ->with($target, $this->dependenciesDir . '/letudiant/foo-bar/dev-develop/newtarget');

        $this->repository
            ->expects($this->exactly(4))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false, false));

        $library->update($this->repository, $initial, $target);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');
        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');

        $this->assertFileNotExists($library->getInstallPath($initial));
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
        $library = new SharedPackageInstaller($this->io, $this->composer);

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
            ->expects($this->exactly(3))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false));

        $library->update($this->repository, $initial, $target);

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
        $library = new SharedPackageInstaller($this->io, $this->composer);

        $initial = $this->createDevelopmentPackageMock();
        $target  = $this->createStablePackageMock();

        $initial
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('initial-package'));

        $target
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('package1'));
        $target
            ->expects($this->any())
            ->method('getTargetDir')
            ->will($this->returnValue('newtarget'));

        $this->dm
            ->expects($this->once())
            ->method('download')
            ->with($target, $this->vendorDir . '/package1/newtarget');

        $this->repository
            ->expects($this->exactly(4))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(true, true, false, false));

        $library->update($this->repository, $initial, $target);

        $this->assertFileExists($this->vendorDir, 'Vendor dir should be created');
        $this->assertFileExists($this->binDir, 'Bin dir should be created');
        $this->assertFileExists($this->dependenciesDir, 'Dependencies dir should be created');
        $this->assertFileExists($this->symlinkDir, 'Symlink dir should be created');

        $this->assertFalse(is_link($this->symlinkDir . '/letudiant/foo-bar'));
    }

    /**
     * @test
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Package is not installed : letudiant/foo-bar
     */
    public function updatePackageNotFoundException()
    {
        $initial = $this->createDevelopmentPackageMock();
        $target = $this->createDevelopmentPackageMock();

        $this->repository
            ->expects($this->exactly(1))
            ->method('hasPackage')
            ->will($this->onConsecutiveCalls(false));

        $library = new SharedPackageInstaller($this->io, $this->composer);
        $library->update($this->repository, $initial, $target);
    }

    /**
     * @test
     */
    public function uninstallStable()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
        $package = $this->createStablePackageMock();
        $package
            ->expects($this->any())
            ->method('getPrettyName')
            ->will($this->returnValue('pkg'));
        $this->repository
            ->expects($this->exactly(2))
            ->method('hasPackage')
            ->with($package)
            ->will($this->onConsecutiveCalls(true, false));
        $this->dm
            ->expects($this->once())
            ->method('remove')
            ->with($package, $this->vendorDir.'/pkg');
        $this->repository
            ->expects($this->once())
            ->method('removePackage')
            ->with($package);

        $library->uninstall($this->repository, $package);
        $this->setExpectedException('InvalidArgumentException');
        $library->uninstall($this->repository, $package);
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

        $filesystem = $this->getMock('\LEtudiant\Composer\Util\SymlinkFilesystem');
        $filesystem
            ->expects($this->once())
            ->method('removeSymlink')
            ->willReturn(true)
        ;

        $library = new SharedPackageInstaller($this->io, $this->composer, $filesystem);
        $package = $this->createDevelopmentPackageMock();

        $this->repository
            ->expects($this->exactly(2))
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
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Package is not installed : letudiant/foo-bar
     */
    public function uninstallNotFoundPackageException()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
        $package = $this->createDevelopmentPackageMock();

        $this->repository
            ->expects($this->once())
            ->method('hasPackage')
            ->with($package)
            ->willReturn(false);

        $library->uninstall($this->repository, $package);
    }

    /**
     * @test
     */
    public function getInstallPathStable()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
        $package = $this->createStablePackageMock();
        $package
            ->expects($this->once())
            ->method('getTargetDir')
            ->will($this->returnValue(null));
        $this->assertEquals($this->vendorDir . '/' . $package->getName(), $library->getInstallPath($package));
    }

    /**
     * @test
     */
    public function getInstallPathDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
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
    public function getInstallPathWithTargetDirStable()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
        $package = $this->createStablePackageMock();
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

        $this->assertEquals($this->vendorDir . '/' . $package->getPrettyName() . '/Some/Namespace', $library->getInstallPath($package));
    }

    /**
     * @test
     */
    public function getInstallPathWithTargetDirDevelopment()
    {
        $library = new SharedPackageInstaller($this->io, $this->composer);
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
        $library = new SharedPackageInstaller($this->io, $this->composer);

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
