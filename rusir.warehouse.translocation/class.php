<?php

use Bitrix\Crm\CompanyTable;
use Bitrix\Crm\Field;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\RelationIdentifier;
use Bitrix\Crm\Service\Container;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Crm\Service;
use Bitrix\Main\UserTable;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;

class CRusirWarehouseTranslocationTabComponent extends CBitrixComponent implements Controllerable{

    const WAREHOUSE_IBLOCK = 23;
    const WAREHOUSE_RESTS_IBLOCK = 24;
    const ORDER_SP_TYPE = 140;
    const TRANS_SP_TYPE = 180;
    const ISSUE_SP_TYPE = 141;
    const POSITION_IBLOCK = 15;
    const POSITION_PROPOSAL_IBLOCK = 20;

    public function __construct($component = null)
    {
        parent::__construct($component);
    }

    /**
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     * @throws LoaderException
     */
    private function beforeExecute(): void
    {
        Loader::includeModule('lists');
        Loader::includeModule('crm');
//        $this->prepareCurrencies();
    }
    /**
     * @return mixed|void|null
     * @throws LoaderException
     * @throws SystemException
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public function executeComponent()
    {

        $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $page = '';
        $this->beforeExecute();
        if (empty($this->arParams['ENTITY_ID']) || empty($this->arParams['ENTITY_TYPE_ID'])) {
            $this->arResult['error'] = "Нет связи с сущностью";
        } else {
            $entityId = new ItemIdentifier($this->arParams['ENTITY_TYPE_ID'], $this->arParams['ENTITY_ID']);

            if($this->arParams['MODE'] === 'translocation') {
                $this->prepareResultTranslocation($this->arParams['SOURCE_WAREHOUSE'], $this->arParams['DESTINATION_WAREHOUSE']);
//                if($_SERVER['REMOTE_ADDR']=='89.208.107.105') {
//
//                } else
                    $page = 'translocation';
            } else if ($this->arParams['MODE'] === 'distribution') {
                $this->prepareResultDistribution();
                $page = 'distribution';
            } else if($this->arParams['MODE'] === 'translocationWithPackages') {
                $this->prepareResultTranslocationPcs($this->arParams['SOURCE_WAREHOUSE'], $this->arParams['DESTINATION_WAREHOUSE']);
                $page = 'translocationPcs';
            }
            else if($this->arParams['MODE'] === 'placement') {
                $this->prepareResultPlacement();
                $page = 'placement';
            }
        }
        $this->includeComponentTemplate($page);


    }

    private function prepareResultPlacement() {
        $PlacementFactory = Service\Container::getInstance()->getFactory(152);
        $PlacementItem = $PlacementFactory->getItem($this->arParams['ENTITY_ID']);

//        echo "<pre>";
//        var_dump($PlacementItem->toArray());
//        die;
        $TranslocationFactory = Service\Container::getInstance()->getFactory(180);
        $ParentTranslocationItem = $TranslocationFactory->getItem($PlacementItem->get('PARENT_ID_180'));

        $OrderFactory = Service\Container::getInstance()->getFactory(140);
        $OrderItems = $OrderFactory->getItems(['filter' => [
            'PARENT_ID_180' => $ParentTranslocationItem->getId(),
        ]]);


        $rows = [];
        $columns = [
            [
                'title' => 'Позиция',
                'field' => 'PRODUCT_NAME',
                'editor' => false,
            ],
            [
                'title' => 'Кол-во',
                'field' => 'QUANTITY',
                'editor' => false,
            ],
            [
                'title' => 'Ед. измерения',
                'field' => 'MEASURE',
                'editor' => false,
            ],
            [
                'title' => 'Вес за ед.',
                'field' => 'WEIGHT_PER_UNIT',
                'editor' => false,
            ],
            [
                'title' => 'Вес общ.',
                'field' => 'WEIGHT_TOTAL',
                'editor' => false,
                'bottomCalc' => 'sum',
                'bottomCalcParams' => ['precision' => 2],
            ],
        ];
        foreach ($OrderItems as $row) {
            $Position = CIBlockElement::GetByID($row->get('UF_CRM_12_POSITION'))->GetNextElement();

            $Quantity = $row->get('UF_CRM_12_QUANTITY');
            $Unit = $Position->GetProperties()['EDINITSA_IZMERENIYA1']['VALUE'];
            $WeightPerUnit = $Position->GetProperties()['WEIGHT']['VALUE'];
            $rows[] = [
                "PRODUCT_NAME" => $Position->fields['NAME'],
                'QUANTITY' => $Quantity,
                'MEASURE' => $Unit,
                'WEIGHT_PER_UNIT' => (float) $WeightPerUnit,
                'WEIGHT_TOTAL' => (float) $WeightPerUnit * $Quantity
            ];
        }
        $this->arResult['columns'] = $columns;
        $this->arResult['rows'] = $rows;
    }
    private function prepareResultTranslocation($src, $destination) {

        $list = new CList(15);
        $listFields = $list->getFields();
        $displayFields = ['ID', 'NAME'];
        $rows = [];
        $columns = $this->getColumns($listFields, $displayFields);
        foreach ($columns as &$col) {
            if($col['field']=='NAME')
                $col['title'] = 'Позиция';
            $col['editor'] = false;
        }


        $srcWH = ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => $src]]);
        $dstWH = ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => $destination]]);

        foreach ($ar = [$srcWH, $dstWH] as $warehouse) {
            $columns[] = [
                'title' => $warehouse['NAME'],
                'field' => $warehouse['CODE'],
                'CODE' => $warehouse['CODE'],
                'editor' => false,
            ];
            if(next($ar)) {
                $columns[] = [
                    'title' => "Переместить",
                    'field' => "QUANTITY",
                    'CODE' => "QUANTITY",
                    'editor' => "number",
                    'editorParams' => ['min'=>'1','step'=>'1']
                ];
            }
        }


        $res = CIBlockElement::GetList(['SORT' => 'DESC'], [
            'IBLOCK_ID' => 15,
            'PROPERTY_ISSUE' => $this->getPrefixedEntityId(
                $this->arParams['ENTITY_TYPE_ID'], $this->arParams['ENTITY_ID']
            ),
        ]);
        while($row = $res->GetNextElement(false)) {
            $fields = $row->GetFields();
            foreach ($displayFields as $fld)
                if (!empty($fields["~$fld"]))
                    $fields[$fld] = $fields["~$fld"];

            $props = [];
            foreach ($row->GetProperties() as $prop) {
                $this->processPropValue($prop, $listFields, $props);
            }

            $row = array_intersect_key($fields + $props, $listFields);
            $row['ID'] = $fields['ID'];
            $row['QUANTITY'] = $this->getProductQuantityOnWarehouseFromUnCompletedOrders($row['ID'], $srcWH['ID']);

            $srcWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $srcWH['ID']
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
            $row[$srcWH['CODE']] = $srcWHRests ? (int) $srcWHRests['PROPERTY_QUANTITY_VALUE'] : 0;
            $dstWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $dstWH['ID']
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
            $row[$dstWH['CODE']] = $dstWHRests ? (int) $dstWHRests['PROPERTY_QUANTITY_VALUE'] : 0;


            $rows[] = $row;
        }

        $deps = $this->getDepartments(45);

//        $this->arResult['HISTORY']['FROM'] = $this->getWarehouseTranslocationHistory($srcWH['ID'], '', array_column($rows, 'ID'));
//        $this->arResult['HISTORY']['TO'] = $this->getWarehouseTranslocationHistory('', $srcWH['ID'], array_column($rows, 'ID'));
        $this->arResult['HISTORY'] = $this->getWarehouseTranslocationHistory($srcWH['ID'], $this->arParams['ENTITY_ID']);
        $this->arResult['RESPONSIBLE'] = $this->arResult['HISTORY']['FROM'] ? reset($this->arResult['HISTORY']['FROM'])['UID'] : 1;
//        var_dump($deps); die;
        $this->arResult['USERS'] = $this->getUsersMap();
        $this->arResult['columns'] = $columns;
        $this->arResult['rows'] = $rows;
        $this->arResult['SRC_WH'] = $src;
        $this->arResult['DST_WH'] = $destination;
        $this->arResult['tableId'] = 'CRM_'.$this->arParams['ENTITY_TYPE_ID'];
        $this->arResult['tableTitle'] = $srcWH['NAME'];
//        echo "<pre>";
//        var_dump($this->arResult);
//        echo "</pre>";


    }
    private function prepareResultTranslocationPcs($src, $destination) {

        $list = new CList(15);
        $listFields = $list->getFields();
        $displayFields = ['ID', 'NAME'];
        $rows = [];
        $columns = $this->getColumns($listFields, $displayFields);
        foreach ($columns as &$col) {
            if($col['field']=='NAME')
                $col['title'] = 'Позиция';
            $col['editor'] = false;
        }


        $srcWH = ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => $src]]);
        $dstWH = ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => $destination]]);

        foreach ($ar = [$srcWH, $dstWH] as $warehouse) {
            $columns[] = [
                'title' => $warehouse['NAME'],
                'field' => $warehouse['CODE'],
                'CODE' => $warehouse['CODE'],
                'editor' => false,
            ];
            if(next($ar)) {
                $columns[] = [
                    'title' => "Переместить",
                    'field' => "QUANTITY",
                    'CODE' => "QUANTITY",
                    'editor' => "number",
                    'editorParams' => ['min'=>'1','step'=>'1']
                ];
            }
        }


        $res = CIBlockElement::GetList(['SORT' => 'DESC'], [
            'IBLOCK_ID' => 15,
            'PROPERTY_ISSUE' => $this->getPrefixedEntityId(
                $this->arParams['ENTITY_TYPE_ID'], $this->arParams['ENTITY_ID']
            ),
        ]);
        while($row = $res->GetNextElement(false)) {
            $fields = $row->GetFields();
            foreach ($displayFields as $fld)
                if (!empty($fields["~$fld"]))
                    $fields[$fld] = $fields["~$fld"];

            $props = [];
            foreach ($row->GetProperties() as $prop) {
                $this->processPropValue($prop, $listFields, $props);
            }

            $row = array_intersect_key($fields + $props, $listFields);
            $row['ID'] = $fields['ID'];
            $row['QUANTITY'] = $this->getProductQuantityOnWarehouseFromUnCompletedOrders($row['ID'], $srcWH['ID']);

            $srcWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $srcWH['ID']
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
            $row[$srcWH['CODE']] = $srcWHRests ? (int) $srcWHRests['PROPERTY_QUANTITY_VALUE'] : 0;
            $dstWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $dstWH['ID']
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
            $row[$dstWH['CODE']] = $dstWHRests ? (int) $dstWHRests['PROPERTY_QUANTITY_VALUE'] : 0;


            $rows[] = $row;
        }

        $this->arResult['HISTORY'] = $this->getWarehouseTranslocationHistory($srcWH['ID'], $this->arParams['ENTITY_ID']);
        $this->arResult['RESPONSIBLE'] = $this->arResult['HISTORY']['FROM'] ? reset($this->arResult['HISTORY']['FROM'])['UID'] : 1;
        $this->arResult['USERS'] = $this->getUsersMap();
        $this->arResult['columns'] = $columns;
        $this->arResult['rows'] = $rows;
        $this->arResult['SRC_WH'] = $src;
        $this->arResult['DST_WH'] = $destination;
        $this->arResult['tableId'] = 'CRM_'.$this->arParams['ENTITY_TYPE_ID'];
        $this->arResult['tableTitle'] = $srcWH['NAME'];
//        echo "<pre>";
//        var_dump($this->arResult);
//        echo "</pre>";


    }
    private function prepareResultDistribution() {
        $OrderFactory = Service\Container::getInstance()->getFactory(140);
        $TranslocationFactory = Service\Container::getInstance()->getFactory(141);
        $rows = $lrows = $prows = [];
        $lResponsible = $rResponsible = 0;
        $list = new CList(15);
        $listFields = $list->getFields();
        $displayFields = ['ID', 'NAME', 'QUANTITY'];
        $columns = $this->getColumns($listFields, $displayFields);

        if($columns) {
            $quantityCol = false;
            foreach ($columns as &$col) {
                $col['editor'] = false;

                if($col['field']=='NAME') {

                    $col['title'] = 'Позиция';
                    $lcolumns[] = $pcolumns[] = $col;
                }
                if($col['CODE']=='QUANTITY') {
                    $col['title'] = 'На складе Метизов';
                    $quantityCol = $col;
                }
            }

            $quantityCol['title'] = 'Отправка клиенту';
            $quantityCol['field'] = 'LOGISTIC';
            $quantityCol['CODE'] = 'LOGISTIC';
            $columns[] = $quantityCol;
            $quantityCol['title'] = 'В производстве';
            $quantityCol['field'] = 'PRODUCTION';
            $quantityCol['CODE'] = 'PRODUCTION';
            $columns[] = $quantityCol;

            $quantityCol['editor'] = 'number';
            $quantityCol['field'] = 'QUANTITY';
            $quantityCol['CODE'] = 'QUANTITY';
            $quantityCol['title'] = 'Переместить';
            $lcolumns[] = $quantityCol;
            $pcolumns[] = $quantityCol;

        }

        $AWarehouseID = \Bitrix\Iblock\ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => 'ACCEPT']])['ID'];
        $LWarehouseID = \Bitrix\Iblock\ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => 'BUILD']])['ID'];
        $PWarehouseID = \Bitrix\Iblock\ElementTable::getRow(['filter' => ['IBLOCK_ID' => 23, 'CODE' => 'PRODUCTION']])['ID'];
        $res = CIBlockElement::GetList(['SORT' => 'DESC'], [
            'IBLOCK_ID' => 15,
            'PROPERTY_ISSUE' => $this->getPrefixedEntityId(
                $this->arParams['ENTITY_TYPE_ID'], $this->arParams['ENTITY_ID']
            ),
        ]);
        while($row = $res->GetNextElement(false)) {
            $lrow = $prow = [];
            $fields = $row->GetFields();
            foreach ($displayFields as $fld)
                if (!empty($fields["~$fld"]))
                    $fields[$fld] = $fields["~$fld"];

            $props = [];
            foreach ($row->GetProperties() as $prop) {
                $this->processPropValue($prop, $listFields, $props);
            }
            $row = array_intersect_key($fields + $props, $listFields);
            $row['ID'] = $lrow['ID'] = $prow['ID'] = $fields['ID'];
            $lrow['NAME'] = $prow['NAME'] = $row['NAME'];

            $AWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $AWarehouseID
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
            $row['PROPERTY_60'] = $AWHRests['PROPERTY_QUANTITY_VALUE'];
            $row['PRODUCTION'] = $row['LOGISTIC'] = 0;
            $LWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $LWarehouseID
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
//        var_dump($LWHRests);

            $row['LOGISTIC_ORDER'] = $this->getProductQuantityOnWarehouseFromCompletedOrders($row['ID'], $LWarehouseID);
            $lrow['QUANTITY'] = $this->getProductQuantityOnWarehouseFromUnCompletedOrders($row['ID'], $LWarehouseID);
            if($LWHRests) {
                $row['LOGISTIC'] = (int) $LWHRests['PROPERTY_QUANTITY_VALUE'];
                $lastOrderL = $OrderFactory->getItems([
                    'filter' => [
                        'UF_CRM_12_POSITION' => $row['ID'],
                        'UF_CRM_12_DST_STORAGE' => $LWarehouseID
                    ],
                    'select' => ['ASSIGNED_BY_ID'],
                    'order' => ['ID' => 'DESC'],
                    'limit' => 1
                ]);
                if(!$lResponsible && $lastOrderL) {
                    $lResponsible = end($lastOrderL)->getAssignedById();
                }
            }
            $RWHRests = CIBlockElement::GetList([], [
                'IBLOCK_ID' => 24,
                'PROPERTY_POSITION' => $row['ID'],
                'PROPERTY_WAREHOUSE' => $PWarehouseID
            ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
            $row['PRODUCTION_ORDER'] = $this->getProductQuantityOnWarehouseFromCompletedOrders($row['ID'], $PWarehouseID);
            $prow['QUANTITY'] = $this->getProductQuantityOnWarehouseFromUnCompletedOrders($row['ID'], $PWarehouseID);
            if($RWHRests) {
                $row['PRODUCTION'] = (int) $RWHRests['PROPERTY_QUANTITY_VALUE'];
                $lastOrderR = $OrderFactory->getItems([
                    'filter' => [
                        'UF_CRM_12_POSITION' => $row['ID'],
                        'UF_CRM_12_DST_STORAGE' => $PWarehouseID
                    ],
                    'select' => ['ASSIGNED_BY_ID'],
                    'order' => ['ID' => 'DESC'],
                    'limit' => 1
                ]);
                if(!$rResponsible && $lastOrderR) {
                    $rResponsible = end($lastOrderR)->getAssignedById();
                }
            }

            $rows[] = $row;
            $lrows[] = $lrow;
            $prows[] = $prow;
        }
//        $this->arResult['HISTORY_LOGISTIC']['FROM'] = $this->getWarehouseTranslocationHistory($LWarehouseID, '', array_column($lrows, 'ID'));
//        $this->arResult['HISTORY_LOGISTIC']['TO'] = $this->getWarehouseTranslocationHistory('', $LWarehouseID, array_column($lrows, 'ID'));
        $this->arResult['HISTORY_LOGISTIC'] = $this->getWarehouseTranslocationHistory($LWarehouseID, $this->arParams['ENTITY_ID']);
//        var_dump([$LWarehouseID, $this->arResult['HISTORY_LOGISTIC']]);
//        $this->arResult['HISTORY_PRODUCTION']['FROM'] = $this->getWarehouseTranslocationHistory($PWarehouseID, '', array_column($prows, 'ID'));
//        $this->arResult['HISTORY_PRODUCTION']['TO'] = $this->getWarehouseTranslocationHistory('', $PWarehouseID, array_column($prows, 'ID'));
        $this->arResult['HISTORY_PRODUCTION'] = $this->getWarehouseTranslocationHistory($PWarehouseID, $this->arParams['ENTITY_ID']);
//        var_dump($this->arResult['HISTORY_LOGISTIC']);
        $this->arResult['USERS'] = $this->getUsersMap();
        $this->arResult['PRODUCTION_RESPONSIBLE'] = $rResponsible;
        $this->arResult['LOGISTIC_RESPONSIBLE'] = $lResponsible;
        $this->arResult['main_columns'] = $columns;
        $this->arResult['main_rows'] = $rows;
        $this->arResult['logistic_columns'] = $lcolumns;
        $this->arResult['logistic_rows'] = $lrows;
        $this->arResult['production_columns'] = $pcolumns;
        $this->arResult['production_rows'] = $prows;
        $this->arResult['tableId'] = 'CRM_'.$this->arParams['ENTITY_TYPE_ID'];
    }

    public function configureActions()
    {
        define('LOG_FILENAME', __DIR__.'log.log');

        // Сбрасываем фильтры по-умолчанию (ActionFilter\Authentication и ActionFilter\HttpMethod)
        // Предустановленные фильтры находятся в папке /bitrix/modules/main/lib/engine/actionfilter/
        return [
            'initialTranslocationAction' => [ // Ajax-метод
                'prefilters' => [
                    new ActionFilter\Authentication,
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ])
                ],
            ],
            'saveBeforeDistributeAction' => [ // Ajax-метод
                'prefilters' => [
                    new ActionFilter\Authentication,
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ])
                ],
            ],
            'commitDistributeAction' => [ // Ajax-метод
                'prefilters' => [
                    new ActionFilter\Authentication,
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ])
                ],
            ],
            'reloadAction' => [
                'prefilters' => [
                    new ActionFilter\Authentication,
                    new ActionFilter\HttpMethod([
                        ActionFilter\HttpMethod::METHOD_POST
                    ])
                ],
            ]
        ];
    }

    private static function createTranslocationDocument(\Bitrix\Crm\Item $Translocation, $dstWareHouseID) {

        $Warehouse = CIBlockElement::GetByID($dstWareHouseID)->Fetch();
        $result = false;
        require_once $_SERVER['DOCUMENT_ROOT']."/local/components/b-integration/rusir.document.generator/DocumentGenerator.php";
        $II = new ItemIdentifier($Translocation->getEntityTypeId(), $Translocation->getId());
        try {
            $Generator = new DocumentGenerator($II, $Warehouse['CODE']);
            $result = $Generator->generate();
        } catch (\Bitrix\Main\SystemException $e) {

        }
        return $result;
    }
    public function initialTranslocationAction($elements, $entityID)
    {
        $IE = new ItemIdentifier(self::ISSUE_SP_TYPE, $entityID);
        $OrderFactory = Service\Container::getInstance()->getFactory(self::ORDER_SP_TYPE);
        $TranslocationFactory = Service\Container::getInstance()->getFactory(self::TRANS_SP_TYPE);

        $errors = [];
        $resultArr = [];
        $createdOrders = [];

        list($from, $to) = self::getSrcDstWarehousesByCodes('', 'ACCEPT');
        $Translocation = $TranslocationFactory->createItem(['UF_CRM_13_SRC_STORAGE' => $from, 'UF_CRM_13_DST_STORAGE' => $to, 'PARENT_ID_141' => $IE->getEntityId()]);
        foreach ($elements as $element) {
            $quantity = (float) $element['ACCEPT'];
            if(!$quantity)
                continue;

            $PositionOrderQuantity = self::getProductQuantityInProposal($element['ID']);
            if(!$PositionOrderQuantity)
                return ['data' => [], 'errors' => [
                    "Позиция {$element['NAME']} не найдена или её кол-во в заказе = 0"
                ]];
            $Translocation->addToProductRows(\Bitrix\Crm\ProductRow::createFromArray([
                'PRODUCT_ID' => 0,
                'QUANTITY' => $quantity,
                'PRODUCT_NAME' => $element['NAME']. " [{$element['ID']}]",
//                'XML_ID' => $element['ID']
            ]));
            $alreadyAccepted = 0;
            $staged = 0;
            $AcceptOrders = $OrderFactory->getItems(['filter' => [
                'UF_CRM_12_POSITION' => $element['ID'], 'UF_CRM_12_DST_STORAGE' => $to, 'STAGE_ID' => 'DT140_33:SUCCESS']
            ]);
            foreach ($AcceptOrders as $order)
                $alreadyAccepted += (int) $order->get('UF_CRM_12_QUANTITY');

            $StagedOrders = $OrderFactory->getItems(['filter' => [
                'UF_CRM_12_POSITION' => $element['ID'], 'UF_CRM_12_DST_STORAGE' => $to, 'STAGE_ID' => 'DT140_33:NEW']
            ]);
            foreach ($StagedOrders as $order)
                $staged += (int) $order->get('UF_CRM_12_QUANTITY');

            if($quantity + $alreadyAccepted + $staged > $PositionOrderQuantity) {
                $err = sprintf("Внимание, указанное кол-во (%d) + уже попавшее на склад Приемки (%d) + ожидающее подтверждения приемки (%d) позиции %s <b>больше, чем в запросе </b> (%d)", $quantity, $alreadyAccepted, $staged, $element['NAME'], $PositionOrderQuantity);
                $errors[] = $err;
                return ['data' => [], 'errors' => $errors];
            }

        }
        $AddAction = $TranslocationFactory->getAddOperation($Translocation);
        $CreateTranslocationResult = $AddAction->launch();
        if($CreateTranslocationResult->isSuccess()) {
            $ResultData = $CreateTranslocationResult->getData();

            $createdOrders = $ResultData['UF_CRM_13_ISSUES'];
            foreach ($createdOrders as $order) {
                $item = $OrderFactory->getItem($order);
                $item->set('PARENT_ID_180', $Translocation->getId());
                $item->save();
            }
//            var_dump($createdOrders); die;
            if($createdOrders) {
                $complexDocumentID = implode("_", ["DYNAMIC", $IE->getEntityTypeId(), $IE->getEntityId()]);
                $arErrorsTmp = [];
                $wfId = CBPDocument::StartWorkflow(
                    496,
                    ['crm', 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Dynamic', $complexDocumentID],
                    array('ORDERS' => $createdOrders, 'TRANSLOCATION' => $Translocation->getId()),
                    $arErrorsTmp
                );
//                if($arErrorsTmp)
//                    var_dump($arErrorsTmp);
//                die;
            }
        } else {
            $errors = array_merge($errors, $CreateTranslocationResult->getErrorMessages());
        }
        AddMessage2Log(print_r($createdOrders, true));

        return ['data' => $resultArr, 'errors' => $errors];
    }

    public function saveBeforeDistributeAction($entityID, $elements, $src, $destination, $assigned = 0, $packageData = []) {
        $errors = [];
        $resultArr = [];
        list($from, $to) = self::getSrcDstWarehousesByCodes($src, $destination);
        if(!$from && !$to) {
            $errors[] = "Не найден ID складов по кодам {$src}, {$destination}";
            return ['data' => [], 'errors' => $errors];
        }

        if(!$assigned)
            $assigned = $GLOBALS['USER']->GetId();


        $OrderFactory = Service\Container::getInstance()->getFactory(self::ORDER_SP_TYPE);
        $TranslocationFactory = Service\Container::getInstance()->getFactory(self::TRANS_SP_TYPE);
        $notFinishedTranslocations = self::findNotFinishedTranslocations($entityID, $from, $to);
//        var_dump(count($notFinishedTranslocations)); die;
        if($notFinishedTranslocations) {
            foreach ($notFinishedTranslocations as $translocation) {
                $res = $TranslocationFactory->getDeleteOperation($translocation)->launch();
            }
        }


        $Translocation = $TranslocationFactory->createItem(['ASSIGNED_BY_ID' => $assigned, 'UF_CRM_13_INCLUDE_PACKAGES' => (bool) $packageData, 'UF_CRM_13_SRC_STORAGE' => $from, 'UF_CRM_13_DST_STORAGE' => $to, 'PARENT_ID_141' => $entityID]);
        if($packageData) {
            $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
            foreach ($packageData as $field => $packageDatum) {
                if($Translocation->hasField('UF_CRM_13_PCG_'.$field))
                    $Translocation->set('UF_CRM_13_PCG_'. $field, $packageDatum);
            }
        }

        foreach ($elements as $element) {
            $quantity = (float) $element['QUANTITY'];
            if(!$quantity)
                continue;

            $Translocation->addToProductRows(\Bitrix\Crm\ProductRow::createFromArray([
                'PRODUCT_ID' => 0,
                'QUANTITY' => $quantity,
                'PRODUCT_NAME' => $element['NAME']. " [{$element['ID']}]",
            ]));

        }
        $AddAction = $TranslocationFactory->getAddOperation($Translocation);
        $CreateTranslocationResult = $AddAction->launch();
        if($CreateTranslocationResult->isSuccess()) {
            $ResultData = $CreateTranslocationResult->getData();
            $resultArr['translocation_link'] = "/crm/type/180/details/{$Translocation->getId()}/";
            $createdOrders = $ResultData['UF_CRM_13_ISSUES'];
            foreach ($createdOrders as $order) {
                $item = $OrderFactory->getItem($order);
                $item->set('PARENT_ID_180', $Translocation->getId());
                $item->setAssignedById($assigned);
                $item->save();
            }

            if($packageData) {
                $PackageFactory = Service\Container::getInstance()->getFactory(152);
                $Package = $PackageFactory->createItem([
                    'PARENT_ID_180' => $Translocation->getId(),
                    'PARENT_ID_141' => $Translocation->get('PARENT_ID_141'),
                    'TITLE' => '',
                    'ASSIGNED_BY_ID' => $assigned,
                    'UF_CRM_16_MEDIA' => $Translocation->get('UF_CRM_13_PCG_MEDIA'),
                    'UF_CRM_16_TRANSPORT_INFO' => $Translocation->get('UF_CRM_13_PCG_TRANSPORT_INFO'),
                    'UF_CRM_16_TRANSPORT_TYPE' => $Translocation->get('UF_CRM_13_PCG_TRANSPORT_TYPE'),
                    'UF_CRM_16_WEIGHT' => $Translocation->get('UF_CRM_13_PCG_WEIGHT'),
                ]);
                $comment = "Упаковке подлежат следующие позиции: \n";
                foreach ($Translocation->getProductRows()->toArray() as $row) {
                    $comment.= "{$row['PRODUCT_NAME']} - {$row['QUANTITY']} шт. \n";
                }
                $Package->set('UF_CRM_16_COMMENT', $comment);
                $PackageFactory->getAddOperation($Package)->launch();
                $resultArr['package_link'] = "/crm/type/152/details/{$Package->getId()}/";
            }

        } else {
            $errors = array_merge($errors, $CreateTranslocationResult->getErrorMessages());
        }
        return ['data' => $resultArr, 'errors' => $errors];
    }

    public function commitDistributeAction($entityID, $elements, $src, $destination, $assigned = 0, $packageData = []) {
        $errors = [];
        $resultArr = [];
        list($from, $to) = self::getSrcDstWarehousesByCodes($src, $destination);
        if(!$from && !$to) {
            $errors[] = "Не найден ID складов по кодам {$src}, {$destination}";
            return ['data' => [], 'errors' => $errors];
        }
        if(!$assigned)
            $assigned = $GLOBALS['USER']->GetId();

        $TranslocationFactory = Service\Container::getInstance()->getFactory(self::TRANS_SP_TYPE);
        $OrderFactory = Service\Container::getInstance()->getFactory(self::ORDER_SP_TYPE);
        if($notFinishedTranslocations = self::findNotFinishedTranslocations($entityID, $from, $to)) {
            foreach ($notFinishedTranslocations as $translocation) {
                $res = $TranslocationFactory->getDeleteOperation($translocation)->launch();
            }
        }

        $Translocation = $TranslocationFactory->createItem(['ASSIGNED_BY_ID' => $assigned, 'UF_CRM_13_INCLUDE_PACKAGES' => (bool) $packageData, 'UF_CRM_13_SRC_STORAGE' => $from, 'UF_CRM_13_DST_STORAGE' => $to, 'PARENT_ID_141' => $entityID]);
        if($packageData) {
            foreach ($packageData as $field => $packageDatum) {
                if($Translocation->hasField('UF_CRM_13_PCG_'.$field))
                    $Translocation->set('UF_CRM_13_PCG_'. $field, $packageDatum);
            }
        }
        $FinishedOrders = [];
        foreach ($elements as $element) {
            $quantity = (float) $element['QUANTITY'];
            if(!$quantity)
                continue;

            $Translocation->addToProductRows(\Bitrix\Crm\ProductRow::createFromArray([
                'PRODUCT_ID' => 0,
                'QUANTITY' => $quantity,
                'PRODUCT_NAME' => $element['NAME']. " [{$element['ID']}]",
            ]));
        }

        $AddAction = $TranslocationFactory->getAddOperation($Translocation);
        $CreateTranslocationResult = $AddAction->launch();
        if($CreateTranslocationResult->isSuccess()) {
            $ResultData = $CreateTranslocationResult->getData();
            $createdOrders = $ResultData['UF_CRM_13_ISSUES'];
            foreach ($createdOrders as $order) {
                $item = $OrderFactory->getItem($order);
                $item->set('PARENT_ID_180', $Translocation->getId());
                $item->setAssignedById($assigned);
                $item->save();
            }

            if($packageData) {
                $PackageFactory = Service\Container::getInstance()->getFactory(152);
                $Package = $PackageFactory->createItem([
                    'PARENT_ID_180' => $Translocation->getId(),
                    'PARENT_ID_141' => $Translocation->get('PARENT_ID_141'),
                    'TITLE' => '',
                    'ASSIGNED_BY_ID' => $assigned,
                    'UF_CRM_16_MEDIA' => $Translocation->get('UF_CRM_13_PCG_MEDIA'),
                    'UF_CRM_16_TRANSPORT_INFO' => $Translocation->get('UF_CRM_13_PCG_TRANSPORT_INFO'),
                    'UF_CRM_16_TRANSPORT_TYPE' => $Translocation->get('UF_CRM_13_PCG_TRANSPORT_TYPE'),
                    'UF_CRM_16_WEIGHT' => $Translocation->get('UF_CRM_13_PCG_WEIGHT'),
                ]);
                $comment = "Упаковке подлежат следующие позиции: \n";
                foreach ($Translocation->getProductRows()->toArray() as $row) {
                    $comment.= "{$row['PRODUCT_NAME']} - {$row['QUANTITY']} шт. \n";
                }
                $Package->set('UF_CRM_16_COMMENT', $comment);
                $PackageFactory->getAddOperation($Package)->launch();
                $resultArr['package_link'] = "/crm/type/152/details/{$Package->getId()}/";
            }

            $FinishedOrders = $ResultData['UF_CRM_13_ISSUES'];
            $Translocation->setStageId('DT180_34:SUCCESS');
            $UpdAction = $TranslocationFactory->getUpdateOperation($Translocation);
            $UpdActionResult = $UpdAction->launch();
            if($UpdActionResult->isSuccess() ) {
                $resultArr['translocation_link'] = "/crm/type/180/details/{$Translocation->getId()}/";
                $resultArr['doc'] = $Translocation->get('UF_CRM_13_DOCUMENT_LINK');
            } else {
                $errors = array_merge($errors, $UpdActionResult->getErrorMessages());
            }
        } else {
            $errors = array_merge($errors, $CreateTranslocationResult->getErrorMessages());
        }
        if($FinishedOrders) {
            $complexDocumentID = implode("_", ["DYNAMIC", self::ISSUE_SP_TYPE, $entityID]);
            $arErrorsTmp = [];
            $wfId = CBPDocument::StartWorkflow(
                498,
                ['crm', 'Bitrix\\Crm\\Integration\\BizProc\\Document\\Dynamic', $complexDocumentID],
                array('ORDERS' => $FinishedOrders, 'DST_WAREHOUSE' => $to),
                $arErrorsTmp
            );
        }

        return ['data' => $resultArr, 'errors' => $errors];

    }

    public function reloadAction($entTypeID, $entityID, $mode, $tab_id, $wh = []) {
        ob_start();
        $GLOBALS['APPLICATION']->IncludeComponent('b-integration:rusir.warehouse.translocation', '', [
            'IS_AJAX' => 'Y',
            'ENTITY_ID' => $entityID,
            'ENTITY_TYPE_ID' => $entTypeID,
            'MODE' => $mode,
            'TAB_ID' => $tab_id,
            'SOURCE_WAREHOUSE' => $wh['src'],
            'DESTINATION_WAREHOUSE' => $wh['dst']
        ]);
        $content = ob_get_contents();
        return $content;
    }
    private static function reductionQuantityValid($from, $amount) {
        return (float) $from - (float) $amount >= 0;
    }

    private static function getSrcDstWarehousesByCodes($from, $to) {
        $srcWH = (int) ElementTable::getRow(['filter' => ['IBLOCK_ID' => self::WAREHOUSE_IBLOCK, 'CODE' => $from]])['ID'];
        $dstWH = (int) ElementTable::getRow(['filter' => ['IBLOCK_ID' => self::WAREHOUSE_IBLOCK, 'CODE' => $to]])['ID'];
        return [$srcWH, $dstWH];
    }

    /**
     * @param $product
     * @param $src
     * @param $dst
     * @return \Bitrix\Crm\Item[] | null
     */
    private static function findNotFinishedTranslocations($issue, $src, $dst) {
//        var_dump(func_get_args());
        $TranslocationFactory = Service\Container::getInstance()->getFactory(self::TRANS_SP_TYPE);
        return $TranslocationFactory->getItems(['filter' => [
            'UF_CRM_13_SRC_STORAGE' => $src,
            'UF_CRM_13_DST_STORAGE' => $dst,
            'STAGE_ID' => 'DT180_34:NEW',
            'PARENT_ID_141' => $issue
        ]]);
    }

