<?php

declare(strict_types=1);

namespace PayKit\Bundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the PayKit tables. Written with the portable DBAL schema API so it runs on
 * PostgreSQL, MySQL/MariaDB and SQLite. Registered automatically by the bundle when
 * DoctrineMigrationsBundle is installed.
 */
final class Version20261016000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PayKit: create billing tables (customers, products, subscriptions, entitlements, invoices, webhooks)';
    }

    public function up(Schema $schema): void
    {
        self::createTables($schema);
    }

    public function down(Schema $schema): void
    {
        foreach (['paykit_webhook_event', 'paykit_checkout_session', 'paykit_payment_method', 'paykit_invoice', 'paykit_entitlement', 'paykit_subscription', 'paykit_product_feature', 'paykit_product', 'paykit_feature', 'paykit_customer'] as $table) {
            $schema->dropTable($table);
        }
    }

    /** @internal also used by the test-suite to check the migration matches the entity mapping */
    public static function createTables(Schema $schema): void
    {
        $customer = $schema->createTable('paykit_customer');
        $customer->addColumn('id', Types::STRING, ['length' => 191]);
        $customer->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
        $customer->addColumn('name', Types::STRING, ['notnull' => false, 'length' => 255]);
        $customer->addColumn('metadata', Types::JSON, ['notnull' => false]);
        $customer->addColumn('provider_customer_id', Types::STRING, ['notnull' => false, 'length' => 255]);
        $customer->addColumn('deleted_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $customer->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $customer->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $customer->setPrimaryKey(['id']);
        $customer->addIndex(['provider_customer_id'], 'paykit_customer_provider_idx');

        $feature = $schema->createTable('paykit_feature');
        $feature->addColumn('id', Types::STRING, ['length' => 64]);
        $feature->addColumn('type', Types::STRING, ['length' => 16]);
        $feature->setPrimaryKey(['id']);

        $product = $schema->createTable('paykit_product');
        $product->addColumn('internal_id', Types::STRING, ['length' => 40]);
        $product->addColumn('plan_id', Types::STRING, ['length' => 64]);
        $product->addColumn('version', Types::INTEGER);
        $product->addColumn('name', Types::STRING, ['length' => 255]);
        $product->addColumn('plan_group', Types::STRING, ['length' => 64]);
        $product->addColumn('is_default', Types::BOOLEAN);
        $product->addColumn('price_amount', Types::INTEGER, ['notnull' => false]);
        $product->addColumn('price_currency', Types::STRING, ['notnull' => false, 'length' => 3]);
        $product->addColumn('price_interval', Types::STRING, ['notnull' => false, 'length' => 16]);
        $product->addColumn('trial_days', Types::INTEGER, ['notnull' => false]);
        $product->addColumn('hash', Types::STRING, ['length' => 64]);
        $product->addColumn('provider_product_id', Types::STRING, ['notnull' => false, 'length' => 255]);
        $product->addColumn('provider_price_id', Types::STRING, ['notnull' => false, 'length' => 255]);
        $product->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $product->setPrimaryKey(['internal_id']);
        $product->addIndex(['provider_price_id'], 'paykit_product_provider_price_idx');
        $product->addUniqueIndex(['plan_id', 'version'], 'paykit_product_plan_version_unique');

        $productFeature = $schema->createTable('paykit_product_feature');
        $productFeature->addColumn('feature_limit', Types::INTEGER, ['notnull' => false]);
        $productFeature->addColumn('reset_interval', Types::STRING, ['notnull' => false, 'length' => 16]);
        $productFeature->addColumn('product_internal_id', Types::STRING, ['length' => 40]);
        $productFeature->addColumn('feature_id', Types::STRING, ['length' => 64]);
        $productFeature->setPrimaryKey(['product_internal_id', 'feature_id']);
        $productFeature->addIndex(['product_internal_id'], 'IDX_1EE3EDA013F02AC7');
        $productFeature->addIndex(['feature_id'], 'IDX_1EE3EDA060E4B879');
        $productFeature->addForeignKeyConstraint('paykit_product', ['product_internal_id'], ['internal_id'], [], 'FK_1EE3EDA013F02AC7');
        $productFeature->addForeignKeyConstraint('paykit_feature', ['feature_id'], ['id'], [], 'FK_1EE3EDA060E4B879');

        $subscription = $schema->createTable('paykit_subscription');
        $subscription->addColumn('id', Types::STRING, ['length' => 40]);
        $subscription->addColumn('provider_subscription_id', Types::STRING, ['notnull' => false, 'length' => 255]);
        $subscription->addColumn('provider_schedule_id', Types::STRING, ['notnull' => false, 'length' => 255]);
        $subscription->addColumn('status', Types::STRING, ['length' => 32]);
        $subscription->addColumn('cancel_at_period_end', Types::BOOLEAN);
        $subscription->addColumn('started_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('trial_ends_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('current_period_start_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('current_period_end_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('canceled_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('ended_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('scheduled_plan_id', Types::STRING, ['notnull' => false, 'length' => 64]);
        $subscription->addColumn('quantity', Types::INTEGER);
        $subscription->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $subscription->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $subscription->addColumn('customer_id', Types::STRING, ['length' => 191]);
        $subscription->addColumn('product_internal_id', Types::STRING, ['length' => 40]);
        $subscription->setPrimaryKey(['id']);
        $subscription->addIndex(['customer_id', 'status', 'ended_at'], 'paykit_subscription_customer_status_idx');
        $subscription->addIndex(['provider_subscription_id'], 'paykit_subscription_provider_idx');
        $subscription->addIndex(['customer_id'], 'IDX_FDA1D4569395C3F3');
        $subscription->addIndex(['product_internal_id'], 'IDX_FDA1D45613F02AC7');
        $subscription->addForeignKeyConstraint('paykit_customer', ['customer_id'], ['id'], [], 'FK_FDA1D4569395C3F3');
        $subscription->addForeignKeyConstraint('paykit_product', ['product_internal_id'], ['internal_id'], [], 'FK_FDA1D45613F02AC7');

        $entitlement = $schema->createTable('paykit_entitlement');
        $entitlement->addColumn('id', Types::STRING, ['length' => 40]);
        $entitlement->addColumn('customer_id', Types::STRING, ['length' => 191]);
        $entitlement->addColumn('feature_id', Types::STRING, ['length' => 64]);
        $entitlement->addColumn('entitlement_limit', Types::INTEGER, ['notnull' => false]);
        $entitlement->addColumn('balance', Types::INTEGER, ['notnull' => false]);
        $entitlement->addColumn('reset_interval', Types::STRING, ['notnull' => false, 'length' => 16]);
        $entitlement->addColumn('next_reset_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $entitlement->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $entitlement->addColumn('subscription_id', Types::STRING, ['length' => 40]);
        $entitlement->setPrimaryKey(['id']);
        $entitlement->addIndex(['customer_id', 'feature_id'], 'paykit_entitlement_customer_feature_idx');
        $entitlement->addIndex(['subscription_id'], 'paykit_entitlement_subscription_idx');
        $entitlement->addForeignKeyConstraint('paykit_subscription', ['subscription_id'], ['id'], [], 'FK_430D0B599A1887DC');

        $invoice = $schema->createTable('paykit_invoice');
        $invoice->addColumn('id', Types::STRING, ['length' => 40]);
        $invoice->addColumn('subscription_id', Types::STRING, ['notnull' => false, 'length' => 40]);
        $invoice->addColumn('provider_invoice_id', Types::STRING, ['length' => 255]);
        $invoice->addColumn('status', Types::STRING, ['length' => 32]);
        $invoice->addColumn('amount', Types::INTEGER);
        $invoice->addColumn('currency', Types::STRING, ['length' => 3]);
        $invoice->addColumn('hosted_url', Types::TEXT, ['notnull' => false]);
        $invoice->addColumn('period_start_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $invoice->addColumn('period_end_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $invoice->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $invoice->addColumn('customer_id', Types::STRING, ['length' => 191]);
        $invoice->setPrimaryKey(['id']);
        $invoice->addIndex(['customer_id'], 'paykit_invoice_customer_idx');
        $invoice->addUniqueIndex(['provider_invoice_id'], 'paykit_invoice_provider_unique');
        $invoice->addForeignKeyConstraint('paykit_customer', ['customer_id'], ['id'], [], 'FK_2315F7739395C3F3');

        $paymentMethod = $schema->createTable('paykit_payment_method');
        $paymentMethod->addColumn('id', Types::STRING, ['length' => 40]);
        $paymentMethod->addColumn('provider_method_id', Types::STRING, ['length' => 255]);
        $paymentMethod->addColumn('type', Types::STRING, ['length' => 32]);
        $paymentMethod->addColumn('brand', Types::STRING, ['notnull' => false, 'length' => 32]);
        $paymentMethod->addColumn('last4', Types::STRING, ['notnull' => false, 'length' => 4]);
        $paymentMethod->addColumn('expiry_month', Types::INTEGER, ['notnull' => false]);
        $paymentMethod->addColumn('expiry_year', Types::INTEGER, ['notnull' => false]);
        $paymentMethod->addColumn('is_default', Types::BOOLEAN);
        $paymentMethod->addColumn('deleted_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $paymentMethod->addColumn('customer_id', Types::STRING, ['length' => 191]);
        $paymentMethod->setPrimaryKey(['id']);
        $paymentMethod->addUniqueIndex(['provider_method_id'], 'paykit_payment_method_provider_unique');
        $paymentMethod->addIndex(['customer_id'], 'IDX_F6F420139395C3F3');
        $paymentMethod->addForeignKeyConstraint('paykit_customer', ['customer_id'], ['id'], [], 'FK_F6F420139395C3F3');

        $checkoutSession = $schema->createTable('paykit_checkout_session');
        $checkoutSession->addColumn('id', Types::STRING, ['length' => 40]);
        $checkoutSession->addColumn('provider_session_id', Types::STRING, ['length' => 255]);
        $checkoutSession->addColumn('customer_id', Types::STRING, ['length' => 191]);
        $checkoutSession->addColumn('product_internal_id', Types::STRING, ['length' => 40]);
        $checkoutSession->addColumn('completed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $checkoutSession->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $checkoutSession->setPrimaryKey(['id']);
        $checkoutSession->addUniqueIndex(['provider_session_id'], 'paykit_checkout_provider_unique');

        $webhookEvent = $schema->createTable('paykit_webhook_event');
        $webhookEvent->addColumn('id', Types::STRING, ['length' => 40]);
        $webhookEvent->addColumn('provider_event_id', Types::STRING, ['length' => 255]);
        $webhookEvent->addColumn('type', Types::STRING, ['length' => 64]);
        $webhookEvent->addColumn('payload', Types::JSON);
        $webhookEvent->addColumn('status', Types::STRING, ['length' => 16]);
        $webhookEvent->addColumn('error', Types::TEXT, ['notnull' => false]);
        $webhookEvent->addColumn('attempts', Types::INTEGER);
        $webhookEvent->addColumn('received_at', Types::DATETIME_IMMUTABLE);
        $webhookEvent->addColumn('processed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $webhookEvent->setPrimaryKey(['id']);
        $webhookEvent->addIndex(['status'], 'paykit_webhook_event_status_idx');
        $webhookEvent->addUniqueIndex(['provider_event_id'], 'paykit_webhook_event_provider_unique');
    }
}
