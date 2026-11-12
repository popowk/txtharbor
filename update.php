<?php
/*
* TxtHarbor GitHub updater, based on the 162100 message board.
* Original copyright and noncommercial restriction preserved in original files.
*/
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/content.php';
require_once __DIR__ . '/inc/github_update.php';
$web = text_v2_config_utf8($web);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
if (!confirm_power()) {
    http_response_code(403);
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><title>请先登录管理后台</title><body><p>请先从 <a href="admin.php">管理设置</a> 登录后再进入更新中心。</p></body></html>';
    exit;
}
function th_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$settings = th_update_settings();
$error = '';
$success = '';
$release = null;
$has_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
if ($has_post) {
    $token = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!hash_equals(th_update_token(), $token)) {
        $error = '操作令牌已失效，请刷新后台并重新提交。';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'save') {
            $new = array(
                'project_url' => isset($_POST['project_url']) ? $_POST['project_url'] : '',
                'proxy_project_url' => isset($_POST['proxy_project_url']) ? $_POST['proxy_project_url'] : '',
                'download_mode' => isset($_POST['download_mode']) ? $_POST['download_mode'] : 'auto');
            if (th_update_save_settings($new, $error)) {
                $settings = th_update_settings();
                $success = '更新源设置已保存。';
            } else {
                $settings = array_merge($settings, $new);
            }
        } elseif ($action === 'test_proxy' || $action === 'test_direct') {
            $repo = th_update_repo($settings['project_url']);
            if (!$repo) $error = '仓库地址不正确，请先保存更新源。';
            else {
                $api = 'https://api.github.com/repos/'.$repo['owner'].'/'.$repo['repo'];
                $prefer = $action === 'test_proxy' ? 'proxy' : 'direct';
                $json = th_update_request($api, $settings, 1024*1024, $error, $prefer);
                if ($json !== false) {
                    $info = json_decode($json, true);
                    if (is_array($info) && isset($info['full_name']) &&
                        strcasecmp($info['full_name'], $repo['owner'].'/'.$repo['repo']) === 0) {
                        $success = ($prefer === 'proxy' ? '代理' : '直连').'测试成功：'. $info['full_name'];
                        $error = '';
                    } else $error = '无法验证仓库元数据，请检查 URL 或代理规则。';
                }
            }
        } elseif ($action === 'check') {
            $release = th_update_release($settings, $error);
            if ($release) $success = $release['newer'] ? '发现可用的新版本。' : '当前已是最新版本。';
            elseif (strpos($error, '404') !== false) $error .= '（该仓库可能尚未发布正式 Release。）';
        } elseif ($action === 'install') {
            $result = th_update_install($settings, $error);
            if ($result) $success = '更新成功！已安装 v'.$result['version'].'，更新 '.(int)$result['files'].' 个代码文件；旧代码已备份于服务器的非公开临时目录。';
        } else $error = '不支持的管理操作。';
    }
}
$project = th_update_repo($settings['project_url']);
$mirror = th_update_proxy_repo($settings['proxy_project_url'], $project);
$version = th_update_version();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>版本更新 · TxtHarbor</title>
<script src="inc/theme.js?v=1.4.1&amp;t=<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link rel="stylesheet" href="inc/style.css?<?php echo @filemtime('inc/style.css'); ?>">
<link rel="stylesheet" href="inc/modern.css?<?php echo @filemtime('inc/modern.css'); ?>">
<style>
.update-wrap{max-width:980px;margin:0 auto;padding:0 20px 65px}
.update-shell{background:#112136;border:1px solid #2d4761;border-radius:15px;padding:27px;margin-bottom:16px;color:#d6e7f4}
.update-shell h2{font-size:18px;color:#f1faff;margin:0 0 15px;font-weight:700}
.update-shell p,.update-shell small{color:#91adc4;font-size:13px;line-height:1.9}
.update-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.update-form-row{margin-bottom:19px}
.update-form-row label{display:block;color:#a7becf;font-size:12px;font-weight:700;margin-bottom:9px}
.update-form-row input,.update-form-row select{box-sizing:border-box;display:block;width:100%;padding:12px 13px;background:#091928;border:1px solid #35516e;border-radius:8px;color:#f0f6fc;font-size:14px;min-width:0}
.update-form-row input:focus,.update-form-row select:focus{outline:2px solid #359daf;outline-offset:1px}
.update-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.update-btn{border:1px solid #416b7e;background:#15394e;color:#a3f3ff;border-radius:9px;padding:11px 16px;cursor:pointer;font-size:13px;font-weight:700}
.update-btn:hover{background:#23516c;color:#fff}
.update-btn.secondary{background:#1b2b42;color:#cbdfef;border-color:#38536d}
.update-btn.danger{background:#482633;border-color:#854252;color:#ffc9d0}
.update-link{display:inline-block;color:#8be0f2;text-decoration:none;overflow-wrap:anywhere;font-size:13px}
.update-link:hover{text-decoration:underline;color:#fff}
.update-notice{border:1px solid #285d55;border-radius:10px;padding:14px 17px;background:#123329;color:#b8f5d8;margin:0 0 17px;overflow-wrap:anywhere}
.update-notice.error{background:#35202a;color:#ffbec9;border-color:#80394a}
.update-caption{font:700 11px/1.4 ui-monospace,monospace;color:#70bacd;letter-spacing:.13em;margin-bottom:14px}
.update-meta{color:#8ba5ba;font-size:13px;line-height:1.8}
.update-meta b{color:#d3f9ff}
.update-tip{font-size:12px;color:#a1b9cb;margin:14px 0 0;line-height:1.9}
.update-links{display:flex;gap:18px;flex-wrap:wrap}
@media(max-width:700px){.update-grid{grid-template-columns:1fr}.update-shell{padding:20px}.update-wrap{padding:0 13px 50px}}
</style>
<link rel="stylesheet" href="inc/theme.css?v=1.4.1&amp;t=<?php echo @filemtime('inc/theme.css'); ?>">
</head>
<body class="app-page admin-page">
<header id="top"><div class="body top-inner"><a href="./" id="logo"><span class="brand-symbol">T<span>_</span></span><span class="brand-name">TxtHarbor</span></a><nav class="site-nav"><a href="./">留言列表</a><a href="index.php?admin_run=1">留言管理</a><a href="admin.php">站点设置</a><a href="update.php" class="active">版本更新</a><a href="run.php?act=logout">退出管理员</a></nav><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="点击切换主题" title="点击切换主题"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span></button><button type="button" class="theme-menu-toggle" id="theme-menu-toggle" aria-label="选择主题模式" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu" title="选择自动、浅色或深色模式"><span class="theme-chevron" aria-hidden="true">⌄</span></button>
      <div class="theme-menu" id="theme-menu" role="group" aria-label="显示主题" hidden>
        <button type="button" data-theme-choice="auto">◐ <span>自动</span></button>
        <button type="button" data-theme-choice="light">☀ <span>浅色</span></button>
        <button type="button" data-theme-choice="dark">☾ <span>深色</span></button>
      </div>
    </div></div></header>
<main class="page-main">
<section class="intro update-wrap"><h1>版本更新</h1></section>
<div class="update-wrap">
<?php if ($error !== ''): ?><div class="update-notice error" role="alert"><?php echo th_h($error); ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="update-notice" role="status"><?php echo th_h($success); ?></div><?php endif; ?>
<div class="update-grid">
<section class="update-shell">

<h2>当前版本 <span class="version-number">v<?php echo th_h($version); ?></span></h2>
<p>检查 GitHub 正式发布的最新版本。</p>
<form method="post" class="update-actions"><input type="hidden" name="csrf" value="<?php echo th_h(th_update_token()); ?>"><button class="update-btn" type="submit" name="action" value="check">检查新版本</button><button class="update-btn secondary" type="submit" name="action" value="test_direct">测试 GitHub</button><button class="update-btn secondary" type="submit" name="action" value="test_proxy">测试国内代理</button></form>
<?php if ($release): ?><div class="update-meta" style="margin-top:16px">最新 Release：<b>v<?php echo th_h($release['version']); ?></b><br>升级包：<?php echo $release['asset'] ? '已找到' : '未上传'; ?></div>
<div class="update-links" style="margin-top:10px"><a class="update-link" target="_blank" rel="noopener noreferrer" href="<?php echo th_h($release['release_url']); ?>">查看 Release</a><a class="update-link" target="_blank" rel="noopener noreferrer" href="<?php echo th_h($release['url']); ?>">直连下载</a><?php if ($release['proxy_url']): ?><a class="update-link" target="_blank" rel="noopener noreferrer" href="<?php echo th_h($release['proxy_url']); ?>">代理下载</a><?php endif; ?></div>
<?php if ($release['newer'] && $release['asset']): ?><form method="post" class="update-actions" style="margin-top:20px" onsubmit="return confirm('确认安装新版本？会备份现有代码，不会覆盖 data 或 config.php。建议先单独备份整个站点。');"><input type="hidden" name="csrf" value="<?php echo th_h(th_update_token()); ?>"><button class="update-btn danger" type="submit" name="action" value="install">校验并在线安装更新</button></form><?php endif; ?><?php endif; ?>
</section>
<section class="update-shell">
<h2>项目地址</h2>
<?php if ($project): ?><p>GitHub 项目</p><a class="update-link" target="_blank" rel="noopener noreferrer" href="<?php echo th_h($project['url']); ?>"><?php echo th_h($project['url']); ?></a><?php endif; ?>
<?php if ($mirror): ?><p>国内代理</p><a class="update-link" target="_blank" rel="noopener noreferrer" href="<?php echo th_h($mirror); ?>"><?php echo th_h($mirror); ?></a><?php endif; ?>

</section>
</div>
<section class="update-shell"><h2>配置更新源</h2>
<form method="post"><input type="hidden" name="csrf" value="<?php echo th_h(th_update_token()); ?>">
<div class="update-form-row"><label for="project_url">GitHub 项目地址</label><input type="url" id="project_url" name="project_url" required spellcheck="false" value="<?php echo th_h($settings['project_url']); ?>" placeholder="https://github.com/popowk/txtharbor"></div>
<div class="update-form-row"><label for="proxy_project_url">代理项目地址</label><input type="url" id="proxy_project_url" name="proxy_project_url" required spellcheck="false" value="<?php echo th_h($settings['proxy_project_url']); ?>" placeholder="https://git.wksite.cn/popowk/txtharbor"></div>
<div class="update-form-row"><label for="download_mode">连接策略</label><select name="download_mode" id="download_mode"><option value="auto"<?php echo $settings['download_mode']==='auto'?' selected':''; ?>>自动：GitHub 优先，失败后代理</option><option value="proxy"<?php echo $settings['download_mode']==='proxy'?' selected':''; ?>>优先国内：仅使用代理</option><option value="direct"<?php echo $settings['download_mode']==='direct'?' selected':''; ?>>直连：仅使用 GitHub</option></select></div>
<button type="submit" class="update-btn" name="action" value="save">保存更新设置</button>
</form><p class="update-tip">更新设置独立保存，不影响站点数据。</p>
</section>
<section class="update-shell"><h2>更新安全</h2><p>安装前会验证 SHA-256 并备份代码，不会覆盖留言、密码或站点配置。升级前建议另行备份网站；缺少 ZipArchive 时可手动更新。</p></section>
</div>
</main>
</body></html>
