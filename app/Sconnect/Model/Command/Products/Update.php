<?php

class Sforsoftware_Sconnect_Model_Command_Products_Update extends Sforsoftware_Sconnect_Model_Command implements Sforsoftware_Sconnect_Model_Command_Interface
{
    private $_postData;
    private $_product;
    private $_stockItem;
    private $_responseData;

    public function __construct(Mage_Core_Controller_Request_Http $request, $pagination, $parameters)
    {
        parent::__construct($request, $pagination, $parameters);
        $this->_postData = json_decode(file_get_contents("php://input"));
        $this->_product = Mage::getModel('catalog/product');
    }

    public function processRequest()
    {
        if ($this->_postData) {
            if (property_exists($this->_postData, 'data')) {
                if (count($this->_postData)) {
                    foreach ($this->_postData->data as $_data) {
                        if ($_data->Artikelcode != '') {
                            try {
                                $_productId = $this->_product->getIdBySku($_data->Artikelcode);

                                if ($_productId) {
                                    $_product = $this->_product->load($_productId);
                                } else {
                                    $_product = $this->_product;
                                }

                                $_product = $this->_processProduct($_product, $_data);

                                try {
                                    $_product->save();

                                    $this->_responseData[] = array(
                                        'Artikelcode' => $_product->getSku(),
                                    );
                                    $_product = null;
                                } catch (Exception $e) {
                                }
                            } catch (Exception $e) {
                            }
                        }
                    }

                    if (((int)$this->getCurPage()) * (int)$this->getPageSize() >= (int)$this->_postData->aantal) {
                        $this->_reindex();
                    }

                    $this->setResponseCode(200);
                    $this->setResponseBody(json_encode(array('data' => $this->_responseData, 'aantal' => count($this->_responseData))));
                    return;
                }
            }
        }
        //$this->setResponseCode(403);
        $this->setResponseBody('');
    }

    private function _processProduct($_product, $_data)
    {
        // Set required product data
//        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $_product->setWebsiteIds(array(Mage::app()->getWebsite()->getId()));
        $_product->setStoreId(Mage::app()->getStore()->getId());
        $_product->setAttributeSetId($_product->getDefaultAttributeSetId());
        $_product->setSku($_data->Artikelcode);
        $_product->setStatus(($_data->Nonactief == true ? 0 : 1));
        $_product->setPrice($_data->Verkoopprijs);
        $_product->setCost($_data->Inkoopprijs);
        $_product->setTaxClassId($this->_getTaxClassIdBySnelstartTaxId($_data->BtwId));
        $_product->setUrlKey($_product->formatUrlKey($_product->getSku() . '-' . $_product->getName()));

        if (!$_product->isObjectNew()) {
            if (Mage::getStoreConfig('sforsoftware/sconnect_products/overwrite_product_name')) {
                $_product->setName($_data->Omschrijving);
            }

            if (Mage::getStoreConfig('sforsoftware/sconnect_products/overwrite_product_description')) {
                $_product->setDescription($_data->Omschrijving);
                $_product->setShortDescription($_data->Omschrijving);
            }

        } else {
            // New product
            $_product->setTypeId(Mage_Catalog_Model_Product_Type::DEFAULT_TYPE);
            $_product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
            $_product->setName($_data->Omschrijving);
            $_product->setDescription($_data->Omschrijving);
            $_product->setShortDescription($_data->Omschrijving);
        }

        // Check if manage stock
        $_stockData = array();

        if ($_data->Voorraadcontrole == true) {
            $_stockData = [
                'use_config_manage_stock' => 0,
                'manage_stock'            => 1,
                'qty'                     => (int)$_data->VoorraadWeb
            ];

            if ((int)$_data->VoorraadWeb > 0) {
                $_stockData['is_in_stock'] = 1;
            } else {
                $_stockData['is_in_stock'] = 0;
            }
        } else {
            $_stockData = array(
                'use_config_manage_stock' => 0,
                'manage_stock'            => 0,
                'is_in_stock'             => 1
            );
        }

        // Check minimal qtys
        if ((int)$_data->MinimumBestelaantal > 1) {
            $_stockData['use_config_min_sale_qty'] = 0;
            $_stockData['min_sale_qty'] = (int)$_data->MinimumBestelaantal;
        } else {
            $_stockData['use_config_min_sale_qty'] = 1;
            $_stockData['min_sale_qty'] = 1;
        }

        // Check qty increments
        if ((int)$_data->Besteleenheid > 1) {
            $_stockData['enable_qty_increments'] = 1;
            $_stockData['use_config_qty_increments'] = 0;
            $_stockData['use_config_enable_qty_inc'] = 0;
            $_stockData['qty_increments'] = (int)$_data->Besteleenheid;
        } else {
            $_stockData['enable_qty_increments'] = 0;
            $_stockData['use_config_qty_increments'] = 1;
            $_stockData['use_config_enable_qty_inc'] = 1;
            $_stockData['qty_increments'] = 1;
        }

        $_product->setStockData($_stockData);

        $this->_addImages($_product);

        $_eventData = new Varien_Object();
        $_eventData->setData('product', $_product);
        $_eventData->setData('postData', $_data);

        Mage::dispatchEvent('sconnect_product_update_before_save', array('event_data' => $_eventData));

        $_product = $_eventData->getData('product');

        return $_product;
    }

