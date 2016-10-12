<?php

// If this variable is set to any PID that is in the spreadsheet, all items above it will not be processed -- making it
// the first PID to be processed;
$first_pid = 'pitt:00vsep07';
// $first_pid = NULL;

// Set this variable if we only need to process a specific number of items from the spreadsheet.
$process_exactly_howmany = 1;
// $process_exactly_howmany = PHP_INT_MAX;

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/hopkins/process_csv_special.php');
//

// This array defines the mappings for the various fields provided in the hopkins_special_chars.csv spreadsheet.
// The geo fields have an "*" in them and this character would be replaced with numbers 1, 2, etc. until there are no more
// values found.
$mods_mappings = array(0 => array('final_title' => '/mods:mods/mods:titleInfo/mods:title'),
                       1 => array('part_number' => '/mods:mods/mods:titleInfo/mods:partNumber'),
                       2 => array('date' => '/mods:mods/mods:originInfo/mods:dateCreated'),
                       3 => array('date' => '/mods:mods/mods:originInfo/mods:dateOther[@type="display"]'),
                       4 => array('publisher' => '/mods:mods/mods:originInfo/mods:publisher'),
                       5 => array('pubplace' => '/mods:mods/mods:originInfo/mods:place/mods:placeTerm[@type="text"]'),
                       6 => array('County*' => '/mods:mods/mods:subject/mods:hierarchicalGeographic/mods:county'),
                       7 => array('City*' => '/mods:mods/mods:subject/mods:hierarchicalGeographic/mods:city'),
                       8 => array('CitySection*' => '/mods:mods/mods:subject/mods:hierarchicalGeographic/mods:citySection'),
                       9 => array('streets' => '/mods:mods/mods:note[@type="streets"]'),
                       10 => array('features' => '/mods:mods/mods:note[@type="features"]'),
                       99 => array('increment_idx' => NULL)
  );

// This is the logfile for this 
define('LOGFILE', '/usr/local/src/islandora_tools/hopkins/logfile');

// XML Transformation
define('TRANSFORM_STYLESHEET', dirname(__FILE__).'/transforms/remove_heir.xml');
define('TRANSFORM_MODS2DC_STYLESHEET', dirname(__FILE__).'/../common/xsl/mods_to_dc.xsl');

$last_container_created_id = -1;
$filename = dirname(__FILE__).'/hopkins_special_chars.csv';
$file = file($filename);
$headings_row = array(array_shift($file));
$headings = array_pop(array_map('str_getcsv', $headings_row));
$identifier_column_idx = array_search('identifier', $headings);
$csv = array_map('str_getcsv', $file);
$max_geo_idx = 2;

$s = "";
$i = 0;
$process = (is_null($first_pid) ? TRUE : FALSE);
foreach ($csv as $record_idx=>$row) {
  $pid = (isset($row[$identifier_column_idx]) ? 'pitt:' . $row[$identifier_column_idx] : '');
  // To set the start of processing at a specific PID value
  if (!$process && !is_null($first_pid)) {
    $process = $pid == $first_pid;
  }
  if ($process && $i < $process_exactly_howmany) {
    $pid = (isset($row[$identifier_column_idx]) ? 'pitt:' . $row[$identifier_column_idx] : '');
    $s .= '<h1>row#' . $record_idx . ' = ' . $pid . ' (' . (1 + $i) . '/' . (($process_exactly_howmany == PHP_INT_MAX) ? 'all' : $process_exactly_howmany) . ")</h1>";
    $islandora_object = islandora_object_load($pid);
    if (is_object($islandora_object)) {
      $s .= 'islandora_object(' . $pid . ') ' . (is_object($islandora_object) ? ' loaded ok' : ' NOT LOADED');
      $s .= ', ' . (is_object($islandora_object) && isset($islandora_object['MODS']) ? 'has MODS' : 'no MODS') . "\n";
      $record_data = array();
      foreach ($headings as $idx=>$column) {
        if ((isset($row[$idx]) && $row[$idx]<> '')) {
          // We only need the elements that have values.
          $record_data[$column] = $row[$idx];
        }
      }
      $record_data['increment_idx'] = TRUE;
      $s .= process_changes($islandora_object, $record_data, $mods_mappings);
    }
    $i++;
  }
  else {
    $s .= '.';
  }
}

// $s was displayed as full HTML when we'd die($s), but the devel module does not print any html tags.
// echo strip_tags(str_replace(array('<br>', '<hr>'), "\n", $s));
die($s);

