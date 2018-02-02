<?php

class Sforsoftware_Sconnect_Model_Resource_Export_Order extends Mage_Core_Model_Resource_Db_Abstract{
    public function _construct(){
        $this->_init('sforsoftware_sconnect/export_order', 'Id');
    }
}