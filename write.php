<?php
/* TxtHarbor-build:1.5.4 */
/*
 * 162100 留言本衍生界面，原项目版权信息保留。
 * 作者：162100.com；仅供学习交流，禁用于商业目的。
 */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once 'config.php';
require_once __DIR__.'/inc/admin_auth.php';
require_once 'inc/entries.php';
$web = text_v2_config_utf8($web);
header('Content-Type: text/html; charset=UTF-8');
if ($web['site_close'] === 1) { header('Location: 0.php'); exit; }
$category = isset($_GET['subarea']) && is_numeric($_GET['subarea']) && isset($web['subarea'][$_GET['subarea']]) ? (int)$_GET['subarea'] : 0;
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0" />
<meta name="color-scheme" content="light dark" />
<title><?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?> · 新建留言</title>
<script src="inc/theme.js?v=<?php echo rawurlencode(trim((string)@file_get_contents(__DIR__.'/VERSION'))); ?>&amp;t=<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link rel="stylesheet" href="inc/style.css?<?php echo filemtime('inc/style.css'); ?>" />
<link rel="stylesheet" href="inc/modern.css?<?php echo filemtime('inc/modern.css'); ?>" /><link rel="stylesheet" href="inc/entries.css?<?php echo @filemtime('inc/entries.css'); ?>" />
<link rel="stylesheet" href="inc/theme.css?v=<?php echo rawurlencode(trim((string)@file_get_contents(__DIR__.'/VERSION'))); ?>&amp;t=<?php echo @filemtime('inc/theme.css'); ?>">
<script src="inc/main.js?v=<?php echo rawurlencode(trim((string)@file_get_contents(__DIR__.'/VERSION'))); ?>&amp;t=<?php echo filemtime('inc/main.js'); ?>"></script>
<script>
main.minWordCount = 1;
main.maxSubjectWordCount = <?php echo SUBJ_SIZE; ?>;
main.maxContentWordCount = <?php echo WORD_SIZE; ?>;
</script>
</head>
<body class="app-page composer-page">
<header id="top">
  <div class="body top-inner">
    <a href="./" id="logo" aria-label="返回留言列表"><span class="brand-symbol">T<span>_</span></span><span class="brand-name"><?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?></span></a>
    <nav class="site-nav" aria-label="主要导航">
      <a href="./">浏览内容</a><a class="active" href="write.php">新建留言</a>
      <?php if (th_admin_authorized()) { ?><a href="run.php?act=logout">退出管理员</a><?php } ?>
    </nav><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="点击切换主题" title="点击切换主题"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span></button><button type="button" class="theme-menu-toggle" id="theme-menu-toggle" aria-label="选择主题模式" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu" title="选择自动、浅色或深色模式"><span class="theme-chevron" aria-hidden="true">⌄</span></button>
      <div class="theme-menu" id="theme-menu" role="group" aria-label="显示主题" hidden>
        <button type="button" data-theme-choice="auto">◐ <span>自动</span></button>
        <button type="button" data-theme-choice="light">☀ <span>浅色</span></button>
        <button type="button" data-theme-choice="dark">☾ <span>深色</span></button>
      </div>
    </div>
  </div>
  <iframe id="lastFrame" name="lastFrame" title="提交结果" style="display:none;"></iframe>
