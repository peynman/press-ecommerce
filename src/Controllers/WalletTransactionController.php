<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;

class WalletTransactionController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.wallet_transactions.name'),
            self::class,
            WalletTransactionCRUDProvider::class,
            [
                'any.request_unverified' => [
                    'uses' => '\\'.self::class.'@requestUnverifiedWalletTransaction',
                    'methods' => ['POST'],
                    'url' => config('larapress.ecommerce.routes.wallet_transactions.name').'/request',
                ]
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param IBankingService $service
     * @param Request $request
     * @return mixed
     */
    public function requestUnverifiedWalletTransaction(IBankingService $service, Request $request) {
        return $service->addBalanceForUser(
            Auth::user(),
            $request->get('amount'),
            config('larapress.ecommerce.banking.currency.id'),
            WalletTransaction::TYPE_UNVERIFIED,
            0,
            $request->get('desc')
        );
    }
}
