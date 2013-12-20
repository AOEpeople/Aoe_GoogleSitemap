<?php

class Aoe_GoogleSitemap_Model_Sitemap extends Mage_Sitemap_Model_Sitemap {

    protected $_stores = null;

    /**
     * Get local and base URL for given store ID
     */
    protected function _getStoreData($storeId) {
        if (null === $this->_stores) {
            $this->_stores = array();
            $stores = Mage::app()->getStores(false, false);
            foreach ($stores as $store) {
                $this->_stores[$store->getId()] = new Varien_Object(array(
                    'locale' => str_replace('_', '-', Mage::getStoreConfig('general/locale/code', $store->getId())),
                    'base_url' => Mage::app()->getStore($store->getId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK)
                ));
            }
        }

        if (array_key_exists($storeId, $this->_stores)) return $this->_stores[$storeId];
        return new Varien_Object;
    }

    /**
     * Format alternate links from the links collection for given item (product or category)
     */
    protected function _getAlternateLinks($item, $alternateLinksCollection) {
        $xml = '';
        if (array_key_exists($item->getId(), $alternateLinksCollection)) {
            foreach ($alternateLinksCollection[$item->getId()] as $storeId => $itemUrl) {
                $xml .= sprintf('<xhtml:link rel="alternate" hreflang="%s" href="%s"/>',
                    Mage::getStoreConfig('sitemap/alternate_links/hreflang', $storeId),
                    htmlspecialchars($this->_getStoreData($storeId)->getBaseUrl() . $itemUrl)
                );
            }
        }

        $additionalLinks = trim(Mage::getStoreConfig('sitemap/alternate_links/additionallinks', $item->getStoreId()));
        if (!empty($additionalLinks)) {
            $xml .= $additionalLinks;
        }
        return $xml;
    }

    public function saveSitemapToDb($filename, $content) {
        try {
            $model = Mage::getModel('core/file_storage_database');
            $sitemapDbData = array(
                'filename'      => $filename,
                'content'       => $content,
                'update_time'   => Mage::getSingleton('core/date')->date(),
                'directory'     => "/sitemap"
            );
            $filePath = $sitemapDbData['directory'];
            $directory = Mage::getModel('core/file_storage_directory_database')->loadByPath($filePath);
            if (!$directory->getId()) {
                throw new Exception("Directory is empty or not exists");
            }
            $sitemapDbData['directory_id'] = $directory->getId();
            $model->_getResource()->saveFile($sitemapDbData);
        }
        catch(Exception $e){
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
    }

    /**
     * Protected "override" of parent's generateXml() method that includes
     * language alternate links in the sitemap feed
     */
    protected function _generateXml() {
        $io = new Varien_Io_File();
        $io->setAllowCreateFolders(true);
        $io->open(array('path' => $this->getPath()));

        if ($io->fileExists($this->getSitemapFilename()) && !$io->isWriteable($this->getSitemapFilename())) {
            Mage::throwException(Mage::helper('sitemap')->__('File "%s" cannot be saved. Please, make sure the directory "%s" is writeable by web server.', $this->getSitemapFilename(), $this->getPath()));
        }

        $io->streamOpen($this->getSitemapFilename());

        $io->streamWrite('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $io->streamWrite('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">');

        $storeId = $this->getStoreId();
        $date    = Mage::getSingleton('core/date')->gmtDate('Y-m-d');
        $baseUrl = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);

        /**
         * Generate categories sitemap
         */
        $changefreq = (string)Mage::getStoreConfig('sitemap/category/changefreq', $storeId);
        $priority   = (string)Mage::getStoreConfig('sitemap/category/priority', $storeId);
        $collection = Mage::getResourceModel('sitemap/catalog_category')->getCollection($storeId);
        $alternateLinksCollection = Mage::getResourceModel('sitemap/catalog_category')->getAlternateLinksCollection(Mage::app()->getStore($storeId));
        foreach ($collection as $item) {
            $xml = sprintf(
                '<url><loc>%s</loc>%s<lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%.1f</priority></url>',
                htmlspecialchars($baseUrl . $item->getUrl()),
                $this->_getAlternateLinks($item, $alternateLinksCollection),
                $date,
                $changefreq,
                $priority
            );
            $io->streamWrite($xml);
        }
        unset($collection);

        /**
         * Generate products sitemap
         */
        $changefreq = (string)Mage::getStoreConfig('sitemap/product/changefreq', $storeId);
        $priority   = (string)Mage::getStoreConfig('sitemap/product/priority', $storeId);
        $collection = Mage::getResourceModel('sitemap/catalog_product')->getCollection($storeId);
        $alternateLinksCollection = Mage::getResourceModel('sitemap/catalog_product')->getAlternateLinksCollection(Mage::app()->getStore($storeId));
        foreach ($collection as $item) {
            $xml = sprintf(
                '<url><loc>%s</loc>%s<lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%.1f</priority></url>',
                htmlspecialchars($baseUrl . $item->getUrl()),
                $this->_getAlternateLinks($item, $alternateLinksCollection),
                $date,
                $changefreq,
                $priority
            );
            $io->streamWrite($xml);
        }
        unset($collection);

        /**
         * Generate cms pages sitemap
         */
        $changefreq = (string)Mage::getStoreConfig('sitemap/page/changefreq', $storeId);
        $priority   = (string)Mage::getStoreConfig('sitemap/page/priority', $storeId);
        $collection = Mage::getResourceModel('sitemap/cms_page')->getCollection($storeId);
        foreach ($collection as $item) {
            $xml = sprintf(
                '<url><loc>%s</loc><lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%.1f</priority></url>',
                htmlspecialchars($baseUrl . $item->getUrl()),
                $date,
                $changefreq,
                $priority
            );
            $io->streamWrite($xml);
        }
        unset($collection);

        $io->streamWrite('</urlset>');
        $io->streamClose();

        $this->setSitemapTime(Mage::getSingleton('core/date')->gmtDate('Y-m-d H:i:s'));
        $this->save();

        return $this;
    }

    public function generateXml() {
        // Check if alternate links are enabled and use appropriate generating routine
        if (Mage::getStoreConfigFlag('sitemap/alternate_links/enabled', $this->getStoreId())) {
            // "overriden" method, includes alternate links
            $result = $this->_generateXml();
        } else {
            // original method, no alternate links
            $result = parent::generateXml();
        }
        $content = file_get_contents($this->getSitemapFilename());
        $this->saveSitemapToDb($this->getSitemapFilename(),$content);
        return $result;
    }

}
