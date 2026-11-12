<?php
/* TxtHarbor-build:1.5.4 */
/** Browser-isolated administrator sessions. No plaintext tokens in data files. */
function th_admin_cookie_path() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/run.php'));
    return $dir === '/' || $dir === '.' ? '/' : rtrim($dir, '/') . '/';
}
function th_admin_cookie_name() { return 'th_admin_auth'; }
function th_admin_cookie_secure() {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}
function th_admin_set_cookie($token, $persistent = false, $clear = false, $name = null, $path = null) {
    $name = $name ?: th_admin_cookie_name();
    setcookie($name, $clear ? '' : $token, array(
        'expires'=>$clear ? time()-3600 : ($persistent ? time()+30*86400 : 0),
        'path'=>$path ?: th_admin_cookie_path(),
        'secure'=>th_admin_cookie_secure(), 'httponly'=>true,'samesite'=>'Lax'));
}
function th_admin_sessions_path() { return dirname(__DIR__).'/data/admin_sessions.php'; }
function th_admin_load($fp) {
    rewind($fp); $raw = stream_get_contents($fp);
    $prefix = "<?php exit; ?>\n";
    if ($raw === '') return array();
    if (!is_string($raw) || strncmp($raw,$prefix,strlen($prefix))!==0) throw new RuntimeException('管理员会话文件损坏');
    $data=json_decode(substr($raw,strlen($prefix)),true);
    if (!is_array($data)) throw new RuntimeException('管理员会话数据无效');
    return $data;
}
function th_admin_update_sessions($callback) {
    $file=th_admin_sessions_path();
    $fp=@fopen($file,'c+b');
    if (!$fp || !@flock($fp,LOCK_EX)) {
        if ($fp) @fclose($fp);
        throw new RuntimeException('管理员会话文件不可写');
    }
    try {
        $sessions=th_admin_load($fp);
        $now=time();
        foreach ($sessions as $key=>$record) {
            $exp=is_array($record)?($record['exp']??0):0;
            if (!is_string($key) || !is_int($exp) || $exp<$now) unset($sessions[$key]);
        }
        $sessions=$callback($sessions);
        if (!is_array($sessions)) throw new RuntimeException('管理员会话数据无效');
        $payload="<?php exit; ?>\n".json_encode($sessions,JSON_UNESCAPED_SLASHES);
        if (@ftruncate($fp,0)===false || @fseek($fp,0)!==0 || @fwrite($fp,$payload)!==strlen($payload) || !@fflush($fp))
            throw new RuntimeException('保存管理员会话失败');
        @chmod($file,0600);
        return true;
    } finally { @flock($fp,LOCK_UN); @fclose($fp); }
}
function th_admin_authorized() {
    $cookie=$_COOKIE[th_admin_cookie_name()] ?? null;
    if (!is_string($cookie) || !preg_match('/^[a-f0-9]{64}$/D',$cookie)) return false;
    $file=th_admin_sessions_path();
    if (!is_file($file)) return false;
    $fp=@fopen($file,'rb');
    if (!$fp) return false;
    try {
        if (!@flock($fp,LOCK_SH)) return false;
        $sessions=th_admin_load($fp);
        $hash=hash('sha256',$cookie);
        global $web;
        $record=$sessions[$hash]??null;
        return is_array($record) && is_int($record['exp']??null) && $record['exp']>time()
            && is_string($record['pw']??null) && !empty($web['password'])
            && hash_equals($record['pw'],hash('sha256',(string)$web['password']));
    } catch (Throwable $ex) {
        error_log('TxtHarbor auth session read: '.$ex->getMessage()); return false;
    } finally { @flock($fp,LOCK_UN); @fclose($fp); }
}
function th_admin_target($from) { return $from==='admin'?'admin.php':'index.php?admin_run=1'; }
function th_admin_handle_request($web) {
    $act=$_POST['act']??'';
    if ($act==='login' && ($_SERVER['REQUEST_METHOD']??'')==='POST') {
        header('Cache-Control: no-store, private');
        $from=($_POST['login_from']??'')==='admin'?'admin':'index';
        $redirect=th_admin_target($from);
        $password=$_POST['password']??null;$error='';
        if (!is_string($password)||$password==='') $error='empty';
        elseif (!isset($web['password']) || !is_string($web['password']) ||
            !hash_equals($web['password'],substr(sha1(md5($password)),4,32))) $error='invalid';
        else {
            try {
                $token=bin2hex(random_bytes(32));
                $persistent=!empty($_POST['save_cookie']);
                $expiry=time()+($persistent?30*86400:86400);
                th_admin_update_sessions(function($all)use($token,$expiry,$web){
                    $all[hash('sha256',$token)]=array('exp'=>$expiry,'pw'=>hash('sha256',(string)$web['password']));
                    return $all;
                });
                th_admin_set_cookie($token,$persistent);
                // Discard duplicate-path legacy cookies; they are never accepted by new auth.
                foreach(array_unique(array('/',th_admin_cookie_path())) as $path)
                    th_admin_set_cookie('',false,true,'manage',$path);
            } catch (Throwable $ex) {
                $error='storage';error_log('TxtHarbor administrator login: '.$ex->getMessage());
            }
        }
        if($error!=='') $redirect.=(strpos($redirect,'?')===false?'?':'&').'login_error='.$error;
        header('Location: '.$redirect,true,303);exit;
    }
    if(($_GET['act']??'')==='logout') {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');header('Expires: 0');
        $token=$_COOKIE[th_admin_cookie_name()]??'';
        if(is_string($token)&&preg_match('/^[a-f0-9]{64}$/D',$token)) {
            try {
                th_admin_update_sessions(function($all)use($token){unset($all[hash('sha256',$token)]);return $all;});
            }catch(Throwable $ex){
                error_log('TxtHarbor logout: '.$ex->getMessage());
                http_response_code(503);exit('管理员退出状态无法安全写入，请检查 data/admin_sessions.php 权限。');
            }
        }
        foreach(array_unique(array('/',th_admin_cookie_path(),rtrim(th_admin_cookie_path(),'/'))) as $path) {
            if($path==='') continue;
            th_admin_set_cookie('',false,true,th_admin_cookie_name(),$path);
            th_admin_set_cookie('',false,true,'manage',$path);
        }
        unset($_COOKIE[th_admin_cookie_name()],$_COOKIE['manage']);
        header('Location: ./?logged_out=1',true,303);exit;
    }
}
