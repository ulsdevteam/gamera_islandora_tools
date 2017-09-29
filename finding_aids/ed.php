<?php

/**
 * This script will scan the EAD_FOLDER for all *.ead.xml files and MARC_FOLDER
 * for the related *.marc.xml files - and ingest them (or update them if they already exist).
 * Call from http://gamera.library.pitt.edu/devel/php with the following line of code
 *   include_once('/usr/local/src/islandora_tools/finding_aids/process_eads.php');
 */

error_log('started ' . date('H:i:s'));

$skip_eads = array('AACCWP-ead.xml');

define('EAD_FOLDER', '/usr/local/src/EAD-Delivery'); 
define('MARC_DERIVED_FOLDER', '/usr/local/src/MARC/Derived');
define('MARC_FOLDER', '/usr/local/src/MARC'); 
define('MEMBEROFSITE_NAMESPACE', variable_get('islandora_memberofsite_namespace', 'http://digital.library.pitt.edu/ontology/relations#')); 

// XML Transformations
define('TRANSFORM_STYLESHEET', dirname(__FILE__).'/xsl/MARC21slim2MODS3-5.xsl');
define('TRANSFORM_PITT_IDENTIFIER', dirname(__FILE__).'/xsl/mods_add_pitt_identifier.xsl');
define('TRANSFORM_MODS2DC_STYLESHEET', dirname(__FILE__).'/../common/xsl/mods_to_dc.xsl');

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
$ead_files = _get_files(EAD_FOLDER, '-ead.xml');
$marcs = _get_files(MARC_DERIVED_FOLDER, '-marc.xml');

// Make this array all uppercase -- later the uppercase EAD filename will be searched for in this array
// and if it returns ONLY one key when searched, we can assume that the MARC filename is 100% match.
foreach ($marcs as $index => $marc_filename) {
  $uc_marcs[$index] = strtoupper($marc_filename);
}
$marc_file = MARC_DERIVED_FOLDER . '/2017_01_12_FindingAidsComplete.xml'; //FindingAidsCorrected.xml'; // _get_files(MARC_FOLDER, '-marc.xml');
echo "<h1>" . $marc_file . "</h1>";
sort($ead_files);
$marc_DOM = new DOMDocument();
if (!@$marc_DOM->load($marc_file)) {
  return 'ERROR: MARC did not load for this file : ' . $marc_file;
}

// $pids = array('pitt:US-PPiU-ffal002','pitt:US-PPiU-ffal001','pitt:US-PPiU-ais199616','pitt:US-PPiU-ais200619b','pitt:US-PPiU-ais201506');

