<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\BaseObject;

class DiscuzXForum extends BaseObject
{
  public function getForum($ForumId)
  {
    global $_G;
    include_once libfile('function/forum');
    include_once libfile('function/forumlist');
    include_once libfile('function/discuzcode');

    $ForumIds = is_array($ForumId) ? $ForumId : [$ForumId];

    $adminid = $_G['adminid'];
    $Forums = \DB::fetch_all("SELECT ff.*, f.* FROM %t f LEFT JOIN %t ff ON ff.fid=f.fid WHERE f.fid IN(%n)", array("forum_forum", 'forum_forumfield', $ForumIds));

    foreach ($Forums as &$Forum) {
      if ($_G['uid']) {
        if ($_G['member']['accessmasks']) {
          $query = \C::t('forum_access')->fetch_all_by_fid_uid($Forum['fid'], $_G['uid']);
          $Forum['allowview'] = $query[0]['allowview'];
          $Forum['allowpost'] = $query[0]['allowpost'];
          $Forum['allowreply'] = $query[0]['allowreply'];
          $Forum['allowgetattach'] = $query[0]['allowgetattach'];
          $Forum['allowgetimage'] = $query[0]['allowgetimage'];
          $Forum['allowpostattach'] = $query[0]['allowpostattach'];
          $Forum['allowpostimage'] = $query[0]['allowpostimage'];
        }
        if ($adminid == 3) {
          $Forum['ismoderator'] = \C::t('forum_moderator')->fetch_uid_by_fid_uid($Forum['fid'], $_G['uid']);
        }
      }
      $Forum['ismoderator'] = !empty($Forum['ismoderator']) || $adminid == 1 || $adminid == 2 ? 1 : 0;
      $gorup_admingroupids = $_G['setting']['group_admingroupids'] ? dunserialize($_G['setting']['group_admingroupids']) : array('1' => '1');

      if ($Forum['status'] == 3) {
        if (!empty($Forum['moderators'])) {
          $Forum['moderators'] = dunserialize($Forum['moderators']);
        } else {
          include_once libfile('function/group');
          $Forum['moderators'] = update_groupmoderators($Forum['fid']);
        }
        if ($_G['uid'] && $_G['adminid'] != 1) {
          $Forum['ismoderator'] = !empty($Forum['moderators'][$_G['uid']]) ? 1 : 0;
          $_G['adminid'] = 0;
          if ($Forum['ismoderator'] || $gorup_admingroupids[$_G['groupid']]) {
            $_G['adminid'] = $_G['adminid'] ? $_G['adminid'] : 3;
            if (!empty($gorup_admingroupids[$_G['groupid']])) {
              $Forum['ismoderator'] = 1;
              $_G['adminid'] = 2;
            }

            $group_userperm = dunserialize($_G['setting']['group_userperm']);
            if (is_array($group_userperm)) {
              $_G['group'] = array_merge($_G['group'], $group_userperm);
              $_G['group']['allowmovethread'] = $_G['group']['allowcopythread'] = $_G['group']['allowedittypethread'] = 0;
            }
          }
        }
      }
      foreach (array('threadtypes', 'threadsorts', 'creditspolicy', 'modrecommend') as $key) {
        $Forum[$key] = !empty($Forum[$key]) ? dunserialize($Forum[$key]) : array();
        if (!is_array($Forum[$key])) {
          $Forum[$key] = array();
        }
      }

      if (!empty($Forum['threadtypes']['types'])) {
        safefilter($Forum['threadtypes']['types']);
      }
      if (!empty($Forum['threadtypes']['options']['name'])) {
        safefilter($Forum['threadtypes']['options']['name']);
      }
      if (!empty($Forum['threadsorts']['types'])) {
        safefilter($Forum['threadsorts']['types']);
      }

      if ($Forum['status'] == 3) {
        $_G['isgroupuser'] = 0;
        $_G['basescript'] = 'group';
        if ($Forum['level'] == 0) {
          $levelinfo = \C::t('forum_grouplevel')->fetch_by_credits($Forum['commoncredits']);
          $levelid = $levelinfo['levelid'];
          $Forum['level'] = $levelid;
          \C::t('forum_forum')->update_group_level($levelid, $fid);
        }
        if ($Forum['level'] != -1) {
          loadcache('grouplevels');
          $grouplevel = $_G['grouplevels'][$Forum['level']];
          if (!empty($grouplevel['icon'])) {
            $valueparse = parse_url($grouplevel['icon']);
            if (!isset($valueparse['host'])) {
              $grouplevel['icon'] = $_G['setting']['attachurl'] . 'common/' . $grouplevel['icon'];
            }
          }
        }

        $group_postpolicy = $grouplevel['postpolicy'];
        if (is_array($group_postpolicy)) {
          $Forum = array_merge($Forum, $group_postpolicy);
        }
        $Forum['allowfeed'] = $_G['setting']['group_allowfeed'];
        if ($_G['uid']) {
          if (!empty($Forum['moderators'][$_G['uid']])) {
            $_G['isgroupuser'] = 1;
          } else {
            $groupuserinfo = \C::t('forum_groupuser')->fetch_userinfo($_G['uid'], $fid);
            $_G['isgroupuser'] = $groupuserinfo['level'];
            if ($_G['isgroupuser'] <= 0 && empty($Forum['ismoderator'])) {
              $_G['group']['allowrecommend'] = $_G['cache']['usergroup_' . $_G['groupid']]['allowrecommend'] = 0;
              $_G['group']['allowcommentpost'] = $_G['cache']['usergroup_' . $_G['groupid']]['allowcommentpost'] = 0;
              $_G['group']['allowcommentitem'] = $_G['cache']['usergroup_' . $_G['groupid']]['allowcommentitem'] = 0;
              $_G['group']['raterange'] = $_G['cache']['usergroup_' . $_G['groupid']]['raterange'] = array();
              $_G['group']['allowvote'] = $_G['cache']['usergroup_' . $_G['groupid']]['allowvote'] = 0;
            } else {
              $_G['isgroupuser'] = 1;
            }
          }
        }
      }
      $Forum['extra'] = dunserialize($Forum['extra']);
      $Forum['formulaperm'] = dunserialize($Forum['formulaperm']);
      $Forum['icon'] = get_forumimg($Forum['icon']);
    }

    return is_array($ForumId) ? $Forums : $Forums[0];
  }
}