/**
 * This will update the MODS, and finally call islandora_add_object() on the object which
 * should kick off the repository ingestObject() function which ultimately will call islandora_invoke_object_hooks()
 * on the object and islandora_invoke_object_hooks() on all of the datastreams of the object - which will cause the
 *  derivative creation.
 */
function process_changes($islandora_object, $record_data, $mods_mappings) {
  global $max_geo_idx;
  _log('PROCESSING ' . $islandora_object->id);
  $s = 'spreadsheet values for ' . $islandora_object->id . "\n";
  foreach ($record_data as $key => $val) {
    $s .= '[' . $key . '] = ' . $val . "<br>\n";
  }

  $mods = $islandora_object['MODS'];
  $tempFilename = tempnam("/tmp", "MODS_xml_initial_");
  // save the html body to a file -- datastream can load from the file
  $mods_file = $mods->getContent($tempFilename);
  $mods_file = implode("", file($tempFilename));
  $s .= '</pre><hr><h3>original MODS</h3><pre style="color:#227">' . htmlspecialchars($mods_file);

  // HACK - transform these to remove any hierarchicalGeographic tags and the wrappers before proceeding
  $mods_file = doHackTransform($mods_file);
  $mods_file = str_replace('<mods:subject/>' . "\n", '', $mods_file);

  // With a DOMDocument, parse the MODS xml using the xpaths needed for each field.
  $namespace = 'http://www.loc.gov/mods/v3';
  $doc = new DOMDocument();
  $doc->loadXML($mods_file);
  $xpath = new DOMXPath($doc);
  $xpath->registerNamespace('mods', $namespace);
  $s .= '</pre><hr>';

  // loop through record_data to see which values are provided and will need to be set.
  $geo_idx = 1;
  foreach ($record_data as $metadata_field => $metadata_value) {
    // $s .= '<span style="font-size:20pt;color:#845">' . $metadata_field . ' = ' . $metadata_value . "</span><br>";
    if ($metadata_field == 'identifier' || $metadata_field == 'increment_idx') {
      if ($metadata_field == 'increment_idx') {
        $geo_idx++;
      }
    }
    else {
      $init_metadata_field = $metadata_field;
      // Current value for this field from the islandora object:
      // nonIdxField is the field name that corresponds to the $mods_mappings field names - specifically
      // because the geo fields do not have a number, but an asterisk.
      $nonIdxField = preg_replace('/[0-9]+/', '', $metadata_field);      
      $is_geo_field = ($metadata_field <> $nonIdxField);
      $geo_idx = str_replace($nonIdxField, '', $metadata_field);
      // Now that this is established, we could set a new value for $metadata_field and use this for the rest of this process.
      $metadata_field = ($is_geo_field) ? $nonIdxField . '*' : $metadata_field;
      $mods_mapping_indexes = _find_metadatafield_idxs($metadata_field, $mods_mappings);
      if (count($mods_mapping_indexes) > 0) {
        // Using this mods_mapping_indexes, execute the xpath for that index on the DOM Document object.
        foreach ($mods_mapping_indexes as $mods_mapping_index) {
          $xmap = $mods_mappings[$mods_mapping_index][$metadata_field];
          $s .= _add_full_xpath($doc, $xpath, $xmap, $metadata_value, $namespace, (($is_geo_field) ? $geo_idx : NULL));
        }
      }
    }
    $s .= "</hr>";
  }
  $xml = $doc->saveXML();
  // because this was a hack -- cleaning up the results of a bad hack above
  $xml = str_replace('<mods:subject/>', '', $xml);
  for ($i = 0; $i <= $max_geo_idx; $i++) {
    $replacements = array('hierarchicalGeographic' . $i . '>', 'county' . $i . '>', 'city' . $i . '>', 'citySection' . $i . '>');
    $vals = array('hierarchicalGeographic>', 'county>', 'city>', 'citySection>');
    $xml = str_replace($replacements, $vals, $xml);
  }
  $s .= '<div style="color:green"><pre>' . htmlspecialchars($xml) . '</pre></div>';

  // This is essentially the code that executes when a user clicks "Updage" from an XML MODS form.
  // It seems that we do not explicitly need to call islandora_add_object() here.
  $datastream = $islandora_object['MODS'];
  $datastream->setContentFromString(trim($xml));
  if ($datastream->mimetype != 'text/xml') {
    $datastream->mimetype = 'text/xml';
  }

  // This will update the DC record by transforming the current MODS xml.
  doDC($islandora_object, $xml);

  // Finally, update the label value
  $islandora_object->label = $record_data['final_title'];

  _log('new MODS for ' . $islandora_object->id . ' = ' . $xml);
  _log('PROCESSING DONE for ' . $islandora_object->id);

  //  $s .= '<h3>updated MODS</h3><pre style="color:#2b2">' . htmlspecialchars($xml) . "</pre>";
  return $s;
}

