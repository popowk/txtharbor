<?php
/* TxtHarbor-build:1.5.6 */
/** Single stable-link view and authorized edit/update entry point. */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require __DIR__.'/config.php';
require_once __DIR__.'/inc/admin_auth.php';
require_once __DIR__.'/inc/entry_view.php';
$web=text_v2_config_utf8($web);
if ((int)$web['site_close']===1) {header('Location: 0.php');exit;}
define('ADMIN_POWER',th_admin_authorized());
try {th_access_session();} catch(Throwable $ex) {
    error_log('TxtHarbor unlock session: '.$ex->getMessage());
    http_response_code(503);exit('密码访问会话不可用：请管理员检查 PHP Session 存储权限。');
}
try {$id=isset($_GET['id']) && is_string($_GET['id'])?$_GET['id']:''; $entry=th_entry_find($id);}catch(Throwable $ex){http_response_code(503);exit('留言数据暂不可用，请管理员检查权限元数据。');}
if (!$entry || (!ADMIN_POWER && th_entry_expired($entry))) {http_response_code(404);exit('留言不存在或已过期');}
$message='';$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    // Verify the entered password before including it in a share link. Passwords are never stored in plaintext.
    if (($_POST['action'] ?? '') === 'share_recover') {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
        if (!th_access_csrf_valid($_POST['csrf']??null)) {
            http_response_code(403);
            echo json_encode(array('ok'=>false,'code'=>'csrf','message'=>'验证已过期，请刷新页面'),JSON_UNESCAPED_UNICODE);
        } elseif ($entry['pending'] && !ADMIN_POWER || th_entry_expired($entry) && !ADMIN_POWER) {
            http_response_code(403);
            echo json_encode(array('ok'=>false,'code'=>'forbidden','message'=>'留言不可访问'),JSON_UNESCAPED_UNICODE);
        } else {
            $secret=th_share_secret($entry);
            if ($entry['password_hash'] === '' || is_string($secret)) {
                $relative=th_entry_share($entry);
                if ($entry['password_hash'] !== '') $relative.='#key='.rawurlencode($secret);
                echo json_encode(array('ok'=>true,'url'=>$relative),JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                http_response_code(409);
                echo json_encode(array('ok'=>false,'code'=>'needs_password',
                    'message'=>'当前浏览器没有此留言的分享凭据，请输入一次访问密码'),JSON_UNESCAPED_UNICODE);
            }
        }
        exit;
    }
    if (($_POST['action'] ?? '') === 'share_verify') {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
        $ok=false;$reason='访问密码不正确';$status=403;
        try {
            if (!th_access_csrf_valid($_POST['csrf']??null)) $reason='验证已过期，请刷新页面';
            elseif ($entry['password_hash']==='') {$ok=true;$status=200;$reason='ok';}
            elseif (!th_access_rate($entry['id'])) {$status=429;$reason='尝试次数过多，请 15 分钟后重试';}
            elseif (!isset($_POST['password']) || !is_string($_POST['password']) ||
                !password_verify($_POST['password'], $entry['password_hash'])) {
                th_access_rate($entry['id'], false);
            } else { th_access_rate($entry['id'], true); th_share_remember($entry['id'],$_POST['password'],$entry['password_hash']); $ok=true;$status=200;$reason='ok'; }
        } catch (Throwable $ex) {$status=503;$reason='密码验证暂不可用，请联系管理员检查权限';}
        http_response_code($status);
        echo json_encode(array('ok'=>$ok,'message'=>$reason),JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!th_access_csrf_valid($_POST['csrf']??null)) {$error='请求已过期，请刷新页面后重试';http_response_code(403);}
    elseif (isset($_POST['action']) && $_POST['action']==='unlock') {
        if ($entry['password_hash']==='') {$message='此留言不需要密码';}
        else {
            try {
                if (!th_access_rate($entry['id'])) {$error='错误次数过多，请 15 分钟后再试';http_response_code(429);}
                elseif (!isset($_POST['password']) || !is_string($_POST['password']) || !password_verify($_POST['password'],$entry['password_hash'])) {
                    th_access_rate($entry['id'],false);$error='访问密码不正确';http_response_code(403);
                } else {
                    th_access_rate($entry['id'],true);
                    th_access_session();
                    $_SESSION['th_unlock'][$entry['id']]=hash('sha256',$entry['password_hash']);
                    th_share_remember($entry['id'],$_POST['password'],$entry['password_hash']);
                    session_write_close();
                    header('Location: '.th_entry_share($entry),true,303);exit;
                }
            } catch (Throwable $ex) {$error='密码验证暂不可用，请检查服务器临时目录写权限';http_response_code(503);}
        }
    }
    elseif (ADMIN_POWER && ($_POST['action']??'')==='toggle_pin') {
        try {
            th_metadata_update(function ($all) use ($entry) {
                $record=isset($all[$entry['id']]) && is_array($all[$entry['id']])?$all[$entry['id']]:array();
                $record['pinned']=!$entry['pinned'];
                $all[$entry['id']]=$record;
                return $all;
            });
            header('Location: entry.php?id='.$entry['id'],true,303);exit;
        }catch(Throwable $e){$error='置顶操作失败：'.$e->getMessage();http_response_code(500);}
    }
    elseif (ADMIN_POWER && ($_POST['action']??'')==='save') {
        $subject=$_POST['subject']??null; $body=$_POST['content']??null;
        if(!is_string($subject)||!is_string($body))$error='标题或正文格式不正确';
        elseif($subject!=='' && ($problem=text_v2_problem($subject,SUBJ_SIZE,'标题'))!=='')$error=$problem;
        elseif(($problem=text_v2_problem($body,WORD_SIZE,'正文'))!=='')$error=$problem;
        else {
            $expiry=$_POST['expiry']??'never'; $expiration=0;
            if($expiry==='1d')$expiration=time()+86400;
            elseif($expiry==='7d')$expiration=time()+7*86400;
            elseif($expiry==='30d')$expiration=time()+30*86400;
            elseif($expiry==='custom') {
                $selected=th_expiry_from_post($_POST);
                if ($selected===null) $error='请选择有效的未来日期、小时和分钟（北京时间）';
                else $expiration=$selected;
            } elseif($expiry==='keep')$expiration=$entry['expires_at'];
            elseif($expiry!=='never')$error='有效期选项不正确';
            $mode=$_POST['password_mode']??'keep';
            if(!in_array($mode,array('keep','set','remove'),true))$error='密码模式不正确';
            $new_password=$_POST['new_password']??'';
            if($mode==='set' && (!is_string($new_password)||!text_v2_valid_utf8($new_password)||text_v2_length($new_password)<4||strlen($new_password)>128))$error='访问密码至少 4 个字符，不超过 128 字节';
            if($error==='') {
                try {
                    $newhash=$mode==='set'?password_hash($new_password,PASSWORD_DEFAULT):null;
                    if ($mode==='set' && (!is_string($newhash) || $newhash==='')) throw new RuntimeException('无法生成安全密码哈希');
                    th_metadata_update(function($all)use($entry,$subject,$body,$expiration,$mode,$newhash){
                        $record=isset($all[$entry['id']])&&is_array($all[$entry['id']])?$all[$entry['id']]:array();
                        $record['subject']=$subject;$record['content']=$body;
                        $record['edited_at']=time();$record['pinned']=!empty($_POST['pinned']);$record['expires_at']=$expiration;
                        if($mode==='set')$record['password_hash']=$newhash;
                        elseif($mode==='remove')unset($record['password_hash']);
                        if(!isset($record['media']))$record['media']=array(); // future cloud references only
                        $all[$entry['id']]=$record;return $all;
                    });
                    if ($mode==='set' && is_string($newhash) && $newhash!=='') {
                        try { th_share_remember($entry['id'],$new_password,$newhash); }
                        catch(Throwable $e) { error_log('TxtHarbor share session: '.$e->getMessage()); }
                    }
                    if (isset($_POST['ajax']) && $_POST['ajax']==='1') {
                        header('Content-Type: application/json; charset=UTF-8');
                        header('Cache-Control: no-store, private');
                        echo json_encode(array('ok'=>true,'url'=>'entry.php?id='.$entry['id'].'&saved=1',
                            'share_url'=>'entry.php?id='.$entry['id']),JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    header('Location: entry.php?id='.$entry['id'].'&saved=1',true,303);exit;
                }catch(Throwable $e){$error='保存失败：'.$e->getMessage();http_response_code(500);}
            }
        }
    } else {$error='没有权限执行此操作';http_response_code(403);}
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['ajax'] ?? '')==='1' && $error !== '') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, private');
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(array('ok'=>false,'message'=>$error),JSON_UNESCAPED_UNICODE);
    exit;
}
$entry=th_entry_find($entry['id']);
$locked=!th_entry_unlocked($entry);
$is_edit=ADMIN_POWER && isset($_GET['edit']);
$csrf=th_access_csrf();
$update_active=isset($_GET['saved'])&&!empty($_GET['saved']);
?><!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><title>留言详情 · <?php echo text_v2_html($web['sitename']); ?></title>
<script src="inc/theme.js?<?php echo @filemtime('inc/theme.js'); ?>"></script><link rel="stylesheet" href="inc/style.css?<?php echo @filemtime('inc/style.css'); ?>"><link rel="stylesheet" href="inc/modern.css?<?php echo @filemtime('inc/modern.css'); ?>"><link rel="stylesheet" href="inc/theme.css?<?php echo @filemtime('inc/theme.css'); ?>"><link rel="stylesheet" href="inc/entries.css?<?php echo @filemtime('inc/entries.css'); ?>"><script src="inc/main.js?<?php echo @filemtime('inc/main.js'); ?>"></script></head>
<body class="app-page archive-page"><header id="top"><div class="body top-inner"><a id="logo" href="./"><span class="brand-symbol">T<span>_</span></span><span class="brand-name"><?php echo text_v2_html($web['sitename']); ?></span></a><nav class="site-nav"><a href="./">浏览内容</a><a href="write.php">发布留言</a><?php if(ADMIN_POWER): ?><a href="run.php?act=logout">退出管理员</a><?php endif; ?></nav><div class="theme-control"><button type="button" class="theme-toggle" id="theme-toggle"><span class="theme-icon">◐</span><span id="theme-label">自动</span></button><button type="button" class="theme-menu-toggle" id="theme-menu-toggle" aria-expanded="false">⌄</button><div class="theme-menu" id="theme-menu" hidden><button type="button" data-theme-choice="auto">自动</button><button type="button" data-theme-choice="light">浅色</button><button type="button" data-theme-choice="dark">深色</button></div></div></div></header>
<main class="page-main"><section class="intro body"><h1><?php echo $is_edit?'编辑留言':'留言详情'; ?></h1></section>
<?php if($error!==''): ?><div class="body entry-alert"><?php echo text_v2_html($error); ?></div><?php elseif($update_active): ?><div class="body entry-success">修改已保存</div><?php endif; ?>
<?php if($is_edit): ?>
<form method="post" class="body rounded entry-settings" action="entry.php?id=<?php echo $entry['id']; ?>&amp;edit=1" accept-charset="UTF-8" onsubmit="return main.saveEntrySettings(event,this);">
<input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?php echo text_v2_html($csrf); ?>">
<label>标题<input name="subject" class="modern-input" maxlength="<?php echo SUBJ_SIZE; ?>" value="<?php echo text_v2_html($entry['subject']); ?>"></label>
<label>正文（保留 Markdown 原文）<textarea name="content" rows="15" class="modern-input" required><?php echo text_v2_html($entry['content']); ?></textarea></label>
<label class="entry-check"><input type="checkbox" name="pinned" value="1" <?php echo $entry['pinned']?'checked':''; ?>> 置顶留言</label>
<label>有效期<select name="expiry" id="entry-expiry-option"><option value="keep" selected>保持当前设置<?php if($entry['expires_at']>0) echo '（当前：'.(new DateTimeImmutable('@'.$entry['expires_at']))->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d H:i').'）'; ?></option><option value="never">永久有效</option><option value="1d">自保存起 1 天</option><option value="7d">自保存起 7 天</option><option value="30d">自保存起 30 天</option><option value="custom">自定义到期时间</option></select></label>
<?php $th_edit_time=(new DateTimeImmutable('@'.($entry['expires_at']>0?$entry['expires_at']:time()+86400)))->setTimezone(new DateTimeZone('Asia/Shanghai')); ?>
<div class="expiry-picker" id="entry-expiry-custom" hidden>
  <span class="expiry-picker-title">自定义到期时间 <small>北京时间 UTC+8</small></span>
  <div class="expiry-picker-fields">
    <label for="entry-expiry-date">日期<input class="modern-input" id="entry-expiry-date" type="date" name="expiry_date" value="<?php echo $th_edit_time->format('Y-m-d'); ?>" disabled></label>
    <label for="entry-expiry-hour">小时（0–23）<select id="entry-expiry-hour" name="expiry_hour" disabled><?php for($i=0;$i<24;$i++): $v=sprintf('%02d',$i); ?><option value="<?php echo $v; ?>" <?php echo $th_edit_time->format('H')===$v?'selected':''; ?>><?php echo $v; ?> 时</option><?php endfor; ?></select></label>
    <label for="entry-expiry-minute">分钟（0–59）<select id="entry-expiry-minute" name="expiry_minute" disabled><?php for($i=0;$i<60;$i++): $v=sprintf('%02d',$i); ?><option value="<?php echo $v; ?>" <?php echo $th_edit_time->format('i')===$v?'selected':''; ?>><?php echo $v; ?> 分</option><?php endfor; ?></select></label>
  </div>
  <small>到期后访客无法查看或通过原链接访问，留言不会自动删除。</small>
</div>
<label>访问密码<select name="password_mode"><option value="keep">保持原密码</option><option value="set">设置或更换密码</option><option value="remove">移除密码保护</option></select></label>
<label>新密码<div class="password-tools"><input id="entry-new-password" name="new_password" type="password" autocomplete="new-password" minlength="4" class="modern-input" placeholder="至少 4 位，推荐更长密码"><button type="button" class="tool-button" onclick="main.generateAccessPassword('entry-new-password',this)">随机生成</button></div></label>
<div class="editor-footer"><a href="entry.php?id=<?php echo $entry['id']; ?>">取消</a><button class="send2 primary-button" type="submit">保存修改</button></div></form>
<?php else: ?>
<article class="body rounded message-card entry-detail"><div class="texts"><div class="entry-topline"><span><?php echo date('Y-m-d H:i',$entry['created_at']); ?><?php echo $entry['pinned']?' · 📌 已置顶':''; ?><?php echo th_entry_expired($entry)?' · 已过期':''; ?></span><span><a href="./">返回列表</a><?php if(ADMIN_POWER): ?>　<a href="entry.php?id=<?php echo $entry['id']; ?>&amp;edit=1">编辑与设置</a><?php endif; ?></span></div>
<?php if (ADMIN_POWER): ?><form method="post" action="entry.php?id=<?php echo $entry['id']; ?>" class="entry-pin-form"><input type="hidden" name="action" value="toggle_pin"><input type="hidden" name="csrf" value="<?php echo text_v2_html($csrf); ?>"><button type="submit" class="entry-action"><?php echo $entry['pinned'] ? '取消置顶' : '📌 一键置顶'; ?></button></form><?php endif; ?>
<?php if($locked): ?>
<section class="entry-locked" aria-labelledby="entry-locked-title">
  <div class="entry-lock-mark" aria-hidden="true">🔒</div>
  <h2 id="entry-locked-title">这条留言已加密保护</h2>
  <p class="entry-lock-intro">输入访问密码后可查看正文、复制 Markdown 原文。</p>
  <form class="entry-unlock-form" method="post" action="entry.php?id=<?php echo $entry['id']; ?>">
    <input type="hidden" name="action" value="unlock"><input type="hidden" name="csrf" value="<?php echo text_v2_html($csrf); ?>">
    <label class="entry-unlock-label" for="entry-unlock-password">访问密码</label>
    <input id="entry-unlock-password" class="modern-input entry-unlock-input" type="password" name="password" required autocomplete="off" placeholder="请输入访问密码">
    <p class="entry-unlock-tip">带密码的分享链接会自动填入密码。</p>
    <button type="submit" class="send2 primary-button entry-unlock-submit">解锁并查看留言</button>
  </form>
</section>
<?php elseif($entry['pending']&&!ADMIN_POWER): ?><p>内容审核中</p>
<?php else: ?><div class="text"><?php echo th_entry_body_html($entry,ADMIN_POWER); ?></div><?php if($entry['reply'] !== ''): ?><div class="ad_reply"><strong>管理员回复</strong><div class="entry-raw"><?php echo text_v2_html($entry['reply']); ?></div></div><?php endif; ?><div class="entry-actions"><?php echo th_entry_controls($entry,ADMIN_POWER); ?></div><?php endif; ?>
</div></article><?php endif; ?>
</main>
<script>
(function(){
  // Hash fragments are never sent to PHP or HTTP access logs.
  var hash = window.location.hash;
  if (!hash || hash.slice(1,5) !== 'key=') return;
  try {
    var key=new URLSearchParams(hash.slice(1)).get('key');
    var field=document.getElementById('entry-unlock-password');
    if (key !== null) {
      if (field) {field.value=key;field.focus();}
      if (window.history && window.history.replaceState) {
        window.history.replaceState(null,'',window.location.pathname+window.location.search);
      }
    }
  } catch(e) { /* malformed share fragment: allow manual password entry */ }
})();
  <?php if($is_edit): ?>
  main.setupExpiryPicker('entry-expiry-option','entry-expiry-custom');
  <?php endif; ?>
</script></body></html>
