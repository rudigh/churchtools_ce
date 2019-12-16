<?php
include_once (CHURCHCORE . '/churchcore_db.php');
include_once (CHURCHDB . '/churchdb_db.php');

/**
 * get groups responsible for service $service_id
 *
 * @param int $service_id
 * @return array with g_ids
 */
function churchservice_getGroupsOfService($service_id) {
  // get groupIds for services

  //SELECT GROUP_CONCAT(cdb_gruppen_ids) AS ids FROM cs_service group by id having id=24
  $res = db_query("SELECT cdb_gruppen_ids FROM {cs_service}
                   WHERE id=:service_id",
                   array(':service_id' => $service_id));
  $arr = array ();
  foreach ($res as $entry) if ($entry->cdb_gruppen_ids != null) {
      $arr[] = $entry->cdb_gruppen_ids;
  }
    // implode comma separated id values and then explode them alltogether
  if (count($arr)) $arr = explode(",", implode(',', $arr));

    return $arr;
}

/**
 * get groups responsible for services in servicegroup $servicegroup_id
 *
 * @param int $servicegroup_id
 * @return array with g_ids
 */
function churchservice_getGroupsOfServiceGroup($servicegroup_id) {
  // get groupIds for servicegroup
  $res = db_query("SELECT cdb_gruppen_ids
                   FROM {cs_service}
                   WHERE servicegroup_id=:servicegroup_id",
                   array(':servicegroup_id' => $servicegroup_id));
  $arr = array ();
  foreach ($res as $entry) if ($entry->cdb_gruppen_ids != null) {
    $arr[] = $entry->cdb_gruppen_ids;
  }
  // implode comma separated id values and then explode them alltogether
  if (count($arr) == 0) return $arr;
  else return explode(",", implode(',', $arr));
}

$auth = null; // FIXME: delete this? dont let it stay here!

/**
 * get church service authorisation
 *
 * @return array
 */
function churchservice_getAuthorization() {
  global $auth;
  if (!isset($_SESSION["user"]->auth["churchservice"])) return null;

  $auth = $_SESSION["user"]->auth["churchservice"];
  $user_pid = $_SESSION["user"]->id;
  $res = null; // TODO: why not $res = array();
  $res["user_pid"] = $user_pid;

  if (user_access("view", "churchdb")) $res["viewchurchdb"] = true;
  if (user_access("administer persons", "churchcore")) $res["administer persons"] = true;

  if (isset($auth["view"])) $res["read"] = true;
  if (isset($auth["edit events"])) {
    $res["read"] = true;
    $res["write"] = true;
  }
  if (isset($auth["view history"])) $res["viewhistory"] = true;
  if (isset($auth["view history"])) $res["viewhistory"] = true;
  if (isset($auth["export data"])) $res["export"] = true;
  if (isset($auth["edit template"])) $res["edit template"] = true;
  if (isset($auth["edit masterdata"])) $res["admin"] = true;

  if (isset($auth["manage absent"])) $res["manageabsent"] = true;
  if (isset($auth["view facts"])) $res["viewfacts"] = true;
  if (isset($auth["export facts"])) $res["exportfacts"] = true;
  if (isset($auth["edit facts"])) {
    $res["editfacts"] = true;
    $res["viewfacts"] = true;
  }
  if (isset($auth["view song"])) {
    if (isset($auth["view songcategory"])) {
      $res["viewsong"] = true;
      $res["viewsongcategory"] = $auth["view songcategory"];
    }
    if (isset($auth["view song statistics"])) {
      $res["view song statistics"] = $auth["view song statistics"];
    }
    if (isset($auth["edit song"])) if (isset($auth["view songcategory"])) {
      $res["viewsong"] = true;
      $res["editsong"] = true;
      $res["viewsongcategory"] = $auth["view songcategory"];
    }
  }

  if (isset($auth["view servicegroup"])) $res_view = $auth["view servicegroup"];
  else $res_view = array ();
  //check if user is member in a group of servicegroup
  $arr = churchcore_getTableData("cs_servicegroup", "sortkey");

  $myTnGroups = churchdb_getMyGroups($user_pid, true, false);
  $myLdGroups = churchdb_getMyGroups($user_pid, true, true);

  // TODO: maybe put $arr and churchservice_getGroupsOfServiceGroup in one request:
//   SELECT cs_servicegroup.*,cs_service.*
//   FROM cs_servicegroup, cs_service
//   WHERE cs_service.servicegroup_id=cs_servicegroup.id AND (servicegroup_id = 1 OR viewall_yn=1)
  foreach ($arr as $grp) {
    $groups = churchservice_getGroupsOfServiceGroup($grp->id);
    if ($grp->viewall_yn == 1 || array_in_array($groups, $myTnGroups)) {
      $res_view[$grp->id] = true;
    }
  }
  $res["viewgroup"] = $res_view;

  if (isset($auth["edit servicegroup"])) {
    $res["editgroup"] = $auth["edit servicegroup"];
    // Copy edit permission to view permissions!
    // TODO: is there a difference to $res["viewgroup"] = $auth["edit servicegroup"]?
    foreach ($auth["edit servicegroup"] as $key => $a) {
      $res["viewgroup"][$key] = $a;
    }
  }
  else
    $res["editgroup"] = array ();


  // check if user is leader or at least member of a group
  // this is important for editing entries
  $arr = churchcore_getTableData("cs_service", "sortkey");
  $res_member = array ();
  $res_leader = array ();
  $res_edit = array ();

  foreach ($arr as $service) {
    $groups = churchservice_getGroupsOfService($service->id);
    if (array_in_array($groups, $myLdGroups)) {
      $res_member[$service->id] = true;
      $res_leader[$service->id] = true;
    }
    else if (array_in_array($groups, $myTnGroups)) {
      $res_member[$service->id] = true;
    }
    // check edit permission for service group
    if (isset($res["editgroup"][$service->servicegroup_id])) $res_edit[$service->id] = true;
  }
  $res["memberservice"] = $res_member;
  $res["leaderservice"] = $res_leader;
  $res["editservice"]   = $res_edit;

  if (isset($auth["view agenda"]))  $res["view agenda"] = $auth["view agenda"];
  if (isset($auth["edit agenda"])) {
    $res["edit agenda"] = $auth["edit agenda"];
    // copy permissions to view
    // TODO: is there a difference to $res["edit_agenda"] = $res["view agenda"]?
    foreach ($res["edit agenda"] as $key => $edit) {
      $res["view agenda"][$key] = $edit;
    }
  }
  if (isset($auth["edit agenda templates"])) $res["edit agenda templates"] = $auth["edit agenda templates"];

  $auth = $res; //$auth is global; TODO: why not use $auth all the way rather then the additional $res?

  return $res;
}

