{*
    * @File: dskapipanel.tpl
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Банка ДСК
    * @Version: 1.2.0
*}
{if $dskapi_deviceis eq 'mobile'}
    <div class="dskapi_float" onclick="window.open('{$DSKAPI_LIVEURL}/procedure.php', '_blank');">
    {else}
        <div class="dskapi_float" onclick="DskapiChangeContainer();">
        {/if}
        <img src="{$dskapi_logo}" class="dskapi-my-float">
    </div>
    <div class="dskapi-label-container">
        <div class="dskapi-label-text">
            <div class="dskapi-label-text-mask">
                <img src="{$dskapi_picture}" class="dskapi_header">
                <p class="dskapi_txt1">{$dskapi_container_txt1}</p>
                <p class="dskapi_txt2">{$dskapi_container_txt2}</p>
                <p class="dskapi-label-text-a"><a href="{$dskapi_logo_url}" target="_blank"
                        alt="За повече информация">За повече информация</a></p>
            </div>
        </div>
</div>