<?php

class Sforsoftware_Sconnect_Model_Observer_Order{
    public function saveForExport(Varien_Event_Observer $observer){
        $_order = $observer->getOrder();

        if($_order instanceof Mage_Sales_Model_Order){
            if(in_array($_order->getStatus(), $this->getStatusesForExport())){
                $_exportedOrders = Mage::getModel('sforsoftware_sconnect/export_order')->getCollection();

                if($_exportedOrders->addFilter('order_id', $_order->getId())->load()->count() == 0){
                    $_exportOrder = new Sforsoftware_Sconnect_Model_Export_Order();
                    $_exportOrder->setOrderId($_order->getId())
                        ->setCreatedAt(strtotime('now'))
                        ->save();
                }
                return;
            }
        }
    }

    private function getStatusesForExport(){
        $_statuses = Mage::getStoreConfig('sforsoftware/sconnect_orders/order_statuses', Mage::app()->getStore());

        return explode(',', $_statuses);
    }
}