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

          if (empty($_REQUEST)) { //TO-DO: Check if this is the correct way to check for POST data
            exit('Invalid request!');
          }

          $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

          $post_data = [];
      
          foreach ($expected_keys as $key) {
            if (isset($_REQUEST[$key])) {
              $post_data[$key] = $_REQUEST[$key];
            }
          }

          $scMerchantClient = new SCMerchantClient(
            $this->module->merchant_api_url,
            $this->module->project_id,
            $this->module->client_id,
            $this->module->client_secret,
            $this->module->auth_url
          );

          $callback = $scMerchantClient->spectrocoin_process_callback($post_data);

          if ($callback){

            $history           = new OrderHistory();
            $history->id_order = $post_data['orderId'];

            switch ($callback->getStatus()) {
              case SpectroCoin_OrderStatusEnum::$New:
              case SpectroCoin_OrderStatusEnum::$Pending:
                break;
              case SpectroCoin_OrderStatusEnum::$Expired:
                $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $post_data['orderId']);
                break;
              case SpectroCoin_OrderStatusEnum::$Failed:
                $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $post_data['orderId']);
                break;
              case SpectroCoin_OrderStatusEnum::$Test:
              case SpectroCoin_OrderStatusEnum::$Paid:
                $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $post_data['orderId']);
                $history->addWithemail(true, array(
                  'order_name' => $post_data['orderId'],
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
