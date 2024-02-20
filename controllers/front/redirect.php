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

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart)) {
			Tools::redirect('index.php?controller=order');
		}

		$total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
		$currency = Context::getContext()->currency;
		require_once $this->module->getLocalPath().'/SCMerchantClient/SCMerchantClient.php';

		$this->module->validateOrder($cart->id, Configuration::get('SPECTROCOIN_PENDING'), $total, $this->module->displayName, NULL, NULL, $currency->id);

		$scMerchantClient = new SCMerchantClient(
            $this->module->merchant_api_url,
            $this->module->project_id,
            $this->module->client_id,
            $this->module->client_secret,
            $this->module->auth_url
          );

		$createOrderRequest = new SpectroCoin_CreateOrderRequest(
			$this->module->currentOrder, // order id
			'Order #'.$this->module->currentOrder, // description
			NULL, // pay amount
			'BTC', // pay currency code
			$total, // receive amount
			$currency->iso_code, // receive currency code
			$this->context->link->getModuleLink('spectrocoin', 'callback'), // callback url
			$this->context->link->getModuleLink('spectrocoin', 'validation'), // success url
			$this->context->link->getModuleLink('spectrocoin', 'cancel'), // failure url
			$this->module->lang,
		);
		$createOrderResponse = $scMerchantClient->spectrocoin_create_order($createOrderRequest);
		if ($createOrderResponse instanceof SpectroCoin_ApiError) {
			$this->renderResponseErrorCode($createOrderResponse->getCode(), $createOrderResponse->getMessage());
		} else if ($createOrderResponse instanceof SpectroCoin_CreateOrderResponse) {
			Tools::redirect($createOrderResponse->getRedirectUrl());
		}

	}

	/**
     * Function to render error response HTML.
     *
     * @param int    $errorCode    The error code.
     * @param string $errorMessage The error message.
     */
    protected function renderResponseErrorCode($errorCode, $errorMessage)
	{
		$shopLink = Context::getContext()->link->getPageLink('index');
		
		echo '<link rel="stylesheet" href="' . MODULE_ROOT_DIR . 'modules/spectrocoin/views/css/error-response.css" type="text/css" media="all" />';
		echo '
			<div class="container">
				<div class="content_container">
					<div class="header_container">
						<h3>Error: '. $errorCode . ' ' . $errorMessage . '</h3>
					</div>
					<div class="content_content">
						<div class="form_body">';

		echo '
							<a href=' . $shopLink . '><button>Return to shop</button></a>
						</div>
					</div>
					<div class="footer_container">
						<div class="footer_link">
							<a href="mailto:merchant@spectrocoin.com">Contact support</a>
						</div>
					</div>
				</div>
			</div>';
	}


}