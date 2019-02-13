<?php

// Load our own Library.
require_once(dirname(__FILE__) .'/../uls-tuque-lib.php');

global $config;
global $batch_name;
global $date_start;

$config['db'] = get_config_value('mysql' ,'database');
$config['username'] = get_config_value('mysql','username');
$config['password'] = get_config_value('mysql','password');
$config['host'] = get_config_value('mysql', 'host');

if (!$batch_name) {
  $batch_name = (array_key_exists(1, $argv)) ? trim($argv[1]) : '';
}

set_time_limit(0);
// backup();

/**
 * Call from http://gamera.library.pitt.edu/devel/php with the following line of code
 *
 *   module_load_include('module', 'upitt_workflow', 'upitt_workflow');
 *   // Optionally set a date for updating records after the date_start value
 *   // if not set, the entire set of tables will be REPLACED.
 *   global $date_start;
 *   $date_start = '2018/01/20';
 *   include_once('/usr/local/src/islandora_tools/workflow_django_migrate/migrate.php');
 *
 * Alternatively, the value for a single batch name can be provided which should import ONLY
 * that batch and its related records.  This would skip all of the setup tables.
 *   module_load_include('module', 'upitt_workflow', 'upitt_workflow');
 *   // Optionally set a date for updating records after the date_start value
 *   // if not set, the entire set of tables will be REPLACED.
 *   global $batch_name;
 *   $batch_name = '20180205-ezbatch';
 *   include_once('/usr/local/src/islandora_tools/workflow_django_migrate/migrate.php');
 *
 *
 * BEFORE running this script, be sure that the workflow system is not being actively being used.
 * The code will create a backup of the workflow_django database by running :
 *   $ mysqldump -uislandora -p -h bigfoot.library.pitt.edu workflow_django > ~/workflow_django.sql
 *
 * This will use the dev.gamera instance which will have a second database configured within the settings.php.
 * Production Gamera install will not have this change to settings.php for now because this is just connectivity
 * testing and lookup functionality.
 */

// both the mysql databases are accessable fia the upitt_workflow_get_databaselink function calls in upitt_workflow.
// module_load_include('module', 'upitt_workflow', 'upitt_workflow');

/*
legacy table                     transf?   new                               lookup table?
-----------------------------------------------------------------------------------------------
core_action                                action
core_batch                       YES       batch
core_batch_item                            batch_item
core_collection                            collection                        YES
(new table)                                content_types                     YES
core_item                                  item
core_item_current_status                   item_current_status
core_item_file                             item_file
core_item_type                             item_type                         YES
core_property_owner              minor     property_owner
(new table)                                sites
(new table)                                batch_collections
core_transaction                           transaction
core_workflow_sequence                     workflow_sequence                 YES
core_workflow_sequence_actions             workflow_sequence_actions
*/

_get_dump_renametable_run_new('core_batch_item', 'batch_item');
_get_dump_renametable_run_new('core_item_file', 'item_file');
_get_dump_renametable_run_new('core_item', 'item');


// test this function 
if (!$batch_name) {
  _get_dump_renametable_run_new('core_transaction', 'transaction');
  _get_dump_renametable_run_new('core_property_owner', 'property_owner');
  _get_dump_renametable_run_new('core_item_file', 'item_file');
  _get_dump_renametable_run_new('core_item_current_status', 'item_current_status');
  _get_dump_renametable_run_new('core_item', 'item');
  _get_dump_renametable_run_new('core_batch_item', 'batch_item');
  _get_dump_renametable_run_new('core_action', 'action');
  _get_dump_renametable_run_new('core_workflow_sequence_actions', 'core_workflow_sequence_actions');
  _get_dump_renametable_run_new('core_workflow_sequence', 'workflow_sequence');

  // this table will be renamed in the sqls/auth_user.sql since it does not simply need to have "core_" dropped from the naming.
  _get_dump_renametable_run_new('auth_user', 'auth_user');
}
else {
  echo "<div style='color:blue'>Skipping the lookup tables.</div>";
  _get_dump_renametable_run_new('core_item_file', 'item_file');
  _get_dump_renametable_run_new('core_item_current_status', 'item_current_status');
  _get_dump_renametable_run_new('core_item', 'item');
  _get_dump_renametable_run_new('core_batch_item', 'batch_item'); 
}