</header>
<main class="page-main">
  <section class="intro body">
    
    <h1>发布留言</h1>
    
  </section>
  <form id="mainform" name="mainform" method="post" action="run.php" target="lastFrame" onsubmit="return main.submitMessage(event, this);" accept-charset="UTF-8">
    <input type="hidden" name="act" value="write" />
    <div class="body rounded editor-card">
      <div class="editor-header"><div><strong>编辑内容</strong></div></div>
      <div class="texts editor-content">
        <?php if (count($web['subarea']) > 0) { ?>
        <div class="field-block"><div class="field-title"><label>内容分类</label></div>
          <div id="subarea" class="tag-selector">
            <?php foreach ($web['subarea'] as $key => $value) { ?>
            <label for="p<?php echo (int)$key; ?>" <?php if ($category === (int)$key) echo 'id="is"'; ?> onclick="main.confirmSubarea(this);"><input type="radio" name="posttype" id="p<?php echo (int)$key; ?>" value="<?php echo (int)$key; ?>" <?php if ($category === (int)$key) echo 'checked'; ?> /><span><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></span></label>
            <?php } ?>
          </div>
        </div>
        <?php } ?>
        <?php if ($web['subj_open'] === 10 || $web['subj_open'] === 11) { ?>
        <div class="field-block"><div class="field-title"><label for="subject">留言标题 <?php if ($web['subj_open'] === 11) { ?><span class="redword">*</span><?php } else { ?><small>选填</small><?php } ?></label><span id="max_words_subject" class="counter"></span></div>
          <input type="text" id="subject" name="subject" class="modern-input" placeholder="输入标题（可选）" oninput="main.runGetWordCount('subject', main.maxSubjectWordCount);" autocomplete="off" />
        </div>
        <?php } ?>
        <div class="field-block"><div class="field-title"><label for="content">正文内容 <span class="redword">*</span></label><span id="max_words_content" class="counter"></span></div>
          <div class="text-editor"><textarea id="content" name="content" rows="12" spellcheck="false" placeholder="从这里开始输入或粘贴…&#10;&#10;支持 /  \\  https://  JSON  多行代码和连续空格" oninput="main.runGetWordCount('content', main.maxContentWordCount);"></textarea>
            <div class="editor-toolbar"><div class="toolbar-actions"><button type="button" class="tool-button" onclick="main.clearInput('content');">✕ <span>一键清空</span></button><button type="button" class="tool-button paste" onclick="main.pasteInput('content');">▣ <span>一键粘贴</span></button></div></div>
          </div>
        </div>
        <?php if ($web['imcode_close'] !== 1 && !th_admin_authorized()) { ?>
        <div class="field-block" id="imcodediv"><div class="field-title"><label for="imcode">验证码 <span class="redword">*</span></label></div><div class="captcha-row"><input type="text" name="imcode" id="imcode" size="6" maxlength="6" autocomplete="off" /><iframe id="imcodejs" name="imcodejs" title="验证码" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" src="inc/imcode.html"></iframe></div></div>
        <?php } ?>
        <div class="entry-publish-options">
          <div class="field-title"><label>分享设置</label><small>默认永久公开，密码留空表示不加密</small></div>
          <div class="entry-options-grid"><label>有效期
            <select name="expiry" id="expiry-option"><option value="never">永久有效</option><option value="1d">1 天</option><option value="7d">7 天</option><option value="30d">30 天</option><option value="custom">自定义时间</option></select>
          </label><label>访问密码（可选）<div class="password-tools"><input id="publish-access-password" type="password" name="access_password" class="modern-input" autocomplete="new-password" minlength="4" placeholder="至少 4 位，留空表示公开"><button type="button" class="tool-button" onclick="main.generateAccessPassword('publish-access-password',this)">随机生成</button></div><small>默认生成 4 位小写字母和数字，更长的密码更安全</small></label></div>
          <label id="expiry-custom-wrap" hidden>到期时间（北京时间）<input type="datetime-local" name="expiry_custom" class="modern-input"></label>
        </div>
        <div class="editor-footer"><span class="privacy-hint">保留换行、空格及特殊字符</span><button class="send2 primary-button" type="submit">发布留言 <span aria-hidden="true">↗</span></button></div>
        <div id="submit-feedback" class="submit-feedback" role="alert" aria-live="assertive" hidden></div>
      </div>
    </div>
  </form>
</main>
<footer id="foot"><div class="body"><span>© <?php echo gmdate('Y', time() + floatval($web['time_pos']) * 3600); ?> <?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?></span><span></span></div></footer>
<noscript><div class="warning">请开启 JavaScript 以使用快捷操作和提交反馈。</div></noscript>
<script>
(function(){
  var expiry = document.getElementById('expiry-option');
  var custom = document.getElementById('expiry-custom-wrap');
  if (expiry && custom) expiry.addEventListener('change', function () { custom.hidden = expiry.value !== 'custom'; });
  main.runGetWordCount('content', main.maxContentWordCount);
  if (document.getElementById('subject')) main.runGetWordCount('subject', main.maxSubjectWordCount);
})();
</script>
</body>
</html>
