<?php
define('STOP_STATISTICS', true);
define('BX_SECURITY_SHOW_MESSAGE', true);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC','Y');
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$APPLICATION->RestartBuffer();
header('Content-Type: application/x-javascript; charset='.LANG_CHARSET);

if (!$USER->IsAuthorized() || !check_bitrix_sessid() || !$request->isPost())
{
    return;
}

if (!\Bitrix\Main\Loader::IncludeModule('crm') || !$request->getQuery('action'))
{
    return;
}
if($request->getPost('action')=='reload') {
    header('Content-Type: text/html; charset='.LANG_CHARSET);

    $res = $APPLICATION->IncludeComponent('b-integration:rusir.warehouse.translocation', '', [
        'IS_AJAX' => 'Y',
        'ENTITY_ID' => $request->getPost('entityID'),
        'ENTITY_TYPE_ID' => $request->getPost('entTypeID'),
        'MODE' => $request->getPost('mode'),
        'TAB_ID' => $request->getPost('tab_id'),
        'SOURCE_WAREHOUSE' => $request->getPost('wh')['src'],
        'DESTINATION_WAREHOUSE' => $request->getPost('wh')['dst'],
    ]);

}

