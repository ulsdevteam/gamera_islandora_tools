<?php

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/books_size_and_ocr/process.php');
//

include_once(dirname(__FILE__) .'/../common/funcs.php');
module_load_include('inc', 'islandora_paged_content', 'includes/utilities');

// This is the logfile for this
define('LOGFILE', '/usr/local/src/islandora_tools/books_size_and_ocr/logfile');

// If this variable is set to any PID that is in the spreadsheet, all items above it will not be processed -- making it
// the first PID to be processed;
// $first_pid = 'pitt:01v01ind';
$first_pid = NULL;

// Set this variable if we only need to process a specific number of items from the spreadsheet.
$process_exactly_howmany = 1;
// $process_exactly_howmany = PHP_INT_MAX;


$last_container_created_id = -1;
$filename = dirname(__FILE__).'/books.csv';
$file = file($filename);

$filename = dirname(__FILE__).'/done_books.csv';
$done_file = file($filename);
$max_geo_idx = 2;

// Get only the PID values that have not been done yet.
$need_to_file = array_diff($file, $done_file);

$s = "";
$i = 0;
$process = (is_null($first_pid) ? TRUE : FALSE);
foreach ($need_to_file as $record_idx=>$pid) {
  $pid = trim($pid);
  // To set the start of processing at a specific PID value
  if (!$process && !is_null($first_pid)) {
    $process = $pid == $first_pid;
  }
  if ($process && $i < $process_exactly_howmany) {
    $s .= '<h1>row#' . $record_idx . ' = ' . $pid . ' (' . (1 + $i) . '/' . (($process_exactly_howmany == PHP_INT_MAX) ? 'all' : $process_exactly_howmany) . ")</h1>";
    $islandora_object = islandora_object_load($pid);
    if (is_object($islandora_object)) {
      $s .= 'islandora_object(' . $pid . ') ' . (is_object($islandora_object) ? ' loaded ok' : ' NOT LOADED');
      $s .= ', ' . (is_object($islandora_object) && isset($islandora_object['MODS']) ? 'has MODS' : 'no MODS') . "\n";
      $s .= process_changes($pid);
    }
    else {
      _log('pid ' . $pid . ' not found');
    }
    $i++;
  }
  else {
    $s .= '.';
  }
}

_log($s);
// $s was displayed as full HTML when we'd die($s), but the devel module does not print any html tags.
echo strip_tags(str_replace(array('<br>', '<hr>'), "\n", $s));
die($s);

// this will call the drush command to update page size and then call the OCR/HOCR process
function process_changes($pid) {
  $pagesize_values = islandora_paged_content_paged_content_update_page_image_sizes($pid);
  $OCR_values = islandora_paged_content_paged_content_generate_ocr_datastreams($pid, TRUE);
  $values = array_merge($pagesize_values, $OCR_values);
  return $values;
}

?>
