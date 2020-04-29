<?php

namespace Bitqit\Searchtap\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $tableName = $setup->getTable('searchtap_queue');
        $tableName2= $setup->getTable('searchtap_config');

        if ($setup->getConnection()->isTableExists($tableName) != true && $setup->getConnection()->isTableExists($tableName2) != true)
        {
            $table = $setup->getConnection()
                ->newTable($setup->getTable('searchtap_queue'))
                ->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => false
                    ],
                    'Entity ID'
                )
                ->addColumn(
                    'action',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    100,
                    [
                        'nullable' => false
                    ],
                    'Action'
                )
                ->addColumn(
                    'status',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    100,
                    [
                        'nullable' => false
                    ],
                    'Status'
                )
                ->addColumn(
                    'type',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    100,
                    [
                        'nullable' => false
                    ],
                    'Type'
                )
                ->addColumn(
                    'store_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    100,
                    [
                        'nullable' => true
                    ],
                    'Store IDs'
                )->setComment("SearchTap Queue Table");


            $table2 = $setup->getConnection()
                ->newTable($setup->getTable('searchtap_config'))
                ->addColumn(
                    'id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'unsigned' => true,
                        'nullable' => false,
                        'primary' => true
                    ],
                    'ID'
                )
                ->addColumn(
                    'title',
                    Table::TYPE_TEXT,
                    null,
                    ['nullable' => false, 'default' => ''],
                    'Title'
                )->setComment("SearchTap Config Table");


            $setup->getConnection()->createTable($table);
            $setup->getConnection()->createTable($table2);
        }

        $setup->endSetup();
    }
}
