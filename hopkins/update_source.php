<?php

/**
 * This process must run from within the Islandora framework -- easiest way is to just call this from 
 * /devel/php with the single line of code:
 *    include_once('/usr/local/src/islandora_tools/hopkins/update_source.php');
 * 
 * This process will update the Source values for the objects that are in the Hopkins maps collection so that the source
 * fields (mods_relatedItem_host_titleInfo_title_ms) include the year value.
 */

/**
 * STEPS:
 *   1) run Solr query to get the objects in the collection.
 *   2) get the MODS for each object 
 *   3) update the mods_relatedItem_host_titleInfo_title_ms value so that it contains (mods_relatedItem_host_titleInfo_title_ms) + (mods_originInfo_type_display_dateOther_s)
 */

define('LOGFILE', '/usr/local/src/islandora_tools/logs/hopkins-update-source.log');
_log('started ' . date('H:i:s'));

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

$connection = getRepositoryConnection();
$repository = getRepository($connection);

echo "<pre>";
$hopkins_objects = _get_hopkins_pids();
update_sources($hopkins_objects);
die();

function _get_hopkins_pids() { 
  $query_processor = new IslandoraSolrQueryProcessor();
  $query_processor->solrQuery = '(PID:pitt\:*) AND RELS_EXT_isMemberOfCollection_uri_ms:*pitt\:collection.240';
  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 9999;
  $query_processor->solrParams = array(
    'fl' => "PID,fgs_label_s,mods_relatedItem_host_titleInfo_ms,mods_originInfo_type_display_dateOther_s",
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
      $year = $trip['mods_originInfo_type_display_dateOther_s'];
      if (strlen($year) > 4 && !($timestamp = strtotime($year)) === false) {
        $year = date('Y', $timestamp);
      }
      $results[$trip['PID']] = array(
          'PID' => $trip['PID'],
          'fgs_label_s' => $trip['fgs_label_s'],
          'mods_relatedItem_host_titleInfo_ms' => $trip['mods_relatedItem_host_titleInfo_ms'][0],
          'mods_originInfo_type_display_dateOther_s' => $year,
        );
    }
    return $results;
  }
  catch (Exception $e) {
    return array();
  }  
}

function update_sources($hopkins_objects) {
  $sources = array();
  foreach ($hopkins_objects as $pid => $hopkins_object) {
    $new_source = $hopkins_object['mods_relatedItem_host_titleInfo_ms']; /*.
      (isset($hopkins_object['mods_originInfo_type_display_dateOther_s']) ? ' (' . $hopkins_object['mods_originInfo_type_display_dateOther_s'] . ')' : ''); */

    _log('PID: ' . $pid . ', old source = \'' . addslashes($hopkins_object['mods_relatedItem_host_titleInfo_ms']) . '\', '.
      'date: ' . $hopkins_object['mods_originInfo_type_display_dateOther_s'] . ', new source = \'' . $new_source . '\'');
    echo $new_source . "<br>";
    update_source($pid, $new_source);
    $sources[$new_source]++;
  }
  echo "<pre>".print_r($sources, true)."</pre>";
}

function update_source($pid, $new_source) {
  $islandora_object = islandora_object_load($pid);
  if ($islandora_object) {
    $MODS_datastream = isset($islandora_object['MODS']) ? $islandora_object['MODS'] : NULL;
    if (!is_null($MODS_datastream)) {
       _log($MODS_datastream->content);
      $doc = new DOMDocument();
      $doc->loadXML($MODS_datastream->content);

      // use a transform to update the value?
      $fly_transform = _fly_transform($new_source);
      $new_MODS = _runXslTransform(
            array(
              'xsl' => $fly_transform,
              'input' => $MODS_datastream->content,
            )
          );
      $datastream = $islandora_object['MODS'];
      $datastream->setContentFromString($new_MODS);
      $islandora_object->ingestDatastream($datastream);
      unlink($fly_template);
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

// this will create and save a transform file on the fly that will contain the value for the new_source.
function _fly_transform($new_source) {
  $tempFilename = tempnam("/tmp", "MODS_xml_initial_");
  $data = str_replace(
      array('{|', '|}', '}', '{'), 
      array('?', '?', '>', '<'), '{{|xml version="1.0" |}}
{xsl:stylesheet version="1.0"
   xmlns:mods="http://www.loc.gov/mods/v3"
   xmlns:xsl="http://www.w3.org/1999/XSL/Transform"}
   {xsl:template match="/ | @* | node()"}
         {xsl:copy}
           {xsl:apply-templates select="@* | node()" /}
         {/xsl:copy}
   {/xsl:template}
   {xsl:template match="/mods:mods/mods:relatedItem[@type=\'host\']/mods:titleInfo"}{mods:titleInfo}{mods:title}' . $new_source . '{/mods:title}{/mods:titleInfo}{/xsl:template}
{/xsl:stylesheet}');

  file_put_contents($tempFilename, $data);
  return $tempFilename;
}

