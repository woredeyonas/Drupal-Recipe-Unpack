# Drupal Recipe Unpack Composer Plugin
This composer plugin allows the extraction of a packages dependencies into the project root composer and lock files for the sole purpose of implementation within Drupal recipes.


## Usage

### Prerequisite

For this plugin to work properly, Drupal core must have the recipe functionality set and ready. The recipe functionality can be added through [this][recipe-patch] patch. For more information regarding applying the patch and any updates, refer to the [Drupal recipe project page][drupal-recipe-project].

### Installation

Installation can be done with [Composer][composer], by requiring this package as a development dependency:

1. Add the git repos under the projects repositories list as follows:

```bash
{
    "type": "vcs",
    "url": "https://gitlab.ewdev.ca/yonas.legesse/drupal-recipe-unpack.git"
}
```
2. Run the install of the package and the recipe.
```bash
composer require ewcomposer/unpack:dev-master
```
When using Composer 2.2 or higher, Composer will [ask for your permission](https://blog.packagist.com/composer-2-2/#more-secure-plugin-execution) to allow this plugin to execute code. For this plugin to be functional, permission needs to be granted.

When permission has been granted, the following snippet will automatically be added to your `composer.json` file by Composer:
```json
{
    "config": {
        "allow-plugins": {
            "ewcomposer/unpack": true,
        }
    }
}
```

When using Composer < 2.2, you can add the permission flag ahead of the upgrade to Composer 2.2, by running:
```bash
composer config allow-plugins.ewcomposer/unpack true
```
### Running the command

Once a drupal recipe has been applied to your project, simply run the command below and the dependecies defined in the composer.json file of the recipe will be unpacked and updated to the projects root composer and lock files.
```bash
# For a drupal recipe with name drupal_recipe/startup_recipe
composer unpack drupal_recipe/startup_recipe
```

### Compatibility

This plugin is compatible with:

- PHP **7.x** and **8.x**
- [Composer][composer] **ˆ1.8** and **2.x**

[composer]: https://getcomposer.org/
[recipe-patch]: https://git.drupalcode.org/project/distributions_recipes/-/raw/patch/recipe.patch
[drupal-recipe-project]: https://www.drupal.org/project/distributions_recipes

## Roadmap
The current feature available allows a default unpacking of a packages dependencies into the project root composer file. However, the full list of intended features are listed below:

1. Have an **unpack** command that copies all requirements of a package into the project composer.json
2. A configuration option, set in composer.json, that specifies which package types should be auto-unpacked i.e. unpacked when they're required or updated. Sample:
```bash
{
    "config": {
        "drupal-recipe": {
            "auto-unpack": true,
        }
    }
}
```
3. A configuration option, set in composer.json, that specifies which packages should be recursively unpacked. Sample:
```bash
composer unpack --recursive drupal-recipe/RECIPE
```
4. A configuration option, that sets the logic of removing (or not) the recipe requirement after unpacking. Sample:
```bash
{
    "config": {
        "drupal-recipe": {
            "unpack-remove-recipe": true,
        }
    }
}
```