<?php

if (!defined('_PS_VERSION_'))
  exit;

class SpectroCoin extends PaymentModule
{
  private $_html = '';
  private $_postErrors = array();

  public $merchantId;
  public $apiId;
  public $currency_code;
  public $culture;
  public $private_key;

  public $SC_API_URL = 'https://spectrocoin.com/api/merchant/1';

  public function __construct()
  {
    $this->name = 'spectrocoin';
    $this->tab = 'payments_gateways';
    $this->version = '0.1';
    $this->author = 'UAB Spectro Finance';
    $this->controllers = array('payment', 'redirect', 'callback');

    $this->currencies = true;
    $this->currencies_mode = 'checkbox';

    $config = Configuration::getMultiple(array(
      'SPECTROCOIN_MERCHANTID',
      'SPECTROCOIN_APIID',
      'SPECTROCOIN_CURRENCY_CODE',
      'SPECTROCOIN_CULTURE',
      'SPECTROCOIN_PRIVATE_KEY',
    ));

    if (!empty($config['SPECTROCOIN_MERCHANTID']))
      $this->merchantId = $config['SPECTROCOIN_MERCHANTID'];
    if (!empty($config['SPECTROCOIN_APIID']))
      $this->apiId = $config['SPECTROCOIN_APIID'];
    if (!empty($config['SPECTROCOIN_CURRENCY_CODE']))
      $this->currency_code = $config['SPECTROCOIN_CURRENCY_CODE'];
    if (!empty($config['SPECTROCOIN_CULTURE']))
      $this->culture = $config['SPECTROCOIN_CULTURE'];
    if (!empty($config['SPECTROCOIN_PRIVATE_KEY']))
      $this->private_key = $config['SPECTROCOIN_PRIVATE_KEY'];

    $this->bootstrap = true;
    parent::__construct();

    $this->displayName = $this->l('SpectroCoin');
    $this->description = $this->l('Accept payments for your products via SpectroCoin bitcoin transfer.');
    $this->confirmUninstall = $this->l('Are you sure about removing these details?');
    if (!count(Currency::checkPaymentCurrencies($this->id)))
      $this->warning = $this->l('No currency has been set for this module.');

    if($this->currency_code && !Currency::getIdByIsoCode($this->currency_code))
      $this->warning = $this->l('Currency '.$this->currency_code.' is not configured.');
  }

  public function install()
  {
          if (!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('displayPaymentEU')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')) {
            return false;
        }

    if(!Configuration::get('SPECTROCOIN_PENDING')){
      /* add pending order state */
      $OrderPending              = new OrderState();
      $OrderPending->name        = array_fill(0, 10, 'Awaiting SpectroCoin payment');
      $OrderPending->send_email  = 0;
      $OrderPending->invoice     = 0;
      $OrderPending->color       = '#E59759';
      $OrderPending->unremovable = false;
      $OrderPending->logable     = 0;

      if ($OrderPending->add()) {
        Configuration::updateValue('SPECTROCOIN_PENDING', $OrderPending->id);
      }
    }

    return true;
  }

  public function uninstall()
  {
    if (
      !Configuration::deleteByName('SPECTROCOIN_MERCHANTID')
        || !Configuration::deleteByName('SPECTROCOIN_APIID')
        || !Configuration::deleteByName('SPECTROCOIN_CURRENCY_CODE')
        || !Configuration::deleteByName('SPECTROCOIN_CULTURE')
        || !Configuration::deleteByName('SPECTROCOIN_PRIVATE_KEY')
        || !parent::uninstall())
      return false;
    return true;
  }

  private function _postValidation()
  {
    if (Tools::isSubmit('btnSubmit'))
    {
      if (!Tools::getValue('SPECTROCOIN_MERCHANTID'))
        $this->_postErrors[] = $this->l('Merchant ID is required.');
      elseif (!Tools::getValue('SPECTROCOIN_APIID'))
        $this->_postErrors[] = $this->l('Project Id is required.');
    }
  }

  private function _postProcess()
  {
    if (Tools::isSubmit('btnSubmit'))
    {
      Configuration::updateValue('SPECTROCOIN_MERCHANTID', Tools::getValue('SPECTROCOIN_MERCHANTID'));
      Configuration::updateValue('SPECTROCOIN_APIID', Tools::getValue('SPECTROCOIN_APIID'));
      Configuration::updateValue('SPECTROCOIN_CURRENCY_CODE', Tools::getValue('SPECTROCOIN_CURRENCY_CODE'));
      Configuration::updateValue('SPECTROCOIN_CULTURE', Tools::getValue('SPECTROCOIN_CULTURE'));
      if(Tools::getValue('SPECTROCOIN_PRIVATE_KEY')){
        Configuration::updateValue('SPECTROCOIN_PRIVATE_KEY', Tools::getValue('SPECTROCOIN_PRIVATE_KEY'));
      }
    }
    $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
  }

