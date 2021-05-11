<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\BaseCRUDTestTrait;
use Larapress\CRUD\Tests\PackageTestApplication;
use Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider;

class ProductCategoryCRUDTest extends PackageTestApplication
{
    use BaseCRUDTestTrait;

    public function testCRUDProductCategory()
    {
        $this->doCRUDCreateTest(
            new ProductCategoryCRUDProvider(),
            [
                'name' => 'sample-category',
            ]
        )->assertStatus(400);
        $this->doCRUDCreateTest(
            new ProductCategoryCRUDProvider(),
            [
                'name' => 'sample-category',
                'data' => [
                    'title' => 'sample-category'
                ]
            ]
        )->assertStatus(200);
    }
}
