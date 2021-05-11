<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\ECommerce\IECommerceUser;

/**
 * Standard CRUD Controller for Wallet Transaction resource.
 *
 * @group Wallet Transaction Management
 */
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
     * Issue unverified wallet transaction
     *
     * @return Response
     */
    public function requestUnverifiedWalletTransaction(IWalletService $service, Request $request)
    {
        /** @var IECommerceUser */
        $user = Auth::user();
        return $service->addBalanceForUser(
            $user,
            $request->get('amount'),
            config('larapress.ecommerce.banking.currency.id'),
            WalletTransaction::TYPE_UNVERIFIED,
            0,
            $request->get('desc')
        );
    }
}
