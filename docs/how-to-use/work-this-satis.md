# Composer - Shared Package Plugin

## How to use

### Work with Satis : increase Composer speed

As we know, Composer isn't the fastest tool in the world. But it does its job well.  
Unfortunately, **with custom repositories**, Composer is very slow because it reads each repository `composer.json` file (run a `composer update -v`, you'll see).  
The only way to increase the Composer speed, is to install a proxy to host our custom repositories : Satis can do this, and it's open-source.

For more information about the Satis installation/configuration, please read [the official documentation](https://getcomposer.org/doc/articles/handling-private-packages-with-satis.md#satis).

With 10 customs repositories, Composer took about 2/3 minutes to proceed the `update` command and only ~10 seconds after than Satis was installed.

### Next

See [Update only your own packages](./update-only-your-own-packages.md).
