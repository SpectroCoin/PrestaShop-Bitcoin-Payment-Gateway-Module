<?php

/**
 * @since 1.5.0
 */
class SpectrocoinRedirectModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent() {

		parent::initContent();

		global $link, $cookie;
		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart)) {
			Tools::redirect('index.php?controller=order');
		}

		$total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
		$currency = Context::getContext()->currency;
		require_once $this->module->getLocalPath().'/SCMerchantClient/SCMerchantClient.php';

		$this->module->validateOrder($cart->id, Configuration::get('SPECTROCOIN_PENDING'), $total, $this->module->displayName, NULL, NULL, $currency->id);

		$scMerchantClient = new SCMerchantClient(
			$this->module->SC_API_URL,
			$this->module->merchantId,
			$this->module->apiId
		);
		$scMerchantClient->setPrivateMerchantKey($this->module->private_key);

		$createOrderRequest = new CreateOrderRequest(
			$this->module->currentOrder,
			'BTC',
			NULL,
			$currency->iso_code,
			$total,
			'Order #'.$this->module->currentOrder,
			$this->module->culture,
			$link->getModuleLink('spectrocoin', 'callback'),
			$link->getModuleLink('spectrocoin', 'validation'),
			$link->getModuleLink('spectrocoin', 'cancel')
		);
		$createOrderResponse = $scMerchantClient->createOrder($createOrderRequest);
		if ($createOrderResponse instanceof ApiError) {
			die('Error occurred. '.$createOrderResponse->getCode().': '.$createOrderResponse->getMessage());
		} else if ($createOrderResponse instanceof CreateOrderResponse) {
			Tools::redirect($createOrderResponse->getRedirectUrl());
		}

	}
}