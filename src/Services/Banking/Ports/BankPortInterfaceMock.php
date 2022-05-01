<?php

namespace Larapress\ECommerce\Services\Banking\Ports;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Services\Banking\IBankPortInterface;

class BankPortInterfaceMock implements IBankPortInterface
{
    public const BankRedirectURL = 'https://localhost/banking-redireted';

    protected $verifyStatus = BankGatewayTransaction::STATUS_SUCCESS;
    protected $portName = 'zarinpal';

    public function setVerifyStatus($status) {
        $this->verifyStatus = $status;
    }

    /*
     * Undocumented function
     *
     * @return string
     */
    public function name()
    {
        return $this->portName;
    }

    /**
     * Undocumented function
     *
     * @param float $price
     * @param int $currency
     * @return mixed
     */
    public function convertForPriceAndCurrency(float $price, int $currency) {
        return [floor($price), $currency];
    }


    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     * @param string                 $callback_url
     *
     * @return View|Response|RedirectResponse
     */
    public function redirect(Request $request, BankGatewayTransaction $transaction, string $callback_url) {
        unset($transaction['bank_gateway']);
        unset($transaction->bank_gateway);
        unset($transaction->domain);
        unset($transaction->customer);

        $transaction->update([
            'status' => BankGatewayTransaction::STATUS_FORWARDED,
        ]);

        CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

        return redirect(self::BankRedirectURL);
    }

    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     *
     * @return BankGatewayTransaction
     */
    public function verify(Request $request, BankGatewayTransaction $transaction) {
        $transaction->update([
            'status' => $this->verifyStatus,
        ]);

        CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

        return $transaction;
    }


    /**
     * Undocumented function
     *
     * @param array $config
     *
     * @return boolean
     */
    public function isValidGatewayConfig(array $config)
    {
        return true;
    }
}