    private function _getTaxClassIdBySnelstartTaxId($_snelstartBtwId)
    {
        $_configPath = 'sforsoftware/sconnect_products/snelstart_tax_rate_' . (int)$_snelstartBtwId;
        $_configValue = Mage::getStoreConfig($_configPath, false);

        if (is_string($_configValue)) {
            return $_configValue;
        }

        // Return high tax rate as default value
        $_configPath = 'sforsoftware/sconnect_products/snelstart_tax_rate_2';
        return Mage::getStoreConfig($_configPath, false);

    }

    private function _addImages($_product)
    {

        // Check if import images
        if (!Mage::getStoreConfig('sforsoftware/sconnect_products/import_images')) {
            return $this;
        }

        // Search for image file
        $_importImgsDir = Mage::getBaseDir('media') . DS . 'import';

        chdir($_importImgsDir);

        $matches = glob(trim($_product->getSku()) . '*');

        if (is_array($matches) && count($matches) > 0) {
            sort($matches, SORT_NATURAL | SORT_FLAG_CASE);

            // Add filename with sku to beginning of array
            foreach ($matches as $key => $match) {
                $match = explode('.', $match);
                if (is_array($match) && count($match) == 2) {
                    if ($match[0] == trim($_product->getSku())) {
                        unset($matches[$key]);

                        array_unshift($matches, implode('.', $match));
                        break;
                    }
                }
            }

            // Image found, delete existing images
            if ($_product->isObjectNew() == false) {
                $mediaGalleryAttribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode($_product->getEntityTypeId(), 'media_gallery');

                $gallery = $_product->getMediaGalleryImages();

                if ($gallery->count()) {
                    foreach ($gallery as $image) {
                        $mediaGalleryAttribute->getBackend()->removeImage($_product, $image->getFile());
                    }
                    $_product->save();
                }
            }

            $_product->setMediaGallery(array('images' => array(), 'values' => array()));

            $i = 0;
            foreach ($matches as $_match) {
                $_image = '/' . $_match;
                $_imagePath = $_importImgsDir . $_image;

                if($i == 0){
                    $_types = array('image', 'small_image', 'thumbnail');
                }else{
                    $_types = array();
                }
                $_product->addImageToMediaGallery($_imagePath, $_types, false, false);

                $i++;
            }

        }

        return $this;
    }

    private function _reindex()
    {
        $_indicesToReindex = array('catalog_product_attribute', 'catalog_product_price', 'catalog_product_flat', 'catalogsearch_fulltext', 'cataloginventory_stock');

        foreach ($_indicesToReindex as $_index) {
            $_process = Mage::getSingleton('index/indexer');
            $_process = $_process->getProcessByCode($_index);

            if ($_process) {
                try {
                    $_process->reindexAll();
                } catch (Exception $e) {
                }
            }
        }
    }
}