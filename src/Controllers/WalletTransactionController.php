<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\ECommerce\IECommerceUser;

/**
 * @group Wallet Transaction Management
 */
class WalletTransactionController extends CRUDController
{
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
            $request->get('desc'),
            []
        );
    }
}
