<?php

/*
 * 2007-2012 PrestaShop
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
 *  @copyright  2007-2012 PrestaShop SA
 *  @version  Release: $Revision: 14265 $
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * modified by Prestahost.cz/eu
 * allows carrier selection
 */

if (!defined('_PS_VERSION_')) exit;


class CashOnDelivery extends PaymentModule
{
    private $fee;
    private $feefree;
    protected $allowed_carriers;
    public function __construct()
    {
        $this->name = 'cashondelivery';
        $this->tab = 'payments_gateways';
        $this->version = '1.3';
        $this->author = 'Modified';
        $this->need_instance = 1;
        
        $this->currencies = false;
        
        $config = Configuration::getMultiple(array(
            'COD_CARRIERS',
            'COD_FEE',
            'COD_FEEFREE'
        ));
        $this->fee = $config['COD_FEE'];
        $this->feefree = $config['COD_FEEFREE'];
        
        parent::__construct();

        
        if (!empty($config['COD_CARRIERS'])) {

            $this->allowed_carriers = unserialize($config['COD_CARRIERS']);
        }
        
        
        parent::__construct();

        
        $this->displayName = $this->l('Cash on delivery (COD)');
        $this->description = $this->l('Accept cash on delivery payments');
    }
    
    public function install()
    {
        
        if (!

        parent::install() or !$this->registerHook('payment') or !$this->registerHook('paymentReturn')) return false;

        return true;
    }
    
    public function uninstall()
    {
        
        if (!Configuration::deleteByName('COD_FEE') || !Configuration::deleteByName('COD_FEEFREE') || !Configuration::deleteByName('COD_CARRIERS') || !

        parent::uninstall()) return false;

        return true;
    }
    
    public function getContent()
    {
        $this->_html = '<h2>' . $this->displayName . '</h2>';
        
        
        if (!empty($_POST)) {

            $this->_postValidation();
            
            if (!sizeof($this->_postErrors)) $this->_postProcess();

            else 
            foreach ($this->_postErrors AS $err) $this->_html.= '<div class="alert error">' . $err . '</div>';

        } 
        else $this->_html.= '<br />';
        $this->_displayCod();
        $this->_displayForm();
        return $this->_html;
    }
    
