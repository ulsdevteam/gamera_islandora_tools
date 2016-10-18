<?php

/**
 * This script will scan the EAD_FOLDER for all the Heinz Center *.ead.xml files.
 * and ingest them (or update them if they already exist).
 *
 * WE MAY OR MAY NOT BE ABLE TO OBTAIN MATCHING MARC XML FILES -- if we do not get the MARC, we must
 * generate a MODS from the EAD with at least enough values to be able to identify the finding aid when
 * it is displayed in the search results -- as well as to offer any facet functionality that may be applicable.
 *
 * Call from http://gamera.library.pitt.edu/devel/php with the following line of code
 *   include_once('/usr/local/src/islandora_tools/finding_aids/process_heinz_eads.php');
 */

error_log('started ' . date('H:i:s'));

define('EAD_FOLDER', '/usr/local/src/EAD-Delivery/HSWP-EAD/'); 
define('MEMBEROFSITE_NAMESPACE', variable_get('upitt_islandora_memberofsite_namespace')); 
// if FINDING_AIDS_COLLECTION is set, each ingested object would have an isMemberOfCollection relationship to this collection.
// define('FINDING_AIDS_COLLECTION', 'islandora:manuscriptCollection');
define('FINDING_AIDS_COLLECTION', 'pitt:finding-aids');
define('FINDING_AIDS_HEINZ_COLLECTION', 'pitt:fa.heinz');

// XML Transformations
define('TRANSFORM_PITT_IDENTIFIER', dirname(__FILE__).'/xsl/mods_add_pitt_identifier.xsl');
define('TRANSFORM_EAD2MODS_STYLESHEET', dirname(__FILE__).'/xsl/generic_ead_to_mods.xsl');

include_once(dirname(__FILE__) .'/../common/funcs.php');

// Allow this script to run until it is done ~ will certainly exceed 100 seconds.
set_time_limit(0);

// This variable controls how many of the total items are processed when this script runs.
// $process_exactly = PHP_INT_MAX;
$process_exactly = 1;


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


$missing = $finding_aids = array();
$i = 0;
foreach ($ead_files as $idx => $ead) {
echo $ead."<br>";
  $s0 = '';
  $ead_name = str_replace('-ead.xml', '', $ead);
  if ($i < $process_exactly) {
    $ead_id = _get_ead_id($ead);
    $call_result = process_finding_aid_xml($ead_id, $ead, $repository, $solr);
    if (strstr($call_result, 'ERROR:')) {
      $count_bad++;
    }
    else {    
      $count_good++;
    }
    $s0 .= '[' . $i . '] ' . process_finding_aid_xml($ead_id, $ead, $repository, $solr) . ' ';
  }
  else {
    $s0 .= '[' .$i . '] skipped (' . $ead_name . ')' . ' ';
  }
  $i++;
  $s .= $s0;
  if ($s0) {
    error_log($s0);
  }
}
echo "<html><head><title>test</title></head><body>";
echo "<b>" . number_format($count_good) . " good ~ EAD</b></br>";
echo "<b>" . number_format($count_bad) . " bad ~ EAD</b></br>";

echo "<pre>";
error_log('finished ' . date('H:i:s'));

