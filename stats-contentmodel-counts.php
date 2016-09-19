#!/usr/bin/php
<?php
//
// Tuque script.
// More Info:
// https://github.com/Islandora/islandora/wiki/Working-With-Fedora-Objects-Programmatically-Via-Tuque
//

// If this script isn't being run from the Tuque folder, you'll have to
// specify the path before loading Tuque files.

// Load our own Library.
require_once('uls-tuque-lib.php');

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
        print "Error - \"" . $path_to_tuque . "\" Invalid path to Tuque.\n";
        exit(1);
}


// Setup Solr Connection.
$solr_url = get_config_value('solr','url');
$solr_proxy_username = get_config_value('solr','proxy_username');
$solr_proxy_password = get_config_value('solr','proxy_password');
$solr = str_replace('http://', "http://$solr_proxy_username:$solr_proxy_password@", $solr_url);
#print "Solr:$solr\n";

/**
 * Finally, you can manipulate your object here.
 */

$connection = getRepositoryConnection();
$repository = getRepository($connection);
#$object = getObject($repository,$bookpid);
#$relations = $object->relationships;
#$id = getObjectID($object);
#$label = getObjectLabel($object);
#$models = getObjectModels($object);
#$ds = getDatastreams($object);

/**
 * Statistics
 */

print "Islandora Statistics Content Type counts\n";
print "-----------------------------------------------------------\n";

/*
 # The models and names for them are now stored in a mysql table - do not need this block

$cmodels = array('collectionCModel',
                 'bookCModel',
                 'pageCModel',
                 'sp_basic_image',
                 'sp_large_image_cmodel',
                 'sp-audioCModel',
                 'sp_videoCModel',
                 'newspaperCModel',
                 'newspaperIssueCModel',
                 'newspaperPageCModel',
                 'sp_pdf',
                 'mapCModel'
                );

$cmodel_desc = array ('Collection',
                'Book',
                'Book Page',
                'Image',
                'Large Image',
                'Audio',
                'Video',
                'Newspaper',
                'Newspaper Issue',
                'Newspaper Page',
                'PDF',
                'Map'
                );

*/

/**
 * Connect to mysql
 */

$report_date = date('Ymd');
$sql_inserts = array();
echo "Date = '". $report_date. "'/r";

$db_host = get_config_value('mysql','host');
$db_user = get_config_value('mysql','username');
$db_pass = get_config_value('mysql','password');
$db_name = get_config_value('mysql','database');
$link = mysql_connect($db_host, $db_user, $db_pass);
if (!$link) {
    die('Not connected : ' . mysql_error());
}

// make foo the current db
$db_selected = mysql_select_db($db_name, $link);
if (!$db_selected) {
    die ('Can\'t use ' . $db_name . ' : ' . mysql_error());
}

$cmodels = $cmodel_desc = array();
$sql = 'SELECT `model_id`, `model`, `model_desc` FROM islandora_stats_models';
$result = mysql_query($sql);
while ($row=mysql_fetch_array($result)) {
  $cmodels[$row['model_id']] = $row['model'];
  $cmodel_desc[$row['model_id']] = $row['model_desc'];
}


foreach ($cmodels as $model_id=>$cm) {
        $query_total   = "select?q=RELS_EXT_hasModel_uri_s:*$cm*&fl=PID&rows=0";
        $query_pitt    = "select?q=RELS_EXT_hasModel_uri_s:*$cm*%20AND%20PID:pitt\:*&fl=PID&rows=0";
        $query_nonpitt = "select?q=RELS_EXT_hasModel_uri_s:*$cm*%20AND%20!PID:pitt\:*&fl=PID&rows=0";

        $total_pitt = solr_query_totals_json($solr, $query_pitt);
        $total_nonpitt = solr_query_totals_json($solr, $query_nonpitt);
        $sql_inserts[] = "REPLACE INTO islandora_stats_contentmodel_counts (`date`, `model_id`, `pitt_count`, `nonpitt_count`) ".
                         "VALUES ('" . $report_date . "', " . $model_id . ", " . $total_pitt . ", " . $total_nonpitt . ");";
        $total = solr_query_totals_json($solr, $query_total);

        $label_pitt = $cmodel_desc[$model_id]." (pitt:)";
        $label_nonpitt = $cmodel_desc[$model_id]." (non pitt:)";
        $label = $cmodel_desc[$model_id]." Total";

        print "Content Model: $cm\n";
        print_it($label_pitt,$total_pitt);
        print_it($label_nonpitt, $total_nonpitt);
        print_it($label,$total);
        print "\n";
}
// Store results in database
foreach ($sql_inserts as $sql) {
  echo $sql. "\n";
  mysql_query($sql);
}
exit(0);

function print_it($title,$total) {
        printf("%-40s%9d\n",$title,$total);
}

function solr_query_totals_json($solr, $query) {
        // Solr Query...
        #$json = file_get_contents("$solr/select?q=RELS_EXT_isMemberOf_uri_s:*$pid_in&fl=PID&wt=json&indent=yes&sort=PID+asc&rows=3000");
        $output = "&wt=json";
        $json = file_get_contents("$solr/$query$output");
        if($json === FALSE) {
                print "Error talking with Solr.\n";
                exit(1);
        } else {
                $obj = json_decode($json);
                $num = $obj->response->numFound;
                return($num);
        }
}

exit();

?>


