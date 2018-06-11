<?php

class Sforsoftware_Sconnect_IndexController extends Mage_Core_Controller_Front_Action {
    private $_apirequest;

    public function preDispatch(){
        $this->getResponse()->setHeader('Content-type', 'application/json');

        $this->_apirequest = new Sforsoftware_Sconnect_Model_ApiRequest($this->getRequest());
    }

    public function indexAction(){
        
        $_command = $this->getRequest()->getParam('command', false);
        $_pagination = array();
        $_pagination['start']   			= $this->getRequest()->getParam('start', 0);
        $_pagination['aantal']  			= $this->getRequest()->getParam('aantal', 20);
		$_pagination['vanaf_datum_tijd'] 	= $this->getRequest()->getParam('vanaf_datum_tijd', 0);

        if ($this->_apirequest->validate() && $_command) {
            $_command_model = Sforsoftware_Sconnect_Model_Command::getCommandModel($_command);
            $_command_model = 'Sforsoftware_Sconnect_Model_Command_'.$_command_model;

            $_command = new $_command_model($this->getRequest(), $_pagination, null);
            $_command->processRequest();

            $this->getResponse()->setHeader('HTTP/1.0', $_command->getResponseCode(), true);
            $this->getResponse()->setBody($_command->getResponseBody());
            return;
        }

        $this->getResponse()->setHeader('HTTP/1.0','403',true);
    }
}

