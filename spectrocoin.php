<?php

//spectrocoin.php

if (!defined('_PS_VERSION_'))
  exit;

class SpectroCoin extends PaymentModule
{
  private $_html = '';
  private $_postErrors = array();

  public $merchant_api_url = 'https://test.spectrocoin.com/api/public';
  public $auth_url = 'https://test.spectrocoin.com/api/public/oauth/token';
  public $project_id;
  public $client_id;
  public $client_secret;

  public $currency_code;
  public $lang;

  public $moduleRootDir;

  public $acceptedCurrencies = array();

  public function __construct()
  {
    $shop = Context::getContext()->shop;
    $baseURL = $shop->getBaseURL();

    define('MODULE_ROOT_DIR',  $baseURL);

    $this->name = 'spectrocoin';
    $this->tab = 'payments_gateways';
    $this->version = '2.0.0';
    $this->author = 'SpectroCoin';
    $this->controllers = array('payment', 'redirect', 'callback');

    $this->currencies = true;
    $this->currencies_mode = 'checkbox';

    $config = Configuration::getMultiple(
      array(
        'SPECTROCOIN_PROJECT_ID',
        'SPECTROCOIN_CLIENT_ID',
        'SPECTROCOIN_CLIENT_SECRET',
        'SPECTROCOIN_CURRENCY_CODE',
        'SPECTROCOIN_LANG',
      )
    );

    if (!empty($config['SPECTROCOIN_PROJECT_ID']))
      $this->project_id = $config['SPECTROCOIN_PROJECT_ID'];
    if (!empty($config['SPECTROCOIN_CLIENT_ID']))
      $this->client_id = $config['SPECTROCOIN_CLIENT_ID'];
    if (!empty($config['SPECTROCOIN_CLIENT_SECRET']))
      $this->client_secret = $config['SPECTROCOIN_CLIENT_SECRET'];
    if (!empty($config['SPECTROCOIN_CURRENCY_CODE']))
      $this->currency_code = $config['SPECTROCOIN_CURRENCY_CODE'];
    if (!empty($config['SPECTROCOIN_LANG']))
      $this->lang = $config['SPECTROCOIN_LANG'];

    $this->bootstrap = true;
    parent::__construct();

    $this->displayName = $this->l('SpectroCoin Crypto Payment Gateway');
    $this->description = $this->l('Easily accept payments for your products by enabling SpectroCoin\'s seamless cryptocurrency transfer option.');
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    if (!count(Currency::checkPaymentCurrencies($this->id)))
      $this->warning = $this->l('No currency has been set for this module.');

    if ($this->currency_code && !Currency::getIdByIsoCode($this->currency_code))
      $this->warning = $this->l('Currency ' . $this->currency_code . ' is not configured.');
  }