    /**
     * @param array $listFields
     * @param $displayFields
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    private function getColumns(array $listFields, $displayFields): array
    {
        $columns = [];
        foreach ($listFields as $f) {
            if (!in_array($f['FIELD_ID'], $displayFields) && !in_array($f['CODE'], $displayFields))
                continue;
            $col = ['title' => $f['NAME'], 'editor' => true, 'field' => $f['FIELD_ID']];
            if (!empty($f['CODE']))
                $col['CODE'] = $f['CODE'];
            if ($f['TYPE'] == 'S:Money') {
                $col['formatter'] = 'money';
                $col['bottomCalc'] = "sum";
                $col['bottomCalcParams'] = ['precision' => 2];
                $col['bottomCalcFormatter'] = 'money';
            } elseif ($f['TYPE'] == 'S:Date' || $f['TYPE'] == 'S:DateTime') {
                $col['formatter'] = 'datetime';
                $col['formatterParams'] = [
                    'inputFormat' => "DD.MM.YYYY",
                    'outputFormat' => "DD.MM.YYYY"
                ];
            } elseif ($f['TYPE'] == 'N') {
                $col['editor'] = 'number';
                $col['editorParams'] = [
                    'min' => 1,
                    'step' => 1
                ];

            } elseif ($f['TYPE'] == 'L') {
                $enum = ['' => 'Не выбрано'];
                $enum = $enum + array_column(PropertyEnumerationTable::getList(
                        [
                            'select' => ['ID', 'VALUE'],
                            'filter' => ['PROPERTY_ID' => $f['ID']]
                        ])->fetchAll(), 'VALUE', 'ID');
                $col['formatter'] = 'lookup';
                $col['formatterParams'] = $enum;
                $col['editor'] = 'select';
                $col['editorParams'] = [
                    'values' => $enum
                ];
                if ($f['MULTIPLE'] == 'Y')
                    $col['editorParams']['multiselect'] = true;
            } elseif ($f['TYPE'] == 'E' || $f['PROPERTY_TYPE'] == 'E') {
                $col['formatter'] = 'iblock';
                $col['formatterParams'] = [
                    'IBLOCK_ID' => $f['LINK_IBLOCK_ID']
                ];
                $col['editor'] = 'iblock';
                $col['editorParams'] = [
                    'IBLOCK_ID' => $f['LINK_IBLOCK_ID']
                ];
                $this->arResult['IBLOCK_LOOKUP'][$f['LINK_IBLOCK_ID']] = [];
                if ($f['MULTIPLE'] == 'Y')
                    $col['editorParams']['multiselect'] = true;
            } elseif ($f['TYPE'] == 'DETAIL_PICTURE') {
                $col['formatter'] = 'html';
                $col['formatterClipboard'] = 'noCopy';
                $col['editable'] = false;
            } else {
                $col['formatter'] = 'textarea';
                $col['editor'] = 'input';
            }
            $columns[] = $col;
        }
        return $columns;
    }

    /**
     * @param array $prop
     * @param array $listFields
     * @param array $props
     */
    function processPropValue(array $prop, array $listFields, array &$props)
    {

        $fieldName = 'PROPERTY_' . $prop['ID'];
        if ($listFields[$fieldName]['TYPE'] == 'S:Money') {
            $arTmp = explode('|', $prop['~VALUE'], 2);
            if (!empty($arTmp)) {
                $props[$fieldName] = $arTmp[0];
                if (!empty($arTmp[1]))
                    $currency = $arTmp[1];
            } else
                $props[$fieldName] = 0;
        } elseif ($listFields[$fieldName]['TYPE'] == 'N') {
            $props[$fieldName] = floatval($prop['~VALUE']);
        } elseif ($listFields[$fieldName]['TYPE'] == 'L') {
            $props[$fieldName] = $prop['VALUE_ENUM_ID'];
        } elseif ($listFields[$fieldName]['MULTIPLE'] == 'Y') {
            $props[$fieldName] = implode(',', $prop['VALUE']);
        } elseif ($listFields[$fieldName]['TYPE'] == 'E' || $listFields[$fieldName]['PROPERTY_TYPE'] == 'E') {
//        $this->arResult['IBLOCK_LOOKUP'][$listFields[$fieldName]['LINK_IBLOCK_ID']][$prop['~VALUE']] = false;
            $props[$fieldName] = $prop['~VALUE'];
        }elseif ($listFields[$fieldName]['TYPE']=='S:HTML'){
            //  $props[$fieldName]=$prop['~VALUE']['TEXT'];
        }else
            $props[$fieldName] = $prop['~VALUE'];


    }

