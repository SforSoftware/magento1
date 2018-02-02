<?php

class Sforsoftware_Sconnect_Model_Command_Products extends Sforsoftware_Sconnect_Model_Command implements Sforsoftware_Sconnect_Model_Command_Interface{

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
            ->addAttributeToSelect('*')
            ->setOrder('Id', 'ASC')
            ->setCurPage($this->getCurPage())
            ->setPageSize($this->getPageSize());

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

            $_taxData = $this->_getProductTaxClassData($_product->getTaxClassId());
            $_stockData = Mage::getModel('cataloginventory/stock_item')->loadByProduct($_product);

            $this->_products[] = array(
                'Artikelcode'           =>  $_product->getSku(),
                'Omschrijving'          =>  $_product->getName(),
                'Eenheid'               =>  null,
                'Verkoopprijs'          =>  $_product->getPrice(),
                'Inkoopprijs'           =>  $_product->getCost(),
                'AutomatischePrijs'     =>  false,
                'BtwId'                 =>  $_taxData['BtwId'],
                'Btw'                   =>  $_taxData['Btw'],
                'BtwPercentage'         =>  $_taxData['BtwPercentage'],
                'Omzetgroepnummer'      =>  null,
                'Omzetgroep'            =>  null,
                'MaxKortingspercentage' =>  null,
                'Kortingsgroepnummer'   =>  null,
                'Kortingsgroep'         =>  null,
                'Leveranciercode'       =>  null,
                'Leverancier'           =>  null,
                'Voorraadcontrole'      =>  ($_stockData->getData('manage_stock') == 1 ? true : false),
                'MinimumVoorraad'       =>  null,
                'GewensteVoorraad'      =>  null,
                'Besteleenheid'         =>  ($_stockData->getData('enable_qty_increments') == 1 ? $_stockData->getData('qty_increments') : 1),
                'MinimumBestelaantal'   =>  ($_stockData->getData('use_config_min_sale_qty') == 0 ? $_stockData->getData('min_sale_qty') : 1),
            );
        }
        return $this;
    }
    private function _getProductTaxClassData($_taxClassId){
        $_model     = Mage::getSingleton('tax/calculation');
        $_request   = $_model->getRateRequest();
        $_request->setCountryId('NL');
        $_percent   = $_model->getRate($_request->setProductClassId($_taxClassId));

        if($_percent == 0){
            return array('BtwId' => 0, 'BtwPercentage' => $_percent, 'Btw' => 'geen');
        }elseif($_percent == 6){
            return array('BtwId' => 1, 'BtwPercentage' => $_percent, 'Btw' => 'laag');
        }elseif($_percent == 21){
            return array('BtwId' => 2, 'BtwPercentage' => $_percent, 'Btw' => 'hoog');
        }else{
            return array('BtwId' => 3, 'BtwPercentage' => $_percent, 'Btw' => 'overig');
        }
    }
}
