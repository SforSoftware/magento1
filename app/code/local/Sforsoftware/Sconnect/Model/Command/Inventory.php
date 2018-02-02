<?php

class Sforsoftware_Sconnect_Model_Command_Inventory extends Sforsoftware_Sconnect_Model_Command implements Sforsoftware_Sconnect_Model_Command_Interface{

    private $_productCollection;
    private $_products;

    public function __construct(Mage_Core_Controller_Request_Http $request, $pagination, $parameters = null)
    {
        parent::__construct($request, $pagination, $parameters);

        $this->_productCollection = Mage::getResourceModel('catalog/product_collection');
    }

    public function processRequest()
    {
        $this->_productCollection
                  //->addAttributeToSelect('*')
                  ->setOrder('Id', 'ASC')
                  ->setCurPage((int)$this->getCurPage())
                  ->setPageSize((int)$this->getPageSize())
                  ->load();

        Mage::getSingleton('cataloginventory/stock')->addItemsToProducts($this->_productCollection);

        if($this->_productCollection->count() == 0){
            $this->setResponseCode(200);
            $this->setResponseBody(json_encode(array('aantal' => 0, 'data' => array())));
            return $this;
        }else{
            $this->_processProducts();
            $this->setResponseCode(200);
            $this->setResponseBody(json_encode(array('aantal' => $this->_productCollection->count(), 'data' => $this->_products)));
        }
    }
    private function _processProducts(){
        foreach($this->_productCollection as $_product){
            $this->_products[] = array(
                'Artikelcode'       =>  $_product->getSku(),
                'Aantal'            =>  $_product->getStockItem()->getQty()
            );
        }
        return $this;
    }

}
