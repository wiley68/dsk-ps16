{*
    * @File: dskpayment_cart.tpl
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Банка ДСК
    * @Version: 1.2.0
*}
<div id="dskapi-product-button-container" {if $dskapi_gap > 0}style="margin-top:{$dskapi_gap}px;" {/if}>
    <table class="dskapi_table">
        <tr>
            <td class="dskapi_button_table">
                <div class="dskapi_button_div_txt">
                    {$dskapi_zaglavie}
                </div>
            </td>
        </tr>
    </table>
    <table class="dskapi_table_img">
        <tr>
            <td class="dskapi_button_table">
                {if $dskapi_custom_button_status eq 1}
                    <img id="btn_dskapi" class="dskapi_btn_click dskapi_logo" src="{$dskapi_button_normal_custom}"
                        alt="Кредитен калкулатор DSK Credit" onmouseover="this.src='{$dskapi_button_hover_custom}'"
                        onmouseout="this.src='{$dskapi_button_normal_custom}'" />
                {else}
                    <img id="btn_dskapi" class="dskapi_btn_click dskapi_logo" src="{$dskapi_button_normal}"
                        alt="Кредитен калкулатор DSK Credit" onmouseover="this.src='{$dskapi_button_hover}'"
                        onmouseout="this.src='{$dskapi_button_normal}'" />
                {/if}
            </td>
        </tr>
        {if $dskapi_isvnoska eq 1}
            <tr>
                <td class="dskapi_button_table">
                    <p><span id="dskapi_vnoski_txt">{$dskapi_vnoski}</span> x <span
                            id="dskapi_vnoska_txt">{$dskapi_vnoska}</span> {$dskapi_sign}</p>
                </td>
            </tr>
        {/if}
    </table>
</div>
<input type="hidden" id="dskapi_price" value="{$dskapi_price}" />
<input type="hidden" id="dskapi_cid" value="{$dskapi_cid}" />
<input type="hidden" id="dskapi_product_id" value="{$dskapi_product_id}" />
<input type="hidden" id="DSKAPI_LIVEURL" value="{$DSKAPI_LIVEURL}" />
<input type="hidden" id="dskapi_button_status" value="{$dskapi_button_status}" />
<input type="hidden" id="dskapi_maxstojnost" value="{$dskapi_maxstojnost}" />
<input type="hidden" id="dskapi_eur" value="{$dskapi_eur}" />
<input type="hidden" id="dskapi_currency_code" value="{$dskapi_currency_code}" />
<input type="hidden" id="dskapi_checkout_url" value="{$dskapi_checkout_url}" />
<div id="dskapi-product-popup-container" class="modalpayment_dskapi">
    <div class="modalpayment-content_dskapi">
        <div id="dskapi_body">
            <div class="{$dskapi_PopUp_Detailed_v1}">
                <div class="{$dskapi_Mask}">
                    <img src="{$dskapi_picture}" class="dskapi_header">
                    <p class="{$dskapi_product_name}">Купи на изплащане със стоков кредит от Банка ДСК</p>
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
                            <select id="dskapi_pogasitelni_vnoski_input" class="dskapi_txt_right"
                                onchange="dskapi_pogasitelni_vnoski_input_change();"
                                onfocus="dskapi_pogasitelni_vnoski_input_focus(this.value);">
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
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_price_txt"
                                            readonly="readonly" value="{$dskapi_price}" />
                                    </div>
                                </div>
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">Месечна вноска /{$dskapi_sign}/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_vnoska"
                                            readonly="readonly" value="{$dskapi_vnoska}" />
                                    </div>
                                </div>
                            </div>
                            <div class="{$dskapi_sumi_panel}">
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">Обща дължима сума /{$dskapi_sign}/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_obshtozaplashtane"
                                            readonly="readonly" />
                                    </div>
                                </div>
                                <div class="{$dskapi_kredit_panel}">
                                    <div class="dskapi_sumi_txt">ГПР /%/</div>
                                    <div>
                                        <input class="dskapi_mesecna_price" type="text" id="dskapi_gpr"
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
                        <div class="dskapi_btn" id="dskapi_buy_credit">Купи на изплащане</div>
                        <div class="dskapi_btn_cancel" id="dskapi_back_credit">Откажи</div>
                        <div class="{$dskapi_body_panel_left}">
                            <div class="dskapi_txt_footer">Ver. {$DSKAPI_VERSION}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>