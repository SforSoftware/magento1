<?php

    class Sforsoftware_Sconnect_Model_Command_Orders extends Sforsoftware_Sconnect_Model_Command implements Sforsoftware_Sconnect_Model_Command_Interface
    {
        private $_orders;
        private $_products;
        private $_exportOrdersCollection;
        private $_timestamp;

        public function __construct(Mage_Core_Controller_Request_Http $request, $pagination = null, $timestamp) {
            parent::__construct($request, $pagination, $timestamp);
            $this->_timestamp              = $pagination['vanaf_datum_tijd'];
            $this->_exportOrdersCollection = Mage::getModel('sforsoftware_sconnect/export_order')->getCollection();

        }

        public function processRequest() {
            // Get orderIds from collection
            $this->_exportOrdersCollection->addFieldToFilter(
                'created_at', array(
                    array('gteq' => $this->_timestamp),
                )
            )->setPageSize((int)$this->_pagination['aantal'])->setCurPage((int)$this->_pagination['start']);

            if ($this->_exportOrdersCollection->count() == 0 || $this->_timestamp == 0) {
                $this->setResponseCode(200);
                $this->setResponseBody(json_encode(array('aantal' => 0, 'data' => array())));

                return $this;
            } else {
                $this->_processOrders();
                $this->setResponseCode(200);
                $this->setResponseBody(json_encode(array('data' => $this->_orders)));
            }

            return $this;
        }

        private function _processOrders() {
            foreach ($this->_exportOrdersCollection as $_exportItem) {
                $_order = Mage::getModel('sales/order')->load($_exportItem->getOrderId());

                if ($_order->getId() === null) {
                    Mage::log('SConnect order: skipping order ' . $_exportItem->getOrderId());
                    continue;
                }

                $_data = array(
                    'Betalingskenmerk'   => $_order->getIncrementId(),
                    //'Sjabloon'              =>  '',
                    'Datum'              => date('Y-m-d', $_order->getCreatedAtDate()->getTimestamp()),
                    'Kortingspercentage' => $_order->getDiscountAmount(),
                    'Omschrijving'       => html_entity_decode(Mage::getStoreConfig('sforsoftware/sconnect_orders/description', $_order->getStore()->getId())),
                    'Ordermemo'          => '',
                    'VerkoperLoginnaam'  => '',
                    'Kostenplaatsnummer' => '',
                    'SoortPrijzen'       => $this->_getMagentoPriceConfig(),
                    'Klantcode'          => $_order->getCustomerEmail(),
                    'Klant'              => array(
                        'VerzendAdres'    => array(
                            'Naam'           => ($_order->getShippingAddress() != null ? $_order->getShippingAddress()->getCompany() : $_order->getBillingAddress()->getCompany()),
                            'Contactpersoon' => ($_order->getShippingAddress() != null ? $_order->getShippingAddress()->getName() : $_order->getBillingAddress()->getName()),
                            'Straat'         => ($_order->getShippingAddress() != null ? implode(' ', $_order->getShippingAddress()->getStreet()) : implode(
                                ' ', $_order->getBillingAddress()->getStreet()
                            )),
                            'Huisnummer'     => '',
                            'Postcode'       => ($_order->getShippingAddress() != null ? $_order->getShippingAddress()->getPostcode() : $_order->getBillingAddress()->getPostcode(
                            )),
                            'Plaats'         => ($_order->getShippingAddress() != null ? $_order->getShippingAddress()->getCity() : $_order->getBillingAddress()->getCity()),
                            'Landcode'       => ($_order->getShippingAddress() != null ? $_order->getShippingAddress()->getCountry() : $_order->getBillingAddress()->getCountry()),
                        ),
                        'FactuurAdres'    => array(
                            'Naam'           => $_order->getBillingAddress()->getCompany(),
                            'Contactpersoon' => $_order->getBillingAddress()->getName(),
                            'Straat'         => implode(' ', $_order->getBillingAddress()->getStreet()),
                            'Huisnummer'     => '',
                            'Postcode'       => $_order->getBillingAddress()->getPostcode(),
                            'Plaats'         => $_order->getBillingAddress()->getCity(),
                            'Landcode'       => $_order->getBillingAddress()->getCountry(),
                        ),
                        'Telefoon'        => $_order->getBillingAddress()->getTelephone(),
                        'MobieleTelefoon' => '',
                        'Fax'             => '',
                        'Emailadres'      => $_order->getBillingAddress()->getEmail(),
                        'Website'         => '',
                        'Memo'            => '',
                        'BtwNummer'       => $_order->getCustomerTaxvat(),
                        'KvkNummer'       => '',
                    ),
                    //'Abonnement'            =>  array(
                    //    'IntervalSoort'         =>  '',
                    //    'IntervalAantal'        =>  '',
                    //    'TijdstipMaand'         =>  '',
                    //    'TijdstipDagInWeek'     =>  '',
                    //    'TijdstipDagInMaand'    =>  '',
                    //    'TijdstipWeekInMaand'   =>  '',
                    //    'Begindatum'            =>  '',
                    //    'Einddatum'             =>  '',
                    //    'EindeNaAantal'         =>  ''
                    //),
                    'Regels'             => $this->_processOrderProducts($_order),
                );


                $_eventData = new Varien_Object();
                $_eventData->setData('order', $_data);
                $_eventData->setData('order_data', $_order);

                Mage::dispatchEvent('sforsoftware_sconnect_order_after', array('event_data' => $_eventData));

                $this->_orders[] = $_eventData->getData('order');

                //$_exportItem->setExportedAt(date('Y-m-d H:i:s'));
                //$_exportItem->save();
            }

            return $this;
        }

        private function _processOrderProducts($_order) {
            $this->_products = array(); // Reset products array
            $_storeId        = $_order->getStore()->getId();

            foreach ($_order->getAllItems() as $_item) {
                $_productOptions = $_item->getProductOptions();

                $_options = array();
                if ($_item->getProductType() == 'configurable') {

                    if (array_key_exists('attributes_info', $_productOptions) && count($_productOptions['attributes_info']) > 0) {
                        foreach ($_productOptions['attributes_info'] as $_option) {
                            $_options[] = array(
                                'Label'  => html_entity_decode($_option['label']),
                                'Waarde' => html_entity_decode($_option['value']),
                            );
                        }
                    }

                    if (array_key_exists('simple_sku', $_productOptions)) {
                        $_product = Mage::getModel('catalog/product')->setStoreId($_storeId)->loadByAttribute('sku', $_productOptions['simple_sku']);
                    } else {
                        continue;
                    }

                    $this->_processOrderProduct($_item, $_product, $_options, $_order);

                } elseif ($_item->getProductType() == 'bundle') {
                    $this->_products[] = [
                        'Type'         => 'Tekst',
                        'Omschrijving' => $_item->getName() . ' - ' . (int)$_item->getQtyOrdered() . ' stuks',
                    ];
                    continue;
                } else {

                    if ($_parentItem = $_item->getParentItem()) {
                        if ($_parentItem->getProductType() == 'configurable') {
                            // Skip simple products for parent configurable
                            continue;
                        }
                    }
                    $_product = Mage::getModel('catalog/product')->setStoreId($_storeId)->load($_item->getProductId());

                    if (array_key_exists('options', $_productOptions) && count($_productOptions['options']) > 0) {
                        foreach ($_productOptions['options'] as $_option) {
                            $_options[] = array(
                                'Label'  => html_entity_decode($_option['label']),
                                'Waarde' => html_entity_decode($_option['value']),
                            );
                        }
                    }

                    $this->_processOrderProduct($_item, $_product, $_options, $_order);
                }

                if (trim($_item->getSku()) == '') {
                    Mage::log('Sconnect order: order width Id ' . $_order->getId() . ' has a product width invalid SKU');
                }
            }

            $this->_addShippingCosts($_order);
            $this->_addDiscount($_order);
            $this->_addGrandtotal($_order);

            $_eventData = new Varien_Object();
            $_eventData->setData('order', $_order);
            $_eventData->setData('products', $this->_products);

            Mage::dispatchEvent('sforsoftware_sconnect_order_totals_after', array('event_data' => $_eventData));

            $this->_products = $_eventData->getData('products');

            return $this->_products;
        }

        private function _processOrderProduct($_item, $_product, $_options = array(), $_order) {

            $_productData = array(
                'Type'              => 'Artikel',
                'Artikelcode'       => ($_product != false ? $_product->getSku() : (trim($_item->getSku()) != '' ? $_item->getSku() : 'NIET GEVONDEN')),
                'Artikel'           => array(
                    'Omschrijving'          => ($_item->getProductType() != 'configurable' ? $_item->getName() : ($_product != false ? $_product->getName() : $_item->getName())),
                    'SoortPrijzen'          => $this->_getMagentoPriceConfig(),
                    'Verkoopprijs'          => ($_product != false ? $_product->getPrice() : $_item->getPrice()),  // Set original price, later on we process discount
                    'Inkoopprijs'           => ($_product != false ? $_product->getCost() : $_item->getCost()),
                    'MaxKortingspercentage' => '',
                    'Omzetgroepnummer'      => '',
                    'BtwSoort'              => ($_product != false ? $this->_getSnelstartTaxClassId($_product->getData('tax_class_id')) : null),
                    'BtwPercentage'         => $_item->getTaxPercent(),
                    'Kortingsgroepnummer'   => '',
                    'Eenheid'               => '',
                    'Leveranciercode'       => '',
                    'Voorraadcontrole'      => ($_product != false ? (bool)$_product->getStockItem()->getManageStock() : false),
                    'MinimumVoorraad'       => '',
                    'GewensteVoorraad'      => '',
                ),
                'Omschrijving'      => ($_item->getProductType() != 'configurable' ? $_item->getName() : ($_product != false ? $_product->getName() : $_item->getName())),
                'Opties'            => $_options,
                'Aantal'            => $_item->getQtyOrdered(),
                'Verkoopprijs'      => ($this->_getMagentoPriceConfig() == 'InclusiefBtw' ? $_item->getPriceInclTax() : $_item->getPrice()),
                'BtwBedrag'         => ($_item->getTaxAmount() > 0 ? $_item->getTaxAmount() / $_item->getQtyOrdered() : 0),
                'KortingPercentage' => 0
                //($_product->getPrice() > 0 && $_item->getPrice() > 0 ? abs((($_item->getPrice() - $_product->getPrice()) / $_product->getPrice()) * 100) : 0)
            );

            $_eventData = new Varien_Object();
            $_eventData->setData('order', $_order);
            $_eventData->setData('order_item', $_item);
            $_eventData->setData('product', $_productData);

            Mage::dispatchEvent('sforsoftware_sconnect_order_product_after', array('event_data' => $_eventData));

            $this->_products[] = $_eventData->getData('product');

            return $this;
        }

        private function _addShippingCosts($_order) {
            if ($_order->getShippingAmount() > 0) {
                $_shippingTaxClassId = Mage::getStoreConfig('tax/classes/shipping_tax_class');

                $this->_products[] = array(
                    'Type'              => 'Verzendkosten',
                    'Artikelcode'       => '',
                    'Artikel'           => array(
                        'Omschrijving' => html_entity_decode($_order->getShippingDescription()),
                        'SoortPrijzen' => $this->_getMagentoPriceConfig(),
                        'Verkoopprijs' => $_order->getShippingAmount(),
                        'BtwSoort'     => $this->_getSnelstartTaxClassId($_shippingTaxClassId),
                    ),
                    'Omschrijving'      => $_order->getShippingDescription(),
                    'Aantal'            => 1,
                    'Verkoopprijs'      => $_order->getShippingAmount(),
                    'BtwBedrag'         => $_order->getShippingTaxAmount(),
                    'KortingPercentage' => 0,
                );
            }

            return $this;
        }

        private function _addGrandtotal($_order) {
            $this->_products[] = array(
                'Type'              => 'Totaal',
                'Verkoopprijs'      => $_order->getGrandTotal(),
                'BtwBedrag'         => $_order->getTaxAmount(),
                'KortingPercentage' => 0,
            );

            return $this;
        }

        private function _addDiscount($_order) {
            if ($_order->getDiscountAmount() < 0) {
                $this->_products[] = array(
                    'Type'              => 'Kortingsbedrag',
                    'Verkoopprijs'      => $_order->getDiscountAmount() + $_order->getShippingDiscountAmount(),
                    'BtwBedrag'         => '',
                    'KortingPercentage' => 0,
                );
            }

            return $this;
        }

        private function _getMagentoPriceConfig() {
            // Check catalog pricetypes
            $_catalogPricesWithTax  = Mage::getStoreConfig('tax/calculation/price_includes_tax', Mage::app()->getStore());
            $_shippingPricesWithTax = Mage::getStoreConfig('tax/calculation/shipping_includes_tax', Mage::app()->getStore());

            if ($_catalogPricesWithTax == $_shippingPricesWithTax && $_catalogPricesWithTax == 0) {
                return 'ExclusiefBtw';
            } elseif ($_catalogPricesWithTax == $_shippingPricesWithTax && $_catalogPricesWithTax == 1) {
                return 'InclusiefBtw';
            } else {
                return '';
            }
        }

        private function _getSnelstartTaxClassId($_magentoTaxClassId) {
            for ($i = 0; $i <= 2; $i++) {

                $_configPath  = 'sforsoftware/sconnect_products/snelstart_tax_rate_' . $i;
                $_configValue = Mage::getStoreConfig($_configPath);

                if ((int)$_configValue == (int)$_magentoTaxClassId) {
                    return $i;
                }
            }

            // Return high tax rate as default
            return 2;
        }

        private function _getShippingTaxGroup($_order) {
            if ($_order->getShippingAmount() > 0) {
                if ((int)$_order->getShippingTaxAmount() == 0 || (int)$_order->getShippingAmount() == 0) {
                    if (number_format(($_order->getShippingTaxAmount() / $_order->getShippingAmount() * 100), 4) == '21.0000') {
                        return 'Hoog';
                    } elseif (number_format(($_order->getShippingTaxAmount() / $_order->getShippingAmount() * 100), 4) == '6.0000') {
                        return 'Laag';
                    }
                }
            }

            return 'Geen';
        }
    }