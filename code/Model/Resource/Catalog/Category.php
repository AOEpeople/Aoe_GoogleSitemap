<?php

class Aoe_GoogleSitemap_Model_Resource_Catalog_Category extends Mage_Sitemap_Model_Resource_Catalog_Category {

    /**
     * Returns all store views within the same website excluding the given one
     *
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
     */
    public function getAlternateLinksCollection($store) {

        $urConditions = array(
            'e.entity_id=ur.category_id',
            'ur.product_id IS NULL',
            $this->_getWriteAdapter()->quoteInto('ur.store_id IN (?)', $this->_getAllWebsiteStoresExcept($store)),
            $this->_getWriteAdapter()->quoteInto('ur.is_system=?', 1),
        );
        $this->_select = $this->_getWriteAdapter()->select()
            ->from(array('e' => $this->getMainTable()), array($this->getIdFieldName()))
            ->join(
                array('ur' => $this->getTable('core/url_rewrite')),
                join(' AND ', $urConditions),
                array('url' => 'request_path', 'store_id' => 'store_id')
            );

        $this->_addFilter($store->getId(), 'is_active', 1);
        $this->_addFilter($store->getId(), 'sitemap_exclude', 0);

        $query = $this->_getWriteAdapter()->query($this->_select);

        $alternateLinks = array();

        while ($row = $query->fetch()) {
            $alternateLinks[$row[$this->getIdFieldName()]][$row['store_id']] = $row['url'];
        }

        return $alternateLinks;

    }

    public function getCollection($storeId) {
        $categories = array();

        $store = Mage::app()->getStore($storeId);
        /* @var $store Mage_Core_Model_Store */

        if (!$store) {
            return false;
        }

        $this->_select = $this->_getWriteAdapter()->select()
            ->from($this->getMainTable())
            ->where($this->getIdFieldName() . '=?', $store->getRootCategoryId());
        $categoryRow = $this->_getWriteAdapter()->fetchRow($this->_select);

        if (!$categoryRow) {
            return false;
        }

        $urConditions = array(
            'e.entity_id=ur.category_id',
            $this->_getWriteAdapter()->quoteInto('ur.store_id=?', $store->getId()),
            'ur.product_id IS NULL',
            $this->_getWriteAdapter()->quoteInto('ur.is_system=?', 1),
        );
        $this->_select = $this->_getWriteAdapter()->select()
            ->from(array('e' => $this->getMainTable()), array($this->getIdFieldName()))
            ->joinLeft(
                array('ur' => $this->getTable('core/url_rewrite')),
                join(' AND ', $urConditions),
                array('url'=>'request_path')
            )
            ->where('e.path LIKE ?', $categoryRow['path'] . '/%');

        $this->_addFilter($storeId, 'is_active', 1);
        //Added filtering for sitemap_exclude attribute - Martin.W
        $this->_addFilter($storeId, 'sitemap_exclude', 0);
        //---------------------------------------------
        $query = $this->_getWriteAdapter()->query($this->_select);
        while ($row = $query->fetch()) {
            $category = $this->_prepareCategory($row);
            $categories[$category->getId()] = $category;
        }

        return $categories;
    }
}
