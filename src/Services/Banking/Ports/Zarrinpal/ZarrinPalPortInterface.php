<?php


namespace Larapress\ECommerce\Services\Banking\Ports\Zarrinpal;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Exceptions\ValidationException;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Services\Banking\IBankPortInterface;
use Larapress\Profiles\IProfileUser;

class ZarrinPalPortInterface implements IBankPortInterface
{
    protected $configRules = [
        'merchantId' => 'required|string',
        'isZarinGate' => 'nullable|bool',
        'isSandbox' => 'nullable|bool',
        'email' => 'nullable|email',
        'mobile' => 'nullable|string',
    ];

    public function __construct(public array $config)
    {
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function name()
    {
        return 'zarinpal';
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param float $price
     * @param integer $currency
     * @param integer $cart_id
     * @param string $callback_url
     * @return BankGatewayTransaction
     */
    public function convertForPriceAndCurrency(float $price, int $currency)
    {
        // @todo: support for currency change
        return [$price, $currency];
    }

    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     * @param string                 $callback_url
     *
     * @return View|Response|RedirectResponse
     */
    public function redirect(Request $request, BankGatewayTransaction $transaction, string $callback_url)
    {
        $this->validate();

        $zarinpal = new Zarrinpal();
        $result = $zarinpal->request(
            $this->config['merchantId'],
            $transaction->amount,
            $transaction->data['description'],
            $this->config['email'] ?? '',
            $this->config['mobile'] ?? '',
            $callback_url,
            $this->config['isSandbox'] ?? false,
            $this->config['isZarinGate'] ?? false,
        );
        if (isset($result["Status"]) && $result["Status"] == 100) {
            unset($transaction['bank_gateway']);
            unset($transaction->bank_gateway);
            unset($transaction->domain);
            unset($transaction->customer);
            // Success and redirect to pay
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FORWARDED,
                'reference_code' => $result['Authority'],
            ]);
            CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

            return $zarinpal->redirect($result["StartPay"]);
        }

        Log::critical('ZarrinPal error: ' . json_encode($result));
        throw new Exception("could not contact bank gateway zarrinpal");
    }

    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     *
     * @return BankGatewayTransaction
     */
    public function verify(Request $request, BankGatewayTransaction $transaction)
    {
        $this->validate();

        $zp = new Zarrinpal();
        $transaction->update([
            'status' => BankGatewayTransaction::STATUS_RECEIVED,
        ]);
        CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

        $result = $zp->verify(
            $this->config['merchantId'],
            $transaction->amount,
            $this->config['isSandbox'] ?? false,
            $this->config['isZarinGate'] ?? false
        );

        if (isset($result["Status"]) && $result["Status"] == 100) {
            // Success
            $data = $transaction->data;
            $data['amount'] = $result["Amount"];
            $data['reference_code'] = $result["RefID"];
            $data['authority'] = $result["Authority"];
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_SUCCESS,
                'data' => $data,
            ]);
            CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

            return $transaction;
        } else {
            // error
            $data = $transaction->data;
            $data['error_code'] = $result["Status"];
            $data['error_message'] = $result["Message"];
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FAILED,
                'data' => $data,
            ]);
            CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

            return $transaction;
        }
    }

    /**
     * @return boolean
     * @throws ValidationException
     */
    protected function validate()
    {
        return $this->isValidGatewayConfig($this->config);
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
        $validate = Validator::make($config, $this->configRules);
        if ($validate->fails()) {
            Log::critical("Bank gateway " . $this->name() . " is invalid");
            throw new ValidationException($validate->errors());
        }

        return true;
    }
}
