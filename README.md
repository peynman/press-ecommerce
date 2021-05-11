# Larapress Authentication
A package to provide ECommerce & Product management based on Model in Larapress Profiles.

## Dependencies
* Larapress CRUD
* Larapress Reports
* Larapress Profiles

## Install
1. ```composer require peynman/larapress-ecommerce```


## Config
1. ```php artisan vendor:publish --tag=larapress-ecommerce```

## Usage


## Development/Contribution Guid
* create a new laravel project
* add this project as a submodule at path packages/larapress-ecommerce
* use phpunit, phpcs
    * ```vendor/bin/phpunit -c packages/larapress-ecommerce/phpunit.xml```
    * ```vendor/bin/phpcs --standard=packages/larapress-ecommerce/phpcs.xml packages/larapress-ecommerce/```