    public function hookPayment($params)
    {
        
        if (!$this->active) return;

        
        if (!$this->isAllowedCarrier($params['cart']->id_carrier)) return false;

        
        global $smarty;
        $amountPaid = 0;
        
        // Check if cart has product download
        
        foreach ($params['cart']->getProducts() AS $product) {

            $amountPaid+= $product['price'];
            $pd = ProductDownload::getIdFromIdProduct((int)($product['id_product']));
            
            if ($pd and Validate::isUnsignedInt($pd)) return false;

        }
        
        $smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));
        
        
        if ($this->feefree > 0 && $amountPaid > $this->feefree) {

            $smarty->assign(array(
                'dobirecne' => $this->l('Free!')
            ));
        } 
        else {
            $smarty->assign(array(
                'dobirecne' => $this->l('The fee is ') . Tools::displayPrice(Tools::convertPrice($this->fee))
            ));
        }
        return $this->display(__FILE__, 'payment.tpl');
    }
    
    public function hookPaymentReturn($params)
    {
        
        if (!$this->active) return;

        
        return $this->display(__FILE__, 'confirmation.tpl');
    }
    
    public function hookUpdateCarrier($params)
    {
        $this->_renumberCarriers($params);
        Configuration::updateValue('COD_CARRIERS', serialize($this->allowed_carriers));
    }
    
    /**
     * Validate an order in database
     * Function called from a payment module
     *
     * @param integer $id_cart Value
     * @param integer $id_order_state Value
     * @param float $amount_paid Amount really paid by customer (in the default currency)
     * @param string $payment_method Payment method (eg. 'Credit card')
     * @param string $message Message to attach to order
     */
    
    public function validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method = 'Unknown', $message = null, $extra_vars = array() , $currency_special = null, $dont_touch_amount = false, $secure_key = false, Shop $shop = null)
    {
        $this->context->cart = new Cart($id_cart);
        $this->context->customer = new Customer($this->context->cart->id_customer);
        $this->context->language = new Language($this->context->cart->id_lang);
        $this->context->shop = ($shop ? $shop : new Shop($this->context->cart->id_shop));
        $id_currency = $currency_special ? (int)$currency_special : (int)$this->context->cart->id_currency;
        $this->context->currency = new Currency($id_currency, null, $this->context->shop->id);
        
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') $context_country = $this->context->country;

        
        $order_status = new OrderState((int)$id_order_state, (int)$this->context->language->id);
        
        if (!Validate::isLoadedObject($order_status)) throw new PrestaShopException('Can\'t load Order state status');

        
        
        if (!$this->active) die(Tools::displayError());

        
        // Does order already exists ?
        
        if (Validate::isLoadedObject($this->context->cart) && $this->context->cart->OrderExists() == false) {

            
            if ($secure_key !== false && $secure_key != $this->context->cart->secure_key) die(Tools::displayError());

            
            // For each package, generate an order
            $delivery_option_list = $this->context->cart->getDeliveryOptionList();
            $package_list = $this->context->cart->getPackageList();
            $cart_delivery_option = $this->context->cart->getDeliveryOption();
            
            // If some delivery options are not defined, or not valid, use the first valid option
            
            foreach ($delivery_option_list as $id_address => $package) 

            if (!isset($cart_delivery_option[$id_address]) || !array_key_exists($cart_delivery_option[$id_address], $package)) 

            foreach ($package as $key => $val) {

                $cart_delivery_option[$id_address] = $key;
                break;
            }
            
            $order_list = array();
            $order_detail_list = array();
            $reference = Order::generateReference();
            $this->currentOrderReference = $reference;
            
            $order_creation_failed = false;
            $cart_total_paid = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH) , 2);
            
            
            if ($this->context->cart->orderExists()) {

                $error = Tools::displayError('An order has already been placed using this cart.');
                Logger::addLog($error, 4, '0000001', 'Cart', intval($this->context->cart->id));
                die($error);
            }
            
            
            foreach ($cart_delivery_option as $id_address => $key_carriers) 

            foreach ($delivery_option_list[$id_address][$key_carriers]['carrier_list'] as $id_carrier => $data) 

            foreach ($data['package_list'] as $id_package) $package_list[$id_address][$id_package]['id_carrier'] = $id_carrier;

            
            // Make sure CarRule caches are empty
            CartRule::cleanCache();
            
            
            foreach ($package_list as $id_address => $packageByAddress) 

            foreach ($packageByAddress as $id_package => $package) {

                $order = new Order();
                $order->product_list = $package['product_list'];
                
                
                if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {

                    $address = new Address($id_address);
                    $this->context->country = new Country($address->id_country, $this->context->cart->id_lang);
                }
                
                $carrier = null;
                
                if (!$this->context->cart->isVirtualCart() && isset($package['id_carrier'])) {

                    $carrier = new Carrier($package['id_carrier'], $this->context->cart->id_lang);
                    $order->id_carrier = (int)$carrier->id;
                    $id_carrier = (int)$carrier->id;
                } 
                else {
                    $order->id_carrier = 0;
                    $id_carrier = 0;
                }
                
                $order->id_customer = (int)$this->context->cart->id_customer;
                $order->id_address_invoice = (int)$this->context->cart->id_address_invoice;
                $order->id_address_delivery = (int)$id_address;
                $order->id_currency = $this->context->currency->id;
                $order->id_lang = (int)$this->context->cart->id_lang;
                $order->id_cart = (int)$this->context->cart->id;
                $order->reference = $reference;
                $order->id_shop = (int)$this->context->shop->id;
                $order->id_shop_group = (int)$this->context->shop->id_shop_group;
                
                $order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($this->context->customer->secure_key));
                $order->payment = $payment_method;
                
                if (isset($this->name)) $order->module = $this->name;

                $order->recyclable = $this->context->cart->recyclable;
                $order->gift = (int)$this->context->cart->gift;
                $order->gift_message = $this->context->cart->gift_message;
                $order->conversion_rate = $this->context->currency->conversion_rate;
                $amount_paid = !$dont_touch_amount ? Tools::ps_round((float)$amount_paid, 2) : $amount_paid;
                $order->total_paid_real = 0;
                
                $order->total_products = (float)$this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);
                $order->total_products_wt = (float)$this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);
                
                $order->total_discounts_tax_excl = (float)abs($this->context->cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
                $order->total_discounts_tax_incl = (float)abs($this->context->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
                $order->total_discounts = $order->total_discounts_tax_incl;
                
                /*//////////////////////////////////////////////////////////
                ////////////////// CÁLCULO DEL RECARGO /////////////////////
                //////////////////////////////////////////////////////////*/
                require 'recargominimo.php';
                
                if ($this->feefree > 0 && $amount_paid > $this->feefree) {

                    $fee = 0;
                } 
                else {
                    $fee = Tools::convertPrice($this->fee, $order->id_currency);
                    $fee = $cart_total_paid / 100 * $fee;
                }
                
                if ($fee > $cart_total_paid) {

                    $fee = Tools::convertPrice($this->fee, $order->id_currency) / 100;
                }
                
                if ($fee < $RecargoMinimo) {

                    $fee = $RecargoMinimo;
                }
                
                /*//////////////////////////////////////////////////////////
                ///////////////// FIN CÁLCULO DEL RECARGO //////////////////
                //////////////////////////////////////////////////////////*/
                
                $order->total_shipping_tax_excl = (float)$this->context->cart->getPackageShippingCost((int)$id_carrier, false, null, $order->product_list) + $fee;
                $order->total_shipping_tax_incl = (float)$this->context->cart->getPackageShippingCost((int)$id_carrier, true, null, $order->product_list) + $fee;
                $order->total_shipping = $order->total_shipping_tax_incl;
                
                
                if (!is_null($carrier) && Validate::isLoadedObject($carrier)) $order->carrier_tax_rate = $carrier->getTaxesRate(new Address($this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE') }));

                
                $order->total_wrapping_tax_excl = (float)abs($this->context->cart->getOrderTotal(false, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
                $order->total_wrapping_tax_incl = (float)abs($this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
                $order->total_wrapping = $order->total_wrapping_tax_incl;
                
                /*/////////////////////////////////////////////////////////*/
                $order->total_paid_tax_excl = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(false, Cart::BOTH, $order->product_list, $id_carrier) + $fee, 2);
                $order->total_paid_tax_incl = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH, $order->product_list, $id_carrier) + $fee, 2);
                $order->total_paid = $order->total_paid_tax_incl;
                
                $order->invoice_date = '0000-00-00 00:00:00';
                $order->delivery_date = '0000-00-00 00:00:00';
                
                // Creating order
                $result = $order->add();
                
                
                if (!$result) throw new PrestaShopException('Can\'t save Order');

                
                // Amount paid by customer is not the right one -> Status = payment error
                // We don't use the following condition to avoid the float precision issues : http://www.php.net/manual/en/language.types.float.php
                // if ($order->total_paid != $order->total_paid_real)
                // We use number_format in order to compare two string
                
                /////////////////////////////////////////////////// REVISADO ///////////////////////////////////////////////////
                
                
                if (($order_status->logable && number_format($cart_total_paid + $fee, 2) != number_format($amount_paid + $fee, 2)) and ($order_status->logable && number_format($cart_total_paid + $fee, 2) != number_format($amount_paid, 2))) {

                    $id_order_state = Configuration::get('PS_OS_ERROR');
                }
                
                /////////////////////////////////////////////////// REVISADO ///////////////////////////////////////////////////
                
                $order_list[] = $order;
                
                // Insert new Order detail list using cart for the current order
                $order_detail = new OrderDetail(null, null, $this->context);
                $order_detail->createList($order, $this->context->cart, $id_order_state, $order->product_list, 0, true, $package_list[$id_address][$id_package]['id_warehouse']);
                $order_detail_list[] = $order_detail;
                
                // Adding an entry in order_carrier table
                
                if (!is_null($carrier)) {

                    $order_carrier = new OrderCarrier();
                    $order_carrier->id_order = (int)$order->id;
                    $order_carrier->id_carrier = (int)$id_carrier;
                    $order_carrier->weight = (float)$order->getTotalWeight();
                    $order_carrier->shipping_cost_tax_excl = (float)$order->total_shipping_tax_excl;
                    $order_carrier->shipping_cost_tax_incl = (float)$order->total_shipping_tax_incl;
                    $order_carrier->add();
                }
            }
            
            // The country can only change if the address used for the calculation is the delivery address, and if multi-shipping is activated
            
            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') $this->context->country = $context_country;

            
            // Register Payment only if the order status validate the order
            
            if ($order_status->logable) {

                
                // $order is the last order loop in the foreach
                // The method addOrderPayment of the class Order make a create a paymentOrder
                //     linked to the order reference and not to the order id
                
                if (!$order->addOrderPayment($amount_paid)) throw new PrestaShopException('Can\'t save Order Payment');

            }
            
            // Next !
            $only_one_gift = false;
            $cart_rule_used = array();
            $products = $this->context->cart->getProducts();
            $cart_rules = $this->context->cart->getCartRules();
            
            // Make sure CarRule caches are empty
            CartRule::cleanCache();
            
            
            foreach ($order_detail_list as $key => $order_detail) {

                $order = $order_list[$key];
                
                if (!$order_creation_failed & isset($order->id)) {

                    
                    if (!$secure_key) $message.= '<br />' . Tools::displayError('Warning: the secure key is empty, check your payment account before validation');

                    
                    // Optional message to attach to this order
                    
                    if (isset($message) & !empty($message)) {

                        $msg = new Message();
                        $message = strip_tags($message, '<br>');
                        
                        if (Validate::isCleanHtml($message)) {

                            $msg->message = $message;
                            $msg->id_order = intval($order->id);
                            $msg->private = 1;
                            $msg->add();
                        }
                    }
                    
                    // Insert new Order detail list using cart for the current order
                    //$orderDetail = new OrderDetail(null, null, $this->context);
                    //$orderDetail->createList($order, $this->context->cart, $id_order_state);
                    
                    // Construct order detail table for the email
                    $products_list = '';
                    $virtual_product = true;
                    
                    foreach ($products as $key => $product) {

                        $price = Product::getPriceStatic((int)$product['id_product'], false, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null) , 6, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE') });
                        $price_wt = Product::getPriceStatic((int)$product['id_product'], true, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null) , 2, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE') });
                        
                        $customization_quantity = 0;
                        
                        if (isset($customized_datas[$product['id_product']][$product['id_product_attribute']])) {

                            $customization_text = '';
                            
                            foreach ($customized_datas[$product['id_product']][$product['id_product_attribute']] as $customization) {

                                
                                if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD])) 

                                foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text) $customization_text.= $text['name'] . ': ' . $text['value'] . '<br />';

                                
                                
                                if (isset($customization['datas'][Product::CUSTOMIZE_FILE])) $customization_text.= sprintf(Tools::displayError('%d image(s)') , count($customization['datas'][Product::CUSTOMIZE_FILE])) . '<br />';

                                
                                $customization_text.= '---<br />';
                            }
                            
                            $customization_text = rtrim($customization_text, '---<br />');
                            
                            $customization_quantity = (int)$product['customizationQuantityTotal'];
                            $products_list.= '<tr style="background-color: ' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
                                <td style="padding: 0.6em 0.4em;">' . $product['reference'] . '</td>
                                <td style="padding: 0.6em 0.4em;"><strong>' . $product['name'] . (isset($product['attributes']) ? ' - ' . $product['attributes'] : '') . ' - ' . Tools::displayError('Customized') . (!empty($customization_text) ? ' - ' . $customization_text : '') . '</strong></td>
                                <td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt, $this->context->currency, false) . '</td>
                                <td style="padding: 0.6em 0.4em; text-align: center;">' . $customization_quantity . '</td>
                                <td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice($customization_quantity * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt) , $this->context->currency, false) . '</td>
                            </tr>';
                        }
                        
                        
                        if (!$customization_quantity || (int)$product['cart_quantity'] > $customization_quantity) $products_list.= '<tr style="background-color: ' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
                                <td style="padding: 0.6em 0.4em;">' . $product['reference'] . '</td>
                                <td style="padding: 0.6em 0.4em;"><strong>' . $product['name'] . (isset($product['attributes']) ? ' - ' . $product['attributes'] : '') . '</strong></td>
                                <td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice(Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt, $this->context->currency, false) . '</td>
                                <td style="padding: 0.6em 0.4em; text-align: center;">' . ((int)$product['cart_quantity'] - $customization_quantity) . '</td>
                                <td style="padding: 0.6em 0.4em; text-align: right;">' . Tools::displayPrice(((int)$product['cart_quantity'] - $customization_quantity) * (Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt) , $this->context->currency, false) . '</td></tr>';

                        
                        // Check if is not a virutal product for the displaying of shipping
                        
                        if (!$product['is_virtual']) $virtual_product&= false;

                    }
                     // end foreach ($products)
                    
                    $cart_rules_list = '';
                    
                    foreach ($cart_rules as $cart_rule) {

                        $package = array(
                            'id_carrier' => $order->id_carrier,
                            'id_address' => $order->id_address_delivery,
                            'products' => $order->product_list
                        );
                        $values = array(
                            'tax_incl' => $cart_rule['obj']->getContextualValue(true, $this->context, CartRule::FILTER_ACTION_ALL, $package) ,
                            'tax_excl' => $cart_rule['obj']->getContextualValue(false, $this->context, CartRule::FILTER_ACTION_ALL, $package)
                        );
                        
                        // If the reduction is not applicable to this order, then continue with the next one
                        
                        if (!$values['tax_excl']) continue;

                        
                        $order->addCartRule($cart_rule['obj']->id, $cart_rule['obj']->name, $values);
                        
                        /* IF
                         ** - This is not multi-shipping
                         ** - The value of the voucher is greater than the total of the order
                         ** - Partial use is allowed
                         ** - This is an "amount" reduction, not a reduction in % or a gift
                         ** THEN
                         ** The voucher is cloned with a new value corresponding to the remainder
                        */
                        
                        if (count($order_list) == 1 && $values['tax_incl'] > $order->total_products_wt && $cart_rule['obj']->partial_use == 1 && $cart_rule['obj']->reduction_amount > 0) {

                            
                            // Create a new voucher from the original
                            $voucher = new CartRule($cart_rule['obj']->id);
                             // We need to instantiate the CartRule without lang parameter to allow saving it
                            unset($voucher->id);
                            
                            // Set a new voucher code
                            $voucher->code = empty($voucher->code) ? substr(md5($order->id . '-' . $order->id_customer . '-' . $cart_rule['obj']->id) , 0, 16) : $voucher->code . '-2';
                            
                            if (preg_match('/\-([0-9]{1,2})\-([0-9]{1,2})$/', $voucher->code, $matches) && $matches[1] == $matches[2]) $voucher->code = preg_replace('/' . $matches[0] . '$/', '-' . (intval($matches[1]) + 1) , $voucher->code);

                            
                            // Set the new voucher value
                            
                            if ($voucher->reduction_tax) $voucher->reduction_amount = $values['tax_incl'] - $order->total_products_wt;

                            else $voucher->reduction_amount = $values['tax_excl'] - $order->total_products;
                            
                            $voucher->id_customer = $order->id_customer;
                            $voucher->quantity = 1;
                            
                            if ($voucher->add()) {

                                
                                // If the voucher has conditions, they are now copied to the new voucher
                                CartRule::copyConditions($cart_rule['obj']->id, $voucher->id);
                                
                                $params = array(
                                    '{voucher_amount}' => Tools::displayPrice($voucher->reduction_amount, $this->context->currency, false) ,
                                    '{voucher_num}' => $voucher->code,
                                    '{firstname}' => $this->context->customer->firstname,
                                    '{lastname}' => $this->context->customer->lastname,
                                    '{id_order}' => $order->reference,
                                    '{order_name}' => $order->getUniqReference()
                                );
                                Mail::Send((int)$order->id_lang, 'voucher', sprintf(Mail::l('New voucher regarding your order %s', (int)$order->id_lang) , $order->reference) , $params, $this->context->customer->email, $this->context->customer->firstname . ' ' . $this->context->customer->lastname, null, null, null, null, _PS_MAIL_DIR_, false, (int)$order->id_shop);
                            }
                        }
                        
                        
                        if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && !in_array($cart_rule['obj']->id, $cart_rule_used)) {

                            $cart_rule_used[] = $cart_rule['obj']->id;
                            
                            // Create a new instance of Cart Rule without id_lang, in order to update its quantity
                            $cart_rule_to_update = new CartRule($cart_rule['obj']->id);
                            $cart_rule_to_update->quantity = max(0, $cart_rule_to_update->quantity - 1);
                            $cart_rule_to_update->update();
                        }
                        
                        $cart_rules_list.= '<tr style="background-color:#EBECEE;"><td colspan="4" style="padding:0.6em 0.4em;text-align:right">' . Tools::displayError('Voucher name:') . ' ' . $cart_rule['obj']->name . '</td><td style="padding:0.6em 0.4em;text-align:right">' . ($values['tax_incl'] != 0.00 ? '-' : '') . Tools::displayPrice($values['tax_incl'], $this->context->currency, false) . '</td></tr>';
                    }
                    
                    // Specify order id for message
                    $old_message = Message::getMessageByCartId((int)$this->context->cart->id);
                    
                    if ($old_message) {

                        $message = new Message((int)$old_message['id_message']);
                        $message->id_order = (int)$order->id;
                        $message->update();
                        
                        // Add this message in the customer thread
                        $customer_thread = new CustomerThread();
                        $customer_thread->id_contact = 0;
                        $customer_thread->id_customer = (int)$order->id_customer;
                        $customer_thread->id_shop = (int)$this->context->shop->id;
                        $customer_thread->id_order = (int)$order->id;
                        $customer_thread->id_lang = (int)$this->context->language->id;
                        $customer_thread->email = $this->context->customer->email;
                        $customer_thread->status = 'open';
                        $customer_thread->token = Tools::passwdGen(12);
                        $customer_thread->add();
                        $customer_message = new CustomerMessage();
                        $customer_message->id_customer_thread = $customer_thread->id;
                        $customer_message->id_employee = 0;
                        $customer_message->message = htmlentities($message->message, ENT_COMPAT, 'UTF-8');
                        $customer_message->private = 0;
                        
                        
                        if (!$customer_message->add()) $this->errors[] = Tools::displayError('An error occurred while saving message');

                    }
                    
                    // Hook validate order
                    Hook::exec('actionValidateOrder', array(
                        'cart' => $this->context->cart,
                        'order' => $order,
                        'customer' => $this->context->customer,
                        'currency' => $this->context->currency,
                        'orderStatus' => $order_status
                    ));
                    
                    
                    foreach ($this->context->cart->getProducts() as $product) 

                    if ($order_status->logable) ProductSale::addProductSale((int)$product['id_product'], (int)$product['cart_quantity']);

                    
                    
                    if (Configuration::get('PS_STOCK_MANAGEMENT') && $order_detail->getStockState()) {

                        $history = new OrderHistory();
                        $history->id_order = (int)$order->id;
                        $history->changeIdOrderState(Configuration::get('PS_OS_OUTOFSTOCK') , (int)$order->id);
                        $history->addWithemail();
                    }
                    
                    // Set order state in order history ONLY even if the "out of stock" status has not been yet reached
                    // So you migth have two order states
                    $new_history = new OrderHistory();
                    $new_history->id_order = (int)$order->id;
                    
                    if (Tools::version_compare(_PS_VERSION_, '1.5.2')) $new_history->changeIdOrderState((int)$id_order_state, (int)$order->id, true);

                    else $new_history->changeIdOrderState((int)$id_order_state, $order, true);
                    
                    $new_history->addWithemail(true, $extra_vars);
                    
                    unset($order_detail);
                    
                    // Order is reloaded because the status just changed
                    $order = new Order($order->id);
                    
                    // Send an e-mail to customer (one order = one email)
                    
                    if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && $this->context->customer->id) {

                        $invoice = new Address($order->id_address_invoice);
                        $delivery = new Address($order->id_address_delivery);
                        $delivery_state = $delivery->id_state ? new State($delivery->id_state) : false;
                        $invoice_state = $invoice->id_state ? new State($invoice->id_state) : false;
                        
                        $data = array(
                            '{firstname}' => $this->context->customer->firstname,
                            '{lastname}' => $this->context->customer->lastname,
                            '{email}' => $this->context->customer->email,
                            '{delivery_block_txt}' => $this->_getFormatedAddress($delivery, "\n") ,
                            '{invoice_block_txt}' => $this->_getFormatedAddress($invoice, "\n") ,
                            '{delivery_block_html}' => $this->_getFormatedAddress($delivery, '<br />', array(
                                'firstname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>',
                                'lastname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>'
                            )) ,
                            '{invoice_block_html}' => $this->_getFormatedAddress($invoice, '<br />', array(
                                'firstname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>',
                                'lastname' => '<span style="color:#DB3484; font-weight:bold;">%s</span>'
                            )) ,
                            '{delivery_company}' => $delivery->company,
                            '{delivery_firstname}' => $delivery->firstname,
                            '{delivery_lastname}' => $delivery->lastname,
                            '{delivery_address1}' => $delivery->address1,
                            '{delivery_address2}' => $delivery->address2,
                            '{delivery_city}' => $delivery->city,
                            '{delivery_postal_code}' => $delivery->postcode,
                            '{delivery_country}' => $delivery->country,
                            '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
                            '{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
                            '{delivery_other}' => $delivery->other,
                            '{invoice_company}' => $invoice->company,
                            '{invoice_vat_number}' => $invoice->vat_number,
                            '{invoice_firstname}' => $invoice->firstname,
                            '{invoice_lastname}' => $invoice->lastname,
                            '{invoice_address2}' => $invoice->address2,
                            '{invoice_address1}' => $invoice->address1,
                            '{invoice_city}' => $invoice->city,
                            '{invoice_postal_code}' => $invoice->postcode,
                            '{invoice_country}' => $invoice->country,
                            '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
                            '{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
                            '{invoice_other}' => $invoice->other,
                            '{order_name}' => $order->getUniqReference() ,
                            '{date}' => Tools::displayDate(date('Y-m-d H:i:s') , (int)$order->id_lang, 1) ,
                            '{carrier}' => $virtual_product ? Tools::displayError('No carrier') : $carrier->name,
                            '{payment}' => Tools::substr($order->payment, 0, 32) ,
                            '{products}' => $this->formatProductAndVoucherForEmail($products_list) ,
                            '{discounts}' => $this->formatProductAndVoucherForEmail($cart_rules_list) ,
                            '{total_paid}' => Tools::displayPrice($order->total_paid, $this->context->currency, false) ,
                            '{total_products}' => Tools::displayPrice($order->total_paid - $order->total_shipping - $order->total_wrapping + $order->total_discounts, $this->context->currency, false) ,
                            '{total_discounts}' => Tools::displayPrice($order->total_discounts, $this->context->currency, false) ,
                            '{total_shipping}' => Tools::displayPrice($order->total_shipping, $this->context->currency, false) ,
                            '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $this->context->currency, false)
                        );
                        
                        
                        if (is_array($extra_vars)) $data = array_merge($data, $extra_vars);

                        
                        // Join PDF invoice
                        
                        if ((int)Configuration::get('PS_INVOICE') && $order_status->invoice && $order->invoice_number) {

                            $pdf = new PDF($order->getInvoicesCollection() , PDF::TEMPLATE_INVOICE, $this->context->smarty);
                            $file_attachement['content'] = $pdf->render(false);
                            $file_attachement['name'] = Configuration::get('PS_INVOICE_PREFIX', (int)$order->id_lang) . sprintf('%06d', $order->invoice_number) . '.pdf';
                            $file_attachement['mime'] = 'application/pdf';
                        } 
                        else $file_attachement = null;
                        
                        
                        if (Validate::isEmail($this->context->customer->email)) Mail::Send((int)$order->id_lang, 'order_conf', Mail::l('Order confirmation', (int)$order->id_lang) , $data, $this->context->customer->email, $this->context->customer->firstname . ' ' . $this->context->customer->lastname, null, null, $file_attachement, null, _PS_MAIL_DIR_, false, (int)$order->id_shop);

                    }
                    
                    // updates stock in shops
                    
                    if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {

                        $product_list = $order->getProducts();
                        
                        foreach ($product_list as $product) {

                            
                            // if the available quantities depends on the physical stock
                            
                            if (StockAvailable::dependsOnStock($product['product_id'])) {

                                
                                // synchronizes
                                StockAvailable::synchronize($product['product_id'], $order->id_shop);
                            }
                        }
                    }
                } 
                else {
                    $error = Tools::displayError('Order creation failed');
                    Logger::addLog($error, 4, '0000002', 'Cart', intval($order->id_cart));
                    die($error);
                }
            }
             // End foreach $order_detail_list
            // Use the last order as currentOrder
            $this->currentOrder = (int)$order->id;
            return true;
        } 
        else {
            $error = Tools::displayError('Cart cannot be loaded or an order has already been placed using this cart');
            Logger::addLog($error, 4, '0000001', 'Cart', intval($this->context->cart->id));
            die($error);
        }
    }
    
    private function _postValidation()
    {
        
        if (Tools::isSubmit('btnSubmit')) {

            
            if (intval(Tools::getValue('COD_FEE')) < 0) {

                return false;
            }
            
            
            if (intval(Tools::getValue('COD_FEEFREE')) <= 0) {

                $_POST['COD_FEEFREE'] = 0;
            }
            return true;
        }
    }
    
    private function _displayForm()
    {
        
        /*///////////////////////////////////////////////////////////////////////////////
        ///////////////////// CREACIÓN DEL ARCHIVO RECARGOMINIMO.PHP ////////////////////
        ///////////////////////////////////////////////////////////////////////////////*/
        $archivo = "../modules/cashondelivery/recargominimo.php";
        $valor = str_replace(",", ".", $_POST["minimo"]);
        $valor = str_replace("'", ".", $valor);
        $valor = array(
            $valor
        );
        
        /*///ASEGURAMOS QUE SEA UN VALOR NUMÉRICO///*/
        
        foreach ($valor as $element) {

            
            if (is_numeric($element)) {

                $valor = $element;
            } 
            else {
                $valor = 0;
            }
        }
        
        /*///// ARCHIVO A CREAR /////*/
        $contenido = '<?php $RecargoMinimo = ' . $valor . ';?>';
        
        /*/// ASEGURAMOS QUE SOLO SE CREA EL ARCHIVO SI SE ENVIA EL FORMULARIO ///*/
        
        if ($_POST["minimo"] == true) {

            $fch = fopen($archivo, 'w');
            fwrite($fch, $contenido);
            fclose($fch);
        }
        
        /*///////////////////////////////////////////////////////////////////////////////
        ///////////////////// CREACIÓN DEL ARCHIVO RECARGOMINIMO.PHP ////////////////////
        ///////////////////////////////////////////////////////////////////////////////*/
        require 'recargominimo.php';
        $currency = Currency::getCurrencyInstance(Configuration::get('PS_CURRENCY_DEFAULT'));
        $this->_html.= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
        $this->_html.= '<table><tr><td style="width:120px">' . $this->l('Minimum Surcharge') . ':</td><td style="width:111px"><input type="text" name="minimo" style="text-align:center;width:60px;padding:0;border:2px inset" value="' . $RecargoMinimo . '"> <b>' . $currency->sign . '</b></td><td style="border:solid 1px #ccc;padding:0 4px;background:white;color:green;font-weight:bold">' . $this->l('You can insert a minimum, if the charge does not exceed this amount in any case apply the minimum. For value = 0, insert value = 0.0') . '</td></tr><tr><td style="width:120px">' . $this->l('Recargo') . ':</td><td style="width:111px"><input  name="COD_FEE" value="' . Tools::convertPrice($this->fee) . '" style="text-align:center;width:60px"/> <b>' . $currency->sign . '</b> ' . $this->l('or') . ' <b>%</b></td><td style="border:solid 1px #ccc;padding:1px 4px;background:white;color:green;font-weight:bold">' . $this->l('To assign a percentage value entered is less than (100), for example if you want to assign a surcharge (2 percent) will have to insert the value (2).') . '<br/>' . $this->l('To assign a value fixed mark inserted above (100), for example if you want to assign a surcharge (2 € or $) you have to insert the value (200).') . '</td><tr><td>' . $this->l('Free from') . ':</td><td><input  name="COD_FEEFREE" value="' . Tools::convertPrice($this->feefree) . '" style="text-align:center;width:60px"/> <b>' . $currency->sign . '</b></td><td style="border:solid 1px #ccc;padding:0 4px;background:white;color:green;font-weight:bold">' . $this->l('Indicates the minimum price at which the COD payment charge will be free.') . '</td></tr></table>';
        $this->_html.= '<br /><br />' . $this->l('Carriers, leave blank for all or marking that if they apply the surcharge.') . '<br/><br/>' . $this->fetchCarriers($this->allowed_carriers) . '<br/><input class="button" name="btnSubmit" value="' . $this->l('Save') . '" type="submit"/></fieldset>';
        $this->_html.= '</form><span style="font-weight:bold;float:right;margin-top:-15px;color:#444">Created by <a target="_blank" href="http://www.prestashop.com/" style="color:green;font-weight:bold">PrestaShop</a>, modified by <a target="_blank" href="http://prestahost.eu/" style="color:green;font-weight:bold">PrestaHost</a>, and the latest changes by <a target="_blank" href="http://www.takeonme.es/" style="color:green;font-weight:bold">TakeonMe</a>.</span></div>';
    }
    
    private function _postProcess()
    {
        
        if (Tools::isSubmit('btnSubmit')) {

            
            Configuration::updateValue('COD_FEE', intval(Tools::getValue('COD_FEE')));
            $this->fee = intval(Tools::getValue('COD_FEE'));
            Configuration::updateValue('COD_FEEFREE', intval(Tools::getValue('COD_FEEFREE')));
            $this->feefree = intval(Tools::getValue('COD_FEEFREE'));
            
            $carrier = Tools::getValue('carrier');
            
            if (!empty($carrier) && is_array($carrier)) {

                $this->allowed_carriers = array_keys($carrier);
                Configuration::updateValue('COD_CARRIERS', serialize($this->allowed_carriers));
            } 
            else {
                $this->allowed_carriers = array();
                Configuration::updateValue('COD_CARRIERS', null);
            }
            
            $this->_html.= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="' . $this->l('OK') . '" />' . $this->l('Changes Saved') . '</div>';
        }
    }
    
    private function _displayCod()
    {
        $this->_html.= '<div style="border:solid 1px #ccc;padding:15px;background:#eee;border-radius:3px"><img src="../modules/cashondelivery/cashondelivery.jpg" style="float:left; margin-right:15px;border:solid 1px #ccc;padding:5px;background:#fff"><b>' . $this->l('Module Cash on Delivery') . '<br /></b><br />';
    }
    protected function isAllowedCarrier($id_carrier)
    {
        
        // No restriction if allowed_carriers is empty
        
        if (!is_array($this->allowed_carriers) || !count($this->allowed_carriers)) return true;

        
        if (in_array($id_carrier, $this->allowed_carriers)) return true;

        return false;
    }
    
    protected function fetchCarriers($selected)
    {
        $id_lang = Configuration::get(PS_LANG_DEFAULT);
        $carriers = Carrier::getCarriers($id_lang, true);
        $retval = '';
        
        foreach ($carriers as $carrier) {

            $retval.= "<input type='checkbox' name='carrier[{$carrier['id_carrier']}]' value='{$carrier['id_carrier']}'";
            
            if (is_array($selected) && in_array($carrier['id_carrier'], $selected)) $retval.= ' checked="checked"';

            $retval.= '/> - ' . $carrier['name'] . '<br/>';
        }
        return $retval;
    }
    
    protected function _renumberCarriers($params)
    {
        
        if ($params['carrier']->id && ($params['carrier']->id != $params['id_carrier']) && is_array($this->allowed_carriers)) {

            $carriers = array();
            
            foreach ($this->allowed_carriers as $carrier) {

                $carriers[] = ($carrier == $params['id_carrier']) ? $params['carrier']->id : $carrier;
            }
            $this->allowed_carriers = $carriers;
        }
    }
}
