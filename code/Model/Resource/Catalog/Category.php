<?php

class Aoe_GoogleSitemap_Model_Resource_Catalog_Category extends Mage_Sitemap_Model_Resource_Catalog_Category {

    /**
     * Returns all store views within the same website excluding the given one
     */
    protected function _getAllWebsiteStoresExcept($excludedStore) {
        $stores = array();
        $_stores = Mage::app()->getStores(false, false);
        foreach ($_stores as $_store) {
            if ($_store->getId() != $excludedStore->getId()) {
                $stores[] = $_store->getId();
            }
        }
        return $stores;
    }

    /**
     * Prepare a database query and fetch data containing language alternate
     * links for the store views different than given one
     */
    public function getAlternateLinksCollection($store) {

        $urConditions = array(
            'e.entity_id=ur.category_id',
            'ur.product_id IS NULL',
            $this->_getWriteAdapter()->quoteInto('ur.store_id IN (?)', $this->_getAllWebsiteStoresExcept($store)),
            $this->_getWriteAdapter()->quoteInto('ur.is_system=?', 1),
        );

        $excludeAttribute = $this->_getAttributeModel('sitemap_exclude');
        $activeAttribute = $this->_getAttributeModel('is_active');

        $this->_select = $this->_getWriteAdapter()->select()
            ->from(array('e' => $this->getMainTable()), array($this->getIdFieldName()))
            ->join(
                array('ur' => $this->getTable('core/url_rewrite')),
                join(' AND ', $urConditions),
                array('url' => 'request_path', 'store_id' => 'store_id')
            )
            ->join(
                array('excl' => $excludeAttribute['table']),
                'e.entity_id=excl.entity_id AND excl.store_id=0',
                array('global_excluded' => 'value')
            )
            ->where('excl.attribute_id=?', $excludeAttribute['attribute_id'])
            ->joinLeft(
                array('excl2' => $excludeAttribute['table']),
                'excl.entity_id = excl2.entity_id AND excl.attribute_id = excl2.attribute_id AND excl2.store_id = ur.store_id',
                array('excluded' => 'value')
            )
            ->join(
                array('act' => $activeAttribute['table']),
                'e.entity_id=act.entity_id AND act.store_id=0',
                array('global_is_active' => 'value')
            )
            ->where('act.attribute_id=?', $activeAttribute['attribute_id'])
            ->joinLeft(
                array('act2' => $activeAttribute['table']),
                'act.entity_id = act2.entity_id AND act.attribute_id = act2.attribute_id AND act2.store_id = ur.store_id',
                array('is_active' => 'value')
            );

        $this->_addFilter($store->getId(), 'is_active', 1);
        $this->_addFilter($store->getId(), 'sitemap_exclude', 0);

        $query = $this->_getWriteAdapter()->query($this->_select);

        $alternateLinks = array();

        while ($row = $query->fetch()) {
            // get rid of excluded categories
            if (null == $row['excluded'] && (int)$row['global_excluded'] != 0 || null != $row['excluded'] && (int)$row['excluded'] != 0) {
                continue;
            }

            // get rid of inactive categories
            if (null == $row['is_active'] && (int)$row['global_is_active'] == 0 || null != $row['is_active'] && (int)$row['is_active'] == 0) {
                continue;
            }

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
        $this->_addFilter($storeId, 'sitemap_exclude', 0);

        $query = $this->_getWriteAdapter()->query($this->_select);
        while ($row = $query->fetch()) {
            $category = $this->_prepareCategory($row);
            $categories[$category->getId()] = $category;
        }

        return $categories;
    }

    /**
     * Returns category attribute model
     */
    protected function _getAttributeModel($attributeCode) {
        if (!isset($this->_attributesCache[$attributeCode])) {
            $attribute = Mage::getSingleton('catalog/category')->getResource()->getAttribute($attributeCode);

            $this->_attributesCache[$attributeCode] = array(
                'entity_type_id'    => $attribute->getEntityTypeId(),
                'attribute_id'      => $attribute->getId(),
                'table'             => $attribute->getBackend()->getTable(),
                'is_global'         => $attribute->getIsGlobal() == Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
                'backend_type'      => $attribute->getBackendType()
            );
        }

        return $this->_attributesCache[$attributeCode];
    }

}
