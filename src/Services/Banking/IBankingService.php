<?php


namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\ECommerce\Models\BankGatewayTransaction;

interface IBankingService
{
    /**
     * @param BankRedirectRequest   $request
     * @param Cart|int $cart
     * @param callable  $onFailed
     * @param callable  $onAlreadyPurchased
     *
     * @return Response
     */
    public function redirectToBankForCart(BankRedirectRequest $request, $cart, $onFailed, $onAlreadyPurchased);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param callback|null $onFailed
     * @param callback|null $onAlreadyPurchased
     *
     * @return Response
     */
    public function redirectToBankForAmount(BankRedirectRequest $request, $onFailed, $onAlreadyPurchased);

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
     * @param string $gateway
     * @param array $data
     * @return IBankPortInterface
     */
    public function getPortInterface(string $gateway, array $data): IBankPortInterface;

}
