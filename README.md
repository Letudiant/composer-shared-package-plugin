# Composer - Shared Package Plugin

[![Code Climate](https://codeclimate.com/github/Letudiant/composer-shared-package-plugin/badges/gpa.svg)](https://codeclimate.com/github/Letudiant/composer-shared-package-plugin)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Letudiant/composer-shared-package-plugin/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Letudiant/composer-shared-package-plugin/?branch=master)
[![Build Status](https://travis-ci.org/Letudiant/composer-shared-package-plugin.svg?branch=master)](https://travis-ci.org/Letudiant/composer-shared-package-plugin)
[![Test Coverage](https://codeclimate.com/github/Letudiant/composer-shared-package-plugin/badges/coverage.svg)](https://codeclimate.com/github/Letudiant/composer-shared-package-plugin)

This composer plugin allows you to share **your selected packages between your projects by creating symlinks**.  
All shared packages will be in the same dedicated directory for all of your projects (ordered by versions) and a symlink directory container will be created on your projects (`vendor-shared` by default).

**This plugin will improve your work process** to avoid to work into the `vendor` folder or to avoid to force you to push your package to work/test it with another project.

* [How it works](#how-it-works)
* [Installation](#installation)
* [Structure generation example](#structure-generation-example)
* [How to use (known issues)](#how-to-use-known-issues)
 * [Update only your own packages](./docs/how-to-use/update-only-your-own-packages.md)
 * [Disable this plugin in development environment (for CI purpose, for example)](./docs/how-to-use/disable-this-plugin-in-development-environment.md)
 * [Work with Satis : increase the Composer speed](./docs/how-to-use/work-with-satis.md)
* [All available configurations](#all-available-configurations)
* [Reporting an issue or a feature request](#reporting-an-issue-or-a-feature-request)
* [ChangeLog](#changelog)
* [Credit](#credit)
* [License](#license)

## How it works

A shared package is flagged by two ways :

* By setting the root project `composer.json` extra configuration `package-list` with the selected package name *(works only with the `>= 2.x` version)*.
* By setting the `composer.json` package `type`  to `shared-package` *(the `<= 1.x` version way, still works on `2.x`)*.



If this composer plugin is required in the root project `composer.json` : the package will be downloaded in the dedicated dependencies directory that you provided and a symlink will be created in the project `vendor-shared` directory *(by default)*.  

This plugin allows you to work with many versions at the same time for a package by creating a sub-directory named by the version of your package *(dev-master, dev-develop, 1.0.x-dev, etc)*.

A `packages.json` file is created in the dependencies sources directory to know which projects use a package version and be able to ask you if you want to delete the version directory during the Composer uninstall process, if no project seems to use it.

## Installation

### Step 1 : edit your root composer.json

Add, to your root project `composer.json`, this require **(in dev only)** :

``` json
// composer.json (project)
{
    "require-dev": {
        "letudiant/composer-shared-package-plugin": "~2.0"
    }
}
```

**Note:** this plugin works fine in production mode, but it has been created for development purpose.

### Step 2 : set your dependencies vendor path

Your dependencies vendor path is the path where all your shared packages will be downloaded. This path should be at the same level (or above) of all your projects.  
If you IDE doesn't handle symlinks, you may use this directory to work on your development packages. Otherwise, you'll be able to work directly on your symlinks with modern IDE (PHP Storm, SublimeText, ...).

Add, to your root project `composer.json`, this extra configuration :

``` json
// composer.json
{
    "extra": {
        "shared-package": {
            "vendor-dir": "/path/to/your/dependencies/directory"
        }
    }
}
```

**Note:** you can pass a relative path  (`foo/bar`) or absolute path (starts with "/" : `/foo/bar`).  
If your path is relative, your symlink directory base path will be relative too.

**Note for VM users:** you can manually override the symlink directory base path with the configuration `symlink-base-path` if your host machine dependencies directory path is not the same as your guest machine, see [all available configurations](./docs/all-available-configurations.md) page for more information.

### Step 3 : select your shared packages

Add, in your own package `composer.json`, which one you want to share between your projects :

``` json
// composer.json
{
    "extra": {
        "shared-package": {
            "vendor-dir": "/path/to/your/dependencies/directory",
            "package-list": [
                "foo/bar",
                "bar/*"
            ]
        }
    }
}
```

**Note:** as you can see, you can pass a wild card `*` to the package name. So, in this example, all packages that starts with `bar/` will be shared.
**Note²:** you can set a package name to `*` to share **all packages**.

### Step 4 : (re)install your dependencies

*If you already have installed your project dependencies, you have to fully delete your `vendor/` directory and your `composer.lock` file.*  
Run the `composer install` command.

You should see a new `vendor-shared` folder with all shared packages symlinks.

### Step 5 : play with require-dev :

You can avoid to have two project `composer.json` by setting your `require` dependencies on a stable version (`~x.x.x`) and work on dev environement with a your working in progress version by setting, in your `require-dev` with your development version, like this :

``` json
// composer.json (project)
{
    "require": {
        "acme/foo-bar": "~1.0"
    },
    "require-dev": {
        "acme/foo-bar": "dev-develop as 1.0"
    }
}
```

Thanks to that, you will be able to work with development version in dev environement and have stable version in production.

**Note:** the alias `* as 1.0` may avoid a Composer version solver error, because this behavior is not handled by default.  
**Note²:** Composer has not been created to work with development version/branch, so when you run a `composer install`, the current package branch `HEAD` commit will flagged in your `composer.lock`.  
So, the next time you'll run this command on dev environement, and if you already have a `composer.lock` file, Composer will checkout the flagged commit and not the new `HEAD` *(if you made new commit)* of your branch : **your shared packages won't be up to date**. To avoid this behavior, please read "[How to use - Update only your own packages](./docs/how-to-use/update-only-your-own-packages.md)".

## Structure generation example

Here, a complete example. Our own shared package is called `acme/foo-bar`.

``` json
// composer.json (project)
{
    "require": {
        "letudiant/composer-shared-package-plugin": "~1.0",
        "symfony/console": "~2.6",
        "acme/foo-bar": "~1.0"
    },
    "require-dev": {
        "acme/foo-bar": "dev-develop as 1.0"
    },
    "extra": {
        "shared-package": {
            "vendor-dir": "../composer-dependencies",
            "package-list": [
                "acme/foo-bar"
            ]
        }
    }
}
```

With this `composer.json`, the structure will look like :

``` bash
|-- packages.json
|-- dependencies/
|   +-- acme/
|       +-- foo-bar/
|           +-- dev-develop/
|               |-- src/
|           |-- composer.json
|           +-- ...
+-- project/
+-- src/
+-- vendor/
|   +-- symfony/
|       +-- console/
|           +-- ...
|-- vendor-shared/
|   +-- acme/
|       +-- foo-bar/ (symlink to "../../../dependencies/acme/foo-bar/dev-develop/")
+-- ...
```

## How to use (and known issues)

This plugin implement a new behavior which is not handled by Composer, so there are a few known issues. Here, the way to fix them :

* [Update only your own packages](./docs/how-to-use/update-only-your-own-packages.md)
* [Disable this plugin in development environment (for CI purpose, for example)](./docs/how-to-use/disable-this-plugin-in-development-environment.md)
* [Work with Satis : increase the Composer speed](./docs/how-to-use/work-with-satis.md)


## All available configurations

See the [all available configurations documentation](./docs/all-available-configurations.md).

## Reporting an issue or a feature request

Feel free to open an issue, fork this project or suggest an awesome new feature in [the issue tracker](https://github.com/Letudiant/composer-shared-package-plugin/issues).

## ChangeLog

### 2.0.0 :

* Implement the possibility to choice each package you want to share with the configuration `package-list` - [More information](./docs/all-available-configurations.md).
* Delete conditions on stable/dev version. Now a shared package is shared on stable version too (tag).

### 1.2.0 :

* Rewrite installer, the installer choice process is now in a dedicated class.
* Implement new `symlink-enabled` configuration to allow to enable/disable the symlink creation process - [More information](./docs/all-available-configurations.md).

### 1.1.0 :

* Implement new `symlink-base-path` configuration, as suggested by [philbates35](https://github.com/philbates35), to allow VM users to override the symlink directory base path, see [issue](https://github.com/Letudiant/composer-shared-package-plugin/issues/1) - [More information](./docs/all-available-configurations.md).

## Credit

![L'Étudiant](http://www.letudiant.fr/etucmsEtuPlugin/images/header/logo.png)

This plugin project is maintained by **[L'Etudiant](https://github.com/Letudiant)**.  
The Composer project is maintained by Nils Adermann & Jordi Boggiano, see https://github.com/composer/composer#authors for more information.

## License

This plugin is licensed under MIT license, see the [LICENSE file](./LICENSE) for more information.  
You can also read the [Composer license](https://github.com/composer/composer/blob/master/LICENSE) for more information.
