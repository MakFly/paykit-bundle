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
        // Before PHP 8.4 (or with ORM < 3.4) Doctrine generates proxy classes via symfony/var-exporter
        // LazyGhost, which needs a proxy directory; on 8.4 native lazy objects are used instead.
        $proxyDir = sys_get_temp_dir().'/paykit-bundle-tests/proxies';
        $paths = [\dirname(__DIR__).'/src/Entity'];
        // createAttributeMetadataConfig() appeared in ORM 3.4; the older name is deprecated there.
        $config = method_exists(ORMSetup::class, 'createAttributeMetadataConfig')
            ? ORMSetup::createAttributeMetadataConfig($paths, true, $proxyDir)
            : ORMSetup::createAttributeMetadataConfiguration($paths, true, $proxyDir);
        if (\PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir($proxyDir);
            $config->setProxyNamespace('PayKitTestProxies');
            $config->setAutoGenerateProxyClasses(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $em = new EntityManager($connection, $config);
        if ($createSchema) {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        }

        return $em;
    }
}
