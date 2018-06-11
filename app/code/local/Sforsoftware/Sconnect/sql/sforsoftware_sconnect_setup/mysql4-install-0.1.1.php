<?php

$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
            ->newTable($installer->getTable('sforsoftware_sconnect/export_order'))
            ->addColumn('Id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
                    'identity'  => true,
                    'unsigned'  => true,
                    'nullable'  => false,
                    'primary'   => true,
                ), 'Id')
            ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
                    'nullable'  => false,
                ), 'Order Increment Id')
            ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
                    'nullable'  => false,
                ), 'Created at')
            ->addColumn('exported_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
                    'nullable'  => true,
                ), 'Exported at');

$installer->getConnection()->createTable($table);

$installer->endSetup();