<?php

/**
 * This script must be run from within the Drupal bootstrap.  One way is to execute this 
 * from the Devel "Execute PHP Code" page at devel/php -- another way is to use
 * a drush command that will bootstrap.
 *
 * The log output file for this script is stored in LOGFILE.
 */

set_time_limit(0);
define('LOGFILE', '/usr/local/src/islandora_tools/logs/ds_update_titles.log');

$fq = 'RELS_EXT_hasModel_uri_s:*bookCModel';

// build SOLR query
_log('start ' . date('c'));
$records = _get_SOLR_records($fq);
_log('found '.count($records).' matching SOLR records that have title ending with comma');
_process_title_change($records);
_log('stop ' . date('c'));


function _get_SOLR_records($fq) {
  _log('in _get_SOLR_records [fq=' . $fq . ']');
  $query_processor = new IslandoraSolrQueryProcessor();
  $query_processor->solrQuery = '(PID:pitt\:*) AND fgs_label_s:*,';
  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 1;
  $query_processor->solrParams = array(
    'fl' => "PID,fgs_label_s,RELS_EXT_hasModel_uri_s",
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
      _log('add ' . $trip['PID']);
      $results[] = $trip['PID'];
    }
    return $results;
  }
  catch (Exception $e) {
_log(print_r($e, true));
    return array();
  }
}

function _process_title_change($pids) {
  _log('in _process_title_change - pids = ' . print_r($pids, true));
  foreach ($pids as $pid) {
    $islandora_object = islandora_object_load($pid);
    if (is_object($islandora_object)) {
      _log('loaded ok ... ?');
      // try to call xml_form_builder_update_metadata_datastream from here

      $mods_str = $islandora_object['MODS']->content;

      $dom = new DOMDocument();
      $dom->loadXML($mods_str);
//      $dom->perserveWhiteSpace = TRUE;
//      $dom->formatOutput = TRUE;
      $tit = $dom->getElementsByTagName("titleInfo");
      $title = trim($tit->item(0)->nodeValue);
      $new_title = substr($title, 0, -1);
      _log('title = [' . $title. '] ... set to [' . $new_title . ']');

      $dom->getElementsByTagName("titleInfo")->item(0)->nodeValue = $new_title;
      $as_xml = $dom->saveXML();
dpm($as_xml);

//      $mods_xml = new SimpleXMLElement($mods_str);
//      $mods_xml->registerXPathNamespace('mods', 'http://www.loc.gov/mods/v3');

//      $title_results = $mods_xml->xpath('//mods:mods[1]/mods:titleInfo/mods:title');
//      $title = (string) reset($title_results);

//      $islandora_object->label = substr($title, 0, strlen($title) - 1);

//      $title_results->item(0)->nodeValue = substr($title, 0, strlen($title) - 1);
// _log('nodevalue ? ' . print_r($title_results->nodeValue, true));

return false;
      // remove the comma - write out the XML file and update the MODS datastream
      if (isset($islandora_object['MODS'])) {
//        $tempFilename = tempnam('/tmp', "MODS-xml_changed_");
//        $mods_xml->saveXML($tempFilename);
        $datastream = $islandora_object['MODS'];

//        $tempFilename = tempnam('/tmp', "MODS-xml_");
//        $datastream->setContentFromString(trim($doc->saveXML()));
//        $islandora_object->ingestDatastream($datastream);
      }
      else {
        _log('Object has no MODS datastream PID = ' . $pid);
      }
    } 
    else {
      _log('problem loading PID ' . $pid);
    }

/*
    if ($ds->mimetype <> $to) {
      $ds->mimetype = $to;
      _log('updated "'.$islandora_object->label.'", PID:'.$pid);
    }
    else {
      _log('skipped "'.$islandora_object->label.'", PID:'.$pid);
    }
*/
  }
}

function _log($message) {
  if (function_exists('drupal_set_message')) {
    drupal_set_message($message, 'status');
  }
  error_log(date('c') . ' ' . $message."\n", 3, LOGFILE);
}

