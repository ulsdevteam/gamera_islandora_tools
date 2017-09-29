<?php

/**
 * This script will scan the EAD_FOLDER for all *.ead.xml files and update only the EAD
 * datastream.
 *
 * Call from http://gamera.library.pitt.edu/devel/php with the following line of code
 *   include_once('/usr/local/src/islandora_tools/finding_aids/update_eads.php');
 */

error_log('started ' . date('H:i:s'));

$skip_eads = array(''); // ZZ AACCWP-ead.xml');

define('EAD_FILE_ENDSWITH', '-ead.xml');

define('EAD_FOLDER', '/usr/local/src/EAD-Delivery');
define('MEMBEROFSITE_NAMESPACE', variable_get('islandora_memberofsite_namespace', 'http://digital.library.pitt.edu/ontology/relations#'));

include_once(dirname(__FILE__) .'/../common/funcs.php');

// Allow this script to run until it is done ~ will certainly exceed 100 seconds.
set_time_limit(0);

// This variable controls how many of the total items are processed when this script runs.
$process_exactly = PHP_INT_MAX;
// $process_exactly = 1;

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


// Setup Solr Connection.
$solr_url = get_config_value('solr','url');
$solr_proxy_username = get_config_value('solr','proxy_username');
$solr_proxy_password = get_config_value('solr','proxy_password');
$solr = str_replace('http://', "http://$solr_proxy_username:$solr_proxy_password@", $solr_url);

$connection = getRepositoryConnection();
$repository = getRepository($connection);

$s = ''; // for debug output
$lines_good = $lines_bad = array();
$count_good = $count_bad = 0;
$ead_files = _get_files(EAD_FOLDER, EAD_FILE_ENDSWITH);

sort($ead_files);

// $pids = array('pitt:US-PPiU-ffal002','pitt:US-PPiU-ffal001','pitt:US-PPiU-ais199616','pitt:US-PPiU-ais200619b','pitt:US-PPiU-ais201506');

$missing = $finding_aids = array();
$i = 0;
foreach ($ead_files as $idx => $ead) {
  echo $ead."<hr>";
  if (array_search($ead, $skip_eads) === FALSE) {
    $s0 = '';
    $ead_name = str_replace(EAD_FILE_ENDSWITH, '', $ead);

    if ($i < $process_exactly) {
      $ead_id = _get_ead_id($ead);
      //# uncomment next line if the code should only process objects that return TRUE for a function call
      echo "working on " . $ead_id ."<br>";
      //# uncomment next line for SINGLE_ITEM
//    if (!(array_search('pitt:' . $ead_id, $pids) === FALSE)) {
      // if ($ead_id == 'US-PPiU-ais199616') {
      $s0 .= 'given EAD file "' . $ead . '", ead_id = ' . $ead_id . ' ';
      $s2 = 'given EAD file "' . $ead . '", ead_id = ' . $ead_id;

      echo $s2."\n" . '<a href="http://gamera.library.pitt.edu/islandora/object/pitt:' . $ead_id . '/manage/datastreams">link</a>';
      $ead_marc = array('ead' => $ead);

      $call_result = process_finding_aid_xml($ead_id, $ead_marc, $repository, $solr);
      $count_good += (strstr($call_result, "ERROR:") == '') ? 1 : 0;
      $count_bad += (strstr($call_result, "ERROR:") <> '') ? 1 : 0;

      $s0 .= '[' . $i . '] ' . $call_result;
      echo $s0;

    //# uncomment next line for SINGLE_ITEM
//    }
    }
    $i++;
    $s .= $s0;
    if ($s0) {
      error_log($s0);
    }
  }
  else {
    echo "skipped $ead\n";
  }
}
echo "<html><head><title>test</title></head><body>";
echo "<br><b>" . number_format($count_good) . " good MARC ~ EAD</b></br>";
echo "<b>" . number_format($count_bad) . " bad MARC ~ EAD</b></br>";

echo "<pre>";
error_log('finished ' . date('H:i:s'));

