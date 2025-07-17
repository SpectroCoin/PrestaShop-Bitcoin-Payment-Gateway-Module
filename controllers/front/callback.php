<?php

/**
 * SpectroCoin Module
 *
 * Copyright (C) 2014-2025 SpectroCoin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @author SpectroCoin
 * @copyright 2014-2025 SpectroCoin
 * @license https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

declare(strict_types=1);

use GuzzleHttp\Exception\RequestException;
use SpectroCoin\SCMerchantClient\Enum\OrderStatus;
use SpectroCoin\SCMerchantClient\Http\OldOrderCallback;
use SpectroCoin\SCMerchantClient\Http\OrderCallback;
use SpectroCoin\SCMerchantClient\SCMerchantClient;

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
            PrestaShopLogger::addLog(
                'SpectroCoin Callback: Invalid request method: ' . $_SERVER['REQUEST_METHOD'],
                3
            );
            http_response_code(405);
            exit;
        }

        try {
            if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $cb = $this->initCallbackFromJson();
                if (! $cb) {
                    throw new InvalidArgumentException('Invalid JSON callback payload');
                }

                $client = new SCMerchantClient(
                    $this->module->project_id,
                    $this->module->client_id,
                    $this->module->client_secret
                );
                $orderData = $client->getOrderById($cb->getUuid());

                if (
                    !is_array($orderData)
                    || empty($orderData['orderId'])
                    || empty($orderData['status'])
                ) {
                    throw new InvalidArgumentException('Malformed order data from API');
                }

                $orderIdRaw = $orderData['orderId'];
                $statusRaw  = $orderData['status'];
            } else {
                $cb = $this->initCallbackFromPost();
                if (! $cb) {
                    throw new InvalidArgumentException('Invalid form-encoded callback payload');
                }

                $orderIdRaw = $cb->getOrderId();
                $statusRaw  = $cb->getStatus();
            }
            $history            = new OrderHistory();
            $orderId = explode('-', $orderIdRaw, 2)[0];
            $history->id_order  = (int) $orderId;
            $statusEnum = OrderStatus::normalize($statusRaw);

            switch ($statusEnum) {
                case $statusEnum::NEW:
                    break;

                case $statusEnum::EXPIRED:
                    $history->changeIdOrderState(
                        (int) Configuration::get('PS_OS_CANCELED'),
                        (int) $orderIdRaw
                    );
                    break;

                case $statusEnum::FAILED:
                    $history->changeIdOrderState(
                        (int) Configuration::get('PS_OS_ERROR'),
                        (int) $orderIdRaw
                    );
                    break;

               case $statusEnum::PAID:
                    $history->changeIdOrderState(
                        (int) Configuration::get('PS_OS_PAYMENT'),
                        (int) $orderIdRaw
                    );
                    $history->addWithemail(true, ['order_name' => $orderIdRaw]);
                    break;

                default:
                    PrestaShopLogger::addLog(
                        'SpectroCoin Callback: Unknown order status: ' . $statusRaw,
                        3
                    );
                    http_response_code(400);
                    exit;
            }

            http_response_code(200);
            exit('*ok*');
        } catch (RequestException $e) {
            PrestaShopLogger::addLog('Callback API error: ' . $e->getMessage(), 3);
            http_response_code(500);
            exit;
        } catch (InvalidArgumentException $e) {
            PrestaShopLogger::addLog('Error processing callback: ' . $e->getMessage(), 3);
            http_response_code(400);
            exit;
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'SpectroCoin Callback Exception: ' . get_class($e) . ': ' . $e->getMessage(),
                3
            );
            http_response_code(500);
            exit;
        }
    }

    /**
     * Initializes the callback data from POST (form-encoded) request.
     * 
     * Callback format processed by this method is URL-encoded form data.
     * Example: merchantId=1387551&apiId=105548&userId=…&sign=…
     * Content-Type: application/x-www-form-urlencoded
     * These callbacks are being sent by old merchant projects.
     *
     * Extracts the expected fields from `$_POST`, validates the signature,
     * and returns an `OldOrderCallback` instance wrapping that data.
     *
     * @deprecated since v2.1.0
     *
     * @return OldOrderCallback|null the callback object if data is valid, null otherwise
     */
    private function initCallbackFromPost(): ?OldOrderCallback
    {
        $expected_keys = [
            'userId',
            'merchantApiId',
            'merchantId',
            'apiId',
            'orderId',
            'payCurrency',
            'payAmount',
            'receiveCurrency',
            'receiveAmount',
            'receivedAmount',
            'description',
            'orderRequestId',
            'status',
            'sign',
        ];

        $callback_data = [];

        foreach ($expected_keys as $key) {
            if (\Tools::getIsset($key)) {
                $callback_data[$key] = $_POST[$key];
            }
        }

        if (empty($callback_data)) {
            PrestaShopLogger::addLog('No data received in callback', 3);
            return null;
        }

        return new OldOrderCallback($callback_data);
    }

    /**
     * Initializes the callback data from JSON request body.
     *
     * Reads the raw HTTP request body, decodes it as JSON, and returns
     * an OrderCallback instance if the payload is valid.
     *
     * @return OrderCallback|null  An OrderCallback if the JSON payload
     *                             contained valid data; null if the body
     *                             was empty.
     *
     * @throws \JsonException           If the request body is not valid JSON.
     * @throws \InvalidArgumentException If required fields are missing
     *                                   or validation fails in OrderCallback.
     *
     */
    private function initCallbackFromJson(): ?OrderCallback
    {
        $body = (string) \file_get_contents('php://input');
        if ($body === '') {
            PrestaShopLogger::addLog('Empty JSON callback payload', 3);
            return null;
        }

        $data = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            PrestaShopLogger::addLog('JSON callback payload is not an object', 3);
            return null;
        }

        return new OrderCallback(
            $data['id'] ?? null,
            $data['merchantApiId'] ?? null
        );
    }
}
