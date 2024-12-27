<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
/** @var CMain $APPLICATION */
$APPLICATION->SetTitle("");

$APPLICATION->IncludeComponent('b-integration:rusir.warehouse.translocation', '', [
    'ENTITY_ID' => $_POST['PARAMS']['PLACEMENT_OPTIONS']['ID'],
    'ENTITY_TYPE_ID' => $_POST['PARAMS']['PLACEMENT'],
    'MODE' => $_POST['PARAMS']['MODE'],
    'TAB_ID' => $_POST['PARAMS']['TAB_ID'],
    'SOURCE_WAREHOUSE' => $_POST['PARAMS']['SOURCE_WAREHOUSE'],
    'DESTINATION_WAREHOUSE' => $_POST['PARAMS']['DESTINATION_WAREHOUSE'],
]);

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');

