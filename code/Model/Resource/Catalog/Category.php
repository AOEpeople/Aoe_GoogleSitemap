<?php
class Aoe_GoogleSitemap_Model_Resource_Catalog_Category extends Mage_Sitemap_Model_Resource_Catalog_Category
{
    public function getCollection($storeId)
    {
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