function _find_metadatafield_idxs($metadata_field, $mods_mappings) {
  $found = array();
  foreach ($mods_mappings as $idx => $mods_mapping) {
    $keymap_array_pair = array_keys($mods_mapping);
    if (!(array_search($metadata_field, $keymap_array_pair) === FALSE)) {
      $found[] = $idx;
    }
  }
  return $found;
}

function _add_full_xpath($doc, $xpath, $xmap, $metadata_value, $namespace, $geo_idx) {
  // ltrim this because it will prevent the first element from being empty.
  $parts = explode("/", ltrim($xmap, '/'));
  $partial_xpath = '';
  $last_found_parent = '';
  $total_parts = count($parts);
  // $s .= 'parts = ' . print_r($parts, true). '<br>';
  foreach ($parts as $part_idx => $part) {
    if ($part) {      
      $partial_xpath .= '/' . $part;
$s .= '<h2>' . $partial_xpath . '</h2>';
      $results = $xpath->query($partial_xpath);
//      $existed = (($results->item(0)) ? TRUE : FALSE);
      $nodeValue = '';
      foreach ($results as $result) {
        $nodeValue = htmlspecialchars(trim($result->nodeValue));
        $s .= '{{{'.$partial_xpath . ' | ' . $nodeValue . "}}}<br>";
        /* if ($partial_xpath == '/mods:mods/mods:originInfo/mods:dateOther/@type="display"') {
           $s .= '<div style="padding:10px">'.$nodeValue.'</div>';
        } */
      }
      $trimmed = substr($nodeValue, 0, 30) . ((strlen($nodeValue) > 30) ? '... ' : '');

$existed = false;
if ($results->item(0)) {
 foreach ($results as $result) {
    $existed = true;
$s .= $result->nodeValue . "<hr>";
  }
}
      $s .= '<span style="color:' . (($existed) ? 'green' : 'red') . '">partial_xpath = ' . $partial_xpath . ', xmap = ' . $xmap . " [" . $metadata_value . "]</span><br>";
      // DETERMINE whether or not this is creating an empty node, a node with a value, or a node with a value and an attribute.
      $set_value = ($metadata_value && ($part_idx == $total_parts - 1));
      if (!$existed || !is_null($geo_idx)) {
        $s .= '<h3>' . $partial_xpath . ' [existed,geo_idx] = ['.(($existed) ? 'TRUE' : 'FALSE').','. (is_null($geo_idx) ? 'NULL' : $geo_idx).']  Will need to CREATE or UPDATE' . 
          (($set_value) ? ' and set value to "' . $metadata_value . '"' : '') . 
          '.</h3>';
        $s .= '<div style="border:1px solid black;padding:10px;">' . _add_this_node_to_parent($doc, $partial_xpath, $last_found_parent, $xpath, $namespace, $metadata_value, $results, $set_value, $geo_idx) . "</div>";
      }
      else {
        $set_value;
        $s .= '<h3>' . $partial_xpath . ' exists!' . 
          (($set_value) ? '  Will need to UPDATE "' . $trimmed . '" to "' . $metadata_value . '".' : '').
          '</h3>';
        if ($metadata_value && ($part_idx == $total_parts - 1)) {
          $s .= '<div style="border:1px solid black;padding:10px;">' . _update_existing_tag($doc, $partial_xpath, $last_found_parent, $xpath, $namespace, $metadata_value, $results, $set_value) . "</div>";
        }
      }
      // $s .= '<span style="color:#877"><b>part_idx[' . ($part_idx + 1) . '/' . $total_parts . '] q ' . $partial_xpath . '</b></span><br>';
      $last_found_parent = $partial_xpath;
    }
  }

  $s .= "<hr>";
  return $s;
}

