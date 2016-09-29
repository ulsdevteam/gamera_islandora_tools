<?php

/**
 * This script will scan the EAD_FOLDER for all *.ead.xml files and MARC_FOLDER
 * for the related *.marc.xml files - and ingest them (or update them if they already exist).
 * Call from http://gamera.library.pitt.edu/devel/php with the following line of code
 *   include_once('/usr/local/src/islandora_tools/finding_aids/process_eads.php');
 */

define('EAD_FOLDER', '/usr/local/src/EAD-Delivery'); 
define('MARC_DERIVED_FOLDER', '/usr/local/src/MARC/Derived');
define('MARC_FOLDER', '/usr/local/src/MARC'); 
define('MEMBEROFSITE_NAMESPACE', variable_get('upitt_islandora_memberofsite_namespace')); 
define('FINDING_AIDS_COLLECTION', 'info:fedora/islandora:manuscriptCollection');
// XML Transformation
define('TRANSFORM_STYLESHEET', dirname(__FILE__).'/xsl/MARC21slim2MODS3-5.xsl');

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
$marc_file = MARC_DERIVED_FOLDER . '/finding-aids.xml'; // _get_files(MARC_FOLDER, '-marc.xml');

$marc_DOM = new DOMDocument();
if (!@$marc_DOM->load($marc_file)) {
  return 'ERROR: MARC did not load for this file : ' . $marc_file;
}

$missing = $finding_aids = array();
$i = 0;
foreach ($ead_files as $idx => $ead) {
  $ead_name = str_replace('-ead.xml', '', $ead);
  $marc_filename = str_replace('-ead.xml', '-marc.xml', $ead);
  if ($i < $process_exactly) {
  //  $s1 = 'marc_filename = ' . $marc_filename;
    $marc = NULL;
    $ead_id = _get_ead_id($ead);
    $s .= 'given EAD file "' . $ead . '", ead_id = ' . $ead_id . "\n";
    $s2 = 'given EAD file "' . $ead . '", ead_id = ' . $ead_id;
//  create MARC from the large marc container xml
    $parts = explode(".", $ead_name);
  // Generate the fuzzy EAD filename match to search for in the large MARC file.
    if (count($parts) > 1) {
      if (count($parts) == 2) {
        $xpath_value_ead_name = $parts[0] . ' ' . $parts[1];
      } elseif (count($parts) == 3) {
        $xpath_value_ead_name = $parts[0] . ' ' . $parts[1] . ':' . $parts[2];
      } elseif (count($parts) == 4) {
        $xpath_value_ead_name = $parts[0] . ' ' . $parts[1] . ':' . $parts[2];
//      $xpath_value_ead_name = $parts[0] . ' ' . $parts[1] . ':' . $parts[2] . '-' . $parts[3];
      } elseif (count($parts) > 4) {
        $xpath_value_ead_name = $parts[0] . ' ' . $parts[1] . ':' . $parts[2];
/*      $dash_parts = $parts;
      // Shift off 3 elements for the implode to work on the remaining items above 2
      array_shift($dash_parts);array_shift($dash_parts);array_shift($dash_parts);
      $xpath_value_ead_name = $parts[0] . ' ' . $parts[1] . ':' . $parts[2] . '-' . implode("-", $dash_parts);
*/
      }
    // since this is not xpath 2.0, need to use string-lengths to fake the "ends-with"
      $marc_query = '/m:collection/m:record[m:datafield[@tag="856"]/m:subfield[@code="u"]["' . $ead_id . '" = substring(., string-length(.)-string-length("' . $ead_id . '")+1)]]';
//    $marc_query = '/m:collection/m:record[m:datafield[@tag="099"]/m:subfield[@code="a"][starts-with(.,"' . $starts_with . '") and ends-with(.,"' . $ends_with . '")]]';
//    $marc_query = '/m:collection/m:record[m:datafield[@tag="099"]/m:subfield[@code="a"]="'. $xpath_value_ead_name .'"]';
//    echo $ead_name . "\n";
      if (_save_marc($marc_query, $marc_DOM, $marc_filename)) {
        $marc = $marc_filename;
        $lines_good[] = $s2;
        $lines_good[] = $xpath_value_ead_name;
        $lines_good[] = 'query = ' . $marc_query;
        $s .= 'MARC found for EAD : ' . $ead_id . "\n";
        $count_good++;
      } else {
        // look for the matching MARC file in the MARC_FOLDER
        $AT_marc_filename = MARC_FOLDER . '/' . $marc_filename;
        if (file_exists($AT_marc_filename)) {
          $s .= 'found matching MARC by filename : ' . $AT_marc_filename . "\n";
          copy($AT_marc_filename, MARC_DERIVED_FOLDER . '/' . $marc_filename);
          $marc = $marc_filename;
          $lines_good[] = $s2;
          $s .= '_save_marc did not find anything for the query ' . $marc_query . ' but found matching MARC file : ' . $marc_filename . "\n";
          $count_good++;
        } else {
          $lines_bad[] = $s2;
          $s .= '_save_marc did not find anything for the query ' . $marc_query . ' AND COULD NOT FIND matching MARC by filename : ' . $AT_marc_filename . "\n";
          $count_bad++;
        }
      }
    }
    else {
      $s .= $ead_name . ' could not be found in the marc' . "\n";
      $lines_bad[] = $s2;
      $lines_bad[] = $ead_name . ' could not be found in the marc';
      $count_bad++;
    }
/*  $marc = (!(array_search($marc_filename, $marcs) === FALSE) ? $marc_filename : NULL);
  if (!$marc) {
    $missing[] = $ead;
  } */
    $ead_marc = array('ead' => $ead,
                      'marc' => $marc);
    $s .= '[' . $i . '] ' . process_finding_aid_xml($ead_id, $ead_marc, $repository, FINDING_AIDS_COLLECTION, $solr) . "\n";
  }
  else {
    $s .= '[' .$i . '] skipped (' . $ead_name . ', ' . $marc_filename . ')' . "\n";
  }
  $i++;
}
echo "<b>" . number_format($count_good) . " good MARC ~ EAD</b></br>";
echo "<b>" . number_format($count_bad) . " bad MARC ~ EAD</b></br>";

