<?php

declare(strict_types=1);

use SpectroCoin\SCMerchantClient\Enum\OrderStatus;
use SpectroCoin\SCMerchantClient\Http\OrderCallback;

use Exception;
use InvalidArgumentException;

use GuzzleHttp\Exception\RequestException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SpectrocoinCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    public function postProcess(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $logMessage = "SpectroCoin Callback: Invalid request method: " . $_SERVER['REQUEST_METHOD'];
            PrestaShopLogger::addLog($logMessage, 3);
            http_response_code(405);
            exit($logMessage);
        }

        $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

        $post_data = [];

        foreach ($expected_keys as $key) {
            if (isset($_POST[$key])) {
                $post_data[$key] = $_POST[$key];
            } else {
                PrestaShopLogger::addLog("SpectroCoin Callback: Missing expected key: " . $key, 3);
            }
        }

        try {
            $order_callback = $this->initCallbackFromPost();
            PrestaShopLogger::addLog(json_encode($order_callback)); //DEBUG
            if ($order_callback) {
                $history = new OrderHistory();
                $history->id_order = (int) $post_data['orderId'];
                $status = $order_callback->getStatus();
                switch ($status) {
                    case OrderStatus::New ->value:
                    case OrderStatus::Pending->value:
                        break;
                    case OrderStatus::Expired->value:
                        $history->changeIdOrderState((int) Configuration::get('PS_OS_CANCELED'), (int) $post_data['orderId']);
                        break;
                    case OrderStatus::Failed->value:
                        $history->changeIdOrderState((int) Configuration::get('PS_OS_ERROR'), (int) $post_data['orderId']);
                        break;
                    case OrderStatus::Paid->value:
                        $history->changeIdOrderState((int) Configuration::get('PS_OS_PAYMENT'), (int) $post_data['orderId']);
                        $history->addWithemail(true, ['order_name' => $post_data['orderId']]);
                        break;
                    default:
                        $logMessage = "SpectroCoin Callback: Unknown order status: " . $status;
                        PrestaShopLogger::addLog($logMessage, 3);
                        http_response_code(400);
                        exit($logMessage);
                }
                http_response_code(200);
                exit('*ok*');
            } else {
                $logMessage = "SpectroCoin Callback: Invalid callback data processed.";
                PrestaShopLogger::addLog($logMessage, 3);
                http_response_code(400);
                exit($logMessage);
            }
        } catch (RequestException $e) {
            $logMessage = "Callback API error: {$e->getMessage()}";
            PrestaShopLogger::addLog($logMessage, 3);
            http_response_code(500); // Internal Server Error
            exit($logMessage);
        } catch (InvalidArgumentException $e) {
            $logMessage = "Error processing callback: {$e->getMessage()}";
            PrestaShopLogger::addLog($logMessage, 3);
            http_response_code(400); // Bad Request
            exit($logMessage);
        } catch (Exception $e) {
            $logMessage = "SpectroCoin Callback Exception: " . get_class($e) . ': ' . $e->getMessage();
            PrestaShopLogger::addLog($logMessage, 3);
            http_response_code(500);
            exit($logMessage);
        }
    }

    /**
     * Initializes the callback data from POST request.
     * 
     * @return OrderCallback|null Returns an OrderCallback object if data is valid, null otherwise.
     */
    private function initCallbackFromPost(): ?OrderCallback
    {
        $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

        $callback_data = [];
        foreach ($expected_keys as $key) {
            if (isset($_POST[$key])) {
                $callback_data[$key] = $_POST[$key];
            }
        }

        if (empty($callback_data)) {
            PrestaShopLogger::addLog("No data received in callback", 3);
            return null;
        }
        return new OrderCallback($callback_data);
    }
}