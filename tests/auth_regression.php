<?php
/** Isolated CLI regression for site sessions, browser-scoped admin tokens and logout. */
$base=sys_get_temp_dir().'/th-auth-test-'.bin2hex(random_bytes(6));
@mkdir($base.'/inc',0700,true); @mkdir($base.'/data',0700,true);
foreach(array('admin_auth.php','entries.php','content.php') as $name) {
    if (!@copy(dirname(__DIR__).'/inc/'.$name,$base.'/inc/'.$name)) throw new RuntimeException('Fixture setup failed');
}
function th_assert_auth($value,$label) {
    if (!$value) {fwrite(STDERR,'FAIL: '.$label."\n");exit(1);}
    echo 'PASS: '.$label."\n";
}
register_shutdown_function(function()use($base) {
    $clear=function($path)use(&$clear){if(is_dir($path)){foreach(scandir($path) as $n){if($n!=='.'&&$n!=='..')$clear($path.'/'.$n);}@rmdir($path);}else @unlink($path);};
    $clear($base);
});
$_SERVER['SCRIPT_NAME']='/notes/entry.php';
$_SERVER['DOCUMENT_ROOT']=$base;
require $base.'/inc/admin_auth.php';
require $base.'/inc/entries.php';
$web=array('password'=>'test-config-password-hash');
$token=bin2hex(random_bytes(32));
$token2=bin2hex(random_bytes(32));
$update=function($all)use($token,$token2,$web){
    $exp=time()+3600;$pw=hash('sha256',$web['password']);
    $all[hash('sha256',$token)]=array('exp'=>$exp,'pw'=>$pw);
    $all[hash('sha256',$token2)]=array('exp'=>$exp,'pw'=>$pw);
    return $all;
};
th_admin_update_sessions($update);
$_COOKIE['th_admin_auth']=$token;
$first=th_admin_authorized();
$_COOKIE['th_admin_auth']=$token2;
$second=th_admin_authorized();
th_admin_revoke($token);
$_COOKIE['th_admin_auth']=$token;
$revoked=!th_admin_authorized();
$_COOKIE['th_admin_auth']=$token2;
$preserved=th_admin_authorized();
// The PHP CLI requires a session to start before the first printed PASS line.
ini_set('session.save_path','/proc/does-not-exist/for-txtharbor');
th_access_session();
$name=session_name();
$cookiePath=session_get_cookie_params()['path'];
$sessionPath=session_save_path();
$csrf=th_access_csrf();
$valid=th_access_csrf_valid($csrf);
session_write_close();
th_assert_auth($first && $second,'Two independent admin tokens are accepted');
th_assert_auth($revoked && $preserved,'Revocation only removes the selected browser token');
th_assert_auth($name==='THSESSID' && $cookiePath==='/notes/','Dedicated session cookie and subdirectory scope');
th_assert_auth(is_dir($sessionPath) && strpos($sessionPath,'txtharbor-session-')!==false,'Private session path replaces broken shared-host setting');
th_assert_auth($valid,'Password unlock CSRF is stored in persistent PHP session');
