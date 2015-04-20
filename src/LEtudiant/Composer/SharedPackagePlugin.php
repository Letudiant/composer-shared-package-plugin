<?php

/*
 * This file is part of the "Composer Shared Package Plugin" package.
 *
 * https://github.com/Letudiant/composer-shared-package-plugin
 *
 * For the full license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LEtudiant\Composer;

use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use LEtudiant\Composer\Data\Package\SharedPackageDataManager;
use LEtudiant\Composer\Installer\SharedPackageInstaller;
use LEtudiant\Composer\Installer\Solver\SharedPackageInstallerSolver;
use LEtudiant\Composer\Util\SymlinkFilesystem;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@gmail.com>
 */
class SharedPackagePlugin implements PluginInterface
{
    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new SharedPackageInstallerSolver(
            new SharedPackageInstaller(
                $io,
                $composer,
                new SymlinkFilesystem(),
                new SharedPackageDataManager($composer)
            ),
            new LibraryInstaller($io, $composer)
        );
        $composer->getInstallationManager()->addInstaller($installer);
    }
}
