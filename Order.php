<?php

namespace BIntegration\Service\Factory\Order;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use Bitrix\Crm\Item;
use Bitrix\Crm\Service;
use Bitrix\Crm\Service\Operation;
use Bitrix\Main\DI;
use Bitrix\Iblock\ElementTable;
class OrderFactory extends Service\Factory\Dynamic
{
    public function getAddOperation(Item $item, Service\Context $context = null): Operation\Add
    {
        $operation = parent::getAddOperation($item, $context);
        return $operation->addAction(
            Operation::ACTION_BEFORE_SAVE,
            new WarehouseOrderCreateAction()
        );
    }

    public function getUpdateOperation(Item $item, \Bitrix\Crm\Service\Context $context = null): Operation\Update
    {
        $updateOperation = parent::getUpdateOperation($item, $context);
        AddMessage2Log("Изменение стадии: ".print_r($item->toArray(), true));
        if($item->isChangedStageId() && str_contains($item->getStageId(), 'SUCCESS')) {
            return $updateOperation->addAction(
                Operation::ACTION_BEFORE_SAVE,
                new WarehouseOrderCommitAction()
            );
        }
        return $updateOperation;
    }
}

class WarehouseOrderCommitAction extends Operation\Action {

    private ?Service\Factory $IssueFactory;
    private int $WAREHOUSE_IBLOCK =  23;
    private int $WAREHOUSE_RESTS_IBLOCK =  24;
    private int $POSITION_PROPOSAL_IBLOCK =  20;
    function __construct()
    {
        parent::__construct();
        $this->IssueFactory = Service\Container::getInstance()->getFactory(141);
    }

