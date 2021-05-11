<?php


namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\BankGatewayTransaction;

interface IBankingService
{
    /**
     * @param Request            $request
     * @param Cart|integer       $cart
     * @param integer            $gateway_id
     * @param callable|null      $onFailed
     *
     * @return Response
     */
    public function redirectToBankForCart(Request $request, $cart, $gateway_id, $onFailed, $onAlreadyPurchased);

    /**
     * @param Request         $request
     * @param BankGatewayTransaction|integer         $transaction_id
     * @param callable        $onAlreadyPurchased
     * @param callable        $onSuccess
     * @param callable        $onFailed
     *
     * @return Response
     */
    public function verifyBankRequest(Request $request, $transaction, $onAlreadyPurchased, $onSuccess, $onFailed, $onCancel);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $gateway_id
     * @param float $amount
     * @param int $currency
     * @param callback|null $onFailed
     * @return Response
     */
    public function redirectToBankForAmount(Request $request, $gateway_id, $amount, $currency, $onFailed, $onAlreadyPurchased);

}
