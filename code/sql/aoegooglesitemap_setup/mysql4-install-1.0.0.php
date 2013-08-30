<?php
$installer = $this; /* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$installer->addAttribute('catalog_category', 'sitemap_exclude', array(
    'group'    => 'General Information',
    'input'    => 'select',
    'type'     => 'text',
    'label'    => 'Exclude from Sitemap',
    'source'   => 'eav/entity_attribute_source_boolean',
    'required' => 0,
    'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'note'     => 'Exclude from xml sitemap generation',
    'default'  => 0
));
$dbStorage = Mage::helper('core/file_storage_database')->getStorageDatabaseModel(); /* @var $dbStorage Mage_Core_Model_File_Storage_Database */
$dbStorage->getDirectoryModel()->prepareStorage();
$dbStorage->prepareStorage();

$installer->run("
  INSERT IGNORE INTO `core_directory_storage` (`directory_id`, `name`, `path`, `upload_time`, `parent_id`) VALUES
(1, 'sitemap', '/', NOW(), NULL);
");

$installer->endSetup();