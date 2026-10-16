<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

final class OrmFactory
{
    public static function create(bool $createSchema = true): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $em = new EntityManager($connection, $config);
        if ($createSchema) {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        }

        return $em;
    }
}
