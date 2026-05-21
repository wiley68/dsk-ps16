{*
    * @File: dskpayment_product.tpl
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Банка ДСК
    * @Version: 1.2.2
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
<input type="hidden" id="DSKAPI_PRODUCT_API_URL" value="{$DSKAPI_PRODUCT_API_URL}" />
<input type="hidden" id="dskapi_button_status" value="{$dskapi_button_status}" />
<input type="hidden" id="dskapi_maxstojnost" value="{$dskapi_maxstojnost}" />
<input type="hidden" id="dskapi_eur" value="{$dskapi_eur}" />
<input type="hidden" id="dskapi_currency_code" value="{$dskapi_currency_code}" />
<input type="hidden" id="dskapi_checkout_url" value="{$dskapi_checkout_url}" />
<div id="dskapi-product-popup-container" class="modalpayment_dskapi">
    <div class="modalpayment-content_dskapi">
        <div id="dskapi_body">
            {include file='./dskapi_popup_calculator.tpl' dskapi_popup_title='Купи на изплащане със стоков кредит от Банка ДСК' dskapi_popup_context='product'}
        </div>
    </div>
</div>