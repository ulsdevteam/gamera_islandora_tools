<?php

function _log($message, $logfile = '') {
  if (function_exists('drupal_set_message')) {
    drupal_set_message($message, 'status');
  }
  if ($logfile) {
    error_log(date('c') . ' ' . $message."\n", 3, $logfile);
  }
  else {
    error_log($message);
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