$missing = $finding_aids = array();
$i = 0;
foreach ($ead_files as $idx => $ead) {
 if (array_search($ead, $skip_eads) === FALSE) {
  $s0 = '';
  $ead_name = str_replace('-ead.xml', '', $ead);
  $marc_filename = str_replace('-ead.xml', '-marc.xml', $ead);

  $uc_marc_filename = strtoupper($marc_filename);
  if ($i < $process_exactly) {
    $marc = NULL;
    $ead_id = _get_ead_id($ead);
    //# uncomment next line if the code should only process objects that return TRUE for a function call 
    echo "working on " . $ead_id ."<br>";
    //# uncomment next line for SINGLE_ITEM 
//    if (!(array_search('pitt:' . $ead_id, $pids) === FALSE)) {
    // if ($ead_id == 'US-PPiU-ais199616') {
    $s0 .= 'given EAD file "' . $ead . '", ead_id = ' . $ead_id . ' ';
    $s2 = 'given EAD file "' . $ead . '", ead_id = ' . $ead_id;

    // since this is not xpath 2.0, need to use string-lengths to fake the "ends-with"
    $u_hack_ead_id = u_hack($ead_id);
    $l_hack_ead_id = l_hack($ead_id);
    $l2_hack_ead_id = l2_hack($ead_id);
    $marc_query = '/m:collection/m:record[m:datafield[@tag="856"]/m:subfield[@code="u"]["' . $ead_id . '" = substring(., string-length(.)-string-length("' . $ead_id . '")+1)]]';
    $marc_query2 = '/m:collection/m:record[m:datafield[@tag="856"]/m:subfield[@code="u"]["' . $u_hack_ead_id . '" = substring(., string-length(.)-string-length("' . $u_hack_ead_id . '")+1)]]';
    $marc_query3 = '/m:collection/m:record[m:datafield[@tag="856"]/m:subfield[@code="u"]["' . $l_hack_ead_id . '" = substring(., string-length(.)-string-length("' . $l_hack_ead_id . '")+1)]]';
    $marc_query4 = '/m:collection/m:record[m:datafield[@tag="856"]/m:subfield[@code="u"]["' . $l2_hack_ead_id . '" = substring(., string-length(.)-string-length("' . $l2_hack_ead_id . '")+1)]]';

    if ($saved_marc_xml = _save_marc($marc_query, $marc_DOM, $marc_filename, $marc_query2, $marc_query3, $marc_query4)) {
      $marc = $marc_filename;
      $lines_good[] = $s2;
      //  $lines_good[] = 'query = ' . $marc_query;
      $s0 .= 'MARC found for EAD : ' . $ead_id . ' (' . $saved_marc_xml . ')';
      $count_good++;
    } else {
      // Look for the matching MARC file in the MARC_FOLDER.  Due to case sensitive filenames, there were some MARC that did not match
      // the filenames based on the EAD name.  Set up the initial MARC filename using the "-ead" conversion to "-marc".
      $AT_marc_filename = MARC_FOLDER . '/' . $marc_filename;
      // Look for a case-insensitive match from the $uc_marcs (uppercase) array... if it returns ONLY 1 filename, then it is the matching
      // MARC filename.
      $marc_keys = array_keys($uc_marcs, $uc_marc_filename);
      if (count($marc_keys) == 1) {
        $AT_marc_filename = MARC_FOLDER . '/' . $marcs[$marc_keys[0]];
      }

      if (file_exists($AT_marc_filename)) {
        $s0 .= 'found matching MARC by filename : ' . $AT_marc_filename . ' ';
        $marc = (copy($AT_marc_filename, MARC_DERIVED_FOLDER . '/' . $marc_filename)) ? $marc_filename : NULL;
        $lines_good[] = $s2;
        $s0 .= '_save_marc did not find anything for the query ' . $marc_query . ' but found matching MARC file : ' . $marc_filename . ' ';
        $count_good++;
      } else {
        // if the MARC was derived earlier, try to use that one.
        $marc = file_exists(MARC_DERIVED_FOLDER . '/' . $marc_filename) ? $marc_filename : NULL;
        $lines_bad[] = $s2;
        $s0 .= '_save_marc did not find anything for the query ' . $marc_query . ' AND COULD NOT FIND matching MARC by filename : ' . $AT_marc_filename . ' ';
        $count_bad++;
      }
    }
    echo $s2."\n" . '<a href="http://gamera.library.pitt.edu/islandora/object/pitt:' . $ead_id . '/manage/datastreams">link</a>';
    $ead_marc = array('ead' => $ead,
                      'marc' => $marc);
    $s0 .= (!is_null($marc)) ? '[' . $i . '] ' . process_finding_aid_xml($ead_id, $ead_marc, $repository, $solr) . ' ' : 
      '<br>[' . $i . '] no MARC for ' . $ead;
    echo $s0;

    //# uncomment next line for SINGLE_ITEM 
//    }
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



function process_finding_aid_xml($ead_id, $ead_marc, $repository, $solr) {
  echo "<hr> in process_finding_aid_xml($ead_id)<br>";
  $ead = EAD_FOLDER . '/' . $ead_marc['ead'];
  $marc = MARC_DERIVED_FOLDER . '/' . $ead_marc['marc'];

  // Schema check
  $doc0 = new DOMDocument();
  echo "checking schema of ead XML";
  if (!@$doc0->load($ead) || (!@$doc0->schemaValidate(dirname(__FILE__) .'/schema/ead_explicit_xsd.xsd'))) {  //  ead.xsd'))) {
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

  // These site mappings are based on the ead filename.
  add_site_mappings($object, $ead_marc['ead']);

/*
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
*/
/*
  $mods_filename = doMODSTransform($marc, $ead_id);
  $dsid = 'MODS';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new MODS datastream
  if ($datastream->label <> 'MODS Record') {
    $datastream->label = 'MODS Record';
  }
  if ($datastream->mimeType <> 'text/xml') {
    $datastream->mimeType = 'text/xml';
  }
  $datastream->setContentFromFile($mods_filename);
  $object->ingestDatastream($datastream);
  $mods_file = $datastream->content;

  @unlink($mods_filename);
  $dsid = 'MARC';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new MARC datastream
  if ($datastream->label <> $ead_marc['marc']) {
    $datastream->label = $ead_marc['marc'];
  }
  if ($datastream->mimeType <> 'application/xml') {
    $datastream->mimeType = 'application/xml';
  }
  $datastream->setContentFromFile($marc);
  $object->ingestDatastream($datastream);

  // This will update the DC record by transforming the current MODS xml.
  $mods_file = $object['MODS']->content;
  if ($mods_file == '' && !$object_existed) {
    echo "PID:pitt" . $ead_id . " did not exist<br>";
  }
  else {
    doDC($object, $mods_file);
  }

  // If the object IS only constructed, ingesting it here also ingests the datastream.
  if (!$object_existed) {
    $repository->ingestObject($object);
  } 
*/
  return 'EAD = ' . $ead . ', ' .
         'MARC = ' . $marc . ', ' . 
         'PID = ' . $object->id;
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

function add_site_mappings($object, $ead_filename) {
  $site_ead_maps = array(
    // HistPitt
    'pitt:site.historic-pittsburgh'=>
      // most CAM finding aids should not be in histpitt, but one or two need to be manually be added to the site 
      array('AACCWP', 'AIS', 'CTC', 'DAR', 'UA'),
    // Documenting
    'pitt:site.documenting-pitt'=>
      array('UA'),
    // Digital
    'pitt:site.uls-digital-collections' =>
      array('AIS', 'ASP', 'CAM', 'CASEY', 'CTC', 'DAR', 'EAL', 'EUDC', 'FFAL', 'HAMM', 'LATINAMER', 'SC', 'UA', 'UE'));

  // skipping for now: AACCWP, EUDC, FFAL, HAMM, LATINAMER,
  $prefix = substr($ead_filename, 0, strpos($ead_filename, '.'));

  // Remove any collection relationship -- since this is no longer how they will be searched.
  $object->relationships->remove(FEDORA_RELS_EXT_URI, 'isMemberOfCollection', NULL);
  // Since the site mappings may change, remove any site mapping - if this was run before.
  $object->relationships->remove(MEMBEROFSITE_NAMESPACE, 'isMemberOfSite', NULL);

  foreach ($site_ead_maps as $site => $site_mappings) {
    foreach ($site_mappings as $site_mapping) {
      if ($prefix == 'UE' && $site == 'pitt:site.historic-pittsburgh') {
echo "XX " . $ead_filename . "\n";
      }
      if ($site_mapping == $prefix) {
        _add_relationship_if_not_exists($object, 'isMemberOfSite', $site, MEMBEROFSITE_NAMESPACE);
      }
    }
  }
}

function _add_relationship_if_not_exists($object, $relationship, $value, $namespace) {
  // get the current relationships
  $rels = $object->relationships->get($namespace, $relationship);
  $existed = FALSE;
  foreach ($rels as $rel) {
    $existed |= (isset($rel['object']['value']) && $rel['object']['value'] == $value);
  }
  if (!$existed) {
    $object->relationships->add($namespace, $relationship, $value);
  }
  if ($relationship == 'isMemberOfSite') {
    $object->relationships->remove($namespace, 'isMemberOfSite', 'info:fedora/' . $value);
  }
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

function _save_marc($query, $marc_DOM, $marc_filename, $query2, $query3, $query4) {
  $xpath = new DOMXPath($marc_DOM);
  $xpath->registerNamespace('m', 'http://www.loc.gov/MARC21/slim');
  $results = $xpath->query($query);
  $retval = FALSE;
  foreach ($results as $result) {
    $retval = TRUE;
    $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
  }
  if (!$retval) {
    $results = $xpath->query($query2);
    $retval = FALSE;
    foreach ($results as $result) {
      $retval = TRUE;
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    }
  }
  if (!$retval) {
    $results = $xpath->query($query3);
    $retval = FALSE;
    foreach ($results as $result) {
      $retval = TRUE;
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    } 
  }
  if (!$retval) {
    $results = $xpath->query($query4);
    $retval = FALSE;
    foreach ($results as $result) {
      $retval = TRUE;
      $retval = file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    }
  }
  return $retval;
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

/* the $info variable has array elements:
              'xsl' => TRANSFORM_PITT_IDENTIFIER,
              'input' => $marc_file,
              'param_name' => 'mods_identifier_pitt',
              'param_value' => $ead_id,
*/
function _runXslTransformWithParam($info) {

  $xsl = new DOMDocument();
  $xsl->load($info['xsl']);
  $input = new DOMDocument();
  $input->loadXML($info['input']);

  $processor = new XSLTProcessor();
  $processor->importStylesheet($xsl);  

  if (isset($info['param_name']) && isset($info['param_value'])) {
    $processor->setParameter('', $info['param_name'], $info['param_value']);
  }

  if (isset($info['php_functions'])) {
    $processor->registerPHPFunctions($info['php_functions']);
  }

  // XXX: Suppressing warnings regarding unregistered prefixes.
  return $processor->transformToXML($input);
}

/**
 * This will run MARC to MODS transformation and save resultant MODS
 * to a temporary file. This also needs to set the 
 *   Date:mods_originInfo_type_display_dateOther_s, and
 *   Depositor: mods_name_depositor_namePart_ms 
 * so that the it appear for the search results item.
 *
 * Returns the filename for the new MODS file.
 */
function doMODSTransform($marc, $ead_id) {
  $marc_file = file_get_contents($marc);
  // Get the DC by transforming from MODS.
  $new_MODS = ($marc_file) ? _runXslTransform(
            array(
              'xsl' => TRANSFORM_STYLESHEET,
              'input' => $marc_file,
            )
          ) : '';
  $new_MODS = _inject_eadid($new_MODS, $ead_id);
// echo "<pre style='color:#78a'>".htmlspecialchars($new_MODS)."</pre>";

/*  $new_MODS = ($new_MODS) ? _runXslTransformWithParam(
            array(
              'xsl' => TRANSFORM_PITT_IDENTIFIER,
              'input' => $new_MODS,
              'param_name' => 'mods_identifier_pitt',
              'param_value' => $ead_id,
            )
          ) : ''; */

  $filename = tempnam("/tmp", "MODS_xml_derived_");
  // This file must be deleted in the process function that called this.
  file_put_contents($filename, $new_MODS);
  return $filename;
}

// Mostly COPIED from islandora_batch/includes/islandora_scan_batch.inc.
/**
 * Helper function to transform the MODS to get dc.
 */
function doDC($object, $mods_content) {
  $dsid = 'DC';
  $dc_datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  if ($dc_datastream->versionable <> TRUE) {
    $dc_datastream->versionable = TRUE;
  }
  if ($dc_datastream->mimetype <> 'application/xml') {
    $dc_datastream->mimetype = 'application/xml';
  }
  if ($dc_datastream->label <> 'DC Record') {
    $dc_datastream->label = 'DC Record';
  }

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

// HORRIBLE hack
function _inject_eadid($new_MODS, $ead_id) {
  $node_partial = '<identifier type="pitt">';
  $node_full = $node_partial . $ead_id . "</identifier>";
  if (strstr($new_MODS, $node_partial)) {
    return $new_MODS;
  } else {
    return str_replace("</mods>", $node_full."</mods>", $new_MODS);
  }
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
