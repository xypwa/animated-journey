<?php

namespace BIntegration\Service\Factory\Translocation;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\Type;
use Bitrix\Crm\Relation\RelationManager;
use Bitrix\Disk\AttachedObject;
use Bitrix\Disk\Internals\AttachedObjectTable;
use Bitrix\Disk\Internals\Error\ErrorCollection;
use Bitrix\Lists\Entity\Element;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\Result;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service;
use Bitrix\Crm\Service\Operation;
use Bitrix\Main\DI;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Date;

class TranslocationFactory extends Service\Factory\Dynamic
{
    public function __construct(Type $type)
    {
        parent::__construct($type);
        if(!defined('POSITIONS_IBLOCK_ID'))
            define('POSITIONS_IBLOCK_ID', 15);
        if(!defined('PROPOSALS_IBLOCK_ID'))
            define('PROPOSALS_IBLOCK_ID', 20);
        if(!defined('RESTS_IBLOCK_ID'))
            define('RESTS_IBLOCK_ID', 24);
        if(!defined('WAREHOUSE_IBLOCK_ID'))
            define('WAREHOUSE_IBLOCK_ID', 23);
        if(!defined('SP_ORDER_ID'))
            define('SP_ORDER_ID', 140);
        if(!defined('SP_PLACEMENT_ID'))
            define('SP_PLACEMENT_ID', 152);
        if(!defined('SP_ISSUE_ID'))
            define('SP_ISSUE_ID', 141);
    }

    public function getAddOperation(Item $item, Service\Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);
        return $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new TranslocationStart()
        );
    }

    public function getUpdateOperation(Item $item, Service\Context $context = null): Operation\Update
    {
        $updateOperation = parent::getUpdateOperation($item, $context);
        AddMessage2Log("Изменение стадии: ".print_r($item->toArray(), true));
        if($item->isChangedStageId() && str_contains($item->getStageId(), 'SUCCESS')) {
            return $updateOperation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new TranslocationCommit()
            );
        } else {
            $item->delete();
        }
        return $updateOperation;
    }

    public function getDeleteOperation(Item $item, Service\Context $context = null): Operation\Delete
    {
        $operation = parent::getDeleteOperation($item, $context);
        return $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new TranslocationDestroy()
        );
    }
}

class Translocation extends Operation\Action {

    public function __construct()
    {
        parent::__construct();
        $this->WarehouseIB = \Bitrix\Iblock\Iblock::wakeUp(WAREHOUSE_IBLOCK_ID)->getEntityDataClass();
        $this->RestsIB = \Bitrix\Iblock\Iblock::wakeUp(RESTS_IBLOCK_ID)->getEntityDataClass();
        $this->PositionsIB = \Bitrix\Iblock\Iblock::wakeUp(POSITIONS_IBLOCK_ID)->getEntityDataClass();
        $this->ProposalIB = \Bitrix\Iblock\Iblock::wakeUp(PROPOSALS_IBLOCK_ID)->getEntityDataClass();
        $this->IssueFactory = Service\Container::getInstance()->getFactory(SP_ISSUE_ID);
        $this->OrderFactory = Service\Container::getInstance()->getFactory(SP_ORDER_ID);
        $this->PlacementFactory = Service\Container::getInstance()->getFactory(SP_PLACEMENT_ID);
    }


    public function process(Item $item): Result {
        $result = new Result();
//        $CIBlockEl = new \CIBlockElement();
//        AddMessage2Log(print_r($item->toArray(), true));
        return $result;
    }

    /**
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentException
     */
    function getWarehouseById($id) {
        return $this->WarehouseIB::getById($id)->fetch();
//        return ElementTable::getRow(['filter' => ['IBLOCK_ID' => self::WAREHOUSE_IBLOCK, 'ID' => (int)$id]]);
    }
    function getPositionById($id) {
        $res = $this->PositionsIB::getByPrimary($id, ['select' => ['ID', 'NAME', 'QUANTITY_' => 'QUANTITY', 'PROPOSAL_' => 'OFFER']]);
        $result = [];
        if($res->getSelectedRowsCount()) {
            $res = $res->fetchObject();
            $result['NAME'] = $res->getName();
            $result['ID'] = $res->getId();
            $result['QUANTITY'] = $res->getQuantity()->getValue();
            $Proposal = $res->getOffer()->getValue();
            if($Proposal) {
                $Proposal = $this->ProposalIB::getByPrimary($Proposal, ['select' => ['ID', 'NAME', 'QUANTITY_' => 'VNALICHII']])->fetchObject();
                $result['PROPOSAL_QUANTITY'] = $Proposal->get('VNALICHII')->getValue();
            }
        }
        return $result;
    }