die(implode('
', $lines_good) . "</pre><hr><pre>" . implode('
', $lines_bad) . "<hr>" . $s);



function process_finding_aid_xml($ead_id, $ead_marc, $repository, $solr) {
  echo "<hr> in process_finding_aid_xml($ead_id)<br>";
  $ead = EAD_FOLDER . '/' . $ead_marc['ead'];

  // Schema check
  $doc0 = new DOMDocument();

  echo "checking schema of ead XML";
  if (!@$doc0->load($ead) || (!@$doc0->schemaValidate(dirname(__FILE__) .'/schema/ead_explicit_xsd.xsd'))) {  //  ead.xsd'))) {
    echo "<pre>" . htmlspecialchars(file_get_contents($ead)) . "</pre>";
    return 'ERROR: Schema did not validate for this file : ' . $ead . "\n";
  }
  echo " - schema good<br>";

  $doc_xml = $doc0->saveXML();
  // use the $ead_id to make the PID
  $id = 'pitt:' . $ead_id;
  $object = islandora_object_load($id);
  if (!$object) {
    $object = $repository->constructObject($id);
    $object_existed = FALSE;
  }
  else {
    $object_existed = TRUE;
  }
  // Get the title from the MARC file
  $title = _get_xpath_nodeValue($doc_xml, '//d:filedesc/d:titlestmt/d:titleproper');

  $object->label = ($title) ? $title : $ead_id;
  // Setting the object's models value should create a RELS-EXT
  $object->models = 'islandora:findingAidCModel';
echo $title."<hr><hr>";

  // These site mappings are based on the ead filename.
  $dsid = 'EAD';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new EAD datastream
  if ($datastream->label <> $ead_marc['ead']) {
    $datastream->label = $ead_marc['ead'];
  }
  if ($datastream->mimeType <> 'application/xml') {
    $datastream->mimeType = 'application/xml';
  }
  $datastream->setContentFromFile($ead);
  $object->ingestDatastream($datastream);

  // If the object IS only constructed, ingesting it here also ingests the datastream.
  if (!$object_existed) {
    try {
//      $repository->ingestObject($object);
      echo "SKIP CODE TO create new object $id<br>";
    } catch (Exception $e) {
      echo 'Caught exception: ',  $e->getMessage(), "\n";
      return 'ERROR: new object ' . $id . ' could not be ingested - ' . $e->getMessage();
    }
  }
  else {
    echo "existing object updated $id<br>";
    $mvto_file = EAD_FOLDER . '/done/' . $ead_marc['ead'];
    $mv = 'mv ' . $ead . ' ' . $mvto_file;
    exec($mv);
  }
  return 'EAD = ' . $ead . ', ' .
         'PID = ' . $object->id . "<br>";
}

function _get_xpath_nodeValue($doc_xml, $query) {
  $doc = new DOMDocument();
  if (!$doc->loadXML($doc_xml)) {
    die('in _get_xpath_nodeValue, could not load XML - ' . htmlspecialchars(substr($doc_xml, 0, 99)) . '...');
    return '';
  }
  $xpath = new DOMXPath($doc);
  $xpath->registerNamespace('d', 'urn:isbn:1-931666-22-9');
  $results = $xpath->query($query);
  $nodeValue = NULL;
  // the value coming from the XML usually has a bunch of extra spaces and potential line feeds
  foreach ($results as $result) {
    $nodeValue = str_replace(array("\t", "\r", "\n"), "", trim($result->nodeValue));
  }
  while (strstr($nodeValue, "  ")) {
    $nodeValue = str_replace("  ", " ", $nodeValue);
  }
  return $nodeValue;
}

function _get_files($path, $filename_wildcard = '') {
  $results = array();
  if ($handle = opendir($path)) {
    while (false !== ($entry = readdir($handle))) {
      if ($entry != "." && $entry != ".." && strstr($entry, $filename_wildcard)) {
        $results[] = $entry;
      }
    }
    closedir($handle);
  }
  return $results;
}

function _get_ead_id($ead) {
  $nodeValue = NULL;
  $doc0 = new DOMDocument();
  if (@$doc0->load(EAD_FOLDER . '/' . $ead)) {
    $xpath = new DOMXPath($doc0);
    $xpath->registerNamespace('d', 'urn:isbn:1-931666-22-9');

    $query = '//d:eadid';
    $results = $xpath->query($query);
    foreach ($results as $result) {
      $nodeValue = trim($result->nodeValue);
    }
  }
  else {
    die('in _get_ead_id, could not load - ' . EAD_FOLDER . '/' . $ead);
  }
  return $nodeValue;
}

