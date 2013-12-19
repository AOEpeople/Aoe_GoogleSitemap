<?php

class Aoe_GoogleSitemap_Model_Resource_Catalog_Product extends Mage_Sitemap_Model_Resource_Catalog_Product {

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
            'e.entity_id=ur.product_id',
            'ur.category_id IS NULL',
            $this->_getWriteAdapter()->quoteInto('ur.store_id IN (?)', $this->_getAllWebsiteStoresExcept($store)),
            $this->_getWriteAdapter()->quoteInto('ur.is_system=?', 1),
        );

        $statusAttribute = $this->_getAttributeModel('status');
        $visibilityAttribute = $this->_getAttributeModel('visibility');

        $visibleInSiteIds = Mage::getSingleton('catalog/product_visibility')->getVisibleInSiteIds();
        $visibleStatusIds = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();

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
            )
            ->join(
                array('st' => $statusAttribute['table']),
                'e.entity_id=st.entity_id AND st.store_id=0',
                array('global_status' => 'value')
            )
            ->where('st.attribute_id=?', $statusAttribute['attribute_id'])
            ->joinLeft(
                array('st2' => $statusAttribute['table']),
                'st.entity_id = st2.entity_id AND st.attribute_id = st2.attribute_id AND st2.store_id = ur.store_id',
                array('status' => 'value')
            )
            ->join(
                array('vis' => $visibilityAttribute['table']),
                'e.entity_id=vis.entity_id AND vis.store_id=0',
                array('global_visibility' => 'value')
            )
            ->where('vis.attribute_id=?', $visibilityAttribute['attribute_id'])
            ->joinLeft(
                array('vis2' => $visibilityAttribute['table']),
                'vis.entity_id = vis2.entity_id AND vis.attribute_id = vis2.attribute_id AND vis2.store_id = ur.store_id',
                array('visibility' => 'value')
            );

        $this->_addFilter($store->getId(), 'visibility', $visibleInSiteIds, 'in');
        $this->_addFilter($store->getId(), 'status', $visibleStatusIds, 'in');

        $query = $this->_getWriteAdapter()->query($this->_select);

        $alternateLinks = array();

        while ($row = $query->fetch()) {
            // get rid of invisible products
            if (null == $row['status'] && !in_array((int)$row['global_status'], $visibleStatusIds) || null != $row['status'] && !in_array((int)$row['status'], $visibleStatusIds)) {
                continue;
            }

            // get rid of inactive products
            if (null == $row['visibility'] && !in_array((int)$row['global_visibility'], $visibleInSiteIds) || null != $row['visibility'] && !in_array((int)$row['visibility'], $visibleInSiteIds)) {
                continue;
            }

            $alternateLinks[$row[$this->getIdFieldName()]][$row['store_id']] = $row['url'];
        }

        return $alternateLinks;
    }

    /**
     * Returns product attribute model
     */
    protected function _getAttributeModel($attributeCode) {
        if (!isset($this->_attributesCache[$attributeCode])) {
            $attribute = Mage::getSingleton('catalog/product')->getResource()->getAttribute($attributeCode);

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
