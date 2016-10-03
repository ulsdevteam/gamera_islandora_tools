<?php

// to run, use devel/php to call this :
//   include_once('/usr/local/src/islandora_tools/process_bags/process.php');

define('HOPKINS_COLLECTION', 'pitt:hopkins');


/*
 * This script will ingest the files related to bagIt objects that were exported from Gamera.
 * The tgz files have already been expanded manually to /home/vagrant/bags/hopkins.
 * The PID values are converted to Bag-it filenames: for the object pitt:20090330-hopkins-0017, the
 * Bag-it filename is Bag-pitt_20090330_hopkins_0017.
 **/

error_log('started ' . date('H:i:s'));

// Allow this script to run until it is done ~ will certainly exceed 100 seconds.
set_time_limit(0);

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


$dir    = '/home/vagrant/bags/hopkins';
$folders = scandir($dir);
foreach ($folders as $folder) {
  if ($folder <> '.' && $folder <> '..') {
    echo "<b>" . $folder . "</b><br>";
    process_bag($folder, $repository)."\n";
  }
}

die();


function process_bag($folder, $repository) {
  $dir    = '/home/vagrant/bags/hopkins/' . $folder;
  $data_dir = $dir . '/data';
  $files = scandir($dir);
  echo '<div style="border:1px solid #999;padding:2px;">';
  foreach ($files as $file) {
    if ($file <> '.' && $file <> '..') {
      if ($file == 'data') {
        echo '<b>data</b><br>';
        $datafiles = scandir($data_dir);
        // if this folder has any files,
        if (count($datafiles > 2)) {
          echo '<div style="padding-left:5px">';
          foreach ($datafiles as $datafile) {
            if ($datafile <> '.' && $datafile <> '..') {
              echo $datafile ."<br>";
            }
          }
          echo "</div>";
        }
      }
      else {
        echo $file ."<br>";
      }
    }
  }
  if (array_search('data', $files) === FALSE) {
    echo '<span style="color:red">data contents NOT found for ' . $folder . '</span><br>';
  }
  else {
    // process the data
    $pid = convert_foldername_to_pid($folder);
    echo '<span style="color:green">PID : ' . $pid . "</span><br>";
    echo create_object_from_bag($pid, $datafiles, $data_dir, $repository);
  }
  echo "</div>";
}

/*  The PID values are converted to Bag-it filenames: for the object pitt:20090330-hopkins-0017, the
 * Bag-it filename is Bag-pitt_20090330_hopkins_0017.
 */
function convert_foldername_to_pid($folder) {
  return str_replace(array('Bag-', 'pitt_', '_'), array('', 'pitt:', '-'), $folder);
}

function create_object_from_bag($pid, $datafiles, $data_dir, $repository) {
  $mimetype_mappings = array('bin' => 'application/xml',
      'rdf' => 'application/rdf+xml',
      'jpg' => 'image/jpeg',
      'jp2' => 'image/jpeg',
      'png' => 'image/png',
      'tif' => 'image/tiff',
      'xml' => 'text/xml'
    );
  $object = islandora_object_load($pid);

  if (!$object) {
    $object = $repository->constructObject($pid);
    $object_existed = FALSE;
  }
  else {
    $object_existed = TRUE;
  }

  // Since the RELS-EXT is made by adding relationships to the object, the datafile for RELS-EXT.rdf
  // must be parsed here.
  $RELSEXT_filename = $data_dir . '/RELS-EXT.rdf';
  update_object_RELSEXT($object, $RELSEXT_filename);

  // get the title from the MODS
  $MODS_filename = $data_dir . '/MODS.xml';
  set_title_from_MODS($object, $MODS_filename);

  foreach ($datafiles as $datafile) {
    if ($datafile <> 'foo.xml' && $datafile <> 'RELS-EXT.rdf' && $datafile <> '.' && $datafile <> '..') {
      $data_filename = $data_dir . '/' . $datafile;
      $path_parts = pathinfo($data_filename);
      $mimetype = $mimetype_mappings[$path_parts['extension']];

      $dsid = strtoupper($path_parts['filename']);
      $datastream = isset($object[$dsid]) ? $object[$dsid] : $object->constructDatastream($dsid);
      // update existing or set new EAD datastream
      $datastream->label = $path_parts['filename'];
      $datastream->mimeType = $mimetype;
      $datastream->setContentFromFile($data_filename);
      $object->ingestDatastream($datastream);
    }
  }
  // If the object IS only constructed, ingesting it here also ingests the datastream.
  if (!$object_existed) {
    $repository->ingestObject($object);
  }
}

function update_object_RELSEXT($object, $RELSEXT_filename) {
  _add_relationship_if_not_exists($object, 'isMemberOfCollection', HOPKINS_COLLECTION, FEDORA_RELS_EXT_URI);
  // we know they are all large image models
  $object->models = 'islandora:sp_large_image_cmodel';
}

function set_title_from_MODS($object, $MODS_filename) {
  $title_query = 'mods:mods/mods:titleInfo/mods:title';
  $plate_no_query = 'mods:mods/mods:titleInfo/mods:partNumber';
  $MODS_DOM = new DOMDocument();
  if (!@$MODS_DOM->load($MODS_filename)) {
    die('could not load MODS file ' . $MODS_filename);
  }
  $xpath = new DOMXPath($MODS_DOM);
  //  $xpath->registerNamespace('m', 'http://www.loc.gov/MARC21/slim');
  $title_results = $xpath->query($title_query);
  $plate_no_results = $xpath->query($plate_no_query);
  $full_title = '';
  foreach ($title_results as $title) {
    $full_title .= $title->nodeValue;
  }
  foreach ($plate_no_results as $plate_no) {
    $full_title .= (($full_title) ? ' ' . $plate_no->nodeValue : $plate_no->nodeValue);
  }
  $object->label = $full_title;
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
