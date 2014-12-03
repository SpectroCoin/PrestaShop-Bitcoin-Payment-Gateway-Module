<?php

/**
 * @since 1.5.0
 */
class SpectrocoinRedirectModuleFrontController extends ModuleFrontController {

  public $display_column_left = false;
  public $display_column_right = false;
  public $display_header = false;
  public $display_footer = false;
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

    $currency = Context::getContext()->currency;

    $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

    if($currency->iso_code != $this->module->currency_code){
      if($id_currency_to = Currency::getIdByIsoCode($this->module->currency_code)){
        $currency_to = Currency::getCurrencyInstance($id_currency_to);
        $total_converted = Tools::convertPriceFull($total, $currency, $currency_to);
      }else{
        die('Error occurred. Currency '.$this->module->currency_code.' is not configured.');
      }
    }else{
      $total_converted = $total;
    }

    require_once $this->module->getLocalPath().'/SCMerchantClient/SCMerchantClient.php';

    $this->module->validateOrder($cart->id, Configuration::get('SPECTROCOIN_PENDING'), $total, $this->module->displayName, NULL, NULL, $currency->id);

    $scMerchantClient = new SCMerchantClient(
      $this->module->SC_API_URL,
      $this->module->merchantId,
      $this->module->apiId,
      $this->module->private_key
    );

    $createOrderRequest = new CreateOrderRequest(
      $this->module->currentOrder,
      NULL,
      $total_converted,
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
      if($this->module->currency_code != $createOrderResponse->getReceiveCurrency()){
        die('Error occurred. Pay and receive currency mismatch.');
      }else{
        Tools::redirect($createOrderResponse->getRedirectUrl());
      }
    }

  }
}