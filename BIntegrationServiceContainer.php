<?php

namespace BIntegration\Service;

use Bitrix\Crm\Service;
use Bitrix\Main\Loader;
use Bitrix\Main\DI;
//use BIntegration\Service\Factory\Translocation;
//use BIntegration\Service\Factory\Order;
use BIntegration\Service\Factory\Translocation\TranslocationFactory;
use BIntegration\Service\Factory\Order\OrderFactory;
use BIntegration\Service\Factory\Placement\PlacementFactory;
use BIntegration\Service\Factory\Issue\IssueFactory;

if (!Loader::includeModule('crm')) {
    return;
}

define('SP_TRANSLOCATION_ID', 180);
define('SP_ORDER_ID', 140);
define('SP_PLACEMENT_ID', 152);
define('SP_ISSUE_ID', 141);

//Loader::registerNamespace(
//    "Factory",
//    Loader::getDocumentRoot() . "/local/php_interface/classes/factory"
//);
//Loader::registerNamespace(
//    "\\BIntegration\\Service\\Factory\\Order",
//    Loader::getDocumentRoot() . "/local/php_interface/classes/Order.php"
//);
Loader::registerAutoLoadClasses(
    null,
    [
        OrderFactory::class => '/local/php_interface/classes/Order.php',
        TranslocationFactory::class => '/local/php_interface/classes/Translocation.php',
        PlacementFactory::class => '/local/php_interface/classes/Placement.php',
        IssueFactory::class => '/local/php_interface/classes/Issue.php',
    ]
);

/*B-Integration custom class for overriding default Bitrix crm Factories*/
$BIntServiceContainerClass = new class extends Service\Container {
//    const implemented_factories = [
//        140 => RusirServiceFactory::class,
//    ];

    public function getFactory(int $entityTypeId): ?Service\Factory
    {
//        if (self::implemented_factories[$entityTypeId]) {
//            return new self::implemented_factories[$entityTypeId]($this->getTypeByEntityTypeId($entityTypeId));
//        } else {
//            return parent::getFactory($entityTypeId);
//        }
//        require_once Loader::getDocumentRoot() . "/local/php_interface/classes/Order.php";
//        require_once Loader::getDocumentRoot() . "/local/php_interface/classes/Translocation.php";

        if ($entityTypeId === SP_ORDER_ID )
            return new OrderFactory($this->getTypeByEntityTypeId($entityTypeId));
        else if ($entityTypeId === SP_TRANSLOCATION_ID )
            return new TranslocationFactory($this->getTypeByEntityTypeId($entityTypeId));
        else if ($entityTypeId === SP_PLACEMENT_ID )
            return new PlacementFactory($this->getTypeByEntityTypeId($entityTypeId));
        else if ($entityTypeId === SP_ISSUE_ID )
            return new IssueFactory($this->getTypeByEntityTypeId($entityTypeId));
        else
            return parent::getFactory($entityTypeId);

    }
};

DI\ServiceLocator::getInstance()->addInstance('crm.service.container', $BIntServiceContainerClass);


