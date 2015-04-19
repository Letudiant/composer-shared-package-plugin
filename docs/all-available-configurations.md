# Composer - Shared Package Plugin

## All available configurations

All these configuration should set in the `extra` `shared-package`configuration key in your project `composer.json`.

* `vendor-dir` *(required)* : your shared packages sources directory. This folder is used by the project Composer autoloader. Relative or absolute path are allowed.
* `symlink-dir` : your symlinks container directory on your project *(default: vendor-shared)*. Relative or absolute path are allowed.
* `symlink-base-path` : the source base path for all of your symlinks. By default, it's the `vendor-dir` path, but you can override this configuration. It's useful if you use a Virtual Machine and if your dependency directory path is not the same on both machines. Relative or absolute path are allowed. If you choose to set a relative path, it should start from your project root directory (where your project `composer.json` file is located).
* `symlink-enabled` : *(boolean, default: true)* enable or not the symlink directory creation process. Useful if you work directly with the sources directory and you don't want to have the symlink directory in your project.

### Example

``` json
// composer.json (project)
{
    "extra": {
        "shared-package": {
            "vendor-dir": "/var/projects/composer-dependencies",
            "symlink-dir": "symlinks-folder",
            "symlink-base-path": "/home/www/projects/composer-dependencies,
            "symlink-enabled": true
        }
    }
}
```
