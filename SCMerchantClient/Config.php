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

namespace SpectroCoin\SCMerchantClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Config
{
    public const MERCHANT_API_URL = 'https://spectrocoin.com/api/public';
    public const AUTH_URL = 'https://spectrocoin.com/api/public/oauth/token';
    public const ACCEPTED_FIAT_CURRENCIES = ['EUR', 'USD', 'PLN', 'CHF', 'SEK', 'GBP', 'AUD', 'CAD', 'CZK', 'DKK', 'NOK'];
    public const PUBLIC_SPECTROCOIN_CERT_LOCATION = 'https://spectrocoin.com/files/merchant.public.pem';

    public const SPECTROCOIN_ACCESS_TOKEN_CONFIG_KEY = 'SPECTROCOIN_ACCESS_TOKEN';
}
