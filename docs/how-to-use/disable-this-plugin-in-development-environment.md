# Composer - Shared Package Plugin

## How to use

### Disable this plugin in development environment (for CI purpose, for example)

Just add the `--no-plugins` flag to your `install/update` command.  
Composer will disable all plugins, including this one.

`composer install --no-plugins`

If you run this command on a Continuous Integration, don't forget to [update your own packages](./update-only-your-own-packages.md) after.

### Next

See [Work with Satis : increase the Composer speed](./work-with-satis.md).