    function getPrefixedEntityId($entityTypeId, int $entityId = null)
    {
        if ($entityTypeId instanceof ItemIdentifier && $entityId == null) {
            $entityId = $entityTypeId->getEntityId();
            $entityTypeId = $entityTypeId->getEntityTypeId();
        }
        if (is_numeric($entityTypeId)) {
            if ($entityTypeId < CCrmOwnerType::DynamicTypeStart)
                return CCrmOwnerType::ResolveName($entityTypeId) . '_' . $entityId;
            else
                return 'T' . dechex($entityTypeId) . '_' . $entityId;
        } else
            return $entityTypeId . '_' . $entityId;
    }

    function getProductQuantityOnWarehouseFromCompletedOrders($product, $warehouse) {
        $quantity = 0;
        if($warehouse) {
            $OrderFactory = Service\Container::getInstance()->getFactory(140);
            $OrderItems = $OrderFactory->getItems([
                'order' => ['ID'=>'DESC'],
//                'limit'=> 1,
                'filter' => [
                    'UF_CRM_12_POSITION' => $product,
                    'UF_CRM_12_DST_STORAGE' => $warehouse,
                    'STAGE_ID' => 'DT140_33:SUCCESS',
                ]
            ]);

            foreach ($OrderItems as $orderItem)
                $quantity += (int) $orderItem->get('UF_CRM_12_QUANTITY');
        }

        return $quantity;
    }

