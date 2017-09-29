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


  
  $collection='pitt:collection.9'; 

    $models_query = <<<EOQ
    SELECT DISTINCT ?model from <#ri> where {
?object <fedora-model:hasModel> ?model ;
        <fedora-rels-ext:isMemberOfCollection> <info:fedora/{$collection}>
}
EOQ;
    $models_results = $repository->ri->sparqlQuery($models_query);


echo print_r($models_results, true);
  
