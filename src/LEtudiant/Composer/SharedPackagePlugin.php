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
use Composer\Factory;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use LEtudiant\Composer\Data\Package\SharedPackageDataManager;
use LEtudiant\Composer\Installer\Config\SharedPackageInstallerConfig;
use LEtudiant\Composer\Installer\Solver\SharedPackageSolver;
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
        $extraConfigs = $composer->getPackage()->getExtra();
        $requires = $composer->getPackage()->getRequires();

        // If plugin is only installed globally, merge extra.
        if (!isset($requires['letudiant/composer-shared-package-plugin'])) {

            $factory = new Factory();
            $globalComposer = $factory->createGlobal($io);
            $globalComposerExtra = $globalComposer->getPackage()->getExtra();

            if (isset($globalComposerExtra['shared-package'])) {
                $extraConfigs = array_merge_recursive($extraConfigs, array('shared-package' => $globalComposerExtra['shared-package']));
                $composer->getPackage()->setExtra($extraConfigs);
            }
        }

        // Only activate if we have a vendor-dir set.
        if (isset($extraConfigs[SharedPackageInstaller::PACKAGE_TYPE]['vendor-dir'])) {

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
