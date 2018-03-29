<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
{
  exit;
}

require_once _PS_MODULE_DIR_.'braintreepayment/vendor/autoload.php'; 

class BraintreePayment extends PaymentModule
{
	protected $isReady = false;

	public function __construct()
	{
		$this->name = 'braintreepayment';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'Mox Alibudbud';
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
		$this->controllers = array('validation');
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Braintree Payment');
		$this->description = $this->l('Payment gateway using Braintree');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

		if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

		if (!Configuration::get('BRAINTREE_PAYMENT_NAME'))
		  $this->warning = $this->l('No name provided');
	}

	public function install()
    {
        if (
            !parent::install() || 
            !$this->registerHook('paymentOptions') || 
            !$this->registerHook('paymentReturn') ||
            !$this->registerHook('header') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_NAME', 'Braintree Payment Gateway') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_ENVIRONMENT', 'sandbox') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID', '') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY', '') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY', '') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID', '') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY', '') ||
            !Configuration::updateValue('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY', '')
        ) {
            return false;
        }

        return true;
    }

	public function uninstall()
	{
	 	if (!parent::uninstall() ||
	    	!Configuration::deleteByName('BRAINTREE_PAYMENT_NAME') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_ENVIRONMENT') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_MERCHANT_ID') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_PUBLIC_KEY') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_PRIVATE_KEY') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY') ||
            !Configuration::deleteByName('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY')
	  	)
	    	return false;

		return true;
	}

	public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        if (Configuration::get('BRAINTREE_PAYMENT_ENVIRONMENT') == 'sandbox' && !$this->validateSandBoxCredentials()) {
        	return;
        }

        if (Configuration::get('BRAINTREE_PAYMENT_ENVIRONMENT') == 'production' && !$this->validateProductionCredentials()) {
        	return;
        }


        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText('Pay by Braintree')
                ->setForm($this->generateForm())
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        return [$newOption];
    }

    public function validateSandBoxCredentials(){
    	if (
    		!empty(Configuration::get('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID')) &&
    		!empty(Configuration::get('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY')) &&
    		!empty(Configuration::get('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY'))
    	) {
    		return true;
    	}
    	return false;
    }

    public function validateProductionCredentials(){
    	if (
    		!empty(Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID')) &&
    		!empty(Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY')) &&
    		!empty(Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY'))
    	) {
    		return true;
    	}
    	return false;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function generateForm()
    {	
    	if (Configuration::get('BRAINTREE_PAYMENT_ENVIRONMENT') == 'production') {
    		$environment = Configuration::get('BRAINTREE_PAYMENT_ENVIRONMENT');
    		$merchantId = Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID');
    		$publicKey = Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY');
    		$privateKey = Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY');
    	}else{
    		$environment = Configuration::get('BRAINTREE_PAYMENT_ENVIRONMENT');
    		$merchantId = Configuration::get('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID');
    		$publicKey = Configuration::get('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY');
    		$privateKey = Configuration::get('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY');
    	}

    	$gateway = new Braintree\Gateway([
		    'environment' => $environment,
		    'merchantId' => $merchantId,
		    'publicKey' => $publicKey,
		    'privateKey' => $privateKey,
		]);
        

		$cart = $this->context->cart;
        $currency_order = new Currency($cart->id_currency);
        
        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'amount' => $cart->getOrderTotal(true, Cart::BOTH),
            'client_token' => $gateway->ClientToken()->generate(),
            'currency' => $currency_order->iso_code,
            'environment' => $environment
        ]);

        return $this->context->smarty->fetch('module:braintreepayment/views/templates/front/form.tpl');
    }
    

	public function getContent()
	{
	    $output = null;
	    
	    if (Tools::isSubmit('submit'.$this->name))
	    {
            Configuration::updateValue('BRAINTREE_PAYMENT_ENVIRONMENT', Tools::getValue('BRAINTREE_PAYMENT_ENVIRONMENT'));
			
			// Production
			Configuration::updateValue('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID', Tools::getValue('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID'));
			Configuration::updateValue('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY', Tools::getValue('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY'));
			Configuration::updateValue('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY', Tools::getValue('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY'));

			// Sandbox
			Configuration::updateValue('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID', Tools::getValue('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID'));
			Configuration::updateValue('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY', Tools::getValue('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY'));
			Configuration::updateValue('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY', Tools::getValue('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY'));

            $output .= $this->displayConfirmation($this->l('Settings updated'));
	    }
	    return $output.$this->displayForm();
	}

	public function displayForm()
	{
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Production Credentials'),
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Merchant ID'),
					'name' => 'BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID',
					'required' => false
				),
				array(
					'type' => 'text',
					'label' => $this->l('Public Key'),
					'name' => 'BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY',
					'required' => false
				)
				,array(
					'type' => 'text',
					'label' => $this->l('Private Key'),
					'name' => 'BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY',
					'required' => false
				)
			)
		);

		$fields_form[1]['form'] = array(
			'legend' => array(
				'title' => $this->l('Sandbox Credentials'),
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Merchant ID'),
					'name' => 'BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID',
					'required' => false
				),
				array(
					'type' => 'text',
					'label' => $this->l('Public Key'),
					'name' => 'BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY',
					'required' => false
				)
				,array(
					'type' => 'text',
					'label' => $this->l('Private Key'),
					'name' => 'BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY',
					'required' => false
				)
			)
		);

		$fields_form[2]['form'] = array(
			'legend' => array(
				'title' => $this->l('Active environment'),
			),
			'input' => array(
				array(
					'type' => 'radio',
					'label' => $this->l('Environment'),
					'name' => 'BRAINTREE_PAYMENT_ENVIRONMENT',
					'required' => false,
					'values' => array(
						['id' => 'sandbox', 'value' => 'sandbox', 'label' => 'Sandbox'],
						['id' => 'production', 'value' => 'production', 'label' => 'Production']
					),
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'btn btn-default pull-right'
			)
		);

		$helper = new HelperForm();

		// module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;
		$helper->toolbar_scroll = true;
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
		'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
				'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
		'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		// Load current value
		$helper->fields_value['BRAINTREE_PAYMENT_ENVIRONMENT'] 	= Tools::getValue('BRAINTREE_PAYMENT_ENVIRONMENT', Configuration::get('BRAINTREE_PAYMENT_ENVIRONMENT'));

		$helper->fields_value['BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID'] 	= Tools::getValue('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID', Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_MERCHANT_ID'));
		$helper->fields_value['BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY'] 	= Tools::getValue('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY', Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_PRIVATE_KEY'));
		$helper->fields_value['BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY'] 	= Tools::getValue('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY', Configuration::get('BRAINTREE_PAYMENT_PRODUCTION_PUBLIC_KEY'));

		$helper->fields_value['BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID'] 	= Tools::getValue('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID', Configuration::get('BRAINTREE_PAYMENT_SANDBOX_MERCHANT_ID'));
		$helper->fields_value['BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY'] 	= Tools::getValue('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY', Configuration::get('BRAINTREE_PAYMENT_SANDBOX_PRIVATE_KEY'));
		$helper->fields_value['BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY'] 	= Tools::getValue('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY', Configuration::get('BRAINTREE_PAYMENT_SANDBOX_PUBLIC_KEY'));

		return $helper->generateForm($fields_form);
	}
	 
	public function hookDisplayHeader()
	{
		$this->context->controller->addCSS($this->_path.'css/braintreepayment.css', 'all');
		$this->context->controller->addJS($this->_path.'js/braintreepayment.js', 'all');
	}

}
