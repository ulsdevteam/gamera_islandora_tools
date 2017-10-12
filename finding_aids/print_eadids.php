<?php

/**
 * This script will scan the EAD_FOLDER for all *.ead.xml files and MARC_FOLDER
 * for the related *.marc.xml files - and ingest them (or update them if they already exist).
 * Call from http://gamera.library.pitt.edu/devel/php with the following line of code
 *   include_once('/usr/local/src/islandora_tools/finding_aids/print_eadids.php');
 */

error_log('started ' . date('H:i:s'));

$skip_eads = array('');

define('EAD_FILE_ENDSWITH', '.xml');
define('MARC_FILE_ENDSWITH', '.xml');

define('EAD_FOLDER', '/usr/local/src/HSWP-EAD');
define('MARC_FOLDER', '/usr/local/src/HSWP-MARC');

include_once(dirname(__FILE__) .'/../common/funcs.php');

// Allow this script to run until it is done ~ will certainly exceed 100 seconds.
set_time_limit(0);

// This variable controls how many of the total items are processed when this script runs.
$process_exactly = PHP_INT_MAX;
// $process_exactly = 3;

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
$marcs = _get_files(MARC_DERIVED_FOLDER, MARC_FILE_ENDSWITH);

// Make this array all uppercase -- later the uppercase EAD filename will be searched for in this array
// and if it returns ONLY one key when searched, we can assume that the MARC filename is 100% match.
foreach ($marcs as $index => $marc_filename) {
  $uc_marcs[$index] = strtoupper($marc_filename);
}

$marc_file = MARC_FOLDER . '/marc_export.xml';
// echo "<h1>" . $marc_file . "</h1>";
$marc_DOM = new DOMDocument();
if (!@$marc_DOM->load($marc_file)) {
  return 'ERROR: MARC did not load for this file : ' . $marc_file;
}

sort($ead_files);