    public function process(Item $item): Result
    {
        $result = new Result();
        $CIBlockEl = new \CIBlockElement();
        \AddMessage2Log(print_r($item->toArray(), true));
        $OriginalPosition = $this->getPosition($item->get('UF_CRM_12_POSITION'));

        if(!$OriginalPosition) {
            $result->addError((new \Bitrix\Main\Error("Позиция ID: {$item->get('UF_CRM_12_POSITION')} не существует")));
            return $result;
        }
        $PositionProposal = $this->getPositionSelectedProposal($OriginalPosition->fields['ID']);
        $Quantity = $item->get('UF_CRM_12_QUANTITY');
        $OriginalQuantity = $OriginalPosition->GetProperties()['QUANTITY']['VALUE'];
        $ProposalQuantity = $PositionProposal->GetProperties()['VNALICHII']['VALUE'];

        $Src = $item->get('UF_CRM_12_SRC_STORAGE');
        $NonEmptySource = (bool) $Src;
        $Dst = $item->get('UF_CRM_12_DST_STORAGE');
        $DstWH = ElementTable::getById($item->get('UF_CRM_12_DST_STORAGE'))->fetch();

        $filterDstWH = ['IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK, 'PROPERTY_POSITION' => $OriginalPosition->fields['ID'], 'PROPERTY_WAREHOUSE' => $Dst];
        $DstWHRests = \CIBlockElement::GetList([], $filterDstWH, false, false, ['PROPERTY_QUANTITY', 'ID'])->Fetch();


        /*Перемещаемое не больше, чем в -з-а-к-а-з-е- -> Закупе*/
//            if(!$this->changeQuantityAllowed($OriginalQuantity, $Quantity)) {
        if(!$this->changeQuantityAllowed($ProposalQuantity, $Quantity)) {
            $err = sprintf(
//                    "Перемещаемое кол-во (%d) позиций %s <b>больше</b> заказанного (%d)",
                "Перемещаемое кол-во (%d) позиций %s <b>больше</b> чем в закупе (%d)",
                $Quantity, $OriginalPosition->fields['NAME'], $ProposalQuantity
            );
            $result->addError((new \Bitrix\Main\Error($err)));
            return $result;
        }

        /*Склад Источник не указывается в случае [повторяемой] приемки товара
        * В этом случае баланс считается по остальным складам
        */
        if(!$NonEmptySource) {
            $otherWHRest = 0;
            $filterOthrWH = ['IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK, 'PROPERTY_POSITION' => $OriginalPosition->fields['ID']];
            $otherWarehuseRests = CIBlockElement::GetList([], $filterOthrWH, false, false, ['PROPERTY_QUANTITY', 'ID']);
            while($otherWarehouseProductRest = $otherWarehuseRests->Fetch()) {
                $otherWHRest += $otherWarehouseProductRest['PROPERTY_QUANTITY_VALUE'];
            }
//                if(!$this->changeQuantityAllowed($OriginalQuantity, $otherWHRest + $Quantity)) {
            if(!$this->changeQuantityAllowed($ProposalQuantity, $otherWHRest + $Quantity)) {
                $err = sprintf(
//                        "Перемещаемое кол-во (%d) + кол-во на других складах (%d) позиции %s на <b style='color:#ff6c62'>%s</b> превышает кол-во в заказе (%d)",
                    "Перемещаемое кол-во (%d) + кол-во на других складах (%d) позиции %s на <b style='color:#ff6c62'>%s</b> превышает кол-во в закупе (%d)",
                    $Quantity, $otherWHRest, $OriginalPosition->fields['NAME'], $DstWH['NAME'], $ProposalQuantity
                );
                $result->addError((new \Bitrix\Main\Error($err)));
                return $result;
            }
        } else {
            /*Отнимаем у склада Источника*/
            $SrcWH = ElementTable::getById($Src)->fetch();
            $filterSrcWH = ['IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK, 'PROPERTY_POSITION' => $OriginalPosition->fields['ID'], 'PROPERTY_WAREHOUSE' => $Src];
            $SrcWHRests = \CIBlockElement::GetList([], $filterSrcWH, false, false, ['PROPERTY_QUANTITY', 'ID'])->Fetch();

            if($this->changeQuantityAllowed($SrcWHRests['PROPERTY_QUANTITY_VALUE'], $Quantity)) {
                $newQuantity = $SrcWHRests['PROPERTY_QUANTITY_VALUE'] - $Quantity;
//                $updFields = [
//                    'IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK,
//                    'PROPERTY_VALUES' => [
//                        'WAREHOUSE' => $Src,
//                        'POSITION' => $OriginalPosition->fields['ID'],
//                        'QUANTITY' => $newQuantity,
//                    ]
//                ];
//                $res = $CIBlockEl->Update($SrcWHRests['ID'], $updFields);
//                if(!$res) {
//                    $result->addError((new \Bitrix\Main\Error("Ошибка обновления эл-та остатков склада: ".$CIBlockEl->LAST_ERROR)));
//                    return $result;
//                }
                $result->setData(['SRC_NEW_Q' => $newQuantity]);

            } else {
                $err = sprintf(
                    "Перемещаемое кол-во (%d) позиций %s на <b style='color:#ff6c62'>%s</b> больше имеющегося, чем на <b style='color:#ff6c62'>%s</b>",
                    $Quantity, $OriginalPosition->fields['NAME'], $DstWH['NAME'], $SrcWH['NAME']
                );
                $result->addError((new \Bitrix\Main\Error($err)));
                return $result;
            }
        }

        /*Прибавляем на складе назначения*/
        $newQuantity = $DstWHRests['PROPERTY_QUANTITY_VALUE'] + $Quantity;
//        $updFields = [
//            'IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK,
//            'PROPERTY_VALUES' => [
//                'WAREHOUSE' => $DstWH['ID'],
//                'POSITION' => $OriginalPosition->fields['ID'],
//                'QUANTITY' => $newQuantity,
//            ]
//        ];
//        $res = $CIBlockEl->Update($DstWHRests['ID'], $updFields);
//        if(!$res) {
//            $result->addError((new \Bitrix\Main\Error("Ошибка обновления эл-та остатков склада: ".$CIBlockEl->LAST_ERROR)));
//            return $result;
//        }
        $result->setData(array_merge($result->getData(), ['DST_NEW_Q' => $newQuantity]));

        if(!$result->isSuccess()) {
            AddMessage2Log("COMMIT ORDER ERROR: \n".implode("\n", $result->getErrorMessages()));
        }
        return $result;
    }

    private function changeQuantityAllowed($from, $amount) {
        return (float) $from - (float) $amount >= 0;
    }
    private function getPositionSelectedProposal($positionID) {
        $Position = $this->getPosition($positionID);
        $PositionProposalID = $Position->GetProperties()['OFFER']['VALUE'];
        return \CIBlockElement::GetByID($PositionProposalID)->GetNextElement();
    }

    /**
     * @param $id
     * @return _CIBElement|array
     */
    private function getPosition($id) {
        return \CIBlockElement::GetByID($id)->GetNextElement();
    }

}

class WarehouseOrderCreateAction extends Operation\Action {
    private $WAREHOUSE_IBLOCK =  23;
    private $WAREHOUSE_RESTS_IBLOCK =  24;

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function process(Item $item): Result
    {
        $result = new Result();
        if(!$item->get('UF_CRM_12_POSITION')) {
            $result->addError( new \Bitrix\Main\Error('Не заполнено поле Позиция') );
            return $result;
        }
        $OriginalPosition = ElementTable::getById($item->get('UF_CRM_12_POSITION'))->fetch();
        if(!$OriginalPosition) {
            $result->addError( new \Bitrix\Main\Error('Указанная позиция не найдена') );
            return $result;
        }
        $DestinationWarehouseID = $item->get('UF_CRM_12_DST_STORAGE');
        if(!$DestinationWarehouseID) {
            $result->addError( new \Bitrix\Main\Error('Не указан склад назначения') );
//                $result->addError( Bitrix\Crm\Field::getRequiredEmptyError('UF_CRM_12_DST_STORAGE'));
//
            return $result;
        }
        $DestinationWarehouse = ElementTable::getById($DestinationWarehouseID)->fetch();
        $filterDstWH = ['IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK, 'PROPERTY_WAREHOUSE' => $DestinationWarehouseID, 'PROPERTY_POSITION' => $OriginalPosition['ID']];
        $DestinationWarehouseRests = \CIBlockElement::GetList([], $filterDstWH)->Fetch();

        /*Если нет записей об остатках на Складе назначения, создадим её*/
        if(!$DestinationWarehouseRests) {
            $CIBlockEl = new \CIBlockElement();
            $addFields = [
                'NAME' => "[{$DestinationWarehouse['NAME']}] {$OriginalPosition['NAME']}",
                'IBLOCK_ID' => $this->WAREHOUSE_RESTS_IBLOCK,
                'PROPERTY_VALUES' => [
                    'WAREHOUSE' => $DestinationWarehouse['ID'],
                    'POSITION' => $OriginalPosition['ID'],
                    'QUANTITY' => 0
                ]
            ];
            $res = $CIBlockEl->Add($addFields);
            if(!$res) {
                $result->addError((new \Bitrix\Main\Error("Ошибка создания эл-та остатков склада: ".$CIBlockEl->LAST_ERROR)));
                return $result;
            }
        }

        $SourceWarehouseID = $item->get('UF_CRM_12_SRC_STORAGE');
        if($SourceWarehouseID && $SourceWarehouse = ElementTable::getById($SourceWarehouseID)->fetch()) {
            $item->setTitle("Перемещение {$SourceWarehouse['NAME']} -> {$DestinationWarehouse['NAME']}");
        } else {
            $item->setTitle("Приход на {$DestinationWarehouse['NAME']}");
            $fabric = Service\Container::getInstance()->getFactory($item->getEntityTypeId());
            /*
             * todo
             * Сделать проверку на непревышение числа заказанных включив уже оприодованные и непроведенные ордера
             *
             * */
        }

        if(!$result->isSuccess()) {
            AddMessage2Log("ADD ORDER ERROR: \n".implode("\n", $result->getErrorMessages()));
        }
        return $result;
    }
}