<?php

class CRUDServiceTest extends Orchestra\Testbench\TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return ['Larapress\CRUD\Providers\PackageServiceProvider'];
    }

    /**
     * Setup migrations & other bootstrap stuff.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }
}