    function createEmptyRestForWH(array $positionIDs, $dstWH) {

        $DestinationWarehouse = $this->getWarehouseById($dstWH);

        $CIBlockEl = new \CIBlockElement();
        foreach ($positionIDs as $positionID) {
            $position = $this->getPositionById($positionID);

            $addFields = [
                'NAME' => "[{$DestinationWarehouse['NAME']}] {$position['NAME']}",
                'IBLOCK_ID' => RESTS_IBLOCK_ID,
                'PROPERTY_VALUES' => [
                    'WAREHOUSE' => $DestinationWarehouse['ID'],
                    'POSITION' => $position['ID'],
                    'QUANTITY' => 0
                ]
            ];
//            $res = $this->WarehouseIB::add(['fields' => $addFields]);
            $res = $CIBlockEl->Add($addFields);
            if($CIBlockEl->LAST_ERROR) {
                $err = $res->getErrorMessages();
                AddMessage2Log("Не удалось создать позицию в остатках: ".$err);
                return false;
            }
        }
        return true;
    }

    public function TranslocationAvaliable($src, $dst, $amount, $positionID) {

        $Result = new Result();
        $Position = $this->getPositionById($positionID);
        //step 1: Amount should be Less or equal Position Proposal
        //
         if(!$this->compare($Position['PROPOSAL_QUANTITY'], $amount)) {
             $err = sprintf(
                 "Перемещаемое кол-во (%d) позиций %s <b>больше</b> чем в закупе (%d)",
                 $amount, $Position['NAME'], $Position['PROPOSAL_QUANTITY']
             );
             $Result->addError((new Error($err)));
             return $Result;
         }
        //step 2: Amount SUM by other warehouse rests should be Less or equal Position Proposal
        //
        $otherWHRests = 0;
        $dstWH = $this->getWarehouseById($dst);
        $res = $this->RestsIB::getList([
             'select' => ['ID', 'NAME', 'WAREHOUSE_' => 'WAREHOUSE', 'POSITION_' => 'POSITION', 'QUANTITY_' => 'QUANTITY'],
             'filter' => ['POSITION_VALUE' => $Position['ID'], '!WAREHOUSE_VALUE' => $src]
        ])->fetchCollection();
        foreach ($res as $row) {
            $otherWHRests += $row->getQuantity()->getValue();
        }
//        var_dump($Position['PROPOSAL_QUANTITY']);
//        var_dump($otherWHRests);
//        var_dump($amount);
//        die;
        if(!$this->compare($Position['PROPOSAL_QUANTITY'] - $otherWHRests, $amount)) {
            $dstWH = $this->getWarehouseById($dst);
            $err = sprintf(
                "Перемещаемое кол-во (%d) + кол-во на других складах (%d) позиции %s на <b style='color:#ff6c62'>%s</b> превышает кол-во в закупе (%d)",
                $amount, $otherWHRests, $Position['NAME'], $dstWH['NAME'], $Position['PROPOSAL_QUANTITY']
            );
            $Result->addError((new Error($err)));
            return $Result;
        }
        return $Result;
    }

    function compare($from, $amount) {
        return (float) $from - (float) $amount >= 0;
    }

    /**
     * @param Item $item
     * @return array
     */
    public function getRelatedOrders(Item $item){
        $res = [];
        $RelManager = new RelationManager();

        $RelatedElements = $RelManager->getChildElements(new ItemIdentifier($item->getEntityTypeId(), $item->getId()));
        foreach ($RelatedElements as $element) {
            if ($element->getEntityTypeId() == SP_ORDER_ID) {
                $res[] = $element->getEntityId();
            }
        }
        return $res;
    }

    public function getRelatedPackage(Item $item){
        $res = null;
        $RelManager = new RelationManager();

        $RelatedElements = $RelManager->getChildElements(new ItemIdentifier($item->getEntityTypeId(), $item->getId()));
        foreach ($RelatedElements as $element) {
            if ($element->getEntityTypeId() == SP_PLACEMENT_ID) {
                return $element->getEntityId();
            }
        }
        return $res;
    }

