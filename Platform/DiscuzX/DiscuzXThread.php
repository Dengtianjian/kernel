<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\BaseObject;
use kernel\Foundation\ReturnResult\ReturnResult;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

function viewthread_updateviews($tableid)
{
  global $_G;

  if (!$_G['setting']['preventrefresh'] || getcookie('viewid') != 'tid_' . $_G['tid']) {
    if (!$tableid && getglobal('setting/optimizeviews')) {
      if (isset($_G['forum_thread']['addviews'])) {
        if ($_G['forum_thread']['addviews'] < 100) {
          \C::t('forum_threadaddviews')->update_by_tid($_G['tid']);
        } else {
          if (!\discuz_process::islocked('update_thread_view')) {
            $row = \C::t('forum_threadaddviews')->fetch($_G['tid']);
            \C::t('forum_threadaddviews')->update($_G['tid'], array('addviews' => 0));
            \C::t('forum_thread')->increase($_G['tid'], array('views' => $row['addviews'] + 1), true);
            \discuz_process::unlock('update_thread_view');
          }
        }
      } else {
        \C::t('forum_threadaddviews')->insert(array('tid' => $_G['tid'], 'addviews' => 1), false, true);
      }
    } else {
      \C::t('forum_thread')->increase($_G['tid'], array('views' => 1), true, $tableid);
    }
  }
  dsetcookie('viewid', 'tid_' . $_G['tid']);
}

function viewthread_loadcache()
{
  global $_G;
  $_G['thread']['livedays'] = ceil((TIMESTAMP - $_G['thread']['dateline']) / 86400);
  $_G['thread']['lastpostdays'] = ceil((TIMESTAMP - $_G['thread']['lastpost']) / 86400);

  $threadcachemark = 100 - ($_G['thread']['digest'] * 20 +
    min($_G['thread']['views'] / max($_G['thread']['livedays'], 10) * 2, 50) +
    max(-10, (15 - $_G['thread']['lastpostdays'])) +
    min($_G['thread']['replies'] / $_G['setting']['postperpage'] * 1.5, 15));
  if ($threadcachemark < $_G['forum']['threadcaches']) {

    $threadcache = getcacheinfo($_G['tid']);

    if (TIMESTAMP - $threadcache['filemtime'] > $_G['setting']['cachethreadlife']) {
      @unlink($threadcache['filename']);
      define('CACHE_FILE', $threadcache['filename']);
    } else {
      $start_time = microtime(TRUE);
      $filemtime = $threadcache['filemtime'];
      ob_start(function ($input) use (&$filemtime) {
        return replace_formhash($filemtime, $input);
      });
      readfile($threadcache['filename']);
      viewthread_updateviews($_G['forum_thread']['threadtableid']);
      $updatetime = dgmdate($filemtime, 'Y-m-d H:i:s');
      $debuginfo = ", Updated at $updatetime";
      if (getglobal('setting/debug')) {
        $gzip = $_G['gzipcompress'] ? ', Gzip On' : '';
        $debuginfo .= ', Processed in ' . sprintf("%0.6f", microtime(TRUE) - $start_time) . ' second(s)' . $gzip;
      }
      echo '<script type="text/javascript">$("debuginfo") ? $("debuginfo").innerHTML = "' . $debuginfo . '." : "";</script></body></html>';
      ob_end_flush();
      exit();
    }
  }
}

function viewthread_lastmod(&$thread)
{
  global $_G;
  if (!$thread['moderated']) {
    return array();
  }
  $lastmod = array();
  $lastlog = \C::t('forum_threadmod')->fetch_by_tid($thread['tid']);
  if ($lastlog) {
    $lastmod = array(
      'moduid' => $lastlog['uid'],
      'modusername' => $lastlog['username'],
      'moddateline' => $lastlog['dateline'],
      'modaction' => $lastlog['action'],
      'magicid' => $lastlog['magicid'],
      'stamp' => $lastlog['stamp'],
      'reason' => $lastlog['reason']
    );
  }

  if ($lastmod) {
    $modactioncode = lang('forum/modaction');
    $lastmod['moduid'] = $_G['setting']['moduser_public'] ? $lastmod['moduid'] : 0;
    $lastmod['modusername'] = $lastmod['modusername'] ? ($_G['setting']['moduser_public'] ? $lastmod['modusername'] : lang('forum/template', 'thread_moderations_team')) : lang('forum/template', 'thread_moderations_cron');
    $lastmod['moddateline'] = dgmdate($lastmod['moddateline'], 'u');
    $lastmod['modactiontype'] = $lastmod['modaction'];
    if ($modactioncode[$lastmod['modaction']]) {
      $lastmod['modaction'] = $modactioncode[$lastmod['modaction']] . ($lastmod['modaction'] != 'SPA' ? '' : ' ' . $_G['cache']['stamps'][$lastmod['stamp']]['text']);
    } elseif (substr($lastmod['modaction'], 0, 1) == 'L' && preg_match('/L(\d\d)/', $lastmod['modaction'], $a)) {
      $lastmod['modaction'] = $modactioncode['SLA'] . ' ' . $_G['cache']['stamps'][intval($a[1])]['text'];
    } else {
      $lastmod['modaction'] = '';
    }
    if ($lastmod['magicid']) {
      loadcache('magics');
      $lastmod['magicname'] = $_G['cache']['magics'][$lastmod['magicid']]['name'];
    }
  } else {
    \C::t('forum_thread')->update($thread['tid'], array('moderated' => 0), false, false, $thread['threadtableid']);
    $thread['moderated'] = 0;
  }
  return $lastmod;
}

function viewthread_baseinfo($post, $extra)
{
  global $_G;
  list($key, $type) = $extra;
  $v = '';
  if (substr($key, 0, 10) == 'extcredits') {
    $i = substr($key, 10);
    $extcredit = $_G['setting']['extcredits'][$i];
    if ($extcredit) {
      $v = $type ? ($extcredit['img'] ? $extcredit['img'] . ' ' : '') . $extcredit['title'] : $post['extcredits' . $i] . ' ' . $extcredit['unit'];
    }
  } elseif (substr($key, 0, 6) == 'field_') {
    $field = substr($key, 6);
    if (!empty($post['privacy']['profile'][$field])) {
      return '';
    }
    require_once libfile('function/profile');
    if ($field != 'qq') {
      $v = profile_show($field, $post);
    } elseif (!empty($post['qq'])) {
      $v = '<a href="//wpa.qq.com/msgrd?v=3&uin=' . $post['qq'] . '&site=' . $_G['setting']['bbname'] . '&menu=yes&from=discuz" target="_blank" title="' . lang('spacecp', 'qq_dialog') . '"><img src="' . STATICURL . '/image/common/qq_big.gif" alt="QQ" style="margin:0px;"/></a>';
    }
    if ($v) {
      if (!isset($_G['cache']['profilesetting'])) {
        loadcache('profilesetting');
      }
      $v = $type ? $_G['cache']['profilesetting'][$field]['title'] : $v;
    }
  } elseif ($key == 'eccredit_seller') {
    $v = $type ? lang('space', 'viewthread_userinfo_sellercredit') : '<a href="home.php?mod=space&uid=' . $post['uid'] . '&do=trade&view=eccredit#buyercredit" target="_blank" class="vm"><img src="' . STATICURL . 'image/traderank/seller/' . countlevel($post['buyercredit']) . '.gif" /></a>';
  } elseif ($key == 'eccredit_buyer') {
    $v = $type ? lang('space', 'viewthread_userinfo_buyercredit') : '<a href="home.php?mod=space&uid=' . $post['uid'] . '&do=trade&view=eccredit#sellercredit" target="_blank" class="vm"><img src="' . STATICURL . 'image/traderank/seller/' . countlevel($post['sellercredit']) . '.gif" /></a>';
  } else {
    $v = getLinkByKey($key, $post);
    if ($v !== '') {
      $v = $type ? lang('space', 'viewthread_userinfo_' . $key) : $v;
    }
  }
  return $v;
}

