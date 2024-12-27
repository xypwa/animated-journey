<?php

namespace BIntegration\Service\Factory\Placement;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\Type;
use Bitrix\Crm\ProductRow;
use Bitrix\Crm\Relation\RelationManager;
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
use Bitrix\Crm\Service\Factory;

class PlacementFactory extends Service\Factory\Dynamic
{
    public function __construct(Type $type)
    {
        parent::__construct($type);
        if(!defined('WAREHOUSE_IBLOCK_ID'))
            define('WAREHOUSE_IBLOCK_ID', 23);
        if(!defined('POSITION_IBLOCK_ID'))
            define('POSITION_IBLOCK_ID', 15);
    }

    public function getAddOperation(Item $item, Service\Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);
        return $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new PlacementCreate()
        );
//        return $operation;
    }

    public function getUpdateOperation(Item $item, Service\Context $context = null): Operation\Update
    {
        $updateOperation = parent::getUpdateOperation($item, $context);
        AddMessage2Log("Изменение стадии: ".print_r($item->toArray(), true));
        if($item->isChangedStageId()) {
            if(str_contains($item->getStageId(), 'SUCCESS')) {
                return $updateOperation->addAction(
                    Operation::ACTION_BEFORE_SAVE,
                    new PlacementFinish()
                );
            } else if(str_contains($item->getStageId(), 'PREPARATION')) {
                return $updateOperation->addAction(
                    Operation::ACTION_AFTER_SAVE,
                    new PlacementSetup()
                );
            }
        }
        return $updateOperation;
    }

//    public function getDeleteOperation(Item $item, Service\Context $context = null): Operation\Delete
//    {
//        $operation = parent::getDeleteOperation($item, $context);
//        return $operation->addAction(
//            Operation::ACTION_BEFORE_SAVE,
//            new TranslocationDestroy()
//        );
//    }
}
class Placement extends Operation\Action {

    protected $SRC_WAREHOUSE_CODE = 'LOGISTIC';
    protected $DST_WAREHOUSE_CODE = 'DISCARD_LOGISTIC';
    public Factory $TranslocationFactory;
    function __construct()
    {
        parent::__construct();
        $this->WarehouseIB = \Bitrix\Iblock\Iblock::wakeUp(WAREHOUSE_IBLOCK_ID)->getEntityDataClass();
        $this->PositionIB = \Bitrix\Iblock\Iblock::wakeUp(POSITION_IBLOCK_ID)->getEntityDataClass();
        $this->TranslocationFactory = Service\Container::getInstance()->getFactory(SP_TRANSLOCATION_ID);
        $this->OrderFactory = Service\Container::getInstance()->getFactory(SP_ORDER_ID);
        $this->IssueFactory = Service\Container::getInstance()->getFactory(SP_ISSUE_ID);

    }

    public function process(Item $item): Result {
        $result = new Result();
//        $CIBlockEl = new \CIBlockElement();
//        AddMessage2Log(print_r($item->toArray(), true));
        return $result;
    }

    /**
     * @param Item $item
     * @return Item|null
     */
    function getParentTranslocation(Item $item) {
        $RM = new RelationManager();
        $ItemParents = $RM->getParentElements(ItemIdentifier::createByItem($item));
        foreach ($ItemParents as $ItemParent) {
            if($ItemParent->getEntityTypeId() == SP_TRANSLOCATION_ID) {
                return $this->TranslocationFactory->getItem($ItemParent->getEntityId());;
            }
        }
        return null;
    }

    function getRelatedOrders(Item $item) {
        $res = [];
        $RM = new RelationManager();

        $RelatedElements = $RM->getChildElements(ItemIdentifier::createByItem($item));
        foreach ($RelatedElements as $element) {
            if ($element->getEntityTypeId() == SP_ORDER_ID) {
                $res[] = $this->OrderFactory->getItem($element->getEntityId());
            }
        }
        return $res;
    }
    function getWarehouseByCode($code) {
        $a = ElementTable::getRow(['filter' => ['IBLOCK_ID' => WAREHOUSE_IBLOCK_ID, 'CODE' => $code]]);
        return $a['ID'];
    }
}

