<?php

define('LOGFILE', '/usr/local/src/islandora_tools/logs/bulk_add_collection_items.log');

include_once(dirname(__FILE__) .'/common/funcs.php');

$path = dirname(__FILE__);
$items = file($path . '/duplicates.txt');

// dpm($items);
$add_to_collection_object = islandora_object_load('pitt:collection.159');

_log('started at '.date('c'));
// set up array to track progress
$pids = array();
$i = 0;
foreach ($items as $pid) {
  $pid = trim($pid);
  if ($pid) {
    $i++;
    $pids[$pid] = $pid;
  }
}

// loop through pids and process them
$process = true;
foreach ($pids as $pid=>$value) {
  if ($process) {
    $pids[$pid] = array('started' => TRUE);
    $pids[$pid] = _add_item_to_collection($pid, $add_to_collection_object->id);
  }
  else {
    $process = ($pid == 'pitt:pittpressreleases19740359');
  }
}
dpm($pids);
_log('done at '.date('c'));


/**
 * Helper function to see whether or not this object is related to the collections already.
 *
 * If the object is not related to $add_to_collection, the relationship must be added.
 */
function _add_item_to_collection($pid, $add_to_collection) {
  $this_object = islandora_object_load($pid);
  $ret = array('done' => FALSE,
               'added_to' => FALSE,
               'already_existed' => FALSE,
    );
  if (!$this_object) {
    _log(format_string('Could not load object with PID !PID', array('PID' => $pid)));
    return array('done' => FALSE);
  }

  // load the fedora object and call the ->relationships->add() method to add
  $this_relationships = $this_object->relationships->get(FEDORA_RELS_EXT_URI, 'isMemberOfCollection');
  $related_to_add_to = 0;
  foreach ($this_relationships as $relationship) {
    if ($relationship['object']['value'] == $add_to_collection) {
      $related_to_add_to++;
    }
  }

  _log('PID: ' . $pid . '], related_to_add_to = '. $related_to_add_to);

  // now, add or delete based on the $related_to_add_to / $related_to_from booleans
  if ($related_to_add_to > 1) {
    _log('object removed from collection DUPLICATE RELATIONSHIPS "' . $from_collection . '" for PID ' . $pid);
    $this_object->relationships->remove(FEDORA_RELS_EXT_URI, 'isMemberOfCollection', $add_to_collection);
  } elseif ($related_to_add_to > 0) {
    _log('already related to "add to" : ' . $add_to_collection);
    $ret['already_existed'] = TRUE;
  }
  else {
    _log('Object was not related.  Added to "' . $add_to_collection . '" for PID ' . $pid);
    $this_object->relationships->add(FEDORA_RELS_EXT_URI, 'isMemberOfCollection', $add_to_collection);
    $ret['added_to'] = TRUE;
  }

  $ret['done'] = TRUE;
  return $ret;
}