function viewthread_profile_nodeparse($param)
{
  list($name, $s, $e, $extra, $post) = $param;
  if (strpos($name, ':') === false) {
    if (function_exists('profile_node_' . $name)) {
      return call_user_func('profile_node_' . $name, $post, $s, $e, explode(',', $extra));
    } else {
      return '';
    }
  } else {
    list($plugin, $pluginid) = explode(':', $name);
    if ($plugin == 'plugin') {
      global $_G;
      static $pluginclasses = array();
      if (isset($_G['setting']['plugins']['profile_node'][$pluginid])) {
        @include_once DISCUZ_ROOT . './source/plugin/' . $_G['setting']['plugins']['profile_node'][$pluginid] . '.class.php';
        $classkey = 'plugin_' . $pluginid;
        if (!class_exists($classkey, false)) {
          return '';
        }
        if (!isset($pluginclasses[$classkey])) {
          $pluginclasses[$classkey] = new $classkey;
        }
        return call_user_func(array($pluginclasses[$classkey], 'profile_node'), $post, $s, $e, explode(',', $extra));
      }
    }
  }
}

function viewthread_profile_node($type, $post)
{
  global $_G;
  $tpid = false;
  if (!empty($post['verifyicon'])) {
    $tpid = isset($_G['setting']['profilenode']['groupid'][-$post['verifyicon'][0]]) ? $_G['setting']['profilenode']['groupid'][-$post['verifyicon'][0]] : false;
  }
  if ($tpid === false) {
    $tpid = isset($_G['setting']['profilenode']['groupid'][$post['groupid']]) ? $_G['setting']['profilenode']['groupid'][$post['groupid']] : 0;
  }
  $template = $_G['setting']['profilenode']['template'][$tpid][$type];
  $code = $_G['setting']['profilenode']['code'][$tpid][$type];
  include_once template('forum/viewthread_profile_node');
  foreach ($code as $k => $p) {
    $p[] = $post;
    $template = str_replace($k, call_user_func('viewthread_profile_nodeparse', $p), $template);
  }
  echo $template;
}

function viewthread_numbercard($post)
{
  global $_G;
  if (!is_array($_G['setting']['numbercard'])) {
    $_G['setting']['numbercard'] = dunserialize($_G['setting']['numbercard']);
  }

  $numbercard = array();
  foreach ($_G['setting']['numbercard']['row'] as $key) {
    if (substr($key, 0, 10) == 'extcredits') {
      $numbercard[] = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=profile', 'value' => $post[$key], 'lang' => $_G['setting']['extcredits'][substr($key, 10)]['title']);
    } else {
      $getLink = getLinkByKey($key, $post, 1);
      $numbercard[] = array('link' => $getLink['link'], 'value' => $getLink['value'], 'lang' => lang('space', 'viewthread_userinfo_' . $key));
    }
  }
  return $numbercard;
}
function getLinkByKey($key, $post, $returnarray = 0)
{
  switch ($key) {
    case 'uid':
      $v = array('link' => '?' . $post['uid'], 'value' => $post['uid']);
      break;
    case 'posts':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=thread&type=reply&view=me&from=space', 'value' => $post['posts'] - $post['threads']);
      break;
    case 'threads':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=thread&type=thread&view=me&from=space', 'value' => $post['threads']);
      break;
    case 'digestposts':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=thread&type=thread&view=me&from=space', 'value' => $post['digestposts']);
      break;
    case 'feeds':
      $v = array('link' => 'home.php?mod=follow&uid=' . $post['uid'] . '&do=view', 'value' => $post['feeds']);
      break;
    case 'doings':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=doing&view=me&from=space', 'value' => $post['doings']);
      break;
    case 'blogs':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=blog&view=me&from=space', 'value' => $post['blogs']);
      break;
    case 'albums':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=album&view=me&from=space', 'value' => $post['albums']);
      break;
    case 'sharings':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=share&view=me&from=space', 'value' => $post['sharings']);
      break;
    case 'friends':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=friend&view=me&from=space', 'value' => $post['friends']);
      break;
    case 'follower':
      $v = array('link' => 'home.php?mod=follow&do=follower&uid=' . $post['uid'], 'value' => $post['follower']);
      break;
    case 'following':
      $v = array('link' => 'home.php?mod=follow&do=following&uid=' . $post['uid'], 'value' => $post['following']);
      break;
    case 'credits':
      $v = array('link' => 'home.php?mod=space&uid=' . $post['uid'] . '&do=profile', 'value' => $post['credits']);
      break;
    case 'digest':
      $v = array('value' => $post['digestposts']);
      break;
    case 'readperm':
      $v = array('value' => $post['readaccess']);
      break;
    case 'regtime':
      $v = array('value' => $post['regdate']);
      break;
    case 'lastdate':
      $v = array('value' => $post['lastdate']);
      break;
    case 'oltime':
      $v = array('value' => $post['oltime'] . ' ' . lang('space', 'viewthread_userinfo_hour'));
      break;
  }
  if (!$returnarray) {
    if ($v['link']) {
      $v = '<a href="' . $v['link'] . '" target="_blank" class="xi2">' . $v['value'] . '</a>';
    } else {
      $v = $v['value'];
    }
  }
  return $v;
}
function countlevel($usercredit)
{
  global $_G;

  $rank = 0;
  if ($usercredit) {
    foreach ($_G['setting']['ec_credit']['rank'] as $level => $credit) {
      if ($usercredit <= $credit) {
        $rank = $level;
        break;
      }
    }
  }
  return $rank;
}
function remaintime($time)
{
  $days = intval($time / 86400);
  $time -= $days * 86400;
  $hours = intval($time / 3600);
  $time -= $hours * 3600;
  $minutes = intval($time / 60);
  $time -= $minutes * 60;
  $seconds = $time;
  return array((int)$days, (int)$hours, (int)$minutes, (int)$seconds);
}

function getrelateitem($tagarray, $tid, $relatenum, $relatetime, $relatecache = '', $type = 'tid')
{
  $tagidarray = $relatearray = $relateitem = array();
  $updatecache = 0;
  $limit = $relatenum;
  if (!$limit) {
    return '';
  }
  foreach ($tagarray as $var) {
    $tagidarray[] = $var['0'];
  }
  if (!$tagidarray) {
    return '';
  }
  if (empty($relatecache)) {
    $thread = \C::t('forum_thread')->fetch_thread($tid);
    $relatecache = $thread['relatebytag'];
  }
  if ($relatecache) {
    $relatecache = explode("\t", $relatecache);
    if (TIMESTAMP > $relatecache[0] + $relatetime * 60) {
      $updatecache = 1;
    } else {
      if (!empty($relatecache[1])) {
        $relatearray = explode(',', $relatecache[1]);
      }
    }
  } else {
    $updatecache = 1;
  }
  if ($updatecache) {
    $query = \C::t('common_tagitem')->select($tagidarray, $tid, $type, 'itemid', 'DESC', $limit, 0, '<>');
    foreach ($query as $result) {
      if ($result['itemid']) {
        $relatearray[] = $result['itemid'];
      }
    }
    if ($relatearray) {
      $relatebytag = implode(',', $relatearray);
    }
    \C::t('forum_thread')->update($tid, array('relatebytag' => TIMESTAMP . "\t" . $relatebytag));
  }


  if (!empty($relatearray)) {
    rsort($relatearray);
    foreach (\C::t('forum_thread')->fetch_all_by_tid($relatearray) as $result) {
      if ($result['displayorder'] >= 0) {
        $relateitem[] = $result;
      }
    }
  }
  return $relateitem;
}

