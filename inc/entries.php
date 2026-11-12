<?php
/** TxtHarbor v1.5 entry extension. Old byte-indexed record data is read-only. */
require_once __DIR__.'/content.php';
const TH_META_PREFIX = "<?php exit; ?>\n";
function th_meta_path() { return dirname(__DIR__).'/data/entry_meta.php'; }
function th_meta_guard_path() { return dirname(__DIR__).'/data/entry_meta_guard.php'; }
function th_metadata_read() {
    $path = th_meta_path();
    if (!is_file($path)) {
        if (is_file(th_meta_guard_path())) throw new RuntimeException('留言权限数据缺失；已停止访问，请管理员恢复 data/entry_meta.php');
        return array();
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || strncmp($raw, TH_META_PREFIX, strlen(TH_META_PREFIX)) !== 0) {
        throw new RuntimeException('留言权限数据无法读取，已停止访问');
    }
    $data = json_decode(substr($raw, strlen(TH_META_PREFIX)), true);
    if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
        throw new RuntimeException('留言权限数据不完整，已停止访问');
    }
    return $data['entries'];
}
/** Atomic and mutually exclusive sidecar writes, never transform legacy records. */
function th_metadata_update($callback) {
    $dir = dirname(th_meta_path());
    if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('data 目录不可写，无法安全保存留言设置');
    $lock = @fopen($dir.'/.th-meta.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('留言设置文件锁定失败');
    try {
        $entries = th_metadata_read();
        $new = $callback($entries);
        if (!is_array($new)) throw new RuntimeException('无效的留言元数据');
        if (!is_file(th_meta_guard_path()) && @file_put_contents(th_meta_guard_path(), '<?php exit; ?>', LOCK_EX) === false)
            throw new RuntimeException('留言设置安全标记创建失败');
        $json = json_encode(array('version'=>1, 'entries'=>$new), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new RuntimeException('留言设置序列化失败');
        // The temp file is PHP too: accidental web retrieval cannot expose passwords or content.
        $tmp = $dir.'/entry_meta_'.bin2hex(random_bytes(8)).'.php';
        if (@file_put_contents($tmp, TH_META_PREFIX.$json, LOCK_EX) === false) throw new RuntimeException('留言设置写入失败');
        @chmod($tmp, 0600);
        if (!@rename($tmp, th_meta_path())) { @unlink($tmp); throw new RuntimeException('留言设置替换失败'); }
        return $new;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function th_entry_id($loc, $len, $row) {
    return substr(hash('sha256', (string)$loc."\0".(string)$len."\0".(string)($row[0]??'')."\0".(string)($row[2]??'')), 0, 24);
}
function th_entry_find($id, $meta = null) {
    if (!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D', $id)) return null;
    $list = th_entries_scan($meta);
    foreach ($list as $entry) if ($entry['id'] === $id) return $entry;
    return null;
}
/** Scan current index instead of trusting a submitted record offset. */
function th_entries_scan($meta = null) {
    if ($meta === null) $meta = th_metadata_read();
    $base = dirname(__DIR__).'/data';
    $idx = @fopen($base.'/index.txt', 'rb');
    $body = @fopen($base.'/file.php', 'rb');
    if (!$idx || !$body) { if ($idx) fclose($idx); if ($body) fclose($body); return array(); }
    $width = defined('INDEX_WIDTH') ? INDEX_WIDTH : 15;
    $size = @filesize($base.'/index.txt');
    if ($size === false || $size % $width !== 0) { fclose($idx); fclose($body); throw new RuntimeException('留言索引文件损坏'); }
    $out = array();
    for ($pos=0; $pos<$size; $pos+=$width) {
        $line = trim(fread($idx, $width));
        if (!preg_match('/^(\d+)\|(\d+)$/D', $line, $parts)) continue;
        $loc=(int)$parts[1]; $len=(int)$parts[2];
        if ($loc < 15 || $len < 1 || $len > 10000000 || fseek($body, $loc) !== 0) continue;
        $raw = fread($body, $len);
        if (strlen($raw) !== $len) continue;
        $row = explode('|', $raw);
        if (count($row)<3 || !preg_match('/^\d{10}/', $row[0])) continue;
        $id=th_entry_id($loc, $len, $row);
        $props=isset($meta[$id]) && is_array($meta[$id]) ? $meta[$id] : array();
        if (array_key_exists('password_hash', $props) && !is_string($props['password_hash']))
            throw new RuntimeException('密码保护记录无效，已暂停访问');
        if (array_key_exists('expires_at', $props) && (!is_int($props['expires_at']) || $props['expires_at'] < 0))
            throw new RuntimeException('留言有效期记录无效，已暂停访问');
        $content=text_v2_message($row[2]);
        if (isset($props['content']) && is_string($props['content'])) $content['content']=$props['content'];
        if (isset($props['subject']) && is_string($props['subject'])) $content['subject']=$props['subject'];
        $out[] = array('id'=>$id,'loc'=>$loc,'len'=>$len,'pos'=>$pos,'row'=>$row,
            'created_at'=>(int)substr($row[0],0,10), 'subject'=>$content['subject'], 'content'=>$content['content'],
            'pinned'=>!empty($props['pinned']), 'expires_at'=>max(0,(int)($props['expires_at']??0)),
            'password_hash'=>isset($props['password_hash']) && is_string($props['password_hash']) ? $props['password_hash'] : '',
            'edited_at'=>(int)($props['edited_at']??0),
            'reply'=>isset($props['reply']) && is_string($props['reply']) ? $props['reply'] :
                (count($row)===4 && trim($row[3])!=='' ? text_v2_reply($row[3]) : ''),
            'media'=>isset($props['media']) && is_array($props['media']) ? $props['media'] : array(),
            'pending'=>count($row)===5 && trim($row[3])==='' && trim($row[4])==='');
    }
    fclose($idx); fclose($body);
    return $out;
}
function th_is_admin() { return defined('ADMIN_POWER') ? ADMIN_POWER : (function_exists('confirm_power') && confirm_power()); }
function th_entry_expired($entry) {return $entry['expires_at'] > 0 && $entry['expires_at'] <= time();}
function th_access_session() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        ini_set('session.use_strict_mode','1');
        session_set_cookie_params(array('lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax'));
        session_start();
    }
}
function th_entry_unlocked($entry) {
    if (th_is_admin()) return true;
    if ($entry['password_hash'] === '') return true;
    th_access_session();
    return isset($_SESSION['th_unlock'][$entry['id']]) && hash_equals(hash('sha256', $entry['password_hash']), $_SESSION['th_unlock'][$entry['id']]);
}
function th_entry_visible($entry) {
    return th_is_admin() || !th_entry_expired($entry);
}
function th_entry_public_content($entry) {
    return th_entry_visible($entry) && (!$entry['pending'] || th_is_admin()) && th_entry_unlocked($entry);
}
function th_entry_key($entry) {return $entry['loc'].'|'.$entry['len'].'|'.$entry['pos'];}
function th_entry_share($entry) {return 'entry.php?id='.$entry['id'];}
function th_access_csrf() {
    th_access_session();
    if (empty($_SESSION['th_csrf'])) $_SESSION['th_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['th_csrf'];
}
function th_access_csrf_valid($value) {return is_string($value) && hash_equals(th_access_csrf(), $value);}
/** IP + entry aware attempt limit, avoids session-reset bypass. */
function th_access_rate($id, $success = null) {
    $dir=dirname(__DIR__).'/__temp__/password_attempts';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) return false;
    $addr=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    $name=hash('sha256', $id.'|'.$addr);
    $file=$dir.'/'.$name.'.php';
    $fp=@fopen($file,'c+'); if (!$fp || !flock($fp,LOCK_EX))return false;
    try {
        rewind($fp); $raw=stream_get_contents($fp);
        $x=strncmp($raw,TH_META_PREFIX,strlen(TH_META_PREFIX))===0 ? json_decode(substr($raw,strlen(TH_META_PREFIX)),true):null;
        if (!is_array($x) || (int)($x['since']??0) < time()-900) $x=array('since'=>time(),'count'=>0);
        if ($success === null) return $x['count'] < 6;
        $x['count'] = $success ? 0 : $x['count']+1;
        if ($success) $x['since']=time();
        rewind($fp);ftruncate($fp,0);$ok=fwrite($fp,TH_META_PREFIX.json_encode($x))!==false;fflush($fp);return $ok;
    } finally {flock($fp,LOCK_UN);fclose($fp);}
}
/** Future-only external cloud object references, never expose private URLs before auth. */
function th_media_descriptor($provider, $object_id, $name, $mime) {
    if (!is_string($provider)||!preg_match('/^[a-z0-9_-]{2,32}$/D',$provider) ||
        !is_string($object_id) || strlen($object_id)>512 || !is_string($name) || !is_string($mime))
        throw new InvalidArgumentException('媒体描述符不合法');
    return array('provider'=>$provider,'object_id'=>$object_id,'filename'=>$name,'mime'=>$mime);
}
