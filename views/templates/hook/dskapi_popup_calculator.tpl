{*
    Shared calculator popup markup (responsive via CSS).
    @param string $dskapi_popup_title Popup heading
    @param string $dskapi_popup_context product|payment
*}
<div class="dskapi_PopUp_Detailed_v1">
    <div class="dskapi_Mask">
        <img src="{$dskapi_picture_desktop|escape:'html':'UTF-8'}" class="dskapi_header dskapi_header--desktop"
            alt="Банка ДСК">
        <img src="{$dskapi_picture_mobile|escape:'html':'UTF-8'}" class="dskapi_header dskapi_header--mobile"
            alt="Банка ДСК">
        <p class="dskapi_product_name">{$dskapi_popup_title|escape:'html':'UTF-8'}</p>
        <div class="dskapi_body_panel_txt3">
            <div class="dskapi_body_panel_txt3_left">
                <p>
                    • Улеснена процедура за електронно подписване<br />
                    • Атрактивни условия по кредита<br />
                    • Параметри изцяло по Ваш избор<br />
                    • Одобрение до няколко минути изцяло онлайн
                </p>
            </div>
            <div class="dskapi_body_panel_txt3_right">
                {if $dskapi_popup_context == 'payment'}
                    <select id="dskapi_payment_pogasitelni_vnoski_input" class="dskapi_txt_right"
                        onchange="dskapi_payment_pogasitelni_vnoski_input_change();"
                        onfocus="dskapi_payment_pogasitelni_vnoski_input_focus(this.value);">
                    {else}
                        <select id="dskapi_pogasitelni_vnoski_input" class="dskapi_txt_right"
                            onchange="dskapi_pogasitelni_vnoski_input_change();"
                            onfocus="dskapi_pogasitelni_vnoski_input_focus(this.value);">
                        {/if}
                        {for $i=3 to 48}
                            {if $dskapi_vnoski_visible_arr[$i]}
                                <option value="{$i}" {if $dskapi_vnoski == $i}selected{/if}>{$i} месеца</option>
                            {/if}
                        {/for}
                    </select>
                <div class="dskapi_sumi_panel">
                    <div class="dskapi_kredit_panel">
                        <div class="dskapi_sumi_txt">Размер на кредита /{$dskapi_sign}/</div>
                        <div>
                            {if $dskapi_popup_context == 'payment'}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_price_txt"
                                    readonly="readonly" value="{$dskapi_price}" />
                            {else}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_price_txt" readonly="readonly"
                                    value="{$dskapi_price}" />
                            {/if}
                        </div>
                    </div>
                    <div class="dskapi_kredit_panel">
                        <div class="dskapi_sumi_txt">Месечна вноска /{$dskapi_sign}/</div>
                        <div>
                            {if $dskapi_popup_context == 'payment'}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_vnoska"
                                    readonly="readonly" value="{$dskapi_vnoska}" />
                            {else}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_vnoska" readonly="readonly"
                                    value="{$dskapi_vnoska}" />
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="dskapi_sumi_panel">
                    <div class="dskapi_kredit_panel">
                        <div class="dskapi_sumi_txt">Обща дължима сума /{$dskapi_sign}/</div>
                        <div>
                            {if $dskapi_popup_context == 'payment'}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_obshtozaplashtane"
                                    readonly="readonly" />
                            {else}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_obshtozaplashtane"
                                    readonly="readonly" />
                            {/if}
                        </div>
                    </div>
                    <div class="dskapi_kredit_panel">
                        <div class="dskapi_sumi_txt">ГПР /%/</div>
                        <div>
                            {if $dskapi_popup_context == 'payment'}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_payment_gpr"
                                    readonly="readonly" />
                            {else}
                                <input class="dskapi_mesecna_price" type="text" id="dskapi_gpr" readonly="readonly" />
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="dskapi_body_panel_txt4">
            Изчисленията са направени при допускането за първа падежна дата след 30 дни и са с насочваща
            цел. Избери най-подходящата месечна вноска.
        </div>
        <div class="dskapi_body_panel_footer">
            {if $dskapi_popup_context == 'payment'}
                <div class="dskapi_btn_close" id="dskapi_payment_close">Затвори</div>
            {else}
                <div class="dskapi_btn" id="dskapi_buy_credit">Купи на изплащане</div>
                <div class="dskapi_btn_cancel" id="dskapi_back_credit">Откажи</div>
            {/if}
            <div class="dskapi_body_panel_left">
                <div class="dskapi_txt_footer">Ver. {$DSKAPI_VERSION}</div>
            </div>
        </div>
    </div>
</div>
