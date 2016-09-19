<?php

/**
 * This script must be run from within the Drupal bootstrap.  One way is to execute this 
 * from the Devel "Execute PHP Code" page at devel/php -- another way is to use
 * a drush command that will bootstrap.
 *
 * The log output file for this script is stored in LOGFILE.
 */

set_time_limit(0);
dpm('1');
define('LOGFILE', '/usr/local/src/islandora_tools/logs/ds_update_mimetype.log');

$mime_from = 'image/jpg';
$mime_to = 'image/jpeg';
$fq = '';

// build SOLR query
_log('start ' . date('c'));
$records = _get_SOLR_records($mime_from, $fq);
_log('found '.count($records).' matching SOLR records that have image/jpg mimetype for TN_LARGE');
_process_mimetype_change($records, $mime_to);
_log('stop ' . date('c'));


function _get_SOLR_records($mime_from, $fq) {
  _log('in _get_SOLR_records [fq=' . $fq . ']');
  $query_processor = new IslandoraSolrQueryProcessor();
  $query_processor->solrQuery = '(PID:pitt\:*)%20AND%20(RELS_EXT_hasModel_uri_s:info\:fedora\/islandora\:bookCModel)%20AND%20(fedora_datastream_version_TN_LARGE_MIMETYPE_ms:' . str_replace("/", "\/", $mime_from) . ')';
  $query_processor->solrStart = 20000;
  $query_processor->solrLimit = 22000;
  $query_processor->solrParams = array(
    'fl' => "PID,fgs_label_s,RELS_EXT_hasModel_uri_s",
    'fq' => $fq,
  );
  $url = parse_url(variable_get('islandora_solr_url', 'localhost:8080/solr'));
  $solr = new Apache_Solr_Service($url['host'], $url['port'], $url['path'] . '/');
  $solr->setCreateDocuments(FALSE);
  try {
    $results = $solr->search($query_processor->solrQuery, $query_processor->solrStart, $query_processor->solrLimit, $query_processor->solrParams, 'GET');
 _log($query_processor->solrQuery);
    $tmp = json_decode($results->getRawResponse(), TRUE);
    $results = array();
    foreach ($tmp['response']['docs'] as $trip) {
      $results[] = $trip['PID'];
    }
    return $results;
  }
  catch (Exception $e) {
    return array();
  }
}

function _process_mimetype_change($pids, $to) {
  _log('in _process_mimetype_change');
  foreach ($pids as $pid) {
    $islandora_object = islandora_object_load($pid);
    $ds = $islandora_object->getDatastream('TN_LARGE');
    if ($ds->mimetype <> $to) {
      $ds->mimetype = $to;
      _log('updated "'.$islandora_object->label.'", PID:'.$pid);
    }
    else {
      _log('skipped "'.$islandora_object->label.'", PID:'.$pid);
    }
  }

}

function _log($message) {
  if (function_exists('drupal_set_message')) {
    drupal_set_message($message, 'status');
  }
  error_log(date('c') . ' ' . $message."\n", 3, LOGFILE);
}

