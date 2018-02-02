<?php

/**
 * Class Sforsoftware_Sconnect_Model_Request
 */
class Sforsoftware_Sconnect_Model_Command{
    private $_allowed_commands;
    protected $_pagination;
    protected $responseCode;
    protected $responseBody;
    
    /**
     * Sforsoftware_Sconnect_Model_Request constructor.
     * @param Mage_Core_Controller_Request_Http $request
     */
    public function __construct(Mage_Core_Controller_Request_Http $request, $pagination = null, $parameters = null){
        $this->_request = $request;
        $this->_pagination = $pagination;
        $this->_parameters = $parameters;
    }
    public static function getAllowedCommands(){
        return $_allowed_commands = array(
            'koppeling_maken'    => 'Connect',
            'orders'             => 'Orders',
            'voorraad'           => 'Inventory',
            'voorraad_bijwerken' => 'Inventory_Update',
            'artikelen'          => 'Products',
            'artikelen_bijwerken'=> 'Products_Update',
        );
    }
    public static function getCommandModel($command){
        $_commands = self::getAllowedCommands();

        return $_commands[$command];
    }

    public function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    public function setResponseBody($responseBody)
    {
        $this->responseBody = $responseBody;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }
    public function getCurPage(){
        if($this->_pagination['start'] == 0){
            $this->_pagination['start'] = 1;
        }
        return $this->_pagination['start'];
    }
    public function getPageSize(){
        return $this->_pagination['aantal'];
    }
}