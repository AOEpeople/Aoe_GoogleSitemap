<?php
class Aoe_GoogleSitemap_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        try {
            $databaseFileStorage = Mage::getModel('core/file_storage_database');
            $filename = $this->getRequest()->getParam('file');
            $databaseFileStorage->loadByFilename('/sitemap/' . $filename);
            if ($databaseFileStorage->getId()) {
                $directory = 'var/tmp/';
                if (!is_dir($directory)) {
                    mkdir($directory, 0775, true);
                }

                $fp = fopen($directory.$filename, 'w');
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    ftruncate($fp, 0);
                    fwrite($fp, $databaseFileStorage->getContent());
                }
                flock($fp, LOCK_UN);
                fclose($fp);
                $this->sendFile($directory.$filename);
            } else {
                header('HTTP/1.0 404 Not Found');
            }
            exit;
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    function sendFile($file) {
        if (file_exists($file) || is_readable($file)) {
            $transfer = new Varien_File_Transfer_Adapter_Http();
            $transfer->send($file);
        } else {
            header('HTTP/1.0 404 Not Found');
        }
    }
}