    public function increasePositionRest($positionID, $amount, $warehouseID) {
        $result = new Result();
        $restRecord = $this->RestsIB::getRow([
            'select' => ['ID', 'POSITION_' => 'POSITION', 'WAREHOUSE_' => 'WAREHOUSE', 'QUANTITY_' => 'QUANTITY'],
            'filter' => ['POSITION_VALUE' => $positionID, 'WAREHOUSE_VALUE' => $warehouseID]
        ]);
        if($restRecord) {
            $newQuantity = $amount + $restRecord['QUANTITY_VALUE'];
            \CIBlockElement::SetPropertyValueCode($restRecord['ID'], 'QUANTITY', $newQuantity);
            $res = $this->RestsIB::update($restRecord['ID'], ['fields' => ['QUANTITY' => $newQuantity]]);
            if($res->isSuccess())
               $result->setData(['NEW_QUANTITY' => $newQuantity]);
            else {
                $err = $res->getErrorMessages();
                AddMessage2Log("Не удалось изменить остаток позиции: ".reset($err));
                $result->addErrors($res->getErrors());
            }
        }

        return $result;
    }

    public function reducePositionRest($positionID, $amount, $warehouseID) {
        $result = new Result();
        $restRecord = $this->RestsIB::getRow([
            'select' => ['ID', 'POSITION_' => 'POSITION', 'WAREHOUSE_' => 'WAREHOUSE', 'QUANTITY_' => 'QUANTITY'],
            'filter' => ['POSITION_VALUE' => $positionID, 'WAREHOUSE_VALUE' => $warehouseID]
        ]);
        if($restRecord) {
            $newQuantity = $restRecord['QUANTITY_VALUE'] - $amount;
            \CIBlockElement::SetPropertyValueCode($restRecord['ID'], 'QUANTITY', $newQuantity);
            $res = $this->RestsIB::update($restRecord['ID'], ['fields' => ['QUANTITY' => $newQuantity]]);
            if($res->isSuccess())
                $result->setData(['NEW_QUANTITY' => $newQuantity]);
            else {
                $err = $res->getErrorMessages();
                AddMessage2Log("Не удалось изменить остаток позиции: ".reset($err));
                $result->addErrors($res->getErrors());
            }
        } else {
            $result->addError(new Error(sprintf('Не удалось найти остаток позиции %d на складе %d', $positionID, $warehouseID)));
        }
        return $result;
    }
}
class TranslocationStart extends Translocation {
    public function process(Item $item): Result
    {
        $result = new Result();

        /*Проверка склада назначения*/
        $DestinationWarehouse = $this->getWarehouseById($item->get('UF_CRM_13_DST_STORAGE'));
        if(!$DestinationWarehouse) {
            $result->addError( new Error('Не указан склад назначения') );
            return $result;
        }

        /*Проверка позиций*/
        $Positions = [];
        $PositionsCollection = $item->getProductRows();
        if($PositionsCollection && $PositionsCollection->toArray())
            foreach ($PositionsCollection as $PositionItem) {
                if($PositionItem['QUANTITY'] <= 0)
                    continue;

                $PosName = $PositionItem['PRODUCT_NAME'];
                $Quantity = $PositionItem['QUANTITY'];
                $OriginalPosition = null;
                $PosIDs = [];
                preg_match("~(\[\d+\])$~u", $PosName, $PosIDs);
                if($PosIDs) {
                    $PosID = trim(end($PosIDs), "[]");
                    $OriginalPosition = $this->getPositionById($PosID);
                    $OriginalPosition['QUANTITY'] = $Quantity;
                }
                if(!$OriginalPosition) {
                    $result->addError( new Error('Указанная позиция не найдена') );
                    return $result;
                }
                $check = $this->TranslocationAvaliable($item->get('UF_CRM_13_SRC_STORAGE'), $DestinationWarehouse['ID'], $Quantity, $PosID);
                if(!$check->isSuccess()) {
                    foreach ($check->getErrors() as $err) {
                        $result->addError($err);
                    }
                    return $result;
                }

                $Positions[] = $OriginalPosition;

            }
        else {
            $result->addError( new Error('Не указаны перемещаемые Позиции') );
            return $result;
        }
        if(!$Positions) {
            $result->addError( new Error('Ни одна позиция не выбрана') );
            return $result;
        }
//        if(!$item->get('UF_CRM_13_POSITIONS') && is_array($item->get('UF_CRM_13_POSITIONS')) ) {
//            foreach ($item->get('UF_CRM_13_POSITIONS') as $positionID) {
//                $OriginalPosition = ElementTable::getById($positionID)->fetch();
//                if(!$OriginalPosition) {
//                    $result->addError( new \Bitrix\Main\Error('Указанная позиция не найдена') );
//                    return $result;
//                }
//                $OriginalPosition['PROPOSAL'] = $this->getPositionSelectedProposal($positionID);
//                $Positions[] = $OriginalPosition;
//            }
//
//        }

        $restRec = $this->RestsIB::getRow([
            'select' => ['ID', 'WAREHOUSE_' => 'WAREHOUSE', 'POSITION_' => 'POSITION'],
            'filter' => ['WAREHOUSE_VALUE' => $DestinationWarehouse['ID'], 'POSITION_VALUE' => array_column($Positions, 'ID')]
        ]);
        if(!$restRec) {
            if(!$this->createEmptyRestForWH(array_column($Positions, 'ID'), $DestinationWarehouse['ID'])) {
                $result->addError((new Error("Ошибка создания эл-та остатков склада назначения")));
                return $result;
            }
        }

        $SourceWarehouseID = $item->get('UF_CRM_13_SRC_STORAGE');
        $this->setDescription($item, $DestinationWarehouse, $SourceWarehouseID, $Positions);

        /*создать ордера*/
        $newOrderIDs = [];
        $OrderFactory = Service\Container::getInstance()->getFactory(SP_ORDER_ID);
//        var_dump($Positions);
        foreach ($Positions as $p) {
            $NewOrder = $OrderFactory->createItem([
                'UF_CRM_12_POSITION' => $p['ID'],
                'UF_CRM_12_SRC_STORAGE' => $SourceWarehouseID,
                'UF_CRM_12_DST_STORAGE' => $DestinationWarehouse['ID'],
                'UF_CRM_12_QUANTITY' => $p['QUANTITY'],
                'ASSIGNED_BY_ID' => $item->getAssignedById(),
            ]);
            $OrderAddOperation = $OrderFactory->getAddOperation($NewOrder)->launch();
            $newOrderIDs[] = $NewOrder->getId();
        }
        $item->set('UF_CRM_13_ISSUES', $newOrderIDs);
        $result->setData(array_merge($result->getData(), ['ORDERS' => $newOrderIDs]));

        if(!$result->isSuccess()) {
            AddMessage2Log("ADD ORDER ERROR: \n".implode("\n", $result->getErrorMessages()));
        }
        return $result;
    }


