<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'config.php';
require_once 'inc/content.php';
$web = text_v2_config_utf8($web);
header('Content-Type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?> · 站点维护</title>
<script src="inc/theme.js?<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link rel="stylesheet" href="inc/style.css?<?php echo filemtime('inc/style.css'); ?>" />
<link rel="stylesheet" href="inc/modern.css?<?php echo filemtime('inc/modern.css'); ?>" />
<link rel="stylesheet" href="inc/theme.css?<?php echo @filemtime('inc/theme.css'); ?>">
<script src="inc/main.js?<?php echo filemtime('inc/main.js'); ?>"></script>
</head>
<body class="app-page">
<header id="top"><div class="body top-inner"><a href="./" id="logo"><span class="brand-symbol">T<span>_</span></span><span class="brand-name"><?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?></span></a><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="选择显示主题" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span><span class="theme-chevron" aria-hidden="true">⌄</span></button>
      <div class="theme-menu" id="theme-menu" role="group" aria-label="显示主题" hidden>
        <button type="button" data-theme-choice="auto">◐ <span>自动</span></button>
        <button type="button" data-theme-choice="light">☀ <span>浅色</span></button>
        <button type="button" data-theme-choice="dark">☾ <span>深色</span></button>
      </div>
    </div></div><iframe id="lastFrame" name="lastFrame" style="display:none;" title="操作结果"></iframe></header>
<main class="page-main"><section class="intro body"><h1>站点维护中</h1><p>当前正在维护中，请稍后再访问。</p></section></main>
<footer id="foot"><div class="body"><span>© <?php echo gmdate('Y', time() + floatval($web['time_pos']) * 3600); ?> <?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?></span><span></span></div></footer>
</body>
</html>