class PlacementSetup extends Placement
{
    public function process(Item $item): Result
    {
        $result = new Result();
        $ParentTranslocation = $this->getParentTranslocation($item);

        if(!$ParentTranslocation) {
            $result->addError(new Error("Нет данных о перемещении этого места"));
            return $result;
        }
        $ParentTranslocationOrders = $this->getRelatedOrders($ParentTranslocation);
        if(!$ParentTranslocationOrders) {
            $result->addError(new Error("У связанного с этим местом Перемещения отсутствуют Ордера"));
            return $result;
        }
        $TranslocationItem = null;
        $AlreadyExistedTranslocation = $this->TranslocationFactory->getItems([
            'filter' => [
                'UF_CRM_13_SRC_STORAGE' => $this->getWarehouseByCode($this->SRC_WAREHOUSE_CODE),
                'UF_CRM_13_DST_STORAGE' => $this->getWarehouseByCode($this->DST_WAREHOUSE_CODE),
                'PARENT_ID_141' => $ParentTranslocation->get('PARENT_ID_141'),
            ],
        ]);
        if($AlreadyExistedTranslocation) {
            $RemoveOperation = $this->TranslocationFactory->getDeleteOperation(reset($AlreadyExistedTranslocation));
//            $TranslocationItem = reset($AlreadyExistedTranslocation);
            $OperationResult = $RemoveOperation->launch();
            if(!$OperationResult->isSuccess()) {
                $result->addErrors($RemoveOperation->getErrors());
//                var_dump($RemoveOperation->getErrorMessages());
//                die;
                return $result;
            }
        }

        $TranslocationItem = $this->TranslocationFactory->createItem([
            'UF_CRM_13_SRC_STORAGE' => $this->getWarehouseByCode($this->SRC_WAREHOUSE_CODE),
            'UF_CRM_13_DST_STORAGE' => $this->getWarehouseByCode($this->DST_WAREHOUSE_CODE),
            'PARENT_ID_141' => $ParentTranslocation->get('PARENT_ID_141'),
        ]);
        $ProductIds = [];
        foreach ($ParentTranslocationOrders as $Order) {
            $Product = $Order->get('UF_CRM_12_POSITION');
            $ProductIds[] = $Product;

            $p = ElementTable::getById($Product)->fetch();
            $TranslocationItem->addToProductRows(ProductRow::createFromArray([
                'PRODUCT_ID' => 0,
                'QUANTITY' => $Order->get('UF_CRM_12_QUANTITY'),
                'PRODUCT_NAME' => $p['NAME']. " [{$Product}]",
            ]));
        }
        $item->set('UF_CRM_16_POSITIONS', $ProductIds);
//        $TranslocationItem->setProductRows($ParentTranslocation->getProductRows());

        $AddOperation = $this->TranslocationFactory->getAddOperation($TranslocationItem);
        $OperationResult = $AddOperation->launch();
        if($OperationResult->isSuccess()) {
            $ResultData = $OperationResult->getData();
//            var_dump($ResultData);
//            die;
            $createdOrders = $ResultData['UF_CRM_13_ISSUES'];

            foreach ($createdOrders as $order) {
                $Order = $this->OrderFactory->getItem($order);
                $Order->set('PARENT_ID_180', $TranslocationItem->getId());
                $Order->save();
            }
//            var_dump($ResultData);
//            die;
            $item->set('UF_CRM_16_NEXT_TRANSLOCATION', $TranslocationItem->getId());
            $item->save();

        } else {
            $result->addErrors($OperationResult->getErrors());
//            var_dump($OperationResult);
//            die;
        }

        return $result;
    }
}

class PlacementFinish extends Placement
{
    public function process(Item $item): Result
    {
        $result = new Result();

        $TranslocationItem = $this->TranslocationFactory->getItem($item->get('UF_CRM_16_NEXT_TRANSLOCATION'));

        if(!$TranslocationItem) {
            $result->addError(new Error('Не найдено перемещение, связанное с этим местом'));
            return $result;
        }
        $TranslocationItem->setStageId('DT180_34:SUCCESS');
        $CommitTranslocationOperation = $this->TranslocationFactory->getUpdateOperation($TranslocationItem);
        $CommitResult = $CommitTranslocationOperation->launch();
        if($CommitResult->isSuccess()) {

        } else {
            $result->addErrors($CommitResult->getErrors());
        }
        return $result;

        // Завершаем перемещение с логистики в списание
    }
}

class PlacementCreate extends Placement {
    public function process(Item $item): Result {
        $result = new Result();
        if($item->get('PARENT_ID_180')) {
            $Translocation = $this->TranslocationFactory->getItem($item->get('PARENT_ID_180'));
            if($Translocation && $Translocation->get('PARENT_ID_141')) {
                $Issue = $this->IssueFactory->getItem($Translocation->get('PARENT_ID_141'));
                if($Issue && $Issue->get('UF_CRM_11_1712656371')) {
                    $item->set('UF_CRM_16_TRANSPORT_TYPE', $Issue->get('UF_CRM_11_1712656371'))->save();
                }
            }
            $ttl_weight = 0;
            foreach ($Translocation->get('UF_CRM_13_ISSUES') as $OrderID) {
                $Order = $this->OrderFactory->getItem($OrderID);
                $PositionID = $Order->get('UF_CRM_12_POSITION');
                $Position = $this->PositionIB::getByPrimary($PositionID, ['select' => ['WEIGHT_' => 'WEIGHT']])->fetchObject();
                $weight = (float) $Position->getWeight()->getValue();

                $quantity = $Order->get('UF_CRM_12_QUANTITY');
                $ttl_weight += $weight * $quantity;
            }
            $item->set('UF_CRM_16_WEIGHT_NETTO', $ttl_weight)->save();
        }

        return $result;
    }
}