function _update_existing_tag($doc, $partial_xpath, $last_found_parent, $xpath, $namespace, $metadata_value, $parent_node_results, $set_value) {
  $s = ''; // in update <br>';
  if ($set_value) {
    $nodeValue = FALSE;
    $s = '';
    foreach ($parent_node_results as $element) {
      $nodeValue = htmlspecialchars(trim($element->nodeValue));
    }
    if ($nodeValue) {
      $results = $xpath->query($partial_xpath);
      foreach ($results as $result) {
        $s .= '<div style="padding:4px;border: 3px solid ' . (($set_values) ? 'green' : 'red') . '"><span style="color:#964">updating node value ' . $metadata_value . ', was "' . trim($result->nodeValue) . '"</span></div>';
        // $s .= '<i>'.trim($result->nodeValue) ."</i><br>";
        $result->nodeValue = htmlspecialchars($metadata_value);
      }
    }
  }
  return $s;
}

function _add_this_node_to_parent($doc, $partial_xpath, $last_found_parent, $xpath, $namespace, $metadata_value, $parent_node_results, $set_value, $geo_idx) {
  global $last_container_created_id, $max_geo_idx;
  $node_part = ltrim(str_replace($last_found_parent, '', $partial_xpath), '/');
  $s = 'geo_idx = ' . $geo_idx . ', last_found_parent = ' . $last_found_parent . ', node_part = ' . $node_part . ', partial_xpath = ' . $partial_xpath . "\n";
  if (!is_null($geo_idx)) {
    if ($geo_idx > $max_geo_idx) {
      $max_geo_idx = $geo_idx;
    }
    $s.= '<div style="padding: 5px;border: 1px dotted black;">is_geo_field -- path = ' . $last_found_parent.' // node_part = ' . $node_part . '<br>';
    $s.= '<h3 style="color:green">geo_idx = ' . $geo_idx . '</h3>' . "\n";
    if (!(strstr($node_part, 'hierarchicalGeographic'))) {
      $last_found_parent = str_replace('hierarchicalGeographic', 'hierarchicalGeographic' . $geo_idx, $partial_xpath);
      //  mods:citySection2 under /mods:mods/mods:subject/mods:hierarchicalGeographic2/mods:citySection
      $nonNumeric_node_part = '/' . preg_replace('/[0-9]+/', '', $node_part);
      $last_found_parent = ($node_part <> $last_found_parent) ? rtrim(str_replace($node_part, '', $last_found_parent), '/') : $node_part;
    }
    else {
      $s .= '!!!!!!!!!!!!!!!!! node_part = ' . $node_part . "<hr>";
    }
    if (($last_container_created_id <> $geo_idx) && (strstr($node_part, 'hierarchicalGeographic')) || !strstr($node_part, 'hierarchicalGeographic')) {
      $s .= 'last_found_parent = ' . $last_found_parent . "\n";
      $results = $xpath->query($last_found_parent);
      if (is_object($results)) { //  && ((strstr($node_part, 'hierarchicalGeographic') || strstr($last_found_parent, 'hierarchicalGeographic')))) {
        // Parent item found
        // ONLY add the geo_idx if the field is one of the known geo fields
        if (strstr($node_part, 'mods:hierarchicalGeographic') || strstr($node_part, 'mods:county') || 
          strstr($node_part, 'mods:city')) {
          $node_part .= $geo_idx;
          $last_container_created_id = $geo_idx;
        }
        $s .= '<span style="color:#964">making child node named &lt;' . $node_part . '&gt;' . (($set_value) ? ' and setting value to ' . $metadata_value : '') . '</span><br>';
        $child = $doc->createElementNS($namespace, $node_part);
        if (strstr($node_part, 'mods:hierarchicalGeographic') == '' && strstr($node_part, 'mods:subject') == '') {
          $child->nodeValue = htmlspecialchars($metadata_value);
        }
        foreach ($results as $result) {
          $s.= 'top adding child result (' . $node_part .")\n";
          $result->appendChild($child);
        }
      }
      else {
        $s .= "skipping " . $last_found_parent . ' + ' . $node_part . '[is_object]' . (is_object($results) ? 'TRUE' : 'FALSE') . "<br>";
      }
    }
    else {
      $s .= '<div style="color:red;border:5px solid green">last_container = ' . $last_container_created_id . ', geo_idx = ' . $geo_idx . ', node_part = ' . $node_part ."</div>";
    }
    $s .= "</div>";
    return $s;
  }
  
  // $trimmed = substr($nodeValue, 0, 30) . ((strlen($nodeValue) > 30) ? '... ' : '');
  $nodeValue = FALSE;
  $s = '';
  foreach ($parent_node_results as $element) {
    $nodeValue = trim($element->nodeValue);
  }
  // $s .= 'metadata ['.$metadata_value.']<br>partial path= ' . $partial_xpath . ' <br> <b>last_parent = ' . $last_found_parent . "<br> node part   = " . $node_part . "</b><br>";
  if (strstr($node_part, '@') <> '') {
    list($node, $split) = explode('[', $node_part);
    @list($attr_name, $attr_value) = explode('=', str_replace(array('@', '"', ']', '['), '', str_replace($node, '', $node_part)));
    $results = $xpath->query($last_found_parent);
    $s .= '<div style="background-color:#eef">partial_xpath = ' . $partial_xpath . ', attrib = ' . $attr_name . ' / val = "' . $attr_value . '", parent = ' . $last_found_parent ."<br><b>node = " . $node . "</b><br>";
    if ($results->item(0)) {
      $child = $doc->createElementNS($namespace, $node);
      // $s .=  "child node " . $node ."<hr>";
      $child->nodeValue = $metadata_value;
      $s .= 'Setting on ' . $last_found_parent . ' -- ' . $attr_name . ' = ' . $attr_value ."<br>";
      $child->setAttribute($attr_name, $attr_value);
      foreach ($results as $result) {
        $s.= 'mid adding child result ' ."\n";
        $result->appendChild($child);
      }  
    }
    $s .= '</div><span style="color:#292">ADDING (with attribute) ' . $xmap_higher . ' = ' . $metadata_value . "</span><hr>";
  }
  else {
    // this should find the parent 
    $parts = explode('/', $node_part);
    if (count($parts) > 1) {
      $node_part = $parts[count($parts) - 1];
    }
    $results = $xpath->query($last_found_parent);
    if (is_object($results)) {
      // Parent item found
      $verb = ($nodeValue) ? 'UPDATING' : 'ADDING';
      $s .= '<span style="color:#292">('. $nodeValue.') ' . $verb . ' ' . $node_part . ' :: ' . $metadata_value . " via last_found_parent query " . $last_found_parent. "</span><br>";
      // ADDING NEW VALUE
      if (!$nodeValue) {
        $s .= '<span style="color:#964">making child node named &lt;' . $node_part . '&gt;' . (($set_value) ? ' and setting value to ' . $metadata_value : '') . '</span><br>';
        $child = $doc->createElementNS($namespace, $node_part);
        if ($set_value) { 
          $child->nodeValue = htmlspecialchars($metadata_value);
        }
        $s.= 'low adding child result ' ."\n";
        $results->item(0)->appendChild($child);
      }
      // UPDATE EXISTING NODE
      if ($nodeValue) {
        $results = $xpath->query($partial_xpath);
        foreach ($results as $result) {
          $s .= '<span style="color:#964">updating node value ' . $metadata_value . ', was "' . trim($result->nodeValue) . '"</span><br>';
          $s .= '<i>'.trim($result->nodeValue) ."</i><br>";
          $result->nodeValue = htmlspecialchars($metadata_value);
        }      
      }
    }
    else {
      $s .= '<b>NOT CREATING ' . $node_part . ' :: ' . $metadata_value . '<br>';
    }
  }
  return $s;
}

