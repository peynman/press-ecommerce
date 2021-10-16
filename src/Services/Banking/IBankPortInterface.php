<?php


namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Larapress\ECommerce\Models\BankGatewayTransaction;

interface IBankPortInterface
{
    /*
     * Undocumented function
     *
     * @return string
     */
    public function name();

    /**
     * Undocumented function
     *
     * @param float $price
     * @param int $currency
     * @return mixed
     */
    public function convertForPriceAndCurrency(float $price, int $currency);


    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     * @param string                 $callback_url
     *
     * @return View|Response|RedirectResponse
     */
    public function redirect(Request $request, BankGatewayTransaction $transaction, string $callback_url);

    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     *
     * @return BankGatewayTransaction
     */
    public function verify(Request $request, BankGatewayTransaction $transaction);

    /**
     * Undocumented function
     *
     * @param array $config
     *
     * @return boolean
     */
    public function isValidGatewayConfig(array $config);
}
