        <thead>
{*
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
*  @version  Release: $Revision: 14011 $
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" rel="nofollow" title="{l s='Go back to the Checkout' mod='cashondelivery'}">{l s='Checkout' mod='cashondelivery'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Cash on delivery (COD) payment' mod='cashondelivery'}
{/capture}

<h2>{l s='Order Summary' mod='cashondelivery'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

<h3>{l s='Cash on delivery (COD) payment' mod='cashondelivery'}</h3>

<form action="{$link->getModuleLink('cashondelivery', 'validation', [], true)}" method="post">
    
    <input type="hidden" name="confirm" value="1" />

    <p>{l s='You have chosen the cash on delivery method.' mod='cashondelivery'}</p><br/><br />

    <table width="95%" align="center" id="cart_summary" class="std">
        <thead>
            <th>{l s='Cash on delivery (COD) payment' mod='cashondelivery'}</th>
            <th style="text-align:center;width:115px">Total</th>
        </thead>
        <tr>
            <td>
            {l s='The total amount of your order is' mod='cashondelivery'}
            </td>
            <td style="text-align:center">
            {convertPrice price=$total-$dobirecne}
            </td>
        </tr>
        <tr>
            <td>{l s='Surcharge for cash on delivery' mod='cashondelivery'}, <span style="font-style:oblique;color:#555">( {l s='this amount will be added to the shipping' mod='cashondelivery'} )</span>
            </td>
            <td style="text-align:center">{convertPrice price=$dobirecne}</td>
        </tr>
        <tr>
            <td rowspan="2">
                <p style="margin-top:4%">{l s='Total Order' mod='cashondelivery'},
                    <em style="color:#555">
                    {if $use_taxes == 1}
                        {l s='(tax incl.)' mod='cashondelivery'}
                    {/if}
                    </em>
                </p>
            </td>
            <td style="background-color:#333;font-size:112%;color:white">TOTAL:</td>
        </tr>
        <tr>
            <td style="text-align:center;font-size:155%">{convertPrice price=$total}</td>
        </tr>
    </table>
    <br/><br/>
    <p>
        <b>{l s='Please confirm your order by clicking \'I confirm my order\'' mod='cashondelivery'}.</b>
    </p>
    <p class="cart_navigation" id="cart_navigation">
        <a href="{$link->getPageLink('order', true)}?step=3" class="button-exclusive btn btn-default"><i class="icon-chevron-left"></i>{l s='Other payment methods' mod='cashondelivery'}</a>
        <button type="submit" class="button btn btn-default button-medium"><span>{l s='I confirm my order' mod='cashondelivery'}</span></button>
    </p>
</form>
