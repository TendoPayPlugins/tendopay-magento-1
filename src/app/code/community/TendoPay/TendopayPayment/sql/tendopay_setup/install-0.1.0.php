<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$table = $installer->getTable('sales/quote_payment');
$installer->getConnection()->addColumn($table, 'tendopay_token', "varchar(255) DEFAULT NULL COMMENT 'TendoPay Order Token'");
$installer->getConnection()->addColumn($table, 'tendopay_order_id', "varchar(255) DEFAULT NULL COMMENT 'TendoPay Order ID'");

$table = $installer->getTable('sales_flat_order_payment');
$installer->getConnection()->addColumn($table, "tendopay_token", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Hash'");
$installer->getConnection()->addColumn($table, "tendopay_order_id", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Order ID'");
$installer->getConnection()->addColumn($table, "tendopay_disposition", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Status'");
$installer->getConnection()->addColumn($table, "tendopay_verification_token", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Verification Token'");
$installer->getConnection()->addColumn($table, "tendopay_fetched_at", "TIMESTAMP NULL");

$table = $installer->getTable('sales_flat_order');
$installer->getConnection()->addColumn($table, "tendopay_token", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Hash'");
$installer->getConnection()->addColumn($table, "tendopay_order_id", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Order ID'");
$installer->getConnection()->addColumn($table, "tendopay_disposition", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Status'");
$installer->getConnection()->addColumn($table, "tendopay_verification_token", "varchar(255) DEFAULT NULL COMMENT 'TendoPay Verification Token'");
$installer->getConnection()->addColumn($table, "tendopay_fetched_at", "TIMESTAMP NULL");

// add new status and map it to Payment Review state
$status = 'tendopay_payment_review';
$state  = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;
$installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('{$status}', 'TendoPay Processing');");
$installer->run("INSERT INTO `{$this->getTable('sales_order_status_state')}` (`status`, `state`, `is_default`) VALUES ('{$status}', '{$state}', '0');");

$installer->endSetup();
