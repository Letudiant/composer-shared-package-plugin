<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Unit\LEtudiant\Composer\Installer\Solver;

use Composer\Installer\LibraryInstaller;
use Composer\Package\Package;
use Composer\Repository\InstalledRepositoryInterface;
use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\Config\SharedPackageSolver;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\Installer\Solver\SharedPackageInstallerSolver;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\Installer\Solver\SharedPackageInstallerSolver
 */
class SharedPackageInstallerSolverStableTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LibraryInstaller|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $installer;

    /**
     * @var InstalledRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $repository;


    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->installer = $this->getMockBuilder('\Composer\Installer\LibraryInstaller')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->repository = $this->getMock('\Composer\Repository\InstalledRepositoryInterface');
    }

    /**
     * @test
     */
    public function getInstallPath()
    {
        $package = $this->createPackageMock();

        $this->installer
            ->expects($this->once())
            ->method('getInstallPath')
            ->with($package)
        ;

        $this->createSolver()->getInstallPath($package);
    }

    /**
     * @test
     */
    public function install()
    {
        $package = $this->createPackageMock();

        $this->installer
            ->expects($this->once())
            ->method('install')
            ->with($this->repository, $package)
        ;

        $this->createSolver()->install($this->repository, $package);
    }

    /**
     * @test
     */
    public function isInstalled()
    {
        $package = $this->createPackageMock();

        $this->installer
            ->expects($this->once())
            ->method('isInstalled')
            ->with($this->repository, $package)
        ;

        $this->createSolver()->isInstalled($this->repository, $package);
    }

    /**
     * @test
     */
    public function update()
    {
        $initial = $this->createPackageMock();
        $target = $this->createPackageMock();

        $this->installer
            ->expects($this->once())
            ->method('update')
            ->with($this->repository, $initial, $target)
        ;

        $this->createSolver()->update($this->repository, $initial, $target);
    }

    /**
     * @test
     */
    public function uninstall()
    {
        $package = $this->createPackageMock();

        $this->installer
            ->expects($this->once())
            ->method('uninstall')
            ->with($this->repository, $package)
        ;

        $this->createSolver()->uninstall($this->repository, $package);
    }

    /**
     * @test
     */
    public function supports()
    {
        $this->assertTrue($this->createSolver()->supports('library'));
    }

    /**
     * @return SharedPackageInstallerSolver
     */
    protected function createSolver()
    {
        /** @var SharedPackageInstaller|\PHPUnit_Framework_MockObject_MockObject $symlinkInstaller */
        $symlinkInstaller = $this->getMockBuilder('\LEtudiant\Composer\Installer\SharedPackageInstaller')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $config = new SharedPackageInstallerConfig('foo', 'bar', array(
            SharedPackageInstaller::PACKAGE_TYPE => array(
                'vendor-dir' => 'foo'
            )
        ));

        return new SharedPackageInstallerSolver(new SharedPackageSolver($config), $symlinkInstaller, $this->installer);
    }

    /**
     * @return Package|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function createPackageMock()
    {
        return $this->getMockBuilder('Composer\Package\Package')
            ->setConstructorArgs(array(md5(mt_rand()), '1.0.0.0', '1.0.0'))
            ->getMock()
        ;
    }
}