/**
 *
 * @return string
 */
function churchservice_getModulesPath() {
  return CHURCHSERVICE;
}

/**
 * extract event service data from $arr
 *
 * @param array $arr
 * @param string $auth
 * @param string $event_admin
 *
 * @return array
 */
function churchservice_extractEventServiceData($arr, $auth = null, $event_admin = false) {
  $res = array ();
  $res["id"] = (isset($arr->eventservice_id) ? $arr->eventservice_id : $arr->id);
  $res["service_id"] = $arr->service_id;
  $res["counter"] = $arr->counter;
  $res["name"] = $arr->name;
  if (isset($arr->cmsuserid))
    $res["cmsuserid"] = $arr->cmsuserid;
  $res["cdb_person_id"] = $arr->cdb_person_id;
  $res["zugesagt_yn"] = $arr->zugesagt_yn;
  $res["valid_yn"] = $arr->valid_yn;
  $res["datum"] = $arr->modified_date;
  $res["user_id"] = $arr->modified_pid;
  $res["user"] = $arr->modifieduser;
  $res["mailsenddate"] = $arr->mailsenddate;
  if (($auth != null) && ($arr->reason != null)) {
    if ((isset($auth["leaderservice"]) && isset($auth["leaderservice"][$arr->service_id]))
        || isset($auth["admin"]) || $event_admin)
        $res["reason"] = $arr->reason;
  }
  return $res;
}

/**
 * get event data, which has changed after LastLogId
 * @param array $params
 * @return array with events
 */
function churchservice_getNewEventData($params) {
  global $user;
  $last_id = $params["last_id"];
  $arr = db_query("SELECT event_id FROM {cs_eventservice}
                   WHERE modified_pid!=:user and id>=:last_id group by event_id",
                   array (":user" => $user->id, ":last_id" => $last_id));
  $events = array ();
  foreach ($arr as $data) {
    $event = churchservice_getAllEventData(array ("id" => $data->event_id));
    if (isset($event[$data->event_id])) $events[$data->event_id] = $event[$data->event_id];
  }
  return $events;
}

/**
 * Load all Events and services in the current time slot
 *
 * @param array $params
 * @return array
 */
function churchservice_getAllEventData($params) {
  global $user;
  $id = null;
  if (isset($params["id"])) $id = $params["id"];

  $auth = churchservice_getAuthorization();

  include_once (CHURCHCAL . '/churchcal_db.php');
  $cat = churchcal_getAllowedCategories(false, true);
  $cat[] = -1;

  $lastday = -getConf('churchservice_entries_last_days');

  $excs_db = db_query("SELECT id, cal_id, except_date_start, except_date_end FROM {cc_cal_except}");
  $excs = array();
  foreach ($excs_db as $exc) {
    if (!isset($excs[$exc->cal_id])) $excs[$exc->cal_id] = array();
    $excs[$exc->cal_id][] = $exc;
  }

  $res = db_query(
      'SELECT e.id, e.startdate startdate, e.valid_yn, cal.startdate cal_startdate, cal.enddate cal_enddate,
          e.cc_cal_id, cal.bezeichnung, e.special, cal.category_id, e.admin, cal.repeat_id, cal.repeat_until,
          cal.repeat_frequence, cal.repeat_option_id, cal.intern_yn, cal.notizen, cal.ort, cal.link,
         datediff(e.startdate,CURRENT_DATE) datediff
       FROM {cs_event} e, {cc_cal} cal
       WHERE cal.id=e.cc_cal_id AND '.($id != null ? "e.id=$id" : "1=1")
           ." AND cal.category_id in (" . db_implode($cat) . ")"
           . " AND DATEDIFF(now(), e.startdate)<366*2");


  $events = array ();
  if ($res != false) {
    foreach ($res as $arr) {
      if ($arr->repeat_frequence == null) unset($arr->repeat_frequence);
      if ($arr->repeat_option_id == null) unset($arr->repeat_option_id);
      if ($arr->repeat_until == null) unset($arr->repeat_until);
      if (isset($excs[$arr->cc_cal_id])) $arr->exceptions = $excs[$arr->cc_cal_id];

      // here we go!
      $events[$arr->id] = $arr;
      $event_admin = false;
      if ($arr->admin == null) unset($events[$arr->id]->admin);
      else if (in_array($user->id, explode(",", $arr->admin))) $event_admin = true;
      if ($arr->special == null) $events[$arr->id]->special = null;
      // We don't have an enddate in cs-event, so we calculate it from the calendar
      $diff = strtotime($arr->cal_enddate) - strtotime($arr->cal_startdate);
      $event_enddate = new DateTime($arr->startdate);
      $event_enddate->modify("+" . $diff . " seconds");
      $arr->enddate = $event_enddate->format('Y-m-d H:i:s');

      if ((1 == 1) && $arr->datediff > $lastday) { ///TODO: remove 1 == 1

        // Check if agenda items are available for this event
        $b = db_query( "SELECT * FROM {cs_event_item}
          WHERE event_id=:event_id limit 1",
          array (":event_id" => $arr->id))
          ->fetch();
          $arr->agenda = $b != false;

        $services = db_query("
          SELECT es.service_id, es.name, es.cdb_person_id, es.id eventservice_id, es.counter,
               es.zugesagt_yn, es.valid_yn, es.modified_date, es.modified_pid, es.mailsenddate,
               case when p.id is null then '?' else
               concat(p.vorname, ' ',p.name) end as modifieduser,
               es.reason, s.servicegroup_id, cmsuser.cmsuserid
          FROM {cs_service} s, {cs_eventservice} es left join {cdb_person} p on (es.modified_pid=p.id)
          LEFT JOIN {cdb_person} cmsuser on (es.cdb_person_id=cmsuser.id)
          WHERE es.service_id=s.id and event_id=:event_id",
          array (":event_id" => $arr->id));

        $s = array ();
        foreach ($services as $service) {
          if ($service->servicegroup_id != null && (isset($auth["viewgroup"][$service->servicegroup_id]) || $event_admin)) {
            $s[] = churchservice_extractEventServiceData($service, $auth, $event_admin);
          }
        }
        if (count($s) > 0) $events[$arr->id]->services = $s;
      }
    }
  }
  return $events;
}

