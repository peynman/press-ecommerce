<?php

namespace Larapress\ECommerce\Services\Ports;

use App\Exceptions\ValidationException;
use App\Extend\Helpers;
use App\Models\BankGatewayTransaction;
use App\Services\BankGateways\BankGatewayID;
use App\Models\Flags\BankGatewayTransactionStatus;
use App\Services\BankGateways\IBankPortInterface;
use App\Services\Currency\ICurrency;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Stripe\Stripe;

class StripePortInterface implements IBankPortInterface
{
	protected $gateway_id;
	protected $config = [
		'quantity' => 1,
		'currency' => 'usd',
		'title' => 'Deposit'
	];
	protected $configRules = [
		'client_id' => 'required|string',
		'client_secret' => 'required|string',
		'currency' => 'nullable',
		'price' => 'required|numeric',
		'title' => 'nullable|string',
		'description' => 'nullable|string',
		'callback_url' => 'required|string',
		'quantity' => 'nullable|numeric',
	];
	protected $session;

	public function __construct($gateway_id)
	{
		$this->gateway_id = $gateway_id;
	}

	/**
	 * @return int
	 */
	function getBankGatewayID()
	{
		return $this->gateway_id;
	}

	/**
	 * @return int
	 */
	function getGatewayID()
	{
		return BankGatewayID::STRIPE;
	}

	/**
	 * @return string
	 */
	function getBankGatewayName()
	{
		return 'stripe';
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
			dd($validate);
			throw new ValidationException($validate);
		}

		return true;
	}

	/**
	 * @param BankGatewayTransaction $transaction
	 *
	 * @return bool
	 */
	function prepare( BankGatewayTransaction $transaction )
	{
		$currency = null;
		$price = intval($this->config['price']);
		if ($this->config['currency'] instanceof ICurrency) {
			switch ($this->config['currency']->getCurrencyName()) {
				case 'swedish_krona':
					$currency = 'sek';
					break;
				case 'ukp':
					$currency = 'gbp';
					$price = $price * 100;
					break;
				case 'euro':
					$currency = 'eur';
					$price = $price * 100;
					break;
				default:
					$currency = 'usd';
					$price = $price * 100;
			}
		} else {
			$currency = $this->config['currency'];
		}

		Stripe::setApiKey($this->config['client_secret']);
		$this->session = \Stripe\Checkout\Session::create([
			'success_url' => Helpers::addQueryParamToUrl($this->config['callback_url'], ['success' => true]),
			'cancel_url' => Helpers::addQueryParamToUrl($this->config['callback_url'], ['success' => false]),
			'payment_method_types' => ['card'],
			'line_items' => [[
				'amount' => $price,
				'currency' => $currency,
				'quantity' => $this->config['quantity'],
				'name' => $this->config['title'],
			]]
		], [
			'stripe_version' => '2018-11-08; checkout_sessions_beta=v1'
		]);

		if (!is_null($this->session)) {
			$transaction->update([
				'status' => BankGatewayTransactionStatus::UNAPPROVED,
				'bank_ref_code' => $this->session['id'],
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
		return \view('themes.BetPress.pages.partials.stripe-redirect', [
			'publicKey' => $this->config['client_id'],
			'purchaseSession' => $this->session['id'],
		]);
	}

	/**
	 * @param Request                $request
	 * @param BankGatewayTransaction $bank_tr
	 *
	 * @return boolean
	 */
	function verify( Request $request, BankGatewayTransaction $bank_tr )
	{
		$success = $request->get('success');
		if ($success) {
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
			'amount' => 'required|numeric|'.implode('|', BankGatewayID::getAmountRuleForCurrencies(BankGatewayID::STRIPE)),
			'currency_id' => 'required|numeric|exists:filters,id',
			'gateway_id' => 'required|numeric|exists:bank_gateways,id',
		];
	}
}
