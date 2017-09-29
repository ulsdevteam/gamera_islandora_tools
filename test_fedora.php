<?php

// Load our own Library.
require_once(dirname(__FILE__) .'/uls-tuque-lib.php');

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
include_once(dirname(__FILE__) .'/common/funcs.php');

$connection = getRepositoryConnection();
// $api = new FedoraApi($connection);
// $repository = new FedoraRepository($api, new simpleCache());
$repository = getRepository($connection);
die(print_r($repository, true));

// $pid = 'pitt:715.08813.CP'; // BAD
$pid = 'pitt:31735061277657';  // this should be good, but still gives error 
try {
  $object = $repository->getObject($pid);
  /**
   * Logic for working with the loaded object would go here.
   */
}
catch (Exception $e) {
  /**
   * Logic for object load failure would go here.
   */
   die('exception ' . print_r($e, true));
}

echo print_r($object, true);
