<?php

class Sforsoftware_Sconnect_Model_Command_Connect extends Sforsoftware_Sconnect_Model_Command implements Sforsoftware_Sconnect_Model_Command_Interface{

    public function __construct(Mage_Core_Controller_Request_Http $request, $pagination = null, $parameters = null)
    {
        parent::__construct($request, $pagination, $parameters);
    }

    public function processRequest()
    {
        $_response = array();
        $_response['api_informatie'] = array(
            'naam'          =>      'SConnect.API.Magento',
            'omschrijving'  =>      'Order en voorraad plugin',
            'bedrijf'       =>      'GSTALT',
            'copyright'     =>      'Copyright (C) 2016 - GSTALT',
            'version'       =>      Sforsoftware_Sconnect_Model_Version::VERSION
        );
        $_response['api_configuratie'] = array(
            'methoden'              =>      implode('|', array_keys(Sforsoftware_Sconnect_Model_Command::getAllowedCommands())),
            'voorraad_bijhouden'    =>      Mage::getStoreConfig('cataloginventory/item_options/manage_stock', Mage::app()->getSTore()),
            'voorraad_decimalen'    =>      0 // Magento default
        );

        $this->setResponseBody(json_encode($_response));
        $this->setResponseCode(200);
        
        return $this;
    }
    
}