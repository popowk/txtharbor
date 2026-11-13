<?php
/* TxtHarbor-build:1.5.6 */
/* TxtHarbor GitHub release updater. Keep the original software copyright notices. */

function th_update_defaults() {
    return array(
        'project_url' => 'https://github.com/popowk/txtharbor',
        'proxy_project_url' => 'https://git.wksite.cn/popowk/txtharbor',
        'download_mode' => 'auto'
    );
}
function th_update_settings() {
    $defaults = th_update_defaults();
    $file = dirname(__DIR__) . '/data/update_source.php';
    if (!is_file($file)) return $defaults;
    $settings = @include $file;
    return is_array($settings) ? array_merge($defaults, array_intersect_key($settings, $defaults)) : $defaults;
}
function th_update_repo($url) {
    $url = trim((string)$url);
    if (!preg_match('~^https://github\.com/([A-Za-z0-9][A-Za-z0-9-]{0,38})/([A-Za-z0-9_.-]{1,100})/?$~iD', $url, $m)) return null;
    if ($m[2] === '.' || $m[2] === '..') return null;
    return array('owner' => $m[1], 'repo' => $m[2],
        'url' => 'https://github.com/' . $m[1] . '/' . $m[2]);
}
/** The short proxy path must mirror the same owner and repo as the real URL. */
function th_update_proxy_repo($url, $repo) {
    $url = trim((string)$url);
    if (!$repo || !preg_match('~^https://git\.wksite\.cn/([A-Za-z0-9][A-Za-z0-9-]{0,38})/([A-Za-z0-9_.-]{1,100})/?$~iD', $url, $m)) return null;
    if (strcasecmp($m[1], $repo['owner']) !== 0 || strcasecmp($m[2], $repo['repo']) !== 0) return null;
    return 'https://git.wksite.cn/' . $m[1] . '/' . $m[2];
}
/** Mirrors GitHub repository paths exactly and full GitHub API URLs by path. */
function th_update_proxy($settings, $url) {
    $repo = th_update_repo(isset($settings['project_url']) ? $settings['project_url'] : '');
    $proxy = th_update_proxy_repo(isset($settings['proxy_project_url']) ? $settings['proxy_project_url'] : '', $repo);
    if (!$repo || !$proxy || !is_string($url)) return null;
    $github = $repo['url'];
    if ($url === $github || strncmp($url, $github.'/', strlen($github)+1) === 0) {
        return $proxy . substr($url, strlen($github));
    }
    $api_prefix = 'https://api.github.com/repos/'.$repo['owner'].'/'.$repo['repo'];
    if ($url === $api_prefix || strncmp($url, $api_prefix.'/', strlen($api_prefix)+1) === 0) {
        // The worker accepts a full upstream URL when the path starts with https://.
        return 'https://git.wksite.cn/' . $url;
    }
    return null;
}
function th_update_save_settings($settings, &$error) {
    $repo = th_update_repo(isset($settings['project_url']) ? $settings['project_url'] : '');
    if (!$repo) {
        $error = '请输入 GitHub 项目地址，例如 https://github.com/popowk/txtharbor'; return false;
    }
    $proxy = th_update_proxy_repo(isset($settings['proxy_project_url']) ? $settings['proxy_project_url'] : '', $repo);
    if (!$proxy) {
        $error = '代理项目地址应为 https://git.wksite.cn/用户名/仓库名，且与 GitHub 仓库相同。'; return false;
    }
    $mode = isset($settings['download_mode']) ? $settings['download_mode'] : '';
    if (!in_array($mode, array('auto', 'direct', 'proxy'), true)) {
        $error = '不支持的连接方式。'; return false;
    }
    $file = dirname(__DIR__) . '/data/update_source.php';
    $output = array('project_url' => $repo['url'],
        'proxy_project_url' => $proxy, 'download_mode' => $mode);
    $payload = "<?php\n/* Auto-generated private site settings, never commit to GitHub. */\nreturn " . var_export($output, true) . ";\n";
    if (!is_dir(dirname($file)) || !is_writable(dirname($file))) {
        $error = '无法保存更新配置：data 目录不可写。'; return false;
    }
    $temp = @tempnam(dirname($file), '.th-update-');
    if (!$temp || @file_put_contents($temp, $payload, LOCK_EX) !== strlen($payload) || !@rename($temp, $file)) {
        if ($temp) @unlink($temp);
        $error = '无法保存更新设置，请检查 data 目录写权限。'; return false;
    }
    @chmod($file, 0600);
    return true;
}
function th_update_version() {
    $file = dirname(__DIR__) . '/VERSION';
    return is_file($file) ? trim((string)@file_get_contents($file)) : '0.0.0';
}
/** Check all required running components, not just the VERSION text file.
 * Does not touch passwords, database bytes or user data. */