  private function displaySpectrocoin()
  {
	  return $this->display(__FILE__, 'infos.tpl');
  }

  public function getContent()
  {
    if (Tools::isSubmit('btnSubmit'))
    {
      $this->_postValidation();
      if (!count($this->_postErrors))
        $this->_postProcess();
      else
        foreach ($this->_postErrors as $err)
          $this->_html .= $this->displayError($err);
    }
    else {
      $this->_html .= '<br />';
	}
	$this->html .= $this->displaySpectrocoin();
    $this->_html .= $this->renderForm();

    return $this->_html;
  }

  public function hookPayment($params)
  {
    if (!$this->active)
      return;
    if (!$this->checkCurrency($params['cart']))
      return;

    $this->smarty->assign(array(
      'this_path' => $this->_path,
      'this_path_bw' => $this->_path,
      'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
    ));
    return $this->display(__FILE__, 'payment.tpl');
  }

  public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText('Pay with Bitcoin via SpectroCoin.com')
                      ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
                      ->setAdditionalInformation($this->context->smarty->fetch('module:spectrocoin/views/templates/hook/spectrocoin_intro.tpl'));

        $payment_options = [
            $newOption,
        ];

        return $payment_options;
    }

  public function checkCurrency($cart)
  {
    $currency_order = new Currency($cart->id_currency);
    $currencies_module = $this->getCurrency($cart->id_currency);

    if (is_array($currencies_module))
      foreach ($currencies_module as $currency_module)
        if ($currency_order->id == $currency_module['id_currency'])
          return true;
    return false;
  }

  public function renderForm()
  {
    $fields_form = array(
      'form' => array(
        'input' => array(
          array(
            'type' => 'text',
            'label' => $this->l('Merchant Id'),
            'name' => 'SPECTROCOIN_MERCHANTID',
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Project Id'),
            'name' => 'SPECTROCOIN_APIID',
          ),
          array(
            'type' => 'select',
            'label' => $this->l('Language for response'),
            'name' => 'SPECTROCOIN_CULTURE',
            'options' => array(
              'query' => array(
                array('key' => 'en', 'value' => 'en'),
                array('key' => 'lt', 'value' => 'lt'),
                array('key' => 'ru', 'value' => 'ru'),
                array('key' => 'de', 'value' => 'de'),
              ),
              'id' => 'key',
              'name' => 'value'
            ),
          ),
          array(
            'type' => 'textarea',
            'label' => $this->l('Private key'),
            'name' => 'SPECTROCOIN_PRIVATE_KEY',
            'desc' => $this->l('If you have already entered your private key before, you should leave this field blank, unless you want to change the stored private key.'),
          ),
        ),
        'submit' => array(
          'title' => $this->l('Save'),
        )
      ),
    );

    $helper = new HelperForm();
    $helper->show_toolbar = false;
    $helper->table =  $this->table;
    $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
    $helper->default_form_language = $lang->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
    $this->fields_form = array();
    $helper->id = (int)Tools::getValue('id_carrier');
    $helper->identifier = $this->identifier;
    $helper->submit_action = 'btnSubmit';
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->tpl_vars = array(
      'fields_value' => $this->getConfigFieldsValues(),
      'languages' => $this->context->controller->getLanguages(),
      'id_language' => $this->context->language->id
    );

    return $helper->generateForm(array($fields_form));
  }

  public function getConfigFieldsValues()
  {
    return array(
      'SPECTROCOIN_MERCHANTID' => Tools::getValue('SPECTROCOIN_MERCHANTID', Configuration::get('SPECTROCOIN_MERCHANTID')),
      'SPECTROCOIN_APIID' => Tools::getValue('SPECTROCOIN_APIID', Configuration::get('SPECTROCOIN_APIID')),
      'SPECTROCOIN_CURRENCY_CODE' => Tools::getValue('SPECTROCOIN_CURRENCY_CODE', Configuration::get('SPECTROCOIN_CURRENCY_CODE', 'EUR')),
      'SPECTROCOIN_CULTURE' => Tools::getValue('SPECTROCOIN_CULTURE', Configuration::get('SPECTROCOIN_CULTURE', 'en')),
      'SPECTROCOIN_PRIVATE_KEY' => Tools::getValue('SPECTROCOIN_PRIVATE_KEY', ''),
    );
  }
}