echo "<pre>";
die(implode("\n", $lines_good) . "</pre><hr><pre>" . implode("\n", $lines_bad) . "<hr>" . $s);


//echo 'Missing MARC files ' . "\n" . implode("\n", $missing) . "\n-------------------------------------------\n";
// echo 'Files ' . "\n" . print_r($finding_aids, true) . "\n-------------------------------------------\n";


function process_finding_aid_xml($ead_id, $ead_marc, $repository, $collection, $solr) {
  module_load_include('inc', 'islandora_solution_pack_manuscript', 'includes/ead_upload.form');
  $ead = EAD_FOLDER . '/' . $ead_marc['ead'];
  $marc = MARC_DERIVED_FOLDER . '/' . $ead_marc['marc'];

  // Schema check
  $doc0 = new DOMDocument();
  if (!@$doc0->load($ead) || (!@$doc0->schemaValidate(dirname(__FILE__) .'/schema/ead.xsd'))) {
    return 'ERROR: Schema did not validate for this file : ' . $ead . "\n";
  }

  $doc_xml = $doc0->saveXML();

  // use the $ead_id to make the PID
  $id = 'pitt:' . $ead_id;

  // Get the next Id from the system under the "finding-aid" namespace.
/*  $id = _get_existing_PID(str_replace(array('-ead.xml', '_ead.xml'), '', $ead_marc['ead']), $solr);
  if (!$id) {
    $id = 'pitt:' . str_replace(":", ".", $repository->getNextIdentifier('finding-aid'));
  }
*/
/*
  // Get the Title field for setting the object ID to values like "AIS.1997.36"
  $ead_num_value = _get_xpath_nodeValue($doc_xml, '//d:filedesc/d:titlestmt/d:titleproper');
  $id = 'pitt:' . (($ead_num_value) ? $ead_num_value : str_replace(":", ".", str_replace(array('-ead.xml', '_ead.xml'), '', $ead_marc['ead'])));
*/

  $object = islandora_object_load($id);
  if (!$object) {
    $object = $repository->constructObject($id);
    $object_existed = FALSE;
  }
  else {
    $object_existed = TRUE;
  }

  // Get the title from the MARC file
  $title = _get_xpath_nodeValue($doc_xml, '//d:filedesc/d:titlestmt/d:titleproper/d:num');
  $object->label = ($title) ? $title : 'EAD Title';

  // Setting the object's models value should create a RELS-EXT	
  $object->models = 'islandora:findingAidCModel';

  // Setting the isMemberOfCollection
  if ($collection) { 
    $object->relationships->add(FEDORA_RELS_EXT_URI, 'isMemberOfCollection', $collection);
  }

  add_site_mappings($object, $ead_marc['ead']);

  $dsid = 'EAD';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new EAD datastream
  $datastream->label = $ead_marc['ead'];
  $datastream->mimeType = 'application/xml';
  $datastream->setContentFromFile($ead);
  $object->ingestDatastream($datastream);

  $mods_filename = doMODSTransform($marc);
  $dsid = 'MODS';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new MODS datastream
  $datastream->label = 'MODS Record';
  $datastream->mimeType = 'text/xml';
  $datastream->setContentFromFile($mods_filename);
  $object->ingestDatastream($datastream);
  @unlink($tempFilename);

  $dsid = 'MARC';
  $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
  // update existing or set new MARC datastream
  $datastream->label = $ead_marc['marc'];
  $datastream->mimeType = 'application/xml';
  $datastream->setContentFromFile($marc);
  $object->ingestDatastream($datastream);

  // If the object IS only constructed, ingesting it here also ingests the datastream.
  if (!$object_existed) {
    $repository->ingestObject($object);
  }
  // $object->relationships->add(FEDORA_RELS_EXT_URI, 'isMemberOf', $finding_aid->id);

  return 'EAD = ' . $ead . "\n" .
         'MARC = ' . $marc . "\n" . 
         'PID = ' . $object->id . "\n";
}

