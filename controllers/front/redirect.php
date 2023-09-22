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
			$this->module->userId,
			$this->module->merchantApiId
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
			$this->context->link->getModuleLink('spectrocoin', 'callback'),
			$this->context->link->getModuleLink('spectrocoin', 'validation'),
			$this->context->link->getModuleLink('spectrocoin', 'cancel')
		);
		$createOrderResponse = $scMerchantClient->createOrder($createOrderRequest);
		if ($createOrderResponse instanceof ApiError) {
			$this->renderResponseErrorCode($createOrderResponse->getCode(), $createOrderResponse->getMessage());
			//die("tavo mamama");
			//renderResponseErrorCode($createOrderResponse->getCode(), $createOrderResponse->getMessage());
			//die('Error occurred. '.$createOrderResponse->getCode().': '.$createOrderResponse->getMessage());
			//die(renderResponseErrorCode($createOrderResponse->getCode(), $createOrderResponse->getMessage()));
		} else if ($createOrderResponse instanceof CreateOrderResponse) {
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
		echo '<link rel="stylesheet" href="http://localhost/specPresta/modules/spectrocoin/views/css/error-response.css" type="text/css" media="all" />';
        // Echo the HTML content for error display
        echo '
            <div class="container">
                <div class="content_container">
                    <div class="header_container">
                        <h3>Error</h3>
                    </div>
                    <div class="content_content">
                        <div class="form_body">
                            <div class="form_content response_code">
                                <span class="form-header">Response code:</span>
                                <span class="form-text">' . $errorCode . ' ' . $errorMessage . '</span>
                            </div>
                            <div class="form_content possible_cause">
                                <span class="form-header">Possible causes:</span>
                                <span class="form-text"></span>
                            </div>
                            <div class="form_content general_cause">
                                <span class="form-header">General causes:</span>
                                <span class="form-text"></span>
                            </div>
                            <button><a>Return to shop</a></button>
                        </div>
                    </div>
                    <div class="footer_container">
                        <div class="footer_link">
                            <a>Contact support</a>
                        </div>
                    </div>
                </div>
            </div>';
    }

}