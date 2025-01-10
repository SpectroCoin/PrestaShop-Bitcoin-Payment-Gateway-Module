<?php

declare(strict_types=1);

namespace SpectroCoin\SCMerchantClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Config
{
    const MERCHANT_API_URL = 'https://pp.spectrocoin.com/api/public';
    const AUTH_URL = 'https://pp.spectrocoin.com/api/public/oauth/token';
    const ACCEPTED_FIAT_CURRENCIES = ["EUR", "USD", "PLN", "CHF", "SEK", "GBP", "AUD", "CAD", "CZK", "DKK", "NOK"];

    const SPECTROCOIN_ACCESS_TOKEN_CONFIG_KEY = 'SPECTROCOIN_ACCESS_TOKEN';
}
