<?php
/*
 * 162100 留言本衍生界面，原项目版权信息保留。
 * 作者：162100.com；仅供学习交流，禁用于商业目的。
 */
header('Content-Type: text/html; charset=UTF-8');
require_once 'config.php';
require_once 'inc/content.php';
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
<script src="inc/theme.js?<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link rel="stylesheet" href="inc/style.css?<?php echo filemtime('inc/style.css'); ?>" />
<link rel="stylesheet" href="inc/modern.css?<?php echo filemtime('inc/modern.css'); ?>" />
<link rel="stylesheet" href="inc/theme.css?<?php echo @filemtime('inc/theme.css'); ?>">
<script src="inc/main.js?<?php echo filemtime('inc/main.js'); ?>"></script>
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
      <?php if (confirm_power()) { ?><a href="run.php?act=logout" target="lastFrame" onclick="main.addSubmitSafe();">退出管理</a><?php } ?>
    </nav><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="选择显示主题" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span><span class="theme-chevron" aria-hidden="true">⌄</span></button>
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
        <?php if ($web['imcode_close'] !== 1 && !confirm_power()) { ?>
        <div class="field-block" id="imcodediv"><div class="field-title"><label for="imcode">验证码 <span class="redword">*</span></label></div><div class="captcha-row"><input type="text" name="imcode" id="imcode" size="6" maxlength="6" autocomplete="off" /><iframe id="imcodejs" name="imcodejs" title="验证码" frameborder="0" marginwidth="0" marginheight="0" scrolling="no" src="inc/imcode.html"></iframe></div></div>
        <?php } ?>
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
  main.runGetWordCount('content', main.maxContentWordCount);
  if (document.getElementById('subject')) main.runGetWordCount('subject', main.maxSubjectWordCount);
})();
</script>
</body>
</html>
