<?php

/**
 * ChurchTools 2.0
 * http://www.churchtools.de
 *
 * Copyright (c) 2014 Jens Martin Rauen
 * Licensed under the MIT license, located in LICENSE.txt
 *
 * ChurchCal Module
 * Depends on ChurchCore
 */
function churchcal_main() {
  global $config, $base_url, $config, $embedded;

  drupal_add_css(ASSETS . '/fullcalendar/fullcalendar.css');
  if (isset($_GET["printview"])) drupal_add_css(ASSETS . '/fullcalendar/fullcalendar.print.css');

  drupal_add_css(ASSETS . '/simplecolorpicker/jquery.simplecolorpicker.css');
  drupal_add_js(ASSETS . '/simplecolorpicker/jquery.simplecolorpicker.js');

  drupal_add_js(ASSETS . '/fullcalendar/moment.min.js');
  drupal_add_js(ASSETS . '/fullcalendar/fullcalendar.min.js');

  drupal_add_js(CHURCHCORE . '/cc_events.js');
  drupal_add_js(CHURCHCORE . '/cc_abstractview.js');
  drupal_add_js(CHURCHCORE . '/cc_standardview.js');
  drupal_add_js(CHURCHCORE . '/cc_maintainstandardview.js');
  drupal_add_js(CHURCHCAL . '/eventview.js');
  drupal_add_js(CHURCHCAL . '/yearview.js');
  drupal_add_js(CHURCHCAL . '/calendar.js');
  drupal_add_js(CHURCHCAL . '/cal_sources.js');

  drupal_add_js(createI18nFile("churchcore"));
  drupal_add_js(createI18nFile("churchcal"));

  $txt = '';

  if ($catId = getVar("category_id")) {
  	include_once (CHURCHCAL. '/churchcal_db.php');
  	$auth = churchcal_getAuthForAjax();
  	$perm = true;
  	foreach (explode(",", $catId) as $id) {
      // Check permission, perhaps show login mask
      if (empty($auth["view category"]) || empty($auth["view category"][$id])) $perm = false;
  	}
  	if (!$perm) {
  	  include_once(MAIN.'/login.php');
  	  $login = login_main();
  	  if (!userLoggedIn()) return $login;
  	}
    $txt .= '<input type="hidden" id="filtercategory_id" name="category_id" value="' . $catId . '"/>' . NL;
    if ($id = getVar("id")) { // only of category_id is set
      $txt .= '<input type="hidden" id="filterevent_id" name="id" value="' . $id . '"/>' . NL;
    }
  }
  if (getVar("printview")) {
    $txt .= '<input type="hidden" id="printview" value="true"/>';
    $embedded = true;
  }

  if ($embedded) {
    if ($catSel = getVar("category_select")) {
      $txt .= '<input type="hidden" id="filtercategory_select" name="category_select" value="' . $catSel . '"/>' . NL;
    }
    if ($cssUrl = getVar("cssurl")) drupal_add_css($cssUrl);
    // if (getConf("churchcal_css", "-") != "-") $txt .= '<style>' . getConf("churchcal_css") . '</style>'; //TODO: is
    // the '-' important?
    if ($css = getConf("churchcal_css")) $txt .= "<style>$css</style>" . NL;
    if (getVar("minical")) $txt .= '<input type="hidden" id="isminical"/>';
    $txt .= '
        <div class="row-fluid">
          <div id="cdb_filter"></div>
        </div>
        <div id="cdb_content"><div id="calendar"></div></div>
    ';
    if (getVar("embedded")) $txt.= '<input type="hidden" id="isembedded"/>';
    if ($t = getVar("title"))     $txt .= '<input type="hidden" id="embeddedtitle" value="' . $t . '"/>';
    if ($e = getVar("entries"))   $txt .= '<input type="hidden" id="entries" value="' . $e . '"/>';
    if ($s = getVar("startdate")) $txt .= '<input type="hidden" id="init_startdate" value="' . $s . '"/>';
    if ($e = getVar("enddate"))   $txt .= '<input type="hidden" id="init_enddate" value="' . $e . '"/>';
  }
  else
    $txt .= '
      <div class="row-fluid">
    <div class="span3"><div id="cdb_filter"></div></div>
    <div class="span9"><div id="header" class="pull-right"></div><div id="cdb_content"><div id="calendar"></div></div></div>
        <p align=right><small>
          <a target="_blank" href="' . $base_url .'?q=churchcal&embedded=true"> ' . t("embed", getConf("churchcal_name")) . '</a>
          <a target="_clean" href="/?q=churchwiki#WikiView/filterWikicategory_id:0/doc:ChurchCal%C2%A0einbetten/"><i class="icon-question-sign"></i></a>
          &nbsp; <a id="abo" href="' . $base_url . '?q=churchcal/ical">' . $config["churchcal_name"] . ' ' . t("subscribe.to.ical") . '</a>' . '</small>';

  if ($d = getVar("date"))     $txt .= '<input type="hidden" name="viewdate" id="viewdate" value="' . $d . '"/>';
  if ($v = getVar("viewname")) $txt .= '<input type="hidden" name="viewname" id="viewname" value="' . $v . '"/>';

  return $txt;
}

/**
 *
 * @return CTModuleForm
 */
