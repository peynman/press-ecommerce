<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\BaseCRUDTestTrait;
use Larapress\CRUD\Tests\PackageTestApplication;
use Larapress\ECommerce\CRUD\ProductTypeCRUDProvider;

class ProductTypeCRUDTest extends PackageTestApplication
{
    use BaseCRUDTestTrait;

    public function testCRUDProductType()
    {
        $this->doCRUDCreateTest(
            new ProductTypeCRUDProvider(),
            [
                'name' => 'sample-type',
            ]
        )->assertStatus(400);

        $this->doCRUDCreateTest(
            new ProductTypeCRUDProvider(),
            [
                'name' => 'sample-type',
                'data' => [
                    'title' => 'Sample Type'
                ]
            ]
        )->assertStatus(200);
    }
}
