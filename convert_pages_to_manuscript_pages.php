<?php

include_once('common/funcs.php');
define('LFILE', '/usr/local/src/islandora_tools/logs/manuscript_pages.log');

set_time_limit(0);
_log('test', LFILE);

$file = file('/home/bgilling/manuscript_pids.txt');
module_load_include('inc', 'islandora_paged_content', 'includes/utilities');

foreach ($file as $line) {
  $pid = trim($line);
  if ($pid) {
   $object = islandora_object_load($pid);
_log("manuscript $pid", LFILE);

   // If changing to a Book, convert all child pages to pageCModel objects... else convert to manuscriptPageCModel
   $pages = islandora_paged_content_get_pages($object);

   $resultant_page_model = 'islandora:manuscriptPageCModel';
   foreach ($pages as $page) {
_log(" page: " . $page['pid'], LFILE);
    $page_object = islandora_object_load($page['pid']);
    $page_current_base_model = x_base_model_of_object($page_object);
_log(" model = '" . $page_current_base_model . "'", LFILE);
/*
    if ($page_current_base_model <> $resultant_page_model) {
      $page_object->models = array("fedora-system:FedoraObject-3.0", $resultant_page_model);
    }
*/
//    ob_flush();
//    flush();
   }
  }
}

_log('done');

/**
 * Helper function to derive the base model -- omitting the "fedora-system:FedoraObject-3.0" value.
 */
function x_base_model_of_object($object) {
  $models = $object->models;
  $object_base_model = FALSE;
  foreach ($models as $model) {
    if ($model <> 'fedora-system:FedoraObject-3.0' && !$object_base_model) {
      $object_base_model = $model;
    }
  }
  return $object_base_model;
}