function churchcal_getAdminForm() {
  global $config;

  $model = new CTModuleForm("churchcal");
  if (!isset($config["churchcal_maincalname"])) $config["churchcal_maincalname"] = "Gemeindekalender";
  $model->addField("churchcal_maincalname", "", "INPUT_REQUIRED", t('name.of.main.calendar'))
    ->setValue($config["churchcal_maincalname"]);

  if (!isset($config["churchcal_firstdayinweek"])) $config["churchcal_firstdayinweek"] = "1";
  $model->addField("churchcal_firstdayinweek", "", "INPUT_REQUIRED", t('first.day.in.week'))
    ->setValue($config["churchcal_firstdayinweek"]);

  if (!isset($config["churchcal_entries_last_days"])) $config["churchcal_entries_last_days"] = "180";
  $model->addField("churchcal_entries_last_days", "", "INPUT_REQUIRED", t('data.from.x.how.many.days.in.the.past.to.load', getConf("churchcal_name")))
    ->setValue($config["churchcal_entries_last_days"]);

  if (!isset($config["churchcal_css"])) $config["churchcal_css"] = "";
  $model->addField("churchcal_css", "", "TEXTAREA", t('css.for.embedded.cal'))
    ->setValue($config["churchcal_css"]);

  return $model;
}

/**
 *
 * @return string; empty div
 */
function churchcal_getUserOpenMeetingRequests() {
  return '<div id="cc_openmeetingrequests"></div>';
}

/**
 *
 * @return string; empty div
 */
function churchcal_getUserMeetings() {
  return '<div id="cc_nextmeetingrequests"></div>';
}

/**
 *
 * @return string; empty div
 */
