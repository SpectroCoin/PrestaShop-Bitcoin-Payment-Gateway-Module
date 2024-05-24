<?php

/**
 * @since 1.5.0
 */
class SpectrocoinCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    public function postProcess()
    {

         // Enable all PHP errors
         error_reporting(E_ALL);
         ini_set('display_errors', 1);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            PrestaShopLogger::addLog("SpectroCoin Callback: Invalid request method: " . $_SERVER['REQUEST_METHOD'],3);
            http_response_code(405);
            exit('Invalid request method!');
        }

        $input = file_get_contents("php://input");
        $post_data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            PrestaShopLogger::addLog("SpectroCoin Callback: Invalid JSON data.",3);
            http_response_code(400);
            exit('Invalid JSON data!');
        }

        $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

        foreach ($expected_keys as $key) {
            if (!isset($post_data[$key])) {
                PrestaShopLogger::addLog("SpectroCoin Callback: Missing expected key: " . $key,3);
                http_response_code(400);
                exit('Missing required data!');
            }
        }

        PrestaShopLogger::addLog("SpectroCoin Callback: Received callback data: " . print_r($post_data, true),1);

        try {
            PrestaShopLogger::addLog("SpectroCoin Callback: Initializing SCMerchantClient.",1);

             // Additional logging to check configuration values
             PrestaShopLogger::addLog("SpectroCoin Callback: merchant_api_url: " . $this->module->merchant_api_url,1);
             PrestaShopLogger::addLog("SpectroCoin Callback: project_id: " . $this->module->project_id,1);
             PrestaShopLogger::addLog("SpectroCoin Callback: client_id: " . $this->module->client_id,1);
             PrestaShopLogger::addLog("SpectroCoin Callback: client_secret: " . $this->module->client_secret,1);
             PrestaShopLogger::addLog("SpectroCoin Callback: auth_url: " . $this->module->auth_url,1);


            $scMerchantClient = new SCMerchantClient(
                $this->module->merchant_api_url,
                $this->module->project_id,
                $this->module->client_id,
                $this->module->client_secret,
                $this->module->auth_url
            );

            PrestaShopLogger::addLog("SpectroCoin Callback: Processing callback data.",1);
            $callback = $scMerchantClient->spectrocoinProcessCallback($post_data);

            if ($callback) {
                $history = new OrderHistory();
                $history->id_order = $post_data['orderId'];

                $status = $callback->getStatus();
                PrestaShopLogger::addLog("SpectroCoin Callback: Callback status: " . $status,1);

                switch ($status) {
                    case SpectroCoin_OrderStatusEnum::$New:
                    case SpectroCoin_OrderStatusEnum::$Pending:
                        // No action needed for these statuses
                        break;
                    case SpectroCoin_OrderStatusEnum::$Expired:
                        $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $post_data['orderId']);
                        break;
                    case SpectroCoin_OrderStatusEnum::$Failed:
                        $history->changeIdOrderState((int)Configuration::get('PS_OS_ERROR'), $post_data['orderId']);
                        break;
                    case SpectroCoin_OrderStatusEnum::$Paid:
                        $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $post_data['orderId']);
                        $history->addWithemail(true, ['order_name' => $post_data['orderId']]);
                        break;
                    default:
                        PrestaShopLogger::addLog("SpectroCoin Callback: Unknown order status: " . $status,3);
                        http_response_code(400);
                        exit('Unknown order status: ' . $status);
                }
                http_response_code(200);
                exit('*ok*');
            } else {
                PrestaShopLogger::addLog("SpectroCoin Callback: Invalid callback data processed.",3);
                http_response_code(400);
                exit('Invalid callback!');
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog("SpectroCoin Callback Exception: " . get_class($e) . ': ' . $e->getMessage(),3);
            http_response_code(500);
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}
