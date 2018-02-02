<?php

class Sforsoftware_Sconnect_Model_Resource_Export_Order_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract{
    protected function _construct(){
        $this->_init('sforsoftware_sconnect/export_order');
    }
}