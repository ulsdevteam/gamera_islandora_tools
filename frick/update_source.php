<?php

/**
 * This process must run from within the Islandora framework -- easiest way is to just call this from 
 * /devel/php with the single line of code:
 *    include_once('/usr/local/src/islandora_tools/frick/update_source.php');
 * 
 * This process will update the Source values for the objects that have the Source (mods_relatedItem_host_titleInfo_title_ms) 
 * value behind the dc.source field to become "Henry Clay Frick Business Records".
 */

/**
 * STEPS:
 *   1) run Solr query to get the objects in the collection.
 *   2) get the MODS for each object 
 *   3) update the mods_relatedItem_host_titleInfo_title_ms value so that it contains (mods_relatedItem_host_titleInfo_title_ms) + (mods_originInfo_type_display_dateOther_s)
 *   4) finally, must update the DC (call transform mods_to_dc.xsl)
 */

define('TRANSFORM_STYLESHEET', dirname(__FILE__) . '/xsl/update_source.xsl');
define('TRANSFORM_MODS2DC_STYLESHEET', dirname(__FILE__).'/../common/xsl/mods_to_dc.xsl');
define('LOGFILE', '/usr/local/src/islandora_tools/logs/frick-update-source.log');

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
include_once(dirname(__FILE__) .'/../common/funcs.php');

_log('started ' . date('H:i:s'));

$connection = getRepositoryConnection();
$repository = getRepository($connection);

echo "<pre>";
$frick_objects = _get_frick_pids();
update_sources($frick_objects);
die();

function _get_frick_pids() { 
  $query_processor = new IslandoraSolrQueryProcessor();

  $query_processor->solrQuery = '(PID:pitt\:*) AND dc.source:"Helen Clay Frick Foundation Archives*"';
  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 9999;
  $query_processor->solrParams = array(
    'fl' => "PID,fgs_label_s,mods_relatedItem_host_titleInfo_title_ms",
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
          'mods_relatedItem_host_titleInfo_title_ms' => $trip['mods_relatedItem_host_titleInfo_title_ms'][0],
        );
    }
    return $results;
  }
  catch (Exception $e) {
    return array();
  }  
}

function update_sources($frick_objects) {
  $sources = array();
  $new_source = 'Henry Clay Frick Business Records';
  foreach ($frick_objects as $pid => $frick_object) {
    _log('PID: ' . $pid . ', old source = \'' . addslashes($frick_object['mods_relatedItem_host_titleInfo_title_ms']) . '\', '.
      'date: ' . $frick_object['mods_originInfo_type_display_dateOther_s'] . ', new source = \'' . $new_source . '\'');
    update_source($pid);
    echo "<a href='http://gamera.library.pitt.edu/islandora/object/".$pid ."/manage'>".$pid . "</a><br>";
    $sources[$new_source]++;
  }
  echo "<pre>".print_r($sources, true)."</pre>";
}

function update_source($pid) {
  $islandora_object = islandora_object_load($pid);
  if ($islandora_object) {
    $MODS_datastream = isset($islandora_object['MODS']) ? $islandora_object['MODS'] : NULL;
    if (!is_null($MODS_datastream)) {
      _log('previous MODS: ' . "\n" . $MODS_datastream->content);
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
      $datastream = $islandora_object['MODS'];
      $datastream->setContentFromString($new_MODS);
      $islandora_object->ingestDatastream($datastream);
      // This will update the DC record by transforming the current MODS xml.
      doDC($islandora_object, $MODS_datastream->content);
    }
    else {
      _log('PROBLEM LOADING MODS for object ' . $pid);
      echo '<span style="color:red">PROBLEM LOADING MODS for object ' . $pid . "</span><br>";
    }
  }
  else {
    _log('PROBLEM LOADING ' . $pid);
    echo '<span style="color:red">PROBLEM LOADING ' . $pid . "</span><br>";
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

