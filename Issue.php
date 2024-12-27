<?php

namespace BIntegration\Service\Factory\Issue;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Model\Dynamic\Type;
use Bitrix\Crm\ProductRow;
use Bitrix\Crm\Relation\RelationManager;
use Bitrix\Crm\RelationIdentifier;
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

define('LOG_FILENAME', __DIR__ . '/IssueClass.txt');

class IssueFactory extends Service\Factory\Dynamic
{
    public function __construct(Type $type)
    {
        parent::__construct($type);
    }

    public function getAddOperation(Item $item, Service\Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);
        return $operation;
    }

    public function getUpdateOperation(Item $item, Service\Context $context = null): Operation\Update
    {
        $updateOperation = parent::getUpdateOperation($item, $context);
//        AddMessage2Log("Изменение стадии: ".print_r($item->toArray(), true));
//        if($item->isChanged('UF_CRM_11_1711945370858')) {
//            $updateOperation->addAction(
//                Operation::ACTION_BEFORE_SAVE,
//                new IssuePushFields()
//            );
//        }
//        if($item->isChanged('UF_CRM_11_1712656281')) {
//            $updateOperation->addAction(
//                Operation::ACTION_BEFORE_SAVE,
//                new IssuePushFields_2()
//            );
//        }
        return $updateOperation;
    }

}
class Issue extends Operation\Action {

    public Factory $TranslocationFactory;
    function __construct()
    {
        parent::__construct();
        $this->WarehouseIB = \Bitrix\Iblock\Iblock::wakeUp(WAREHOUSE_IBLOCK_ID)->getEntityDataClass();
        $this->IssueFactory = Service\Container::getInstance()->getFactory(SP_ISSUE_ID);
        $this->TranslocationFactory = Service\Container::getInstance()->getFactory(SP_TRANSLOCATION_ID);
        $this->OrderFactory = Service\Container::getInstance()->getFactory(SP_ORDER_ID);

    }

    public function process(Item $item): Result {
        $result = new Result();
        return $result;
    }

}

class IssuePushFields extends Issue
{
    public function process(Item $item): Result
    {
        AddMessage2Log("\nIssuePushFields\n");

        $result = new Result();
        $RelManager = new RelationManager();
        $ProcurementFactory = Service\Container::getInstance()->getFactory(132);
        $PlacementFactory = Service\Container::getInstance()->getFactory(152);
        $relation1 = $RelManager->getRelation(new RelationIdentifier($item->getEntityTypeId(), 164));
        $relation2 = $RelManager->getRelation(new RelationIdentifier(132, 164));
        $subissues = $relation1->getChildElements(ItemIdentifier::createByItem($item));

        foreach ($subissues as $subissue)
            foreach ($relation2->getParentElements($subissue) as $procurement) {
                $ProcurementFactory->getItem($procurement->getEntityId())
                    ->set('UF_CRM_3_1712203538', $item->get('UF_CRM_11_1711945370858') )
                    ->set('ASSIGNED_BY_ID', $item->get('UF_CRM_11_1712656281') )
                    ->save();
            }

        $relation3 = $RelManager->getRelation(new RelationIdentifier($item->getEntityTypeId(), 180));
        $relation4 = $RelManager->getRelation(new RelationIdentifier(180, 152));
        $translocations = $relation3->getChildElements(ItemIdentifier::createByItem($item));
        foreach ($translocations as $translocation)
            foreach ($relation4->getChildElements($translocation) as $placement)
                $PlacementFactory->getItem($placement->getEntityId())->set('UF_CRM_16_1712204779', $item->get('UF_CRM_11_1711945370858'))->save();


        return $result;
    }
}

class IssuePushFields_2 extends Issue
{
    public function process(Item $item): Result
    {
        AddMessage2Log("\nIssuePushFields\n");

        $result = new Result();
        $RelManager = new RelationManager();
        $ProcurementFactory = Service\Container::getInstance()->getFactory(132);
        $relation1 = $RelManager->getRelation(new RelationIdentifier($item->getEntityTypeId(), 164));
        $relation2 = $RelManager->getRelation(new RelationIdentifier(132, 164));
        $subissues = $relation1->getChildElements(ItemIdentifier::createByItem($item));

        foreach ($subissues as $subissue)
            foreach ($relation2->getParentElements($subissue) as $procurement) {
                $ProcurementFactory->getItem($procurement->getEntityId())
                    ->set('ASSIGNED_BY_ID', $item->get('UF_CRM_11_1712656281') )
                    ->save();
            }

        return $result;
    }
}