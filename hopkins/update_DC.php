<?php

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/hopkins/update_DC.php');
//

// XML Transformation
define('TRANSFORM_STYLESHEET', dirname(__FILE__).'/transforms/subject_node_transform.xml');
define('TRANSFORM_MODS2DC_STYLESHEET', dirname(__FILE__).'/transforms/mods_to_dc.xsl');


// This is the logfile for this 
define('LOGFILE', '/usr/local/src/islandora_tools/hopkins/logfile');

// If this variable is set to any PID that is in the spreadsheet, all items above it will not be processed -- making it 
// the first PID to be processed;
// $first_pid = 'pitt:86v01p31';
$first_pid = NULL;

// Set this variable if we only need to process a specific number of items from the spreadsheet.
// $process_exactly_howmany = 1;
$process_exactly_howmany = PHP_INT_MAX;


$last_container_created_id = -1;
$filename = dirname(__FILE__).'/hopkins_metadata_update_data.csv';
$file = file($filename);
$headings_row = array(array_shift($file));
$headings = array_pop(array_map('str_getcsv', $headings_row));
$identifier_column_idx = array_search('identifier', $headings);
$csv = array_map('str_getcsv', $file);
$max_geo_idx = 5;

$s = "";
$i = 0;
$process = (is_null($first_pid) ? TRUE : FALSE);
foreach ($csv as $record_idx=>$row) {
  $pid = (isset($row[$identifier_column_idx]) ? 'pitt:' . $row[$identifier_column_idx] : '');
  // To set the start of processing at a specific PID value
  if (!$process && !is_null($first_pid)) {
    $process = $pid == $first_pid;
  }
  if ($process && $i < $process_exactly_howmany) {
    $pid = (isset($row[$identifier_column_idx]) ? 'pitt:' . $row[$identifier_column_idx] : '');
    $s .= '<h1>row#' . $record_idx . ' = ' . $pid . ' (' . (1 + $i) . '/' . (($process_exactly_howmany == PHP_INT_MAX) ? 'all' : $process_exactly_howmany) . ")</h1>";
    $islandora_object = islandora_object_load($pid);
    if (is_object($islandora_object)) {
      $s .= 'islandora_object(' . $pid . ') ' . (is_object($islandora_object) ? ' loaded ok' : ' NOT LOADED');
      $s .= ', ' . (is_object($islandora_object) && isset($islandora_object['MODS']) ? 'has MODS' : 'no MODS') . "\n";
      $s .= process_changes($islandora_object);
    }
    $i++;
  }
  else {
    $s .= '.';
  }
}

// $s was displayed as full HTML when we'd die($s), but the devel module does not print any html tags.
echo strip_tags(str_replace(array('<br>', '<hr>'), "\n", $s));
// die($s);

/**
 * This will transform the MODS according to the transform file TRANSFORM_STYLESHEET.
 */
function process_changes($islandora_object) {
  global $max_geo_idx;
  _log('PROCESSING ' . $islandora_object->id);

  $s = '';
  $mods = $islandora_object['MODS'];
  $tempFilename = tempnam("/tmp", "MODS_xml_initial_");
  // save the html body to a file -- datastream can load from the file
  $mods_file = $mods->getContent($tempFilename);

  $mods_file = implode("", file($tempFilename));
  //  $s .= '</pre><hr><h3>original MODS</h3><pre style="color:#227">' . htmlspecialchars($mods_file) . '</pre><hr>';
  
  // Transorm the MODS xml here
  //  $updated_mods_file = doTransform($islandora_object, $mods_file);

  // This will update the DC record by transforming the current MODS xml.
  doDC($islandora_object, $mods_file);

  _log('new MODS for ' . $islandora_object->id . ' = ' . $updated_mods_file);
  _log('PROCESSING DONE for ' . $islandora_object->id);

  //  $s .= '<h3>updated MODS</h3><pre style="color:#2b2">' . htmlspecialchars($updated_mods_file) . "</pre>";
  return $s;
}

function _log($message) {
  if (function_exists('drupal_set_message')) {
    drupal_set_message($message, 'status');
  }
  error_log(date('c') . ' ' . $message."\n", 3, LOGFILE);
}

function doTransform($object, $mods_content) {
 // TRANSFORM_STYLESHEET
  $mods_datastream = $object['MODS'];

  // Get the DC by transforming from MODS.
  if ($mods_content) {
    $new_MODS = _runXslTransform(
            array(
              'xsl' => TRANSFORM_STYLESHEET,
              'input' => $mods_content,
            )
          );
    if (isset($new_MODS)) {
      $mods_datastream->setContentFromString($new_MODS);
      _log('--------------- old MODS = ' . print_r($mods_content, true));
      _log('--------------- transform MODS = ' . print_r($new_MODS, true));
    }
  }  
  return $new_MODS;
}

// Mostly COPIED from islandora_batch/includes/islandora_scan_batch.inc.
/**
 * Helper function to transform the MODS to get dc.
 */  
function doDC($object, $mods_content) {
  $dc_datastream = $object['DC'];     
  $dc_datastream->mimetype = 'application/xml';
  $dc_datastream->label = 'DC Record';

  // Get the DC by transforming from MODS.
  if ($mods_content) {
    $new_dc = _runXslTransform(
            array(
              'xsl' => TRANSFORM_MODS2DC_STYLESHEET, // drupal_get_path('module', 'islandora_batch') . '/transforms/mods_to_dc.xsl',
              'input' => $mods_content,
            )
          );
    _log('--------------- transform DC = ' . print_r($new_dc, true));
  }
  if (isset($new_dc)) {
    $dc_datastream->setContentFromString($new_dc);
  }
echo '<a href="http://gamera.library.pitt.edu/islandora/object/' . $object->id . '">' . $object->label . '</a><br>';
  $object->ingestDatastream($dc_datastream);
}

// COPIED directly from islandora_batch/includes/islandora_scan_batch.inc.
/**
  * Run an XSLT, and return the results.
  *
  * @param array $info
  *   An associative array of parameters, containing:
  *   - input: The input XML in a string.
  *   - xsl: The path to an XSLT file.
  *   - php_functions: Either a string containing one or an array containing
  *     any number of functions to register with the XSLT processor.
  *
  * @return string
  *   The transformed XML, as a string.
  */
function _runXslTransform($info) {
  $xsl = new DOMDocument();
  $xsl->load($info['xsl']);
  _log('transform style sheet: ' . $info['xsl']);
  $input = new DOMDocument();
  $input->loadXML($info['input']);

  $processor = new XSLTProcessor();
  $processor->importStylesheet($xsl);

  if (isset($info['php_functions'])) {
    $processor->registerPHPFunctions($info['php_functions']);
  }

  // XXX: Suppressing warnings regarding unregistered prefixes.
  return $processor->transformToXML($input);
}
