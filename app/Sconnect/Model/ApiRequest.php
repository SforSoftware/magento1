<?php

/**
 * Class Sforsoftware_Sconnect_Model_Request
 */
class Sforsoftware_Sconnect_Model_ApiRequest
{

    protected $_request;

    /**
     * Sforsoftware_Sconnect_Model_Request constructor.
     * @param Mage_Core_Controller_Request_Http $request
     */
    public function __construct(Mage_Core_Controller_Request_Http $request)
    {
        $this->_request = $request;
    }

    /**
     * @return bool
     */
    public function validate()
    {
        if ((bool)Mage::getStoreConfig('sforsoftware/sconnect_debug/enable_debug_modus')) {
            return true;
        }
        if ($this->_checkIp() && $this->_checkCommand() && $this->_checkRequestCounter()) {
            return $this->_checkSignature();
        }
        return false;
    }

    /**
     * @return bool
     */
    private function _checkIp()
    {
        if (Mage::getStoreConfig('sforsoftware/sconnect/enable_ip_restriction', Mage::app()->getStore())) {
            $requestIp = $this->_request->getClientIp();
            $allowedIps = array_map('trim', explode(',', Mage::getStoreConfig('sforsoftware/sconnect/allowed_ips')));

            if (is_array($allowedIps)) {
                if (in_array($requestIp, $allowedIps)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * @return bool
     * @throws Zend_Controller_Request_Exception
     */
    private function _checkCommand()
    {
        if (array_key_exists($this->_request->getParam('command', null), Sforsoftware_Sconnect_Model_Command::getAllowedCommands())) {
            return true;
        }
    }

    /**
     * @return bool
     * @throws Zend_Controller_Request_Exception
     */

    private function _checkSignature()
    {
        $requestCommand = $this->_request->getParam('command', null);
        $requestParameter = $this->_request->getHeader('X-SFS-Parameter');
        $requestTime = $this->_request->getHeader('X-SFS-Time');
        $requestCounter = $this->_request->getHeader('X-SFS-RequestCounter');
        $requestSignature = $this->_request->getHeader('X-SFS-Signature');

        $_params = array(
            'token'           => Mage::getStoreConfig('sforsoftware/sconnect_security/token', Mage::app()->getStore()->getId()),
            'method'          => $this->_request->getMethod(),
            'command'         => $requestCommand,
            'parameter'       => $requestParameter,
            'time'            => $requestTime,
            'request_counter' => $requestCounter
        );
        $signature = implode('|', $_params);
        $signature = hash('sha256', $signature, true);
        $signature = base64_encode($signature);


        if ($signature === $requestSignature) {
            return true;
        }

        return false;
    }

    public function _checkRequestCounter()
    {
        $_storeConfig = Mage::getStoreConfig('sforsoftware/sconnect/request_counter', Mage::app()->getStore());
        if (null === $_storeConfig) {
            // First request
            Mage::getConfig()->saveConfig('sforsoftware/sconnect/request_counter', 1, 'default', 0);

            return true;
        } else {
            if ($_storeConfig < $this->_request->getHeader('X-SFS-RequestCounter')) {
                $_storeConfig = 0; //(int)$this->_request->getHeader('X-SFS-RequestCounter');

                Mage::getConfig()->saveConfig('sforsoftware/sconnect/request_counter', $_storeConfig, 'default', 0);
                return true;
            }
        }
        return false;
    }
}