if (!$batch_name) {
  // core_item_type
  _get_dump_renametable_run_new('core_batch', 'batch');
  _get_wflocal_local_batch_records(' LIMIT 0,200');
  _get_wflocal_local_batch_records(' LIMIT 200,200');
  _get_wflocal_local_batch_records(' LIMIT 400,200');
  _get_wflocal_local_batch_records(' LIMIT 600,200');
  _get_wflocal_local_batch_records(' LIMIT 800,200');
  _get_wflocal_local_batch_records(' LIMIT 1000,200');

  _create_batch_collections();

  _run_sql('transaction_actions.sql');
  _run_sql('transaction_actions.data.sql');

  _get_dump_renametable_run_new('core_collection', 'collection');
  _get_legacy_collection_mappings();

  _get_dump_renametable_run_new('wflocal_fedora_collection', 'wflocal_fedora_collection');
  _get_dump_renametable_run_new('wflocal_local_item_fedora_collections', 'wflocal_local_item_fedora_collections');
  _get_dump_renametable_run_new('wflocal_local_item_fedora_sites', 'wflocal_local_item_fedora_sites');
  _get_dump_renametable_run_new('wflocal_fedora_site', 'wflocal_fedora_site');
}
else {
  _delete_all_related_batch_records();
  echo "<div style='color:blue'>Skipping additional lookup tables.</div>";
  // because a field name changes in the transaction and batch table AFTER
  // any data has already been imported, subsequent insert calls would
  // fail due to mismatched field names in the source and target tables.
  // For this reason, there must be a batch_temp and transaction_temp table.

  // Create the batch_temp and transaction_temp tables
  _run_sql('batch_temp_create.sql');
  _run_sql('transaction_temp_create.sql');
  _run_sql('batch_temp.sql');

  // This populates the batch_temp table
  _get_dump_renametable_run_new('core_batch', 'batch_temp');
  _get_wflocal_local_batch_records('', TRUE);

  // This populates the transaction_temp table
  _get_dump_renametable_run_new('core_transaction', 'transaction_temp');

  // This will update the batch_temp structure so that it is the same as the target `batch` table
//  _run_sql('batch_temp.sql');
  _run_sql('transaction_temp.sql');
  _copy_from_batch_temp();
  _copy_from_transaction_temp();
}

die();

function _copy_from_batch_temp() {
  $sql = "INSERT INTO `batch` (SELECT * FROM `batch_temp`)";
  echo "<pre>".$sql . "</pre>";
  $link_new = upitt_workflow_get_databaselink('mysql_new_workflow');
  $result = mysqli_query($link_new, $sql);
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                'Whole query: ' . $sql;
    die($message);
  }
//   $drop = 'DROP TABLE `batch_temp`';
//   mysqli_query($link_new, $drop);
}

function _copy_from_transaction_temp() {
  $sql = "INSERT INTO `transaction` (SELECT * FROM `transaction_temp`)";
  echo "<pre>".$sql . "</pre>";
  $link_new = upitt_workflow_get_databaselink('mysql_new_workflow');
  $result = mysqli_query($link_new, $sql);
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                'Whole query: ' . $sql;
    die($message);
  }
echo "<h1>ok???????????????????????????????????????????????????????</h1>";
//  $drop = 'DROP TABLE `transaction_temp`';
//  mysqli_query($link_new, $drop);
}