function rushreply_rule()
{
  global $rushresult, $preg;
  if (!empty($rushresult['rewardfloor'])) {
    $rushresult['rewardfloor'] = preg_replace('/\*+/', '*', $rushresult['rewardfloor']);
    $rewardfloorarr = explode(',', $rushresult['rewardfloor']);
    if ($rewardfloorarr) {
      foreach ($rewardfloorarr as $var) {
        $var = trim($var);
        if (strlen($var) > 1) {
          $var = str_replace('*', '[^,]?[\d]*', $var);
        } else {
          $var = str_replace('*', '\d+', $var);
        }
        $preg[] = "(,$var,)";
      }
      $preg = is_array($preg) ? $preg : array($preg);
      $preg_str = "/" . implode('|', $preg) . "/";
    }
  }
  return $preg_str;
}

function checkrushreply($post)
{
  global $_G, $rushids;
  if ($_GET['authorid']) {
    return $post;
  }
  if (in_array($post['number'], $rushids)) {
    $post['rewardfloor'] = 1;
  }
  return $post;
}

function parseindex($nodes, $pid)
{
  global $_G;
  $nodes = dhtmlspecialchars($nodes);
  $nodes = preg_replace('/(\**?)\[#(\d+)\](.+?)[\r\n]/', "<a page=\"\\2\" sub=\"\\1\">\\3</a>", $nodes);
  $nodes = preg_replace('/(\**?)\[#(\d+),(\d+)\](.+?)[\r\n]/', "<a tid=\"\\2\" pid=\"\\3\" sub=\"\\1\">\\4</a>", $nodes);
  $_G['forum_posthtml']['header'][$pid] .= '<div id="threadindex">' . $nodes . '</div><script type="text/javascript" reload="1">show_threadindex(' . $pid . ', ' . ($_GET['from'] == 'preview' ? '1' : '0') . ')</script>';
  return '';
}

function parsebegin($linkaddr, $imgflashurl, $w = 0, $h = 0, $type = 0, $s = 0)
{
  static $begincontent;
  if ($begincontent || $_GET['from'] == 'preview') {
    return '';
  }
  preg_match("/((https?){1}:\/\/|www\.)[^\[\"']+/i", $imgflashurl, $matches);
  $imgflashurl = $matches[0];
  $fileext = fileext($imgflashurl);
  preg_match("/((https?){1}:\/\/|www\.)[^\[\"']+/i", $linkaddr, $matches);
  $linkaddr = $matches[0];
  $randomid = 'swf_' . random(3);
  $w = ($w >= 400 && $w <= 1024) ? $w : 900;
  $h = ($h >= 300 && $h <= 640) ? $h : 500;
  $s = $s ? $s * 1000 : 5000;
  switch ($fileext) {
    case 'jpg':
    case 'jpeg':
    case 'gif':
    case 'png':
      $content = '<img style="position:absolute;width:' . $w . 'px;height:' . $h . 'px;" src="' . $imgflashurl . '" />';
      break;
    case 'swf':
      $content = '<span id="' . $randomid . '" style="position:absolute;"></span>' .
        '<script type="text/javascript" reload="1">$(\'' . $randomid . '\').innerHTML=' .
        'AC_FL_RunContent(\'width\', \'' . $w . '\', \'height\', \'' . $h . '\', ' .
        '\'allowNetworking\', \'internal\', \'allowScriptAccess\', \'never\', ' .
        '\'src\', encodeURI(\'' . $imgflashurl . '\'), \'quality\', \'high\', \'bgcolor\', \'#ffffff\', ' .
        '\'wmode\', \'transparent\', \'allowfullscreen\', \'true\');</script>';
      break;
    default:
      $content = '';
  }
  if ($content) {
    if ($type == 1) {
      $content = '<div id="threadbeginid" style="display:none;">' .
        '<div class="flb beginidin"><span><div id="begincloseid" class="flbc" title="' . lang('core', 'close') . '">' . lang('core', 'close') . '</div></span></div>' .
        $content . '<div class="beginidimg" style=" width:' . $w . 'px;height:' . $h . 'px;">' .
        '<a href="' . $linkaddr . '" target="_blank" style="display: block; width:' . $w . 'px; height:' . $h . 'px;"></a></div></div>' .
        '<script type="text/javascript">threadbegindisplay(1, ' . $w . ', ' . $h . ', ' . $s . ');</script>';
    } else {
      $content = '<div id="threadbeginid">' .
        '<div class="flb beginidin">
      <span><div id="begincloseid" class="flbc" title="' . lang('core', 'close') . '">' . lang('core', 'close') . '</div></span>
    </div>' .
        $content . '<div class="beginidimg" style=" width:' . $w . 'px; height:' . $h . 'px;">' .
        '<a href="' . $linkaddr . '" target="_blank" style="display: block; width:' . $w . 'px; height:' . $h . 'px;"></a></div>
    </div>' .
        '<script type="text/javascript">threadbegindisplay(' . $type . ', ' . $w . ', ' . $h . ', ' . $s . ');</script>';
    }
  }
  $begincontent = $content;
  return $content;
}

function _checkviewgroup()
{
  global $_G;
  $RR = new ReturnResult(true);
  $_G['action']['action'] = 3;
  require_once libfile('function/group');
  $status = groupperm($_G['forum'], $_G['uid']);
  if ($status == 1) {
    return $RR->error(400, 400, lang("message", "forum_group_status_off"));
  } elseif ($status == 2) {
    return $RR->error(400, 400, lang("message", "forum_group_noallowed"));
  } elseif ($status == 3) {
    return $RR->error(400, 400, lang("message", "forum_group_moderated"));
  }
}

//* 复制来自function_forum下的showmessagenoperm方法
function noPermToForum($type, $fid, $formula = '')
{
  global $_G;
  loadcache('usergroups');
  if ($formula) {
    $formula = dunserialize($formula);
    $permmessage = stripslashes($formula['message']);
  }

  $usergroups = $nopermgroup = $forumnoperms = array();
  $nopermdefault = array(
    'viewperm' => array(),
    'getattachperm' => array(),
    'postperm' => array(7),
    'replyperm' => array(7),
    'postattachperm' => array(7),
  );
  $perms = array('viewperm', 'postperm', 'replyperm', 'getattachperm', 'postattachperm');

  foreach ($_G['cache']['usergroups'] as $gid => $usergroup) {
    $usergroups[$gid] = $usergroup['type'];
    $grouptype = $usergroup['type'] == 'member' ? 0 : 1;
    $nopermgroup[$grouptype][] = $gid;
  }
  if ($fid == $_G['forum']['fid']) {
    $forum = $_G['forum'];
  } else {
    $forum = \C::t('forum_forumfield')->fetch($fid);
  }

  foreach ($perms as $perm) {
    $permgroups = explode("\t", $forum[$perm]);
    $membertype = $forum[$perm] ? array_intersect($nopermgroup[0], $permgroups) : TRUE;
    $forumnoperm = $forum[$perm] ? array_diff(array_keys($usergroups), $permgroups) : $nopermdefault[$perm];
    foreach ($forumnoperm as $groupid) {
      $nopermtype = $membertype && $groupid == 7 ? 'login' : ($usergroups[$groupid] == 'system' || $usergroups[$groupid] == 'special' ? 'none' : ($membertype ? 'upgrade' : 'none'));
      $forumnoperms[$fid][$perm][$groupid] = array($nopermtype, $permgroups);
    }
  }

  $v = $forumnoperms[$fid][$type][$_G['groupid']][0];
  $gids = $forumnoperms[$fid][$type][$_G['groupid']][1];
  $comma = $permgroups = '';
  if (is_array($gids)) {
    foreach ($gids as $gid) {
      if ($gid && $_G['cache']['usergroups'][$gid]) {
        $permgroups .= $comma . $_G['cache']['usergroups'][$gid]['grouptitle'];
        $comma = ', ';
      } elseif ($_G['setting']['verify']['enabled'] && substr($gid, 0, 1) == 'v') {
        $vid = substr($gid, 1);
        $permgroups .= $comma . $_G['setting']['verify'][$vid]['title'];
        $comma = ', ';
      }
    }
  }

  $custom = 0;
  if ($permmessage) {
    $message = $permmessage;
    $custom = 1;
  } else {
    if ($v) {
      $message = $type . '_' . $v . '_nopermission';
    } else {
      $message = 'group_nopermission';
    }
  }

  return new ReturnResult(false, 403, 403, lang("template", $message, [
    'fid' => $fid, 'permgroups' => $permgroups, 'grouptitle' => $_G['group']['grouptitle']
  ]), [
    "custom" => $custom
  ]);
}

