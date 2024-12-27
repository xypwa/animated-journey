<?php
/** @var CScsgDealTabComponent $component
 *  @var string $templateFolder
 *  @var array $arResult
 *  @var array $arParams
 */
\Bitrix\Main\UI\Extension::register("ui.dialogs.messagebox");
echo CJSCore::GetHTML(["jquery2", "ui.dialogs.messagebox", "ui.notification", 'ui.entity-editor']);
if (!empty($arResult['error'])): ?>
    <h2><?=$arResult['error']?></h2>
<?php else: ?>
    <link href="<?=$templateFolder?>/tabulator.min.css" rel="stylesheet" />
    <script type="text/javascript" src="<?=$templateFolder?>/moment-with-locales.min.js"></script>
    <script type="text/javascript" src="<?=$templateFolder?>/tabulator.js"></script>
    <script type="text/javascript" src="<?=$templateFolder?>/script_Common.js?<?=filemtime(__DIR__.'/script_Common.js')?>"></script>
    <script type="text/javascript" src="/bitrix/components/bitrix/main.file.input/templates/.default/script.js"></script>

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

        .flex-form span {
            word-wrap: normal;
            flex: 0 1 100px;
            margin: 3px;
        }
        .flex-form span label {
            display: block;
            text-align: center;
        }
        .bi-cell-image-wrapper > a > img{
            height: 60px;
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
<!--    <h2>--><?php //=$arResult['tableTitle']?><!--</h2>-->
    <h2>Комплектация и сборка</h2>
    <div id="main-container">
        <div id="items-table-<?=$arResult['SRC_WH'].'_'.$arResult['DST_WH']?>" tabindex="0"></div>

        <div class="ui-btn-toolbar" style="width: 100%">
            <label>Ответственный сотрудник</label>
            <select class="ui-ctl-inline ui-ctl-element" name="RESPONSIBLE" id="">
                <?foreach ($arResult['USERS'] as $k => $user):?>
                    <option <?=$k == (int)$arResult['RESPONSIBLE'] ? "selected" : ''?> value="<?=$k?>"><?=$user?></option>
                <?endforeach;?>
            </select>
        </div>
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
            let tab_id = '<?=$arParams['TAB_ID'];?>';
            let srcWH = "<?=$arResult['SRC_WH']?>";
            let dstWH = "<?=$arResult['DST_WH']?>";

            $(`div.main-buttons-item[data-id=${tab_id}]`).on('click', Reload)

            $("#items-table-BUILD_LOGISTIC-apply-btn").text('Передать');
            function parseFloatEx(v) {
                return parseFloat(String(v).replace(',', '.'));
            }

            const messageAttachInput = document.getElementById('attach');
            if(messageAttachInput) {
                messageAttachInput.addEventListener('change', function() {
                    const files = $(this)[0].files;
                    let labelElement = $(this).next("div");
                    let newLabel = "";
                    if(files.length) {
                        let fileNames = [];
                        $.each(files, function(i, file) {
                            fileNames.push(file.name)
                        })
                        newLabel = fileNames.join(", ");

                    }
                    labelElement.text(newLabel)
                })
            }



            function Reload() {

                // $("#loader").removeClass("hide");

                BX.ajax({
                    url: '<?=$component->getPath()?>/ajax.php?action=reload',
                    method: "POST",
                    data: {
                        sessid: BX.message('bitrix_sessid'),
                        entTypeID: <?=$arParams['ENTITY_TYPE_ID'];?>,
                        entityID: <?=$arParams['ENTITY_ID'];?>,
                        mode: 'translocationWithPackages',
                        tab_id: tab_id,
                        wh: {src: srcWH, dst: dstWH},
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
                //            mode: 'translocationWithPackages',
                //            tab_id: tab_id,
                //            wh: {src: srcWH, dst: dstWH}
                //        }, // ключи объекта data соответствуют параметрам метода
                //    })
                //    .then(function(response) {
                //        console.log(response)
                //        if (response.status === 'success') {
                //            console.log('reloaded')
                //            $(`div[data-tab-id='${tab_id}']`).html(response.data);
                //            $(".loader-wrapper").addClass("hide");
                //
                //        }
                //    });
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
                    dstWH,
                    packageTable: 1
                }
            );

            mainTable.callbacks.beforeCommit = function(data) {
                $(".loader-wrapper").removeClass("hide");
            }
            mainTable.callbacks.afterCommit = function(data) {
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
                    BX.SidePanel.Instance.open(data.translocation_link)
                }
                if(data.package_link) {
                    BX.SidePanel.Instance.open(data.package_link)
                }
                Reload();
                console.log(data)
            }

            // BX.SidePanel.Instance.bindAnchors({
            //     rules:
            //         [
            //             {
            //                 condition: [
            //                     "https://crm.rusir38.ru/~[0-9a-z]{5}",
            //                 ],
            //                 loader: 'default-loader',
            //                 options: {
            //                     width: 1700
            //                 }
            //             },
            //         ]
            // });
        })();
    </script>
<?php endif;
