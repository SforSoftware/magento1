<?php

class Sforsoftware_Sconnect_Model_Command_Inventory_Update extends Sforsoftware_Sconnect_Model_Command implements Sforsoftware_Sconnect_Model_Command_Interface{
    private $_postData;
    private $_product;
    private $_stockItem;
    private $_responseData;

    public function __construct(Mage_Core_Controller_Request_Http $request, $pagination, $parameters)
    {
        parent::__construct($request, $pagination, $parameters);
        $this->_postData = json_decode(file_get_contents('php://input'));
        $this->_product   = Mage::getModel('catalog/product');
        $this->_stockItem = Mage::getModel('cataloginventory/stock_item');
    }
    public function processRequest()
    {
        if(property_exists($this->_postData, 'data')){
            if(count($this->_postData)){
                foreach($this->_postData->data as $_data){
                    $_product = $this->_product->loadByAttribute('sku', $_data->Artikelcode);

                    if($_product){
                        $_stockItem = $this->_stockItem->loadByProduct($_product);

                        $_stockItem->setQty($_data->Aantal);
                        $_stockItem->setIsInStock((int)($_data->Aantal > 0));
                        $_stockItem->save();

                        $this->_responseData[] = array(
                            'Artikelcode' => $_product->getSku(),
                            'Aantal'      => $_stockItem->getQty()
                        );
                        unset($_stockItem);
                        unset($_product);
                    }
                }

                if(((int)$this->getCurPage() + 1) * (int)$this->getPageSize() >= (int)$this->_postData->aantal){
                    $this->_reindex();
                }

                $this->setResponseCode(200);
                $this->setResponseBody(json_encode(array('data' => $this->_responseData)));
                return;
            }
        }
        $this->setResponseCode(200);
        $this->setResponseBody(json_encode(array('data' => '')));
    }
    private function _reindex(){
        $_indicesToReindex = array('cataloginventory_stock');
        $_process = Mage::getModel('index/indexer')->getProcessByCode('catalog_product_price');

        foreach($_indicesToReindex as $_index){
            $_process = $_process->getProcessByCode($_index);

            if($_process){
                try{
                    $_process->reindexAll();
                } catch (Exception $e){}
            }
        }
    }
}