/**
 * check if current $user is admin of event
 *
 * @param int $event_id
 * @return boolean
 */
function churchService_adminOfEvent($event_id) {
  $res = db_query("SELECT admin FROM {cs_event} WHERE id=$event_id")->fetch();
  $admins = explode(",", $res->admin);
  foreach ($admins as $u) {
    if ($u == $_SESSION["user"]->id) return true;
  }
  return false;
}
 /**
  * add/remove service to event
  *
  * @param array $params
  * @throws CTFail
  * @return string
  */
function churchservice_addOrRemoveServiceToEvent($params) {
  global $user;
  $auth = churchservice_getAuthorization();
  $k = 0;
  while (isset($params["col" . $k])) {
    $fields = array ();
    $fields["event_id"] = $params["id"];
    $fields["service_id"] = $params["col" . $k];

    $dt = new datetime();
    $fields["valid_yn"] = 1;
    $fields["modified_date"] = $dt->format('Y-m-d H:i:s');
    $fields["modified_pid"] = $user->id;

    // bugfix: edit list of services for one event
    $fields["modifieduser"] = $user->vorname . ' ' . $user->name;

    $db = db_query("SELECT count(*) c FROM {cs_eventservice}
                    WHERE event_id=:event_id and service_id=:service_id and valid_yn=1",
                    array (":service_id" => $fields["service_id"],
                           ":event_id" => $fields["event_id"]
                    ))->fetch();
    // should not be changed or created
    $soll = 0;  //TODO: replase ist/soll by have/need; is/shall; is/should?
    $ist = $db->c;
    if ((isset($params["val" . $k]) && ($params["val" . $k] == "checked"))) {
      if (isset($params["count" . $k])) $soll = $params["count" . $k];
      else $soll = 1;
    }

    if ($ist != $soll) {
      if ((!isset($auth["editservice"][$params["col". $k]])) &&
          (!isset($auth["leaderservice"][$params["col". $k]])) &&
          (!churchService_adminOfEvent($params["id"])))
          return t("no.rights.to.add.or.remove.service"). ": ". $params["col" . $k];

      // If only one exists but more should be added, set counter to 1 for better looking.
      if (($ist == 1) && ($soll > 1))
        db_query("UPDATE {cs_eventservice} set counter=1
                  WHERE event_id=:event_id and service_id=:service_id and counter is null",
                  array (":event_id" => $fields["event_id"],
                         ":service_id" => $fields["service_id"]
                  ));
      // If more then one exists but should be only one now, set counter to 0 for better looking.
      if (($ist > 1) && ($soll == 1))
        db_query("UPDATE {cs_eventservice} set counter=null
                  WHERE event_id=:event_id and service_id=:service_id and counter=1",
                  array (":event_id" => $fields["event_id"],
                         ":service_id" => $fields["service_id"]
                  ));
    }
    // echo "ist: $ist, soll: $soll";
    while ($ist < $soll) {
      $ist = $ist + 1;
      // if only one needed, it is the first one - counter should be null
      if ($soll == 1)  $fields["counter"] = null;
      // look for a not used count
      else if ($soll > 1) {
        $count = 1;
        $ok = false;
        // TODO: looks not performant - maybe fetch all needed services together using GROUP BY as array and use in_array()?
        // at least reuse statement rather then building a new one for each while
        while ((!$ok) && ($count < 100)) {
          $res = db_query("SELECT count(*) c FROM {cs_eventservice}
                           WHERE event_id=:event_id and service_id=:service_id and counter=$count",
                           array(":event_id" => $fields["event_id"],
                                 ":service_id" => $fields["service_id"]
                           ))->fetch();
          if ($res->c == 0) $ok = true;
          else $count = $count + 1;
        }
        $fields["counter"] = $count;
      }

      db_insert("cs_eventservice")
        ->fields($fields)
        ->execute();
      cdb_log("[ChurchService] Erstelle Service " . $fields["service_id"] . " fuer Event", 2, $fields["event_id"], "service");
    }
    while ($ist > $soll) {
      // Hole den h�chsten freien Count, wenn es keinen gibt, dann stimmt was nicht, denn
      // das wird ja eigentlich �ber JS sichergestellt, dass nur das gel�scht werden kann, wenn was frei ist
      // get max free counter; if none - something is wrong for JS assures to delete only, if it is free
      //TODO: check translation
      $res = db_query(
             "SELECT max(counter) c FROM {cs_eventservice}
              WHERE event_id=:event_id and service_id=:service_id and name is null and valid_yn=1",
              array (":event_id" => $fields["event_id"],
                     ":service_id" => $fields["service_id"]
              ))->fetch();
      if ($res == null) throw new CTFail("Error by query max(counter)");
      else {
        if ($res->c == null) $counter = "counter is null"; //TODO: translate or not?
        else $counter = "counter=" . $res->c;
        db_query("DELETE FROM {cs_eventservice}
                  WHERE event_id=:event_id and service_id=:service_id and " . $counter,
                  array(":event_id" => $fields["event_id"],
                        ":service_id" => $fields["service_id"]
                 ));
        cdb_log("[ChurchService] Entferne Service " . $fields["service_id"] . " $counter vom Event", 2, $fields["event_id"], "service");
        $ist = $ist - 1;
      }
    }
    $k++;
  } // end first while
}

