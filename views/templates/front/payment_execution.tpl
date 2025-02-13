{**
 * SpectroCoin Module
 *
 * Copyright (C) 2014-2025 SpectroCoin
 *
 * This template is part of the SpectroCoin module.
 * It is distributed under the terms of the GNU General Public License,
 * either version 2 of the License or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see the GNU General Public License.
 *}
{capture name=path}{l s='SpectroCoin payment' mod='spectrocoin'}{/capture}
{include file="{$tpl_dir}breadcrumb.tpl"}

<h2>{l s='Order summary' mod='spectrocoin'}</h2>

{assign var='current_step' value='payment'}
{include file="{$tpl_dir}order-steps.tpl"}

{if isset($nbProducts) && $nbProducts <= 0}
  <p class="warning">{l s='Your shopping cart is empty.' mod='spectrocoin'}</p>
{else}

<h3>{l s='Crypto provided by SpectroCoin' mod='spectrocoin'}</h3>
<form action="{$link->getModuleLink('spectrocoin', 'redirect', [], true)|escape:'html':'UTF-8'}" method="post">
  <p>
    <img src="{$this_path_bw|escape:'htmlall':'UTF-8'}spectrocoin-logo.svg" alt="{l s='SpectroCoin' mod='spectrocoin'|escape:'html':'UTF-8'}" width="129" height="49" style="float:left; margin: 0px 10px 5px 0px;" />
    {l s='You have chosen to pay by crypto.' mod='spectrocoin'}
    <br/><br />
    {l s='Here is a short summary of your order:' mod='spectrocoin'}
  </p>
  <p style="margin-top:20px;">
    - {l s='The total amount of your order comes to:' mod='spectrocoin'}
    <span id="amount" class="price">{displayPrice price=$total}</span>
    {if $use_taxes == 1}
      {l s='(tax incl.)' mod='spectrocoin'}
    {/if}
  </p>
  <p>
    <br />
    <b>{l s='Please confirm your order by clicking \'I confirm my order\'' mod='spectrocoin'}.</b>
  </p>
  <p class="cart_navigation" id="cart_navigation">
    <input type="submit" value="{l s='I confirm my order' mod='spectrocoin'}" class="exclusive_large"/>
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" class="button_large">{l s='Other payment methods' mod='spectrocoin'}</a>
  </p>
</form>
{/if}
