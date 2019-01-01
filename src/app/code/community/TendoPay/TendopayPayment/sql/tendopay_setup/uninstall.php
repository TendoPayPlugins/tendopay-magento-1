<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$installer->run("
    ALTER TABLE `{$installer->getTable('sales_flat_order_payment')}`
    DROP COLUMN tendopay_token,
    DROP COLUMN tendopay_order_id,
    DROP COLUMN tendopay_disposition,
    DROP COLUMN tendopay_verification_token,
    DROP COLUMN tendopay_fetched_at;
");

$installer->run("
    ALTER TABLE `{$installer->getTable('sales_flat_order')}`
    DROP COLUMN tendopay_token,
    DROP COLUMN tendopay_order_id,
    DROP COLUMN tendopay_disposition,
    DROP COLUMN tendopay_verification_token,
    DROP COLUMN tendopay_fetched_at;
");

$installer->run("
    ALTER TABLE `{$installer->getTable('sales/quote_payment')}`
    DROP COLUMN tendopay_token,
    DROP COLUMN tendopay_order_id;
");

$installer->run("
    DELETE FROM `{$installer->getTable('sales_order_status_state')}` WHERE status='tendopay_payment_review';
");

$installer->run("
    DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE status='tendopay_payment_review';
");

$installer->run("
    DELETE FROM `{$installer->getTable('core_resource')}` WHERE code='tendopay_setup';
");

$installer->endSetup();
?>