function _delete_all_related_batch_records() {
  global $batch_name;
  // get all of the item_id values from the batch_item table - needed to delete related transaction and item_file records
  $sql = 'SELECT item_id FROM batch_item WHERE batch_id = (SELECT batch_id FROM batch WHERE batch_external_id = \'' . addslashes($batch_name). '\')';
  echo "<pre>".$sql . "</pre>";
  $link_new = upitt_workflow_get_databaselink('mysql_new_workflow');
  $result = mysqli_query($link_new, $sql);   
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                'Whole query: ' . $sql;
    die($message);
  }
  $item_ids = array();
  while ($row = mysqli_fetch_assoc($result)) {
    if (isset($row['item_id'])) {
      $item_ids[] = $row['item_id'];
    }
  }
  if (count($item_ids) > 0) {
    $delete_files = 'DELETE FROM item_file WHERE item_id IN (' . implode(",", $item_ids) . ')';
    echo "<pre>". $delete_files . "</pre>";
    $result = mysqli_query($link_new, $delete_files);
    if (!$result) {
      $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                  'Whole query: ' . $delete_files;
      die($message);
    }

    $delete_transaction = 'DELETE FROM transaction WHERE item_id IN (' . implode(",", $item_ids) . ')';
    echo "<pre>". $delete_transaction . "</pre>";
    $result = mysqli_query($link_new, $delete_transaction);
    if (!$result) {
      $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                  'Whole query: ' . $delete_transaction;
      die($message);
    }

    $delete_item = 'DELETE FROM item WHERE id IN (' . implode(",", $item_ids) . ')';
    echo "<pre>". $delete_item . "</pre>";
    $result = mysqli_query($link_new, $delete_item);
    if (!$result) {
      $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                  'Whole query: ' . $delete_item;
      die($message);
    }

    $delete_batch = 'DELETE FROM batch WHERE batch_external_id = \'' . $batch_name . '\'';
    echo "<pre>". $delete_batch . "</pre>";    
    $result = mysqli_query($link_new, $delete_batch);
    if (!$result) {
      $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                  'Whole query: ' . $delete_batch;
      die($message);
    }
  }
}

function backup() {
  global $config;
  $command = 'mysqldump -u' . $config['username'] . ' -p' . $config['password'] . ' -h ' . $config['host'] . ' islandora_workflow > /home/bgilling/workflow_django.sql';
  echo "<pre>" . $command . "</pre>";
  exec($command, $output, $return_var);
  echo "<div style='color:" . (($return_var > 0) ? 'green' : 'red'). "'>dump sql run '" . $command . "'.</div>";
  if ($return_var) {
    echo '<code style="color:#269">' . _safe($command) . '</code><br />';
  }
}

function _run_sql($filename) {
  global $config;
  $createSql = '/usr/local/src/islandora_tools/workflow_django_migrate/sqls/' . $filename;
  if (file_exists($createSql)) {
    $command = 'mysql -u' . $config['username'] . ' -p' . $config['password'] . ' -h ' . $config['host'] . ' islandora_workflow < ' . $createSql;
    echo "<pre>" . $command . "</pre>";
    exec($command, $output, $return_var);
    echo "<div style='color:" . (($return_var > 0) ? 'green' : 'red'). "'>CREATE TABLE sql run '" . $createSql . "'.</div>";
    if ($return_var) {
      echo '<code style="color:#269">' . _safe($command) . '</code><br />';
    }
  }
  else {
    echo "<h1>SQL file does not exist! : $filename</h1>";
  }
}

function _create_batch_collections() {
  global $config;
  $createSql = '/usr/local/src/islandora_tools/workflow_django_migrate/sqls/batch_collections.sql';
  if (file_exists($createSql)) {
    $command = 'mysql -u' . $config['username'] . ' -p' . $config['password'] . ' -h ' . $config['host'] . ' islandora_workflow < ' . $createSql;
    echo "<pre>" . $command . "</pre>";
    exec($command, $output, $return_var);
    echo "<div style='color:" . (($return_var > 0) ? 'green' : 'red'). "'>CREATE TABLE sql run '" . $createSql . "'.</div>";
    if ($return_var) {
      echo '<code style="color:#269">' . _safe($command) . '</code><br />';
    }
  }
}