function _get_xpath_nodeValue($doc_xml, $query) {
  $doc = new DOMDocument();
  $doc->loadXML($doc_xml);
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
  $site_ead_maps = array(
    // HistPitt
    'info:fedora/pitt:site.historic-pittsburgh'=>
      array('AIS', 'DAR', 'UE'),
    // Documenting
    'info:fedora/pitt:site.documenting-pitt'=>
      array('UA'),
    // Digital
    'info:fedora/pitt:site.uls-digital-collections' =>
      array('AIS', 'ASP', 'CAM', 'CASEY', 'CTC', 'DAR', 'SC', 'UA', 'UE'));

  // skipping for now: AACCWP, EUDC, FFAL, HAMM, LATINAMER,
  $prefix = substr($ead_filename, 0, strpos($ead_filename, '.'));
  foreach ($site_ead_maps as $site => $site_mappings) {
    foreach ($site_mappings as $site_mapping) {
      if ($site_mapping == $prefix) {
        $object->relationships->add(MEMBEROFSITE_NAMESPACE, 'isMemberOfSite', $site);
      }
    }
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
  return $nodeValue;
}

function _save_marc($query, $marc_DOM, $marc_filename) {
  $xpath = new DOMXPath($marc_DOM);
  $xpath->registerNamespace('m', 'http://www.loc.gov/MARC21/slim');
  $results = $xpath->query($query);
  $retval = FALSE;
//  if ($results) {
    foreach ($results as $result) {
      $retval = TRUE;
      echo MARC_DERIVED_FOLDER . '/' . $marc_filename . "\n";
      file_put_contents(MARC_DERIVED_FOLDER . '/' . $marc_filename, _wrap($result));
    }
//  }
  return $retval;
}

function _wrap($xml) { 
  $dom = new DOMDocument();
//   $dom->registerN
  $node = $dom->importNode($xml, TRUE);
  $dom->appendChild($node);

  $xml_as_string = str_replace(array(' xmlns:marc="http://www.loc.gov/MARC21/slim"', '<?xml version="1.0"?>'), '', $dom->saveXML());
  return '<?xml version="1.0" encoding="UTF-8"?>
<marc:collection xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.loc.gov/MARC21/slim http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd">
' . $xml_as_string . '
</marc:collection>';
}

// COPIED directly from islandora_batch/includes/islandora_scan_batch.inc.
/**
  * Run an XSLT, and return the results.
  *
  * @param array $info
  *   An associative array of parameters, containing:
  *   - input: The input XML in a string.
  *   - xsl: The path to an XSLT file.
  *   - php_functions: Either a string containing one or an array containing
  *     any number of functions to register with the XSLT processor.
  *
  * @return string
  *   The transformed XML, as a string.
  */
function _runXslTransform($info) {
  $xsl = new DOMDocument();
  $xsl->load($info['xsl']);
  $input = new DOMDocument();
  $input->loadXML($info['input']);

  $processor = new XSLTProcessor();
  $processor->importStylesheet($xsl);

  if (isset($info['php_functions'])) {
    $processor->registerPHPFunctions($info['php_functions']);
  }

  // XXX: Suppressing warnings regarding unregistered prefixes.
  return $processor->transformToXML($input);
}

/**
 * This will run MARC to MODS transformation and save resultant MODS
 * to a temporary file.  Returns the filename for the new MODS file.
 */
function doMODSTransform($marc) {
  $marc_file = file_get_contents($marc);
  // Get the DC by transforming from MODS.
  $new_MODS = ($marc_file) ? _runXslTransform(
            array(
              'xsl' => TRANSFORM_STYLESHEET,
              'input' => $marc_file,
            )
          ) : '';
  $filename = tempnam("/tmp", "MODS_xml_derived_");
  // This file must be deleted in the process function that called this.
  file_put_contents($filename, $new_MODS);
  return $filename;
}

function _get_existing_PID($pid, $solr) {
  echo $solr . "<hr>" . $pid ."\n\n";
}