/**
 * delete service
 * @param array $params
 * @throws CTNoPermission
 */
function churchservice_deleteService($params) {
  $service_id = $params["id"];
  $auth = churchservice_getAuthorization();
  if (!isset($auth["editservice"]) || (!isset($auth["editservice"][$service_id]))) throw new CTNoPermission("editservice", "churchservice");

  cdb_log("[ChurchService] Entferne Service!", 2, $service_id, "service");
  db_query("DELETE FROM {cs_eventservice} WHERE service_id=:service_id", array (":service_id" => $service_id), false);
  db_query("DELETE FROM {cs_service} WHERE id=:service_id", array (":service_id" => $service_id), false);
}

/**
 * TODO: after creating a new service the add service window will be closed rather then completed with the new service!
 * This should be able without reloading the entire page.
 *
 * @param array $params
 * @throws CTNoPermission
 */
function churchservice_editService($params) {
  $auth = churchservice_getAuthorization();

  if (!isset($auth["editgroup"]) || (!isset($auth["editgroup"][$params["servicegroup_id"]]))) {
    throw new CTNoPermission("editservice", "churchservice");
  }
  if ($params["id"] && (empty($auth["editservice"]) || empty($auth["editservice"][$params["id"]]))) {
    throw new CTNoPermission("editservice", "churchservice");
  }

  $i = new CTInterface();
  $i->setParam("id", false);
  $i->setParam("bezeichnung");
  $i->setParam("notiz");
  $i->setParam("servicegroup_id");
  $i->setParam("cdb_gruppen_ids", false);
  $i->setParam("cdb_tag_ids", false);
  $i->setParam("cal_text_template", false);
  $i->setParam("sendremindermails_yn");
  $i->setParam("allowtonotebyconfirmation_yn");
  $i->setParam("sortkey");

  if ($params["id"] == "null" || $params["id"] == "") {
    db_insert("cs_service")
      ->fields($i->getDBInsertArrayFromParams($params))
      ->execute(false);
  }
  else {
    db_update("cs_service")
      ->fields($i->getDBInsertArrayFromParams($params, true))
      ->condition("id", $params["id"], "=")
      ->execute(false);
  }
}

/**
 * update event service
 *
 * @param array $params
 * @throws CTNoPermission
 * @return array
 */
