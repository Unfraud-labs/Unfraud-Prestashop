<?php

if (!defined('_PS_VERSION_'))
    exit;

class Unfraud extends Module
{
    const UNFRAUD_EMAIL = "UNFRAUD_EMAIL";
    const UNFRAUD_PASSWORD = "UNFRAUD_PASSWORD";
    const UNFRAUD_APIKEY = "UNFRAUD_APIKEY";
    const UNFRAUD_THRESHOLD = "UNFRAUD_THRESHOLD";
    const DASHBOARD_URL = "https://unfraud.com/dashboard";
    const LOGIN_API_URL = "https://unfraud.com/api/v1.1/index.php/user/?login=true";
    const LOGIN_URL = "https://unfraud.com/api/helpers/login.php";
    const BEA_URL = "//bea.unfraud.com/bea.js";

    const SAFE_API_RESPONSE = "safe";
    const FRAUD_API_RESPONSE = "fraud";
    const SUCCESS_API_RESPONSE = 1;

    protected $_analyticsUrl = 'https://www.unfraud.com/unfraud_analytics/analytics.php?getSession=true';
    protected $_eventsUrl = 'http://api.unfraud.com/events';

    private $session_id;

    public function __construct()
    {
        $this->name = 'unfraud';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Unfraud Team';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.4.9', 'max' => _PS_VERSION_+"0.1");//added "+0.1" for better installation stability
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Unfraud.com');
        $this->description = $this->l('Unfraud.com module for Prestashop');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('UNFRAUD_APIKEY'))
            $this->warning = $this->l('No API Key Provided');

        if (_PS_VERSION_ < '1.5')
            require(_PS_MODULE_DIR_ . $this->name . '/backward_compatibility/backward.php');

        $this->default_language = Language::getLanguage(Configuration::get('PS_LANG_DEFAULT'));
        $this->languages = Language::getLanguages();
        $this->module_path = _PS_MODULE_DIR_ . $this->name . '/';
        $this->admin_tpl_path = _PS_MODULE_DIR_ . $this->name . '/views/templates/admin/';

        session_start();

        $this->session_id = session_id();
    }

    public function install()
    {
        // Install Tabs
        $parent_tab = new Tab();
        // Need a foreach for the language
        $languages = Language::getLanguages(false);
        if (is_array($languages)) {
            foreach ($languages as $language) {
                $parent_tab->name[$language['id_lang']] = $this->l('Unfraud');
            }
        }
        $parent_tab->class_name = 'AdminUnfraud';
        if(version_compare(_PS_VERSION_, "1.6.0.0") >= 0){
           $parent_tab->id_parent = 0; // Home tab
        }
        else{
            $parent_tab->id_parent = 19; // Statistics tab
        }
        $parent_tab->module = $this->name;
        $parent_tab->add();


        /* The cURL PHP extension must be enabled to use this module */
        if (!function_exists('curl_version'))
        {
            $this->_errors[] = $this->l('Sorry, this module requires the cURL PHP Extension (http://www.php.net/curl), which is not enabled on your server. Please ask your hosting provider for assistance.');
            return false;
        }

        return parent::install()
        && $this->registerHook('displayFooter')
        && $this->registerHook('orderConfirmation');//paymentReturn

    }

    public function uninstall()
    {
        $idTab = Tab::getIdFromClassName('AdminUnfraud');
        if($idTab != 0) {
            $tab = new Tab($idTab);
            $tab->delete();
        }

        if (!parent::uninstall() ||
            !Configuration::deleteByName(self::UNFRAUD_APIKEY) ||
            !Configuration::deleteByName(self::UNFRAUD_EMAIL) ||
            !Configuration::deleteByName(self::UNFRAUD_PASSWORD)
           // || !Configuration::deleteByName(self::UNFRAUD_THRESHOLD)
        )
            return false;

        return true;
    }


    public function getContent()
    {
        $this->_html .= '<h2>' . $this->displayName . '</h2>';

        if (Tools::isSubmit('submit')) {
            $this->_postProcess();
        }

        $this->_displayForm();

        return $this->_html;
    }


    private function _displayForm() //ps 14
    {

        $this->_html .= '
		<form action="' . $_SERVER['REQUEST_URI'] . '" method="post" style="padding-left:50px">
			<fieldset>
				<legend><img src="../img/admin/cog.gif" alt="" class="middle" />' . $this->l('Settings') . '</legend>
				<label>' . $this->l('API KEY') . '</label>
				<div class="margin-form">
					<input type="text" style="width:100%" name="unfraud_apikey" value="' . Configuration::get(self::UNFRAUD_APIKEY) . '"/>
					<p class="clear">' . $this->l('Your Unfraud website api key.') . '</p>
				</div>
				<label>' . $this->l('E-MAIL') . '</label>
				<div class="margin-form">
					<input type="text" style="width:100%" name="unfraud_email" value="' . Configuration::get(self::UNFRAUD_EMAIL) . '"/>
					<p class="clear">' . $this->l('Your Unfraud email.') . '</p>
				</div>
				<label>' . $this->l('PASSWORD') . '</label>
				<div class="margin-form">
					<input type="password" style="width:100%" name="unfraud_password" value="' . Configuration::get(self::UNFRAUD_PASSWORD) . '"/>
					<p class="clear">' . $this->l('Your Unfraud password.') . '</p>
				</div>	
				<!--<label>' . $this->l('THRESHOLD') . '</label>
				<div class="margin-form">
					<input type="text" style="width:90px" name="unfraud_threshold" value="' . Configuration::get(self::UNFRAUD_THRESHOLD) . '"/>
					<p class="clear">' . $this->l('Fraud Threshold (0.0 to 100.0, empty to disable).') . '</p>
				</div>		-->
				<div class="margin-form">
					<input type="submit" name="submit" value="' . $this->l('Update') . '" class="button" />
				</div>
			</fieldset>
		</form><br><br>';
    }

    private function _postProcess()
    {
        Configuration::updateValue(self::UNFRAUD_APIKEY, Tools::getValue('unfraud_apikey'));
        Configuration::updateValue(self::UNFRAUD_EMAIL, Tools::getValue('unfraud_email'));
        Configuration::updateValue(self::UNFRAUD_PASSWORD, Tools::getValue('unfraud_password'));
        //Configuration::updateValue(self::UNFRAUD_THRESHOLD, Tools::getValue('unfraud_threshold'));

        $this->_html .= '<div class="conf confirm">' . $this->l('Settings updated') . '</div>';
    }

    public function displayForm() //ps 15+
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Email'),
                    'name' => self::UNFRAUD_EMAIL,
                    'class' => "test_class",
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('Password'),
                    'name' => self::UNFRAUD_PASSWORD,
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API KEY'),
                    'name' => self::UNFRAUD_APIKEY,
                    'size' => 20,
                    'required' => true
                ),
               /* array(
                    'type' => 'text',
                    'label' => $this->l('FRAUD THRESHOLD (0.0 to 100.0)'),
                    'name' => self::UNFRAUD_THRESHOLD,
                    'size' => 20,
                    'required' => false
                ),*/
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['UNFRAUD_APIKEY'] = Configuration::get(self::UNFRAUD_APIKEY);
        $helper->fields_value['UNFRAUD_EMAIL'] = Configuration::get(self::UNFRAUD_EMAIL);
        $helper->fields_value['UNFRAUD_PASSWORD'] = Configuration::get(self::UNFRAUD_PASSWORD);
        //$helper->fields_value['UNFRAUD_THRESHOLD'] = Configuration::get(self::UNFRAUD_THRESHOLD);

        return $helper->generateForm($fields_form);
    }

    public function findFirst($cat)
    {
        foreach ($cat as $c) {
            return $c;
        }
    }

    public function hookDisplayFooter($params)
    {
        $apyKey = Configuration::get(self::UNFRAUD_APIKEY);
        if ($apyKey) {
            return "<!-- UnfraudBEA -->
<script type=\"text/javascript\">
    var bea_api_id = '" . $apyKey . "';
    var bea_session_id= '" . $this->session_id . "';
</script>
<script type=\"text/javascript\" src=\"".self::BEA_URL."\"></script>
    <!-- End UnfraudBEA Code -->";
        }

    }

    //commented because it will be unused
    /*public function hookPaymentTop($params)
    {
        exit("hookPaymentTop");
        return;
        if (!Configuration::get(self::UNFRAUD_APIKEY))
            return;

        $customer = new Customer((int)$params['cart']->id_customer);
        $address_delivery = new Address((int)$params['cart']->id_address_delivery);
        $address_invoice = new Address((int)$params['cart']->id_address_invoice);
        $default_currency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));

        if ($address_delivery->id_state !== NULL OR $address_delivery->id_state != '')
            $deliveryState = new State((int)$address_delivery->id_state);

        if ($address_invoice->id_state !== NULL OR $address_invoice->id_state != '')
            $invoiceState = new State((int)$address_invoice->id_state);

        $currency = new Currency((int)$params['cart']->id_currency);

        $product_list = $params['cart']->getProducts();

        $price = 0;
        $items = array();

        foreach ($product_list as $p) {
            $item = new Product((int)$p["id_product"]);
            $cat = new Category((int)$item->id_category_default);
            $price += $p["total_wt"];
            array_push($items, array(
                "item_id" => $p["id_product"],
                "product_title" => $p["name"],
                "price" => $item->price,
                "brand" => Manufacturer::getNameById((int)$item->id_manufacturer),
                "category" => $this->findFirst($cat->name),
                "quantity" => $p["cart_quantity"]));
        }

        $bill_state = new State($address_invoice->id_state);
        $bill_country = new Country($address_invoice->id_country);

        $ship_state = new State($address_delivery->id_state);
        $ship_country = new Country($address_delivery->id_country);

        $billing_address = array(
            "name" => $address_invoice->firstname . " " . $address_invoice->lastname,
            "address_1" => $address_invoice->address1,
            "address_2" => $address_invoice->address2,
            "city" => $address_invoice->city,
            "region" => $bill_state->name,
            "country" => $this->findFirst($bill_country->name),
            "zipcode" => $address_invoice->postcode,
            "phone" => $address_invoice->phone
        );

        $shipping_address = array(
            "name" => $address_delivery->firstname . " " . $address_invoice->lastname,
            "address_1" => $address_delivery->address1,
            "address_2" => $address_delivery->address2,
            "city" => $address_delivery->city,
            "region" => $ship_state->name,
            "country" => $this->findFirst($ship_country->name),
            "zipcode" => $address_delivery->postcode,
            "phone" => $address_delivery->phone
        );

        $unfraud_data = array(
            "type" => "new_order",
            "api_id" => Configuration::get(self::UNFRAUD_APIKEY),
            "user_id" => $customer->id,
            "user_email" => $customer->email,
            "name" => $address_invoice->firstname,
            "surname" => $address_invoice->lastname,
            "order_id" => (string)$params['cart']->id,
            "amount" => $price,
            "currency_code" => $default_currency->iso_code,
            "session_id" => $this->getSessionFromHash(),
            "ip_address" => Tools::getRemoteAddr(),
            "timestamp" => time(),
            "items" => $items,
            "billing_address" => $billing_address,
            "shipping_address" => $shipping_address,
            "unfraud_plugin" => "unfraud-prestashop_1.0.0"
        );

        $this->log("Sending order " . (string)$params['cart']->id);
        $resp = $this->sendRequest($unfraud_data);
        die('Error');
        Tools::redirect('index.php?controller=order&step=1');
        $threshold = Configuration::get(self::UNFRAUD_THRESHOLD);
        //if(	version_compare(_PS_VERSION_, '1.5', '>=')){
        if ($resp->success == self::SUCCESS_API_RESPONSE) {
            $fraud = false;
            if ($resp->unfraud_label != self::SAFE_API_RESPONSE) {
                $this->l("Unfraud Response flagged as '{$resp->unfraud_label}'");
                $fraud = true;
            }
            if ($resp->unfraud_label == self::SAFE_API_RESPONSE && $resp->unfraud_label >= (int)$threshold) {
                $this->l("Unfraud Response score ({$resp->unfraud_label}) higher than default setted in configuration settings ($threshold)");
                $fraud = true;
            }
            if ($fraud == true) {
                die('Error');
                Tools::redirect('index.php?controller=order&step=1');
                //   throw new Unfraud_Unfraud_Model_UnfraudException($this->l('There was an error processing your order. Please contact us or try again later.'));
            }
        } else {
            $this->log("Unfraud API Error");
        }


        return;
    }*/


    /**
     *
     * before it was used "hookNewOrder"
     * @param $params
     */
    public function hookOrderConfirmation($params)
    {
        if (!Configuration::get('PS_SHOP_ENABLE') OR !Configuration::get(self::UNFRAUD_APIKEY))
            return;

        $customer = new Customer((int)$params['objOrder']->id_customer);
        $address_delivery = new Address((int)$params['objOrder']->id_address_delivery);
        $address_invoice = new Address((int)$params['objOrder']->id_address_invoice);
        $default_currency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));

        if ($address_delivery->id_state !== NULL OR $address_delivery->id_state != '')
            $deliveryState = new State((int)$address_delivery->id_state);

        if ($address_invoice->id_state !== NULL OR $address_invoice->id_state != '')
            $invoiceState = new State((int)$address_invoice->id_state);

        $currency = new Currency((int)$params['objOrder']->id_currency);
        $product_list = $params['objOrder']->getProductsDetail();

        $items = array();

        foreach($product_list as $p)
        {
            $item = new Product((int)$p["product_id"]);
            $cat = new Category((int)$item->id_category_default);

            array_push($items, array(
                "item_id"=>$p["product_id"],
                "product_title"=>$p["product_name"],
                "price"=>$item->price,
                "brand"=>Manufacturer::getNameById((int)$item->id_manufacturer),
                "category"=>$this->findFirst($cat->name),
                "quantity"=>$p["product_quantity"]));
        }

        $bill_state = new State($address_invoice->id_state);
        $bill_country = new Country($address_invoice->id_country);

        $ship_state = new State($address_delivery->id_state);
        $ship_country = new Country($address_delivery->id_country);

        $billing_address = array(
            "name"=>$address_invoice->firstname." ".$address_invoice->lastname,
            "address_1"=>$address_invoice->address1,
            "address_2"=>$address_invoice->address2,
            "city"=>$address_invoice->city,
            "region"=>$bill_state->name,
            "country"=>$this->findFirst($bill_country->name),
            "zipcode"=>$address_invoice->postcode,
            "phone"=>$address_invoice->phone
        );

        $shipping_address = array(
            "name"=>$address_delivery->firstname." ".$address_invoice->lastname,
            "address_1"=>$address_delivery->address1,
            "address_2"=>$address_delivery->address2,
            "city"=>$address_delivery->city,
            "region"=>$ship_state->name,
            "country"=>$this->findFirst($ship_country->name),
            "zipcode"=>$address_delivery->postcode,
            "phone"=>$address_delivery->phone
        );

        $unfraud_data = array(
            "type"=>"new_order",
            "api_id"=>Configuration::get(self::UNFRAUD_APIKEY),
            "user_id"=>$customer->email,
            "user_email"=>$customer->email,
            "name"=>$address_invoice->firstname,
            "surname"=>$address_invoice->lastname,
            "order_id"=>$params['objOrder']->id_cart,
            "amount"=>$params['objOrder']->total_paid,
            "currency_code"=>$default_currency->iso_code,
            "session_id"=>$this->session_id,
            "ip_address"=>$_SERVER['REMOTE_ADDR'],
            "timestamp"=>time(),
            "items"=>$items,
            "billing_address"=>$billing_address,
            "shipping_address"=>$shipping_address,
        );

        try {
            $resp = $this->sendRequest($unfraud_data);

            if ($resp->success == self::SUCCESS_API_RESPONSE) {
                $fraud = false;
                if ($resp->unfraud_label != self::SAFE_API_RESPONSE) {
                    $this->log("Unfraud Response flagged as '{$resp->unfraud_label}'");
                    $fraud = true;
                }

                // we cannot add exception as well as in Magento module managed because Opencart hasn't transactions
                // on Checkout actions and we cannot rollback to prevoius situation. Thus we left commented the follow code lines.
                //if ($resp->unfraud_label == self::SAFE_API_RESPONSE && $resp->unfraud_label >= (int)$threshold) {
                //    $this->log(("Unfraud Response score ({$resp->unfraud_label}) higher than default setted in configuration settings ($threshold)");
                //    $fraud = true;
                //}
                // we cannot add exception as well as in Magento module managed because Opencart hasn't transactions
                // on Checkout actions and we cannot rollback to prevoius situation. Thus we left commented the follow code lines.
                //if ($fraud == true) {
                //}
            } else {
                $this->log("Unfraud API Error");
            }
        }
        catch(Exception $e){
            $this->log($e->getMessage());
        }


    }

    /**
     * @param array $fields
     * @return mixed
     */
    protected function sendRequest(array $fields)
    {
        $this->log($fields);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL,$this->_eventsUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $server_output = curl_exec ($ch);
        curl_close ($ch);



        if ($server_output === FALSE) {
            $this->logError("Response from ".$this->_eventsUrl." :");
            $this->logError(sprintf("cUrl error (#%d): %s<br>\n", curl_errno($ch),
                htmlspecialchars(curl_error($ch))));
        }
        else{
            $this->log("Response from ".$this->_eventsUrl." :");
            $this->log("Response from ".$this->_eventsUrl." :");
        }

        $resp = json_decode($server_output);
        return $resp;

    }

    public function translate($msg){
        return $this->l($msg);
    }

    public function log($msg){
        $logger = new FileLogger(0);
        $logger->setFilename(_PS_ROOT_DIR_."/log/unfraud.log");
        $logger->logDebug($msg);
    }

    public function logError($msg){
        $logger = new FileLogger(0);
        $logger->setFilename(_PS_ROOT_DIR_."/log/unfraud_error.log");
        $logger->logDebug($msg);
    }
}