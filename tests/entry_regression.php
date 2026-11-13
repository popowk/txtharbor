<?php
/** Runs on a dedicated temporary fixture, never touches live data. */
$base=sys_get_temp_dir().'/th-entries-test-'.bin2hex(random_bytes(4));
@mkdir($base.'/inc',0700,true);@mkdir($base.'/data',0700,true);@mkdir($base.'/__temp__',0700,true);
$project=dirname(__DIR__);
foreach(array('content.php','entries.php','markdown.php','entry_view.php') as $p)
    copy($project.'/inc/'.$p,$base.'/inc/'.$p);
register_shutdown_function(function()use($base){
    $walk=function($path)use(&$walk){if(is_dir($path)){foreach(scandir($path)as $name){if($name==='.'||$name==='..')continue;$walk($path.'/'.$name);}@rmdir($path);}else @unlink($path);};$walk($base);
});
define('INDEX_WIDTH',15);
require $base.'/inc/entry_view.php';
th_access_session(); // Web requests start the session before HTML output; mirror that here.
function ok($value,$desc){if(!$value){fwrite(STDERR,"FAIL: $desc\n");exit(1);}echo "PASS: $desc\n";}
$initial='<?php die(); ?>';
file_put_contents($base.'/data/file.php',$initial);
$posts=[
    '1700000000|0|'.text_v2_pack('# Title',"**Bold** and \\server\\docs\n<script>alert(1)</script>").'| ',
    '1700000001|0|'.iconv('UTF-8','GB18030','旧标题<旧内容>第二行').'| ',
];
$index='';$pos=strlen($initial);
foreach($posts as $post){$index.=str_pad($pos.'|'.strlen($post),INDEX_WIDTH,' ');file_put_contents($base.'/data/file.php',$post,FILE_APPEND);$pos+=strlen($post);}
file_put_contents($base.'/data/index.txt',$index);
$entries=th_entries_scan();ok(count($entries)===2,'Read V2 + legacy without schema migration');
$id=$entries[0]['id'];ok(preg_match('/^[a-f0-9]{24}$/D',$id)===1,'Opaque stable ID');
ok(strpos($entries[0]['content'],'\\server\\docs')!==false,'Slash fidelity');
ok($entries[1]['content']==="旧内容\n第二行",'Legacy GBK decode');
ok(th_md_detect($entries[0]['content']),'Markdown recognition');
$future=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->modify('+3 day')->setTime(14,37);
$local=$future->format('Y-m-d');
ok(th_expiry_from_post(array('expiry_date'=>$local,'expiry_hour'=>'14','expiry_minute'=>'37'))===$future->getTimestamp(),
    'Explicit Beijing date + hour + minute parsing');
ok(th_expiry_from_post(array('expiry_custom'=>$local.'T14:37'))===$future->getTimestamp(),
    'Legacy datetime-local custom date stays compatible');
ok(th_expiry_from_post(array('expiry_date'=>'2026-02-30','expiry_hour'=>'14','expiry_minute'=>'37'))===null,
    'Reject impossible calendar dates instead of PHP rollover');
ok(th_expiry_from_post(array('expiry_date'=>$local,'expiry_hour'=>'24','expiry_minute'=>'00'))===null &&
   th_expiry_from_post(array('expiry_date'=>$local,'expiry_hour'=>'23','expiry_minute'=>'60'))===null,
   'Reject out-of-range hour/minute');

$render=th_md_render($entries[0]['content']);
ok(strpos($render,'<strong>Bold</strong>')!==false,'Safe Markdown bold rendering');
ok(strpos($render,'<script>')===false && strpos($render,'&lt;script&gt;')!==false,'Raw HTML never executed');
$other=th_md_render('[Click](javascript:alert(1))');ok(strpos($other,'href=')===false,'Unsafe URL does not render as a link');
th_metadata_update(function($all)use($id){$all[$id]=array('subject'=>'Edited title','content'=>'Edited **markdown**','pinned'=>true,
    'expires_at'=>time()+3600,'password_hash'=>password_hash('secret-abc123',PASSWORD_DEFAULT),'media'=>array());return $all;});
$updated=th_entries_scan();ok($updated[0]['id']===$id && $updated[0]['subject']==='Edited title' && $updated[0]['pinned'],'Edit and pin preserve ID');
// Some hosts allow writes to existing files but disallow creating/replacing
// files in data/. This fallback must retain the sidecar's complete contents.
$metadata_bytes = file_get_contents(th_meta_path());
ok(th_meta_inplace_replace(th_meta_path(), $metadata_bytes) &&
   file_get_contents(th_meta_path()) === $metadata_bytes,
   'Locked in-place metadata fallback preserves file bytes');
$old_mode = fileperms($base.'/data') & 0777;
@chmod($base.'/data', 0555);
if (!is_writable($base.'/data')) {
    th_metadata_update(function($all)use($id){$all[$id]['edited_at']=77;return $all;});
    ok((int)(th_metadata_read()[$id]['edited_at']??0)===77,
       'Update existing sidecar when directory disallows new files');
} else {
    echo "SKIP: Directory permission fallback test requires a non-root runtime\n";
}
@chmod($base.'/data', $old_mode);
// When the site temp directory cannot be created, use an isolated PHP temp directory.
@rename($base.'/__temp__',$base.'/__temp__.bak');
file_put_contents($base.'/__temp__','not a directory');
ok(th_access_rate($id) === true && th_access_rate($id, true) === true,
   'Password validation counters fall back when site temp directory is unavailable');
@unlink($base.'/__temp__');@rename($base.'/__temp__.bak',$base.'/__temp__');
ok(!th_entry_public_content($updated[0]),'Password blocks copied/rendered raw content');
ok(strpos(th_entry_body_html($updated[0]),'Edited **markdown**')===false,'Password blocks Markdown source in HTML');
th_metadata_update(function($all)use($id){$all[$id]['expires_at']=time()-1;return $all;});
ok(th_entry_expired(th_entry_find($id)) && !th_entry_visible(th_entry_find($id)),'Expired entry is not publicly visible');
$unprotected=th_entry_find($id); $unprotected['password_hash']='';
ok(!th_entry_public_content($unprotected),'Expiry also blocks unprotected entries; content is retained for admin');
file_put_contents($base.'/data/file.php',$initial.$posts[0].$posts[1]);
ok(th_entry_find($id)['id']===$id,'ID unchanged across metadata updates');
rename($base.'/data/entry_meta.php',$base.'/data/meta.backup.php');
try{th_entries_scan();ok(false,'Fail closed when metadata disappears');}catch(RuntimeException $e){ok(true,'Fail closed when protected sidecar is missing');}
