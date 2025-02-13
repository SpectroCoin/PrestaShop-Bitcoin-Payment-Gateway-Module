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

use SpectroCoin\SCMerchantClient\Exception\ApiError;
use SpectroCoin\SCMerchantClient\Exception\GenericError;
use SpectroCoin\SCMerchantClient\SCMerchantClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SpectrocoinRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent(): void
    {
        parent::initContent();
        $cart = $this->context->cart;
        if (!$this->module->checkFiatCurrency($cart)) {
            \Tools::redirect('index.php?controller=order');
        }

        $total = (float) number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $currency = Context::getContext()->currency;

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('SPECTROCOIN_PENDING'),
            $total,
            $this->module->displayName,
            null,
            null,
            $currency->id
        );

        $sc_merchant_client = new SCMerchantClient(
            $this->module->project_id,
            $this->module->client_id,
            $this->module->client_secret
        );

        $order_data = [
            'orderId' => $this->module->currentOrder,
            'description' => 'Order #' . $this->module->currentOrder,
            'receiveAmount' => $total,
            'receiveCurrencyCode' => $currency->iso_code,
            'callbackUrl' => $this->context->link->getModuleLink('spectrocoin', 'callback'),
            'successUrl' => $this->context->link->getModuleLink('spectrocoin', 'validation'),
            'failureUrl' => $this->context->link->getModuleLink('spectrocoin', 'cancel'),
        ];

        $response = $sc_merchant_client->createOrder($order_data);

        if ($response instanceof ApiError || $response instanceof GenericError) {
            $logMessage = sprintf(
                'Error in SpectroCoin module: %s (Code: %s)',
                $response->getMessage(),
                $response->getCode()
            );
            PrestaShopLogger::addLog($logMessage, 3, null, 'SpectroCoinRedirectModuleFrontController', $cart->id, true);
            $this->renderResponseErrorCode($response->getCode(), $response->getMessage());
        } else {
            \Tools::redirect($response->getRedirectUrl());
        }
    }

    /**
     * Renders error response HTML.
     *
     * @param int $errorCode The error code
     * @param string $errorMessage The error message
     */
    protected function renderResponseErrorCode($errorCode, $errorMessage)
    {
        $shopLink = Context::getContext()->link->getPageLink('index');

        echo '<link rel="stylesheet" href="' . $this->module->getPathUri() . 'modules/spectrocoin/views/css/error-response.css" type="text/css" media="all" />';
        echo '
            <div class="container">
                <div class="content_container">
                    <div class="header_container">
                        <h3>Error: ' . $errorCode . ' ' . $errorMessage . '</h3>
                    </div>
                    <div class="content_content">
                        <div class="form_body">
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
