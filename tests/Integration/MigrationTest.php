<?php

declare(strict_types=1);

namespace PayKit\Bundle\Tests\Integration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\SchemaTool;
use PayKit\Bundle\Migrations\Version20261016000000;
use PayKit\Bundle\Tests\OrmFactory;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    public function testMigrationMatchesEntityMapping(): void
    {
        $em = OrmFactory::create(false);
        $connection = $em->getConnection();

        $migrated = new Schema();
        Version20261016000000::createTables($migrated);
        foreach ($migrated->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }

        $diff = (new SchemaTool($em))->getUpdateSchemaSql($em->getMetadataFactory()->getAllMetadata());
        self::assertSame([], $diff, 'Entity mapping and migration diverged: '.implode("\n", $diff));
    }
}
