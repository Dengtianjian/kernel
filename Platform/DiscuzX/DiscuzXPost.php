<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\BaseObject;

class DiscuzXPost extends BaseObject
{
  protected function parsebegin($linkaddr, $imgflashurl, $w = 0, $h = 0, $type = 0, $s = 0)
  {
    static $begincontent;
    if ($begincontent) {
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
  protected function viewthread_numbercard($post)
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
  public function procpost($Forum, $Thread, $post, $lastvisit, $ordertype, $maxposition = 0)
  {
    global $_G;

    if (!$_G['forum_newpostanchor'] && $post['dateline'] > $lastvisit) {
      $post['newpostanchor'] = '<a name="newpost"></a>';
      $_G['forum_newpostanchor'] = 1;
    } else {
      $post['newpostanchor'] = '';
    }

    $post['lastpostanchor'] = ($ordertype != 1 && $_G['forum_numpost'] == $Thread['replies']) || ($ordertype == 1 && $_G['forum_numpost'] == $Thread['replies'] + 2) ? '<a name="lastpost"></a>' : '';

    if ($maxposition) {
      $post['number'] = $post['position'];
    }

    if (!empty($post['hotrecommended'])) {
      $post['number'] = -1;
    }

    if (!$Thread['special'] && $_G['setting']['threadfilternum'] && getstatus($post['status'], 11)) {
      $post['isWater'] = true;
      if ($_G['setting']['hidefilteredpost'] && !$Forum['noforumhidewater']) {
        $post['inblacklist'] = true;
      }
    } else {
      $_G['allblocked'] = false;
    }

    if ($post['inblacklist']) {
      $_G['blockedpids'][] = $post['pid'];
    }

    $_G['forum_postcount']++;

    $post['dbdateline'] = $post['dateline'];
    $post['dateline'] = dgmdate($post['dateline'], 'u', '9999', getglobal('setting/dateformat') . ' H:i:s');
    $post['groupid'] = $_G['cache']['usergroups'][$post['groupid']] ? $post['groupid'] : 7;

    if ($post['username']) {

      $_G['forum_onlineauthors'][$post['authorid']] = 0;
      $post['usernameenc'] = rawurlencode($post['username']);
      $post['readaccess'] = $_G['cache']['usergroups'][$post['groupid']]['readaccess'];
      if ($_G['cache']['usergroups'][$post['groupid']]['userstatusby'] == 1) {
        $post['authortitle'] = $_G['cache']['usergroups'][$post['groupid']]['grouptitle'];
        $post['stars'] = $_G['cache']['usergroups'][$post['groupid']]['stars'];
      }
      $post['upgradecredit'] = false;
      if ($_G['cache']['usergroups'][$post['groupid']]['type'] == 'member' && $_G['cache']['usergroups'][$post['groupid']]['creditslower'] != 999999999) {
        $post['upgradecredit'] = $_G['cache']['usergroups'][$post['groupid']]['creditslower'] - $post['credits'];
        $post['upgradeprogress'] = 100 - ceil($post['upgradecredit'] / ($_G['cache']['usergroups'][$post['groupid']]['creditslower'] - $_G['cache']['usergroups'][$post['groupid']]['creditshigher']) * 100);
        $post['upgradeprogress'] = min(max($post['upgradeprogress'], 2), 100);
      }

      $post['taobaoas'] = addslashes($post['taobao']);
      $post['regdate'] = dgmdate($post['regdate'], 'd');
      $post['lastdate'] = dgmdate($post['lastvisit'], 'd');

      $post['authoras'] = !$post['anonymous'] ? ' ' . addslashes($post['author']) : '';

      if ($post['medals']) {
        loadcache('medals');
        foreach ($post['medals'] = explode("\t", $post['medals']) as $key => $medalid) {
          list($medalid, $medalexpiration) = explode("|", $medalid);
          if (isset($_G['cache']['medals'][$medalid]) && (!$medalexpiration || $medalexpiration > TIMESTAMP)) {
            $post['medals'][$key] = $_G['cache']['medals'][$medalid];
            $post['medals'][$key]['medalid'] = $medalid;
            $_G['medal_list'][$medalid] = $_G['cache']['medals'][$medalid];
          } else {
            unset($post['medals'][$key]);
          }
        }
      }

      $post['avatar'] = avatar($post['authorid']);
      $post['groupicon'] = $post['avatar'] ? g_icon($post['groupid'], 1) : '';
      $post['banned'] = $post['status'] & 1;
      $post['warned'] = ($post['status'] & 2) >> 1;
    } else {
      if (!$post['authorid']) {
        $post['useip'] = substr($post['useip'], 0, strrpos($post['useip'], '.')) . '.x';
      }
    }
    $post['attachments'] = array();
    $post['imagelist'] = $post['attachlist'] = array();

    $forum_attachpids = [];
    if ($post['attachment']) {
      if ((!empty($_G['setting']['guestviewthumb']['flag']) && !$_G['uid']) || $_G['group']['allowgetattach'] || $_G['group']['allowgetimage']) {
        $forum_attachpids[] = $post['pid'];
        $post['attachment'] = 0;
        if (preg_match_all("/\[attach\](\d+)\[\/attach\]/i", $post['message'], $matchaids)) {
          $_G['forum_attachtags'][$post['pid']] = $matchaids[1];
        }
      } else {
        $post['message'] = preg_replace("/\[attach\](\d+)\[\/attach\]/i", '', $post['message']);
      }
    }

    if ($_G['setting']['ratelogrecord'] && $post['ratetimes']) {
      $_G['forum_cachepid'][$post['pid']] = $post['pid'];
    }
    if ($_G['setting']['commentnumber'] && ($post['first'] && $_G['setting']['commentfirstpost'] || !$post['first']) && $post['comment']) {
      $_G['forum_cachepid'][$post['pid']] = $post['pid'];
    }
    $post['allowcomment'] = $_G['setting']['commentnumber'] && is_array($_G['setting']['allowpostcomment']) && in_array(1, $_G['setting']['allowpostcomment']) && ($_G['setting']['commentpostself'] || $post['authorid'] != $_G['uid']) &&
      ($post['first'] && $_G['setting']['commentfirstpost'] && in_array($_G['group']['allowcommentpost'], array(1, 3)) ||
        (!$post['first'] && in_array($_G['group']['allowcommentpost'], array(2, 3))));
    $forum_allowbbcode = $Forum['allowbbcode'] ? -$post['groupid'] : 0;
    $post['signature'] = $post['usesig'] ? ($_G['setting']['sigviewcond'] ? (strlen($post['message']) > $_G['setting']['sigviewcond'] ? $post['signature'] : '') : $post['signature']) : '';
    $imgcontent = $post['first'] ? getstatus($Thread['status'], 15) : 0;
    if ($post['first']) {
      if (!defined('IN_MOBILE')) {
        $messageindex = false;
        if (strpos($post['message'], '[/index]') !== FALSE) {
          $post['message'] = preg_replace_callback(
            "/\s?\[index\](.+?)\[\/index\]\s?/is",
            function ($matches) use ($post) {
              return parseindex($matches[1], intval($post['pid']));
            },
            $post['message']
          );
          $messageindex = true;
          unset($_GET['threadindex']);
        }
        if (strpos($post['message'], '[page]') !== FALSE) {
          if ($_GET['cp'] != 'all') {
            $postbg = '';
            if (strpos($post['message'], '[/postbg]') !== FALSE) {
              preg_match("/\s?\[postbg\]\s*([^\[\<\r\n;'\"\?\(\)]+?)\s*\[\/postbg\]\s?/is", $post['message'], $r);
              $postbg = $r[0];
            }
            $messagearray = explode('[page]', $post['message']);
            $cp = max(intval($_GET['cp']), 1);
            $post['message'] = $messagearray[$cp - 1];
            if ($postbg && strpos($post['message'], '[/postbg]') === FALSE) {
              $post['message'] = $postbg . $post['message'];
            }
            unset($postbg);
          } else {
            $cp = 0;
            $post['message'] = preg_replace("/\s?\[page\]\s?/is", '', $post['message']);
          }
          if ($_GET['cp'] != 'all' && strpos($post['message'], '[/index]') === FALSE && empty($_GET['threadindex']) && !$messageindex) {
            $_G['forum_posthtml']['footer'][$post['pid']] .= '<div id="threadpage"></div><script type="text/javascript" reload="1">show_threadpage(' . $post['pid'] . ', ' . $cp . ', ' . count($messagearray) . ', ' . ($_GET['from'] == 'preview' ? '1' : '0') . ');</script>';
          }
        }
      }
    }
    if (!empty($_GET['threadindex'])) {
      $_G['forum_posthtml']['header'][$post['pid']] .= '<div id="threadindex"></div><script type="text/javascript" reload="1">show_threadindex(0, ' . ($_GET['from'] == 'preview' ? '1' : '0') . ');</script>';
    }
    if (!$imgcontent) {
      $post['message'] = discuzcode($post['message'], $post['smileyoff'], $post['bbcodeoff'], $post['htmlon'] & 1, $Forum['allowsmilies'], $forum_allowbbcode, ($Forum['allowimgcode'] && $_G['setting']['showimages'] ? 1 : 0), $Forum['allowhtml'], ($Forum['jammer'] && $post['authorid'] != $_G['uid'] ? 1 : 0), 0, $post['authorid'], $_G['cache']['usergroups'][$post['groupid']]['allowmediacode'] && $Forum['allowmediacode'], $post['pid'], getglobal('setting/lazyload'), $post['dbdateline'], $post['first']);
      if ($post['first']) {
        $_G['relatedlinks'] = '';
        $relatedtype = !$Thread['isgroup'] ? 'forum' : 'group';
        if (!getglobal('setting/relatedlinkstatus')) {
          $_G['relatedlinks'] = get_related_link($relatedtype);
        } else {
          $post['message'] = parse_related_link($post['message'], $relatedtype);
        }
        if (strpos($post['message'], '[/begin]') !== FALSE) {
          $post['message'] = preg_replace_callback(
            "/\[begin(=\s*([^\[\<\r\n]*?)\s*,(\d*),(\d*),(\d*),(\d*))?\]\s*([^\[\<\r\n]+?)\s*\[\/begin\]/is",
            function ($matches) {
              global $_G, $post;
              if (!intval($_G['cache']['usergroups'][$post['groupid']]['allowbegincode'])) {
                return '';
              }
              return $this->parsebegin($matches[2], $matches[7], $matches[3], $matches[4], $matches[5], $matches[6]);
            },
            $post['message']
          );
        }
      }
    }
    if (!$post['first']) {
      if (strpos($post['message'], '[page]') !== FALSE) {
        $post['message'] = preg_replace("/\s?\[page\]\s?/is", '', $post['message']);
      }
      if (strpos($post['message'], '[/index]') !== FALSE) {
        $post['message'] = preg_replace("/\s?\[index\](.+?)\[\/index\]\s?/is", '', $post['message']);
      }
      if (strpos($post['message'], '[/begin]') !== FALSE) {
        $post['message'] = preg_replace("/\[begin(=\s*([^\[\<\r\n]*?)\s*,(\d*),(\d*),(\d*),(\d*))?\]\s*([^\[\<\r\n]+?)\s*\[\/begin\]/is", '', $post['message']);
      }
    }
    if ($imgcontent) {
      $post['message'] = '<img id="threadimgcontent" src="./' . stringtopic('', $post['tid']) . '">';
    }
    $_G['forum_firstpid'] = intval($_G['forum_firstpid']);
    $post['numbercard'] = $this->viewthread_numbercard($post);
    $post['mobiletype'] = getstatus($post['status'], 4) ? base_convert(getstatus($post['status'], 10) . getstatus($post['status'], 9) . getstatus($post['status'], 8), 2, 10) : 0;

    $postlist = [
      $post['pid'] => &$post
    ];
    if ($forum_attachpids) {
      include_once libfile('function/attachment');
      if (is_array($threadsortshow) && !empty($threadsortshow['sortaids'])) {
        $skipaids = $threadsortshow['sortaids'];
      }
      $GLOBALS['aimgs'] = &$aimgs;
      parseattach($forum_attachpids, $_G['forum_attachtags'], $postlist, $skipaids);
    }
    return array_values($postlist)[0];
  }
  public function getThreadPosts($ThreadId, $page)
  {
    global $_G;
    if (!isset($_G['thread'])) {
      $archiveid = !empty($_GET['archiveid']) ? intval($_GET['archiveid']) : null;
      $_G['thread'] = $Thread = get_thread_by_tid($ThreadId, $archiveid);
    } else {
      $Thread = $_G['thread'];
    }
    if (!isset($_G['forum_thread'])) {
      $_G['forum_thread'] = &$_G['thread'];
    }

    if (empty($Thread)) {
      $ForumId = $tid = 0;
    } else {
      $ForumId = $Thread['fid'];
    }

    if (isset($_G['forum'])) {
      $Forum = $_G['forum'];
    } else if ($ForumId) {
      $_G['forum'] = $Forum = \C::t('forum_forum')->fetch_info_by_fid($ForumId);
    }

    $posttableid = $Thread['posttableid'];
    $orderType = empty($_GET['ordertype']) && getstatus($Thread['status'], 4) ? 1 : $_GET['ordertype'];

    $maxPosition = 0;
    if (!in_array($Thread['special'], array(2, 3, 5))) {
      $disablepos = \C::t('forum_threaddisablepos')->fetch($ThreadId) ? 1 : 0;
      if (!$disablepos) {
        if ($Thread['maxposition']) {
          $maxPosition = $Thread['maxposition'];
        } else {
          $maxPosition = \C::t('forum_post')->fetch_maxposition_by_tid($posttableid, $ThreadId);
          \C::t('forum_thread')->update($ThreadId, array('maxposition' => $maxPosition));
        }
      }
    }

    if (!empty($_GET['authorid']) || $_GET['checkrush']) {
      $maxPosition = 0;
    }

    $start = ($page - 1) * $_G['ppp'] + 1;
    $end = $start + $_G['ppp'];
    if ($orderType == 1) {
      $end = $maxPosition - ($page - 1) * $_G['ppp'] + ($page > 1 ? 2 : 1);
      $start = $end - $_G['ppp'] + ($page > 1 ? 0 : 1);
      $start = max(array(1, $start));
    }
    $incollection = getstatus($Thread['status'], 9);

    foreach (\C::t('forum_post')->fetch_all_by_tid_range_position($posttableid, $ThreadId, $start, $end, $maxPosition, $orderType) as $post) {
      $postarr[$post['position']] = $post;
    }

    $postlist = [];
    foreach ($postarr as $post) {
      if (!isset($postlist[$post['pid']])) {
        $postusers[$post['authorid']] = array();
        if ($post['first']) {
          if ($orderType == 1 && $page != 1) {
            continue;
          }
          $_G['forum_firstpid'] = $post['pid'];
          if ($Thread['price']) {
            $summary = str_replace(array("\r", "\n"), '', messagecutstr(strip_tags($Thread['freemessage']), 160));
          } else {
            $summary = str_replace(array("\r", "\n"), '', messagecutstr(strip_tags($post['message']), 160));
          }
          $post['summary'] = $summary;

          $tagarray_all = $posttag_array = array();
          $tagarray_all = explode("\t", $post['tags']);
          if ($tagarray_all) {
            foreach ($tagarray_all as $var) {
              if ($var) {
                $tag = explode(',', $var);
                $posttag_array[] = $tag;
                $tagnames[] = $tag[1];
              }
            }
          }
          $post['tags'] = $posttag_array;
          if ($post['tags']) {
            $post['relateitem'] = getrelateitem($post['tags'], $post['tid'], $_G['setting']['relatenum'], $_G['setting']['relatetime']);
          }
          if (!$Forum['disablecollect']) {
            if ($incollection) {
              $post['relatecollection'] = getrelatecollection($post['tid'], false, $post['releatcollectionnum'], $post['releatcollectionmore']);
              if ($_G['group']['allowcommentcollection'] && $_GET['ctid']) {
                $ctid = dintval($_GET['ctid']);
                $post['sourcecollection'] = \C::t('forum_collection')->fetch($ctid);
              }
            } else {
              $post['releatcollectionnum'] = 0;
            }
          }
        }
        $postlist[$post['pid']] = $post;
      }
    }

    if ($postusers) {
      $member_verify = $member_field_forum = $member_status = $member_count = $member_profile = $member_field_home = array();
      $uids = array_keys($postusers);
      $uids = array_filter($uids);

      $selfuids = $uids;
      if ($_G['setting']['threadblacklist'] && $_G['uid'] && !in_array($_G['uid'], $selfuids)) {
        $selfuids[] = $_G['uid'];
      }
      if (!(getglobal('setting/threadguestlite') && !$_G['uid'])) {
        if ($_G['setting']['verify']['enabled']) {
          $member_verify = \C::t('common_member_verify')->fetch_all($uids);
          foreach ($member_verify as $uid => $data) {
            foreach ($_G['setting']['verify'] as $vid => $verify) {
              if ($verify['available'] && $verify['showicon']) {
                if ($data['verify' . $vid] == 1) {
                  $member_verify[$uid]['verifyicon'][] = $vid;
                } elseif (!empty($verify['unverifyicon'])) {
                  $member_verify[$uid]['unverifyicon'][] = $vid;
                }
              }
            }
          }
        }
        $member_count = \C::t('common_member_count')->fetch_all($selfuids);
        $member_status = \C::t('common_member_status')->fetch_all($uids);
        $member_field_forum = \C::t('common_member_field_forum')->fetch_all($uids);
        $member_profile = \C::t('common_member_profile')->fetch_all($uids);
        $member_field_home = \C::t('common_member_field_home')->fetch_all($uids);
      }

      if ($_G['setting']['threadblacklist'] && $_G['uid'] && $member_count[$_G['uid']]['blacklist']) {
        $member_blackList = \C::t('home_blacklist')->fetch_all_by_uid_buid($_G['uid'], $uids);
      }

      foreach (\C::t('common_member')->fetch_all($uids) as $uid => $postuser) {
        $member_field_home[$uid]['privacy'] = empty($member_field_home[$uid]['privacy']) ? array() : dunserialize($member_field_home[$uid]['privacy']);
        $postuser['memberstatus'] = $postuser['status'];
        $postuser['authorinvisible'] = $member_status[$uid]['invisible'];
        $postuser['signature'] = $member_field_forum[$uid]['sightml'];
        unset($member_field_home[$uid]['privacy']['feed'], $member_field_home[$uid]['privacy']['view'], $postuser['status'], $member_status[$uid]['invisible'], $member_field_forum[$uid]['sightml']);
        $postusers[$uid] = array_merge((isset($member_verify[$uid]) ? (array)$member_verify[$uid] : array()), (array)$member_field_home[$uid], (array)$member_profile[$uid], (array)$member_count[$uid], (array)$member_status[$uid], (array)$member_field_forum[$uid], $postuser);
        if ($postusers[$uid]['regdate'] + $postusers[$uid]['oltime'] * 3600 > TIMESTAMP) {
          $postusers[$uid]['oltime'] = 0;
        }
        $postusers[$uid]['office'] = $postusers[$uid]['position'];
        $postusers[$uid]['inblacklist'] = !empty($member_blackList[$uid]);
        $postusers[$uid]['groupcolor'] = $_G['cache']['usergroups'][$postuser['groupid']]['color'];
        unset($postusers[$uid]['position']);
      }
      unset($member_field_forum, $member_status, $member_count, $member_profile, $member_field_home, $member_blackList);
      $_G['medal_list'] = array();
      foreach ($postlist as $pid => $post) {
        if (getstatus($post['status'], 6)) {
          $locationpids[] = $pid;
        }
        $postusers[$post['authorid']]['field_position'] = $postusers[$post['authorid']]['position'];
        $post = array_merge($postlist[$pid], (array)$postusers[$post['authorid']]);
        $postlist[$pid] = $this->procpost($Forum, $Thread, $post, $_G['member']['lastvisit'], $orderType, $maxPosition);
      }
    }

    return $postlist;
  }
}
