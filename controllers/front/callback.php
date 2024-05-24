<?php

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Psr\Log\LoggerInterface;

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

    /** @var LoggerInterface */
    private $logger;

    public function __construct()
    {
        parent::__construct();
        $container = SymfonyContainer::getInstance();
        $this->logger = $container->get('monolog.logger');
    }

    public function postProcess()
    {
        $this->logger->info("SpectroCoin Callback: Incoming callback request.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->logger->error("SpectroCoin Callback: Invalid request method: " . $_SERVER['REQUEST_METHOD']);
            http_response_code(405);
            exit('Invalid request method!');
        }

        $input = file_get_contents("php://input");
        $post_data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("SpectroCoin Callback: Invalid JSON data.");
            http_response_code(400);
            exit('Invalid JSON data!');
        }

        $expected_keys = ['userId', 'merchantApiId', 'merchantId', 'apiId', 'orderId', 'payCurrency', 'payAmount', 'receiveCurrency', 'receiveAmount', 'receivedAmount', 'description', 'orderRequestId', 'status', 'sign'];

        foreach ($expected_keys as $key) {
            if (!isset($post_data[$key])) {
                $this->logger->error("SpectroCoin Callback: Missing expected key: " . $key);
                http_response_code(400);
                exit('Missing required data!');
            }
        }

        $this->logger->info("SpectroCoin Callback: Received callback data: " . print_r($post_data, true));

        try {
            $this->logger->info("SpectroCoin Callback: Initializing SCMerchantClient.");
            $scMerchantClient = new SCMerchantClient(
                $this->module->merchant_api_url,
                $this->module->project_id,
                $this->module->client_id,
                $this->module->client_secret,
                $this->module->auth_url
            );

            $this->logger->info("SpectroCoin Callback: Processing callback data.");
            $callback = $scMerchantClient->spectrocoinProcessCallback($post_data);

            if ($callback) {
                $history = new OrderHistory();
                $history->id_order = $post_data['orderId'];

                $status = $callback->getStatus();
                $this->logger->info("SpectroCoin Callback: Callback status: " . $status);

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
                        $this->logger->error("SpectroCoin Callback: Unknown order status: " . $status);
                        http_response_code(400);
                        exit('Unknown order status: ' . $status);
                }
                http_response_code(200);
                exit('*ok*');
            } else {
                $this->logger->error("SpectroCoin Callback: Invalid callback data processed.");
                http_response_code(400);
                exit('Invalid callback!');
            }
        } catch (Exception $e) {
            $this->logger->error("SpectroCoin Callback Exception: " . get_class($e) . ': ' . $e->getMessage());
            http_response_code(500);
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }
}
