<?php
/* TxtHarbor-build:1.5.7 */
/** Independent administrator sessions; legacy manage cookies never grant access. */
function th_admin_cookie_path() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/run.php'));
    return $dir === '/' || $dir === '.' ? '/' : rtrim($dir, '/') . '/';
}
function th_admin_cookie_name() { return 'th_admin_auth'; }
function th_admin_cookie_secure() { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }
function th_admin_set_cookie($token, $persistent = false, $clear = false, $name = null, $path = null) {
    setcookie($name ?: th_admin_cookie_name(), $clear ? '' : $token, array(
        'expires'=>$clear ? time()-3600 : ($persistent ? time()+30*86400 : 0),
        'path'=>$path ?: th_admin_cookie_path(),
        'secure'=>th_admin_cookie_secure(), 'httponly'=>true,'samesite'=>'Lax'));
}
function th_admin_sessions_path() { return dirname(__DIR__).'/data/admin_sessions.php'; }
/** A site-specific private directory outside the document root if data/ is read-only. */
function th_admin_private_dir() {
    $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
    return rtrim(sys_get_temp_dir(), '/\\').'/txtharbor-auth-'.substr(hash('sha256',$root),0,20);
}
function th_admin_session_paths($create_private = false) {
    $private = th_admin_private_dir();
    $publicRoot=isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    // PHP temporary storage must not accidentally be placed inside the served web root.
    if ($publicRoot && ($private===$publicRoot || strpos($private,rtrim($publicRoot,'/\\').DIRECTORY_SEPARATOR)===0))
        return array(th_admin_sessions_path());
    if ($create_private && !is_dir($private)) @mkdir($private, 0700, true);
    // Never create a file under a publicly served temporary directory.
    return array(th_admin_sessions_path(), $private.'/admin_sessions.php');
}
function th_admin_load($fp) {
    rewind($fp); $raw=stream_get_contents($fp);
    $prefix="<?php exit; ?>\n";
    if ($raw==='') return array();
    if (!is_string($raw) || strncmp($raw,$prefix,strlen($prefix))!==0)
        throw new RuntimeException('管理员会话文件格式异常');
    $data=json_decode(substr($raw,strlen($prefix)),true);
    if (!is_array($data)) throw new RuntimeException('管理员会话数据损坏');
    return $data;
}
function th_admin_store($path,$callback) {
    $fp=@fopen($path,'c+b');
    if (!$fp) throw new RuntimeException('无法写入管理会话目录');
    try {
        if (!@flock($fp,LOCK_EX)) throw new RuntimeException('无法锁定管理员会话文件');
        $data=th_admin_load($fp);
        $now=time();
        foreach ($data as $key=>$item)
            if (!is_string($key) || !is_array($item) || !is_int($item['exp']??null) || $item['exp']<=$now) unset($data[$key]);
        $updated=$callback($data);
        if (!is_array($updated)) throw new RuntimeException('管理员会话数据无效');
        $payload="<?php exit; ?>\n".json_encode($updated,JSON_UNESCAPED_SLASHES);
        if (@ftruncate($fp,0)===false || @fseek($fp,0)!==0 || @fwrite($fp,$payload)!==strlen($payload) || !@fflush($fp))
            throw new RuntimeException('管理员会话保存失败');
        @chmod($path,0600);
        return true;
    } finally { @flock($fp,LOCK_UN);@fclose($fp); }
}
/** On restrictive shared hosts fall back to a private PHP temp directory. */
function th_admin_update_sessions($callback) {
    $errors=array();
    foreach (th_admin_session_paths(true) as $file) {
        if (dirname($file)===th_admin_private_dir() && !is_dir(dirname($file))) continue;
        try {return th_admin_store($file,$callback);}
        catch (Throwable $e) {$errors[]=$e->getMessage();}
    }
    error_log('TxtHarbor admin session storage unavailable: '.implode('; ',$errors));
    throw new RuntimeException('管理员会话存储不可写（data 和 PHP 私有临时目录），请检查服务器权限');
}
function th_admin_read_sessions($file) {
    $fp=@fopen($file,'rb');
    if (!$fp) throw new RuntimeException('管理员会话文件无法读取');
    try {
        if (!@flock($fp,LOCK_SH)) throw new RuntimeException('管理员会话文件无法锁定');
        return th_admin_load($fp);
    } finally { @flock($fp,LOCK_UN);@fclose($fp); }
}
function th_admin_authorized() {
    $token=$_COOKIE[th_admin_cookie_name()]??null;
    if (!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)) return false;
    global $web;
    if (empty($web['password']) || !is_string($web['password'])) return false;
    $digest=hash('sha256',$token);
    $passwordFingerprint=hash('sha256',$web['password']);
    foreach (th_admin_session_paths() as $file) {
        if (!is_file($file)) continue;
        try {
            $sessions=th_admin_read_sessions($file);
            $record=$sessions[$digest]??null;
            if (is_array($record) && is_int($record['exp']??null) && $record['exp']>time()
                && is_string($record['pw']??null) && hash_equals($record['pw'],$passwordFingerprint)) return true;
        } catch (Throwable $e) {error_log('TxtHarbor admin session read: '.$e->getMessage());}
    }
    return false;
}
/** Revoke this browser's token wherever it exists, without logging out other users. */
function th_admin_revoke($token) {
    $hash=hash('sha256',$token);
    $revoked=false;
    $errors=array();
    foreach (th_admin_session_paths() as $file) {
        if (!is_file($file)) continue;
        try {
            $sessions=th_admin_read_sessions($file);
            if (!array_key_exists($hash,$sessions)) continue;
            th_admin_store($file,function($all)use($hash){unset($all[$hash]);return $all;});
            $revoked=true;
        } catch (Throwable $e) { $errors[]=$e->getMessage(); }
    }
    // On a shared host an old data/admin_sessions.php may be unreadable while
    // this user's actual token is held in the private fallback store.
    if ($errors && !$revoked) throw new RuntimeException('管理员会话撤销失败：'.implode('; ',$errors));
    return $revoked;
}
function th_admin_target($from) {return $from==='admin'?'admin.php':'index.php?admin_run=1';}
function th_admin_handle_request($web) {
    $act=$_POST['act']??'';
    if ($act==='login' && ($_SERVER['REQUEST_METHOD']??'')==='POST') {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        $from=($_POST['login_from']??'')==='admin'?'admin':'index';
        $redirect=th_admin_target($from);
        $password=$_POST['password']??null; $error='';
        // Shared, file-backed IP limit; old code allowed unlimited online guesses.
        // This runs only via run.php where th_access_rate() is loaded.
        try {
            if (function_exists('th_access_rate') && !th_access_rate('admin-login')) $error='locked';
        } catch (Throwable $ex) {
            $error='storage';error_log('TxtHarbor admin login rate limit: '.$ex->getMessage());
        }
        if ($error==='') {
            if (!is_string($password)||$password==='') $error='empty';
            elseif (!isset($web['password']) || !is_string($web['password']) ||
                !hash_equals($web['password'],substr(sha1(md5($password)),4,32))) $error='invalid';
            if ($error!=='' && function_exists('th_access_rate')) {
                try { th_access_rate('admin-login',false); }
                catch(Throwable $ex) { $error='storage';error_log('TxtHarbor admin login rate storage: '.$ex->getMessage()); }
            }
        }
        if ($error==='') {
            try {
                $token=bin2hex(random_bytes(32));
                $persistent=!empty($_POST['save_cookie']);
                $expiry=time()+($persistent?30*86400:86400);
                th_admin_update_sessions(function($all)use($token,$expiry,$web){
                    $all[hash('sha256',$token)]=array('exp'=>$expiry,'pw'=>hash('sha256',(string)$web['password']));
                    return $all;
                });
                if (function_exists('th_access_rate')) th_access_rate('admin-login',true);
                th_admin_set_cookie($token,$persistent);
                foreach (array_unique(array('/',th_admin_cookie_path())) as $path)
                    th_admin_set_cookie('',false,true,'manage',$path);
                $redirect.=(strpos($redirect,'?')===false?'?':'&').'login_check=1';
            } catch (Throwable $ex) {
                $error='storage'; error_log('TxtHarbor administrator login: '.$ex->getMessage());
            }
        }
        if ($error!=='') $redirect.=(strpos($redirect,'?')===false?'?':'&').'login_error='.$error;
        header('Location: '.$redirect,true,303);exit;
    }
    if (($_GET['act']??'')==='logout') {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache'); header('Expires: 0');
        $token=$_COOKIE[th_admin_cookie_name()]??'';
        if (is_string($token)&&preg_match('/^[a-f0-9]{64}$/D',$token)) {
            try {th_admin_revoke($token);}
            catch (Throwable $ex) {
                error_log('TxtHarbor administrator logout: '.$ex->getMessage());
                http_response_code(503);
                exit('管理员令牌无法安全撤销，请检查认证文件权限。');
            }
        }
        foreach (array_unique(array('/',th_admin_cookie_path(),rtrim(th_admin_cookie_path(),'/'))) as $path) {
            if ($path==='') continue;
            th_admin_set_cookie('',false,true,th_admin_cookie_name(),$path);
            th_admin_set_cookie('',false,true,'manage',$path);
        }
        unset($_COOKIE[th_admin_cookie_name()],$_COOKIE['manage']);
        header('Location: ./?logged_out=1',true,303);exit;
    }
}
