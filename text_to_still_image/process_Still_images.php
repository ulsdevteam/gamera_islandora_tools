<?php

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/text_to_still_image/process_Still_images.php');
//

// This is the logfile for this
define('LOGFILE', dirname(__FILE__).'/logfile');
define('TRANSFORM_STYLESHEET', dirname(__FILE__).'/xsl/updateTypeOfResource.xsl');
define('TRANSFORM_MODS2DC_STYLESHEET', dirname(__FILE__).'/../common/xsl/mods_to_dc.xsl');

// Control how many of the items are processed
// $process_exactly = 2;
$process_exactly = PHP_INT_MAX;

$csv_filename = dirname(__FILE__) . '/Still_image_to_still_image.csv';

include_once(dirname(__FILE__) .'/../common/funcs.php');

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

$connection = getRepositoryConnection();
$repository = getRepository($connection);


$file = file($csv_filename);
$csv = array_map('str_getcsv', $file);

_log('started', LOGFILE);

$processed_pids = array();
$i = 0;

foreach ($csv as $row) {
  if ($i < $process_exactly) {
    $pid = $row[0];
    $islandora_object = islandora_object_load($pid);
    if (is_object($islandora_object)) {
      // Since we know the spreadsheet row can only have two possible collections, just read 1 and 2 like this
      process_change($islandora_object); 
      $processed_pids[] = $pid;
    }
    else {
      _log('PID not found : ' . $pid, LOGFILE);
    }
  }
  $i++;
}
_log('processed objects : ' . implode(", ", $processed_pids), LOGFILE);
die();
_log('done', LOGFILE);


function process_change($islandora_object) {
  _log('working on ' . $islandora_object->id, LOGFILE);
  echo $islandora_object->id . ' <a href="/islandora/object/' . $islandora_object->id . '/manage/datastreams">' . $islandora_object->id . "</a><br>";

  $MODS_datastream = isset($islandora_object['MODS']) ? $islandora_object['MODS'] : NULL;
  if (!is_null($MODS_datastream)) {
    _log('previous MODS: ' . "\n" . $MODS_datastream->content);
echo "<pre style='color:#698'>".htmlspecialchars(print_r($MODS_datastream->content, true))."</pre>";
    $doc = new DOMDocument();
    $doc->loadXML($MODS_datastream->content);

    // use a transform to update the value?
    $new_MODS = _runXslTransform(
          array(
            'xsl' => TRANSFORM_STYLESHEET,
            'input' => $MODS_datastream->content,
          )
        );
    _log('new MODS: ' . "\n" . $new_MODS);

echo "<pre>".htmlspecialchars(print_r($new_MODS, true))."</pre>";

    $datastream = $islandora_object['MODS'];
    $datastream->setContentFromString($new_MODS);
    $islandora_object->ingestDatastream($datastream);
    // This will update the DC record by transforming the current MODS xml.
    doDC($islandora_object, $MODS_datastream->content);
  }
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
              'xsl' => TRANSFORM_MODS2DC_STYLESHEET,
              'input' => $mods_content,
            )
          );
    error_log('--------------- transform DC = ' . print_r($new_dc, true));
  }
  if (isset($new_dc)) {
    $dc_datastream->setContentFromString($new_dc);
  }
  echo '<a href="http://gamera.library.pitt.edu/islandora/object/' . $object->id . '">' . $object->label . '</a><br>';
  $object->ingestDatastream($dc_datastream);
}

