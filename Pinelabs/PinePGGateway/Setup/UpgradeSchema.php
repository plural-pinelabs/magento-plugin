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

        if (version_compare($context->getVersion(), '2.3.1', '<')) {
            $connection = $setup->getConnection();
            $tableName = $setup->getTable('sales_order');

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
        }

        $setup->endSetup();
    }
}
