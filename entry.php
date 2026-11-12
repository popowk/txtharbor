<?php
/** Single stable-link view and authorized edit/update entry point. */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
require __DIR__.'/config.php';
require_once __DIR__.'/inc/entry_view.php';
$web=text_v2_config_utf8($web);
if ((int)$web['site_close']===1) {header('Location: 0.php');exit;}
define('ADMIN_POWER',confirm_power());
try {$id=isset($_GET['id']) && is_string($_GET['id'])?$_GET['id']:''; $entry=th_entry_find($id);}catch(Throwable $ex){http_response_code(503);exit('留言数据暂不可用，请管理员检查权限元数据。');}
if (!$entry || (!ADMIN_POWER && th_entry_expired($entry))) {http_response_code(404);exit('留言不存在或已过期');}
$message='';$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!th_access_csrf_valid($_POST['csrf']??null)) {$error='请求已过期，请刷新页面后重试';http_response_code(403);}
    elseif (isset($_POST['action']) && $_POST['action']==='unlock') {
        if ($entry['password_hash']==='') {$message='此留言不需要密码';}
        elseif (!th_access_rate($entry['id'])){$error='错误次数过多，请 15 分钟后再试';http_response_code(429);}
        elseif (!isset($_POST['password']) || !is_string($_POST['password']) || !password_verify($_POST['password'],$entry['password_hash'])) {
            th_access_rate($entry['id'],false);$error='访问密码不正确';http_response_code(403);
        } elseif(!th_access_rate($entry['id'],true)){$error='权限验证暂不可用，请稍后重试';http_response_code(503);}
        else {th_access_session();$_SESSION['th_unlock'][$entry['id']]=hash('sha256',$entry['password_hash']);header('Location: '.th_entry_share($entry),true,303);exit;}
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
                $value=$_POST['expiry_custom']??'';
                $dt=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone('Asia/Shanghai')):false;
                if(!$dt || $dt->getTimestamp()<=time())$error='自定义过期时间必须晚于现在（北京时间）';
                else $expiration=$dt->getTimestamp();
            } elseif($expiry==='keep')$expiration=$entry['expires_at'];
            elseif($expiry!=='never')$error='有效期选项不正确';
            $mode=$_POST['password_mode']??'keep';
            if(!in_array($mode,array('keep','set','remove'),true))$error='密码模式不正确';
            $new_password=$_POST['new_password']??'';
            if($mode==='set' && (!is_string($new_password)||strlen($new_password)<8||strlen($new_password)>128))$error='访问密码至少 8 个字节，不超过 128 个字节';
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
                    header('Location: entry.php?id='.$entry['id'].'&saved=1',true,303);exit;
                }catch(Throwable $e){$error='保存失败：'.$e->getMessage();http_response_code(500);}
            }
        }
    } else {$error='没有权限执行此操作';http_response_code(403);}
}
$entry=th_entry_find($entry['id']);
$locked=!th_entry_unlocked($entry);
$is_edit=ADMIN_POWER && isset($_GET['edit']);
$csrf=th_access_csrf();
$update_active=isset($_GET['saved'])&&!empty($_GET['saved']);
?><!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><title>留言详情 · <?php echo text_v2_html($web['sitename']); ?></title>
<script src="inc/theme.js?<?php echo @filemtime('inc/theme.js'); ?>"></script><link rel="stylesheet" href="inc/style.css?<?php echo @filemtime('inc/style.css'); ?>"><link rel="stylesheet" href="inc/modern.css?<?php echo @filemtime('inc/modern.css'); ?>"><link rel="stylesheet" href="inc/theme.css?<?php echo @filemtime('inc/theme.css'); ?>"><link rel="stylesheet" href="inc/entries.css?<?php echo @filemtime('inc/entries.css'); ?>"><script src="inc/main.js?<?php echo @filemtime('inc/main.js'); ?>"></script></head>
<body class="app-page archive-page"><header id="top"><div class="body top-inner"><a id="logo" href="./"><span class="brand-symbol">T<span>_</span></span><span class="brand-name"><?php echo text_v2_html($web['sitename']); ?></span></a><nav class="site-nav"><a href="./">浏览内容</a><a href="write.php">发布留言</a></nav><div class="theme-control"><button type="button" class="theme-toggle" id="theme-toggle"><span class="theme-icon">◐</span><span id="theme-label">自动</span></button><button type="button" class="theme-menu-toggle" id="theme-menu-toggle" aria-expanded="false">⌄</button><div class="theme-menu" id="theme-menu" hidden><button type="button" data-theme-choice="auto">自动</button><button type="button" data-theme-choice="light">浅色</button><button type="button" data-theme-choice="dark">深色</button></div></div></div></header>
<main class="page-main"><section class="intro body"><h1><?php echo $is_edit?'编辑留言':'留言详情'; ?></h1></section>
<?php if($error!==''): ?><div class="body entry-alert"><?php echo text_v2_html($error); ?></div><?php elseif($update_active): ?><div class="body entry-success">修改已保存</div><?php endif; ?>
<?php if($is_edit): ?>
<form method="post" class="body rounded entry-settings" action="entry.php?id=<?php echo $entry['id']; ?>&amp;edit=1" accept-charset="UTF-8">
<input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?php echo text_v2_html($csrf); ?>">
<label>标题<input name="subject" class="modern-input" maxlength="<?php echo SUBJ_SIZE; ?>" value="<?php echo text_v2_html($entry['subject']); ?>"></label>
<label>正文（保留 Markdown 原文）<textarea name="content" rows="15" class="modern-input" required><?php echo text_v2_html($entry['content']); ?></textarea></label>
<label class="entry-check"><input type="checkbox" name="pinned" value="1" <?php echo $entry['pinned']?'checked':''; ?>> 置顶留言</label>
<label>有效期<select name="expiry"><option value="keep" selected>保持当前设置</option><option value="never">永久有效</option><option value="1d">自保存起 1 天</option><option value="7d">自保存起 7 天</option><option value="30d">自保存起 30 天</option><option value="custom">自定义到期时间</option></select></label>
<label>自定义到期时间（北京时间，选择上方“自定义”时生效）<input type="datetime-local" name="expiry_custom" class="modern-input"></label>
<label>访问密码<select name="password_mode"><option value="keep">保持原密码</option><option value="set">设置或更换密码</option><option value="remove">移除密码保护</option></select></label>
<label>新密码<input name="new_password" type="password" autocomplete="new-password" minlength="8" class="modern-input" placeholder="设置或更换时填写，至少 8 位"></label>
<div class="editor-footer"><a href="entry.php?id=<?php echo $entry['id']; ?>">取消</a><button class="send2 primary-button" type="submit">保存修改</button></div></form>
<?php else: ?>
<article class="body rounded message-card entry-detail"><div class="texts"><div class="entry-topline"><span><?php echo date('Y-m-d H:i',$entry['created_at']); ?><?php echo $entry['pinned']?' · 📌 已置顶':''; ?><?php echo th_entry_expired($entry)?' · 已过期':''; ?></span><span><a href="./">返回列表</a><?php if(ADMIN_POWER): ?>　<a href="entry.php?id=<?php echo $entry['id']; ?>&amp;edit=1">编辑与设置</a><?php endif; ?></span></div>
<?php if (ADMIN_POWER): ?><form method="post" action="entry.php?id=<?php echo $entry['id']; ?>" class="entry-pin-form"><input type="hidden" name="action" value="toggle_pin"><input type="hidden" name="csrf" value="<?php echo text_v2_html($csrf); ?>"><button type="submit" class="entry-action"><?php echo $entry['pinned'] ? '取消置顶' : '📌 一键置顶'; ?></button></form><?php endif; ?>
<?php if($locked): ?>
<div class="entry-locked"><h2>🔒 密码保护的留言</h2><p>输入访问密码后才能查看正文和复制 Markdown 原文。</p><form method="post" action="entry.php?id=<?php echo $entry['id']; ?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="csrf" value="<?php echo text_v2_html($csrf); ?>"><label>访问密码<input class="modern-input" type="password" name="password" required autocomplete="off"></label><button type="submit" class="send2 primary-button">解锁内容</button></form></div>
<?php elseif($entry['pending']&&!ADMIN_POWER): ?><p>内容审核中</p>
<?php else: ?><div class="text"><?php echo th_entry_body_html($entry,ADMIN_POWER); ?></div><?php if($entry['reply'] !== ''): ?><div class="ad_reply"><strong>管理员回复</strong><div class="entry-raw"><?php echo text_v2_html($entry['reply']); ?></div></div><?php endif; ?><div class="entry-actions"><?php echo th_entry_controls($entry,ADMIN_POWER); ?></div><?php endif; ?>
</div></article><?php endif; ?>
</main></body></html>
