<?php

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/convert_pages_to_images/convert_pages.php');
//

/**
 * This script will process the books_pages_list.csv file and take the pages from the books and convert them into large images.
 *
 */

set_time_limit(0);

include_once(dirname(__FILE__) .'/../common/funcs.php');

// Set this variable if we only need to process a specific number of items from the spreadsheet.
// $process_exactly_howmany = 1;
$process_exactly_howmany = PHP_INT_MAX;

// Load our own Library.
require_once(dirname(__FILE__) .'/../uls-tuque-lib.php');

// Setup Tuque
$path_to_tuque = get_config_value('tuque','path_to_tuque');
if (file_exists($path_to_tuque)) {
        require_once($path_to_tuque . 'Cache.php');
        require_once($path_to_tuque . 'FedoraApi.php');
        require_once($path_to_tuque . 'FedoraApiSerializer.php');
        require_once($path_to_tuque . 'Object.php');
        require_once($path_to_tuque . 'Repository.php');
        require_once($path_to_tuque . 'RepositoryConnection.php');
} else {
        print "Error - Invalid path to Tuque.\n";
        exit(1);
}

// Setup Solr Connection.
$solr_url = get_config_value('solr','url');
$solr_proxy_username = get_config_value('solr','proxy_username');
$solr_proxy_password = get_config_value('solr','proxy_password');
$solr = str_replace('http://', "http://$solr_proxy_username:$solr_proxy_password@", $solr_url);

$connection = getRepositoryConnection();
$repository = getRepository($connection);


$last_container_created_id = -1;
$filename = dirname(__FILE__).'/books_pages_list.csv';
$file = file($filename);
$csv = array_map('str_getcsv', $file);

$i = 0;
foreach ($csv as $record_idx=>$row) {
  // To set the start of processing at a specific PID value
  if ($i < $process_exactly_howmany) {
    $book_pid = (isset($row[0]) ? $row[0] : '');
    $islandora_book_object = islandora_object_load($book_pid);
    $page_pid = (isset($row[1]) ? $row[1] : '');
    $islandora_page_object = islandora_object_load($page_pid);

    echo '<h1>row#' . $record_idx . ' book="' . $book_pid . '", page="' . $page_pid . '", (' . (1 + $i) . '/' . (($process_exactly_howmany == PHP_INT_MAX) ? 'all' : $process_exactly_howmany) . ")</h1>";
    if (is_object($islandora_book_object) && is_object($islandora_page_object)) {
      echo 'BOOK(' . $book_pid . ') ' . (is_object($islandora_book_object) ? ' loaded ok' : ' NOT LOADED');
      echo ', ' . (is_object($islandora_book_object) && isset($islandora_book_object['MODS']) ? 'has MODS' : 'no MODS') . "<br>";
      echo 'PAGE(' . $page_pid . ') ' . (is_object($islandora_page_object) ? ' loaded ok' : ' NOT LOADED');
      echo ', ' . (is_object($islandora_page_object) && isset($islandora_page_object['OBJ']) ? 'has OBJ' : 'no MODS') . "<hr>";

      $record_data = array();
      process_conversion($islandora_book_object, $islandora_page_object, $repository);
    }
    $i++;
  }
  else {
    echo '.';
  }
}

// $s was displayed as full HTML when we'd die($s), but the devel module does not print any html tags.
die($s);

/**
 * This will perform the steps on the two objects.
 *   1) for each page (only one page per book for these books):
 *      a) get the OBJ datastream from the page
 *   2) transform the MODS from step 1 to update the mods/identifier value to the new image's PID
 *   3) convert the book to a large image add the OBJ (from step 1a) datastream and remove all extra datastreams.
 *   4) call the function to run derivatives on the original object
 *   5) finally, delete the page object
 */
function process_conversion($islandora_book_object, $islandora_page_object, $repository) {
  module_load_include('inc', 'islandora', 'includes/derivatives');
  // save the html body to a file -- datastream can load from the file
  $obj = $islandora_page_object['OBJ'];
  $tempOBJFilename = tempnam("/tmp", "OBJ_initial_" . $islandora_page_object->id);
  $OBJ_file_saved = $obj->getContent($tempOBJFilename);
  echo "in process_conversion<br>";
  if ($OBJ_file_saved) {
    echo 'temp OBJ filename = ' . $tempOBJFilename . '<br>';
    $islandora_book_object->ingestDatastream($obj);
    $islandora_book_object->relationships->remove('info:fedora/fedora-system:def/model#', 'hasModel');
    $islandora_book_object->relationships->add('info:fedora/fedora-system:def/model#', 'hasModel', 'islandora:sp_large_image_cmodel');

    islandora_run_derivatives($islandora_book_object, 'OBJ');
    islandora_delete_object($islandora_page_object);
    echo "<b>converted " . $islandora_book_object->id . "</b> ";
    echo "<a href='http://gamera.library.pitt.edu/islandora/object/" . $islandora_book_object->id . "/manage/datastreams'>link</a><hr>";
    @unlink($tmpOBJFilename);
  }
  else {
    echo "<b style='color:red'>ERROR saving the OBJ</b><br>";
  }
}
