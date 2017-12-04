<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\Unit\LEtudiant\Composer\Util;

use LEtudiant\Composer\Util\SymlinkFilesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 *
 * @covers \LEtudiant\Composer\Util\SymlinkFilesystem
 */
class SymlinkFilesystemTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var string
     */
    protected $testDir;


    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir() . '/composer-filesystem-test';
        mkdir($this->testDir);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        if (is_link($this->testDir . '/foo')) {
            unlink($this->testDir . '/foo');
        }

        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
    }

    /**
     * @test
     */
    public function ensureSymlinkExistsWhenExists()
    {
        symlink(sys_get_temp_dir(), $this->testDir . '/foo');

        $filesystem = new SymlinkFilesystem();
        $this->assertFalse($filesystem->ensureSymlinkExists(sys_get_temp_dir(), $this->testDir . '/foo'));
    }

    /**
     * @test
     */
    public function ensureSymlinkExistsWhenNotExists()
    {
        $filesystem = new SymlinkFilesystem();
        $this->assertTrue($filesystem->ensureSymlinkExists(sys_get_temp_dir(), $this->testDir . '/foo'));
        $this->assertFileExists($this->testDir . '/foo');
    }

    /**
     * @test
     */
    public function removeSymlinkWhenExists()
    {
        symlink(sys_get_temp_dir(), $this->testDir . '/foo');

        $filesystem = new SymlinkFilesystem();
        $this->assertTrue($filesystem->removeSymlink($this->testDir . '/foo'));
        $this->assertFileNotExists($this->testDir . '/foo');
    }

    /**
     * @test
     */
    public function removeSymlinkWhenNotExists()
    {
        $filesystem = new SymlinkFilesystem();
        $this->assertFalse($filesystem->removeSymlink($this->testDir . '/foo'));
    }

    /**
     * @test
     */
    public function removeEmptyDirectoryWhenExists()
    {
        $filesystem = new SymlinkFilesystem();
        $this->assertTrue($filesystem->removeEmptyDirectory($this->testDir));
        $this->assertFileNotExists($this->testDir);
    }

    /**
     * @test
     */
    public function removeEmptyDirectoryWhenNotEmpty()
    {
        symlink(sys_get_temp_dir(), $this->testDir . '/foo');

        $filesystem = new SymlinkFilesystem();
        $this->assertFalse($filesystem->removeEmptyDirectory($this->testDir));
        $this->assertFileExists($this->testDir);
    }

    /**
     * @test
     */
    public function removeEmptyDirectoryWhenNotExists()
    {
        $filesystem = new SymlinkFilesystem();
        $this->assertFalse($filesystem->removeEmptyDirectory($this->testDir . '/bar'));
    }
}
