<?php

/**
 * This process must run from within the Islandora framework -- easiest way is to just call this from 
 * /devel/php with the single line of code:
 *    include_once('/usr/local/src/islandora_tools/enable_versionable_datastreams/process_objects.php');
 * 
 * This process will update the RELS_EXT and RELS_INT datastream's versionable flag to TRUE.
 */

/**
 * STEPS:
 *   1) run Solr query to get the objects that have a RELS_EXT or RELS_INT.
 *   2) if there is a RELS_EXT, update that datastream's versionable flag.
 *   3) if there is a RELS_INT, update that datastream's versionable flag.
 */

define('LOGFILE', '/usr/local/src/islandora_tools/logs/enable-versionable.log');

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

error_log('started ' . date('H:i:s'));

$connection = getRepositoryConnection();
$repository = getRepository($connection);

echo "<pre>";
$objects = _get_pids();
update_datastreams($objects, $repository);
error_log('done ' . date('H:i:s'));
die();

function _get_pids() { 
  $query_processor = new IslandoraSolrQueryProcessor();

  $query_processor->solrQuery = '(fedora_datastream_info_RELS-EXT_VERSIONABLE_ms:false OR fedora_datastream_info_RELS-INT_VERSIONABLE_ms:false OR fedora_datastream_info_DC_VERSIONABLE_ms:false)';
  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 8000;
  $query_processor->solrParams = array(
    'fl' => "PID,fgs_label_s",
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
        );
    }
    return $results;
  }
  catch (Exception $e) {
    return array();
  }  
}

function update_datastreams($objects, $repository) {
  foreach ($objects as $pid => $object) {
    update_datastream($pid, $repository);
  }
}

function update_datastream($pid, $repository) {
  $object = $repository->getObject($pid);
  echo "<a href='http://gamera.library.pitt.edu/islandora/object/" . $pid . "/manage'>" . $pid . "</a><br>";

  $dss = array();
  if ($dc = $object->getDatastream('DC')) {
    $dc->versionable = TRUE;
    $dss[] = 'DC';
  }
  if ($relsint = $object->getDatastream('RELS-INT')) {
    $relsint->versionable = TRUE;
    $dss[] = 'RELS-INT';
  }
  if ($relsext = $object->getDatastream('RELS-EXT')) {
    $relsext->versionable = TRUE;
    $dss[] = 'RELS-EXT';
  }
  error_log('for ' . $pid . ' set ' . implode(', ', $dss) . ' datastream->versionable to TRUE');
}
