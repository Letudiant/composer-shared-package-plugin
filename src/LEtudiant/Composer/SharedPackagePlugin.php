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
use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\Config\SharedPackageSolver;
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
        $config = $this->setConfig($composer);

        $composer->getInstallationManager()->addInstaller(new SharedPackageInstallerSolver(
            new SharedPackageSolver($config),
            new SharedPackageInstaller(
                $io,
                $composer,
                new SymlinkFilesystem(),
                new SharedPackageDataManager($composer),
                $config
            ),
            new LibraryInstaller($io, $composer)
        ));
    }

    /**
     * @param Composer $composer
     *
     * @return SharedPackageInstallerConfig
     */
    protected function setConfig(Composer $composer)
    {
        return new SharedPackageInstallerConfig(
            $composer->getConfig()->get('vendor-dir'),
            $composer->getConfig()->get('vendor-dir', 1),
            $composer->getPackage()->getExtra()
        );
    }
}
