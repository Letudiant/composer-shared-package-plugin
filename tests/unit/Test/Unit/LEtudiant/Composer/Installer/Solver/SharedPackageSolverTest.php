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

use Composer\Package\PackageInterface;
use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\Installer\Solver\SharedPackageSolver;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\Installer\Solver\SharedPackageSolver
 */
class SharedPackageSolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SharedPackageInstallerConfig|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $config;


    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->config = $this->getMockBuilder('LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @test
     */
    public function constructAllShared()
    {
        $this->config
            ->expects($this->once())
            ->method('getPackageList')
            ->willReturn(array(
                '*'
            ))
        ;

        $solver = $this->createSolver();

        $this->assertAttributeEquals(true, 'areAllShared', $solver, 'All packages should be shared');
        $this->assertAttributeCount(0, 'packageCallbacks', $solver, 'Package callbacks should be empty');
    }

    /**
     * @test
     */
    public function constructWithPackageList()
    {
        $this->config
            ->expects($this->once())
            ->method('getPackageList')
            ->willReturn(array(
                'foo/bar',
                'bar/*'
            ))
        ;

        $solver = $this->createSolver();

        $this->assertAttributeEquals(false, 'areAllShared', $solver, 'All packages should not be shared');
        $this->assertAttributeCount(2, 'packageCallbacks', $solver, 'Package callbacks should be filled');

        $callbacks = $this->getObjectAttribute($solver, 'packageCallbacks');
        $this->assertArrayHasKey(0, $callbacks);
        $this->assertInternalType('callable', $callbacks[0]);
        $this->assertInternalType('callable', $callbacks[1]);
    }

    /**
     * @test
     */
    public function constructWhenNull()
    {
        $this->config
            ->expects($this->once())
            ->method('getPackageList')
            ->willReturn(array())
        ;

        $solver = $this->createSolver();

        $this->assertAttributeEquals(false, 'areAllShared', $solver, 'All packages should not be shared');
        $this->assertAttributeCount(0, 'packageCallbacks', $solver, 'Package callbacks should be empty');
    }

    /**
     * @param int    $i
     * @param bool   $expectedValue
     * @param string $packagePrettyName
     *
     * @test
     * @dataProvider createCallbacksDataProvider
     */
    public function createCallbacks($i, $expectedValue, $packagePrettyName)
    {
        $this->config
            ->expects($this->once())
            ->method('getPackageList')
            ->willReturn(array(
                'foo/bar',
                'bar/*'
            ))
        ;

        $solver = $this->createSolver();
        $callbacks = $this->getObjectAttribute($solver, 'packageCallbacks');

        $this->assertEquals($expectedValue, $callbacks[$i]($packagePrettyName));
    }

    /**
     * @return array
     */
    public function createCallbacksDataProvider()
    {
        return array(
            // Raw equality (foo/bar)
            array(0, false, 'foo/bar2'),
            array(0, false, 'foo2/bar2'),
            array(0, false, 'foo2/bar'),
            array(0, false, 'foo/'),
            array(0, false, 'foo'),
            array(0, false, ''),
            array(0, true, 'foo/bar'),

            // Regex equality (bar/*)
            array(1, false, 'foo/bar'),
            array(1, false, 'bar2/foo'),
            array(1, false, 'bar/'),
            array(1, false, 'bar'),
            array(1, true, 'bar/foo'),
            array(1, true, 'bar/foo2'),
            array(1, true, 'bar/foo-2'),
            array(1, true, 'bar/foo_2'),
            array(1, true, 'bar/foO_2'),
        );
    }

    /**
     * @test
     */
    public function isSharedPackageWithAllShared()
    {
        $this->config
            ->expects($this->once())
            ->method('getPackageList')
            ->willReturn(array(
                '*'
            ))
        ;

        $solver = $this->createSolver();

        // False
        $this->assertFalse($solver->isSharedPackage($this->createPackageMock(SharedPackageInstaller::PACKAGE_PRETTY_NAME)));

        // True
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock()));
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock('foo/bar')));
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock('bar/foo')));
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock('unknown/unknown')));
    }

    /**
     * @test
     */
    public function isSharedPackageWithPackageList()
    {
        $this->config
            ->expects($this->once())
            ->method('getPackageList')
            ->willReturn(array(
                'foo/bar',
                'bar/*'
            ))
        ;

        $solver = $this->createSolver();

        // False
        $this->assertFalse($solver->isSharedPackage($this->createPackageMock('unknown/unknown')));

        // True

        // Package with "shared-package" type
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock('unknown/unknown', SharedPackageInstaller::PACKAGE_TYPE)));

        // Packages in the package list
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock('foo/bar')));
        $this->assertTrue($solver->isSharedPackage($this->createPackageMock('bar/foo')));
    }

    /**
     * @param null|string $prettyName
     * @param null|string $type
     *
     * @return PackageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function createPackageMock($prettyName = null, $type = null)
    {
        $package = $this->createMock('Composer\Package\PackageInterface');

        if (null != $prettyName) {
            $package
                ->expects($this->once())
                ->method('getPrettyName')
                ->willReturn($prettyName)
            ;
        }

        if (null != $type) {
            $package
                ->expects($this->once())
                ->method('getType')
                ->willReturn($type)
            ;
        }

        return $package;
    }

    /**
     * @return SharedPackageSolver
     */
    protected function createSolver()
    {
        return new SharedPackageSolver($this->config);
    }
}
