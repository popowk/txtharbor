<?php
/* TxtHarbor-build:1.5.3 */
/** Server-side legacy administrator cookie handling (no iframe/JavaScript cookie writes). */
function th_admin_cookie_path() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/run.php'));
    return $dir === '/' || $dir === '.' ? '/' : rtrim($dir, '/') . '/';
}
function th_admin_set_cookie($token, $persistent = false, $clear = false) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $options = array('expires' => $clear ? time()-3600 : ($persistent ? time()+365*86400 : 0),
        'path'=>th_admin_cookie_path(), 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax');
    setcookie('manage', $clear ? '' : $token, $options);
}
function th_admin_target($from) {
    return $from === 'admin' ? 'admin.php' : 'index.php?admin_run=1';
}
function th_admin_handle_request($web) {
    $act = $_POST['act'] ?? '';
    if ($act === 'login' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        header('Cache-Control: no-store, private');
        $from = ($_POST['login_from'] ?? '') === 'admin' ? 'admin' : 'index';
        $redirect = th_admin_target($from);
        $password = $_POST['password'] ?? null;
        $error = '';
        if (!is_string($password) || $password === '') $error = 'empty';
        elseif (!isset($web['password']) || !is_string($web['password']) ||
            !hash_equals($web['password'], substr(sha1(md5($password)), 4, 32))) $error = 'invalid';
        else {
            try {
                $key = (string)time().bin2hex(random_bytes(24));
                $file = dirname(__DIR__).'/data/admin_key.php';
                if (@file_put_contents($file, '<?php die(); ?>'.$key, LOCK_EX) !== strlen('<?php die(); ?>'.$key))
                    $error = 'storage';
                else th_admin_set_cookie($key, !empty($_POST['save_cookie']));
            } catch (Throwable $e) { $error = 'storage'; error_log('TxtHarbor admin login: '.$e->getMessage()); }
        }
        if ($error !== '') $redirect .= (strpos($redirect,'?')===false?'?':'&').'login_error='.$error;
        header('Location: '.$redirect, true, 303);
        exit;
    }
    if (($_GET['act'] ?? '') === 'logout') {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        $file = dirname(__DIR__).'/data/admin_key.php';
        $revoked = !is_file($file);
        if (!$revoked) {
            $fp = @fopen($file, 'c+b');
            if ($fp && @flock($fp, LOCK_EX)) {
                $text='<?php die(); ?>';
                $revoked = @ftruncate($fp, 0) && @rewind($fp)
                    && @fwrite($fp, $text) === strlen($text) && @fflush($fp);
                @flock($fp, LOCK_UN);
            }
            if ($fp) @fclose($fp);
        }
        // Expire both current and old cookie paths. The revoked server token
        // makes even a surviving obsolete cookie invalid.
        $paths = array_unique(array('/', th_admin_cookie_path(),
            rtrim(th_admin_cookie_path(), '/')));
        foreach ($paths as $path) {
            if (!$path) continue;
            setcookie('manage', '', array('expires'=>time()-3600, 'path'=>$path,
                'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off',
                'httponly'=>true,'samesite'=>'Lax'));
        }
        unset($_COOKIE['manage']);
        if (!$revoked) {
            error_log('TxtHarbor: failed to revoke the administrator token on logout');
            http_response_code(503);
            exit('管理员令牌无法撤销，请检查 data/admin_key.php 写入权限。');
        }
        clearstatcache(true, $file);
        header('Location: ./?logged_out=1', true, 303);
        exit;
    }
}