function _get_dump_renametable_run_new($legacy_tablename, $new_tablename) {
  global $config;
  global $date_start;
  global $batch_name;
  $dmp_filename = '/tmp/' . $legacy_tablename . '_bigfoot.sql';
  echo "<b>clearing local table $new_tablename</b><br />";
//  _truncate($new_tablename);

  // this is going to perform 3 steps
  //   1) get mysql_dump from bigfoot for this tablename
  $date_constrained_tables = array('item', 'item_file', 'batch_item');
  if ($date_start && !(array_search($new_tablename, $date_constrained_tables) === FALSE) ) {
    // if there is a date set, we need to join the table to the batch table for constraining the date AND need to 
    // remove the DROP TABLE/CREATE TABLE output so that this generates *ONLY* INSERT statements
    if ($new_tablename == 'batch_item') {
      $command = 'mysqldump -uislandora -pulsislandora -h bigfoot.library.pitt.edu --opt --lock-tables=false workflow_django ' . $legacy_tablename .
         ' --where="batch_id IN (SELECT id FROM core_batch cb ' .
         'WHERE cb.date > \'' . $date_start . '\')" ' .
         '--no-create-info --skip-add-drop-table > ' . $dmp_filename;
    }
    else {
      $table_id_fieldname = ($new_tablename == 'item') ? 'id' : 'item_id';
      $command = 'mysqldump -uislandora -pulsislandora -h bigfoot.library.pitt.edu --opt --lock-tables=false workflow_django ' . $legacy_tablename . 
         ' --where="' . $table_id_fieldname . ' IN (SELECT cbi.item_id as id FROM core_batch cb ' . 
         'JOIN core_batch_item cbi ON (cbi.batch_id = cb.id) WHERE cb.date > \'' . $date_start . '\')" ' .
         '--no-create-info --skip-add-drop-table > ' . $dmp_filename;
    }
  }
  elseif ($batch_name) {
    if ($new_tablename == 'batch_item') {
      $command = 'mysqldump -uislandora -pulsislandora -h bigfoot.library.pitt.edu --opt --lock-tables=false workflow_django ' . $legacy_tablename .
         ' --where="batch_id IN (SELECT id FROM core_batch cb ' .
         'WHERE cb.name = \'' . addslashes($batch_name) . '\')" ' .
         '--no-create-info --skip-add-drop-table > ' . $dmp_filename;
    }
    elseif ($new_tablename == 'batch') {
      $command = 'mysqldump -uislandora -pulsislandora -h bigfoot.library.pitt.edu --opt --lock-tables=false workflow_django ' . $legacy_tablename .
         ' --where="name = \'' . addslashes($batch_name) . '\' ' .
         '--no-create-info --skip-add-drop-table > ' . $dmp_filename;
    }
    else {
      $table_id_fieldname = ($new_tablename == 'item') ? 'id' : 'item_id';
      $command = 'mysqldump -uislandora -pulsislandora -h bigfoot.library.pitt.edu --opt --lock-tables=false workflow_django ' . $legacy_tablename .
         ' --where="' . $table_id_fieldname . ' IN (SELECT cbi.item_id as id FROM core_batch cb ' .
         'JOIN core_batch_item cbi ON (cbi.batch_id = cb.id) WHERE cb.name = \'' . addslashes($batch_name) . '\')" ' .
         '--no-create-info --skip-add-drop-table > ' . $dmp_filename;
    }
  }
  else {
    $command = 'mysqldump -uislandora -pulsislandora -h bigfoot.library.pitt.edu --opt --lock-tables=false workflow_django ' . $legacy_tablename . ' > ' . $dmp_filename;
  }

  echo '<code style="color:#269">' . _safe($command) . '</code><br />';
  echo "<b>exporting table $legacy_tablename</b><br />";
  exec($command, $output, $return_var);

  if (count($output) > 0) {
    echo "<pre>" . print_r($output, true) ."</pre>";
  }

  echo "<div style='color:" . (($return_var < 1) ? 'green' : 'red'). "'>" . $dmp_filename . "</div>";

  //   2) shell execute a sed command to replace refereces of the legacy table name to the new table name
  $command = "sed -i 's/`core_/`/g' " . $dmp_filename;
  //  echo $command."<hr>";
  exec($command, $output, $return_var);

  echo "<div style='color:" . (($return_var < 1) ? 'green' : 'red'). "'>Local table name updated.</div>";

  if ($return_var < 1) {
    $command = "mv " . $dmp_filename . " " . str_replace("core_", "", $dmp_filename);
    exec($command, $output, $return_var);

    echo "<div style='color:" . (($return_var < 1) ? 'green' : 'red'). "'>Renamed '" . $dmp_filename . "' to '" . str_replace("core_", "", $dmp_filename) . "'.</div>";

    if ($return_var > 0) {
      if (count($output) > 0) {
        echo "<pre>" . print_r($output, true) ."</pre>";
      }
    }
  }

  echo "<b>importing local records</b><br />";
  //   3) execute the resultant sql file
  $command = 'mysql -u' . $config['username'] . ' -p' . $config['password'] . ' -h ' . $config['host']. ' islandora_workflow < ' . str_replace("core_", "", $dmp_filename);
  echo '<code style="color:#269">' . _safe($command) . '</code><br />';
  exec($command);

  // ONLY PERFORM THIS IF THE TARGET TABLE IS NOT ONE OF THE _TEMP TABLES
  if (strstr($new_tablename, '_temp') == '') {
    // run any Alter SQL for this table
    $alterSql = '/usr/local/src/islandora_tools/workflow_django_migrate/sqls/' . str_replace(array("core_", "/tmp/", "_bigfoot"), "", $dmp_filename);
    if (file_exists($alterSql)) {   
      $command = 'mysql -u' . $config['username'] . ' -p' . $config['password'] . ' -h ' . $config['host'] . ' islandora_workflow < ' . $alterSql;
      echo '<code style="color:#269">' . _safe($command) . '</code><br />';
      exec($command, $output, $return_var);

      echo "<div style='color:" . (($return_var > 0) ? 'green' : 'red'). "'>ALTER sql run '" . $alterSql . "'.</div>";
      if ($return_var) {
        echo '<code style="color:#269">' . _safe($command) . '</code><br />';
      }
    }
  }
  echo "<hr>";
}

