{*
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
    *  @copyright  2018-2019 PrestaShop SA
    *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
    *  International Registered Trademark & Property of PrestaShop SA
    *}

    {if $status == 'ok'}
    <p>{l s='Your order on %s is complete.' sprintf=[$shop_name] mod='beyonic'}
        <br /><br />- {l s='Note: Payment instructions have been sent to your phone %s.' sprintf=[$beyonic_phone] mod='beyonic'}
        {l s='Please check your phone to complete the payment.' mod='beyonic'}
        <br /><br /><strong>{l s='Your order cannot be delivered until you complete the payment on your phone.' mod='beyonic'}</strong>
        <br /><br />{l s='For any questions or for further information, please contact our' mod='beyonic'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='beyonic'}</a>.
    </p>
    {else}
    <p class="warning">
        {l s=' %s ' sprintf=[$error] mod='beyonic'}
        {l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='beyonic'}
        <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='beyonic'}</a>.
    </p>
    {/if}