function churchcal_getUserMyMeetings() {
  global $user;
  $db = db_query("SELECT count(*) count, c.id, c.category_id, c.startdate, c.bezeichnung,
                  sum(if (mr.zugesagt_yn=1,1,0)) as zugesagt,
                  sum(if (mr.zugesagt_yn is null AND mr.response_date is not null,1,0)) as perhaps,
                  sum(if (mr.response_date is not null,1,0)) as response
             FROM {cc_cal} c, {cc_meetingrequest} mr
             WHERE c.id = mr.cal_id
              AND c.modified_pid=$user->id AND DATEDIFF(c.startdate,now())>=0
              GROUP BY c.id, c.category_id, c.startdate, c.bezeichnung
              ORDER BY c.startdate");
  $txt ="";
  foreach ($db as $entry) {
    $txt .= '<li><a href="?q=churchcal&category_id=' . $entry->category_id . '&id=' . $entry->id . '">' . churchcore_stringToDateDe($entry->startdate) . " - " . $entry->bezeichnung . "</a>";
    $txt .= '<p><small>';
    $txt .= '<span class="badge badge-info">' . ($entry->count-$entry->response) . '</span> Offen &nbsp;';
    if ($entry->zugesagt>0)
      $txt .= '<span class="badge badge-success">' . $entry->zugesagt . '</span> Zusage &nbsp;';
    if ($entry->perhaps>0)
      $txt .= '<span class="badge">' . ($entry->perhaps) . '</span> Vielleicht &nbsp;';
    if ($entry->response-$entry->perhaps-$entry->zugesagt>0)
      $txt .= '<span class="badge badge-important">' . ($entry->response-$entry->perhaps-$entry->zugesagt) . '</span> Absage &nbsp;';
    $txt .= "</small>";

  }
  if ($txt!="") $txt = "<ul>" . $txt . "</ul>";
  return $txt;
}

function churchcal_getNextGroupCalDates() {
  include_once("churchcal_db.php");
  $cats = churchcal_getAllowedCategories(true);
  $ids = array();
  foreach ($cats as $cat) {
    if ($cat->oeffentlich_yn==0) {
      $ids[] = $cat->id;
    }
  }
  if (count($ids) > 0) {
    $ret = "";
    $now = new DateTime();
    $end = new DateTime();
    $end->modify("+99 days");
    $categories = churchcal_getCalPerCategory(array("category_ids" => $ids), true);
    foreach ($categories as $id=>$category) {
      $entries = array();
      $count = 0;
      foreach ($category as $source) {
        $ds = getAllDatesWithRepeats($source, 0, 99);
        if ($ds) foreach ($ds as $d) {
          if ($d >= $now && $d <= $end) {
            $entries[$d->format('Y.m.d H:i')] = $d->format('d.m.Y H:i') . ' ' . $source->bezeichnung;
          }
        }
      }
      if (count($entries)>0) {
        ksort($entries);
        $ret.= "<li>" . $cats[$id]->bezeichnung . "<br><p><small>";
        $c = 0;
        foreach ($entries as $entry) {
          if ($c < 3) $ret.=$entry . '<br/>';
          else if ($c == 3) $ret.="...";
          $c++;
        }
        $ret.='</small>';
      }
    }
    if ($ret!="") return '<ul>' . $ret . '</ul>';
  }
}

/**
 *
 * @return array
 */
function churchcal_blocks() {
  return (array (
          1 => array (
              "label" => t("your.open.meeting.requests"),
              "col" => 2,
              "sortkey" => 1,
              "html" => churchcal_getUserOpenMeetingRequests(),
              "help" => t('meeting.requests'),
              "class" => "cal-request",
          ),
          2 => array (
              "label" => t("your.next.meetings"),
              "col" => 2,
              "sortkey" => 2,
              "html" => churchcal_getUserMeetings(),
              "help" => t('meeting.requests'),
              "class" => "cal-request",
          ),
          3 => array (
              "label" => t("meeting.requests.made.by.you"),
              "col" => 2,
              "sortkey" => 3,
              "html" => churchcal_getUserMyMeetings(),
              "class" => "cal-request",
          ),
          4 => array (
              "label" => t("upcoming.dates.of.your.group.calendars"),
              "col" => 2,
              "sortkey" => 3,
              "html" => churchcal_getNextGroupCalDates(),
              "class" => "cal-request",
          ),
  ));
}

/**
 * get auth for churchcal
 * @return array auth
 */
function churchcal_getAuth() {
  $cc_auth = array ();
  $cc_auth = addAuth($cc_auth, 401, 'view', 'churchcal', null, t('view.x', getConf("churchcal_name")), 1);
  $cc_auth = addAuth($cc_auth, 403, 'view category', 'churchcal', 'cc_calcategory', t('view.single.calendar'), 0);
  $cc_auth = addAuth($cc_auth, 404, 'edit category', 'churchcal', 'cc_calcategory', t('edit.single.calendar'), 0);
  $cc_auth = addAuth($cc_auth, 409, 'assistance mode', 'churchcal', null,  t('assistant.mode'), 1);
  $cc_auth = addAuth($cc_auth, 407, 'create personal category', 'churchcal', null, 'Pers&ouml;nlichen Kalender erstellen', 1);
  $cc_auth = addAuth($cc_auth, 406, 'admin personal category', 'churchcal', null, 'Pers&ouml;nliche Kalender administrieren', 1);
  $cc_auth = addAuth($cc_auth, 408, 'create group category', 'churchcal', null, t('create.group.calendar'), 1);
  $cc_auth = addAuth($cc_auth, 405, 'admin group category', 'churchcal', null, t('administer.group.calendar'), 1);
  $cc_auth = addAuth($cc_auth, 402, 'admin church category', 'churchcal', null, t('administer.church.calendar'), 1);
  return $cc_auth;
}

function churchcal_getMyServices() {
  global $user;
  include_once (CHURCHSERVICE . '/churchservice_db.php');

  $res = churchservice_getUserCurrentServices($user->id);

  return $res;
}

/**
 * if group_id is given then get only this group, else all groups user is member of
 *
 * @param $params
 * @return array
 */
function churchcal_getAbsents($params) {
  global $user;

  include_once (CHURCHDB . '/churchdb_db.php');
  $persons = array ();

  // Get absents when cal_ids is given
  if($cal_ids = getVar("cal_ids", false, $params)) {
    // who has rights for this calendar?
    $res = db_query("SELECT *
                     FROM  {cc_domain_auth} d
                     WHERE d.auth_id=403 AND d.daten_id IN (" . db_implode($cal_ids) . ")");

    if ($res) foreach ($res as $auth) {
      if ($auth->domain_type == "person") $persons[$auth->domain_id] = $auth->domain_id;
      else if ($auth->domain_type == "gruppe") {
        $allPersonIds = churchdb_getAllPeopleIdsFromGroups(array ($auth->domain_id));
        if ($allPersonIds) foreach ($allPersonIds as $id) {
          $persons[$id] = $id;
        }
      }
    }
  }

  // Get Absents when person_id is given
  if ($pid = getVar("person_id", false, $params)) {
    if ($pid==$user->id || user_access("manage absent", "churchservice")
          || churchdb_isPersonLeaderOfPerson($user->id, $pid)) {
      $persons[$pid] = $pid;
    }
    else throw new CTNoPermission("manage absent");
  }

  $arrs = array();
  if (count($persons)) {
    // get absences
    $res = db_query("SELECT p.id AS p_id, a.id, a.startdate, a.enddate, p.vorname, p.name, absent_reason_id AS reason_id
                     FROM {cs_absent} a, {cdb_person} p
                     WHERE p.id IN (" . db_implode($persons) . ") AND a.person_id=p.id");
    foreach ($res as $a) $arrs[] = $a;
  }
  return $arrs;
}

/**
 * get birthdays (all or from own groups)
 * @param array $params
 * @return
 */
function churchcal_getBirthdays($params) {
  global $user;

  $all = (isset($params["all"])) && ($params["all"] == true);
//  $all = (bool) getVar("all", false, $params); //TODO: use this, not tested

  include_once (CHURCHDB . "/churchdb_db.php");

  if (!$all) {
    $gpids = churchdb_getMyGroups($user->id, true, false);
    if (!$gpids) return null;

    $res = db_query("SELECT p.id, gp.geburtsdatum AS birthday, CONCAT(p.vorname, ' ', p.name) AS name
                     FROM {cdb_person} p, {cdb_gemeindeperson_gruppe} gpg, {cdb_gemeindeperson} gp
                     WHERE gpg.gruppe_id IN (" . db_implode($gpids) . ") AND gpg.gemeindeperson_id=gp.id AND
                       gp.person_id=p.id AND p.archiv_yn=0 AND gp.geburtsdatum IS NOT NULL");
    $arrs = array ();
    foreach ($res as $a) $arrs[$a->id] = $a;

    return $arrs;
  }
  else {                                                                    //why 2x p.id?
    $persons = churchdb_getAllowedPersonData(
        "archiv_yn=0 and geburtsdatum IS NOT NULL", "p.id AS p_id, p.id, gp.id gp_id, CONCAT(p.vorname, ' ',p.name) AS name, geburtsdatum birthday"
    );
    return $persons;
  }
}

/**
 * get some sort of auth
 * TODO: explain name or rename
 *
 * @param array $params;
 * @return array auth; [gruppe|person][403|404][[auth,ids]]
 */
function churchcal_getShares($params) {
  // 403=read, 404=edit
  $cat = churchcal_getAllowedCategories(true, true);
  if (!in_array($params["cat_id"], $cat)) throw new CTNoPermission("Not allowed Category", "churchcal");

  $res = db_query("SELECT *
                  FROM {cc_domain_auth}
                  WHERE auth_id IN (403,404) AND domain_type IN ('gruppe','person') AND daten_id=:cat_id",
                  array (":cat_id" => $params["cat_id"]));
  $ret = array ();
  if ($res) foreach ($res as $auth) { // TODO: simplify this, f.e. fewer help vars, use ?:
    $domainType = array ();
    if (isset($ret[$auth->domain_type])) $domainType = $ret[$auth->domain_type];
    $authId = array ();
    if (isset($domainType[$auth->auth_id])) $authId = $domainType[$auth->auth_id];
    $authId[] = $auth->domain_id;
    $domainType[$auth->auth_id] = $authId;
    $ret[$auth->domain_type] = $domainType;
  }
  return $ret;
}

/**
 * [gruppe|person][403|404][[auth,ids]]
 *
 * @param array $params
 * @return
 */
function churchcal_saveShares($params) {
  $log = "";
  $orig = churchcal_getShares($params);
  // look at the original
  foreach (array ("person", "gruppe") as $domainType) {
    if (isset($orig[$domainType])) foreach ($orig[$domainType] as $authKey => $authId) {
      // look what's removed and delete it
      foreach ($authId as $domainId) {
        if (!isset($params[$domainType]) || !isset($params[$domainType][$authKey]) || !in_array($domainId, $params[$domainType][$authKey])) {
          $log .= "<p>Delete $domainType, $authKey, $domainId";

          db_query("DELETE FROM {cc_domain_auth}
                    WHERE domain_type=:domaintype AND domain_id=:domain_id AND auth_id=:auth_id AND daten_id=:daten_id",
                    array (':domain_id' => $domainId,
                           ":domaintype" => $domainType,
                           ":auth_id" => $authKey,
                           ":daten_id" => $params["cat_id"],
          ));
        }
      }
    }
    // look what's added and save it
    if (isset($params[$domainType])) foreach ($params[$domainType] as $authKey => $authId) {
      foreach ($authId as $domainId) {
        $log .= "<p>Search $domainType, $authKey, $domainId";
        if (!isset($orig[$domainType]) || !isset($orig[$domainType][$authKey]) || !in_array($domainId, $orig[$domainType][$authKey])) {
          $log .= "<p>Add $domainType, $authKey, $domainId";

          db_query("INSERT INTO {cc_domain_auth} (domain_type, domain_id, auth_id, daten_id)
                    VALUES( :domaintype, :domain_id, :auth_id, :daten_id)",
                    array (':domain_id' => $domainId,
                           ":domaintype" => $domainType,
                           ":auth_id" => $authKey,
                           ":daten_id" => $params["cat_id"]
                    ));
        }
      }
    }
  }

  return $log;
}

/**
 * add cal event exception
 * @param array $params
 */
function churchcal_addException($params) {
  $i = new CTInterface();
  $i->setParam("cal_id");
  $i->setParam("except_date_start");
  $i->setParam("except_date_end");
  $i->addModifiedParams();

  db_insert("cc_cal_except")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->execute(false);
}

/**
 * delete cal event exception
 * @param array $params
 */
function churchcal_delException($params) {
  $i = new CTInterface();
  $i->setParam("id");

  db_delete("cc_cal_except")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 * add cal event addition
 * @param array $params
 */
function churchcal_addAddition($params) {
  $i = new CTInterface();
  $i->setParam("cal_id");
  $i->setParam("add_date");
  $i->setParam("with_repeat_yn");
  $i->addModifiedParams();

  db_insert("cc_cal_add")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->execute(false);
}

/**
 * delete cal event addition
 * @param array $params
 */
function churchcal_delAddition($params) {
  $db = db_query("SELECT cal_id FROM {cc_cal_add}
                  WHERE id=:id",
                  array (":id" => $params["id"]))
                  ->fetch();

  if ($db == false) throw new CTException(t('manual.event.not.found', $params["id"]));
  if (!churchcal_isAllowedToEditEvent($db->cal_id)) throw new CTNoPermission("AllowToEditEvent", "churchcal");

  $i = new CTInterface();
  $i->setParam("id");

  db_delete("cc_cal_add")
    ->fields($i->getDBInsertArrayFromParams($params))
    ->condition("id", $params["id"], "=")
    ->execute(false);
}

/**
 * delete cal event
 * @param array $params
 * @param string $source; default: null
 * @throws CTNoPermission
 */
function churchcal_deleteEvent($params, $source = null) {
  global $user;
  $id = $params["id"];
  $logger = db_query("SELECT * FROM {cc_cal} WHERE id=:id", array(":id" => $params["id"])) -> fetch();
  if (!$logger) return;

  if (!churchcal_isAllowedToEditEvent($id)) throw new CTNoPermission("AllowToEditEvent", "churchcal");

  // inform other modules
  if (!$source || $source != "churchresource") {
    include_once (CHURCHRESOURCE . '/churchresource_db.php');
    if (!$source) $source = "churchcal";
    $params["cal_id"] = $params["id"];
    churchresource_deleteResourcesFromChurchCal($params, $source);
  }
  if (!$source || $source != "churchservice") {
    include_once (CHURCHSERVICE . '/churchservice_db.php');
    $cs_params = array_merge(array(), $params);
    $cs_params["cal_id"] = $params["id"];
    $cs_params["informDeleteEvent"] = 1;
    $cs_params["deleteCalEntry"] = 1;
    if (!$source) $source = "churchcal";

    $db = db_query("SELECT * FROM {cs_event}
                    WHERE cc_cal_id=:cal_id",
                    array (":cal_id" => $cs_params["cal_id"]));

    foreach ($db as $cs) {
      $cs_params["id"] = $cs->id;
      churchservice_deleteEvent($cs_params, $source);
    }
  }

  db_query("DELETE FROM {cc_meetingrequest} WHERE cal_id=:id", array (":id" => $id));

  db_query("DELETE FROM {cc_cal_except} WHERE cal_id=:id", array (":id" => $id));
  db_query("DELETE FROM {cc_cal_add}    WHERE cal_id=:id", array (":id" => $id));
  db_query("DELETE FROM {cc_cal}        WHERE id=:id", array (":id" => $id));

  $data = db_query("select * from {cc_calcategory} where id=:id", array(":id"=>$logger->category_id))->fetch();
  $txt = $user->vorname . " " . $user->name . " hat in Kalender ";
  if ($data!=false)
    $txt .= $data->bezeichnung;
  else
    $txt .= $logger->category_id;
  $txt .= " einen Termin gel&ouml;scht: <br>";
  $txt .= churchcore_CCEventData2String($logger);
  ct_notify("category", $logger->category_id, $txt);
}

/**
 * get resources with booking dates
 * @param array $params
 * @return array resources
 */
function churchcal_getResource($params) {
  $resource_ids = $params["resource_id"];
  $res = db_query("
    SELECT r.id resource_id, r.bezeichnung ort, s.bezeichnung status, b.status_id, b.id, b.startdate, b.enddate,
       b.repeat_id, b.repeat_frequence, b.repeat_until, b.repeat_option_id, b.text bezeichnung
    FROM {cr_resource} r, {cr_booking} b, {cr_status} s
    WHERE b.status_id!=99 AND s.id=b.status_id AND b.resource_id=r.id AND r.id IN (" . db_implode($resource_ids) . ")");

  $excs = churchcore_getTableData("cr_exception", "except_date_start");
  $adds = churchcore_getTableData("cr_addition", "add_date");
  $arrs = array ();
  foreach ($res as $a) {
    if ($excs) foreach ($excs as $exc) {
      if ($a->id == $exc->booking_id) $a->exceptions[$exc->id] = $exc;
    }
    if ($adds) foreach ($adds as $add) {
      if ($a->id == $add->booking_id) $a->additions[$add->id] = $add;
    }
    $arrs[] = $a;
  }

  $ret = array ();
  foreach ($resource_ids as $id) {
    $ret[$id] = array ();
    foreach ($arrs as $d) {
      if ($d->resource_id == $id) $ret[$id][$d->id] = $d;
    }
  }

  return $ret;
}

/**
 * get bookings from resources
 * @return array
 */
function churchcal_getEventsFromOtherModules() {
  $res = db_query("SELECT e.id, e.datum AS startdate, e.bezeichnung, category_id
                   FROM {cs_event} e, {cs_category} c
                   WHERE e.category_id=c.id AND c.show_in_churchcal_yn=1");
  $arrs = null;
  foreach ($res as $arr) {
    $arrs[$arr->id] = $arr;
  }
  //get resource bookings which should be shown in calendar
  $res = db_query("SELECT r.id resource_id, r.bezeichnung ort, b.id, b.startdate, b.enddate,
                     b.repeat_id, b.repeat_frequence, b.repeat_until, b.text AS bezeichnung
                   FROM {cr_resource} r, {cr_booking} b
                   WHERE b.status_id!=99 and b.resource_id=r.id and b.show_in_churchcal_yn=1");
  $excs = churchcore_getTableData("cr_exception", "except_date_start");
  $adds = churchcore_getTableData("cr_addition", "add_date");
  // $arrs=array();
  foreach ($res as $r) {
    if ($excs) foreach ($excs as $exc) {
      if ($r->id == $exc->booking_id)  $r->exceptions[$exc->id] = $exc;
    }
    if ($adds) foreach ($adds as $add) {
      if ($r->id == $add->booking_id) $r->additions[$add->id] = $add;
    }
    $arrs[] = $r;
  }
  return $arrs;
}

/**
 *
 * @param string $cond; sql where condition
 * @return array cal events
 */
function churchcal_getAllEvents($cond = "") {
  $ret = array ();

  $ret = churchcore_getTableData("cc_cal", "", $cond);

  $exceptions = churchcore_getTableData("cc_cal_except");
  if ($exceptions) foreach ($exceptions as $val) {
    // there could be exceptions having no date.
    if (isset($ret[$val->cal_id])) {
      if (!isset($ret[$val->cal_id]->exceptions)) $a = array ();
      else $a = $ret[$val->cal_id]->exceptions;
      $a[$val->id] = new stdClass();
      $a[$val->id]->id = $val->id;
      $a[$val->id]->except_date_start = $val->except_date_start;
      $a[$val->id]->except_date_end = $val->except_date_end;
      $ret[$val->cal_id]->exceptions = $a;
    }
  }
  $additions = churchcore_getTableData("cc_cal_add");
  if ($additions) foreach ($additions as $val) {
    // there could be additions having no date.
    if (isset($ret[$val->cal_id])) {
      if (!isset($ret[$val->cal_id]->additions)) $a = array ();
      else $a = $ret[$val->cal_id]->additions;
      $a[$val->id] = new stdClass();
      $a[$val->id]->id = $val->id;
      $a[$val->id]->add_date = $val->add_date;
      $a[$val->id]->with_repeat_yn = $val->with_repeat_yn;
      $ret[$val->cal_id]->additions = $a;
    }
  }

  return $ret;
}

/**
 * is current user owner of cal category?
 * @param int $category_id
 * @return boolean
 */
function churchcal_isUserOwnerOf($category_id) {
  global $user;
  if (!$category_id) return false;
  $res = db_query('SELECT modified_pid
                   FROM {cc_calcategory}
                   WHERE id=:id',
                   array (":id" => $category_id))
                   ->fetch();
  return ($res) ? $res->modified_pid == $user->id : false;
}

/**
 * save cal category
 * @param array $params
 * @throws CTNoPermission
 * @return Ambigous <mixed, string>
 */
function churchcal_saveCategory($params) {
  global $user;

  $id = getVar('id', false, $params);
  $auth = false;
  if ($params["privat_yn"] == 1 && $params["oeffentlich_yn"] == 0) {
    if ($id) $auth = user_access("admin personal category", "churchcal") || churchcal_isUserOwnerOf($id);
    else $auth = user_access("admin personal category", "churchcal") || user_access("create personal category", "churchcal");
  }
  else if ($params["privat_yn"] == 0 && $params["oeffentlich_yn"] == 0) {
    if ($id) $auth = user_access("admin group category", "churchcal") || churchcal_isUserOwnerOf($id);
    else $auth = user_access("admin group category", "churchcal") || user_access("create group category", "churchcal");
  }
  else if ($params["privat_yn"] == 0 && $params["oeffentlich_yn"] == 1) {
    $auth = user_access("admin church category", "churchcal") || churchcal_isUserOwnerOf($id);
  }
  if (!$auth) throw new CTNoPermission("Admin edit category", "churchcal");

  $i = new CTInterface();
  $i->setParam("bezeichnung");
  $i->setParam("sortkey");
  $i->setParam("color");
  $i->setParam("privat_yn");
  $i->setParam("ical_source_url", false);

  if (!$id) {
    // oeffentlich will be set on insert only
    $i->addModifiedParams();
    $i->setParam("oeffentlich_yn");
    $i->setParam("randomurl");
    $params["randomurl"] = random_string(32);
    $id = db_insert("cc_calcategory")
      ->fields($i->getDBInsertArrayFromParams($params))
      ->execute(false);

    // add rights for author
    db_query("INSERT INTO {cc_domain_auth} (domain_type, domain_id, auth_id, daten_id)
              VALUES ('person', :userId, 404, :id)",
              array(':userId' => $user->id, ':id' => $id)
    );
    $_SESSION["user"]->auth = getUserAuthorization($_SESSION["user"]->id);

    if (getVar('accessgroup', false, $params)) {
      db_query("INSERT INTO {cc_domain_auth} (domain_type, domain_id, auth_id, daten_id)
                VALUES ('gruppe', :accessgroup, :auth, :id)",
                array(':accessgroup' =>  $params["accessgroup"],
                      ':auth' => (getVar('writeaccess', false, $params) == true) ? 404 : 403,
                      ':id' => $id,
                ));
    }
  }
  else {
    $c = db_query("SELECT * FROM {cc_calcategory}
                   WHERE id=:id",
                   array (":id" => $id))
                   ->fetch();
    db_update("cc_calcategory")
      ->fields($i->getDBInsertArrayFromParams($params))
      ->condition("id", $id, "=")
      ->execute(false);
  }
  if (!empty($params["ical_source_url"])) churchcal_updateICalSource($id);
  return $id;
}

function churchcal_deleteCategory($params) {
  global $user;
   $id = getVar('id', false, $params);

  $data = db_query("SELECT * FROM {cc_calcategory}
                    WHERE id=:id",
                    array (":id" => $id))
                    ->fetch();

  if (!$data) return CTException(t('category.does.not.exist'));

  $auth = user_access("edit category", "churchcal");
  if ($data->modified_pid != $user->id && (!$auth || !isset($auth[$id]))) throw new CTNoPermission("Edit Category", "churchcal");

  $c = db_query("SELECT COUNT(*) c
                 FROM {cs_event} e, {cc_cal} cal, {cs_eventservice} es
                 WHERE cal.id=e.cc_cal_id AND cal.category_id=:id
                 AND es.event_id=e.id AND zugesagt_yn=1 AND es.valid_yn=1
                 AND DATEDIFF(cal.startdate, now())>=0",
                 array (":id" => $id))
                 ->fetch();
  if ($c->c > 0) throw new CTFail(t('deleting.failed.because.of.remaining.services'));

  $db = db_query("SELECT c.id FROM {cs_event} e, {cc_cal} c WHERE c.category_id=:category_id AND e.cc_cal_id = c.id",
            array(":category_id" => $id));
  foreach ($db as $cal) {
    churchcal_deleteEvent(array("id"=>$cal->id));
  }

  db_query("DELETE FROM {cc_cal}         WHERE category_id=:id", array (":id" => $id));
  db_query("DELETE FROM {cc_calcategory} WHERE id=:id", array (":id" => $id));
  db_query("DELETE FROM {cc_domain_auth} WHERE auth_id in (403, 404) and daten_id=:id", array (":id" => $id));
}

/**
 * edit allowed if user authored the event or has rights for category
 * @param $id von cc_cal.
 * @return bool
 */
function churchcal_isAllowedToEditEvent($id) {
  global $user;

  $data = db_query("SELECT * FROM {cc_cal}
                    WHERE id=:id",
                    array (":id" => $id))
                    ->fetch();
  if (!$data) throw new CTException(t('event.not.found', $id));
  $auth = user_access("edit category", "churchcal");

  // author of event can edit it
  if ($data && $data->modified_pid == $user->id) return true;
  if ($auth && isset($auth[$data->category_id])) return true;

  return false;
}

/**
 *
 * @return array cal events
 */
function churchcal_getCalEvents() {
  $ret = array ();
  $ret["csevents"] = churchcal_getEventsFromOtherModules();
  if ($user != null) $ret["calevents"] = churchcal_getAllEvents(); // TODO: which user?
  else $ret["calevents"] = churchcal_getAllEvents("intern_yn=0");

  return $ret;
}

function churchcal_getAllowedGroups() {
  include_once (CHURCHDB . '/churchdb_db.php');
  return churchdb_getAllowedGroups();
}

function churchcal_getAllowedPersons() {
  include_once (CHURCHDB . '/churchdb_ajax.php');
  return churchdb_getAllowedPersonData('archiv_yn=0');
}

function churchcal_moveCSEvent() {
  throw new CTException("Noch nicht fertig!");
  db_query("UPDATE {cs_event} SET startdate=startdate+ TODO  "); // TODO: is TODO right or a mistake?
}

function churchcal__ajax() {
  include_once (CHURCHCAL . "/churchcal_db.php");

  $module = new CTChurchCalModule("churchcal");

  $ajax = new CTAjaxHandler($module);
  $ajax->addFunction("getCalEvents", "view");
  $ajax->addFunction("getCalPerCategory", "view");
  $ajax->addFunction("getAbsents", "view");
  $ajax->addFunction("getMyServices", "view", "churchservice");
  $ajax->addFunction("getBirthdays", "view");
  $ajax->addFunction("deleteCategory", "view");
  $ajax->addFunction("getShares", "view");
  $ajax->addFunction("saveShares", "view");
  $ajax->addFunction("getResource", "view", "churchresource");
  $ajax->addFunction("getAllowedGroups", "view", "churchdb");
  $ajax->addFunction("getAllowedPersons", "view", "churchdb");
  $ajax->addFunction("saveCategory", "view");
  $ajax->addFunction("delAddition", "view");

  // not ready
  $ajax->addFunction("moveCSEvent");

  drupal_json_output($ajax->call());
}

function churchcal_cron() {
  // Refresh external ical
  $cats = db_query("SELECT * FROM {cc_calcategory}
                    WHERE ical_source_url IS NOT NULL AND ical_source_url!=''
                      AND (DATEDIFF(NOW(), modified_date)>0 OR modified_date IS NULL)");
  foreach ($cats as $cat) {
    churchcal_updateICalSource($cat->id);
  }
  db_query("UPDATE {cc_calcategory} SET modified_date=NOW()
                    WHERE ical_source_url IS NOT NULL AND ical_source_url!=''
                      AND (DATEDIFF(NOW(), modified_date)>0 OR modified_date IS NULL)");
}

function churchcal_updateICalSource($id) {
  // Set modified date to not load this calendar each cron job
  //db_query("UPDATE {cc_calcategory} SET modified_date = now() WHERE id = :id", (array(":id"=>$id)));

  $cat = db_query("SELECT * FROM {cc_calcategory}
                    WHERE id = :id", array(":id"=>$id))->fetch();
  if (!$cat) throw new CTException("No calcategory found");
  include_once (ASSETS . '/ics-parser/class.iCalReader.php');

  $ical   = new ICal($cat->ical_source_url);
  $events = $ical->events();
  if (!$events) {
    ct_log("iCal Source from $cat->bezeichnung could not readed and processed!", 2, "calcategory", $id);
  }
  else {
    db_query("DELETE FROM {cc_cal} WHERE category_id = :id", array(":id" => $id));
    include_once (CHURCHCAL. '/churchcal_db.php');
    foreach ($events as $event) {
      $data = array();
      $data["startdate"] = churchcore_icalToDate($event["DTSTART"]);
      if (isset($event["DTEND"]))
        $data["enddate"] = churchcore_icalToDate($event["DTEND"]);
      // TODO(CHECK_ICAL_IMPORT): utf8_encode(getVar("SUMMARY", "", $event)) ? (commit 5b1c76a468dd4dee36e39c2d51191b286df5bc0f)
      $data["bezeichnung"] = getVar("SUMMARY", "", $event);
      $data["category_id"] = $id;
      $data["repeat_id"] = 0;
      $data["intern_yn"] = 0;
      $data["modified_pid"] = -1;
      $data["notizen"] = getVar("SUMMARY", "", $event) . " " . getVar("DESCRIPTION", "", $event);
      $data["link"] = getVar("URL", "", $event);
      $data["ort"] = "";
      if ($data["startdate"]!="") {
        // Substract one day if it is a whole day date
        $sd = new Datetime($data["startdate"]);
        if (!isset($data["enddate"])) $ed = new Datetime($data["startdate"]);
        else {
          $ed = new Datetime($data["enddate"]);
          if (isFullDay($sd, $ed)) $ed->modify("-1 DAY");
        }
        $data["startdate"] = $sd->format("Y-m-d H:i");
        $data["enddate"] = $ed->format("Y-m-d H:i");
        churchcal_createEvent($data, null, true);
      }
    }
    ct_log("iCal Source from $cat->bezeichnung readed and processed!", 2, $id, "category");
  }
}

/**
 * use template for calendar entries, include text added by surroundWithVCALENDER
 */
function churchcal__ical() {
  global $base_url, $config;
  include_once (CHURCHCAL . "/churchcal_db.php");

  drupal_add_http_header('Content-Type', 'text/calendar;charset=utf-8', false);
  drupal_add_http_header('Content-Disposition', 'inline;filename="ChurchTools.ics"', false);
  drupal_add_http_header('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', false);
  drupal_add_http_header('Cache-Control', 'private', true);
  $content = drupal_get_header();

  $catNames = null;

  if (($security = getVar("security")) && ($id = getVar("id"))) {
    $db = db_query("SELECT * FROM {cc_calcategory}
                    WHERE id=:id AND randomurl=:randomurl",
                    array (":id" => $id, ":randomurl" => $security))
                    ->fetch();
    if ($db) {
      $catNames[$id] = new stdClass();
      $catNames[$id]->bezeichnung = $db->bezeichnung;
      $cats = array (0 => $id);
    }
  }

  if (!$catNames) {
    $cats = churchcal_getAllowedCategories(false, true);
    $catNames = churchcal_getAllowedCategories(false, false);
  }
  $arr = churchcal_getCalPerCategory(array ("category_ids" => $cats), false);

  $txt = "";
  foreach ($arr as $cats) foreach ($cats as $res) {

    $res->startdate = new DateTime($res->startdate);
    $res->enddate = new DateTime($res->enddate);
    $diff = $res->enddate->format("U") - $res->startdate->format("U"); // TODO: use DateTime function like next line?
//     $diff = $res->enddate->diff($res->startdate);
    $subid = 0;
    foreach (getAllDatesWithRepeats($res, -90, 730) as $d) {
      $txt.="BEGIN:VEVENT" . NL;
      $txt.="ORGANIZER:MAILTO:".getConf('site_mail', '') . NL;
      $txt.="SUMMARY:".$res->bezeichnung . NL;
      //$txt.="X-MICROSOFT-CDO-BUSYSTATUS:BUSY" . NL;
      if ($res->link!="")
        $txt.="URL:".$res->link . NL;
      else
        $txt.="URL:".$base_url."?q=churchcal" . NL;
      if ($res->ort!="")
        $txt.="LOCATION:".$res->ort . NL;

      $subid++;
      $txt .= "UID:{$res->id}_$subid" . NL;
      $txt .= "DTSTAMP:" . churchcore_stringToDateICal($res->modified_date) . NL;
      $enddate = clone $d;
      $enddate->modify("+$diff seconds");

      // all day event
      if (($res->startdate->format('His') == "000000") && ($res->enddate->format('His') == "000000")) {
        $txt .= "DTSTART;VALUE=DATE:" . $d->format('Ymd') . NL;
        $txt .= "DTEND;VALUE=DATE:" . date('Ymd', strtotime('+1 day', $enddate->format("U"))) . NL;
      }
      else {
        $txt .= "DTSTART:" . $d->format('Ymd\THis') . NL;
        $txt .= "DTEND:" . $enddate->format('Ymd\THis') . NL;
      }

      $txt .= 'DESCRIPTION:Kalender:' . $catNames[$res->category_id]->bezeichnung;
      if (!empty($res->notizen)) $txt .= ' - ' . cleanICal($res->notizen);
      $txt .= NL;
      $txt .= "END:VEVENT" . NL;
    }
  }

  echo surroundWithVCALENDER($txt);
}
