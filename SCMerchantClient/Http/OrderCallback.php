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
use SpectroCoin\SCMerchantClient\Config;
use Exception;
use InvalidArgumentException;
// @codeCoverageIgnoreStart
if (!defined('_PS_VERSION_')) {
    exit;
}
// @codeCoverageIgnoreEnd

class OrderCallback
{
    private ?string $uuid;
    private ?string $merchantApiId;

    public function __construct(?string $uuid, ?string $merchantApiId)
    {
        $this->uuid = isset($uuid) ? Utils::sanitize_text_field((string)$uuid) : null;
        $this->merchantApiId = isset($merchantApiId) ? Utils::sanitize_text_field((string)$merchantApiId) : null;

        $validation_result = $this->validate();
        if (is_array($validation_result)) {
            $errorMessage = 'Invalid order callback. Failed fields: ' . implode(', ', $validation_result);
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * Validate the input data.
     *
     * @return bool|array True if validation passes, otherwise an array of error messages.
     */
    private function validate(): bool|array
    {
        $errors = [];

        if (empty($this->getUuid())) {
            $errors[] = 'Uuid is empty';
        }

        if (empty($this->getmerchantApiId())) {
            $errors[] = 'merchantApiId is empty';
        }

        return empty($errors) ? true : $errors;
    }

    public function getUuid()
    {
        return $this->uuid;
    }
    public function getmerchantApiId()
    {
        return $this->merchantApiId;
    }
}
