<?php

/**
 * @since 1.5.0
 */
class SpectrocoinCallbackModuleFrontController extends ModuleFrontController {
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess() {
        require_once $this->module->getLocalPath().'/SCMerchantClient/SCMerchantClient.php';

        try {
          $ipn = $_POST;

          // Exit now if the $_POST was empty.
          if (empty($ipn)) {
            exit('Invalid request!');
          }

          $scMerchantClient = new SCMerchantClient(
            $this->module->SC_API_URL,
            $this->module->merchantId,
            $this->module->apiId,
            $this->module->private_key
          );

          $callback = $scMerchantClient->parseCreateOrderCallback($ipn);

          if ($callback != null && $scMerchantClient->validateCreateOrderCallback($callback)){

            $history           = new OrderHistory();
            $history->id_order = $ipn['orderId'];

            switch ($callback->getStatus()) {
              case OrderStatusEnum::$New:
              case OrderStatusEnum::$Pending:
                break;
              case OrderStatusEnum::$Expired:
                $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $ipn['orderId']);
                break;
              case OrderStatusEnum::$Failed:
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $ipn['orderId']);
                break;
              case OrderStatusEnum::$Test:
              case OrderStatusEnum::$Paid:
                $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $ipn['orderId']);
                $history->addWithemail(true, array(
                  'order_name' => $ipn['orderId'],
                ));
                break;
              default:
                exit('Unknown order status: '.$callback->getStatus());
                break;
            }
            exit('*ok*');
          } else {
            exit('Invalid callback!');
          }
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}
