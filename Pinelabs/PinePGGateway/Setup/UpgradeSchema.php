<?php

namespace Pinelabs\PinePGGateway\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();
        $tableName = $setup->getTable('sales_order');

        // Add 'plural_order_id' column if not exists
        if ($connection->isTableExists($tableName) && !$connection->tableColumnExists($tableName, 'plural_order_id')) {
            $connection->addColumn(
                $tableName,
                'plural_order_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Plural Order ID'
                ]
            );
        }

        // Add 'plural_refund_id' column if not exists
        if ($connection->isTableExists($tableName) && !$connection->tableColumnExists($tableName, 'plural_refund_id')) {
            $connection->addColumn(
                $tableName,
                'plural_refund_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Plural Refund ID'
                ]
            );
        }

        $setup->endSetup();
    }
}