  public function install()
  {
    if (
      !parent::install()
      || !$this->registerHook('payment')
      || !$this->registerHook('displayPaymentEU')
      || !$this->registerHook('paymentReturn')
      || !$this->registerHook('paymentOptions')
    ) {
      return false;
    }

    if (!Configuration::get('SPECTROCOIN_PENDING')) {
      /* add pending order state */
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

  public function uninstall()
  {
    if (
      !Configuration::deleteByName('SPECTROCOIN_PROJECT_ID')
      || !Configuration::deleteByName('SPECTROCOIN_CLIENT_ID')
      || !Configuration::deleteByName('SPECTROCOIN_CLIENT_SECRET')
      || !Configuration::deleteByName('SPECTROCOIN_CURRENCY_CODE')
      || !Configuration::deleteByName('SPECTROCOIN_LANG')
      || !parent::uninstall()
    )
      return false;
    return true;
  }

  private function _postValidation()
  {
    if (Tools::isSubmit('btnSubmit')) {
      if (!Tools::getValue('SPECTROCOIN_PROJECT_ID'))
        $this->_postErrors[] = $this->l('Project id is required.');
      elseif (!Tools::getValue('SPECTROCOIN_CLIENT_ID'))
        $this->_postErrors[] = $this->l('Client id is required.');
      elseif (!Tools::getValue('SPECTROCOIN_CLIENT_SECRET'))
        $this->_postErrors[] = $this->l('Client secret is required.');
    }
  }



  private function _postProcess()
  {
    if (Tools::isSubmit('btnSubmit')) {
      Configuration::updateValue('SPECTROCOIN_PROJECT_ID', Tools::getValue('SPECTROCOIN_PROJECT_ID'));
      Configuration::updateValue('SPECTROCOIN_CLIENT_ID', Tools::getValue('SPECTROCOIN_CLIENT_ID'));
      Configuration::updateValue('SPECTROCOIN_CURRENCY_CODE', Tools::getValue('SPECTROCOIN_CURRENCY_CODE'));
      Configuration::updateValue('SPECTROCOIN_LANG', Tools::getValue('SPECTROCOIN_LANG'));
      if (Tools::getValue('SPECTROCOIN_CLIENT_SECRET')) {
        Configuration::updateValue('SPECTROCOIN_CLIENT_SECRET', Tools::getValue('SPECTROCOIN_CLIENT_SECRET'));
      }
      if (Tools::getValue('SPECTROCOIN_TITLE')) {
        Configuration::updateValue('SPECTROCOIN_TITLE', Tools::getValue('SPECTROCOIN_TITLE'));
      } else {
        Configuration::updateValue('SPECTROCOIN_TITLE', "Pay with SpectroCoin");
      }
      if (Tools::getValue('SPECTROCOIN_DESCRIPTION')) {
        Configuration::updateValue('SPECTROCOIN_DESCRIPTION', Tools::getValue('SPECTROCOIN_DESCRIPTION'));
      } else {
        Configuration::updateValue('SPECTROCOIN_DESCRIPTION', '');
      }
      Configuration::updateValue('SPECTROCOIN_CHECKBOX', (int) Tools::getValue('SPECTROCOIN_CHECKBOX'));
    }
    $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
  }


  public function getContent()
  {
    ob_start();

    if (Tools::isSubmit('btnSubmit')) {
      $this->_postValidation();
      if (!count($this->_postErrors))
        $this->_postProcess();
      else
        foreach ($this->_postErrors as $err)
          $this->displayError($err);
    } else {
      echo '<br />';
    }

    $logoPath = $this->_path . '/views/img/spectrocoin-logo.svg';

    if (!empty($this->_html)) {
      echo $this->_html;
    }
    ?>

    <div class="spectrocoin-settings flex-container">
      <div class="flex-col-1 flex-col">
        <div>
          <h4><b>Configuration</b></h4>
        </div>
        <div class="form">
          <?php
          echo $this->renderForm();
          echo $this->renderStyle();
          ?>
        </div>
      </div>
      <div class="flex-col-2 flex-col">
        <div class="logo-container">
          <a href="https://spectrocoin.com/" target="_blank">
            <img class="logo" src="<?php echo $logoPath; ?>" alt="SpectroCoin Logo">
          </a>
        </div>
        <div class="introduction">
          <p>
          <h4><b>Introduction</b></h4>
          </p>
          <p>The Spectroin plugin allows seamless integration of payment gateways into your Prestashop website. To get
            started, you will need to obtain the essential credentials: project id, client id, and client secret. These
            credentials are required to enable secure transactions between your website and the payment gateway. Follow the
            step-by-step tutorial below to acquire these credentials:</p>
          <ul>
            <li>1. <b><a href="https://auth.spectrocoin.com/signup" target="_blank">Sign up</a></b> for a Spectroin Account.</li>
            <li>2. <b><a href="https://auth.spectrocoin.com/login" target="_blank">Log in</a></b> to your Spectroin account.</li>
            <li>3. On the dashboard, locate the "<b><a href="https://spectrocoin.com/en/merchants/projects"
                  target="_blank">Business</a></b>" tab and click on it.</li>
            <li>4. Click on "<b><a href="https://spectrocoin.com/en/merchants/projects/new" target="_blank">New
                  project</a></b>".</li>
            <li>5. Fill in the project details and select desired settings (settings can be changed).</li>
            <li>6. The <b>Private</b> and <b>Public keys</b> are obtained from your merchant project's settings page. Private key is only displayed once when the project is created, but can be newly generated by pressing on <b>"Generate"</b> button below your Public key field. Copy the newly generated private and public keys and store them in module settings.</li>
            <li>7. Click "<b>Submit</b>" to save the project and then click "<b>Close</b>".</li>
            <li>8. Select the option "<b><a href = "https://spectrocoin.com/en/merchants/projects">All projects</a></b>" and choose your project.</li>
            <li>9. In module settings fill the <b>merchant id</b> and <b>project id</b>.</li>
          </ul>
          <p><b>Note:</b> Keep in mind that if you want to use the business services of SpectroCoin, your account has to be
            verified.</p>
            




        </div>
      </div>
      <div class="flex-footer">
        <h4>Still have questions?</h4>
        <p> Contact us via skype: <a href="skype:spectrocoin_merchant?chat">spectrocoin_merchant</a> or email: <a
            href="mailto:merchant@spectrocoin.com">merchant@spectrocoin.com</a></p>
      </div>
    </div>

    <?php

    $content = ob_get_clean();
    return $content;
  }

  public function hookPayment($params)
  {
    if (!$this->active)
      return;
    if (!$this->checkCurrency($params['cart']))
      return;

    $this->smarty->assign(
      array(
        'this_path' => $this->_path,
        'this_path_bw' => $this->_path,
        'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
      )
    );
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

    $title = Configuration::get('SPECTROCOIN_TITLE', $this->l('Pay with SpectroCoin'));
    $description = Configuration::get('SPECTROCOIN_DESCRIPTION', '');
    $iconUrl = $this->_path . '/views/img/bitcoin.png';

    $showLogo = Configuration::get('SPECTROCOIN_CHECKBOX', 0) == 1 ? true : false;

    $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    $newOption->setCallToActionText($title)
      ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
      ->setAdditionalInformation($description);

    if ($showLogo) {
      $newOption->setLogo($iconUrl);
    }

    $payment_options = [
      $newOption,
    ];

    return $payment_options;
  }

  //TODO when sc API will be updated, get currencies from API
  public function checkCurrency($cart)
  {
      $currentCurrencyIsoCode = (new Currency($cart->id_currency))->iso_code;

      $jsonFile = file_get_contents(_PS_MODULE_DIR_ . $this->name . '/SCMerchantClient/data/acceptedCurrencies.JSON'); 
      $acceptedCurrencies = json_decode($jsonFile, true);

      if (in_array($currentCurrencyIsoCode, $acceptedCurrencies)) {
        return true;
      } else {
        return false;
      }

  }

  public function renderForm()
  {
    $fields_form = array(
      'form' => array(
        'input' => array(
          array(
            'type' => 'text',
            'label' => $this->l('Merchant Id'),
            'name' => 'SPECTROCOIN_PROJECT_ID',
            'hint' => $this->l('Enter your Merchant ID here.'),
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Project Id'),
            'name' => 'SPECTROCOIN_CLIENT_ID',
            'hint' => $this->l('Enter your Project ID here.'),
          ),
          array(
            'type' => 'textarea',
            'label' => $this->l('Private key'),
            'name' => 'SPECTROCOIN_CLIENT_SECRET',
            'desc' => $this->l('If you have already entered your private key before, you should leave this field blank, unless you want to change the stored private key.'),
            'hint' => $this->l('Enter your Private Key here.'),
            'class' => 'resizable-textarea'
          ),
          array(
            'type' => 'text',
            'label' => $this->l('Title'),
            'name' => 'SPECTROCOIN_TITLE',
            'hint' => $this->l('This controls the title which the user sees during checkout. If left blank will display default title'),
            'desc' => $this->l('Default: "Pay with SpectroCoin"')
          ),
          array(
            'type' => 'textarea',
            'label' => $this->l('Description'),
            'name' => 'SPECTROCOIN_DESCRIPTION',
            'desc' => $this->l('Max: 80 characters.'),
            'hint' => $this->l('This controls the description which the user sees during checkout. If left blank then will not be displayed'),
            'maxlength' => 80
          ),
          array(
            'type' => 'select',
            'label' => $this->l('Language for response'),
            'name' => 'SPECTROCOIN_LANG',
            'options' => array(
              'query' => array(
                array('key' => 'en', 'value' => 'en'),
                array('key' => 'et', 'value' => 'et'),
                array('key' => 'es', 'value' => 'es'),
                array('key' => 'ar', 'value' => 'ar'),
                array('key' => 'it', 'value' => 'it'),
                array('key' => 'fr', 'value' => 'fr'),
                array('key' => 'ru', 'value' => 'ru'),
                array('key' => 'lt', 'value' => 'lt'),
              ),
              'id' => 'key',
              'name' => 'value'
            ),
            'hint' => $this->l('Select the language, which will be displayed in pre-order view.'),
          ),
          array(
            'type' => 'switch',
            'label' => $this->l('Display logo'),
            'name' => 'SPECTROCOIN_CHECKBOX',
            'is_bool' => true,
            'hint' => $this->l('Check if you want the SpectroCoin logo to be displayed in checkout.'),
            'values' => array(
              array(
                'id' => 'active_on',
                'value' => 1,
              ),
              array(
                'id' => 'active_off',
                'value' => 0,
              )
            ),
          ),

        ),
        'submit' => array(
          'title' => $this->l('Save'),
        ),
      ),
    );


    $helper = new HelperForm();
    $helper->fields_value['SPECTROCOIN_CHECKBOX'] = Configuration::get('SPECTROCOIN_CHECKBOX', 0);
    $helper->show_toolbar = false;
    $helper->table = $this->table;
    $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
    $helper->default_form_language = $lang->id;
    $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
    $this->fields_form = array();
    $helper->id = (int) Tools::getValue('id_carrier');
    $helper->identifier = $this->identifier;
    $helper->submit_action = 'btnSubmit';
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->tpl_vars = array(
      'fields_value' => $this->getConfigFieldsValues(),
      'languages' => $this->context->controller->getLanguages(),
      'id_language' => $this->context->language->id
    );

    return $helper->generateForm(array($fields_form));
  }

  public function renderStyle()
  {
    if (Tools::getValue('configure') == $this->name) {
      $this->context->controller->addCSS($this->_path . '/views/css/settings.css', 'all');
    }
  }

  public function getConfigFieldsValues()
  {
    return array(
      'SPECTROCOIN_PROJECT_ID' => Tools::getValue('SPECTROCOIN_PROJECT_ID', Configuration::get('SPECTROCOIN_PROJECT_ID')),
      'SPECTROCOIN_CLIENT_ID' => Tools::getValue('SPECTROCOIN_CLIENT_ID', Configuration::get('SPECTROCOIN_CLIENT_ID')),
      'SPECTROCOIN_CURRENCY_CODE' => Tools::getValue('SPECTROCOIN_CURRENCY_CODE', Configuration::get('SPECTROCOIN_CURRENCY_CODE', 'EUR')),
      'SPECTROCOIN_LANG' => Tools::getValue('SPECTROCOIN_LANG', Configuration::get('SPECTROCOIN_LANG', 'en')),
      'SPECTROCOIN_CLIENT_SECRET' => Tools::getValue('SPECTROCOIN_CLIENT_SECRET', ''),
      'SPECTROCOIN_TITLE' => Tools::getValue('SPECTROCOIN_TITLE', Configuration::get('SPECTROCOIN_TITLE', '')),
      'SPECTROCOIN_DESCRIPTION' => Tools::getValue('SPECTROCOIN_DESCRIPTION', Configuration::get('SPECTROCOIN_DESCRIPTION', '')),
      'SPECTROCOIN_CHECKBOX' => Tools::getValue('SPECTROCOIN_CHECKBOX', Configuration::get('SPECTROCOIN_CHECKBOX', 0)),
    );
  }

}