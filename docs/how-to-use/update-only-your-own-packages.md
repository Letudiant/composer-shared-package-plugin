# Composer - Shared Package Plugin

## How to use

### Update only your own packages

You can easily update your own package, without updating stable packages. Indeed, Composer allows the wildcard `*` with the `update` command. Imagine that your packages are prefixed by `acme` (`acme/cache`, `acme/awesome-component`, ...), you should execute this command :

`composer update "acme*"`

If you have packages without the same prefix, no worry, just execute this command :

`composer update "acme*" "other-prefix*" "yet-another-prefix*"`

So, when you run a `composer install`, don't forget to run the `composer update "prefix*"` command  after to update your own shared packages because the `composer.lock` keeps only the installation commit reference *(after the first `composer install` command)*, and not your branches `HEAD`.

### Next

See [Disable this plugin in development environment (for CI purpose, for example)](./disable-this-plugin-in-development-environment.md).