    public function setDescription(Item $item, $dstWH, $srcWH, $Positions): void
    {
        if($srcWH && $SourceWarehouse = $this->getWarehouseById($srcWH)) {
            $item->setTitle("Перемещение {$SourceWarehouse['NAME']} -> {$dstWH['NAME']}");
            $item->set('UF_CRM_13_COMMENT',
                "Приход на склад {$dstWH['NAME']}. ".
                "Источник : {$SourceWarehouse['NAME']}"
            );
        } else if(!$this->getWarehouseById($srcWH))  {
            $item->setTitle("Приход на {$dstWH['NAME']}");
            $item->set('UF_CRM_13_COMMENT',
                "Приход на склад {$dstWH['NAME']}. ".
                "Источник : прибыл по заявке от поставщика\nТовары:\n"
            );
        }
        foreach ($Positions as $p) {
            $item->set('UF_CRM_13_COMMENT',
                $item->get('UF_CRM_13_COMMENT').
                "{$p['NAME']} ({$p['QUANTITY']})\n"
            );
        }
    }
}

class TranslocationCommit extends Translocation {

    private $DocGenerator;
    function __construct()
    {
        parent::__construct();

    }

    public function process(Item $item): Result
    {
        $result = new Result();

        if(!$Orders = $this->getRelatedOrders($item)) {
            $result->addError((new Error("С перемещением не связано ордеров")));
            return $result;
        }
        $need_rollback = 0;
        foreach ($Orders as $OrderID) {
            $OrderItem = $this->OrderFactory->getItem($OrderID);
            $Quantity = $OrderItem->get('UF_CRM_12_QUANTITY');
            $Position = $this->getPositionById($OrderItem->get('UF_CRM_12_POSITION'));
            $ProposalQuantity = $Position['PROPOSAL_QUANTITY'];

            $Src = $OrderItem->get('UF_CRM_12_SRC_STORAGE');
            $NonEmptySource = (bool) $Src;
            $Dst = $OrderItem->get('UF_CRM_12_DST_STORAGE');

            $check = $this->TranslocationAvaliable($Src, $Dst, $Quantity, $Position['ID']);
            if(!$check) {
                foreach ($check->getErrors() as $err) {
                    $result->addError($err);
                }
                return $result;
            }

            /*Увеличим остаток на складе назначения*/
            $increaseResult = $this->increasePositionRest($Position['ID'], $Quantity, $Dst);
            if($increaseResult->isSuccess()) {
//                $result->setData(['DST_NEW_Q' => $increaseResult->getData()['NEW_QUANTITY']]);
            } else {
                $result->addErrors($increaseResult->getErrors());
                return $result;
            }

            /* Склад Источник не указывается в случае [повторяемой] приемки товара
            * В этом случае мы "из ни откуда" плюсуем кол-во на склад назначения
             * Если же склад указан, то у него нужно отнять заданное кол-во
            */
            if($NonEmptySource) {
                $reduceResult = $this->reducePositionRest($Position['ID'], $Quantity, $Src);
                if($reduceResult->isSuccess()) {
//                    $result->setData(array_merge($result->getData(), ['SRC_NEW_Q' => $reduceResult->getData()['NEW_QUANTITY']]));
                } else {
                    $reduceResult = $this->reducePositionRest($Position['ID'], $Quantity, $Dst);
                    $result->addErrors($reduceResult->getErrors());
                    return $result;
                }
            }
            $OrderItem->setStageId('DT140_33:SUCCESS');
            $OrderItem->save();
        }

        if(!$result->isSuccess()) {
            AddMessage2Log("COMMIT ORDER ERROR: \n".implode("\n", $result->getErrorMessages()));
        }
        AddMessage2Log("COMMIT ORDER RESULT: \n".implode("\n", $result->getData()));

        if($Package = $this->getRelatedPackage($item)) {

        }

        $createDocument = $this->createTranslocationDocument($item);

        if($createDocument->isSuccess() && $createDocument->getData()['id']) {
            $resultData = $createDocument->getData();
            $result->setData(array_merge($resultData, $result->getData()));
            $item->set('UF_CRM_13_DOCUMENT_LINK', $resultData['url']);
            $item->save();
            $this->createIBDocument($resultData['id'], $item->get('PARENT_ID_141'));

//            if($Package = $this->getRelatedPackage($item)) {
//                $PackageFactory = Service\Container::getInstance()->getFactory(SP_PLACEMENT_ID);
//                $PackageItem = $PackageFactory->getItem($Package);
//                $PackageItem->set('UF_CRM_16_DOCUMENT_LINK', $resultData['url']);
//                \Bitrix\Crm\Timeline\CommentEntry::create(array(
//                    'TEXT' => "Был создан <a href='{$resultData['url']}'>документ</a> по перемещению",
//                    'SETTINGS' => ['HAS_FILES' => 'Y'],
//                    'FILES'=>   array(\Bitrix\Disk\Uf\FileUserType::NEW_FILE_PREFIX.$resultData['id']),
//                    'AUTHOR_ID' => 1,
//                    'BINDINGS' => [['ENTITY_TYPE_ID'=>$PackageItem->getEntityTypeId(), 'ENTITY_ID'=>$Package]],
//                ));
//                $PackageItem->save();
//            }

        } else {
//            var_dump($createDocument->getErrors());
        }
        return $result;
    }

