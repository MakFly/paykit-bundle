<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/** Orders of the e-commerce module. Billing tables come from the bundle's own migrations. */
final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the shop_order table of the e-commerce demo module';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('shop_order');
        $table->addColumn('id', Types::STRING, ['length' => 32]);
        $table->addColumn('customer_id', Types::STRING, ['length' => 191]);
        $table->addColumn('quote', Types::JSON);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['customer_id', 'created_at'], 'shop_order_customer_idx');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('shop_order');
    }
}