$missing = $finding_aids = array();
$i = 0;
foreach ($ead_files as $idx => $ead) {
  echo "<br><br>" . $ead . ", ";
 if (array_search($ead, $skip_eads) === FALSE) {
  $s0 = '';
  $ead_name = str_replace(EAD_FILE_ENDSWITH, '', $ead);
  $marc_filename = str_replace(EAD_FILE_ENDSWITH, MARC_FILE_ENDSWITH, $ead);

  $uc_marc_filename = strtoupper($marc_filename);
  if ($i < $process_exactly) {
    $marc = NULL;
    $ead_id = _get_ead_id($ead);

    // since this is not xpath 2.0, need to use string-lengths to fake the "ends-with"
    $hack_ead_id = ($ead_id == 'US-QQS-mss579') ? 'US-QQS-MSS579 ' : $ead_id;
    if ($ead_id == 'US-QQS-MSS 895') { 
      $hack_ead_id = 'US-QQS-MSS895';
    }
    $u_hack_ead_id = u_hack($hack_ead_id);
    $l_hack_ead_id = l_hack($hack_ead_id);
    $l2_hack_ead_id = l2_hack($hack_ead_id);
    $marc_query = '/marc:collection/marc:record[marc:datafield[@tag="856"]/marc:subfield[@code="u"]["' . $hack_ead_id . '" = substring(., string-length(.)-string-length("' . $hack_ead_id . '")+1)]]';
    $marc_query2 = '/marc:collection/marc:record[marc:datafield[@tag="856"]/marc:subfield[@code="u"]["' . $u_hack_ead_id . '" = substring(., string-length(.)-string-length("' . $u_hack_ead_id . '")+1)]]';
    $marc_query3 = '/marc:collection/marc:record[marc:datafield[@tag="856"]/marc:subfield[@code="3"]["' . $l_hack_ead_id . '" = substring(., string-length(.)-string-length("' . $l_hack_ead_id . '")+1)]]';
    $marc_query4 = '/marc:collection/marc:record[marc:datafield[@tag="856"]/marc:subfield[@code="u"]["' . $l2_hack_ead_id . '" = substring(., string-length(.)-string-length("' . $l2_hack_ead_id . '")+1)]]';
    // Look in the tag 099 for TWO separate values
    $marc_099_query_a = '/marc:collection/marc:record[marc:datafield[@tag="099"]/marc:subfield[text() = "' . $tag099[$ead]['a'] . '"] and marc:datafield[@tag="099"]/marc:subfield[text() = "' . $tag099[$ead]['b'] . '"]]';
    //  echo "<hr><b>" . $ead_id ." ... [" . $hack_ead_id . "]</b><br>";
    if ($saved_marc_xml = _save_marc($marc_query, $marc_DOM, $marc_filename, $marc_query2, $marc_query3, $marc_query4, $marc_099_query_a)) {
      $marc = $marc_filename;
      echo "<b>marc = " . $marc . "</b><br>";
    }

    $s2 = $ead_id;
    echo $ead_id . "<hr>";

    $ead_marc = array('ead' => $ead,
                      'marc' => $marc);

    $s0 .= details_from_ead($ead_id, $ead_marc, $repository, $solr);

    //."\n" . '<a href="http://gamera.library.pitt.edu/islandora/object/pitt:' . $ead_id . '/manage/datastreams">link</a>';
    echo $s0;
  }
  else {
//    $s0 .= '[' .$i . '] skipped (' . $ead_name . ', ' . $marc_filename . ')' . ' ';
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
echo "<b>" . number_format($count_good) . " good MARC ~ EAD</b></br>";
echo "<b>" . number_format($count_bad) . " bad MARC ~ EAD</b></br>";

echo "<pre>";
error_log('finished ' . date('H:i:s'));

die(implode('
', $lines_good) . "</pre><hr><pre>" . implode('
', $lines_bad) . "<hr>" . $s);


function _save_marc($query, $marc_DOM, $marc_filename, $query2, $query3, $query4, $marc_099_query_a) {
  //  echo $query ."<br>" . $marc_099_query_a . "<br>" . $query2 . "<br>" . $query3 . "<br>" . $query4 . "<br><hr>";
  $xpath = new DOMXPath($marc_DOM);
  $results = $xpath->query($query);
  $retval = FALSE;
  foreach ($results as $result) {
    $retval = TRUE;
    $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
  }
  if (!$retval) {
    $results = $xpath->query($marc_099_query_a);
    foreach ($results as $result) {
      $retval = TRUE;
      $file_a = _wrap($result);
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, $file_a);
    }
  }
  if (!$retval) {
    $results = $xpath->query($query2);
    foreach ($results as $result) {
      $retval = TRUE;
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    }
  }
  if (!$retval) {
    $results = $xpath->query($query3);
    foreach ($results as $result) {
      $retval = TRUE;
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    }
  }
  if (!$retval) {
    $results = $xpath->query($query4);
    foreach ($results as $result) {
      $retval = TRUE;
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    }
  }
  echo "found marc? [" . ($retval ? "yes" : "no") . "]";
  return $retval;
}


function details_from_ead($ead_id, $ead_marc, $repository, $solr) {
  if (!isset($ead_marc['ead'])) {
    echo "for ead_id = " . $ead_id . ", no ead passed <pre>" . print_r($ead_marc, true) . "</pre>";
    return;
  }
  $ead = EAD_FOLDER . '/' . $ead_marc['ead'];
  $marc = MARC_DERIVED_FOLDER . '/' . $ead_marc['marc'];
  // Schema check
  $doc0 = new DOMDocument();

  if (!@$doc0->load($ead) || (!@$doc0->schemaValidate(dirname(__FILE__) .'/schema/ead_explicit_xsd.xsd'))) {  //  ead.xsd'))) {
    return 'ERROR: Schema did not validate for this file : ' . $ead . "<br>";
  }
  echo " - schema good<br>";

  $doc_xml = $doc0->saveXML();
  // use the $ead_id to make the PID
  $id = 'pitt:' . $ead_id;
  $object = islandora_object_load($id);
  if (!$object) {
    //    $object = $repository->constructObject($id);
    echo "would need to construct new object " . $id . "<br>";
    $object_existed = FALSE;
  }
  else {
    $object_existed = TRUE;
  }
  // Get the title from the MARC file
  $title = _get_xpath_nodeValue($doc_xml, '//d:filedesc/d:titlestmt/d:titleproper');
  echo ($object_existed ? 
    '<a href="http://gamera.library.pitt.edu/islandora/object/pitt:' . $ead_id . '/viewer">' . $title. "</a>" :
    $title . " <b>does not exist</b>") . 
    "<hr>";


  if ($object_existed) {
    // This will update the DC record by transforming the current MODS xml.
    $mods_file = $object['MODS']->content;
    if ($mods_file == '' && !$object_existed) {
      echo "PID " . $id . " did not exist<br>";
    }
  }
  return '';
}

function _wrap($xml) {
  $dom = new DOMDocument();
  $node = $dom->importNode($xml, TRUE);
  $dom->appendChild($node);
  $xml_as_string = str_replace(array(' xmlns:marc="http://www.loc.gov/MARC21/slim"', '<?xml version="1.0"?>'), '', $dom->saveXML());
  return '<?xml version="1.0" encoding="UTF-8"?>
<marc:collection xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">
' . $xml_as_string . '
</marc:collection>';
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

function u_hack($in) { 
  $parts = explode('-', $in);
  $r = array();
  foreach ($parts as $i => $p) {
    if ($i == (count($parts) - 1)) {
      $r[] = strtoupper($p);
    }
    else { 
      $r[] = $p;
    }
  }
  return implode("-", $r);
}

function l_hack($in) {
  $parts = explode('-', $in);
  $r = array();
  foreach ($parts as $i => $p) {
    if ($i == (count($parts) - 1)) {
      $r[] = strtolower($p);
    }
    else {
      $r[] = $p;
    }
  }
  return implode("-", $r);
}

function l2_hack($in) {
  $parts = explode('-', $in);
  $r = array();
  foreach ($parts as $i => $p) {
    if ($i >= (count($parts) - 2)) {
      $r[] = strtolower($p);
    }
    else {
      $r[] = $p;
    }
  }
  return implode("-", $r);
}
