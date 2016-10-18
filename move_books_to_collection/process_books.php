<?php

set_time_limit(0);

// Run this from the http://dev.gamera.library.pitt.edu/devel/php, execute the following line of code to include this unit.
//
// --->  include_once('/usr/local/src/islandora_tools/move_books_to_collection/process_books.php');
//

// This is the logfile for this
define('LOGFILE', '/usr/local/src/islandora_tools/move_books_to_collection/logfile');

$csv_filename = dirname(__FILE__) . '/books_in_histpitt_not_in_collection.csv';

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


$file = file($csv_filename);
$headings_row = array(array_shift($file));
$headings = array_pop(array_map('str_getcsv', $headings_row));
$identifier_column_idx = array_search('identifier', $headings);
$csv = array_map('str_getcsv', $file);

_log('started', LOGFILE);

foreach ($csv as $row) {
  $pid = $row[0];
  $islandora_object = islandora_object_load($pid);
  if (is_object($islandora_object)) {
    $added_collections = array();
    // Since we know the spreadsheet row can only have two possible collections, just read 1 and 2 like this
    if (isset($row[1]) && (trim($row[1]) <> '')) {
      addToCollection($islandora_object, trim($row[1]));
      $added_collections[] = trim($row[1]);
    }
    if (isset($row[2]) && (trim($row[2]) <> '')) {
      addToCollection($islandora_object, trim($row[2]));
      $added_collections[] = trim($row[2]);
    }
    _log($pid . ' added collection/s : ' . implode(", ", $added_collections), LOGFILE);
  }
  else {
    _log('PID not found : ' . $pid, LOGFILE);
  }
}

_log('done', LOGFILE);

