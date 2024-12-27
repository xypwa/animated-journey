<?php
/** @var CScsgDealTabComponent $component
 *  @var string $templateFolder
 *  @var array $arResult
 *  @var array $arParams
 */
\Bitrix\Main\UI\Extension::register("ui.dialogs.messagebox");
echo CJSCore::GetHTML(["jquery2", "ui.dialogs.messagebox", "ui.notification"]);
if (!empty($arResult['error'])): ?>
    <h2><?=$arResult['error']?></h2>
<?php else: ?>
<!--    <link href="--><?php //=$templateFolder?><!--/tabulator.min.css" rel="stylesheet" />-->
    <script type="text/javascript" src="<?=$templateFolder?>/moment-with-locales.min.js"></script>
    <script type="text/javascript" src="<?=$templateFolder?>/tabulator.js"></script>
    <script type="text/javascript" src="<?=$templateFolder?>/script_Common.js?<?=filemtime(__DIR__.'/script_Common.js')?>"></script>
    <style>
        #main-container {
            max-width: 650px;
        }
        #sub-containers {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 50% 50%;
            column-gap: 10px;
        }
        .tabulator-tableHolder:focus, .tabulator:focus, .tabulator-cell:focus, .tabulator-row:focus {
            box-shadow: 0px 0px 4px 1px #0c66c3 ;
        }
        .ui-btn-toolbar {
            display: inline-block;
            margin: 3px;
        }
        .ui-btn-toolbar .ui-btn:first-child {
            border-right: none !important;
            border-radius: 2px 0 0 2px;
        }
        .ui-btn-toolbar .ui-btn:last-child {
            border-left: none !important;
            border-radius: 0 2px 2px 0;
        }
        .ui-btn-toolbar > .ui-btn {
            margin-left: 0
        }
        .bi-input-control {
            height: var(--ui-btn-size-sm);
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: .25rem;
            margin-left: 5px;
            margin-right: 8px;
        }
        .flex-form input {
            width: 100px;
            font-size: 1rem;
            line-height: 1.5;
            padding: .375rem .75rem;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: .25rem;
        }
        #bi-supply-second {
            width: 100px;
            font-size: 1rem;
            line-height: 1.5;
            padding: .375rem .75rem;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: .25rem;
        }
        .flex-form {
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
        }
        .flex-form span {
            word-wrap: normal;
            flex: 0 1 100px;
            margin: 3px;
        }
        .flex-form span label {
            display: block;
            text-align: center;
        }
        .bi-cell-image-wrapper{
            min-height: 30px;
        }
        .bi-cell-image-wrapper > a > img{

            height: 60px;
        }
        .mli-search-results {
            background-color: white;
            border: solid 1px #333;
            margin: 0;
            padding: 2px;
            font-size: 12px;
            z-index: 1;
        }

        .mli-search-result {
            cursor: pointer;
            padding: 2px;
            margin: 1px
        }

        .mli-search-current {
            background-color: #eee
        }

        .mli-field {
            margin-right: 10px
        }

        .mli-layout {
            line-height: 14px
        }
        .translocate-date {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        .translocation-info {
            padding-left: 10px;
        }
    </style>
    <style>
        #loader {
            display: flex;
            text-align: center;
            width: 100%;
            height: 100%;
            position: absolute;
            z-index: 3;
            background: rgb(161 161 161 / 68%);
        }
        #loader.hide {
            display: none
        }
        .loader {
            position: relative;
            width: 150px;
            height: 150px;
            top: 10%;
            left: 50%;
        }

        .loader:before , .loader:after{
            content: '';
            border-radius: 50%;
            position: absolute;
            inset: 0;
            box-shadow: 0 0 10px 2px rgba(0, 0, 0, 0.3) inset;
        }
        .loader:after {
            box-shadow: 0 2px 0 #FF3D00 inset;
            animation: rotate 2s linear infinite;
        }

        @keyframes rotate {
            0% {  transform: rotate(0)}
            100% { transform: rotate(360deg)}
        }
        #history-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            /*border: 3px dashed #99811b;*/
            padding: 5px;
        }
    </style>
    <div id="loader" class="hide">
        <span class="loader"></span>
    </div>
    <h2><?=$arResult['tableTitle']?></h2>
    <div id="main-container">
        <div id="items-table-<?=$arResult['SRC_WH'].'_'.$arResult['DST_WH']?>" tabindex="0"></div>

        <div id="history-container">
            <div>
                <h3>Перемещения на этот склад</h3>
                <?if($arResult['HISTORY']['TO']):?>
                    <?foreach ($arResult['HISTORY']['TO'] as $Translocation):?>
                        <div class="translocate-date"><?=$Translocation['CREATE_DATE']?></div>
                        <div>(<?=$Translocation['USER']?>)</div>
                        <?foreach ($Translocation['ORDERS'] as $PositionHistory):?>
                            <div class="translated-position-info">
                                <div class="translocation-info">
                                    <b><?=$PositionHistory['PRODUCT']?></b> -
                                    <?=$PositionHistory['QUANTITY']?> шт.
                                </div>
                            </div>
                        <?endforeach;?>
                    <?endforeach;?>
                <?endif;?>
            </div>
            <div>
                <h3>Перемещения с этого склада</h3>
                <?if($arResult['HISTORY']['FROM']):?>
                    <?foreach ($arResult['HISTORY']['FROM'] as $Translocation):?>
                        <div class="translocate-date"><?=$Translocation['CREATE_DATE']?></div>
                        <div>(<?=$Translocation['USER']?>)</div>
                        <?foreach ($Translocation['ORDERS'] as $PositionHistory):?>
                            <div class="translated-position-info">
                                <div class="translocation-info">
                                    <b><?=$PositionHistory['PRODUCT']?></b> -
                                    <?=$PositionHistory['QUANTITY']?> шт.
                                </div>
                            </div>
                        <?endforeach;?>
                    <?endforeach;?>
                <?endif;?>
            </div>
        </div>
    </div>


    <span id="debug-span">
    </span>
    <script>

        (function() {
            function parseFloatEx(v) {
                return parseFloat(String(v).replace(',', '.'));
            }
            // debugger

            let tab_id = '<?=$arParams['TAB_ID'];?>';

            let srcWH = "<?=$arResult['SRC_WH']?>";
            let dstWH = "<?=$arResult['DST_WH']?>";
            function Reload() {

                // $("#loader").removeClass("hide");

                BX.ajax.runComponentAction('b-integration:rusir.warehouse.translocation',
                    'reload', { // Вызывается без постфикса Action
                        mode: 'class',
                        data: {
                            sessid: BX.message('bitrix_sessid'),
                            entTypeID: <?=$arParams['ENTITY_TYPE_ID'];?>,
                            entityID: <?=$arParams['ENTITY_ID'];?>,
                            mode: 'translocation',
                            tab_id: tab_id,
                            wh: {src: srcWH, dst: dstWH}
                        }, // ключи объекта data соответствуют параметрам метода
                    })
                    .then(function(response) {
                        console.log(response)
                        if (response.status === 'success') {
                            console.log('reloaded')
                            $(`div[data-tab-id='${tab_id}']`).html(response.data);
                            $("#loader").addClass("hide");

                        }
                    });
            }


            let mainTable = new BI.RusirTableControllerCommon(
                `#items-table-${srcWH}_${dstWH}`,
                '<?=$arResult['tableId']?>',
                <?=$arParams['ENTITY_TYPE_ID']?>,
                <?=$arParams['ENTITY_ID']?>,
                <?=CUtil::PhpToJSObject($arResult['columns']);?>,
                <?=CUtil::PhpToJSObject($arResult['rows']);?>,
                {
                    noAddDelete: true,
                    noSaveButton: false,
                    srcWH,
                    dstWH
                }
            );

            mainTable.callbacks.beforeCommit = function(data) {
                $("#loader").removeClass("hide");
            }
            mainTable.callbacks.afterCommit = function(data) {
                debugger
                if(data.doc) {
                    BX.UI.Notification.Center.notify({
                        content: `Документ по перемещению был сформирован!`,
                        autoHideDelay: 10000,
                        actions: [
                            {
                                title: "Открыть",
                                events: {
                                    click: function(event, balloon, action) {
                                        balloon.close()
                                        BX.SidePanel.Instance.open(data.doc)
                                    }
                                }
                            },
                        ]
                    })
                }
                if(data.translocation_link) {
                    // BX.SidePanel.Instance.open(data.translocation_link)
                }
                if(data.package_link) {
                    BX.SidePanel.Instance.open(data.package_link)
                }
                Reload();
                console.log(data)
            }
        })();
    </script>
<?php endif;