    private function createTranslocationDocument(Item $item) {
        $result = new Result();

        require_once $_SERVER['DOCUMENT_ROOT']."/local/components/b-integration/rusir.document.generator/DocumentGenerator.php";
        $II = ItemIdentifier::createByItem($item);
        $Warehouse = \CIBlockElement::GetByID($item->get('UF_CRM_13_DST_STORAGE'))->Fetch();
        try {
            $this->DocGenerator = new \DocumentGenerator($II, $Warehouse['CODE']);
            $res = $this->DocGenerator->generate();
            if($res) {
                $result->setData($res);

                if($Package = $this->getRelatedPackage($item)) {
                    $PackageItem = $this->PlacementFactory->getItem($Package);
                    $IssueItem = $this->IssueFactory->getItem($item->get('PRENT_ID_141'));

                    $PackageItem->set('UF_CRM_16_DOCUMENT_LINK', $res['url']);
                    \Bitrix\Crm\Timeline\CommentEntry::create(array(
                        'TEXT' => "Был создан <a href='{$res['url']}'>документ</a> по перемещению",
                        'SETTINGS' => ['HAS_FILES' => 'Y'],
                        'FILES'=> array(\Bitrix\Disk\Uf\FileUserType::NEW_FILE_PREFIX.$res['id']),
                        'AUTHOR_ID' => 1,
                        'BINDINGS' => [['ENTITY_TYPE_ID'=>$PackageItem->getEntityTypeId(), 'ENTITY_ID'=>$Package]],
                    ));

                    $PackageItem->save();
                }

            } else {
                $result->setData(['id' => 0]);
            }
        } catch (\Bitrix\Main\SystemException $e) {

        }
        return $result;
    }