function th_runtime_health() {
    $version = th_update_version();
    $components = array('0.php','admin.php','index.php','run.php','write.php',
        'update.php','entry.php','inc/admin_auth.php','inc/admin_login.php',
        'inc/entries.php','inc/entry_view.php','inc/main.js',
        'inc/entries.css','inc/theme.css','inc/github_update.php');
    $issues = array();
    if (!preg_match('/^\d+\.\d+\.\d+$/D',$version)) $issues[]='VERSION';
    foreach ($components as $name) {
        $path = dirname(__DIR__).'/'.$name;
        if (!is_file($path)) { $issues[]=$name.' 缺失'; continue; }
        $head=@file_get_contents($path,false,null,0,512);
        if (!is_string($head) || strpos($head,'TxtHarbor-build:'.$version)===false)
            $issues[]=$name.' 版本不一致';
    }
    return array('ok'=>count($issues)===0,'version'=>$version,
        'checked'=>count($components),'issues'=>$issues);
}
function th_update_token() {
    $cookie = isset($_COOKIE['th_admin_auth']) ? (string)$_COOKIE['th_admin_auth'] : '';
    return hash_hmac('sha256', 'th-updater-v1', $cookie . '|' . (string)@file_get_contents(dirname(__DIR__) . '/data/admin_key.php'));
}
function th_update_fetch($url, $limit, &$error) {
    if (!preg_match('~^https://(?:github\.com|api\.github\.com|git\.wksite\.cn)/~i', $url)) {
        $error = '禁止访问未授权的下载域名。'; return false;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $buffer = '';
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 22,
            CURLOPT_USERAGENT => 'TxtHarbor-Updater/1.3', CURLOPT_HTTPHEADER => array('Accept: application/vnd.github+json'),
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function($ch, $bytes) use (&$buffer, $limit) {
                if (strlen($buffer) + strlen($bytes) > $limit) return 0;
                $buffer .= $bytes; return strlen($bytes);
            }));
        if (defined('CURLOPT_PROTOCOLS_STR')) curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'https');
        elseif (defined('CURLOPT_PROTOCOLS')) curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        if (defined('CURLOPT_REDIR_PROTOCOLS_STR')) curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS_STR, 'https');
        elseif (defined('CURLOPT_REDIR_PROTOCOLS')) curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $detail = curl_error($ch);
        curl_close($ch);
        if ($ok === false || $status !== 200) {
            $error = '下载失败（HTTP ' . $status . '）：' . ($detail ? $detail : '请检查网络或 Release 是否公开');
            return false;
        }
        return $buffer;
    }
    $context = stream_context_create(array(
        'http' => array('timeout' => 20, 'follow_location' => 0,
            'header' => "User-Agent: TxtHarbor-Updater/1.3\r\nAccept: application/vnd.github+json\r\n"),
        'ssl' => array('verify_peer' => true, 'verify_peer_name' => true)));
    $fh = @fopen($url, 'rb', false, $context);
    if (!$fh) { $error = '服务器无法连接下载地址（请检查 HTTPS、allow_url_fopen 和代理设置）。'; return false; }
    $data = '';
    while (!feof($fh)) {
        $chunk = @fread($fh, 16384);
        if ($chunk === false) break;
        $data .= $chunk;
        if (strlen($data) > $limit) { fclose($fh); $error = '远程文件超出安全大小限制。'; return false; }
    }
    $meta = stream_get_meta_data($fh);
    fclose($fh);
    $headers = isset($meta['wrapper_data']) ? $meta['wrapper_data'] : array();
    if (!isset($headers[0]) || !preg_match('~^HTTP/\S+ 200\b~', $headers[0])) {
        $error = '远程服务器未返回 HTTP 200。'; return false;
    }
    return $data;
}
function th_update_candidates($url, $settings, $preferred = '') {
    $mode = $preferred ? $preferred : $settings['download_mode'];
    $proxy = th_update_proxy($settings, $url);
    if ($mode === 'direct') return array($url);
    if ($mode === 'proxy') return $proxy === null ? array() : array($proxy);
    return $proxy === null ? array($url) : array($url, $proxy);
}
function th_update_request($url, $settings, $limit, &$error, $preferred = '') {
    $errors = array();
    foreach (th_update_candidates($url, $settings, $preferred) as $target) {
        $got = th_update_fetch($target, $limit, $e);
        if ($got !== false) return $got;
        $errors[] = $e;
    }
    $error = implode('；', array_unique($errors));
    return false;
}
function th_update_release($settings, &$error, $preferred = '') {
    $repo = th_update_repo($settings['project_url']);
    if (!$repo) { $error = '请先保存 GitHub 项目地址。'; return false; }
    $api = 'https://api.github.com/repos/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['repo']) . '/releases/latest';
    $json = th_update_request($api, $settings, 1024 * 1024, $error, $preferred);
    if ($json === false) return false;
    $release = json_decode($json, true);
    if (!is_array($release) || empty($release['tag_name']) || !isset($release['assets'])) {
        $error = '未获得有效的 GitHub Release，请先发布正式版本（非草稿或预发布）。'; return false;
    }
    $version = ltrim($release['tag_name'], 'vV');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        $error = 'Release Tag 必须使用 v1.3.1 这样的版本号。'; return false;
    }
    $asset = null;
    foreach ($release['assets'] as $item) {
        if (isset($item['name']) && $item['name'] === 'txtharbor-update.zip') { $asset = $item; break; }
    }
    $download = 'https://github.com/' . $repo['owner'] . '/' . $repo['repo'] . '/releases/download/' . rawurlencode($release['tag_name']) . '/txtharbor-update.zip';
    return array('version' => $version, 'tag' => $release['tag_name'], 'asset' => $asset,
        'url' => $download, 'proxy_url' => th_update_proxy($settings, $download),
        'newer' => version_compare($version, th_update_version(), '>'),
        'release_url' => 'https://github.com/' . $repo['owner'] . '/' . $repo['repo'] . '/releases/tag/' . rawurlencode($release['tag_name']));
}
function th_update_install($settings, &$error, $repair = false) {
    if (!class_exists('ZipArchive')) { $error = '当前 PHP 未启用 ZipArchive，无法在线安装；请手动下载代码包覆盖。'; return false; }
    $release = th_update_release($settings, $error);
    if (!$release) return false;
    $relation = version_compare($release['version'], th_update_version());
    if ($relation < 0 || ($relation === 0 && !$repair)) {
        $error = '当前已是最新版本。如怀疑文件更新不完整，请使用修复安装。'; return false;
    }
    if (!$release['asset'] || empty($release['asset']['digest']) ||
        !preg_match('/^sha256:([0-9a-f]{64})$/i', $release['asset']['digest'], $m)) {
        $error = 'Release 未提供升级包的 SHA-256 校验值。出于安全考虑，已禁用在线安装，请手动下载。'; return false;
    }
    $body = th_update_request($release['url'], $settings, 25 * 1024 * 1024, $error);
    if ($body === false) return false;
    if (!hash_equals(strtolower($m[1]), hash('sha256', $body))) {
        $error = '压缩包 SHA-256 校验失败，已停止安装。请检查代理是否返回了原始 ZIP。'; return false;
    }
    $base = dirname(__DIR__);
    $temp = @tempnam(sys_get_temp_dir(), 'thupdate-');
    if (!$temp || @file_put_contents($temp, $body) !== strlen($body)) {
        if ($temp) @unlink($temp);
        $error = '无法创建临时升级文件。'; return false;
    }
    unset($body);
    $zip = new ZipArchive();
    if ($zip->open($temp) !== true) { @unlink($temp); $error = '升级包不是有效 ZIP。'; return false; }
    $allow = array_fill_keys(array('0.php', 'admin.php', 'index.php', 'run.php', 'write.php', 'update.php', 'VERSION',
        'inc/main.js', 'inc/theme.js', 'inc/style.css', 'inc/modern.css', 'inc/theme.css', 'inc/content.php', 'inc/imcode.html', 'inc/github_update.php', 'inc/admin_auth.php', 'inc/admin_login.php', 'inc/entries.php', 'inc/entry_view.php',
        'inc/markdown.php', 'inc/entries.css', 'inc/media_store.php', 'entry.php', 'data/.htaccess'), true);
    $files = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, 'txt/') === 0) $name = substr($name, 4);
        if (!isset($allow[$name])) continue;
        $stat = $zip->statIndex($i);
        if ($stat['size'] > 2 * 1024 * 1024 || (isset($stat['external_attributes']) &&
            ((($stat['external_attributes'] >> 16) & 0170000) === 0120000))) {
            $error = '升级包含异常文件：' . $name; break;
        }
        if (isset($files[$name])) { $error = '升级包含重复文件：' . $name; break; }
        $part = $zip->getFromIndex($i);
        if ($part === false) { $error = '无法读取升级文件：' . $name; break; }
        $files[$name] = $part;
    }
    $zip->close(); @unlink($temp);
    if (!empty($error)) return false;
    if (!isset($files['VERSION']) || trim($files['VERSION']) !== $release['version'] ||
        !isset($files['update.php']) || !isset($files['inc/github_update.php'])) {
        $error = '升级包版本号不匹配或缺少必要文件。'; return false;
    }
    // Guard against release assets that mix file versions. A repair install
    // must never confirm success from VERSION alone.
    if (version_compare($release['version'],'1.5.3','>=')) {
        $required = array('0.php','admin.php','index.php','run.php','write.php',
            'update.php','entry.php','inc/admin_auth.php','inc/admin_login.php',
            'inc/entries.php','inc/entry_view.php','inc/main.js',
            'inc/entries.css','inc/theme.css','inc/github_update.php');
        foreach ($required as $component) {
            if (!isset($files[$component]) ||
                strpos(substr($files[$component],0,512),'TxtHarbor-build:'.$release['version']) === false) {
                $error = '升级包缺少对应版本的核心文件：'.$component;
                return false;
            }
        }
    }
    // Backups are stored OUTSIDE the site document root; .htaccess alone does
    // not protect downloadable archives on hosts using Nginx.
    $backup_dir = rtrim(sys_get_temp_dir(), '/\\') . '/txtharbor-backup-' .
        substr(hash('sha256', (string)realpath($base)), 0, 16);
    if (!is_dir($backup_dir) && !@mkdir($backup_dir, 0700, true)) {
        $error = '无法创建站点外临时备份目录。'; return false;
    }
    @chmod($backup_dir, 0700);
    $backup = $backup_dir . '/code-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
    $out = new ZipArchive();
    if ($out->open($backup, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        $error = '创建更新备份失败。'; return false;
    }
    $existing = array();
    foreach ($files as $name => $value) {
        $path = $base . '/' . $name;
        if (!is_dir(dirname($path))) { $out->close(); @unlink($backup); $error = '升级目录不存在：' . dirname($name); return false; }
        if (file_exists($path)) {
            $old = @file_get_contents($path);
            if ($old === false || !is_writable($path)) { $out->close(); @unlink($backup); $error = '无法备份或写入：' . $name; return false; }
            $existing[$name] = $old;
            $out->addFromString($name, $old);
        } else {
            if (!is_writable(dirname($path))) { $out->close(); @unlink($backup); $error = '目录不可写：' . dirname($name); return false; }
            $existing[$name] = null;
        }
    }
    if (!$out->close()) { @unlink($backup); $error = '备份 ZIP 写入失败。'; return false; }
    $completed = array();
    // Confirm VERSION only after every other component has been installed.
    $names = array_keys($files);
    $names = array_values(array_diff($names, array('VERSION')));
    if (isset($files['VERSION'])) $names[]='VERSION';
    foreach ($names as $name) {
        $path = $base . '/' . $name;
        $value = $files[$name];
        $written=@file_put_contents($path, $value, LOCK_EX);
        if ($written !== strlen($value) || !is_file($path) ||
            !hash_equals(hash('sha256',$value),(string)@hash_file('sha256',$path))) {
            $error = '更新 ' . $name . ' 失败或文件校验不符，正在还原已替换文件。'; break;
        }
        if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
        $completed[] = $name;
    }
    if ($error) {
        // Restore every attempted file (including the one whose write failed).
        foreach (array_keys($files) as $name) {
            $path = $base . '/' . $name;
            if ($existing[$name] === null) @unlink($path);
            else @file_put_contents($path, $existing[$name], LOCK_EX);
        }
        if (function_exists('opcache_reset')) @opcache_reset();
        return false;
    }
    if (function_exists('opcache_reset')) @opcache_reset();
    return array('version' => $release['version'], 'backup' => basename($backup), 'files' => count($completed));
}
