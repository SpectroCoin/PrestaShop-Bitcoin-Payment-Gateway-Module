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

namespace SpectroCoin\SCMerchantClient\Http;

use SpectroCoin\SCMerchantClient\Utils;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CreateOrderResponse
{
    private ?string $preOrderId;
    private ?string $orderId;
    private ?string $validUntil;
    private ?string $payCurrencyCode;
    private ?string $payNetworkCode;
    private ?string $receiveCurrencyCode;
    private ?string $payAmount;
    private ?string $receiveAmount;
    private ?string $depositAddress;
    private ?string $memo;
    private ?string $redirectUrl;

    /**
     * Constructor to initialize order response properties.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->preOrderId = isset($data['preOrderId']) ? Utils::sanitize_text_field((string) $data['preOrderId']) : null;
        $this->orderId = isset($data['orderId']) ? Utils::sanitize_text_field((string) $data['orderId']) : null;
        $this->validUntil = isset($data['validUntil']) ? Utils::sanitize_text_field((string) $data['validUntil']) : null;
        $this->payCurrencyCode = isset($data['payCurrencyCode']) ? Utils::sanitize_text_field((string) $data['payCurrencyCode']) : null;
        $this->payNetworkCode = isset($data['payNetworkCode']) ? Utils::sanitize_text_field((string) $data['payNetworkCode']) : null;
        $this->receiveCurrencyCode = isset($data['receiveCurrencyCode']) ? Utils::sanitize_text_field((string) $data['receiveCurrencyCode']) : null;
        $this->payAmount = isset($data['payAmount']) ? Utils::sanitize_text_field((string) $data['payAmount']) : null;
        $this->receiveAmount = isset($data['receiveAmount']) ? Utils::sanitize_text_field((string) $data['receiveAmount']) : null;
        $this->depositAddress = isset($data['depositAddress']) ? Utils::sanitize_text_field((string) $data['depositAddress']) : null;
        $this->memo = isset($data['memo']) ? Utils::sanitize_text_field((string) $data['memo']) : null;
        $this->redirectUrl = isset($data['redirectUrl']) ? Utils::sanitizeUrl((string) $data['redirectUrl']) : null;

        $validation = $this->validate();
        if (is_array($validation)) {
            $errorMessage = 'Invalid order creation payload. Failed fields: ' . implode(', ', $validation);
            throw new \InvalidArgumentException($errorMessage);
        }
    }

    /**
     * Validate the data for create order API response.
     *
     * @return bool|array True if validation passes, otherwise an array of error messages
     */
    public function validate(): bool|array
    {
        $errors = [];

        if (empty($this->getPreOrderId())) {
            $errors[] = 'preOrderId is empty';
        }
        if (empty($this->getOrderId())) {
            $errors[] = 'orderId is empty';
        }
        if (strlen($this->getReceiveCurrencyCode()) !== 3) {
            $errors[] = 'receiveCurrencyCode is not 3 characters long';
        }
        if ($this->getReceiveAmount() === null || (float) $this->getReceiveAmount() <= 0) {
            $errors[] = 'receiveAmount is not a valid positive number';
        }
        if (!filter_var($this->getRedirectUrl(), FILTER_VALIDATE_URL)) {
            $errors[] = 'redirectUrl is not a valid URL';
        }

        return empty($errors) ? true : $errors;
    }

    public function getPreOrderId()
    {
        return $this->preOrderId;
    }
    public function getOrderId()
    {
        return $this->orderId;
    }
    public function getValidUntil()
    {
        return $this->validUntil;
    }
    public function getPayCurrencyCode()
    {
        return $this->payCurrencyCode;
    }
    public function getPayNetworkCode()
    {
        return $this->payNetworkCode;
    }
    public function getReceiveCurrencyCode()
    {
        return $this->receiveCurrencyCode;
    }
    public function getPayAmount()
    {
        return $this->payAmount;
    }
    public function getReceiveAmount()
    {
        return $this->receiveAmount;
    }
    public function getDepositAddress()
    {
        return $this->depositAddress;
    }
    public function getMemo()
    {
        return $this->memo;
    }
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }
}
