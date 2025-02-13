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

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use SpectroCoin\SCMerchantClient\Config;

class SpectroCoin extends PaymentModule
{
    private string $_html = '';
    private array $_postErrors = [];
    private ?string $currency_code = null;

    protected string $project_id;
    protected string $client_id;
    protected string $client_secret;
    protected array $fields_form = [];

    public function __construct()
    {
        $shop = Context::getContext()->shop;
        $base_URL = $shop->getBaseURL();
        define('MODULE_ROOT_DIR', $base_URL);

        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.2.0.0',
        ];

        $this->name = 'spectrocoin';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'SpectroCoin';
        $this->controllers = ['payment', 'redirect', 'callback'];

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple([
            'SPECTROCOIN_PROJECT_ID',
            'SPECTROCOIN_CLIENT_ID',
            'SPECTROCOIN_CLIENT_SECRET',
            'SPECTROCOIN_CURRENCY_CODE',
        ]);

        if (!empty($config['SPECTROCOIN_PROJECT_ID'])) {
            $this->project_id = $config['SPECTROCOIN_PROJECT_ID'];
        }
        if (!empty($config['SPECTROCOIN_CLIENT_ID'])) {
            $this->client_id = $config['SPECTROCOIN_CLIENT_ID'];
        }
        if (!empty($config['SPECTROCOIN_CLIENT_SECRET'])) {
            $this->client_secret = $config['SPECTROCOIN_CLIENT_SECRET'];
        }
        if (!empty($config['SPECTROCOIN_CURRENCY_CODE'])) {
            $this->currency_code = $config['SPECTROCOIN_CURRENCY_CODE'];
        }

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('SpectroCoin Crypto Payment Gateway');
        $this->description = $this->l('Easily accept payments for your products by enabling SpectroCoin\'s seamless cryptocurrency transfer option.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        if ($this->currency_code && !Currency::getIdByIsoCode($this->currency_code)) {
            $this->warning = $this->l('Currency ' . $this->currency_code . ' is not configured.');
        }
    }

    public function install(): bool
    {
        if (!parent::install() || !$this->registerHook('payment') || !$this->registerHook('paymentOptions')) {

            return false;
        }

        if (!Configuration::get('SPECTROCOIN_PENDING')) {
            $OrderPending = new OrderState();
            $OrderPending->name = array_fill(0, 10, 'Awaiting SpectroCoin payment');
            $OrderPending->send_email = 0;
            $OrderPending->invoice = 0;
            $OrderPending->color = '#E59759';
            $OrderPending->unremovable = false;
            $OrderPending->logable = 0;

            if ($OrderPending->add()) {
                Configuration::updateValue('SPECTROCOIN_PENDING', $OrderPending->id);
            }
        }

        return true;
    }

    public function uninstall(): bool
    {
        if (!Configuration::deleteByName('SPECTROCOIN_PROJECT_ID') || !Configuration::deleteByName('SPECTROCOIN_CLIENT_ID') || !Configuration::deleteByName('SPECTROCOIN_CLIENT_SECRET') || !Configuration::deleteByName('SPECTROCOIN_CURRENCY_CODE') || !parent::uninstall()) {

            return false;
        }

        return true;
    }

    private function _postValidation(): void
    {
        if (\Tools::isSubmit('btnSubmit')) {
            if (!\Tools::getValue('SPECTROCOIN_PROJECT_ID')) {
                $this->_postErrors[] = $this->l('Project id is required.');
            } elseif (!\Tools::getValue('SPECTROCOIN_CLIENT_ID')) {
                $this->_postErrors[] = $this->l('Client id is required.');
            } elseif (!\Tools::getValue('SPECTROCOIN_CLIENT_SECRET')) {
                $this->_postErrors[] = $this->l('Client secret is required.');
            }
        }
    }

    private function _postProcess(): void
    {
        if (\Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('SPECTROCOIN_PROJECT_ID', (string) \Tools::getValue('SPECTROCOIN_PROJECT_ID'));
            Configuration::updateValue('SPECTROCOIN_CLIENT_ID', (string) \Tools::getValue('SPECTROCOIN_CLIENT_ID'));
            Configuration::updateValue('SPECTROCOIN_CURRENCY_CODE', (string) \Tools::getValue('SPECTROCOIN_CURRENCY_CODE'));

            $clientSecret = (string) \Tools::getValue('SPECTROCOIN_CLIENT_SECRET');
            if ($clientSecret) {
                Configuration::updateValue('SPECTROCOIN_CLIENT_SECRET', $clientSecret);
            }

            $title = (string) \Tools::getValue('SPECTROCOIN_TITLE');
            Configuration::updateValue('SPECTROCOIN_TITLE', $title ?: 'Pay with SpectroCoin', true);

            $description = (string) \Tools::getValue('SPECTROCOIN_DESCRIPTION');
            Configuration::updateValue('SPECTROCOIN_DESCRIPTION', $description ?: '');

            Configuration::updateValue('SPECTROCOIN_CHECKBOX', (int) \Tools::getValue('SPECTROCOIN_CHECKBOX'));
        }

        // Ensure a blank line before this statement for readability
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    public function getContent(): string
    {
        $output = '';
        if (\Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (empty($this->_postErrors)) {
                $this->_postProcess();
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            } else {
                foreach ($this->_postErrors as $err) {
                    $output .= $this->displayError($err);
                }
            }
        }
        $logoPath = $this->_path . 'views/img/spectrocoin-logo.svg';
        $this->context->smarty->assign([
            'logoPath' => $logoPath,
            'form' => $this->renderForm(),
            'configurationTitle' => $this->l('Configuration'),
            'introductionTitle' => $this->l('Introduction'),
            'introductionText' => $this->l(
                'The SpectroCoin plugin allows seamless integration of payment gateways into your website. '
                . 'To get started, you\'ll need to obtain the essential credentials: "Project id", "Client id", and "Client secret". '
                . 'These credentials are required to enable secure transactions between your website and the payment gateway. '
                . 'Follow the step-by-step tutorial below to acquire these credentials:'
            ),
            'tutorialSteps' => [
                sprintf(
                    '<a href="%s" target="_blank">%s</a> %s',
                    'https://auth.spectrocoin.com/signup',
                    $this->l('Sign up'),
                    $this->l('for a SpectroCoin Account.')
                ),
                sprintf(
                    '<a href="%s" target="_blank">%s</a> %s',
                    'https://auth.spectrocoin.com/login',
                    $this->l('Log in'),
                    $this->l('to your SpectroCoin account.')
                ),
                $this->l('On the dashboard, locate the Business tab and click on it.'),
                $this->l('Click on New project.'),
                $this->l('Fill in the project details and select desired settings (settings can be changed).'),
                $this->l('Click "Submit".'),
                $this->l('Copy and paste the "Project id".'),
                $this->l(
                    'Click on the user icon in the top right and navigate to Settings. '
                    . 'Then click on API and choose Create New API.'
                ),
                $this->l(
                    'Add "API name", in scope groups select "View merchant preorders", "Create merchant preorders", '
                    . '"View merchant orders", "Create merchant orders", "Cancel merchant orders" and click "Create API".'
                ),
                $this->l(
                    'Copy and store "Client id" and "Client secret". '
                    . 'Please be aware that the "Client secret" will be shown once, so it should be stored safely. '
                    . 'Lastly, save the settings.'
                ),
            ],
            'note' => $this->l('Keep in mind that if you want to use the business services of SpectroCoin, your account has to be verified.'),
            'contactInformation' => sprintf(
                '%s<br>%s %s',
                $this->l('Accept Bitcoin through SpectroCoin and receive payments in your chosen currency.'),
                $this->l('Still have questions? Contact us via'),
                sprintf(
                    '<a href="skype:spectrocoin_merchant?chat">%s</a> &middot; <a href="mailto:%s">%s</a>',
                    $this->l('skype: spectrocoin_merchant'),
                    'merchant@spectrocoin.com',
                    $this->l('email: merchant@spectrocoin.com'),
                ),
            ),
        ]);
        $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
    
        return $output;
    }
    
    public function hookPayment(array $params): string
    {
        if (!$this->active || !$this->checkFiatCurrency($params['cart'])) {
            error_log('[SpectroCoin Module] hookPayment: Module inactive or currency not accepted.');

            return '';
        }
    
        $this->smarty->assign([
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => \Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ]);
    
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookPaymentOptions(array $params): array
    {
        if (!$this->active || !$this->checkFiatCurrency($params['cart'])) {
            return [];
        }

        $langId = (int) Context::getContext()->language->id;

        $title = Configuration::get('SPECTROCOIN_TITLE', $langId);

        if (empty($title)) {
            $title = Configuration::get('SPECTROCOIN_TITLE');
        }

        if (empty($title)) {
            $sql = 'SELECT value FROM ' . _DB_PREFIX_ . 'configuration WHERE name = \'SPECTROCOIN_TITLE\'';
            $title = Db::getInstance()->getValue($sql);
        }

        if (empty($title)) {
            $title = $this->l('Pay with SpectroCoin');
        }

        $description = Configuration::get('SPECTROCOIN_DESCRIPTION', $langId);
        if ($description === false || empty($description)) {
            $description = Configuration::get('SPECTROCOIN_DESCRIPTION');
        }

        $iconUrl = $this->_path . '/views/img/spectrocoin-logo.svg';
        $show_logo = Configuration::get('SPECTROCOIN_CHECKBOX', 0) === '1';

        $new_option = new PaymentOption();
        $new_option->setCallToActionText($title)
                   ->setAction($this->context->link->getModuleLink($this->name, 'redirect', [], true))
                   ->setAdditionalInformation($description);

        if ($show_logo) {
            $new_option->setLogo($iconUrl);
        }

        return [$new_option];
    }

    public function checkFiatCurrency($cart)
    {
        $current_currency_iso_code = (new Currency($cart->id_currency))->iso_code;
        return in_array($current_currency_iso_code, Config::ACCEPTED_FIAT_CURRENCIES);
    }

    public function renderForm(): string
    {
        $fields_form = [
            'form' => [
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Project id'),
                        'name' => 'SPECTROCOIN_PROJECT_ID',
                        'hint'=> $this->l('Merchant id is obtained from SpectroCoin project settings.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client id'),
                        'name' => 'SPECTROCOIN_CLIENT_ID',
                        'hint' => $this->l('Client id is obtained from SpectroCoin API settings.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client secret'),
                        'name' => 'SPECTROCOIN_CLIENT_SECRET',
                        'hint' => $this->l('Client secret is obtained from SpectroCoin API settings, but is visible once, when API is created.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Title'),
                        'name' => 'SPECTROCOIN_TITLE',
                        'hint' => $this->l('This controls the title which the user sees during checkout. If left blank will display default title'),
                        'desc' => $this->l('Default: "Pay with SpectroCoin"'),
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('Description'),
                        'name' => 'SPECTROCOIN_DESCRIPTION',
                        'desc' => $this->l('Max: 80 characters.'),
                        'hint' => $this->l('This controls the description which the user sees during checkout. If left blank then will not be displayed'),
                        'maxlength' => 80,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Display logo'),
                        'name' => 'SPECTROCOIN_CHECKBOX',
                        'is_bool' => true,
                        'hint' => $this->l('Check if you want the SpectroCoin logo to be displayed in checkout.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->fields_value['SPECTROCOIN_CHECKBOX'] = Configuration::get('SPECTROCOIN_CHECKBOX', 0);
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            : 0;
        $this->fields_form = [];
        $helper->id = (int) \Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function renderStyle(): void
    {
        if (\Tools::getValue('configure') === $this->name) {
            $this->context->controller->addCSS($this->_path . '/views/css/settings.css', 'all');
        }
    }
    
    public function getConfigFieldsValues(): array
    {

        return [
            'SPECTROCOIN_PROJECT_ID' => \Tools::getValue('SPECTROCOIN_PROJECT_ID', Configuration::get('SPECTROCOIN_PROJECT_ID')),
            'SPECTROCOIN_CLIENT_ID' => \Tools::getValue('SPECTROCOIN_CLIENT_ID', Configuration::get('SPECTROCOIN_CLIENT_ID')),
            'SPECTROCOIN_CLIENT_SECRET' => \Tools::getValue('SPECTROCOIN_CLIENT_SECRET', Configuration::get('SPECTROCOIN_CLIENT_SECRET')),
            'SPECTROCOIN_CURRENCY_CODE' => \Tools::getValue('SPECTROCOIN_CURRENCY_CODE', Configuration::get('SPECTROCOIN_CURRENCY_CODE', 'EUR')),
            'SPECTROCOIN_TITLE' => \Tools::getValue('SPECTROCOIN_TITLE', Configuration::get('SPECTROCOIN_TITLE', '')),
            'SPECTROCOIN_DESCRIPTION' => \Tools::getValue('SPECTROCOIN_DESCRIPTION', Configuration::get('SPECTROCOIN_DESCRIPTION', '')),
            'SPECTROCOIN_CHECKBOX' => \Tools::getValue('SPECTROCOIN_CHECKBOX', Configuration::get('SPECTROCOIN_CHECKBOX', 0)),
        ];
    }
}
