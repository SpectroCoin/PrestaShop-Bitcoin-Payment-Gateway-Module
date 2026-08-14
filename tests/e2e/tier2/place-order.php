<?php
/**
 * Prepares a real PrestaShop order attributed to this module, and creates the
 * matching SpectroCoin order through the module's own HTTP client.
 *
 * Scope, stated honestly: the order row is cloned from PrestaShop's own demo
 * order rather than built by validateOrder(), and the redirect controller is
 * not run. Both need the Symfony container, which is null outside an HTTP
 * request, so driving them needs a browser session — that is Tier 3.
 *
 * That limitation is confined to the fixture. The code actually under test —
 * the callback controller — runs over real HTTP against this order, with the
 * full framework behind it, which is where the interesting behaviour lives.
 *
 * The SpectroCoin order IS created by the module's own SCMerchantClient with
 * the payload the redirect controller assembles, so the outbound request,
 * its credentials, its User-Agent and the response parsing are all genuine.
 */

declare(strict_types=1);

require '/var/www/html/config/config.inc.php';

use SpectroCoin\SCMerchantClient\SCMerchantClient;

$db = Db::getInstance();

$template = (int) $db->getValue('SELECT id_order FROM ' . _DB_PREFIX_ . 'orders ORDER BY id_order');
if (!$template) {
    fwrite(STDERR, "no existing order to clone a fixture from\n");
    exit(2);
}

// Clone every column but the key, so the row is as valid as the shop's own.
$columns = array_column($db->executeS('SHOW COLUMNS FROM ' . _DB_PREFIX_ . 'orders'), 'Field');
$columns = array_values(array_filter($columns, static fn ($c) => $c !== 'id_order'));
$list    = '`' . implode('`,`', $columns) . '`';

$db->execute('INSERT INTO ' . _DB_PREFIX_ . "orders ($list) SELECT $list FROM " . _DB_PREFIX_ . "orders WHERE id_order = $template");
$orderId = (int) $db->Insert_ID();

$pending = (int) Configuration::get('SPECTROCOIN_PENDING');
$db->execute('UPDATE ' . _DB_PREFIX_ . "orders
    SET module = 'spectrocoin', payment = 'SpectroCoin', current_state = $pending,
        reference = 'TIER2$orderId'
    WHERE id_order = $orderId");

$total    = (float) $db->getValue('SELECT total_paid FROM ' . _DB_PREFIX_ . "orders WHERE id_order = $orderId");
$currency = new Currency((int) $db->getValue('SELECT id_currency FROM ' . _DB_PREFIX_ . "orders WHERE id_order = $orderId"));

$module = Module::getInstanceByName('spectrocoin');
if (!$module) {
    fwrite(STDERR, "spectrocoin module is not installed\n");
    exit(2);
}

// The URLs the redirect controller builds. Link needs no container.
$context       = Context::getContext();
$context->link = new Link();

$client = new SCMerchantClient(
    $module->project_id,
    $module->client_id,
    $module->client_secret
);

$suffix   = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 5);
$response = $client->createOrder([
    'orderId'             => $orderId . '-' . $suffix,
    'description'         => 'Order #' . $orderId,
    'receiveAmount'       => round($total, 2),
    'receiveCurrencyCode' => $currency->iso_code,
    'callbackUrl'         => $context->link->getModuleLink('spectrocoin', 'callback'),
    'successUrl'          => $context->link->getModuleLink('spectrocoin', 'validation'),
    'failureUrl'          => $context->link->getModuleLink('spectrocoin', 'cancel'),
]);

file_put_contents('/tmp/tier2-order.json', json_encode([
    'order_id' => $orderId,
    'total'    => round($total, 2),
    'currency' => $currency->iso_code,
    'redirect' => is_object($response) && method_exists($response, 'getRedirectUrl')
        ? (string) $response->getRedirectUrl() : '',
    'error'    => is_object($response) && method_exists($response, 'getMessage')
        ? (string) $response->getMessage() : '',
]));
