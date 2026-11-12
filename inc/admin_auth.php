<?php
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
        th_admin_set_cookie('', false, true);
        // Clear any previous pathless cookie from the old iframe implementation.
        if (th_admin_cookie_path() !== '/') {
            setcookie('manage', '', array('expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax'));
        }
        // Revoke the single legacy server-side token as well, if writable.
        $file=dirname(__DIR__).'/data/admin_key.php';
        if (is_file($file)) @file_put_contents($file, '<?php die(); ?>', LOCK_EX);
        header('Cache-Control: no-store, private');
        header('Location: ./', true, 302);
        exit;
    }
}
