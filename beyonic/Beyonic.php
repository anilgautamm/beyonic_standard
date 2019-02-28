<?php
/**
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2015 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Beyonic extends PaymentModule
{
    const FLAG_DISPLAY_PAYMENT_INVITE = 'BEYONIC_PAYMENT_INVITE';

    private $htmlText = '';
    private $postErrors = array();
    public $checkName;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'beyonic';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Beyonic';
        $this->controllers = array('payment', 'validation');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Beyonic Mobile Payments ');
        $this->description = $this->l('');
        $this->confirmUninstall = $this->l('Are you sure you want to delete these details?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.7');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    //module install function
    public function install()
    {
        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
        return parent::install() && $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn');
    }

    //module uninstall function
    public function uninstall()
    {
        if (!Configuration::deleteByName('BEYONIC_API_KEY')
                || !Configuration::deleteByName('BEYONIC_DESCRIPTION')
                || !Configuration::deleteByName('BEYONIC_DESCRIPTION_2')
                || !Configuration::deleteByName(self::FLAG_DISPLAY_PAYMENT_INVITE)
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    //admin panel setting validation for required fields
    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('BEYONIC_API_KEY')) {
                $this->postErrors[] = $this->l('The "API Key" field is required.');
            }
        }
    }

    //admin panel setting saved function in db
    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('BEYONIC_API_KEY', Tools::getValue('BEYONIC_API_KEY'));
            Configuration::updateValue('BEYONIC_DESCRIPTION', Tools::getValue('BEYONIC_DESCRIPTION'));
            Configuration::updateValue('BEYONIC_DESCRIPTION_2', Tools::getValue('BEYONIC_DESCRIPTION_2'));
        }
        $this->htmlText .= $this->displayConfirmation($this->l('Settings updated'));
    }

    //set payment information on admin panel payment setting page
    private function displayCheck()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    //set payment page content
    public function getContent()
    {
        $this->htmlText = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->htmlText .= $this->displayError($err);
                }
            }
        }

        $this->htmlText .= $this->displayCheck();
        $this->htmlText .= $this->renderForm();

        return $this->htmlText;
    }

    //show payment option on frontend paymet tab
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
//        id_address_invoice id_address_delivery
        $address = new Address((int)$params['cart']->id_address_invoice);
        if (!empty(Configuration::get('BEYONIC_DESCRIPTION_2'))) {
            $additional_information = '<section>'
                    . '<p>' . Configuration::get('BEYONIC_DESCRIPTION_2')
                    . " " . $address->phone . ''
                    . '</p></section>';
        } else {
            $additional_information = "";
        }
        if (!empty(Configuration::get('BEYONIC_DESCRIPTION'))) {
            $information = '  (' . Configuration::get('BEYONIC_DESCRIPTION') . ')';
        } else {
            $information = "";
        }
        $name = 'Beyonic Mobile Payments' . $information;
        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
                ->setCallToActionText($this->l($name))
                ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                ->setAdditionalInformation($additional_information);

        return array($newOption);
    }

    //after place order
    public function hookPaymentReturn($params)
    {
        require 'lib/Beyonic.php';
        $this->authorizeBeyonicGw();
        $beyonic_ipn_url = $this->context->shop->getBaseURL(true)
                . 'index.php?fc=module&module=Beyonic&controller=webhook';
        $url = str_replace("http:", "https:", $beyonic_ipn_url);
        $get_hooks = \Beyonic_Webhook::getAll();
        $hook_exist = false;
        if (!empty($get_hooks['results'])) {
            foreach ($get_hooks['results'] as $get_hook) {
                if ($get_hook->target == $url) {
                    $hook_exist = true;
                    break;
                }
            }
        }

        if ($hook_exist == false) {
            try {
                \Beyonic_Webhook::create(array(
                            "event" => "collection.received",
                            "target" => "$url"
                ));
            } catch (Exception $e) {
                $json = json_decode($e->responseBody);
                $error = '';
                if (!empty($json)) {
                    foreach ($json as $value) {
                        $error = $value[0];
                        break;
                    }
                }
                return $this->fetch('module:Beyonic/views/templates/hook/payment_return.tpl');
            }
        }

        try {
            //get the Order object
            $order = $params['order'];
            $cart = $params['cart'];
            $this->currency = new Currency((int) ($cart->id_currency));

            $billingAddress = new Address((int)$params['cart']->id_address_invoice);
            $total_formatted = number_format($params['order']->total_paid, "2", ".", "");

            $request = \Beyonic_Collection_Request::create(array(
                        "phonenumber" => $billingAddress->phone,
                        "first_name" => $billingAddress->firstname,
                        "last_name" => $billingAddress->lastname,
                        "email" => $this->context->customer->email,
                        "amount" => $total_formatted,
                        "success_message" => 'Thank you for your payment!',
                        "send_instructions" => true,
                        "currency" => $this->currency,
                        "metadata" => array("order_id" => $order->id)
            ));

            $beyonic_collection_id = (int)$request->id;
            if (!empty($beyonic_collection_id)) {
                $sql = 'UPDATE `' . _DB_PREFIX_
                        . 'order_payment` SET `laction_id` = "'
                        . $beyonic_collection_id . '" WHERE `id_order_payment` = \''
                        . (int) $order->invoice_number . '\'';
                Db::getInstance()->execute($sql);

                $objOrder = new Order(Tools::getValue('id_order'));
                $history = new OrderHistory();
                $history->id_order = (int) $objOrder->id;
                $history->changeIdOrderState(3, (int) ($objOrder->id));

                $this->smarty->assign(array(
                    'total_to_pay' => Tools::displayPrice(
                        $params['order']->getOrdersTotalPaid(),
                        new Currency($params['order']->id_currency),
                        false
                    ),
                    'shop_name' => $this->context->shop->name,
                    'status' => 'ok',
                    'beyonic_phone' => $billingAddress->phone,
                    'id_order' => $params['order']->id
                ));
                if (isset($params['order']->reference)
                        && !empty($params['order']->reference)) {
                    $this->smarty->assign('reference', $params['order']->reference);
                }
            }
        } catch (\Exception $e) {
            $json = json_decode($e->responseBody);
            $error = '';
            if (!empty($json)) {
                foreach ($json as $value) {
                    $error = $value[0];
                    break;
                }
            }
            $objOrder = new Order(Tools::getValue('id_order'));
            $history = new OrderHistory();
            $history->id_order = (int) $objOrder->id;
            $history->changeIdOrderState(8, (int) ($objOrder->id));

            $this->smarty->assign(array(
                'status' => 'failed',
                'error' => $error
            ));
        }
        return $this->display(__FILE__, 'payment_return.tpl');
    }

    private function authorizeBeyonicGw()
    {
        $apikey = Configuration::get('BEYONIC_API_KEY');
        $version = 'v1';
        Beyonic::setApiVersion($version);
        Beyonic::setApiKey($apikey);
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int) ($cart->id_currency));
        $currencies_module = $this->getCurrency((int) $cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    // create admin panel fields
    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Beyonic Mobile Payment details'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'desc' => $this->l('Please enter your api key (You can get it from your beyonic profile).'),
                        'name' => 'BEYONIC_API_KEY',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Description', array(), 'Modules.Beyonic.Admin'),
                        'desc' => $this->l('This description will be shown on your checkout payment tab.'),
                        'name' => 'BEYONIC_DESCRIPTION',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Order Tab Description', array(), 'Modules.Beyonic.Admin'),
                        'desc' => $this->l('This description will be shown on your checkout confirm tab.'),
                        'name' => 'BEYONIC_DESCRIPTION_2',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Callback notification URL', array(), 'Modules.Beyonic.Admin'),
                        'desc' => $this->l('This is the '
                                . 'notification URL that will be used to '
                                . 'send payment notifications'
                                . ' to your website. You do not need to change it. '
                                . 'NOTE : It must start with "https".'
                                . ' If it does not start with https, then '
                                . 'that means that your website does not have '
                                . 'a secure HTTPS certificate.'),
                        'name' => 'NOTIFICATION_URL',
                        'disabled' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save', array(), 'Admin.Actions'),
                )
            ),
        );
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
                . '&configure=' . $this->name . '&tab_module='
                . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );
        $this->fields_form = array();

        return $helper->generateForm(array($fields_form));
    }

    //set default values and get saved values in admin panel fields
    public function getConfigFieldsValues()
    {
        return array(
            'BEYONIC_API_KEY' => Tools::getValue(
                'BEYONIC_API_KEY',
                Configuration::get('BEYONIC_API_KEY')
            ),
            'BEYONIC_DESCRIPTION' => Tools::getValue(
                'BEYONIC_DESCRIPTION',
                Configuration::get('BEYONIC_DESCRIPTION')
            ),
            'BEYONIC_DESCRIPTION_2' => Tools::getValue(
                'BEYONIC_DESCRIPTION_2',
                Configuration::get('BEYONIC_DESCRIPTION_2')
            ),
            'NOTIFICATION_URL' => $this->context->shop->getBaseURL(true)
                . 'index.php?fc=module&module=Beyonic&controller=webhook',
        );
    }
}
