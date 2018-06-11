<?php

class Sforsoftware_Sconnect_Model_Mage_Core_Store extends Mage_Core_Model_Store{
    /**
     * Round price
     *
     * @param mixed $price
     * @return double
     */
    public function roundPrice($price)
    {
        return round($price, 4);
    }
}