<?php


namespace Larapress\ECommerce\Services\Ports\Zarrinpal;


use App\Exceptions\ValidationException;
use App\Extend\Helpers;
use App\Models\BankGatewayTransaction;
use App\Models\Filter;
use App\Models\Flags\BankGatewayTransactionStatus;
use App\Services\BankGateways\BankGatewayID;
use App\Services\BankGateways\IBankPortInterface;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Zarinpal\Zarinpal;

class PersianWebAppPalPortInterface implements IBankPortInterface
{
    protected $server = 'http://persianwebapp.com/trs/webservice';
    protected $server_to_bank = 'http://persianwebapp.com/trs/payment/goToBank/';
    protected $bank_redirect;

    protected $config = [
        'type' => 'normal',
    ];
    protected $configRules = [
        'merchant_id'  => 'required|string',
        'callback_url' => 'required|string',
        'description'  => 'nullable|string',
        'price'        => 'required|numeric',
    ];
    private $gateway_id;


    public function __construct($gateway_id)
    {
        $this->gateway_id = $gateway_id;
    }

    function getBankGatewayName()
    {
        return 'PersianWebApp';
    }

    function getGatewayID()
    {
        return BankGatewayID::PERSIAN_WEBAPP;
    }

    /**
     * @return int
     */
    function getBankGatewayID()
    {
        return $this->gateway_id;
    }

    /**
     * @param array $config
     */
    function setConfigs($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @return boolean
     * @throws ValidationException
     */
    function validate()
    {
        $validate = Validator::make($this->config, $this->configRules);
        if ($validate->fails()) {
            throw new ValidationException($validate->errors());
        }

        return true;
    }

    public function verifyBank($method, $amount, $authority)
    {
        $type = 'verifyRequest';
        $mid  = $this->config['merchant_id'];
        $data = [
            'mid'           => $mid,
            'amount'        => $amount,
            'authority'     => $authority,
            'paymentmethod' => $method
        ];

        $connect = $this->connect($type, $data);

        return $connect;

    }

    public function connect($type, $data)
    {
        $url    = $this->server . '/' . $type;
        $client = new \GuzzleHttp\Client();
        $data   = json_encode($data);
        $result = $client->get($url . '?params=' . $data);


        $content    = json_decode($result->getBody()->getContents());
        $all_errors = $this->getWebAppResultMessage();
        $return     = [
            'code'    => $content->message,
            'message' => isset($all_errors[$content->message]) ? $all_errors[$content->message] : $content->message,
            'result'  => $content->result,
            'access'  => false
        ];
        if ($content->error == false) {
            $return['access'] = true;
        }


        return $return;
    }

    /**
     * @param BankGatewayTransaction $transaction
     *
     * @return boolean
     */
    function prepare(BankGatewayTransaction $transaction)
    {

        $amount   = $this->config['price'];
        $currency = Helpers::getCurrencyByID($this->config['currency_id']);

        if (isset(config('ecommerce.banking.currencies')[$currency->name]['convert_to'])) {
            $amount = config('ecommerce.banking.currencies')[$currency->name]['convert_to']($amount);
        }


        $type = 'payRequest';
        $mid  = $this->config['merchant_id'];
        $data = [
            'mid'           => $mid,
            'amount'        => intval($amount) * 10,
            'callback'      => urlencode($this->config['callback_url']),
            'paymentmethod' => $this->config['merchant_config_id'],
            'userid'        => 0,
        ];
        //        if ($type === 'payRequest' && $bank !== false) {
        //            $data['bank'] = $bank;
        //        }

        $connect = $this->connect($type, $data);
        if ($connect['access']) {
            $this->bank_redirect = $connect['url'] = $this->server_to_bank . $connect['result'];
        }


        if (isset($connect['access']) && $connect['access'] === true) {
            $transaction->update([
                'status'        => BankGatewayTransactionStatus::UNAPPROVED,
                'bank_ref_code' => $connect['result'],
            ]);

            return true;
        }

        return false;
    }

    /**
     * @return View|Response|RedirectResponse
     */
    function redirect()
    {
        return Redirect::away($this->bank_redirect);
    }

    /**
     * @param Request $request
     * @param BankGatewayTransaction $bank_tr
     *
     * @return boolean
     */
    function verify(Request $request, BankGatewayTransaction $bank_tr)
    {
        $amount = intval($bank_tr->price) * 10;
        $verify = $this->verifyBank($this->config['merchant_config_id'], $amount, $bank_tr->bank_ref_code);
        $bank_tr->update([
            'status' => BankGatewayTransactionStatus::ACCEPTED,
        ]);

        if ($verify['access']) {
            $bank_tr->update([
                'status'       => BankGatewayTransactionStatus::VERIFIED,
                'payment_date' => Carbon::now(),
                'details'      => $verify['result'],
            ]);

            return true;

        } else {
            $bank_tr->update([
                'status'  => BankGatewayTransactionStatus::FAILED,
                'details' => $verify['message'],
            ]);

        }

        return false;
    }

    public static function getWebAppResultMessage()
    {
        return [
            20 => 'Unknown error',
            31 => 'Transaction not found',
            32 => 'Transaction failed',
            33 => 'The transaction amount does not match the amount sent',
            34 => 'Transaction has already been paid',
            35 => 'Gateway with the desired id was not found',
            36 => 'Gateway is inactive',
            37 => 'Invalid parameter send',
            38 => 'Invalid return address (call back)',
            39 => 'Invalid amount parameter',
            40 => 'The amount is higher than the limit',
            41 => 'The amount is lower than the limit',
            42 => 'The method could not be found',
        ];
    }

    /**
     * @return array
     */
    function getRedirectRules()
    {
        return [
            'amount'     => 'required|numeric|' . implode('|',
                    BankGatewayID::getAmountRuleForCurrencies(BankGatewayID::PERSIAN_WEBAPP)),
            'gateway_id' => 'required|numeric|exists:bank_gateways,id',
        ];
    }
}