die(implode('
', $lines_good) . "</pre><hr><pre>" . implode('
', $lines_bad) . "<hr>" . $s);



function process_finding_aid_xml($ead_id, $ead, $repository, $solr) {
  $ead = EAD_FOLDER . '/' . $ead;

  // Schema check
  $doc0 = new DOMDocument();
  if (!@$doc0->load($ead) || (!@$doc0->schemaValidate(dirname(__FILE__) .'/schema/ead.xsd'))) {
    return 'ERROR: Schema did not validate for this file : ' . $ead . "\n";
  }
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

  // Get the title from the EAD file
  $title = _get_xpath_nodeValue($doc_xml, '//d:filedesc/d:titlestmt/d:titleproper');
  $object->label = ($title) ? $title : $ead_id;

  // !!! since the Solr query defaults for Solr base filter is being used to filter out the findingAids for now, don't need to set these inactive
  //     PID:(pitt*) AND -RELS_EXT_hasModel_uri_ms:islandora:findingAidCModel
  // !!!
  // Set the State to Inactive for now --- 
  //  $object->state = 'I';

  // Setting the object's models value should create a RELS-EXT	
  $object->models = 'islandora:findingAidCModel';

  // Setting the isMemberOfCollection
  if (FINDING_AIDS_COLLECTION <> '') {
    _add_relationship_if_not_exists($object, 'isMemberOfCollection', FINDING_AIDS_COLLECTION, FEDORA_RELS_EXT_URI);
  }
  if (FINDING_AIDS_HEINZ_COLLECTION <> '') { 
    _add_relationship_if_not_exists($object, 'isMemberOfCollection', FINDING_AIDS_HEINZ_COLLECTION, FEDORA_RELS_EXT_URI);
  }

  add_site_mappings($object, $ead);

  $dsid = 'EAD';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new EAD datastream
  $datastream->label = $ead;
  $datastream->mimeType = 'application/xml';
  $datastream->setContentFromFile($ead);
  $object->ingestDatastream($datastream);

  _derive_and_ingest_mods($ead, $object);

  // If the object IS only constructed, ingesting it here also ingests the datastream.
  if (!$object_existed) {
    $repository->ingestObject($object);
  }

  return 'EAD = ' . $ead . ', ' .
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
  foreach ($results as $result) {
    $nodeValue = $result->nodeValue;
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
  _add_relationship_if_not_exists($object, 'isMemberOfSite', 'pitt:site.historic-pittsburgh', MEMBEROFSITE_NAMESPACE);
/*
  $site_ead_maps = array(
    // HistPitt
    'pitt:site.historic-pittsburgh'=>
      array('AIS', 'DAR', 'UE'),
    // Documenting
    'pitt:site.documenting-pitt'=>
      array('UA'),
    // Digital
    'pitt:site.uls-digital-collections' =>
      array('AIS', 'ASP', 'CAM', 'CASEY', 'CTC', 'DAR', 'SC', 'UA', 'UE'));

  // skipping for now: AACCWP, EUDC, FFAL, HAMM, LATINAMER,
  $prefix = substr($ead_filename, 0, strpos($ead_filename, '.'));
  foreach ($site_ead_maps as $site => $site_mappings) {
    foreach ($site_mappings as $site_mapping) {
      if ($site_mapping == $prefix) {
        _add_relationship_if_not_exists($object, 'isMemberOfSite', $site, MEMBEROFSITE_NAMESPACE);
      }
    }
  }
*/
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
  $new_MODS = ($new_MODS) ? _runXslTransformWithParam(
            array(
              'xsl' => TRANSFORM_PITT_IDENTIFIER,
              'input' => $marc_file,
              'param_name' => 'mods_identifier_pitt',
              'param_value' => $ead_id,
            )
          ) : '';

  $filename = tempnam("/tmp", "MODS_xml_derived_");
  // This file must be deleted in the process function that called this.
  file_put_contents($filename, $new_MODS);
  return $filename;
}

// Need the various MODS values required for displaying a search record result
function _derive_and_ingest_mods($ead, $object) {
  $ead_file = file($ead);
  $dsid = 'MODS';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new EAD datastream
  $datastream->label = 'MODS Record';
  $datastream->mimeType = 'text/xml';
  $mods_filename = _transform_ead_to_mods($ead_file);
  $datastream->setContentFromFile($mods_filename);
  $object->ingestDatastream($datastream);
}

function _transform_ead_to_mods($ead_file) {
  return _runXslTransform(
            array(
              'xsl' => TRANSFORM_EAD2MODS_STYLESHEET,
              'input' => $ead_file,
            )
          );
}

