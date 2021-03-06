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
*  @version  Release: $Revision: 15094 $
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * This is a fork from the work made available public by Prestashop member @zantos in topic  https://www.prestashop.com/forums/topic/75458-module-cash-on-delivery-cod-v05-with-fixed-and-percentage-fees/
 *
 * Initial code downloaded from @maximo88 post in https://www.prestashop.com/forums/topic/75458-module-cash-on-delivery-cod-v05-with-fixed-and-percentage-fees/page-7#entry1936755
 *  and have also applied the changes made by @sergiocues in post https://www.prestashop.com/forums/topic/75458-module-cash-on-delivery-cod-v05-with-fixed-and-percentage-fees/page-7#entry1979224
 * 
 * @author  Exadra37 <exadra37ingmailpointcom>
 * @package Exadra37/cashondelivery
 * @since   2015/04/14
 * @link    http://exadra37.com
 * 
 */

/**
 * @since 1.5.0
 */
class CashondeliveryValidationModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    
    public $ssl = true;


    public function postProcess()
    {
        if ($this->context->cart->id_customer == 0 || $this->context->cart->id_address_delivery == 0 || $this->context->cart->id_address_invoice == 0 || !$this->module->active) {

            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {

            if ($module['name'] == 'cashondelivery') {

                $authorized = true;
                break;
            }
        }

        if(!$authorized) {

            die(Tools::displayError('This payment method is not available.'));
        }

        $customer = new Customer($this->context->cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {

            Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');
        }

        if (Tools::getValue('confirm')) {

            $customer = new Customer((int) $this->context->cart->id_customer);

            $total    = $this->context->cart->getOrderTotal(true, Cart::BOTH) + $this->module->getFeeToPay();

            $this->module->validateOrder((int) $this->context->cart->id, Configuration::get('PS_OS_PREPARATION'), $total, $this->module->displayName, null, array(), null, false, $customer->secure_key);
            
            Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int) $this->context->cart->id.'&id_module='.(int) $this->module->id.'&id_order='.(int) $this->module->currentOrder);
        }
    }


    /**
    * @see FrontController::initContent()
    */
    public function initContent()
    {
        parent::initContent();

        $dobirecne = $this->module->getFeeToPay();

        $total     = $this->context->cart->getOrderTotal(true, Cart::BOTH);

        $this->context->smarty->assign(array(
            'totalbezdobirky' => $total,
            'dobirecne' => $dobirecne,
            'total'=>$total + $dobirecne,
            'this_path' => $this->module->getPathUri(),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
        ));

        $this->setTemplate('validation.tpl');
    }

    
}
