<?php
$installer = $this; /* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$dbStorage = Mage::helper('core/file_storage_database')->getStorageDatabaseModel(); /* @var $dbStorage Mage_Core_Model_File_Storage_Database */
$dbStorage->getDirectoryModel()->prepareStorage();
$dbStorage->prepareStorage();

$installer->run("
  INSERT IGNORE INTO `core_directory_storage` (`directory_id`, `name`, `path`, `upload_time`, `parent_id`) VALUES
(1, 'sitemap', '/', NOW(), NULL);
");

$installer->endSetup();