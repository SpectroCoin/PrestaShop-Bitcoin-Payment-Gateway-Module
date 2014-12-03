{capture name=path}{l s='SpectroCoin payment' mod='spectrocoin'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h2>{l s='Order summary' mod='spectrocoin'}</h2>

{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}

{if isset($nbProducts) && $nbProducts <= 0}
  <p class="warning">{l s='Your shopping cart is empty.' mod='spectrocoin'}</p>
{else}

<h3>{l s='Bitcoin provided by SpectroCoin' mod='spectrocoin'}</h3>
<form action="{$link->getModuleLink('spectrocoin', 'redirect', [], true)|escape:'html'}" method="post">
  <p>
    <img src="{$this_path_bw}bitcoin.png" alt="{l s='SpectroCoin' mod='spectrocoin'}" width="129" height="49" style="float:left; margin: 0px 10px 5px 0px;" />
    {l s='You have chosen to pay by Bitcoin.' mod='spectrocoin'}
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
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}" class="button_large">{l s='Other payment methods' mod='spectrocoin'}</a>
  </p>
</form>
{/if}
