{*
    * @File: dskapipanel.tpl
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Avalon Ltd
    * @Version: 1.1.1
*}
{if $dskapi_deviceis eq 'mobile'}
<div class="dskapi_float" onclick="window.open('{$DSKAPI_LIVEURL}/procedure.php', '_blank');">
{else}
<div class="dskapi_float" onclick="DskapiChangeContainer();">
{/if}
    <img src="{$DSKAPI_LIVEURL}/dist/img/dsk_logo.png" class="dskapi-my-float">
</div>
<div class="dskapi-label-container">
    <div class="dskapi-label-text">
        <div class="dskapi-label-text-mask">
            <img src="{$dskapi_picture}" class="dskapi_header">
            <p class="dskapi_txt1">{$dskapi_container_txt1}</p>
            <p class="dskapi_txt2">{$dskapi_container_txt2}</p>
            <p class="dskapi-label-text-a"><a href="{$dskapi_logo_url}" target="_blank" alt="За повече информация">За повече информация</a></p>
        </div>
    </div>
</div>
<script type="application/javascript">
    function DskapiChangeContainer(){
        const dskapi_label_container = document.getElementsByClassName("dskapi-label-container")[0];
        if (dskapi_label_container.style.visibility == 'visible'){
            dskapi_label_container.style.visibility = 'hidden';
            dskapi_label_container.style.opacity = 0;
            dskapi_label_container.style.transition = 'visibility 0s, opacity 0.5s ease';
        }else{
            dskapi_label_container.style.visibility = 'visible';
            dskapi_label_container.style.opacity = 1;
        }
    }
</script>