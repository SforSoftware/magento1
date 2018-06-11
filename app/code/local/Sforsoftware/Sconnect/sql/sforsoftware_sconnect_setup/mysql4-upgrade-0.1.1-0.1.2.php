<?php

$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
            ->changeColumn(
                $installer->getTable('sforsoftware_sconnect/export_order'),
                'created_at',
                'created_at',
                Varien_Db_Ddl_Table::TYPE_INTEGER
            );

// Change values to fixed value
$_export_order_collection = Mage::getModel('sforsoftware_sconnect/export_order')->getCollection();
if($_export_order_collection->count()){
    foreach($_export_order_collection as $_export_order){
        $_export_order->setCreatedAt('1480546800');
        $_export_order->save();
    }
}
$installer->endSetup();