function churchservice_updateEventService($params) {
  global $user, $base_url;

  $id = $params["id"];
  $name = isset($params["name"]) ? $params["name"] : null;
  $cdb_person_id = (isset($params["cdb_person_id"]) ? $params["cdb_person_id"] : null);
  $reason = (isset($params["reason"]) ? $params["reason"] : null);
  $zugesagt_yn = $params["zugesagt_yn"];

  include_once (CHURCHSERVICE . "/churchservice_db.php");

  $res = array ();

  if ($name == "null") $name = null;
  if ($cdb_person_id == "null") $cdb_person_id = null;

  // look if event is still valid
  $arr = db_query("SELECT * FROM {cs_eventservice}
                   WHERE id=:id",
                   array (":id" => $id))
                   ->fetch();
  if (!$arr) return "Eintrag nicht gefunden, id nicht g�ltig!";
  if ($arr->valid_yn != 1 && !isset($params["valid_yn"])) return "Eintrag konnte nicht angepasst werden, da veraltet. Bitte neu laden!";

  // check auth
  $auth = churchservice_getAuthorization();
    // Es ist trotzdem erlaubt, wenn die PersonId eingetragen ist, dann wurde er ja angefragt
  if (!isset($auth["editservice"][$arr->service_id]) &&
      !isset($auth["memberservice"][$arr->service_id]) &&
      !churchService_adminOfEvent($arr->event_id) &&
      $arr->cdb_person_id != $user->id)
      throw new CTNoPermission("editservice", "churchservice");

  // Wenn die neue �nderung vom gleichen User kommt und noch kein Cron gelaufen ist,
  // Oder wenn valid_yn valide ist, denn dann soll es upgedates werden!
  // brauchen wir kein neuen Insert, sondern machen nur ein Update.
  // Denn wahrscheinlich war es vorher nur ein Versehen.
  // TODO: translation correct?
  // if changing user is the same as last time and cron had not yet run
  // or if valid_yn is valide (update wished), we dont need an insert, only an update,
  // because the last edit probably was a mistake
  $dt = new datetime();
  if (($arr->modified_pid == $user->id && $arr->mailsenddate == null) || (isset($params["valid_yn"]))) {
    $valid_yn = getVar("valid_yn", 1, $params);
    db_update("cs_eventservice")
    ->fields(array (
        "name" => $name,
        "cdb_person_id" => $cdb_person_id,
        "valid_yn" => $valid_yn,
        "zugesagt_yn" => $zugesagt_yn,
        "reason" => $reason,
        "mailsenddate" => null,
        "modified_date" => $dt->format('Y-m-d H:i:s'),
        "modified_pid" => $user->id,
    ))->condition("id", $id, "=")
    ->execute();
    $new_id = $id;
  }
  else {
    // new entry for edit
    $new_id = db_insert("cs_eventservice")
                ->fields(array (
                    "event_id" => $arr->event_id,
                    "service_id" => $arr->service_id,
                    "valid_yn" => 1,
                    "counter" => $arr->counter,
                    "name" => $name,
                    "cdb_person_id" => $cdb_person_id,
                    "zugesagt_yn" => $zugesagt_yn,
                    "reason" => $reason,
                    "modified_date" => $dt->format('Y-m-d H:i:s'),
                    "modified_pid" => $user->id
                ))->execute();

    //if all ok set existing entry to old
    db_update("cs_eventservice")
      ->fields(array ("valid_yn" => 0))
      ->condition("id", $id, "=")
      ->execute();
  }

  include_once (CHURCHCORE . "/churchcore_db.php");
  $leader = churchcore_getPersonById($arr->modified_pid);

  $event = db_query("SELECT e.startdate datum, c.bezeichnung FROM {cs_event} e, {cc_cal} c
                     WHERE e.cc_cal_id=c.id and e.id=:event_id",
                     array (":event_id" => $arr->event_id))
                     ->fetch();
  $service = churchcore_getTableData("cs_service", "", "id=" . $arr->service_id);

  if ($event && $service) {
    $subject = "[". getConf('site_name', "ChurchTools"). "] ";
    $txt = 'nothing defined'; // for worst case only ;-)
    $service = $service[$arr->service_id];
    $data = array(
      'leader'  => $leader,
      'user'    => $user,
      'service' => $service,
      'event'   => $event,
      'reason'  => $reason,
      'eventUrl'  => $base_url . '?q=churchservice&id=' . $arr->event_id,
    );
    if ($zugesagt_yn) {
      $data['approved'] = true;

      $subject .= t("surname.name.has.confirmed.request", $user->vorname, $user->name);
      $txt = t("surname.name.has.confirmed.service.x.on.date.event",
          $user->vorname,
          $user->name,
          $service->bezeichnung,
          $event->datum,   //TODO: remove seconds from date
          $event->bezeichnung);
    }
    // propose
    else if ($name) {
      $data['approved'] = true;
      $subject .= t("surname.name.has.proposed.someone", $user->vorname, $user->name);
      $txt = t("surname.name.has.proposed.x.for.service.y.on.date.event",
          $user->vorname,
          $user->name,
          $name,
          $service->bezeichnung,
          $event->datum,   //TODO: remove seconds from date
          $event->bezeichnung);
      $subject .= t("surname.name.has.proposed.someone", $user->vorname, $user->name);
    }
    // cancel
    else {
      $data['approved'] = true;
      $subject .= t("surname.name.has.canceled.request", $user->vorname, $user->name);
      $txt = t("surname.name.has.canceled.service.x.on.date.event",
          $user->vorname,
          $user->name,
          $service->bezeichnung,
          $event->datum,   //TODO: remove seconds from date
          $event->bezeichnung);
    }

    ct_notify("service", $arr->service_id, $txt);

    // TODO: what does leader really mean? seems not to be a groupleader but the current user? rename?
    if ($leader) {
      // send mail, if another then the inquirer himself confirmed or canceled
      //TODO: maybe use asker, better to understand for nonenglish programmers
      //TODO: test email template
      if (!empty($leader->email) && $user && $leader->id != $user->id) {
        $setting = churchcore_getUserSettings("churchservice", $leader->id);
        if (isset($setting["informInquirer"]) && ($setting["informInquirer"] == 1)) {

          $txt = $base_url . '?q=churchservice&id=' . $arr->event_id;
          $lang = getUserLanguage($leader->id);
          $content = getTemplateContent('email/serviceRequest', 'churchservice', $data, null, $lang);
          churchservice_send_mail($subject, $content, $leader->email);
        }
      }
      if (!isset($setting["informInquirer"])) {
        churchcore_saveUserSetting("churchservice", $leader->id, "informInquirer", 0);
      }
    }
  }

  $arr = db_query("SELECT es.*, concat(p.vorname,' ',p.name) as modifieduser FROM {cs_eventservice} es, {cdb_person} p
                    WHERE p.id=es.modified_pid and es.id=:id", array (":id" => $new_id))->fetch();
  $res["eventservice"] = churchservice_extractEventServiceData($arr);
  $res["result"] = true;

  return $res;
}

/**
 * get person(s) by group id as array[group][person]
 *
 * @param array $params
 *          - params[ids] = comma-separated liste of group ids
 * @return array with persons
 */
function churchservice_getPersonByGroupIds($params) {
  $ids = db_cleanParam($params["ids"]);
  $res = db_query("SELECT g.bezeichnung, gpg.gruppe_id g_id, p.id p_id, gp.id gp_id, p.vorname, p.name,
                          p.email, gp.imageurl, p.cmsuserid, gpg.status_no leiter
                  FROM {cdb_person} p, {cdb_gemeindeperson} gp, {cdb_gemeindeperson_gruppe} gpg, {cdb_gruppe} g
                  WHERE gpg.gemeindeperson_id=gp.id AND gp.person_id=p.id AND g.id=gpg.gruppe_id
                        AND gpg.status_no>=0 AND gpg.gruppe_id IN (". $ids. ")");
  $arr = array ();
  foreach ($res as $p) {
    if (!isset($arr[$p->g_id])) $arr[$p->g_id] = array ();
    $tags_res = db_query("SELECT * FROM {cdb_gemeindeperson_tag}
                          WHERE gemeindeperson_id=:gp_id", array (":gp_id" => $p->gp_id));
    $p->tags = array ();
    foreach ($tags_res as $tag) $p->tags[] = $tag->tag_id;
    $arr[$p->g_id][$p->p_id] = $p;
    }
  return $arr;
}

/**
 * get user settings and set remindMe to on if not yet
 *
 * @param int $user_pid
 * @return array with settings
 */
function churchservice_getUserSettings($user_pid) {
  $arr = churchcore_getUserSettings("churchservice", $user_pid);
  $arr2 = churchcore_getUserSettings("churchdb", $user_pid);
  if (!isset($arr["remindMe"])) {
    $arr["remindMe"] = "1";
    churchcore_saveUserSetting("churchservice", $user_pid, "remindMe", "1");
  }
  if (isset($arr2["signature"])) $arr["signature"] = $arr2["signature"];

  return $arr;
}

/**
 * get last log id? get last changes from eventservice?
 * @param number $last_id
 */
function churchservice_getLastLogId($last_id = 0) {
  global $user;
  $arr = db_query("SELECT MAX(id) max FROM {cs_eventservice}
                   WHERE modified_pid!=:user AND id>=:last_id",
                   array (":user" => $user->id, ":last_id" => $last_id))
                  ->fetch();

  return $arr->max;
}

/**
 * poll for news
 *
 * @param array $params
 * @return array
 */
function churchservice_pollForNews($params) {
  $last_id = $params["last_id"];
  $arr["lastLogId"] = churchservice_getLastLogId($last_id);

  return $arr;
}

/**
 * echo ical for services to do from user id (read from request)
 *
 * TODO: use template, include texts added by surroundWithVCALENDER()
 */
function churchservice_ical() {
  global $base_url, $config;

  if (!$id = getVar("id")) echo t("please.specify.id");

  drupal_add_http_header('Content-Type', 'text/calendar;charset=utf-8', false);
  drupal_add_http_header('Content-Disposition', 'inline;filename="ChurchTools.ics"', false);
  drupal_add_http_header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', false);
  drupal_add_http_header('Cache-Control', 'private', false);

  $content = drupal_get_header();

  include_once ('./' . CHURCHSERVICE . '/churchservice_db.php');
  $arr = churchservice_getUserCurrentServices($id);

  // TODO: use txt Template
  $txt = "";
  foreach ($arr as $res) {
    $txt .= "BEGIN:VEVENT\r\n";
    $txt .= "ORGANIZER:MAILTO:" . getConf('site_mail', '') . "\r\n";
    if ($res->zugesagt_yn == 1) $txt .= "SUMMARY:" . $res->dienst . " (" . $res->servicegroup . ")\r\n";
    else $txt .= "SUMMARY:Anfrage: " . $res->dienst . " (" . $res->servicegroup . ")?\r\n";
    $txt .= "X-MICROSOFT-CDO-BUSYSTATUS:BUSY\r\n";
    $txt .= "URL:" . $base_url . "/?q=churchservice/entrylist\r\n";
    if ($res->ort != "") $txt .= "LOCATION:" . $res->ort . "\r\n";
    $txt .= "DESCRIPTION:" . $res->dienst . " (" . $res->servicegroup . ") bei " . $res->event . ".";
    if ($res->zugesagt_yn == 1) $txt .= "\r\n";
    else $txt .= " " . t("request.from") . " $res->vorname $res->name [$res->modified_pid]\r\n";
    $txt .= "DTSTAMP:" . $res->modified_date . "\r\n";
    $txt .= "UID:" . $res->eventservice_id . "\r\n";
    $txt .= "DTSTART;TZID=" . $config["timezone"] . ":" . $res->datum_start . "\r\n";
    $txt .= "DTEND;TZID=" . $config["timezone"] . ":" . $res->datum_end . "\r\n";
    $txt .= "END:VEVENT\r\n";
  }
  echo surroundWithVCALENDER($txt);
}

/**
 * save note
 *
 * @param int $event_id
 * @param string $text
 *
 * @return string ok
 */
function churchservice_saveNote($event_id, $text) {
  $text = str_replace("\'", "'", $text);
  $text = str_replace('\"', '"', $text);
  db_query("UPDATE {cs_event} set special=:text
            WHERE id=$event_id", array (":text" => $text));
  return "ok";
}

/**
 * get services from event template
 *
 * @param array $auth
 * @return array or null
 */
function churchservice_getEventtemplateServices($auth) {
  if (!isset($auth["write"])) return null;

  $res = db_query("SELECT * FROM {cs_eventtemplate_service}");
  $arrs = null;
    foreach ($res as $arr) {
    $es = isset($arrs[$arr->eventtemplate_id]) ? $arrs[$arr->eventtemplate_id] : array ();
    $es[$arr->service_id] = $arr->count;
    $arrs[$arr->eventtemplate_id] = $es;
    }
  return $arrs;
}

/**
 * update or insert event template
 *
 * @param int $template_id; if no id insert, else update
 * @param unknown $stunde
 * @param unknown $minute
 * @param unknown $category_id
 * @param unknown $event_bezeichnung
 * @param unknown $special
 * @param unknown $admin
 * @param unknown $services
 */
function churchservice_updateOrInsertTemplate($template_id, $bezeichnung, $stunde, $minute, $dauer_sec, $category_id, $event_bezeichnung, $special, $admin, $services) {
  $fields = array ();
  $fields["bezeichnung"] = $bezeichnung;
  $fields["event_bezeichnung"] = $event_bezeichnung;
  $fields["stunde"] = $stunde;
  $fields["minute"] = $minute;
  $fields["dauer_sec"] = $dauer_sec;
  $fields["special"] = $special;
  $fields["category_id"] = $category_id;
  $fields["admin"] = $admin;

  cdb_log("[ChurchService] Update Template $template_id: cat:$category_id, bez:$event_bezeichnung", 2);

  if ($template_id == null) {
    $arr = db_query("SELECT MAX(id) id FROM {cs_eventtemplate}")->fetch();
    $template_id = $arr->id + 1;
    $fields["id"] = $template_id;

    db_insert("cs_eventtemplate")
      ->fields($fields)
      ->execute();
  }
  else {
    db_update("cs_eventtemplate")
      ->fields($fields)
      ->condition('id',$template_id,"=")
      ->execute();
  }
  if (isset($services)) {
    foreach ($services as $service => $val) {
      if ($val > 0) {
        db_query("INSERT INTO {cs_eventtemplate_service} (eventtemplate_id, service_id, count)
                  VALUES (:eventtemplate_id, :service_id, :count) ON DUPLICATE KEY UPDATE service_id=:service_id, count=:count",
                  array (":eventtemplate_id" => $template_id,
                         ":service_id" => $service,
                         ":count" => $val
                  ));
      }
      else
        db_query("DELETE FROM {cs_eventtemplate_service}
                  WHERE eventtemplate_id=:eventtemplate_id AND service_id=:service_id",
                  array(":eventtemplate_id" => $template_id,
                        ":service_id" => $service,
                    ));
    }
  }
}

/**
 * get all facts for event
 * @return array with facts
 */
function churchservice_getAllFacts() {
  global $config;
  $res = db_query("SELECT id, ef.fact_id, ef.value FROM {cs_event_fact} ef, {cs_event} e
                   WHERE e.id=ef.event_id"); // and datediff(e.datum,current_date)-".$config["churchservice_entries_last_days"]);
  $facts = array ();
  foreach ($res as $arr) {
    if (isset($facts[$arr->id])) $fact = $facts[$arr->id];
    else $fact = array();
    $fact[] = $arr;
    $facts[$arr->id] = $fact;

//     //TODO: Is this the same as above?
//     if (!isset($facts[$arr->id])) $facts[$arr->id] = array();
//     $facts[$arr->id][] = $fact;
  }
  return $facts;
}

/**
 * get all songs
 * @return array songs
 */
function churchservice_getAllSongs() {
  global $config;
  $arr = null;

  if ($db_songs = churchcore_getTableData("cs_song", "bezeichnung")) {
    $files_song = churchcore_getFilesAsDomainIdArr("song");
    $files_song_arrangement = churchcore_getFilesAsDomainIdArr("song_arrangement");
    foreach ($db_songs as $db_s) {
      $db_arrangements = db_query("SELECT * FROM {cs_song_arrangement} WHERE song_id=$db_s->id");
      $arrangement = array ();
      foreach ($db_arrangements as $db_a) {
        if (isset($files_song_arrangement[$db_a->id])) $db_a->files = $files_song_arrangement[$db_a->id];
        unset($db_a->song_id);
        $arrangement[$db_a->id] = $db_a;
      }
      $db_s->arrangement = $arrangement;
      if (isset($files_song[$db_s->id])) $db_s->files = $files_song[$db_s->id];
      $arr["songs"][$db_s->id] = $db_s;
    }
  }

  return $arr;
}

/**
 * add new song
 * @param array $params
 * @return int song id
 */
function churchservice_addNewSong($params) {
  $i = new CTInterface();
  $i->setParam("bezeichnung");
  $i->setParam("songcategory_id");
  $i->setParam("ccli");
  $i->setParam("author");
  $i->setParam("copyright");
  $i->addModifiedParams();

  $params["song_id"] = db_insert("cs_song")
                         ->fields($i->getDBInsertArrayFromParams($params))
                         ->execute(false);

  $params["bezeichnung"] = "Standard-Arrangement";
  $params["default_yn"] = 1;
  $i = new CTInterface();
  $i->setParam("song_id");
  $i->setParam("bezeichnung");
  $i->setParam("bpm");
  $i->setParam("beat");
  $i->setParam("tonality");
  $i->setParam("default_yn");
  $i->addModifiedParams();
  db_insert("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->execute(false);

  return $params["song_id"];
}

/**
 * edit song
 * @param array $params
 */
function churchservice_editSong($params) {
  $i = new CTInterface();
  $i->setParam("id");
  $i->setParam("bezeichnung");
  $i->setParam("songcategory_id");
  $i->setParam("ccli");
  $i->setParam("author");
  $i->setParam("copyright");
  $i->addModifiedParams();

  db_update("cs_song")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 * delete song
 * TODO: performance -  get ALL songs to delete ONE?
 *
 * @param array $params
 * @throws CTException
 */
function churchservice_delSong($params) {
  $songs = churchservice_getAllSongs();
  if (!isset($songs["songs"][$params["id"]])) throw new CTException("Song nicht gefunden!");
  $song = $songs["songs"][$params["id"]];
  if (isset($song->arrangement)) {
    foreach ($song->arrangement as $arr) {
      churchservice_delArrangement(array ("id" => $arr->id));
    }
    db_delete("cs_song")
    ->fields(array("id"=>$params["id"]))
    ->condition("id", $params["id"], "=")
    ->execute(false);
  }
}

/**
 * edit song arrangement
 * @param unknown $params
 */
function churchservice_editArrangement($params) {
  $i = new CTInterface();
  $i->setParam("id");
  $i->setParam("bezeichnung");
  $i->setParam("tonality");
  $i->setParam("bpm");
  $i->setParam("beat");
  $i->setParam("length_min");
  $i->setParam("length_sec");
  $i->setParam("note");
  $i->addModifiedParams();

  db_update("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 * add arrangement
 * @param array $params
 * @return unknown
 */
function churchservice_addArrangement($params) {
  $i = new CTInterface();
  $i->setParam("song_id");
  $i->setParam("bezeichnung");
  $i->addModifiedParams();
  $res = db_insert("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->execute(false);
  return $res;
}

/**
 * delete arrangement
 * @param unknown $params
 */
function churchservice_delArrangement($params) {
  $i = new CTInterface();
  $i->setParam("id");

  $files = churchcore_getFilesAsDomainIdArr("song_arrangement", $params["id"]);
  if (isset($files) && isset($files[$params["id"]])) {
    foreach ($files[$params["id"]] as $file) {
      churchcore_delFile($file->id);
    }
  }
  db_delete("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 *
 * @param a $params
 */
function churchservice_deleteSong($params) {
  $arrs = churchcore_getTableData('cs_song_arrangement', null, 'song_id=' . $params["id"]);
  $files = churchcore_getFiles("song_arrangement");
  if ($arrs) foreach ($arrs as $arr) {  //TODO: without if there was an error on deleting a song - return always an array in function
    if ($files) foreach ($files as $file) {
      if ($file->domain_id == $arr->id) {
        churchcore_delFile($file->id);
      }
    }
  }
  $i = new CTInterface();
  $i->setParam("id");
  db_delete("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("song_id", $params["id"], "=")
    ->execute(false);
  db_delete("cs_song")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 * set arrangement as standard
 * @param array $params
 */
function churchservice_makeAsStandardArrangement($params) {
  $i = new CTInterface();
  $params["default_yn"] = 0;
  $i->setParam("default_yn");
  $i->setParam("song_id");
  $i->addModifiedParams();

  db_update("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("song_id", $params["song_id"], "=")
    ->execute(false);
  $params["default_yn"] = 1;

  db_update("cs_song_arrangement")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 * edit person weight for service group (for auto planning?)
 * @param array $params
 */
function churchservice_editServiceGroupPersonWeight($params) {
  $i = new CTInterface();
  $i->setParam("servicegroup_id");
  $i->setParam("person_id");
  $i->setParam("max_per_month");
  $i->setParam("relation_weight");
  $i->setParam("morning_weight");
  $i->addModifiedParams();

  try {
    db_insert("cs_servicegroup_person_weight")
      ->fields($i->getDBInsertArrayFromParams($params))
      ->execute(false);
  }
  catch (Exception $e) {
    db_query("
        UPDATE {cs_servicegroup_person_weight}
        SET max_per_month=:max_per_month, relation_weight=:relation_weight, morning_weight=:morning_weight
        WHERE servicegroup_id=:servicegroup_id and person_id=:person_id",
        $i->getDBParamsArrayFromParams($params));
  }
}

/**
 * save fact
 * @param unknown $params
 */
function churchservice_saveFact($params) {
  global $user;

  $event_id = $params["event_id"];
  $fact_id = $params["fact_id"];
  $value = $params["value"];

  $dt = new datetime();

  if ($value == "") db_query("DELETE FROM {cs_event_fact}
                              WHERE event_id=$event_id and fact_id=$fact_id");
  else db_query(
      "INSERT INTO {cs_event_fact} (event_id, fact_id, value, modified_date, modified_pid)
       VALUES ($event_id, $fact_id, $value, '" . $dt->format('Y-m-d H:i:s') . "', $user->id)
       ON DUPLICATE KEY UPDATE value=$value, modified_pid=$user->id, modified_date='". $dt->format('Y-m-d H:i:s'). "'");
  cdb_log("[ChurchService] Speichere Fakt $value bei Fakt $fact_id, Event $event_id", 2, $fact_id, "fact");
}

/**
 * get person weight for service group (for auto planning?)
 * @return array
 */
function churchservice_getServiceGroupPersonWeight() {
  $rel_types = db_query("SELECT id FROM {cdb_beziehungstyp} WHERE export_aggregation_yn=1 limit 1")->fetch();
  $p = db_query("SELECT * FROM {cs_servicegroup_person_weight}");
  $res = array ();
  if ($p != false) foreach ($p as $s) {
    if (isset($res[$s->person_id])) $arr = $res[$s->person_id];

    if (($rel_types != null) && ($s->relation_weight != 0)) {
      $rel = db_query("SELECT * FROM {cdb_beziehung}
                       WHERE vater_id=:id or kind_id=:id and beziehungstyp_id=$rel_types->id",
                       array(":id" => $s->person_id))
                       ->fetch();
      if ($rel != null) {
        if ($rel->kind_id != $s->person_id) $s->relation_id = $rel->kind_id;
        else $s->relation_id = $rel->vater_id;
//        $s->relation_id = $rel->kind_id == $s->person_id ? $rel->vater_id : $rel->kind_id; // TODO: same as above
      }
      else $s->relation_id = null;
    }
    $arr[$s->servicegroup_id] = $s;
    $res[$s->person_id] = $arr;
  }
  return $res;
}

/**
 * delete absent
 * @param int $id
 */
function churchservice_delAbsent($id) {
  db_query("DELETE FROM {cs_absent} WHERE id=:id", array (":id" => $id));
}

/**
 * churchservice ajax
   */
function churchservice_ajax() {
  include_once ("churchservice_db.php");

  $module = new CTChurchServiceModule("churchservice");
  $ajax = new CTAjaxHandler($module);

  $ajax->addFunction("pollForNews", "view");
  $ajax->addFunction("getNewEventData", "view");
  $ajax->addFunction("getAllEventData", "view");
  $ajax->addFunction("getPersonByGroupIds", "view");

  // Facts
  $ajax->addFunction("getAllFacts", "view facts || edit facts");
  $ajax->addFunction("saveFact", "edit facts");

  $ajax->addFunction("deleteService");
  $ajax->addFunction("editService");

  $ajax->addFunction("addOrRemoveServiceToEvent");

  drupal_json_output($ajax->call());
}