//* 复制来自function_forum下的formulaperm方法
function S_formulaperm($formula)
{
  global $_G;
  $R = new ReturnResult(true);
  if ($_G['forum']['ismoderator']) {
    return $R;
  }

  $formula = dunserialize($formula);
  $medalperm = $formula['medal'];
  $permusers = $formula['users'];
  $permmessage = $formula['message'];
  if ($_G['setting']['medalstatus'] && $medalperm) {
    $exists = 1;
    $_G['forum_formulamessage'] = '';
    $medalpermc = $medalperm;
    if ($_G['uid']) {
      $memberfieldforum = \C::t('common_member_field_forum')->fetch($_G['uid']);
      $medals = explode("\t", $memberfieldforum['medals']);
      unset($memberfieldforum);
      foreach ($medalperm as $k => $medal) {
        foreach ($medals as $r) {
          list($medalid) = explode("|", $r);
          if ($medalid == $medal) {
            $exists = 0;
            unset($medalpermc[$k]);
          }
        }
      }
    } else {
      $exists = 0;
    }
    if ($medalpermc) {
      loadcache('medals');
      foreach ($medalpermc as $medal) {
        if ($_G['cache']['medals'][$medal]) {
          $_G['forum_formulamessage'] .= '<img src="' . STATICURL . 'image/common/' . $_G['cache']['medals'][$medal]['image'] . '" style="vertical-align:middle;" />&nbsp;' . $_G['cache']['medals'][$medal]['name'] . '&nbsp; ';
        }
      }
      return $R->error(403, 403, lang("template", "forum_permforum_nomedal", [
        'forum_permforum_nomedal' => $_G['forum_formulamessage']
      ]));
    }
  }
  $formulatext = $formula[0];
  $formula = trim($formula[1]);
  if ($_G['adminid'] == 1 || $_G['forum']['ismoderator'] || in_array($_G['groupid'], explode("\t", $_G['forum']['spviewperm']))) {
    return $R->success(false);
  }
  if ($permusers) {
    $permusers = str_replace(array("\r\n", "\r"), array("\n", "\n"), $permusers);
    $permusers = explode("\n", trim($permusers));
    if (!in_array($_G['member']['username'], $permusers)) {
      return $R->error(403, 403, lang("template", "forum_permforum_disallow"));
    }
  }
  if (!$formula) {
    return $R->success(false);
  }
  if (strexists($formula, '$memberformula[')) {
    preg_match_all("/\\\$memberformula\['(\w+?)'\]/", $formula, $a);
    $profilefields = array();
    foreach ($a[1] as $field) {
      switch ($field) {
        case 'regdate':
          $formula = preg_replace_callback("/\{(\d{4})\-(\d{1,2})\-(\d{1,2})\}/", 'formulaperm_callback_123', $formula);
        case 'regday':
          break;
        case 'regip':
        case 'lastip':
          $formula = preg_replace("/\{([0-9a-fA-F\.\:\/]+?)\}/", "'\\1'", $formula);
          $formula = preg_replace('/(\$memberformula\[\'(regip|lastip)\'\])\s*=+\s*\'([0-9a-fA-F\.\:\/]+?)\'/', "ip::check_ip(\\1, '\\3')", $formula);
        case 'buyercredit':
        case 'sellercredit':
          space_merge($_G['member'], 'status');
          break;
        case substr($field, 0, 5) == 'field':
          space_merge($_G['member'], 'profile');
          $profilefields[] = $field;
          break;
      }
    }
    $memberformula = array();
    if ($_G['uid']) {
      $memberformula = $_G['member'];
      if (in_array('regday', $a[1])) {
        $memberformula['regday'] = intval((TIMESTAMP - $memberformula['regdate']) / 86400);
      }
      if (in_array('regdate', $a[1])) {
        $memberformula['regdate'] = date('Y-m-d', $memberformula['regdate']);
      }
      $memberformula['lastip'] = $memberformula['lastip'] ? $memberformula['lastip'] : $_G['clientip'];
    } else {
      if (isset($memberformula['regip'])) {
        $memberformula['regip'] = $_G['clientip'];
      }
      if (isset($memberformula['lastip'])) {
        $memberformula['lastip'] = $_G['clientip'];
      }
    }
  }
  @eval("\$formulaperm = ($formula) ? TRUE : FALSE;");
  if (!$formulaperm) {
    if (!$permmessage) {
      $language = lang('forum/misc');
      $search = array('regdate', 'regday', 'regip', 'lastip', 'buyercredit', 'sellercredit', 'digestposts', 'posts', 'threads', 'oltime');
      $replace = array($language['formulaperm_regdate'], $language['formulaperm_regday'], $language['formulaperm_regip'], $language['formulaperm_lastip'], $language['formulaperm_buyercredit'], $language['formulaperm_sellercredit'], $language['formulaperm_digestposts'], $language['formulaperm_posts'], $language['formulaperm_threads'], $language['formulaperm_oltime']);
      for ($i = 1; $i <= 8; $i++) {
        $search[] = 'extcredits' . $i;
        $replace[] = $_G['setting']['extcredits'][$i]['title'] ? $_G['setting']['extcredits'][$i]['title'] : $language['formulaperm_extcredits'] . $i;
      }
      if ($profilefields) {
        loadcache(array('fields_required', 'fields_optional'));
        foreach ($profilefields as $profilefield) {
          $search[] = $profilefield;
          $replace[] = !empty($_G['cache']['fields_optional']['field_' . $profilefield]) ? $_G['cache']['fields_optional']['field_' . $profilefield]['title'] : $_G['cache']['fields_required']['field_' . $profilefield]['title'];
        }
      }
      $i = 0;
      $_G['forum_usermsg'] = '';
      foreach ($search as $s) {
        if (in_array($s, array('digestposts', 'posts', 'threads', 'oltime', 'extcredits1', 'extcredits2', 'extcredits3', 'extcredits4', 'extcredits5', 'extcredits6', 'extcredits7', 'extcredits8'))) {
          $_G['forum_usermsg'] .= strexists($formulatext, $s) ? '<br />&nbsp;&nbsp;&nbsp;' . $replace[$i] . ': ' . (@eval('return intval(getuserprofile(\'' . $s . '\'));')) : '';
        } elseif (in_array($s, array('regdate', 'regip', 'regday'))) {
          $_G['forum_usermsg'] .= strexists($formulatext, $s) ? '<br />&nbsp;&nbsp;&nbsp;' . $replace[$i] . ': ' . (@eval('return $memberformula[\'' . $s . '\'];')) : '';
        }
        $i++;
      }
      $search = array_merge($search, array('and', 'or', '>=', '<=', '=='));
      $replace = array_merge($replace, array('&nbsp;&nbsp;<b>' . $language['formulaperm_and'] . '</b>&nbsp;&nbsp;', '&nbsp;&nbsp;<b>' . $language['formulaperm_or'] . '</b>&nbsp;&nbsp;', '&ge;', '&le;', '='));
      $_G['forum_formulamessage'] = str_replace($search, $replace, $formulatext);
    } else {
      $_G['forum_formulamessage'] = $permmessage;
    }

    if (!$permmessage) {
      return $R->error(403, 403, lang("template", "forum_permforum_nopermission", [
        'formulamessage' => $_G['forum_formulamessage'], 'usermsg' => $_G['forum_usermsg']
      ]));
    } else {
      return $R->error(403, 403, lang("template", "forum_permforum_nopermission_custommsg", [
        'formulamessage' => $_G['forum_formulamessage']
      ]));
    }
  }
  return $R;
}

