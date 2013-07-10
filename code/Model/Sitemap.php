<?php

class Aoe_GoogleSitemap_Model_Sitemap extends Mage_Sitemap_Model_Sitemap {

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

    public function generateXml()
    {
        $result = parent::generateXml();
        $content = file_get_contents($this->getSitemapFilename());
        $this->saveSitemapToDb($this->getSitemapFilename(),$content);
        return $result;
    }
}