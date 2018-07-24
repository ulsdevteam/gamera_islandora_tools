<?php

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/need_ocr_aggregate/run.php');
//

// Step 1.  http://gamera.library.pitt.edu/solr/select?q=RELS_EXT_hasModel_uri_ms:%22info:fedora/islandora:newspaperIssueCModel%22&fq=-fedora_datastream_version_OCR_ID_ms:[*%20TO%20*]&fl=PID&wt=csv&rows=9999
// Step 2.  loop through the issues from step 1 and check children for OCR and PDF  

// This is the logfile for this
define('LOGFILE', dirname(__FILE__).'/logfile');

// Control how many of the items are processed
// $process_exactly = 2;
$process_exactly = PHP_INT_MAX;


include_once(dirname(__FILE__) .'/../common/funcs.php');

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

$connection = getRepositoryConnection();
$repository = getRepository($connection);

// This will return all the issues that do not have an OCR datastream
$issues = get_issues();

$not_founds = $need_ocr_pdf = $all_ocr_pdf_done = $not_had_0001 = array();
$i = 0;
foreach ($issues as $pid) {
  $pid = trim($pid);
  @list($ns, $barcode) = explode(":", $pid);

  if ($i < $process_exactly) {
    $islandora_object = islandora_object_load($pid);
    if (is_object($islandora_object)) {
      $pages = get_pages($islandora_object);
      $had_0001 = FALSE;
      $ocr_ready = $pdf_ready = TRUE;
      foreach ($pages as $page_pid => $values) {
        echo $page_pid ."<br>";
        $had_0001 |= (strstr($page_pid, '-0001'));
        // need all pages to have OCR and PDF to be ready for aggregation
        $ocr_ready &= ($values['fedora_datastream_version_OCR_SIZE_ms'] > 0);
        $pdf_ready &= ($values['fedora_datastream_version_PDF_SIZE_ms'] > 0);
      }
      // Cool -- now all pages for this issue have been scanned... compare results
      if ($ocr_ready && $pdf_ready && count($pages) > 0 && $had_0001) {
        $all_ocr_pdf_done[] = $barcode;
      } elseif (count($pages) > 0 && $had_0001) {
        $need_ocr_pdf[] = $barcode;
      }
      if (!$had_0001) {
        $not_had_0001[] = $barcode;
      }
      echo "<hr>";
    }
    else {
      $not_founds[] = $barcode;
      echo '<b style="color:red">' . $pid . ' NOT FOUND</b><br>';
      _log('PID not found : ' . $pid, LOGFILE);
    }
  }
  $i++;
}

echo "<hr><h2>Results</h2>
  <h3>Issues needing OCR / PDF</h3>
  <div style='color:red'>" . implode("<br>", $need_ocr_pdf) . "</div>

  <h3>Ready to Aggregate OCR / PDF</h3>
  <div style='color:red'>" . implode("<br>", $all_ocr_pdf_done) . "</div>

  <h3>Missing first page (at least)</h3>
  <div style='color:red'>" . implode("<br>", $not_had_0001) . "</div>

  <h3>Object not found</h3>
  <div style='color:red'>" . implode("<br>", $not_founds) . "</div>";


die();
_log('done', LOGFILE);

// RELS_EXT_hasModel_uri_ms:"info:fedora/islandora:newspaperIssueCModel" and -fedora_datastream_version_OCR_ID_ms:[*%20TO%20*]
function get_issues() {
  $issues = array();
  $query_processor = new IslandoraSolrQueryProcessor();

  $query_processor->solrQuery = "RELS_EXT_hasModel_uri_ms:*newspaperIssueCModel AND -fedora_datastream_version_OCR_ID_ms:[* TO *]";

  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 9999;
  $query_processor->solrParams = array(
    'fl' => "PID",
    'fq' => '',
  );
  $url = parse_url(variable_get('islandora_solr_url', 'localhost:8080/solr'));
  $solr = new Apache_Solr_Service($url['host'], $url['port'], $url['path'] . '/');
  $solr->setCreateDocuments(FALSE);
  try {
    $results = $solr->search($query_processor->solrQuery, $query_processor->solrStart, $query_processor->solrLimit, $query_processor->solrParams, 'GET');
    $tmp = json_decode($results->getRawResponse(), TRUE);
    foreach ($tmp['response']['docs'] as $trip) {
      $issues[$trip['PID']] = $trip['PID'];
    }
    return $issues;
  }
  catch (Exception $e) {
    return array();
  }
}


function get_pages($islandora_object) {
//  module_load_include('inc', 'islandora_paged_content', 'includes/utilities');
//  return array_keys(islandora_paged_content_get_pages($islandora_object));
  $query_processor = new IslandoraSolrQueryProcessor();

  $query_processor->solrQuery = "RELS_EXT_isPageOf_uri_ms:info\:fedora/" . str_replace(":", "\:", $islandora_object->id);

  $query_processor->solrStart = 0;
  $query_processor->solrLimit = 99;
  $query_processor->solrParams = array(
    'fl' => "PID,fedora_datastream_version_OCR_SIZE_ms,fedora_datastream_version_PDF_SIZE_ms",
    'fq' => '',
    'sort' => 'PID ASC',
  );
  $url = parse_url(variable_get('islandora_solr_url', 'localhost:8080/solr'));
  $solr = new Apache_Solr_Service($url['host'], $url['port'], $url['path'] . '/');
  $solr->setCreateDocuments(FALSE);
  try {
    $results = $solr->search($query_processor->solrQuery, $query_processor->solrStart, $query_processor->solrLimit, $query_processor->solrParams, 'GET');
    $tmp = json_decode($results->getRawResponse(), TRUE);
    foreach ($tmp['response']['docs'] as $trip) {
      foreach ($trip as $key => $value) {
        $trip[$key] = is_array($value) ? $value[0] : $value;
      }
      $issues[$trip['PID']] = $trip;
    }
    return $issues;
  }
  catch (Exception $e) {
    return array();
  }
}
