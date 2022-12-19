<?php

namespace gstudio_kernel\App\Api\Attachments;

use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\File;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

class DownloadAttachmentController extends AuthController
{
  public function data()
  {
    global $_G;
    @list($_GET['aid'], $_GET['k'], $_GET['t'], $_GET['uid'], $_GET['tableid'], $_GET['dir']) = daddslashes(explode('|', base64_decode($_GET['aid'])));
    $attachmentId = intval($_GET['aid']);
    $authKey = $_GET['k'];
    $time = $_GET['t'];
    $userId = intval($_GET['uid']);
    $tableId = intval($_GET['tableid']);
    $dir = $_GET['dir'];
    $requestmode = !empty($_GET['request']) && empty($userId);
    $authk = !$requestmode ? substr(md5($attachmentId . md5($_G['config']['security']['authkey']) . $time . $_GET['uid']), 0, 8) : md5($attachmentId . md5($_G['config']['security']['authkey']) . $time);

    if ($authKey != $authk) {
      if (!$requestmode) {
        showmessage('attachment_nonexistence');
      } else {
        exit;
      }
    }

    if (!empty($_GET['findpost']) && ($attach = \C::t('forum_attachment')->fetch($attachmentId))) {
      dheader('location: forum.php?mod=redirect&goto=findpost&pid=' . $attach['pid'] . '&ptid=' . $attach['tid']);
    }

    if ($userId != $_G['uid'] && $userId) {
      $member = getuserbyuid($userId);
      loadcache('usergroup_' . $member['groupid']);
      $_G['group'] = $_G['cache']['usergroup_' . $member['groupid']];
      $_G['group']['grouptitle'] = $_G['cache']['usergroup_' . $_G['groupid']]['grouptitle'];
      $_G['group']['color'] = $_G['cache']['usergroup_' . $_G['groupid']]['color'];
    }

    $tableIdString = 'aid:' . $attachmentId;


    if ($_G['setting']['attachexpire']) {
      if (TIMESTAMP - $time > $_G['setting']['attachexpire'] * 3600) {
        $aid = intval($attachmentId);
        if ($attach = \C::t('forum_attachment_n')->fetch($tableIdString, $attachmentId)) {
          if ($attach['isimage']) {
            dheader('location: ' . $_G['siteurl'] . 'static/image/common/none.gif');
          } else {
            if (!$requestmode) {
              showmessage('attachment_expired', '', array('aid' => aidencode($attachmentId, 0, $attach['tid']), 'pid' => $attach['pid'], 'tid' => $attach['tid']));
            } else {
              exit;
            }
          }
        } else {
          if (!$requestmode) {
            showmessage('attachment_nonexistence');
          } else {
            exit;
          }
        }
      }
    }

    $readmod = getglobal('config/download/readmod');
    $readmod = $readmod > 0 && $readmod < 5 ? $readmod : 2;

    $refererhost = parse_url($_SERVER['HTTP_REFERER']);
    $serverhost = $_SERVER['HTTP_HOST'];
    if (($pos = strpos($serverhost, ':')) !== FALSE) {
      $serverhost = substr($serverhost, 0, $pos);
    }

    if (!$requestmode && $_G['setting']['attachrefcheck'] && $_SERVER['HTTP_REFERER'] && !($refererhost['host'] == $serverhost)) {
      showmessage('attachment_referer_invalid', NULL);
    }

    \periodscheck('attachbanperiods');

    loadcache('threadtableids');
    $threadtableids = !empty($_G['cache']['threadtableids']) ? $_G['cache']['threadtableids'] : array();
    if (!in_array(0, $threadtableids)) {
      $threadtableids = array_merge(array(0), $threadtableids);
    }
    $archiveid = in_array($_GET['archiveid'], $threadtableids) ? intval($_GET['archiveid']) : 0;

    $attachexists = FALSE;
    if (!empty($attachmentId) && is_numeric($attachmentId)) {
      $attach = \C::t('forum_attachment_n')->fetch($tableIdString, $attachmentId);
      if ($attach['tid']) {
        $thread = \C::t('forum_thread')->fetch_by_tid_displayorder($attach['tid'], 0, '>=', null, $archiveid);
        if ($_G['uid'] && $attach['uid'] != $_G['uid']) {
          if ($attach) {
            $attachpost = \C::t('forum_post')->fetch($thread['posttableid'], $attach['pid'], false);
            $attach['invisible'] = $attachpost['invisible'];
            unset($attachpost);
          }
          if ($attach && $attach['invisible'] == 0) {
            $thread && $attachexists = TRUE;
          }
        } else {
          $attachexists = TRUE;
        }
      } else {
        $attachexists = TRUE;
      }
    }

    if (!$attachexists) {
      if (!$requestmode) {
        showmessage('attachment_nonexistence');
      } else {
        exit;
      }
    }

    if (!$requestmode && $attach['tid'] && $thread) {
      $forum = \C::t('forum_forumfield')->fetch_info_for_attach($thread['fid'], $_G['uid']);

      $_GET['fid'] = $forum['fid'];

      if ($attach['isimage']) {
        $allowgetattach = !empty($forum['allowgetimage']) || (($_G['group']['allowgetimage'] || $_G['uid'] == $attach['uid']) && !$forum['getattachperm']) || forumperm($forum['getattachperm']);
      } else {
        $allowgetattach = !empty($forum['allowgetattach']) || (($_G['group']['allowgetattach']  || $_G['uid'] == $attach['uid']) && !$forum['getattachperm']) || forumperm($forum['getattachperm']);
      }
      if ($allowgetattach && ($attach['readperm'] && $attach['readperm'] > $_G['group']['readaccess']) && $_G['adminid'] <= 0 && !($_G['uid'] && $_G['uid'] == $attach['uid'])) {
        showmessage('attachment_forum_nopermission', NULL, array(), array('login' => 1));
      }

      $ismoderator = in_array($_G['adminid'], array(1, 2)) ? 1 : ($_G['adminid'] == 3 ? \C::t('forum_moderator')->fetch_uid_by_tid($attach['tid'], $_G['uid'], $archiveid) : 0);

      $ispaid = FALSE;
      $exemptvalue = $ismoderator ? 128 : 16;
      if (!$thread['special'] && $thread['price'] > 0 && (!$_G['uid'] || ($_G['uid'] != $attach['uid'] && !($_G['group']['exempt'] & $exemptvalue)))) {
        if (!$_G['uid'] || $_G['uid'] && !($ispaid = \C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BTC', $attach['tid']))) {
          showmessage('attachment_payto', 'forum.php?mod=viewthread&tid=' . $attach['tid']);
        }
      }

      $exemptvalue = $ismoderator ? 64 : 8;
      if ($attach['price'] && (!$_G['uid'] || ($_G['uid'] != $attach['uid'] && !($_G['group']['exempt'] & $exemptvalue)))) {
        $payrequired = $_G['uid'] ? !\C::t('common_credit_log')->count_by_uid_operation_relatedid($_G['uid'], 'BAC', $attach['aid']) : 1;
        $payrequired && showmessage('attachement_payto_attach', 'forum.php?mod=misc&action=attachpay&aid=' . $attach['aid'] . '&tid=' . $attach['tid']);
      }
    }

    $isimage = $attach['isimage'];
    $_G['setting']['ftp']['hideurl'] = $_G['setting']['ftp']['hideurl'] || ($isimage && !empty($_GET['noupdate']) && $_G['setting']['attachimgpost'] && strtolower(substr($_G['setting']['ftp']['attachurl'], 0, 3)) == 'ftp');

    if (empty($_GET['nothumb']) && $attach['isimage'] && $attach['thumb']) {
      $db = \DB::object();
      $db->close();
      !$_G['config']['output']['gzip'] && ob_end_clean();
      dheader('Content-Disposition: inline; filename=' . getimgthumbname($attach['filename']));
      dheader('Content-Type: image/pjpeg');
      if ($attach['remote']) {
        $_G['setting']['ftp']['hideurl'] ? getremotefile(getimgthumbname($attach['attachment']), $dir) : dheader('location:' . $_G['setting']['ftp']['attachurl'] . $dir . '/' . getimgthumbname($attach['attachment']));
      } else {
        getlocalfile($_G['setting']['attachdir'] . '/' . $dir . '/' . getimgthumbname($attach['attachment']));
      }
      exit();
    }
    $filename = File::genPath(Config::get("attachmentPath"), $dir, $attach['attachment']);
    if (!$attach['remote'] && !is_readable($filename)) {
      if (!$requestmode) {
        showmessage('attachment_nonexistence');
      } else {
        exit;
      }
    }


    if (!$requestmode && $forum) {
      if (!$ispaid && !$forum['allowgetattach']) {
        if (!$forum['getattachperm'] && !$allowgetattach) {
          showmessage('getattachperm_none_nopermission', NULL, array(), array('login' => 1));
        } elseif (($forum['getattachperm'] && !forumperm($forum['getattachperm'])) || ($forum['viewperm'] && !forumperm($forum['viewperm']))) {
          showmessagenoperm('getattachperm', $forum['fid']);
        }
      }

      $exemptvalue = $ismoderator ? 32 : 4;
      if (!$isimage && !($_G['group']['exempt'] & $exemptvalue)) {
        $creditlog = updatecreditbyaction('getattach', $_G['uid'], array(), '', 1, 0, $thread['fid']);
        if ($creditlog['updatecredit']) {
          if ($_G['uid']) {
            $k = $_GET['ck'];
            $t = $_GET['t'];
            if (empty($k) || empty($t) || $k != substr(md5($aid . $t . md5($_G['config']['security']['authkey'])), 0, 8) || TIMESTAMP - $t > 3600) {
              dheader('location: forum.php?mod=misc&action=attachcredit&aid=' . $attach['aid'] . '&formhash=' . FORMHASH);
              exit();
            }
          } else {
            showmessage('attachment_forum_nopermission', NULL, array(), array('login' => 1));
          }
        }
      }
    }


    $range = 0;
    if ($readmod == 4 && !empty($_SERVER['HTTP_RANGE'])) {
      list($range) = explode('-', (str_replace('bytes=', '', $_SERVER['HTTP_RANGE'])));
    }

    if (!$requestmode && !$range && empty($_GET['noupdate'])) {
      if ($_G['setting']['delayviewcount']) {
        $_G['forum_logfile'] = './data/cache/forum_attachviews_' . intval(getglobal('config/server/id')) . '.log';
        if (substr(TIMESTAMP, -1) == '0') {
          $this->attachmentUpdateViews($_G['forum_logfile']);
        }

        if (@$fp = fopen(DISCUZ_ROOT . $_G['forum_logfile'], 'a')) {
          fwrite($fp, "$aid\n");
          fclose($fp);
        } elseif ($_G['adminid'] == 1) {
          showmessage('view_log_invalid', '', array('logfile' => $_G['forum_logfile']));
        }
      } else {
        \C::t('forum_attachment')->update_download($aid);
      }
    }

    $db = \DB::object();
    $db->close();
    !$_G['config']['output']['gzip'] && ob_end_clean();

    if ($attach['remote'] && !$_G['setting']['ftp']['hideurl'] && $isimage) {
      dheader('location:' . $_G['setting']['ftp']['attachurl'] . $dir . '/' . $attach['attachment']);
    }

    $filesize = !$attach['remote'] ? filesize($filename) : $attach['filesize'];
    $attach['filename'] = '"' . (strtolower(CHARSET) == 'utf-8' && strexists($_SERVER['HTTP_USER_AGENT'], 'MSIE') ? urlencode($attach['filename']) : $attach['filename']) . '"';

    dheader('Date: ' . gmdate('D, d M Y H:i:s', $attach['dateline']) . ' GMT');
    dheader('Last-Modified: ' . gmdate('D, d M Y H:i:s', $attach['dateline']) . ' GMT');
    dheader('Content-Encoding: none');

    if ($isimage && !empty($_GET['noupdate']) || !empty($_GET['request'])) {
      dheader('Content-Disposition: inline; filename=' . $attach['filename']);
    } else {
      dheader('Content-Disposition: attachment; filename=' . $attach['filename']);
    }
    if ($isimage) {
      dheader('Content-Type: image');
    } else {
      dheader('Content-Type: application/octet-stream');
    }

    dheader('Content-Length: ' . $filesize);

    $xsendfile = getglobal('config/download/xsendfile');
    if (!empty($xsendfile)) {
      $type = intval($xsendfile['type']);
      if ($isimage) {
        $type = 0;
      }
      $cmd = '';
      switch ($type) {
        case 1:
          $cmd = 'X-Accel-Redirect';
          $url = $xsendfile['dir'] . $attach['attachment'];
          break;
        case 2:
          $cmd = $_SERVER['SERVER_SOFTWARE'] < 'lighttpd/1.5' ? 'X-LIGHTTPD-send-file' : 'X-Sendfile';
          $url = $filename;
          break;
        case 3:
          $cmd = 'X-Sendfile';
          $url = $filename;
          break;
      }
      if ($cmd) {
        dheader("$cmd: $url");
        exit();
      }
    }

    if ($readmod == 4) {
      dheader('Accept-Ranges: bytes');
      if (!empty($_SERVER['HTTP_RANGE'])) {
        $rangesize = ($filesize - $range) > 0 ?  ($filesize - $range) : 0;
        dheader('Content-Length: ' . $rangesize);
        dheader('HTTP/1.1 206 Partial Content');
        dheader('Content-Range: bytes=' . $range . '-' . ($filesize - 1) . '/' . ($filesize));
      }
    }

    $attach['remote'] ? $this->getRemoteFile($attach['attachment'], $dir) : $this->getLocalFile($filename, $readmod, $range);
  }
  function getLocalFile($filename, $readmod = 2, $range = 0)
  {
    if ($readmod == 1 || $readmod == 3 || $readmod == 4) {
      if ($fp = @fopen($filename, 'rb')) {
        @fseek($fp, $range);
        if (function_exists('fpassthru') && ($readmod == 3 || $readmod == 4)) {
          @fpassthru($fp);
        } else {
          echo @fread($fp, filesize($filename));
        }
      }
      @fclose($fp);
    } else {
      @readfile($filename);
    }
    @flush();
    @ob_flush();
  }
  function getRemoteFile($file, $dir)
  {
    global $_G;
    @set_time_limit(0);
    if (!@readfile($_G['setting']['ftp']['attachurl'] . $dir . '/' . $file)) {
      $ftp = ftpcmd('object');
      $tmpfile = @tempnam($_G['setting']['attachdir'], '');
      if ($ftp->ftp_get($tmpfile, $dir . '/' . $file, FTP_BINARY)) {
        @readfile($tmpfile);
        @unlink($tmpfile);
      } else {
        @unlink($tmpfile);
        return FALSE;
      }
    }
    return TRUE;
  }
  function attachmentUpdateViews($logfile)
  {
    $viewlog = $viewarray = array();
    $newlog = \DISCUZ_ROOT . $logfile . random(6);
    if (@rename(\DISCUZ_ROOT . $logfile, $newlog)) {
      $viewlog = file($newlog);
      unlink($newlog);
      if (is_array($viewlog) && !empty($viewlog)) {
        $viewlog = array_count_values($viewlog);
        foreach ($viewlog as $id => $views) {
          if ($id > 0) {
            $viewarray[$views][] = intval($id);
          }
        }
        foreach ($viewarray as $views => $ids) {
          \C::t('forum_attachment')->update_download($ids, $views);
        }
      }
    }
  }
}
