<?php

namespace Larapress\ECommerce\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Larapress\Auth\Signin\ISigninService;
use Larapress\CRUD\Commands\CRUDPermissionsCommands;
use Tests\TestCase;
use Larapress\Profiles\IProfileUser;
use Larapress\CRUD\ICRUDUser;
use Larapress\CRUD\Models\Permission;
use Larapress\CRUD\Services\IPermissionsService;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\Domain;
use Tymon\JWTAuth\Facades\JWTAuth;
use Larapress\ECommerce\BaseECommerceUser;

class CartPurchaseTest extends \Orchestra\Testbench\TestCase {
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testCartPurchaseSuccessCustomerPipelineTest()
    {
        /** @var BaseECommerceUser|IProfileUser|ICRUDUser */
        $user = User::find(1);

        /** @var ISigninService */
        $authService = app(ISigninService::class);
        $response = $authService->signinUser("tester", "tester");
        $token = $response['tokens']['api'];

        // $this->addProductType($token, 'SampleType1');
        // $this->addProduct($token, 'Sample Product 1', 1000000);
        // $this->addProduct($token, 'Sample Product 2', 1300000);
        // $this->addProduct($token, 'Sample Product 3', 300000);
        // $this->addProduct($token, 'Sample Product 4', 800000);
        // $this->addProduct($token, 'Sample Product 5', 560000);

        /** @var IBankingService */
        $bankingService = app(IBankingService::class);

        $this->withHeaders([
            'HTTP_Authorization: Bearer '.$token,
        ])->json('POST', '/api/me/current-cart/add', [
            'product_id' => 1,
        ]);

        $this->withHeaders([
            'HTTP_Authorization: Bearer '.$token,
        ])->json('POST', '/api/me/current-cart/add', [
            'product_id' => 2,
        ]);

        $bankingService->addBalanceForUser($user, 130000, 1, WalletTransaction::TYPE_VIRTUAL_MONEY, WalletTransaction::FLAGS_REGISTRATION_GIFT, '');
        $cart = $bankingService->getPurchasingCart($user, 1);

        $request = new Request();
        $bankingService->markCartPurchased($request, $cart);

        $this->assertEquals(3, $user->wallet()->count());
    }


    protected function addProductType($token, $typename) {
        return $this->withHeaders([
            'HTTP_Authorization: Bearer '.$token,
        ])->json('POST', '/api/product-types', [
            'author_id' => 1,
            'name' => $typename,
            'data' => [
                'title' => $typename,
            ]
        ])->assertJsonStructure([
            'id',
        ]);
    }
    protected function addProduct($token, $name, $price) {
        return $this->withHeaders([
            'HTTP_Authorization: Bearer '.$token,
        ])->json('POST', '/api/products', [
            'author_id' => 1,
            'name' => $name,
            'data' => [
                'title' => $name,
                'pricing' => [
                    [
                        'amount' => $price,
                        'currency' => 1,
                    ]
                ]
            ],
            'types' => [
                [
                    'id' => 1,
                ]
            ],
        ])->assertJsonStructure([
            'name',
            'data' => [
                'title',
                'pricing'
            ],
            'id',
            'types',
        ]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            'Larapress\CRUD\Providers\PackageServiceProvider',
            'Larapress\Profiles\Providers\PackageServiceProvider',
            'Larapress\Reports\Providers\PackageServiceProvider',
            'Larapress\Auth\Providers\PackageServiceProvider',
            'Larapress\ECommerce\Providers\PackageServiceProvider',
        ];
    }

    protected function getBasePath()
    {
        return \realPath(__DIR__ . '/../../../../');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app->useStoragePath(realpath(__DIR__.'/../../../../storage') );
    }

    /**
     * Setup migrations & other bootstrap stuff.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // $this->loadLaravelMigrations();
        // $this->artisan('migrate')->run();

        $service = app(IPermissionsService::class);
        $service->forEachRegisteredProviderClass(function($meta_data_class) {
            /** @var IPermissionsMetadata $instance */
            $instance = new $meta_data_class();
            $all_verbs = $instance->getPermissionVerbs();
            foreach ($all_verbs as $verb_name) {
                /* @var Permission $model */
                Permission::firstOrCreate([
                    'name' => $instance->getPermissionObjectName(),
                    'verb' => $verb_name,
                ]);
            }
        });
        $service->updateSuperRole();
        CRUDPermissionsCommands::updateSuperUserWithData([
            'name' => 'tester',
            'password' => 'tester',
        ]);

        // Domain::create([
        //     'domain' => 'localhost',
        //     'ips' => '127.0.0.1',
        // ]);
        // $user->domains()->attach(1, [
        //     'flags' => 7
        // ]);

        DB::table('wallet_transactions')->where('user_id', 1)->delete();
        DB::table('carts_products_pivot')->whereIn('cart_id', DB::table('carts')->where('customer_id', 1)->select('id')->pluck('id'))->delete();
        DB::table('carts')->where('customer_id', 1)->delete();
    }
}
