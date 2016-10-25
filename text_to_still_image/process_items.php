<?php

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/text_to_still_image/process_items.php');
//

// This is the logfile for this
define('LOGFILE', dirname(__FILE__).'/logfile');
define('TRANSFORM_STYLESHEET', dirname(__FILE__).'/xsl/replaceTypeOfResource.xsl');
define('TRANSFORM_MODS2DC_STYLESHEET', dirname(__FILE__).'/../common/xsl/mods_to_dc.xsl');

// Control how many of the items are processed
// $process_exactly = 2;
$process_exactly = PHP_INT_MAX;

$csv_filename = dirname(__FILE__) . '/text_that_are_still_image.csv';

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
$headings_row = array(array_shift($file));
$headings = array_pop(array_map('str_getcsv', $headings_row));
$identifier_column_idx = array_search('identifier', $headings);
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
      if (isset($row[1]) && (trim($row[1]) <> '')) {
        process_change($islandora_object, trim($row[1])); 
        $processed_pids[] = $pid;
      }
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


function process_change($islandora_object, $title) {
  _log('working on ' . $islandora_object->id . ' "' . $title . '"', LOGFILE);
  $page = lookup_page_from_book($islandora_object);
  $page_pids = array_keys($page);
  $page_block = array();
  foreach ($page_pids as $page_pid) {
    $page_block[] = '<a href="http://gamera.library.pitt.edu/islandora/object/'. $page_pid . '">' . $page_pid . '</a>';
  }
  echo '<a href="http://gamera.library.pitt.edu/islandora/object/' . $islandora_object->id . '/manage/datastreams">' . $title . "</a> " . $islandora_object->id . "<br><b>" . count($page_pids) . " page" . ((count($page_pids) == 1) ? '' : 's') . ":</b><div style='padding:10px'>" . 
     implode("<br>", $page_block) . "</div><hr>";

//  $current_model = $islandora_object->models;
//  echo "models <pre>" . implode(", ", $current_model) . "</pre>";
return; 

  $MODS_datastream = isset($islandora_object['MODS']) ? $islandora_object['MODS'] : NULL;
  if (!is_null($MODS_datastream)) {
     _log('previous MODS: ' . "\n" . $MODS_datastream->content);
// echo "<pre style='color:#698'>".htmlspecialchars(print_r($MODS_datastream->content, true))."</pre>";
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

// die("<pre>".htmlspecialchars(print_r($new_MODS, true)));
    $datastream = $islandora_object['MODS'];
    $datastream->setContentFromString($new_MODS);
    $islandora_object->ingestDatastream($datastream);
    // This will update the DC record by transforming the current MODS xml.
    doDC($islandora_object, $MODS_datastream->content);
  }
}

function lookup_page_from_book($islandora_object) {
  $query_processor = new IslandoraSolrQueryProcessor();

  $query_processor->solrQuery = "(PID:pitt\:*) AND RELS_EXT_isPageOf_uri_ms:info\:fedora/" . str_replace(":", "\:", $islandora_object->id);
  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 9999;
  $query_processor->solrParams = array(
    'fl' => "PID,RELS_EXT_hasModel_uri_ms,mods_typeOfResource_ms,fgs_label_s",
    'fq' => '',
  );
  $url = parse_url(variable_get('islandora_solr_url', 'localhost:8080/solr'));
  $solr = new Apache_Solr_Service($url['host'], $url['port'], $url['path'] . '/');
  $solr->setCreateDocuments(FALSE);
  try {
    $results = $solr->search($query_processor->solrQuery, $query_processor->solrStart, $query_processor->solrLimit, $query_processor->solrParams, 'GET');
    $tmp = json_decode($results->getRawResponse(), TRUE);
    $results = array();
    foreach ($tmp['response']['docs'] as $trip) {
      $results[$trip['PID']] = array(
          'PID' => $trip['PID'],
          'fgs_label_s' => $trip['fgs_label_s'],
          'mods_typeOfResource_ms' => implode(", ", $trip['mods_typeOfResource_ms']),
          'RELS_EXT_hasModel_uri_ms' => implode(", ", $trip['RELS_EXT_hasModel_uri_ms']),
        );
    }
    return $results;
  }
  catch (Exception $e) {
    return array();
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