function _safe($in) {
  global $config;
  return str_replace(array($config['password'], 'ulsislandora'), '*************', $in);
}

/**
 * This function will update the new `batch` table's remaining fields with those that are stored in
 * the `wflocal_local_batch` table for the batch table that comes over as `core_batch`.
 *
 * @param string $current_limit
 *   raw SQL to limit the results.  It could be used as a WHERE clause, but designed to be 
 * used to segment the results into more managable parts.
 * @param bool $batch_temp
 *   whether or not the resultant table is the batch table or the batch_temp table (which is
 * needed for when this runs with a given $batch_name value.
 *
 * Skipping the following fields (since they should have been copied over by the mysqldump):
 *   cb.name `batch_external_id`,
 *   cb.description `batch_description`,
 *   cb.active `is_batch_active`,
 *   cb.type_id `batch_item_type_id`,
 *   cb.property_owner_id `batch_property_owner_id`,
 *   cb.sequence_id `batch_sequence_id`,
 *   cb.item_count `item_count`,
 *   cb.collection_id `mapto_collections`
 * -- skip batch_id as an output field of this view.
 * -- skip `nid` (default 0), and `batch_title`
 * -- skip `new_or_existing`
 * -- skip `mapto_site_id_values`, but this methodology will need to be surpported.
 */