    function createIBDocument($id, $issueId) {
        $IB = new \CIBlockElement();
        $res = $IB->Add([
            'IBLOCK_ID' => 25,
            'NAME' => 'Test',
            'PROPERTY_VALUES' => [
                107 => $issueId,
//                108 => [
//                    $id
//                ],
                109 => 1,
                110 => Date::createFromTimestamp(time())->format('Y-m-d'),
            ]
        ]);
        if(!$IB->LAST_ERROR) {
            $r = AttachedObject::add(['OBJECT_ID' => $id, 'ENTITY_ID' => $res, 'ENTITY_TYPE' => 'Bitrix\Disk\Uf\IblockElementConnector', 'MODULE_ID' => 'iblock'], new ErrorCollection());
            if($r->getId()) {
                \CIBlockElement::SetPropertyValuesEx($res, 25, [108 => [ 'VALUE' => [$r->getId()]]]);
//                $IB->Update($res, ['ID' => $res, 'PROPERTY_VALUES' => ['108' => [$r->getId()]]]);
            }
//            var_dump($r->toArray());
//            die;
        }
    }

    public function createPackage(Item $item)
    {
        $Package = $this->PlacementFactory->createItem([
            'PARENT_ID_180' => $item->getId(),
            'TITLE' => '',
            'UF_CRM_16_MEDIA' => $item->get('UF_CRM_13_PCG_MEDIA'),
            'UF_CRM_16_TRANSPORT_INFO' => $item->get('UF_CRM_13_PCG_TRANSPORT_INFO'),
            'UF_CRM_16_TRANSPORT_TYPE' => $item->get('UF_CRM_13_PCG_TRANSPORT_TYPE'),
            'UF_CRM_16_WEIGHT' => $item->get('UF_CRM_13_PCG_WEIGHT'),
        ]);
        $comment = "Упаковке подлежат следующие позиции: \n";
        foreach ($item->getProductRows()->toArray() as $row) {
            $comment.= "{$row['PRODUCT_NAME']} - {$row['QUANTITY']} шт. \n";
        }
        $Package->set('UF_CRM_16_COMMENT', $comment);
        $this->PlacementFactory->getAddOperation($Package)->launch();
        return $Package->getId();
    }
}

class TranslocationDestroy extends Translocation {
    function __construct()
    {
        parent::__construct();
    }

    function process(Item $item): Result
    {
        $result = new Result();

        if($Orders = $this->getRelatedOrders($item)) {
            foreach ($Orders as $OrderID) {
                $OrderItem = $this->OrderFactory->getItem($OrderID);
                $OrderDeleteOperation = $this->OrderFactory->getDeleteOperation($OrderItem);
                $res = $OrderDeleteOperation->launch();
                if($res->isSuccess()) {
//                    $result->setData($res->getData());
                } else {
                    $result->addErrors($res->getErrors());
                }
            }
        }
        // TODO удалить Места
        return $result;
    }
}
class TranslocationRollback extends Operation\Action {
    public function process(Item $item): Result {
        return new Result();
    }
}