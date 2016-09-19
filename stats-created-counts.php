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
        print "Error - Invalid path to Tuque.\n";
        exit(1);
}


// Setup Solr Connection.
$solr_url = get_config_value('solr','url');
$solr_proxy_username = get_config_value('solr','proxy_username');
$solr_proxy_password = get_config_value('solr','proxy_password');
$solr = str_replace('http://', "http://$solr_proxy_username:$solr_proxy_password@", $solr_url);


/**
 * Finally, you can manipulate your object here.
 */

$connection = getRepositoryConnection();
$repository = getRepository($connection);

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

/**
 * Statistics
 */

print_header();
counts_by_collection();
// Store results in database
foreach ($sql_inserts as $sql) {
  echo $sql. "\n";
  mysql_query($sql);
}
exit(0);

function get_solr_collections($query)
{
        global $solr;
        // Solr Query...
        $json = file_get_contents("$solr/$query");
        if($json === FALSE) {
                print "Error talking with Solr.\n";
                exit(1);
        } else {
                $obj = json_decode($json);
                $num = $obj->response->numFound;
                $result['total'] = $num;
                $pids = array();
                foreach ($obj->response->docs as $item)
                {
                        array_push($pids, $item->PID);
                }
                $result['pids'] = $pids;
                return($result);
        }
}

function print_header()
{
        global $cmodels;
        global $cmodel_desc;

        print "Collection ID\tTotal";
        foreach ($cmodel_desc as $model)
        {
                print "\t$model";
        }
        print "\n";
}

function counts_by_collection()
{
        global $repository;
        global $report_date;
        global $sql_inserts;

        $list_of_collections_query = "select?q=PID:pitt\:collection\.*&fl=PID&rows=10000&wt=json&indent=yes&sort=PID+asc";
        $collections = get_solr_collections($list_of_collections_query);
        #print_r($collections);
        foreach ($collections['pids'] as $collection)
        {
                print "$collection\t";
                $object = getObject($repository,$collection);
                $pid = str_replace(':', '\:', $collection);
                $coll_query = "select?q=RELS_EXT_isMemberOfCollection_uri_s:*$pid&fl=PID&wt=json&rows=3000000";
                #print "\nQuery:$coll_query\n";
                $one_collection = get_solr_collections($coll_query);
                $total = $one_collection['total'];
                print "$total";
                $sql_inserts[] = "REPLACE INTO islandora_stats_collection_models (`date`, `PID`, `model_id`, `count`) " .
                                 " VALUES ('" . $report_date . "', '" . $collection . "', 0, $total);";

                get_per_collection_counts_by_content_type($pid);
                print "\n";
        }
}

function get_per_collection_counts_by_content_type($pid)
{
    global $cmodel_desc;
    global $cmodels;
    global $solr;
    global $report_date;
    global $sql_inserts;

    foreach ($cmodels as $model_id=>$cm) {
        $query_total = "select?q=RELS_EXT_hasModel_uri_s:*$cm*%20AND%20RELS_EXT_isMemberOfCollection_uri_s:*$pid&fl=PID&rows=0";
        #print "\nQuery: $query_total\n";
        $result_total = solr_query_totals_json($solr, $query_total);
        print "\t$result_total";
        $sql_inserts[] = "REPLACE INTO islandora_stats_collection_models (`date`, `PID`, `model_id`, `count`) " .
                         " VALUES ('" . $report_date . "', '" . str_replace("\:", ":", $pid) . "', $model_id, $result_total);";
    }
}

function counts_by_date($date)
{
        global $solr;
        $query_total = "select?q=fgs_createdDate_mt:$date&fl=PID,&rows=0";
        $total = solr_query_totals_json($solr,$query_total);
        print "Objects Created on $date: $total\n";
}

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