function _log($message) {
  if (function_exists('drupal_set_message')) {
    drupal_set_message($message, 'status');
  }
  error_log(date('c') . ' ' . $message."\n", 3, LOGFILE);
}


// Mostly COPIED from islandora_batch/includes/islandora_scan_batch.inc.
/**
 * Helper function to transform the MODS to get dc.
 */  
function doDC($object, $mods_content) {
  $dc_datastream = $object['DC'];     
  $dc_datastream->mimetype = 'application/xml';
  $dc_datastream->label = 'DC Record';

  // Get the DC by transforming from MODS.
  if ($mods_content) {
    $new_dc = _runXslTransform(
            array(
              'xsl' => TRANSFORM_MODS2DC_STYLESHEET,
              'input' => $mods_content,
            )
          );
    _log('--------------- transform DC = ' . print_r($new_dc, true));
  }
  if (isset($new_dc)) {
    $dc_datastream->setContentFromString($new_dc);
  }
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
  _log('transform style sheet: ' . $info['xsl']);
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

function doHackTransform($mods_content) {
  // Get the DC by transforming from MODS.
  $new_MODS = ($mods_content) ? _runXslTransform(
            array(
              'xsl' => TRANSFORM_STYLESHEET,
              'input' => $mods_content,
            )
          ) : '';
  return $new_MODS;
}