    function getProductQuantityOnWarehouseFromUnCompletedOrders($product, $warehouse) {
        $quantity = 0;
        if($warehouse) {
            $OrderFactory = Service\Container::getInstance()->getFactory(140);
            $OrderItems = $OrderFactory->getItems([
                'order' => ['ID'=>'DESC'],
                'limit'=> 1,
                'filter' => [
                    'UF_CRM_12_POSITION' => $product,
                    'UF_CRM_12_DST_STORAGE' => $warehouse,
                    'STAGE_ID' => 'DT140_33:NEW',
                ]
            ]);

            foreach ($OrderItems as $orderItem)
                $quantity = (int) $orderItem->get('UF_CRM_12_QUANTITY');
        }

//        var_dump($quantity);
//        var_dump([$product, $warehouse]);
        return $quantity;
    }

    /**
     * @param $warehouseFrom
     * @param $warehouseTo
     * @param $products
     * @return array
     * @throws ArgumentException
     */
    function getWarehouseTranslocationHistory($warehouseID, $issueID) {
//        if(!$warehouseFromID && !$warehouseToID) {
//            throw new \Bitrix\Main\ArgumentException('Destination and Source Warehouse BOTH are empty!' );
//        }
//        var_dump($warehouseID);
        if(!$warehouseID) {
            throw new \Bitrix\Main\ArgumentException('Warehouse not specified!' );
        }

        $hist = [];
        $OrderFactory = Service\Container::getInstance()->getFactory(140);
        $TranslocationFactory = Service\Container::getInstance()->getFactory(180);

        $Translocations = $TranslocationFactory->getItems([
            'order' => ['ID'=>'DESC'],
            'filter' => [
                'PARENT_ID_141' => $issueID,
                '%STAGE_ID' => 'SUCCESS',
                ['LOGIC' => 'OR', 'UF_CRM_13_SRC_STORAGE' => $warehouseID, 'UF_CRM_13_DST_STORAGE' => $warehouseID],
            ]
        ]);

        foreach ($Translocations as $translocation) {

            $WarehouseFrom = CIBlockElement::GetByID($translocation->get('UF_CRM_13_SRC_STORAGE'))->Fetch();
            $WarehouseTo = CIBlockElement::GetByID($translocation->get('UF_CRM_13_SRC_STORAGE'))->Fetch();
            $User = CUser::GetByID($translocation->getAssignedById())->Fetch();


            $translocationRow = [
                'ID' => $translocation->getId(),
                'FROM' => $WarehouseFrom['NAME'],
                'TO' => $WarehouseTo['NAME'],
                'USER' => $User['NAME']." ".$User['LAST_NAME'],
                'UID' => $User['ID'],
                'CREATE_DATE' => $translocation->getCreatedTime()->format('d.m.Y H:i'),
                'FINISH_DATE' => $translocation->getClosedate()->format('d.m.Y H:i'),
                'ORDERS' => []
            ];


            $warehouseFromID = $translocation->get('UF_CRM_13_SRC_STORAGE');
            $warehouseToID = $translocation->get('UF_CRM_13_DST_STORAGE');

            $TranslocationOrders = $OrderFactory->getItems(['filter'=>['PARENT_ID_180' => $translocation->getId()]]);

            foreach ($TranslocationOrders as $order) {
                $Position = CIBlockElement::GetById($order->get('UF_CRM_12_POSITION'))->Fetch();
                $translocationRow['ORDERS'][] = [
                    'PRODUCT' => $Position['NAME'],
                    'QUANTITY' => $order->get('UF_CRM_12_QUANTITY'),
                    'ID' => $order->getId(),
                ];
            }


            if($warehouseFromID == $warehouseID)
                $hist['FROM'][] = $translocationRow;
            if($warehouseToID == $warehouseID)
                $hist['TO'][] = $translocationRow;
        }

        return $hist;

    }
    static function getProductRestOnWarehouse($product, $warehouse) {
        $res = 0;
        $ProductWHRest = CIBlockElement::GetList([], [
            'IBLOCK_ID' => self::WAREHOUSE_RESTS_IBLOCK,
            'PROPERTY_POSITION' => $product,
            'PROPERTY_WAREHOUSE' => $warehouse
        ], false, false , ['PROPERTY_QUANTITY'])->Fetch();
        if($ProductWHRest) {
            $res = $ProductWHRest['PROPERTY_QUANTITY_VALUE'];
        }
        return $res;
    }

    static function getProductQuantityInProposal($productID) {
        $Position = CIBlockElement::GetById($productID)->GetNextElement();
        if($Position) {
            $PositionProposalID = $Position->GetProperties()['OFFER']['VALUE'];
//            var_dump($PositionProposalID); die;
            $ProposalPosition = CIBlockElement::GetByID($PositionProposalID)->GetNextElement();
            return (float) $ProposalPosition->GetProperty(91)['VALUE'];
        }
        return 0;
    }
    private function getUsersMap($filter = []): array
    {
        $filter = array_merge([
//            'ACTIVE' => 'Y',
            'IS_REAL_USER' => 'Y',
        ], $filter);
        return array_map(
            function ($a) { return $a['LAST_NAME'] . " " . $a['NAME']; },
            array_column(
                UserTable::getList(
                    [
                        'select' => ['ID', 'NAME', 'LAST_NAME'],
                        'filter' => $filter
                    ])->fetchAll(),
                null,
                'ID'
            )
        );
    }

    private function getDepartments($head): array {
//        $deps = CIntranetUtils::getSubStructure($head);
        $deps = CIntranetUtils::getSubDepartments($head);
        return $deps;
    }
}