function _get_wflocal_local_batch_records($current_limit, $batch_temp = FALSE) {
  global $batch_name;
  $link_legacy = upitt_workflow_get_databaselink('drlworkflow');
  $batch_detail = 'SELECT cb.id, `id`,
                     wfb.permission_notes `batch_default_perm_notes`,
                     wfb.copyright_holder_name `batch_default_CR_holder`,
                     wfb.structural_metadata_treatment `structural_metadata_treatment`,
                     wfb.image_editing_treatment `image_editing_treatment`,
                     wfb.blank_missing_treatment `blank_and_missing_treatment`,
                     wfb.edge_treatment `page_edge_treatment`,
                     wfb.condition_handling `batch_condition_handling`,
                     wfb.use_color_target `use_color_target`,
                     wfb.is_request `is_batch_request`,
                     0 `has_file`,
                     wfb.genre `default_genre`,
                     wfb.type_of_resource `default_type_of_resource`,
                     wfb.depositor `default_depositor`,
                     wfb.ead_id `default_ead_id`,
                     wfb.voyager_id `default_voyager_id`,
                     wfb.publication_status `batch_default_pub_status`,
                     wfb.target_size `output_target_size`,
                     wfb.image_type_bit_depth `image_color_type_and_bitdepth`,
                     wfb.resolution_ppi `image_resolution`,
                     wfb.file_naming `file_naming_scheme`,
                     wfb.file_type `file_type`,
                     wfb.request_due_date `batch_request_due_date`,
                     wfb.requestor `batch_requestor`,
                     wfb.source_id `batch_source_identifier`,
                     wfb.priority `batch_priority`,
                     wfb.copyright_status `batch_default_CR_status`,
                     cb.type_id `content_type_id`
                   FROM wflocal_local_batch wfb
                   JOIN core_batch cb ON (cb.id = wfb.batch_ptr_id) ' .
                   (($batch_temp && $batch_name) ? ' WHERE cb.name = \'' . addslashes($batch_name) . '\'' : '') .
                   $current_limit;
  $batch = array();
  $result = mysqli_query($link_legacy, $batch_detail);
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_legacy) . "\n" .
                'Whole query: ' . $batch_detail;
    die($message);
  }

  $sqls = $fieldname_values = $fields = array();
  while ($row = mysqli_fetch_assoc($result)) {
    if (count($fields) < 1) {
      $fields = array_keys($row);
    }
    foreach ($fields as $field) {
      if ($field == 'id') {
        $id = $row[$field];
      }
      else {
        $fieldname_values[] = '`' . $field . '` = \'' . mysqli_real_escape_string($link_legacy, stripslashes($row[$field])) . '\'';
      }
    }
    $sql = 'UPDATE `' . ($batch_temp ? 'batch_temp' : 'batch') . '` SET ' . implode($fieldname_values, ", ") . ' WHERE batch_id = ' . $id;
    $sqls[$id] = $sql;
  }
  mysqli_close($link_legacy);

  // now take the SQL statements and run them on the other database.
  $link_new = upitt_workflow_get_databaselink('mysql_new_workflow');
  foreach ($sqls as $batch_id => $sql) {
    echo "<pre>" . $sql . "</pre>";
    $result = mysqli_query($link_new, $sql);
    if (!$result) {
      $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                  'Whole query: ' . $sql;
      die($message);
    } else {
      echo "local records updated for batch_id = " . $batch_id . "<br>";
    }
  }
}

function _get_legacy_collection_mappings() {
  $link_legacy = upitt_workflow_get_databaselink('drlworkflow');
  $batch_collection_detail = 'SELECT b.id `batch_id`, b.collection_id FROM `core_batch` b';

  $batch = array();
  $result = mysqli_query($link_legacy, $batch_collection_detail);
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_legacy) . "\n" .
                'Whole query: ' . $batch_collection_detail;
    die($message);
  }

  $sqls = $fieldname_values = $fields = array();
  while ($row = mysqli_fetch_assoc($result)) {
    $sqls[] = 'REPLACE INTO `batch_collections` (`batch_id`, `collection_id`) VALUES (' . $row['batch_id'] . ', '. $row['collection_id'] . ')';
  }
  mysqli_close($link_legacy);

  // now take the SQL statements and run them on the other database.
  $link_new = upitt_workflow_get_databaselink('mysql_new_workflow');
  foreach ($sqls as $batch_id => $sql) {
    $result = mysqli_query($link_new, $sql);
    if (!$result) {
      $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                  'Whole query: ' . $sql;
      die($message);
    } else {
      echo "local records updated for batch_id = " . $batch_id . "<br>";
    }
  }
}

function _get_record_count($legacy_tablename, $link_legacy) {
  $legacy_records = 'SELECT count(*) as `total` FROM `' . $legacy_tablename . '`';
  $result = mysqli_query($link_legacy, $legacy_records);
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_legacy) . "\n" .
                'Whole query: ' . $batch_detail;
    die($message);
  }

  while ($row = mysqli_fetch_assoc($result)) {
    $count = $row['total'];
  }
  return $count;
}

function _truncate($tablename) {
  echo '<code style="color:#269"><b>DROP TABLE IF EXISTS `' . $tablename . '`;</b></code><br />';

  $link_new = upitt_workflow_get_databaselink('mysql_new_workflow');
  $query = 'DROP TABLE IF EXISTS `' . $tablename . '`';

  $result = mysqli_query($link_new, $query);
  if (!$result) {
    $message  = 'Invalid query: ' . mysqli_error($link_new) . "\n" .
                'Whole query: ' . $query;
    die($message);
  }
  mysqli_close($link_new);
}

