<?php
/** @var CScsgDealTabComponent $component
 *  @var array $arResult
 * @var array $arParams
 */
\Bitrix\Main\UI\Extension::register("ui.dialogs.messagebox");
echo CJSCore::GetHTML(["jquery2", "ui.dialogs.messagebox"]);
if (!empty($arResult['error'])): ?>
    <h2><?=$arResult['error']?></h2>
<?php else: ?>
    <link href="<?=$component->getPath()?>/templates/.default/tabulator.min.css" rel="stylesheet" />
    <script type="text/javascript" src="<?=$component->getPath()?>/templates/.default/moment-with-locales.min.js"></script>
    <script type="text/javascript" src="<?=$component->getPath()?>/templates/.default/tabulator.js"></script>
    <script type="text/javascript" src="<?=$component->getPath()?>/templates/.default/script.js?<?=filemtime(__DIR__.'/script.js')?>"></script>
    <style>
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
        .mli-search-results {
            background-color: white;
            border: solid 1px #333;
            margin: 0;
            padding: 2px;
            font-size: 12px;
            z-index: 1
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
    </style>

    <h3>Позиции</h3>
    <div id="procurements-table" tabindex="0"></div>

    <span id="debug-span"></span>
    <script>
        (function() {
            function parseFloatEx(v) {
                return parseFloat(String(v).replace(',', '.'));
            }

            let itemsTable = new BI.RusirTableController(
                "#procurements-table",
                1,
                '<?=$arParams['ENTITY_TYPE_ID']?>',
                <?= intval($arParams['ENTITY_ID']) ?>,
                <?= json_encode($arResult['columns']) ?>,
                <?= json_encode($arResult['rows']); ?>,
                {readOnly: true}
            );

        })();
    </script>
<?php endif;
