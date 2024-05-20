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
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            PrestaShopLogger::addLog("SpectroCoin Callback: Invalid request method: " . $_SERVER['REQUEST_METHOD'], 3);
            exit('Invalid request method!');
        }

        $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

        $post_data = [];

        foreach ($expected_keys as $key) {
            if (isset($_REQUEST[$key])) {
                $post_data[$key] = $_REQUEST[$key];
            } else {
                PrestaShopLogger::addLog("SpectroCoin Callback: Missing expected key: " . $key, 3);
            }
        }

        PrestaShopLogger::addLog("SpectroCoin Callback: Received callback data: " . print_r($post_data, true), 1); // Debug

        try {
            $scMerchantClient = new SCMerchantClient(
                $this->module->merchant_api_url,
                $this->module->project_id,
                $this->module->client_id,
                $this->module->client_secret,
                $this->module->auth_url
            );

            $callback = $scMerchantClient->spectrocoinProcessCallback($post_data);

            if ($callback) {
                $history = new OrderHistory();
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
                        $history->addWithemail(true, ['order_name' => $post_data['orderId']]);
                        break;
                    default:
                        PrestaShopLogger::addLog("SpectroCoin Callback: Unknown order status: " . $callback->getStatus(), 3);
                        exit('Unknown order status: ' . $callback->getStatus());
                }
                exit('*ok*');
            } else {
                PrestaShopLogger::addLog("SpectroCoin Callback: Invalid callback data processed.", 3);
                exit('Invalid callback!');
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog("SpectroCoin Callback Exception: " . get_class($e) . ': ' . $e->getMessage(), 3);
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}