class DiscuzXThread extends BaseObject
{
  function changeThreadViews($threadId, $newViews)
  {
    return \C::t('forum_thread')->increase($threadId, array('views' => $newViews), true);
  }
  function loadThread($ThreadId)
  {
    global $_G;
    include_once libfile('function/forumlist');
    include_once libfile('function/discuzcode');
    include_once libfile('function/post');
    include_once libfile('function/forum');

    $RR = new ReturnResult(null);

    $ForumId = null;
    $Forum = null;
    $_G['forum'] = &$Forum;
    $Thread = null;

    if (isset($_G['setting']['forumpicstyle'])) {
      $_G['setting']['forumpicstyle'] = dunserialize($_G['setting']['forumpicstyle']);
      empty($_G['setting']['forumpicstyle']['thumbwidth']) && $_G['setting']['forumpicstyle']['thumbwidth'] = 203;
      empty($_G['setting']['forumpicstyle']['thumbheight']) && $_G['setting']['forumpicstyle']['thumbheight'] = 0;
    } else {
      $_G['setting']['forumpicstyle'] = array('thumbwidth' => 203, 'thumbheight' => 0);
    }

    $modthreadkey = isset($_GET['modthreadkey']) && $_GET['modthreadkey'] == modauthkey($ThreadId) ? $_GET['modthreadkey'] : '';
    $_G['forum_auditstatuson'] = $modthreadkey ? true : false;

    $metadescription = $hookscriptmessage = '';
    $adminid = $_G['adminid'];

    if (!empty($ThreadId) || !empty($ForumId)) {
      if (!empty($ThreadId)) {
        $archiveid = !empty($_GET['archiveid']) ? intval($_GET['archiveid']) : null;
        $Thread = get_thread_by_tid($ThreadId, $archiveid);
        $_G['thread'] = &$Thread;

        $Thread['allreplies'] = $Thread['replies'] + $Thread['comments'];
        if (
          !$_G['forum_auditstatuson'] && !empty($Thread)
          && !($Thread['displayorder'] >= 0 || (in_array($Thread['displayorder'], array(-4, -3, -2)) && $_G['uid'] && $Thread['authorid'] == $_G['uid']))
        ) {
          $Thread = null;
        }

        $_G['forum_thread'] = &$_G['thread'];

        if (empty($Thread)) {
          $ForumId = $tid = 0;
        } else {
          $ForumId = $Thread['fid'];
          $ThreadId = $Thread['tid'];
        }
      }

      if ($ForumId) {
        $Forum = DiscuzXForum::singleton()->getForum($ForumId);
      }

      if (!$Forum) {
        $ForumId = 0;
      }
    }

    $_G['fid'] = $ForumId;
    $_G['tid'] = $ThreadId;
    $_G['current_grouplevel'] = &$grouplevel;

    if (empty($_G['uid'])) {
      $_G['group']['allowpostactivity'] = $_G['group']['allowpostpoll'] = $_G['group']['allowvote'] = $_G['group']['allowpostreward'] = $_G['group']['allowposttrade'] = $_G['group']['allowpostdebate'] = $_G['group']['allowpostrushreply'] = 0;
    }

    if (!empty($_GET['checkrush']) && preg_match('/[^0-9_]/', $_GET['checkrush'])) {
      $_GET['checkrush'] = '';
    }

    if (!$Thread || !$Forum) {
      return $RR->error(404, 404, "抱歉，指定的主题不存在或已被删除或正在被审核"); //* 抱歉，指定的主题不存在或已被删除或正在被审核
    }

    $page = max(1, $_G['page']);
    $_GET['stand'] = isset($_GET['stand']) && in_array($_GET['stand'], array('0', '1', '2')) ? $_GET['stand'] : null;

    $threadtableids = !empty($_G['cache']['threadtableids']) ? $_G['cache']['threadtableids'] : array();
    $threadtable_info = !empty($_G['cache']['threadtable_info']) ? $_G['cache']['threadtable_info'] : array();

    $archiveid = $Thread['threadtableid'];
    $Thread['is_archived'] = $archiveid ? true : false;
    $Thread['archiveid'] = $archiveid;
    $forum['threadtableid'] = $archiveid;
    $threadtable = $Thread['threadtable'];
    $posttableid = $Thread['posttableid'];
    $posttable = $Thread['posttable'];

    $_G['action']['fid'] = $ForumId;
    $_G['action']['tid'] = $ThreadId;

    $st_p = $_G['uid'] . '|' . TIMESTAMP;
    dsetcookie('st_p', $st_p . '|' . md5($st_p . $_G['config']['security']['authkey']));

    $_GET['authorid'] = !empty($_GET['authorid']) ? intval($_GET['authorid']) : 0;
    $_GET['ordertype'] = !empty($_GET['ordertype']) ? intval($_GET['ordertype']) : 0;

    $fromuid = $_G['setting']['creditspolicy']['promotion_visit'] && $_G['uid'] ? '&amp;fromuid=' . $_G['uid'] : '';
    $feeduid = $_G['forum_thread']['authorid'] ? $_G['forum_thread']['authorid'] : 0;
    $feedpostnum = $_G['forum_thread']['replies'] > $_G['ppp'] ? $_G['ppp'] : ($_G['forum_thread']['replies'] ? $_G['forum_thread']['replies'] : 1);

    if (!empty($_GET['extra'])) {
      parse_str($_GET['extra'], $extra);
      $_GET['extra'] = array();
      foreach ($extra as $_k => $_v) {
        if (preg_match('/^\w+$/', $_k)) {
          if (!is_array($_v)) {
            $_GET['extra'][] = $_k . '=' . rawurlencode($_v);
          } else {
            $_GET['extra'][] = http_build_query(array($_k => $_v));
          }
        }
      }
      $_GET['extra'] = implode('&', $_GET['extra']);
    }

    $_G['forum_threadindex'] = '';
    $skipaids = $aimgs = $_G['forum_posthtml'] = array();

    $Thread['subjectenc'] = rawurlencode($_G['forum_thread']['subject']);
    $Thread['short_subject'] = cutstr($_G['forum_thread']['subject'], 52);

    if ($_G['forum']['status'] == 3) {
      $res = _checkviewgroup();
      if ($res->error) return $res;

      $_G['grouptypeid'] = $_G['forum']['fup'];
    }

    $_GET['extra'] = getgpc('extra') ? rawurlencode($_GET['extra']) : '';

    $_G['forum_tagscript'] = '';

    $threadsort = $Thread['sortid'] && isset($_G['forum']['threadsorts']['types'][$Thread['sortid']]) ? 1 : 0;
    if ($threadsort) {
      include_once libfile('function/threadsort');
      $threadsortshow = threadsortshow($Thread['sortid'], $_G['tid']);
    }

    if (empty($_G['forum']['allowview'])) {
      if (!$_G['forum']['viewperm'] && !$_G['group']['readaccess']) {
        return $RR->error(403, 403, lang("template", "group_nopermission", [
          'grouptitle' => $_G['group']['grouptitle']
        ]));
      } elseif ($_G['forum']['viewperm'] && !forumperm($_G['forum']['viewperm'])) {
        return noPermToForum("viewpern", $_G['fid'], $_G['forum']['formulaperm']);
      }
    } elseif ($_G['forum']['allowview'] == -1) {
      return $RR->error(403, 403, lang("template", "forum_access_view_disallow"));
    }

    if ($_G['forum']['formulaperm']) {
      $res = S_formulaperm($_G['forum']['formulaperm']);
      if ($res->error) return $res;
    }

    if ($_G['forum']['password'] && $_G['forum']['password'] != $_G['cookie']['fidpw' . $_G['fid']]) {
      return $RR->error(400, 400, "该板块需要输入密码才可访问", null, [
        "location" => "{$_G['siteurl']}forum.php?mod=forumdisplay&fid={$_G['fid']}"
      ]);
    }

    if ($_G['forum']['price'] && !$_G['forum']['ismoderator']) {
      $membercredits = \C::t('common_member_forum_buylog')->get_credits($_G['uid'], $_G['fid']);
      $paycredits = $_G['forum']['price'] - $membercredits;
      if ($paycredits > 0) {
        return $RR->error(400, 400, "该板块需要支付一定的积分才可访问", null, [
          "location" => "{$_G['siteurl']}forum.php?mod=forumdisplay&fid={$_G['fid']}"
        ]);
      }
    }

    if ($_G['forum_thread']['readperm'] && $_G['forum_thread']['readperm'] > $_G['group']['readaccess'] && !$_G['forum']['ismoderator'] && $_G['forum_thread']['authorid'] != $_G['uid']) {
      return $RR->error(403, 403, lang("template", "thread_nopermission", [
        'readperm' => $_G['forum_thread']['readperm']
      ]));
    }

    $usemagic = array('user' => array(), 'thread' => array());

    $replynotice = getstatus($_G['forum_thread']['status'], 6);

    $hiddenreplies = getstatus($_G['forum_thread']['status'], 2);

    $rushreply = getstatus($_G['forum_thread']['status'], 3);

    $savepostposition = getstatus($_G['forum_thread']['status'], 1);

    $incollection = getstatus($_G['forum_thread']['status'], 9);

    $_G['forum_threadpay'] = FALSE;
    if ($_G['forum_thread']['price'] > 0 && $_G['forum_thread']['special'] == 0) {
      if ($_G['setting']['maxchargespan'] && TIMESTAMP - $_G['forum_thread']['dateline'] >= $_G['setting']['maxchargespan'] * 3600) {
        \C::t('forum_thread')->update($_G['tid'], array('price' => 0), false, false, $archiveid);
        $_G['forum_thread']['price'] = 0;
      } else {
        $exemptvalue = $_G['forum']['ismoderator'] ? 128 : 16;
        if (!($_G['group']['exempt'] & $exemptvalue) && $_G['forum_thread']['authorid'] != $_G['uid']) {
          if (!(\C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BTC', $_G['tid']))) {
            include_once libfile('thread/pay', 'include');
            $_G['forum_threadpay'] = TRUE;
          }
        }
      }
    }

    if ($rushreply) {
      $rewardfloor = '';
      $rushresult = $rewardfloorarr = $rewardfloorarray = array();
      $rushresult = \C::t('forum_threadrush')->fetch($_G['tid']);
      if ($rushresult['creditlimit'] == -996) {
        $rushresult['creditlimit'] = '';
      }
      if ((TIMESTAMP < $rushresult['starttimefrom'] || ($rushresult['starttimeto'] && TIMESTAMP > $rushresult['starttimeto']) || ($rushresult['stopfloor'] && $_G['forum_thread']['replies'] + 1 >= $rushresult['stopfloor'])) && $_G['forum_thread']['closed'] == 0) {
        \C::t('forum_thread')->update($_G['tid'], array('closed' => 1));
      } elseif (($rushresult['starttimefrom'] && TIMESTAMP > $rushresult['starttimefrom']) && $_G['forum_thread']['closed'] == 1) {
        if (($rushresult['starttimeto'] && TIMESTAMP < $rushresult['starttimeto'] || !$rushresult['starttimeto']) && ($rushresult['stopfloor'] && $_G['forum_thread']['replies'] + 1 < $rushresult['stopfloor'] || !$rushresult['stopfloor'])) {
          \C::t('forum_thread')->update($_G['tid'], array('closed' => 0));
        }
      }
      if ($rushresult['starttimefrom'] > TIMESTAMP) {
        $rushresult['timer'] = $rushresult['starttimefrom'] - TIMESTAMP;
        $rushresult['timertype'] = 'start';
      } elseif ($rushresult['starttimeto'] > TIMESTAMP) {
        $rushresult['timer'] = $rushresult['starttimeto'] - TIMESTAMP;
        $rushresult['timertype'] = 'end';
      }
      $rushresult['starttimefrom'] = $rushresult['starttimefrom'] ? dgmdate($rushresult['starttimefrom']) : '';
      $rushresult['starttimeto'] = $rushresult['starttimeto'] ? dgmdate($rushresult['starttimeto']) : '';
      $rushresult['creditlimit_title'] = $_G['setting']['creditstransextra'][11] ? $_G['setting']['extcredits'][$_G['setting']['creditstransextra'][11]]['title'] : lang('forum/misc', 'credit_total');
    }

    if ($_G['forum_thread']['replycredit'] > 0) {
      $_G['forum_thread']['replycredit_rule'] = \C::t('forum_replycredit')->fetch($Thread['tid']);
      $_G['forum_thread']['replycredit_rule']['remaining'] = $_G['forum_thread']['replycredit'] / $_G['forum_thread']['replycredit_rule']['extcredits'];
      $_G['forum_thread']['replycredit_rule']['extcreditstype'] = $_G['forum_thread']['replycredit_rule']['extcreditstype'] ? $_G['forum_thread']['replycredit_rule']['extcreditstype'] : $_G['setting']['creditstransextra'][10];
    } else {
      $_G['forum_thread']['replycredit_rule']['extcreditstype'] = $_G['setting']['creditstransextra'][10];
    }
    $_G['group']['raterange'] = $_G['setting']['modratelimit'] && $adminid == 3 && !$_G['forum']['ismoderator'] ? array() : $_G['group']['raterange'];

    $_G['group']['allowgetattach'] = (!empty($_G['forum']['allowgetattach'])) ? ($_G['forum']['allowgetattach'] == 1 ? true : false) : ($_G['forum']['getattachperm'] ? forumperm($_G['forum']['getattachperm']) : $_G['group']['allowgetattach']);
    $_G['group']['allowgetimage'] = (!empty($_G['forum']['allowgetimage'])) ? ($_G['forum']['allowgetimage'] == 1 ? true : false) : ($_G['forum']['getattachperm'] ? forumperm($_G['forum']['getattachperm']) : $_G['group']['allowgetimage']);
    $_G['getattachcredits'] = '';
    if ($_G['forum_thread']['attachment']) {
      $exemptvalue = $_G['forum']['ismoderator'] ? 32 : 4;
      if (!($_G['group']['exempt'] & $exemptvalue)) {
        $creditlog = updatecreditbyaction('getattach', $_G['uid'], array(), '', 1, 0, $_G['forum_thread']['fid']);
        $p = '';
        if ($creditlog['updatecredit']) for ($i = 1; $i <= 8; $i++) {
          if ($policy = $creditlog['extcredits' . $i]) {
            $_G['getattachcredits'] .= $p . $_G['setting']['extcredits'][$i]['title'] . ' ' . $policy . ' ' . $_G['setting']['extcredits'][$i]['unit'];
            $p = ', ';
          }
        }
      }
    }

    $exemptvalue = $_G['forum']['ismoderator'] ? 64 : 8;
    $_G['forum_attachmentdown'] = $_G['group']['exempt'] & $exemptvalue;

    list($seccodecheck, $secqaacheck) = seccheck('post', 'reply');
    $usesigcheck = $_G['uid'] && $_G['group']['maxsigsize'];

    $postlist = $_G['forum_attachtags'] = $attachlist = $_G['forum_threadstamp'] = array();
    $aimgcount = 0;
    $_G['forum_attachpids'] = array();

    if ($_G['forum_thread']['stamp'] >= 0) {
      $_G['forum_threadstamp'] = $_G['cache']['stamps'][$_G['forum_thread']['stamp']];
    }

    $lastmod = viewthread_lastmod($_G['forum_thread']);

    $showsettings = str_pad(decbin($_G['setting']['showsettings']), 3, '0', STR_PAD_LEFT);

    $showsignatures = $showsettings[0];
    $showavatars = $showsettings[1];
    $_G['setting']['showimages'] = $showsettings[2];

    $highlightstatus = isset($_GET['highlight']) && str_replace('+', '', $_GET['highlight']) ? 1 : 0;

    $_G['forum']['allowreply'] = isset($_G['forum']['allowreply']) ? $_G['forum']['allowreply'] : '';
    $_G['forum']['allowpost'] = isset($_G['forum']['allowpost']) ? $_G['forum']['allowpost'] : '';

    $allowpostreply = ($_G['forum']['allowreply'] != -1) && (($_G['forum_thread']['isgroup'] || (!$_G['forum_thread']['closed'] && !checkautoclose($_G['forum_thread']))) || $_G['forum']['ismoderator']) && ((!$_G['forum']['replyperm'] && $_G['group']['allowreply']) || ($_G['forum']['replyperm'] && forumperm($_G['forum']['replyperm'])) || $_G['forum']['allowreply']);
    $fastpost = $_G['setting']['fastpost'] && !$_G['forum_thread']['archiveid'] && ($_G['forum']['status'] != 3 || $_G['isgroupuser']);
    $_G['group']['allowpost'] = $_G['forum']['allowpost'] != -1 && ((!$_G['forum']['postperm'] && $_G['group']['allowpost']) || ($_G['forum']['postperm'] && forumperm($_G['forum']['postperm'])) || $_G['forum']['allowpost']);

    $_G['forum']['allowpostattach'] = isset($_G['forum']['allowpostattach']) ? $_G['forum']['allowpostattach'] : '';
    $allowpostattach = $allowpostreply && ($_G['forum']['allowpostattach'] != -1 && ($_G['forum']['allowpostattach'] == 1 || (!$_G['forum']['postattachperm'] && $_G['group']['allowpostattach']) || ($_G['forum']['postattachperm'] && forumperm($_G['forum']['postattachperm']))));

    if ($_G['group']['allowpost']) {
      $_G['group']['allowpostpoll'] = $_G['group']['allowpostpoll'] && ($_G['forum']['allowpostspecial'] & 1);
      $_G['group']['allowposttrade'] = $_G['group']['allowposttrade'] && ($_G['forum']['allowpostspecial'] & 2);
      $_G['group']['allowpostreward'] = $_G['group']['allowpostreward'] && ($_G['forum']['allowpostspecial'] & 4) && isset($_G['setting']['extcredits'][$_G['setting']['creditstrans']]);
      $_G['group']['allowpostactivity'] = $_G['group']['allowpostactivity'] && ($_G['forum']['allowpostspecial'] & 8);
      $_G['group']['allowpostdebate'] = $_G['group']['allowpostdebate'] && ($_G['forum']['allowpostspecial'] & 16);
    } else {
      $_G['group']['allowpostpoll'] = $_G['group']['allowposttrade'] = $_G['group']['allowpostreward'] = $_G['group']['allowpostactivity'] = $_G['group']['allowpostdebate'] = FALSE;
    }

    $_G['forum']['threadplugin'] = $_G['group']['allowpost'] && $_G['setting']['threadplugins'] ? is_array($_G['forum']['threadplugin']) ? $_G['forum']['threadplugin'] : dunserialize($_G['forum']['threadplugin']) : array();

    $_G['setting']['visitedforums'] = $_G['setting']['visitedforums'] && $_G['forum']['status'] != 3 ? visitedforums() : '';

    if (!isset($_G['cookie']['collapse']) || strpos($_G['cookie']['collapse'], 'modarea_c') === FALSE) {
      $collapseimg['modarea_c'] = 'collapsed_no';
      $collapse['modarea_c'] = '';
    } else {
      $collapseimg['modarea_c'] = 'collapsed_yes';
      $collapse['modarea_c'] = 'display: none';
    }

    viewthread_updateviews($archiveid);

    $specialextra = '';
    $tpids = array();
    if ($_G['forum_thread']['special'] == 2) {
      if (!empty($_GET['do']) && $_GET['do'] == 'tradeinfo') {
        include_once libfile('thread/trade', 'include');
      }
      $query = \C::t('forum_trade')->fetch_all_thread_goods($_G['tid']);
      foreach ($query as $trade) {
        $tpids[] = $trade['pid'];
      }
    } elseif ($_G['forum_thread']['special'] == 5) {
      if (isset($_GET['stand'])) {
        $specialextra = "&amp;stand={$_GET['stand']}";
      }
    }

    $onlyauthoradd = '';

    $ordertype = empty($_GET['ordertype']) && getstatus($_G['forum_thread']['status'], 4) ? 1 : $_GET['ordertype'];
    if ($_G['page'] === 1 && $_G['forum_thread']['stickreply'] && empty($_GET['authorid'])) {
      $poststick = \C::t('forum_poststick')->fetch_all_by_tid($_G['tid']);
      foreach (\C::t('forum_post')->fetch_all_post($posttableid, array_keys($poststick)) as $post) {
        if ($post['invisible'] != 0) {
          continue;
        }
        $post['position'] = $poststick[$post['pid']]['position'];
        $post['avatar'] = avatar($post['authorid'], 'small');
        $post['isstick'] = true;
      }
    }
    if ($rushreply) {
      $rushids = $arr = array();
      $str = ',,';
      $preg_str = rushreply_rule($rushresult);
      if ($_GET['checkrush']) {
        for ($i = 1; $i <= $_G['forum_thread']['replies'] + 1; $i++) {
          $str = $str . $i . ',,';
        }
        preg_match_all($preg_str, $str, $arr);
        $arr = $arr[0];
        foreach ($arr as $var) {
          $var = str_replace(',', '', $var);
          $rushids[$var] = $var;
        }
        $temp_reply = $_G['forum_thread']['replies'];
        $_G['forum_thread']['replies'] = $countrushpost = max(0, count($rushids) - 1);
        $countrushpost = max(0, count($rushids));
        $rushids = array_slice($rushids, ($page - 1) * $_G['ppp'], $_G['ppp']);
        foreach (\C::t('forum_post')->fetch_all_by_tid_position($posttableid, $_G['tid'], $rushids) as $post) {
          $postarr[$post['position']] = $post;
        }
      } else {
        for ($i = ($page - 1) * $_G['ppp'] + 1; $i <= $page * $_G['ppp']; $i++) {
          $str = $str . $i . ',,';
        }
        preg_match_all($preg_str, $str, $arr);
        $arr = $arr[0];
        foreach ($arr as $var) {
          $var = str_replace(',', '', $var);
          $rushids[$var] = $var;
        }
        $_G['forum_thread']['replies'] = $_G['forum_thread']['replies'] - 1;
      }
    }

    if (!empty($_GET['authorid'])) {
      $_G['forum_thread']['replies'] = \C::t('forum_post')->count_by_tid_invisible_authorid($_G['tid'], $_GET['authorid']);
      $_G['forum_thread']['replies']--;
      if ($_G['forum_thread']['replies'] < 0) {
        return $RR->error(400, 400, lang("template", "undefined_action"));
      }
      $onlyauthoradd = 1;
    } elseif ($_G['forum_thread']['special'] == 5) {
      if (isset($_GET['stand']) && $_GET['stand'] >= 0 && $_GET['stand'] < 3) {
        $_G['forum_thread']['replies'] = \C::t('forum_debatepost')->count_by_tid_stand($_G['tid'], $_GET['stand']);
      } else {
        $_G['forum_thread']['replies'] = \C::t('forum_post')->count_visiblepost_by_tid($_G['tid']);
        $_G['forum_thread']['replies'] > 0 && $_G['forum_thread']['replies']--;
      }
    } elseif ($_G['forum_thread']['special'] == 2) {
      $tradenum = \C::t('forum_trade')->fetch_counter_thread_goods($_G['tid']);
      $_G['forum_thread']['replies'] -= $tradenum;
    }

    $_G['ppp'] = $_G['forum']['threadcaches'] && !$_G['uid'] ? $_G['setting']['postperpage'] : $_G['ppp'];
    $totalpage = ceil(($_G['forum_thread']['replies'] + 1) / $_G['ppp']);
    $page > $totalpage && $page = $totalpage;
    $_G['forum_pagebydesc'] = $page > 2 && $page > ($totalpage / 2) ? TRUE : FALSE;

    if ($_G['forum_pagebydesc']) {
      $firstpagesize = ($_G['forum_thread']['replies'] + 1) % $_G['ppp'];
      $_G['forum_ppp3'] = $_G['forum_ppp2'] = $page == $totalpage && $firstpagesize ? $firstpagesize : $_G['ppp'];
      $realpage = $totalpage - $page + 1;
      if ($firstpagesize == 0) {
        $firstpagesize = $_G['ppp'];
      }
      $start_limit = max(0, ($realpage - 2) * $_G['ppp'] + $firstpagesize);
      $_G['forum_numpost'] = ($page - 1) * $_G['ppp'];
      if ($ordertype == 1) {
        $_G['forum_numpost'] = $_G['forum_thread']['replies'] + 2 - $_G['forum_numpost'] + ($page == $totalpage ? 1 : 0);
      }
    } else {
      $start_limit = $_G['forum_numpost'] = max(0, ($page - 1) * $_G['ppp']);
      if ($start_limit > $_G['forum_thread']['replies']) {
        $start_limit = $_G['forum_numpost'] = 0;
        $page = 1;
      }
      if ($ordertype == 1) {
        $_G['forum_numpost'] = $_G['forum_thread']['replies'] + 2 - $_G['forum_numpost'];
      }
    }

    $_G['forum_newpostanchor'] = $_G['forum_postcount'] = 0;
    $_G['forum_onlineauthors'] = $_G['forum_cachepid'] = $_G['blockedpids'] = array();

    if ($_G['forum_auditstatuson'] || in_array($_G['forum_thread']['displayorder'], array(-2, -3, -4)) && $_G['forum_thread']['authorid'] == $_G['uid']) {
      $visibleallflag = 1;
    }

    if (getgpc('checkrush') && $rushreply) {
      $_G['forum_thread']['replies'] = $temp_reply;
    }

    $firstpost = \C::t('forum_post')->fetch_threadpost_by_tid_invisible($ThreadId);
    $firstpost = DiscuzXPost::singleton()->procpost($Forum, $Thread, $firstpost, $firstpost['lastvisit'], $ordertype);
    $_G['forum_firstpid'] = $firstpost['pid'];

    $tagnames = $locationpids = $member_blackList = array();

    $_G['allblocked'] = true;

    if ($_G['allblocked']) {
      $_G['blockedpids'] = array();
    }

    if ($locationpids) {
      $locations = \C::t('forum_post_location')->fetch_all($locationpids);
    }

    // if ($postlist && !empty($rushids)) {
    //   foreach ($postlist as $pid => $post) {
    //     $post['number'] = $post['position'];
    //     $postlist[$pid] = checkrushreply($post);
    //   }
    // }

    // if ($_G['setting']['repliesrank'] && $postlist) {
    //   if ($postlist) {
    //     foreach (\C::t('forum_hotreply_number')->fetch_all_by_pids(array_keys($postlist)) as $pid => $post) {
    //       $postlist[$pid]['postreview']['support'] = dintval($post['support']);
    //       $postlist[$pid]['postreview']['against'] = dintval($post['against']);
    //     }
    //   }
    // }

    if ($_G['forum_thread']['special'] > 0) {
      $_G['forum_thread']['starttime'] = gmdate($_G['forum_thread']['dateline']);
      $_G['forum_thread']['remaintime'] = '';
      switch ($_G['forum_thread']['special']) {
        case 1:
          require_once libfile('thread/poll', 'include');
          break;
        case 2:
          require_once libfile('thread/trade', 'include');
          break;
        case 3:
          require_once libfile('thread/reward', 'include');
          break;
        case 4:
          require_once libfile('thread/activity', 'include');
          break;
        case 5:
          require_once libfile('thread/debate', 'include');
          break;
        case 127:
          if ($_G['forum_firstpid']) {
            $sppos = strpos($firstpost['message'], chr(0) . chr(0) . chr(0));
            $specialextra = substr($firstpost['message'], $sppos + 3);
            $firstpost['message'] = substr($firstpost['message'], 0, $sppos);
            if ($specialextra) {
              if (array_key_exists($specialextra, $_G['setting']['threadplugins'])) {
                @include_once DISCUZ_ROOT . './source/plugin/' . $_G['setting']['threadplugins'][$specialextra]['module'] . '.class.php';
                $classname = 'threadplugin_' . $specialextra;
                if (class_exists($classname) && method_exists($threadpluginclass = new $classname, 'viewthread')) {
                  $threadplughtml = $threadpluginclass->viewthread($_G['tid']);
                }
              }
            }
          }
          break;
      }
    }

    if (!empty($_G['setting']['sessionclose'])) {
      $_G['setting']['vtonlinestatus'] = 1;
    }

    if ($_G['setting']['vtonlinestatus'] == 2 && $_G['forum_onlineauthors']) {
      foreach (\C::app()->session->fetch_all_by_uid(array_keys($_G['forum_onlineauthors'])) as $author) {
        if (!$author['invisible']) {
          $_G['forum_onlineauthors'][$author['uid']] = 1;
        }
      }
    } else {
      $_G['forum_onlineauthors'] = array();
    }

    $_G['forum_thread']['heatlevel'] = $_G['forum_thread']['recommendlevel'] = 0;
    if ($_G['setting']['heatthread']['iconlevels']) {
      foreach ($_G['setting']['heatthread']['iconlevels'] as $k => $i) {
        if ($_G['forum_thread']['heats'] > $i) {
          $_G['forum_thread']['heatlevel'] = $k + 1;
          break;
        }
      }
    }

    if (!empty($_G['setting']['recommendthread']['status']) && $_G['forum_thread']['recommends']) {
      foreach ($_G['setting']['recommendthread']['iconlevels'] as $k => $i) {
        if ($_G['forum_thread']['recommends'] > $i) {
          $_G['forum_thread']['recommendlevel'] = $k + 1;
          break;
        }
      }
    }

    if ($_G['forum']['alloweditpost'] && $_G['uid']) {
      $alloweditpost_status = getstatus($_G['setting']['alloweditpost'], $_G['forum_thread']['special'] + 1);
      if (!$alloweditpost_status) {
        $edittimelimit = $_G['group']['edittimelimit'] * 60;
      }
    }

    if ($_G['forum_thread']['replies'] > $_G['forum_thread']['views']) {
      $_G['forum_thread']['views'] = $_G['forum_thread']['replies'];
    }

    $_G['forum_thread']['relay'] = 0;

    if (getstatus($_G['forum_thread']['status'], 10)) {
      $preview = \C::t('forum_threadpreview')->fetch($_G['tid']);
      $_G['forum_thread']['relay'] = $preview['relay'];
    }

    $_G['forum_thread']['authorAvatar'] = $_G['forum_thread']['author'] ? avatar($_G['forum_thread']['authorid'], "middle", 1) : avatar(0, "middle", 1);
    return $RR->success([
      "thread" => $_G['forum_thread'],
      "post" => $firstpost,
      "forum" => $Forum,
    ]);
  }
}
