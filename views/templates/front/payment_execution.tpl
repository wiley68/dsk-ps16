{*
    * @File: payment_execution.tpl
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Avalon Ltd
    * @Version: 1.1.1
*}
{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Обратно към методи на плащане' mod='dskpayment'}">{l s='Методи на плащане' mod='dskpayment'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='DSK Credit API покупки на Кредит' mod='dskpayment'}
{/capture}
{* {include file="$tpl_dir./breadcrumb.tpl"} *}
{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
{if $dskapi_nbProducts <= 0}
    <p class="warning">{l s='Вашата кошница е празна ' mod='dskpayment'}</p>
{else}
<h3>{l s='С избора си да финансирате покупката чрез Банка ДСК Вие декларирате, че сте запознат с Информацията относно обработването на лични данни на физически лица от Банка ДСК АД.' mod='dskpayment'}</h3>
<a target="_blank" href="https://dskbank.bg/docs/default-source/gdpr/%D0%B8%D0%BD%D1%84%D0%BE%D1%80%D0%BC%D0%B0%D1%86%D0%B8%D1%8F-%D0%BE%D1%82%D0%BD%D0%BE%D1%81%D0%BD%D0%BE-%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%B2%D0%B0%D0%BD%D0%B5%D1%82%D0%BE-%D0%BD%D0%B0-%D0%BB%D0%B8%D1%87%D0%BD%D0%B8-%D0%B4%D0%B0%D0%BD%D0%BD%D0%B8-%D0%BD%D0%B0-%D1%84%D0%B8%D0%B7%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8-%D0%BB%D0%B8%D1%86%D0%B0-%D0%BE%D1%82-%D0%B1%D0%B0%D0%BD%D0%BA%D0%B0-%D0%B4%D1%81%D0%BA-%D0%B0%D0%B4-%D0%B8-%D1%81%D1%8A%D0%B3%D0%BB%D0%B0%D1%81%D0%B8%D1%8F-%D0%B7%D0%B0-%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%B2%D0%B0%D0%BD%D0%B5-%D0%BD%D0%B0-%D0%BB%D0%B8%D1%87%D0%BD%D0%B8-%D0%B4%D0%B0%D0%BD%D0%BD%D0%B8.pdf">Информация относно обработването на лични данни на физически лица от 'Банка ДСК' АД</a>
<form action="{$link->getModuleLink('dskpayment', 'validation', [], true)|escape:'html'}" method="post">
    <p>
        <img src="{$dskapi_this_path_bw}logo.png" alt="{l s='DSK Credit API покупки на Кредит' mod='bnplpayment'}" style="float:left; margin: 0px 10px 5px 0px;" />
    </p>
    <br /><br /><br /><br />
    <input type="hidden" name="dskapi_firstname" id="dskapi_firstname" value="{$dskapi_firstname}" />
    <input type="hidden" name="dskapi_lastname" id="dskapi_lastname" value="{$dskapi_lastname}" />
    <input type="hidden" name="dskapi_phone" id="dskapi_phone" value="{$dskapi_phone}" />
    <input type="hidden" name="dskapi_email" id="dskapi_email" value="{$dskapi_email}" />
    <input type="hidden" name="dskapi_address2" id="dskapi_address2" value="{$dskapi_address2}" />
    <input type="hidden" name="dskapi_address2city" id="dskapi_address2city" value="{$dskapi_address2city}" />
    <input type="hidden" name="dskapi_address1" id="dskapi_address1" value="{$dskapi_address1}" />
    <input type="hidden" name="dskapi_address1city" id="dskapi_address1city" value="{$dskapi_address1city}" />
    <input type="hidden" name="dskapi_postcode" id="dskapi_postcode" value="{$dskapi_postcode}" />
    <input type="hidden" name="dskapi_eur" id="dskapi_eur" value="{$dskapi_eur}" />
    <p class="cart_navigation" id="cart_navigation">
    <button id="submit_dskapi" class="exclusive_large" type="submit">Потвърждаване на поръчката</button>
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}" >{l s='Други методи на плащане' mod='dskpayment'}</a>
    </p>
</form>
{/if}