<?php

/**
 * This script must be run from within the Drupal bootstrap.  One way is to execute this
 * from the Devel "Execute PHP Code" page at devel/php -- another way is to use
 * a drush command that will bootstrap.
 *
 * The log output file for this script is stored in LOGFILE.
 */

set_time_limit(0);

define('LOGFILE', '/usr/local/src/islandora_tools/logs/ds_update_TN_LARGE.log');

$fq = 'fedora_datastream_latest_TN_LARGE_SIZE_ms';

// build SOLR query
_log('start ' . date('c'));
$records = _get_SOLR_records($fq);
_log('found '.count($records).' matching SOLR records that have bad TN_LARGE');
_process_change($records);
_log('stop ' . date('c'));


function _get_SOLR_records($fq) {
  _log('in _get_SOLR_records [fq=' . $fq . ']');
  $query_processor = new IslandoraSolrQueryProcessor();
  $query_processor->solrQuery = '(PID:pitt\:*)';
  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 2710;
  $query_processor->solrParams = array(
    'fl' => "PID,fgs_label_s,RELS_EXT_hasModel_uri_s,fedora_datastream_latest_TN_LARGE_SIZE_ms",
    'fq' => $fq,
  );
  $url = parse_url(variable_get('islandora_solr_url', 'localhost:8080/solr'));
  $solr = new Apache_Solr_Service($url['host'], $url['port'], $url['path'] . '/');
  $solr->setCreateDocuments(FALSE);
  try {
    $results = $solr->search($query_processor->solrQuery, $query_processor->solrStart, $query_processor->solrLimit, $query_processor->solrParams, 'GET');
    $tmp = json_decode($results->getRawResponse(), TRUE);
    $results = array();
    foreach ($tmp['response']['docs'] as $trip) {
      drupal_set_message($trip['PID'] . ' = ' . $trip['RELS_EXT_hasModel_uri_s']. '  ');
      $results[] = $trip['PID'];
    }
    return $results;
  }
  catch (Exception $e) {
    return array();
  }
}

function _process_change($pids, $to) {
  _log('in _process_change');
  foreach ($pids as $pid) {
    $object = islandora_object_load($pid);
    $ds = $object->getDatastream('TN_LARGE');
    try {
      $deleted = islandora_delete_datastream($ds);
    }
    catch (Exception $e) {
      drupal_set_message(t('Error deleting %s datastream from object %o %e', array(
          '%s' => 'TN_LARGE',
          '%o' => $object->label,
          '%e' => $e->getMessage())), 'error');
    }
    if ($deleted) {
      drupal_set_message(t('%d datastream sucessfully deleted from Islandora object %o', array(
          '%d' => 'TN_LARGE',
          '%o' => $object->label)));
    }
    else {
      drupal_set_message(t('Error deleting %s datastream from object %o', array(
          '%s' => 'TN_LARGE',
          '%o' => $object->label)), 'error');
    }
    _log('deleted "'.$object->label.'", PID:'.$pid);
  }

}

function _log($message) {
  if (function_exists('drupal_set_message')) {
    drupal_set_message($message, 'status');
  }
  error_log(date('c') . ' ' . $message."\n", 3, LOGFILE);
}
