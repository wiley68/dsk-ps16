{*
    * @File: payment_execution.tpl
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Банка ДСК
    * @Version: 1.2.0
*}
{capture name=path}
    <a href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}" title="{l s='Обратно към методи на плащане' mod='dskpayment'}">{l s='Методи на плащане' mod='dskpayment'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='Банка ДСК покупки на Кредит' mod='dskpayment'}
{/capture}
{* {include file="$tpl_dir./breadcrumb.tpl"} *}
{assign var='current_step' value='payment'}
{include file="$tpl_dir./order-steps.tpl"}
{if $dskapi_nbProducts <= 0}
    <p class="warning">{l s='Вашата кошница е празна ' mod='dskpayment'}</p>
{else}
<h3>{l s='С избора си да финансирате покупката чрез Банка ДСК Вие декларирате, че сте запознат с Информацията относно обработването на лични данни на физически лица от Банка ДСК АД.' mod='dskpayment'}</h3>
<a target="_blank" href="https://dskbank.bg/docs/default-source/gdpr/%D0%B8%D0%BD%D1%84%D0%BE%D1%80%D0%BC%D0%B0%D1%86%D0%B8%D1%8F-%D0%BE%D1%82%D0%BD%D0%BE%D1%81%D0%BD%D0%BE-%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%B2%D0%B0%D0%BD%D0%B5%D1%82%D0%BE-%D0%BD%D0%B0-%D0%BB%D0%B8%D1%87%D0%BD%D0%B8-%D0%B4%D0%B0%D0%BD%D0%BD%D0%B8-%D0%BD%D0%B0-%D1%84%D0%B8%D0%B7%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8-%D0%BB%D0%B8%D1%86%D0%B0-%D0%BE%D1%82-%D0%B1%D0%B0%D0%BD%D0%BA%D0%B0-%D0%B4%D1%81%D0%BA-%D0%B0%D0%B4-%D0%B8-%D1%81%D1%8A%D0%B3%D0%BB%D0%B0%D1%81%D0%B8%D1%8F-%D0%B7%D0%B0-%D0%BE%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%B2%D0%B0%D0%BD%D0%B5-%D0%BD%D0%B0-%D0%BB%D0%B8%D1%87%D0%BD%D0%B8-%D0%B4%D0%B0%D0%BD%D0%BD%D0%B8.pdf">Информация относно обработването на лични данни на физически лица от Банка ДСК АД</a>
<br style="clear:both;" />
<br style="clear:both;" />
{if $dskapi_popup_enabled}
<a href="#" id="dskapi_checkout_interest_rates_link" style="cursor: pointer;">
    Лихвени схеми
</a>
<br style="clear:both;" />
<br style="clear:both;" />
{* Скрити полета за попъпа *}
<input type="hidden" id="dskapi_payment_price" value="{$dskapi_price}" />
<input type="hidden" id="dskapi_payment_cid" value="{$dskapi_cid}" />
<input type="hidden" id="dskapi_payment_product_id" value="{$dskapi_product_id}" />
<input type="hidden" id="dskapi_payment_LIVEURL" value="{$DSKAPI_LIVEURL}" />
<input type="hidden" id="dskapi_payment_maxstojnost" value="{$dskapi_maxstojnost}" />
<input type="hidden" id="dskapi_payment_eur" value="{$dskapi_eur}" />
<input type="hidden" id="dskapi_payment_currency_code" value="{$dskapi_currency_code}" />

{* Попъп с лихвени схеми *}
<div id="dskapi-payment-popup-container" class="modalpayment_dskapi">
    <div class="modalpayment-content_dskapi">
        <div id="dskapi_body">
            <div class="{$dskapi_PopUp_Detailed_v1}">
                <div class="{$dskapi_Mask}">
                    <img src="{$dskapi_picture}" class="dskapi_header" alt="Банка ДСК">
                    <p class="{$dskapi_product_name}">Лихвени схеми за стоков кредит от Банка ДСК</p>
                    <div class="{$dskapi_body_panel_txt3}">
                        <div class="{$dskapi_body_panel_txt3_left}">
                            <p>
                                • Улеснена процедура за електронно подписване<br />
                                • Атрактивни условия по кредита<br />
                                • Параметри изцяло по Ваш избор<br />
                                • Одобрение до няколко минути изцяло онлайн
                            </p>
                        </div>
                        <div class="{$dskapi_body_panel_txt3_right}">
                            <select id="dskapi_payment_pogasitelni_vnoski_input" class="dskapi_txt_right"
                                onchange="dskapi_payment_pogasitelni_vnoski_input_change();"
                                onfocus="dskapi_payment_pogasitelni_vnoski_input_focus(this.value);">
                                {for $i=3 to 48}
                                    {if $dskapi_vnoski_visible_arr[$i]}
                                        <option value="{$i}" {if $dskapi_vnoski == $i}selected{/if}>{$i} месеца</option>
                                    {/if}
                                {/for}
                            </select>
                            <div class="{$dskapi_sumi_panel}">
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">Размер на кредита /{$dskapi_sign}/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_price_txt"
                                            readonly="readonly" value="{$dskapi_price}" />
                                    </div>
                                </div>
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">Месечна вноска /{$dskapi_sign}/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_vnoska"
                                            readonly="readonly" value="{$dskapi_vnoska}" />
                                    </div>
                                </div>
                            </div>
                            <div class="{$dskapi_sumi_panel}">
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">Обща дължима сума /{$dskapi_sign}/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_obshtozaplashtane"
                                            readonly="readonly" />
                                    </div>
                                </div>
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">ГПР /%/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_gpr"
                                            readonly="readonly" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="{$dskapi_body_panel_txt4}">
                        Изчисленията са направени при допускането за първа падежна дата след 30 дни и са с насочваща
                        цел. Избери най-подходящата месечна вноска.
                    </div>
                    <div class="{$dskapi_body_panel_footer}">
                        <div class="dskapi_btn_close" id="dskapi_payment_close">Затвори</div>
                        <div class="{$dskapi_body_panel_left}">
                            <div class="dskapi_txt_footer">Ver. {$DSKAPI_VERSION}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{/if}
<form action="{$link->getModuleLink('dskpayment', 'validation', [], true)|escape:'html'}" method="post">
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