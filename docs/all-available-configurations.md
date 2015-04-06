# Composer - Shared Package Plugin

## All available configurations

All these configuration should set in the `extra` `shared-package`configuration key in your project `composer.json`.

* `vendor-dir` *(required)* : your shared packages sources directory. This folder is used by the project Composer autoloader. Relative or absolute path are allowed.
* `symlink-dir` : your symlinks container directory on your project *(default: vendor-shared)*. Relative or absolute path are allowed.

### Example

``` json
{
    // composer.json (project)
    {
        "extra": {
            "shared-package": {
                "vendor-dir": "/var/projects/composer-dependencies",
                "symlink-dir": "symlinks-folder"
            }
        }
    }
}
```
