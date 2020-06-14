<?php

namespace Larapress\ECommerce\Services\Ports\Zarrinpal;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Larapress\ECommerce\Services\IBankPortInterface;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

class PayPalPortInterface implements IBankPortInterface
{
	protected $config = [
		'type' => 'sandbox',
		'title' => 'Direct Payment',
		'quantity' => 1,
		'currency' => 'USD',
		'description' => '',
	];
	protected $configRules = [
		'client_id' => 'required|string',
		'client_secret' => 'required|string',
		'type' => 'nullable|in:sandbox,live',
		'price' => 'required|numeric',
		'currency' => 'nullable',
		'title' => 'nullable|string',
		'description' => 'nullable|string',
		'quantity' => 'nullable|numeric',
		'callback_url' => 'required|string',
	];
	protected $api_context;

	/** @var Payment */
	protected $payment;
	/** @var int */
	protected $gateway_id;

	/**
	 * PayPalPortInterface constructor.
	 *
	 * @param int $gateway_id
	 */
	public function __construct($gateway_id)
	{
		$this->gateway_id = $gateway_id;
	}

	function getGatewayID()
	{
		return BankGatewayID::PAYPAL;
	}

	/**
	 * @return int
	 */
	function getBankGatewayID()
	{
		return $this->gateway_id;
	}

	/**
	 * @return string
	 */
	function getBankGatewayName()
	{
		return 'PayPal';
	}

	/**
	 * Merge configurations
	 *
	 * @param array $config
	 */
	function setConfigs( $config )
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
			throw new ValidationException($validate);
		}

		return true;
	}

	/**
	 * @param BankGatewayTransaction $bank_tr
	 *
	 * @return bool
	 */
	function prepare(BankGatewayTransaction $bank_tr)
	{
		$this->api_context = new ApiContext(new OAuthTokenCredential(
			$this->config['client_id'],
			$this->config['client_secret']
		));
		$this->api_context->setConfig([
			'mode' => $this->config['type'],
		]);
		$currency = 'USD';
		if ($this->config['currency'] instanceof ICurrency) {
			switch ($this->config['currency']->getCurrencyName()) {
				case 'euro':
					$currency = 'EUR';
					break;
				case 'ukp':
					$currency = 'GBP';
					break;
				case 'swedish_krona':
					$currency = 'SEK';
					break;
			}
		} else {
			$currency = $this->config['currency'];
		}

		$payer = new Payer();
		$payer->setPaymentMethod('paypal');

		$item = new Item();
		$item->setPrice($this->config['price']);
		$item->setCurrency($currency);
		$item->setQuantity($this->config['quantity']);
		$itemList = new ItemList();
		$itemList->addItem($item);

		$amount = new Amount();
		$amount->setTotal($this->config['price']);
		$amount->setCurrency($currency);

		$transaction = new Transaction();
		$transaction->setAmount($amount);
		$transaction->setItemList($itemList);
		$transaction->setDescription($this->config['description']);
		$transaction->setInvoiceNumber($bank_tr->reference_code);

		$redirectUrls = new RedirectUrls();
		$redirectUrls->setReturnUrl(Helpers::addQueryParamToUrl($this->config['callback_url'], ['success' => true]));
		$redirectUrls->setCancelUrl(Helpers::addQueryParamToUrl($this->config['callback_url'], ['success' => false]));

		$payment = new Payment();
		$payment->setPayer($payer);
		$payment->setIntent('Sale');
		$payment->setTransactions([$transaction]);
		$payment->setRedirectUrls($redirectUrls);

		$payment->create($this->api_context);
		$bank_tr->update([
			'bank_ref_code' => $payment->getId(),
			'status' => BankGatewayTransactionStatus::UNAPPROVED,
		]);

		$this->payment = $payment;

		return true;
	}

	/**
	 * @return \Illuminate\Http\Response|\Illuminate\View\View|RedirectResponse
	 */
	function redirect()
	{
		return Redirect::away($this->payment->getApprovalLink());
	}

	/**
	 * @param Request                $request
	 * @param BankGatewayTransaction $bank_tr
	 *
	 * @return bool
	 */
	function verify(Request $request, BankGatewayTransaction $bank_tr)
	{
		$success = $request->get('success');
		if ($success) {
			$bank_tr->update([
				'status' => BankGatewayTransactionStatus::ACCEPTED,
			]);
			$this->api_context = new ApiContext(new OAuthTokenCredential(
				$this->config['client_id'],
				$this->config['client_secret']
			));
			$this->api_context->setConfig([
				'mode' => $this->config['type']
			]);
			$paymentId = $request->get('paymentId');
			$payment = Payment::get($paymentId, $this->api_context);
			$execution = new PaymentExecution();
			$execution->setPayerId($request->get('PayerID'));

			$payment->execute($execution, $this->api_context);
			Payment::get($paymentId, $this->api_context);

			$bank_tr->update([
				'status' => BankGatewayTransactionStatus::VERIFIED,
				'payment_date' => Carbon::now(),
			]);
			return true;
		} else {
			$bank_tr->update([
				'status' => BankGatewayTransactionStatus::CANCELED,
			]);
			return false;
		}
	}

	/**
	 * @return array
	 */
	function getRedirectRules()
	{
		return [
			'amount' => 'required|numeric|'.implode('|', BankGatewayID::getAmountRuleForCurrencies(BankGatewayID::PAYPAL)),
			'currency_id' => 'required|numeric|exists:filters,id',
			'gateway_id' => 'required|numeric|exists:bank_gateways,id',
		];
	}
}
