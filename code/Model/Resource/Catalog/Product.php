<?php

class Aoe_GoogleSitemap_Model_Resource_Catalog_Product extends Mage_Sitemap_Model_Resource_Catalog_Product {

    /**
     * Returns all store views within the same website excluding the given one
     *
     * @author Benoît Xylo
     */
    protected function _getAllWebsiteStoresExcept($excludedStore) {
        $stores = array();
        $_stores = Mage::app()->getStores(false, false);
        foreach ($_stores as $_store) {
            if ($_store->getId() != $excludedStore->getId() && $_store->getWebsiteId() == $excludedStore->getWebsiteId()) {
                $stores[] = $_store->getId();
            }
        }
        return $stores;
    }

    /**
     * Prepare a database query and fetch data containing language alternate
     * links for the store views different than given one
     *
     * @author Benoît Xylo
     */
    public function getAlternateLinksCollection($store) {

        $urConditions = array(
            'e.entity_id=ur.product_id',
            'ur.category_id IS NULL',
            $this->_getWriteAdapter()->quoteInto('ur.store_id IN (?)', $this->_getAllWebsiteStoresExcept($store)),
            $this->_getWriteAdapter()->quoteInto('ur.is_system=?', 1),
        );
        $this->_select = $this->_getWriteAdapter()->select()
            ->from(array('e' => $this->getMainTable()), array($this->getIdFieldName()))
            ->join(
                array('w' => $this->getTable('catalog/product_website')),
                'e.entity_id=w.product_id',
                array()
            )
            ->where('w.website_id=?', $store->getWebsiteId())
            ->joinLeft(
                array('ur' => $this->getTable('core/url_rewrite')),
                join(' AND ', $urConditions),
                array('url' => 'request_path', 'store_id' => 'store_id')
            );

        $this->_addFilter($storeId, 'visibility', Mage::getSingleton('catalog/product_visibility')->getVisibleInSiteIds(), 'in');
        $this->_addFilter($storeId, 'status', Mage::getSingleton('catalog/product_status')->getVisibleStatusIds(), 'in');

        $query = $this->_getWriteAdapter()->query($this->_select);

        $alternateLinks = array();

        while ($row = $query->fetch()) {
            $alternateLinks[$row[$this->getIdFieldName()]][$row['store_id']] = $row['url'];
        }

        return $alternateLinks;

    }

}
