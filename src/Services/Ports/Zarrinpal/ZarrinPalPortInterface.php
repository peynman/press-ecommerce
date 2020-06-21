<?php


namespace Larapress\ECommerce\Services\Ports\Zarrinpal;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Larapress\CRUD\Exceptions\ValidationException;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Services\IBankPortInterface;
use Larapress\Profiles\IProfileUser;

class ZarrinPalPortInterface implements IBankPortInterface
{
    protected $configRules = [
        'merchant_id' => 'required|string',
        'isZarinGate' => 'nullable|bool',
        'isSandbox' => 'nullable|bool',
        'email' => 'nullable|email',
        'mobile' => 'nullable|string',
    ];
    /** @var Zarinpal */
    private $zarinpal;

    /** @var BankGateway */
    private $gateway;


    public function __construct(BankGateway $gateway)
    {
        $this->gateway = $gateway;
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
        $this->validate($transaction);
        $config = $transaction->bank_gateway->data['gateway'];
        $zarinpal = new Zarinpal();
        $result = $zarinpal->request(
            $config['merchant_id'],
            $config['amount'],
            $transaction->data['description'],
            $config['email'],
            $config['mobile'],
            $callback_url,
            $config['isSandbox'],
            $config['isZarinGate']
        );

        if (isset($result["Status"]) && $result["Status"] == 100)
        {
            // Success and redirect to pay
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FORWARDED,
                'reference_code' => $result['Authority'],
            ]);
            return $zarinpal->redirect($result["StartPay"]);
        }

        throw new Exception("could not contact bank gateway zarrinpal: " . json_encode($result));
    }

    /**
     * @param Request                $request
     * @param BankGatewayTransaction $transaction
     *
     * @return boolean
     */
    public function verify(Request $request, BankGatewayTransaction $transaction)
    {
        $this->validate($transaction);
        $config = $transaction->bank_gateway->data['gateway'];
        $zp = new Zarinpal();

        $transaction->update([
            'status' => BankGatewayTransaction::STATUS_RECEIVED,
        ]);
        $result = $zp->verify($config['merchant_id'], $transaction->amount, $config['isSandbox'], $config['isZarinGate']);

        if (isset($result["Status"]) && $result["Status"] == 100)
        {
            // Success
            $data = $transaction->data;
            $data['amount'] = $result["Amount"];
            $data['reference_code'] = $result["RefID"];
            $data['authority'] = $result["Authority"];
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_SUCCESS,
                'data' => $data,
            ]);
            return true;
        } else {
            // error
            $data = $transaction->data;
            $data['error_code'] = $result["Status"];
            $data['error_message'] = $result["Message"];
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FAILED,
                'data' => $data,
            ]);
            return false;
        }
        return false;
    }

    /**
     * @return boolean
     * @throws ValidationException
     */
    protected function validate(BankGatewayTransaction $transaction)
    {
        $validate = Validator::make($transaction->bank_gateway->data['gateway'], $this->configRules);
        if ($validate->fails()) {
            throw new ValidationException($validate->errors());
        }

        return true;
    }

}