<?php
/** @var CScsgDealTabComponent $component
 *  @var string $templateFolder
 *  @var array $arResult
 *  @var array $arParams
 */
\Bitrix\Main\UI\Extension::register("ui.dialogs.messagebox");
echo CJSCore::GetHTML(["jquery2", "ui.dialogs.messagebox"]);
if (!empty($arResult['error'])): ?>
    <h2><?=$arResult['error']?></h2>
<?php else: ?>
    <link href="<?=$templateFolder?>/tabulator.min.css" rel="stylesheet" />
    <script type="text/javascript" src="<?=$templateFolder?>/moment-with-locales.min.js"></script>
    <script type="text/javascript" src="<?=$templateFolder?>/tabulator.js"></script>
    <script type="text/javascript" src="<?=$templateFolder?>/script.js?<?=filemtime(__DIR__.'/script.js')?>"></script>
    <style>
        #main-container {
            max-width: 750px;
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
        .loader-wrapper {
            display: flex;
            text-align: center;
            width: 100%;
            height: 100%;
            position: absolute;
            z-index: 3;
            background: rgb(161 161 161 / 68%);
        }
        .loader-wrapper.hide {
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
    <div class="loader-wrapper hide">
        <span class="loader"></span>
    </div>
    <h2>Склад метизов</h2>
    <div id="main-container">
        <div id="items-table" tabindex="0"></div>
    </div>

    <div id="sub-containers">
        <div class="sub-container">
            <h2>Отправить на комплектацию и сборку</h2>
            <div id="items-table-BUILD"></div>
            <div class="ui-btn-toolbar" style="width: 100%">
                <label>Логист</label>
                <select class="ui-ctl-inline ui-ctl-element" name="LOGISTIC_RESPONSIBLE" id="">
                    <?foreach ($arResult['USERS'] as $k => $user):?>
                        <option <?=$k == (int)$arResult['LOGISTIC_RESPONSIBLE'] ? "selected" : ''?> value="<?=$k?>"><?=$user?></option>
                    <?endforeach;?>
                </select>
            </div>
            <div id="history-container">
                <div>
                    <h3>Перемещения на этот склад</h3>
                    <?if($arResult['HISTORY_LOGISTIC']['TO']):?>
                    <?foreach ($arResult['HISTORY_LOGISTIC']['TO'] as $Translocation):?>
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
                    <?if($arResult['HISTORY_LOGISTIC']['FROM']):?>
                    <?foreach ($arResult['HISTORY_LOGISTIC']['FROM'] as $Translocation):?>
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

        <div class="sub-container">
            <h2>Отправить в производство</h2>
            <div id="items-table-PRODUCTION"></div>
            <div class="ui-btn-toolbar" style="width: 100%">
                <label>Ответственный</label>
                <select class="ui-ctl-inline ui-ctl-element" name="PRODUCTION_RESPONSIBLE" id="">
                    <?foreach ($arResult['USERS'] as $k => $user):?>
                        <option <?=$k == (int)$arResult['PRODUCTION_RESPONSIBLE'] ? "selected" : ''?> value="<?=$k?>"><?=$user?></option>
                    <?endforeach;?>
                </select>
            </div>
            <div id="history-container">
                <div>
                    <h3>Перемещения на этот склад</h3>
                    <?if($arResult['HISTORY_PRODUCTION']['FROM']):?>
                    <?foreach ($arResult['HISTORY_PRODUCTION']['FROM'] as $Translocation):?>
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
                    <?if($arResult['HISTORY_PRODUCTION']['TO']):?>
                    <?foreach ($arResult['HISTORY_PRODUCTION']['TO'] as $Translocation):?>
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
    </div>

    <script>

        (function() {
            let tab_id = '<?=$arParams['TAB_ID'];?>';
            $(`div.main-buttons-item[data-id=${tab_id}]`).on('click', Reload)

            console.log($(`div.main-buttons-item[data-id=${tab_id}]`))
            function Reload() {

                BX.ajax({
                    url: '<?=$component->getPath()?>/ajax.php?action=reload',
                    method: "POST",
                    data: {
                        sessid: BX.message('bitrix_sessid'),
                        entTypeID: <?=$arParams['ENTITY_TYPE_ID'];?>,
                        entityID: <?=$arParams['ENTITY_ID'];?>,
                        mode: 'distribution',
                        tab_id: tab_id,
                        action: 'reload',
                    }, // ключи объекта data соответствуют параметрам метода
                    dataType: "html",
                    onsuccess: function(data) {
                        console.log('reloaded')
                        $(`div.main-buttons-item[data-id=${tab_id}]`).off('click')
                        $(`div[data-tab-id='${tab_id}']`).html(data);
                        $(".loader-wrapper").addClass("hide");
                    }
                });


                //BX.ajax.runComponentAction('b-integration:rusir.warehouse.translocation',
                //    'reload', { // Вызывается без постфикса Action
                //        mode: 'class',
                //        data: {
                //            sessid: BX.message('bitrix_sessid'),
                //            entTypeID: <?php //=$arParams['ENTITY_TYPE_ID'];?>//,
                //            entityID: <?php //=$arParams['ENTITY_ID'];?>//,
                //            mode: 'distribution',
                //            tab_id: tab_id
                //        }, // ключи объекта data соответствуют параметрам метода
                //    })
                //    .then(function(response) {
                //        console.log(response)
                //        if (response.status === 'success') {
                //            console.log('reloaded')
                //            $(`div[data-tab-id='${tab_id}']`).html(response.data);
                //            $("#loader").addClass("hide");
                //
                //        }
                //    });
            }

            let mainTable = new BI.RusirTableController(
                "#items-table",
                '<?=$arResult['tableId']?>',
                <?=$arParams['ENTITY_TYPE_ID']?>,
                <?=$arParams['ENTITY_ID']?>,
                <?=CUtil::PhpToJSObject($arResult['main_columns']);?>,
                <?=CUtil::PhpToJSObject($arResult['main_rows']);?>,
                {
                    noAddDelete: true,
                    noSaveButton: false,
                }
            );

            let subTableLogistic = new BI.RusirTableController(
                "#items-table-BUILD",
                '<?=$arResult['tableId']?>',
                <?=$arParams['ENTITY_TYPE_ID']?>,
                <?=$arParams['ENTITY_ID']?>,
                <?=CUtil::PhpToJSObject($arResult['logistic_columns']);?>,
                <?=CUtil::PhpToJSObject($arResult['logistic_rows']);?>,
                {
                    noAddDelete: true,
                    noSaveButton: true,
                }
            );

            subTableLogistic.callbacks.beforeCommit = function(data) {
                $(".loader-wrapper").removeClass("hide");
            }

            subTableLogistic.callbacks.afterCommit = function(data) {
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
                Reload();
            }

            let subTableProduction = new BI.RusirTableController(
                "#items-table-PRODUCTION",
                "<?=$arResult['tableId']?>",
                <?=$arParams['ENTITY_TYPE_ID']?>,
                <?=$arParams['ENTITY_ID']?>,
                <?=CUtil::PhpToJSObject($arResult['production_columns']);?>,
                <?=CUtil::PhpToJSObject($arResult['production_rows']);?>,
                {
                    noAddDelete: true,
                    noSaveButton: true,
                }
            );
            subTableProduction.callbacks.beforeCommit = function(data) {
                $(".loader-wrapper").removeClass("hide");
            }
            subTableProduction.callbacks.afterCommit = function(data) {
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
                Reload();
            }
        })();
    </script>
    <span id="debug-span">
    </span>
<?php endif;
