<?php
/* TxtHarbor-build:1.5.5 */

/*
* 程序名称：162100留言本（吧）
* 程序编码：UTF-8
* 作者：162100.com
* 网址：http://bbs.162100.com
* 声明：仅供代码爱好者学习交流，禁用于商业目的，请保留此版权信息
*/

// JSON posts must not be polluted by PHP warnings, template output, or fatal HTML.
// If an unexpected fatal happens after data has been written, do not claim that
// the post was definitely rejected: the browser must avoid an automatic retry.
$th_json_post = isset($_POST['act'], $_POST['ajax']) &&
    $_POST['act'] === 'write' && $_POST['ajax'] === '1';
$GLOBALS['th_json_answer_sent'] = false;
$GLOBALS['th_publish_stage'] = 'initial';
if ($th_json_post) {
    ob_start();
    register_shutdown_function(function () {
        if (!empty($GLOBALS['th_json_answer_sent'])) return;
        $err = error_get_last();
        if (!$err || !in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) return;
        $ref = substr(hash('sha256', $err['file'].':'.$err['line'].':'.microtime(true)), 0, 10);
        error_log('TxtHarbor publish failed ['.$ref.'] stage='.$GLOBALS['th_publish_stage'].' '.$err['message'].' at '.$err['file'].':'.$err['line']);
        if (ob_get_level() > 0) @ob_end_clean();
        if (headers_sent()) return;
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
        echo json_encode(array('ok'=>false,
          'message'=>'服务器运行错误（编号 '.$ref.'，阶段 '.$GLOBALS['th_publish_stage'].'）。请先查看留言列表避免重复发布，管理员可在 PHP 日志按编号定位。'),
          JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    });
}
header("content-type: text/html; charset=UTF-8");
// A half-installed release must not become a PHP fatal during publish.
if ($th_json_post) {
    foreach (array('config.php','inc/entries.php','inc/admin_auth.php','inc/github_update.php') as $required) {
        if (!is_file(__DIR__.'/'.$required)) {
            if (ob_get_level()>0) @ob_end_clean();
            http_response_code(503);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('ok'=>false,'message'=>'程序文件缺失：'.$required.'。请重新安装完整代码更新包。'),JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
require_once('config.php');
require_once('inc/entries.php');
require_once('inc/admin_auth.php');
require_once('inc/github_update.php');
$web = text_v2_config_utf8($web);
if (!function_exists('th_admin_handle_request')) {
    if ($th_json_post) {
        if (ob_get_level()>0) @ob_end_clean();
        http_response_code(503); header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('ok'=>false,'message'=>'管理员认证文件尚未同步，请安装完整升级包。'),JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(503); exit('站点认证组件未同步，请管理员修复安装。');
}
th_admin_handle_request($web);
header('Content-Type: text/html; charset=UTF-8');

// The editor uses an explicit JSON acknowledgement, while the original
// iframe-based administrative actions continue to use the legacy response.
$ajax_write = isset($_POST['act'], $_POST['ajax']) &&
    $_POST['act'] === 'write' && $_POST['ajax'] === '1';
function write_finish($ok, $message, $url = '', $status = 400, $share_url = '') {
    global $ajax_write;
    if ($ajax_write) {
        $GLOBALS['th_json_answer_sent'] = true;
        if (ob_get_level() > 0) @ob_end_clean();
        http_response_code($ok ? 200 : $status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, private');
        echo json_encode(array('ok' => (bool)$ok, 'message' => $message,
            'url' => $ok ? $url : '', 'share_url' => $ok ? $share_url : ''), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    $js_message = json_encode($message, JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!$ok) {
        die('<script>main.addSubmitSafe_("i", '.$js_message.');</script>' .
            htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</body></html>');
    }
    $js_url = json_encode($url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    die('<script>main.addSubmitSafe_("ok", '.$js_message.', function () {' .
        'htmlbody.location.replace('.$js_url.');}, 80);</script>' .
        '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">'.
        htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</a></body></html>');
}

/*函数集*/
function md162100($str) {
  return substr(sha1(md5($str)), 4, 32);
}

//获取ip
function user_ip() {
    if(getenv('HTTP_CLIENT_IP')){
      $ip=getenv('HTTP_CLIENT_IP'); 
    }elseif(getenv('HTTP_X_FORWARDED_FOR')){
      $ip=getenv('HTTP_X_FORWARDED_FOR');
    }elseif(getenv('HTTP_X_FORWARDED')){ 
      $ip=getenv('HTTP_X_FORWARDED');
    }elseif(getenv('HTTP_FORWARDED_FOR')){
      $ip=getenv('HTTP_FORWARDED_FOR'); 
    }elseif(getenv('HTTP_FORWARDED')){
      $ip=getenv('HTTP_FORWARDED');
    }elseif($_SERVER['REMOTE_ADDR']=str_replace('::1', '', $_SERVER['REMOTE_ADDR'])){ 
      $ip=$_SERVER['REMOTE_ADDR'];
    }else{ 
      $ip = @file_get_contents('http://www.162100.com/readonly/weather/ip.php');
    }
    return preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip) ? $ip : '127.0.0.1';
}

//删除目录
function deldir($dir, $keep_root=false) {
  if (empty($dir) || !file_exists($dir)) {
    return;
  }
  $n = 0;
  if (is_file($dir)) {
    @unlink($dir);
    $n++;
    return true;
  }
  if ($fp = @opendir($dir)) {
    while (false !== ($file = @readdir($fp))) {
      if ($file != '.' && $file != '..') {
        if (is_dir($dir.'/'.$file)) {
          deldir($dir.'/'.$file);
        } else {
          @unlink($dir.'/'.$file);
          $n++;
        }
      }
    }
    if (readdir($fp) == false) {
      @closedir($fp);
      if ($keep_root == false) {
        @rmdir($dir);
      }
      return $n;
    }
  }
}

//过滤
function s_rand($l) {
  $rand = '';
  $c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ-abcdefghijklmnopqrstuvwxyz_0123456789';
  $n = strlen($c) - 1;
  for ($i = 0; $i < $l; $i++) {
    $rand .= $c[mt_rand(0, $n)];
  }
  return strtolower($rand);
}

//过滤提交
function text_filter($text, $br = '>', $no_filter_key = true) {
	$text = stripslashes($text);
	$text = preg_replace('/&#820[3-7];|&lrm;|&rlm;|&#x200[e-f];|&#x202[a-e];|&#xFEFF;/i', '', $text);
	//$text = htmlspecialchars($text, ENT_NOQUOTES | ENT_XHTML, "gb2312");
	$text = str_replace('&', '&amp;', $text);
	$text = str_replace('<', '&lt;', $text);
	$text = str_replace('>', '&gt;', $text);
	
	$text = preg_replace("/[\r\n]+/", $br, $text);
	$text = preg_replace('/(\s+|\&nbsp\;||	)+/', ' ', $text);
	$text = trim($text);
	$text = str_replace('|', '&#124;', $text);
	//$text = str_replace('\'', '&#039;', $text);
	//$text = preg_replace('/[\r\n]+/', '<br />', $text);
	//$text = str_replace('\\', '&#92;', $text);
	//$text = str_replace('?', '&#63;', $text);
	//$text = str_replace('*', '&#42;', $text);
	//$text = str_replace(':', '&#58;', $text);
	if ($no_filter_key == true) {
		filter_badwords($text);
	}
	return $text;
}
//内容提交检查
function filter_result_check($text) {
	$text = preg_replace('/ |&nbsp;|\r?\n|<br(\s+\/)?>/i', '', $text);
	while (preg_match('/<([a-z]+)[^><]*>([^><]*)<\/\1>/i', $text, $mat)) {
		$text = str_replace($mat[0], $mat[2], $text);
	}
	return $text;
}

//服务器端对内容检验
function text_check($text, $len_limi, $type = '内容') {
	global $web;
	$min_size = defined('MIN_SIZE') && is_numeric(MIN_SIZE) && MIN_SIZE > 0 ? MIN_SIZE : 7;
	if (filter_result_check($text) == '') {
		die('<script> main.addSubmitSafe_(\'i\', \'提交被拒绝！问题分析：1、'.$type.'没添写 2、'.$type.'并未输入有效的文本内容，如：你只输入了空格、空html标签等\'); </script>
		提交被拒绝！问题分析：1、'.$type.'没添写 2、'.$type.'并未输入有效的文本内容，如：你只输入了空格、空html标签等</body></html>');
	}
	$n = strlen($text);
	if ($n < $min_size) die('<script> main.addSubmitSafe_(\'i\', \''.($type == '题目' ? ($web['subj_open'] != 11 ? '若填写题目，' : $type) : $type).'请'.$min_size.'个字符起\'); </script>
		提交被拒绝！'.$type.'请'.$min_size.'个字符起</body></html>');
	if (abs($len_limi) > 0) {
		if ($n > $len_limi) die('<script> main.addSubmitSafe_(\'i\', \'提交被拒绝！'.$type.'字数'.$n.'超出当前限量'.$len_limi.'\'); </script>
			提交被拒绝！'.$type.'字数'.$n.'超出当前限量'.$len_limi.'</body></html>');
	}
}

//过滤词汇
function filter_badwords($text) {
	$badwords = @file('inc/badword.php');
    if ($badwords) $badwords = array_map('text_v2_to_utf8', $badwords);
	if ($badwords) {
		if ($text) {
			$badwords = array_filter(preg_replace('/<\?[^\n\r]*\?>[\n\r\s]*/', '', $badwords));
			foreach($badwords as $badword) {
				$badword = trim($badword);
				if ($badword != '') {
					$badword = preg_replace('/[0-9a-z]|[\x80-\xff]{2}/iU', '$0([^0-9a-z\x80-\xff]|(&[#a-z0-9]+;)| )*', $badword);
					$badword = preg_replace('/\(\[\^0-9a-z\\\x80-\\\xff\]\|\(&\[#a-z0-9\]\+;\)\| \)\*$/i', '', $badword);
					$badword = preg_replace('/^\(\[\^0-9a-z\\\x80-\\\xff\]\|\(&\[#a-z0-9\]\+;\)\| \)\*/i', '', $badword);
					if (preg_match('/'.$badword.'/i', $text, $matches)) {
						$output = preg_replace('/[0-9a-z]|[\x80-\xff]{2}/iU', '$0　', $matches[0]);
                        if (isset($_POST['act']) && $_POST['act'] === 'write') {
                            write_finish(false, '拒绝！内容有禁用词汇，请修改后重新提交。', '', 422);
                        }
					die('<script> main.addSubmitSafe_(\'i\', \'拒绝！内容有禁用词汇“'.$output.'”。请返回检查\'); </script>
						拒绝！内容有禁用词汇“'.$output.'”。请返回检查</body></html>');
					}
					unset($matches);
				}
			}
		}
	}
}

//锁定文件
function f_lock($fp) {
	static $step_lock = 0;
	if ($step_lock >= 3) {
		return false;
	}
	if (!flock($fp, LOCK_EX | LOCK_NB)) {
		$step_lock++;
		sleep(2);
		f_lock($fp);
	}
	return true;
}

//定位写文件
function file_seek_put_contents($file, $text, $loc = 0, $mode = 'rb+') {
    $fp = @fopen($file, $mode);
    if (!$fp) return false;
    if (!f_lock($fp) || @fseek($fp, $loc) !== 0) {
        @flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    $length = strlen($text);
    $written = 0;
    while ($written < $length) {
        $count = @fwrite($fp, substr($text, $written));
        if ($count === false || $count === 0) break;
        $written += $count;
    }
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $written === $length;
}

//找可用的空闲空间
function find_space($strlen) {
	global $GET_POS, $DEL_SPA;
	$DEL_SPA = array();
	if ($fp = @fopen('data/space.txt', 'rb')) {
		f_lock($fp);
		while (!feof($fp)) {
    	    if ($line = fread($fp, INDEX_WIDTH)) { //一步一步
				$here = ftell($fp) - INDEX_WIDTH;
				if (strstr($line, '|')) {
					list($l, $r) = @explode('|', $line);
					$l = trim($l);
					$r = trim($r);
					if ((is_numeric($l) && $l > 0) && (is_numeric($r) && $r > 0)) {
						if ($l < 15 || $r < LIST_SIZE) {
							$DEL_SPA[] = $here;
							continue;
						}
						$diff = $r - $strlen;
						if ($diff >= 0) {
							$GET_POS = array($l, $here, $l + $strlen, $diff);
							if ($diff < LIST_SIZE) {
								$DEL_SPA[] = $here;
								break;
							}
							//彻底遍历一遍data/space.txt
							//break;
						}
					}
				}
			}
		}
		if (isset($GET_POS) && is_array($GET_POS)) {
			//if ($GET_POS[0] >= 15) {
				$loc = $GET_POS[0];
			//}
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	if (!isset($loc)) {
		$loc = file_size('data/file.php');
	}
	return $loc;
}

//优化处理空闲空间
function comb_space() {
	global $GET_POS, $DEL_SPA, $ADD_SPA;
	$b1 = is_array($GET_POS) && count($GET_POS) == 4 ? true : false;
	$b2 = is_array($DEL_SPA) && count($DEL_SPA) > 0 ? true : false;
	$b3 = is_array($ADD_SPA) && count($ADD_SPA) > 0 ? true : false;
	if ($b1 || $b2 || $b3) {
		if ($fp = @fopen('data/space.txt', 'rb+')) {
			f_lock($fp);
			if ($b1) {
				if (is_numeric($GET_POS[1]) && $GET_POS[1] >= 0 && $GET_POS[1] % INDEX_WIDTH == 0 && 
					is_numeric($GET_POS[2]) && $GET_POS[2] >= 15 &&
					is_numeric($GET_POS[3]) && $GET_POS[3] >= LIST_SIZE
				) {
					fseek($fp, $GET_POS[1], SEEK_SET);
					fwrite($fp, str_pad($GET_POS[2].'|'.$GET_POS[3], INDEX_WIDTH, ' '));
					unset($GET_POS);
				}
			}
			if ($b2) {
				$reg = str_pad('', INDEX_WIDTH, 'X');
				foreach ($DEL_SPA as $pos) {
					if (is_numeric($pos) && $pos >= 0 && ($pos % INDEX_WIDTH == 0)) {
						fseek($fp, $pos, SEEK_SET);
						fwrite($fp, $reg);
					}
				}
				unset($DEL_SPA, $pos);
				fseek($fp, 0, SEEK_END);
				$eof = ftell($fp);
				$pos = $size = 0;
				while ($pos < $eof) {
					fseek($fp, $pos, SEEK_SET);
        			$content = fread($fp, INDEX_WIDTH * 200);
					$pos = ftell($fp);
					$content = str_replace($reg, '', $content);
					if ($content != '') {
						fseek($fp, $size, SEEK_SET);
						fwrite($fp, $content);
					}
					$size += strlen($content);
					unset($content);
				}
				if ($size < $pos) {
					ftruncate($fp, $size - $size % INDEX_WIDTH);
				}
			}
			if ($b3) {
				foreach ($ADD_SPA as $spa) {
					if (strlen($spa) == INDEX_WIDTH) {
						list($loc, $len) = explode('|', $spa);
						$len = abs(trim($len));
						if (is_numeric($loc) && $loc >= 15 &&
							is_numeric($len) && $len >= LIST_SIZE) {
							fseek($fp, 0, SEEK_END);
							fwrite($fp, $spa);
						}
					}
				}
				unset($ADD_SPA);
			}
			flock($fp, LOCK_UN);
			fclose($fp);
		}
	}
}

if (!$ajax_write) {
?><!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title></title>
<script language="javascript" type="text/javascript" src="inc/main.js"></script>
</head>
<body>
<?php
}
if (!isset($_POST['act'])) $_POST['act'] = '';
if (!isset($_REQUEST['act'])) $_REQUEST['act'] = '';
define('ADMIN_POWER', th_admin_authorized());

//填写
if (isset($_POST['act']) && $_POST['act'] === 'write') :
    $GLOBALS['th_publish_stage'] = 'integrity';
    if (!function_exists('th_runtime_health')) write_finish(false, '更新组件版本不一致，请重新安装完整升级包。', '', 503);
    $runtime = th_runtime_health();
    if (!$runtime['ok']) {
        write_finish(false, '网站程序文件未同步（'.implode('、', array_slice($runtime['issues'],0,4)).'），请到版本更新中心重新安装当前版本。', '', 503);
    }
    $GLOBALS['th_publish_stage'] = 'validation';

    // A valid admin session or DAY_SIZE=0 bypasses daily limits entirely.
    // Do not access $today/$fd in those cases (PHP 8 throws ValueError).
    $ip = user_ip();
    $track_daily = !ADMIN_POWER && defined('DAY_SIZE') && is_numeric(DAY_SIZE) && DAY_SIZE > 0;
    $today = $track_daily ? gmdate('Ymd', time() + floatval($web['time_pos']) * 3600) : '';
    $fd = $track_daily ? '__temp__/'.$today.'/'.$ip.'.txt' : '';
    if ($track_daily && is_file($fd)) {
        $n = abs((int)@file_get_contents($fd));
        if ($n >= DAY_SIZE) {
            write_finish(false, '已达到今日发表上限：'.DAY_SIZE.' 条', '', 429);
        }
    }

    if (!is_dir('data') && !@mkdir('data', 0775, true)) {
        write_finish(false, '数据库目录无法创建，请检查 data 目录权限', '', 500);
    }
    if (!file_exists('data/file.php') && @file_put_contents('data/file.php', '<?php die(); ?>', LOCK_EX) === false) {
        write_finish(false, '数据库文件无法创建，请检查目录写权限', '', 500);
    }
    if (!file_exists('data/index.txt') && @file_put_contents('data/index.txt', '', LOCK_EX) === false) {
        write_finish(false, '数据库索引无法创建，请检查目录写权限', '', 500);
    }
    if (!file_exists('data/space.txt') && @file_put_contents('data/space.txt', '', LOCK_EX) === false) {
        write_finish(false, '数据库空间索引无法创建，请检查目录写权限', '', 500);
    }
	
	$fs = file_size('data/index.txt');
	if ($fs % INDEX_WIDTH != 0) {
        write_finish(false, '数据库索引结构异常：请检查 INDEX_WIDTH 配置与 index.txt 文件，不要继续提交。', '', 500);
	}
	
    $selected_area = isset($_POST['posttype']) && is_scalar($_POST['posttype']) ? abs((int)$_POST['posttype']) : 0;
    $posttype = isset($web['subarea'][$selected_area]) ? $selected_area : 0;

	$subj = isset($_POST['subject']) && is_string($_POST['subject']) ? $_POST['subject'] : '';
    if ($subj !== '') {
        $problem = text_v2_problem($subj, SUBJ_SIZE, '题目');
        if ($problem !== '') write_finish(false, $problem, '', 422);
	} else {
		if ($web['subj_open'] == 11) {
            write_finish(false, '题目不能为空', '', 422);
		}
	}
    $text = isset($_POST['content']) && is_string($_POST['content']) ? $_POST['content'] : '';
    $problem = text_v2_problem($text, WORD_SIZE, '内容');
    if ($problem !== '') write_finish(false, $problem, '', 422);
    // Respect any configured forbidden words without changing the stored data.
    filter_badwords($subj);
    filter_badwords($text);

	if ($web['imcode_close'] !== 1) {
		if (isset($_COOKIE['regimcode_162100bbs']) && !ADMIN_POWER) {
			if ($_POST['imcode'] != $_COOKIE['regimcode_162100bbs']) {
                write_finish(false, '验证码不符，请重新输入', '', 422);
			}
		}
	}

    // Private settings are committed before publishing the record in index.txt.
    $expiry = isset($_POST['expiry']) && is_string($_POST['expiry']) ? $_POST['expiry'] : 'never';
    $expiration = 0;
    if ($expiry === '1d') $expiration=time()+86400;
    elseif ($expiry === '7d') $expiration=time()+7*86400;
    elseif ($expiry === '30d') $expiration=time()+30*86400;
    elseif ($expiry === 'custom') {
        $raw_date=$_POST['expiry_custom']??'';
        $date=is_string($raw_date)?DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw_date,new DateTimeZone('Asia/Shanghai')):false;
        if (!$date || $date->getTimestamp()<=time()) write_finish(false,'请选择未来的过期时间（北京时间）','',422);
        $expiration=$date->getTimestamp();
    } elseif ($expiry !== 'never') write_finish(false,'有效期参数不正确','',422);
    $password = isset($_POST['access_password']) && is_string($_POST['access_password']) ? $_POST['access_password'] : '';
    if ($password !== '' && (!text_v2_valid_utf8($password) || text_v2_length($password)<4 || strlen($password)>128))
        write_finish(false, '访问密码至少 4 个字符，不超过 128 字节', '', 422);
    if (isset($_POST['access_password']) && !is_string($_POST['access_password']))
        write_finish(false,'访问密码格式不正确','',422);
    $password_digest = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '';
    if ($password !== '' && (!is_string($password_digest) || $password_digest === ''))
        write_finish(false,'服务器无法安全生成密码哈希，留言未发布','',500);
    $GLOBALS['th_publish_stage'] = 'metadata-preflight';
    // Preflight the new metadata store BEFORE touching legacy message bytes.
    // Older sites may allow editing existing data files but forbid creating
    // entry_meta.php or lock files in the data/ directory.
    try {
        th_metadata_preflight();
    } catch (Throwable $error) {
        write_finish(false, '无法发布：'.$error->getMessage(), '', 503);
    }
    // A global advisory lock also serializes new writes that allocate old free slots.
    $write_lock = @fopen('data/.th-write.lock', 'c');
    if (!$write_lock || !@flock($write_lock, LOCK_EX)) write_finish(false,'写入锁定失败，请重试','',503);
	$strput = time().''.($web['ip_open'] !== 0 ? $ip : '').'|'.$posttype.'|'.text_v2_pack($subj, $text).'|'.($web['review'] == 1 && !ADMIN_POWER ? '|' : ' ').'';
	$strlen = strlen($strput);
	
    $GLOBALS['th_publish_stage'] = 'legacy-slot';
	try { $loc = find_space($strlen); }
    catch (Throwable $error) {
        write_finish(false, '数据库空间索引读取失败，留言未发布。请检查 data/space.txt。', '', 503);
    }

	if ($loc > INT_MAX) {
        write_finish(false, '数据文件容量达到系统上限', '', 507);
	}
	
	if (file_size('data/index.txt') > INT_MAX) {
        write_finish(false, '数据库索引容量达到系统上限', '', 507);
	}

    $GLOBALS['th_publish_stage'] = 'record-write';
	//处理数据文件
	if (!file_seek_put_contents('data/file.php', $strput, $loc)) {
        write_finish(false, '无法保存留言，服务器数据库目录可能没有写权限', '', 500);
	}
    $new_id = th_entry_id($loc, $strlen, explode('|', $strput));
    unset($text, $strput);
    $GLOBALS['th_publish_stage'] = 'metadata-commit';
    try {
        // The metadata sidecar is an explicit part of this transaction: never expose a
        // password-protected or expiring entry before its access restrictions exist.
        th_metadata_update(function ($all) use ($new_id, $expiration, $password_digest) {
            $all[$new_id] = array('expires_at'=>$expiration,
                'pinned'=>false,'media'=>array(),
                'password_hash'=>$password_digest);
            return $all;
        });
    } catch (Throwable $error) {
        write_finish(false, '留言未发布：权限设置保存失败（'.$error->getMessage().'）', '', 503);
    }

    $GLOBALS['th_publish_stage'] = 'index-commit';
	//处理索引文件
    if (@file_put_contents('data/index.txt', str_pad($loc.'|'.$strlen, INDEX_WIDTH, ' '), LOCK_EX | FILE_APPEND) === false) {
        write_finish(false, '无法保存留言，服务器数据库目录可能没有写权限', '', 500);
	}
	
    $GLOBALS['th_publish_stage'] = 'committed';
    // The index append is the durable commit. Compaction is best effort: an
    // exception here must not turn a successful post into a misleading 500.
    try { comb_space(); }
    catch (Throwable $error) { error_log('TxtHarbor optional free-space maintenance failed: '.$error->getMessage()); }
    @flock($write_lock, LOCK_UN); @fclose($write_lock);

    if ($track_daily) {
        // Daily statistics are not part of the database commit; missing files
        // must never turn a successfully saved message into an HTTP 500.
        if (!is_dir('__temp__/'.$today)) @mkdir('__temp__/'.$today, 0775, true);
        if (is_dir('__temp__/'.$today)) {
            $n = (is_file($fd) ? abs((int)@file_get_contents($fd)) : 0) + 1;
            @file_put_contents($fd, (string)$n, LOCK_EX);
            if ($date_all = @glob('__temp__/*', GLOB_ONLYDIR)) {
                foreach ($date_all as $date) {
                    if ($date !== '__temp__/'.$today) deldir($date);
                }
            }
        }
    }

    // A JSON response proves the index append succeeded before redirect.
    // The client navigates to the exact record's unique index key.
    if ($password !== '' && $password_digest !== '') {
        try {
            th_share_remember($new_id,$password,$password_digest);
        } catch(Throwable $e) {
            error_log('TxtHarbor: cannot remember temporary share link for browser session: '.$e->getMessage());
        }
    }
    $posted_url = './?focus='.$loc.'-'.$strlen;
    write_finish(true, '发布成功，正在打开刚刚提交的留言…', $posted_url, 200, 'entry.php?id='.$new_id);




//审核通过（单条）
elseif ($_GET['act'] == 'chk') :
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\',\'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	if (empty($_GET['id']) || !preg_match('/^(\d+)\|(\d+)/', $_GET['id'], $m)) {
		die('<script> main.addSubmitSafe_(\'i\',\'参数缺失或错误！\'); </script>
		参数缺失或错误！</body></html>');
	}
	list( , $loc, $len) = $m;
	$rel = 0;
	if ($fp = @fopen('data/file.php', 'rb+')) {
		f_lock($fp);
		fseek($fp, $loc + $len - 2, SEEK_SET);
		if (fread($fp, 2) == '||') {
			fseek($fp, -1, SEEK_CUR);
			$rel += fwrite($fp, ' ');
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	if ($rel == 0) {
		die('<script> main.addSubmitSafe_(\'i\',\'处理失败！可能是目录权限不足\'); </script>
		处理失败！可能是目录权限不足</body></html>');
	} else {
		die('<script>
		var chkchk = htmlbody.document.getElementById("chk_'.$_GET['id'].'");
		if (chkchk != null) {
			chkchk.style.display = "none"; 
			chkchk.innerHTML = ""; 
			chkchk.parentNode.removeChild(chkchk);
		}
		main.addSubmitSafe_(\'ok\',\'审核成功！\'); </script>
		审核成功！</body></html>');
	}
	

//审核通过
elseif ($_POST['act'] == 'chk') :
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\',\'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	$_POST['id'] = array_unique(array_filter((array) $_POST['id']));
	if (count($_POST['id']) == 0) {
		die('<script> main.addSubmitSafe_(\'i\',\'参数缺失！或没有选中项\'); </script>
		参数缺失！或没有选中项</body></html>');
	}
	$rel = 0;
	foreach($_POST['id'] as $id) {
		if (preg_match('/^(\d+)\|(\d+)/', $id, $m)) {
			list( , $loc, $len) = $m;
			if ($fp = @fopen('data/file.php', 'rb+')) {
				f_lock($fp);
				fseek($fp, $loc + $len - 2, SEEK_SET);
				if (fread($fp, 2) == '||') {
					fseek($fp, -1, SEEK_CUR);
					if (fwrite($fp, ' ')) {
						++$rel;
						echo '<script> 
						var chkchk = htmlbody.document.getElementById("chk_'.$id.'");
						if (chkchk != null) {
							chkchk.style.display = "none"; 
							chkchk.innerHTML = ""; 
							chkchk.parentNode.removeChild(chkchk);
						}
						</script>';
						
					}
				}
				flock($fp, LOCK_UN);
				fclose($fp);
			}
		}
	}
	if ($rel == 0) {
		die('<script> main.addSubmitSafe_(\'i\',\'处理失败！可能是目录权限不足\'); </script>
		处理失败！可能是目录权限不足</body></html>');
	} else {
		die('<script> main.addSubmitSafe_(\'ok\',\'审核成功！共处理'.$rel.'条\'); </script>
		审核成功！共处理'.$rel.'条</body></html>');
	}


//删除
elseif ($_POST['act'] == 'del') :
	if (!file_exists('data/space.txt')) file_put_contents('data/space.txt', '', LOCK_EX);
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\',\'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	if (!isset($_POST['delete_token']) || !is_string($_POST['delete_token']) || !hash_equals(text_v2_delete_token(), $_POST['delete_token'])) {
		die('<script>main.addSubmitSafe_(\'i\', \'安全验证失败，请刷新留言页面重试\');</script>安全验证失败</body></html>');
	}
    $th_delete_lock = @fopen('data/.th-write.lock','c');
    if (!$th_delete_lock || !@flock($th_delete_lock, LOCK_EX)) die('留言数据库正在被修改，请稍后重试');
	$fs = file_size('data/index.txt');
	if ($fs % INDEX_WIDTH != 0) {
		die('<script> main.addSubmitSafe_(\'i\', \'程序出错！你可能中途修改了配置文件或数据库索引文件，导致索引步长与配置不符，请恢复上次正确的配置\'); </script>
		程序出错！你可能中途修改了配置文件或数据库索引文件，导致索引步长与配置不符，请恢复上次正确的配置</body></html>');
	}
    // Resolve stable IDs before the legacy index compaction removes the records.
    $th_deleted_ids = array();
    try {
        foreach (th_entries_scan() as $item) {
            if (in_array(th_entry_key($item), (array)$_POST['id'], true)) $th_deleted_ids[]=$item['id'];
        }
    } catch (Throwable $error) {
        die('<script>main.addSubmitSafe_("i","留言保护数据不可用，删除已取消");</script>删除已取消');
    }
	$_POST['id'] = array_unique(array_filter((array) $_POST['id']));
	if (count($_POST['id']) == 0) {
		die('<script> main.addSubmitSafe_(\'i\',\'参数缺失！或没有选中项\'); </script>
		参数缺失！或没有选中项</body></html>');
	}
	
	foreach($_POST['id'] as $id) {
		if (strstr($id, '|')) {
		    list($loc, $len, ) = explode('|', $id);
			if (is_numeric($loc) && $loc >= 15 && 
				is_numeric($len) && $len >= LIST_SIZE && INDEX_WIDTH - strlen($loc.'|'.$len) >= 0) {
				$ADD_SPA[] = str_pad($loc.'|'.$len, INDEX_WIDTH, ' ');
			}
		}
	}
	if (isset($ADD_SPA) && is_array($ADD_SPA) && count($ADD_SPA) > 0) {
		$reg = '/'.implode('|', str_replace('|', '\|', $ADD_SPA)).'/';
		if ($fp = @fopen('data/index.txt', 'rb+')) {
			f_lock($fp);
			fseek($fp, 0, SEEK_END);
			$eof = ftell($fp);
			$pos = $size = 0;
			while ($pos < $eof) {
				fseek($fp, $pos, SEEK_SET);
        		$content = fread($fp, INDEX_WIDTH * 200);
				$pos = ftell($fp);
				$content = preg_replace($reg, '', $content);
				if ($content != '') {
					fseek($fp, $size, SEEK_SET);
					fwrite($fp, $content);
				}
				$size += strlen($content);
				unset($content);
			}
			if ($size < $pos) {
				ftruncate($fp, $size - $size % INDEX_WIDTH);
			}
			flock($fp, LOCK_UN);
			fclose($fp);
		} else {
			die('<script> main.addSubmitSafe_(\'i\',\'写入索引库失败！目录写权限不足\'); </script>
		写入索引库失败！目录写权限不足</body></html>');
		}
	}
	
	//处理空闲库
	comb_space();
    // Never drop access restrictions before the index deletion has succeeded.
    if ($th_deleted_ids) {
        try {
            th_metadata_update(function($all) use($th_deleted_ids) {
                foreach($th_deleted_ids as $id) unset($all[$id]);
                return $all;
            });
        } catch (Throwable $e) {
            die('<script>main.addSubmitSafe_("i","留言索引已删除，但扩展信息清理失败，请联系管理员检查 data/entry_meta.php");</script>扩展信息清理失败');
        }
    }

	
	if (file_size('data/index.txt') == 0) {
		file_put_contents('data/file.php', '<?php die(); ?>', LOCK_EX);
		file_put_contents('data/space.txt', '', LOCK_EX);
	}
	
    @flock($th_delete_lock, LOCK_UN); @fclose($th_delete_lock);
	die('<script> main.addSubmitSafe_(\'ok\', \'删除完成\', \'htmlbody.location.reload();\', 80); </script>
	<a href="./">删除完成</a></body></html>');


      
// 回复写入扩展元数据，而非改写旧记录/指针，保证永久分享链接稳定。
elseif ($_POST['act'] == 'reply') :
    if (!ADMIN_POWER) die('<script>main.addSubmitSafe_("i","管理权限不足");</script>权限不足');
    if (!isset($_POST['delete_token']) || !is_string($_POST['delete_token']) || !hash_equals(text_v2_delete_token(), $_POST['delete_token']))
        die('<script>main.addSubmitSafe_("i","验证失败，请刷新后重试");</script>安全验证失败');
    $reply_key = isset($_POST['reply_id']) && is_string($_POST['reply_id']) ? $_POST['reply_id'] : '';
    $reply_text = isset($_POST['content']) && is_string($_POST['content']) ? $_POST['content'] : '';
    $reply_problem = text_v2_problem($reply_text, WORD_SIZE, '回复');
    if ($reply_problem !== '') die('<script>main.addSubmitSafe_("i","回复内容不合法");</script>内容不合法');
    filter_badwords($reply_text);
    try {
        $reply_entry = null;
        foreach (th_entries_scan() as $item) { if (th_entry_key($item) === $reply_key) { $reply_entry=$item; break; } }
        if (!$reply_entry || $reply_entry['pending']) throw new RuntimeException('留言不存在或仍在审核');
        th_metadata_update(function($all) use($reply_entry,$reply_text) {
            $state=isset($all[$reply_entry['id']]) && is_array($all[$reply_entry['id']]) ? $all[$reply_entry['id']] : array();
            $state['reply']=$reply_text;
            if(!isset($state['media']))$state['media']=array();
            $all[$reply_entry['id']]=$state;
            return $all;
        });
    }catch(Throwable $e){die('<script>main.addSubmitSafe_("i","回复保存失败");</script>回复保存失败');}
    die('<script>main.addSubmitSafe_("ok","回复已保存","htmlbody.location.reload();",80);</script>回复完成');

// 回复旧逻辑保留备查（已被上面的兼容入口处理，不再运行）。
elseif (false) :
	if (!file_exists('data/space.txt')) file_put_contents('data/space.txt', '', LOCK_EX);
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\', \'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	if (!$_POST['reply_id']) {
		die('<script> main.addSubmitSafe_(\'i\', \'参数缺失！\'); </script>
		参数缺失！</body></html>');
	}
	$fs = file_size('data/index.txt');
	if ($fs > INT_MAX) {
		die('<script> main.addSubmitSafe_(\'i\', \'索引库承载超限！已达到限量值'.INT_MAX.'字符\'); </script>
		索引库承载超限！已达到限量值'.INT_MAX.'字符</body></html>');
	}
	if ($fs % INDEX_WIDTH != 0) {
		die('<script> main.addSubmitSafe_(\'i\', \'程序出错！你可能中途修改了配置文件或数据库索引文件，导致索引步长与配置不符，请恢复上次正确的配置\'); </script>
		程序出错！你可能中途修改了配置文件或数据库索引文件，导致索引步长与配置不符，请恢复上次正确的配置</body></html>');
	}
	list($loc, $len, $pos) = explode('|', $_POST['reply_id']);
	if (!(is_numeric($loc) && is_numeric($len) && is_numeric($pos))) {
		die('<script> main.addSubmitSafe_(\'i\', \'参数错误！（ID非整数）\'); </script>
		参数错误！（ID非整数）</body></html>');
	}
	if ($loc < 15 || $len < LIST_SIZE || ($pos % INDEX_WIDTH != 0)) {
		die('<script> main.addSubmitSafe_(\'i\', \'参数错误！（不合规）\'); </script>
		参数错误！（不合规）</body></html>');
	}
	if (file_get_contents('data/file.php', false, NULL, $loc + $len - 2, 2) == '||') {
		die('<script> main.addSubmitSafe_(\'i\', \'内容审核中，无法回复！\'); </script>
		内容审核中，无法回复！</body></html>');
	}
	if ($loc > INT_MAX) {
		die('<script> main.addSubmitSafe_(\'i\', \'参数传递错误！（主帖记录指针超越数据库极限边缘！）\'); </script>
		参数传递错误！（主帖记录指针超越数据库极限边缘！）</body></html>');
	}
	if (strlen($loc) + 1 + strlen($len) > INDEX_WIDTH) {
		die('<script> main.addSubmitSafe_(\'i\', \'参数传递错误！原记录超载！\'); </script>
		参数传递错误！原记录超载！</body></html>');
	}
	
    $reply = isset($_POST['content']) && is_string($_POST['content']) ? $_POST['content'] : '';
    text_v2_check($reply, WORD_SIZE, '回复');
    filter_badwords($reply);
    $text = 'V2REPLY:'.base64_encode($reply);

	$strlen = $len + strlen($text);

	$fz = file_size('data/file.php');

	if ($loc + $len >= $fz) {
		$loc_re = $loc;
	} else {
		$loc_re = find_space($strlen);
		$ADD_SPA[] = str_pad($loc.'|'.$len, INDEX_WIDTH, ' ');
		$comb_space = true;
	}

	if ($loc_re > INT_MAX) {
		die('<script> main.addSubmitSafe_(\'i\', \'回复指针['.$loc_re.']超越数据库极限['.INT_MAX.']边缘！可尝试减少输入，改到数据库其它位置储存（如有）\'); </script>
		回复指针['.$loc_re.']超越数据库极限['.INT_MAX.']边缘！可尝试减少输入，改到数据库其它位置储存（如有）</body></html>');
	}

	if (strlen($loc_re) + 1 + strlen($strlen) > INDEX_WIDTH) {
		$input_limit = abs($strlen - str_pad(9, INDEX_WIDTH - strlen($loc_re) - 1, 9)); //多输入了这些
		die('<script> main.addSubmitSafe_(\'i\', \'数据库承载超限！可尝试减少输入。经计算你要再减少'.$input_limit.'字符\'); </script>
		数据库承载超限！可尝试减少输入。经计算你要再减少'.$input_limit.'字符</body></html>');
	}
	
	
	//处理数据文件
	$text = file_get_contents('data/file.php', false, NULL, $loc, $len).$text;
	if (!file_seek_put_contents('data/file.php', $text, $loc_re)) {
		die('<script> main.addSubmitSafe_(\'i\', \'写入数据库失败！目录写权限不足\'); </script></script>
		写入数据库失败！目录写权限不足</body></html>');
	}
	unset($text);

	$_reg = str_pad($loc.'|'.$len, INDEX_WIDTH, ' ');
	$reg_ = str_pad($loc_re.'|'.$strlen, INDEX_WIDTH, ' ');
	
	//处理索引文件
	if ($fp = @fopen('data/index.txt', 'rb+')) {
		f_lock($fp);
		fseek($fp, $pos, SEEK_SET);
		if (fread($fp, INDEX_WIDTH) == $_reg) {
			fseek($fp, -INDEX_WIDTH, SEEK_CUR);
			fwrite($fp, $reg_);
		} else {
			fseek($fp, 0, SEEK_END);
			$eof = ftell($fp);
			$pos = 0;
			while ($pos < $eof) {
				fseek($fp, $pos, SEEK_SET);
    	    	$content = fread($fp, INDEX_WIDTH * 200);
				if (strstr($content, $_reg)) {
					$content = str_replace($_reg, $reg_, $content);
					fseek($fp, $pos, SEEK_SET);
					fwrite($fp, $content);
					break;
				}
				unset($content);
				$pos = ftell($fp);
			}
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	} else {
		die('<script> main.addSubmitSafe_(\'i\',\'处理索引库失败！目录写权限不足\'); </script>
		处理索引库失败！目录写权限不足</body></html>');
	}
	
	//优化空闲库
	if (isset($comb_space)) {
		comb_space();
	}

	die('<script> main.addSubmitSafe_(\'ok\', \'回复完成\', \'htmlbody.location.reload();\'); </script>
	<a href="./">回复完成</a></body></html>');



// Administrator authentication and logout are handled before HTML output.
elseif ($_POST['act'] == 'set') :
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\', \'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	if ($_POST['password'] == '') {
		$psw = $web['password'];
	} else {
		if (preg_match('/[\s\r\n]+/', $_POST['password'])) {
			die('<script> main.addSubmitSafe_(\'i\', \'管理密码不能有空格\', \'htmlbody.location.reload();\'); </script>
		<a href="./">管理密码不能有空格</a></body></html>');
		}
		if (strlen($_POST['password']) > 30 || strlen($_POST['password']) < 3) {
			die('<script> main.addSubmitSafe_(\'i\', \'管理密码长度是3-30字符\', \'htmlbody.location.reload();\'); </script>
		<a href="./">管理密码长度是3-30字符</a></body></html>');
		}
		$psw = md162100($_POST['password']);
	}

	$text = '<?php
//@ini_set(\'memory_limit\', \'-1\');
//@ini_set(\'memory_limit\', \'20M\');
@ header("content-type: text/html; charset=UTF-8");
@ini_set(\'display_errors\', false);
@error_reporting(E_ERROR | E_WARNING | E_PARSE);

if (PHP_VERSION < 5) die(\' ERROR! PHP_VERSION 小于 5 \');

$_GET = preg_replace(\'/[\r\n\\\'\"\>\<\&]+/i\', \'\', $_GET);


/*  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  */
/*  ┃ --------数据库file.php--------                       ┃  */
/*  ┃ 时间戳|类别|内容|是否（留空格开启审核）+管理员回复   ┃  */
/*  ┃ 0000000000|X|X|X    总计16字节                       ┃  */
/*  ┃                                                      ┃  */
/*  ┃ --------索引库index.php--------                      ┃  */
/*  ┃ 在file.php中的指针位置|数据长度，如：                ┃  */
/*  ┃ 2147483647|9999      总计约15字节                    ┃  */
/*  ┃                                                      ┃  */
/*  ┃ --------索引补丁库space.txt--------                  ┃  */
/*  ┃ 在file.php中的：空闲数据长度|空闲空间指针位置，如：  ┃  */
/*  ┃ 2147483647|9999                                      ┃  */
/*  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  */


$web[\'cache\'] = '.(!isset($_POST['cache']) || $_POST['cache'] != 0 ? 1 : 0).';
//站点状态
$web[\'site_close\'] = '.(abs($_POST['site_close']) != 1 ? 0 : 1).';
//管理密码 注：忘记密码时以 $web[\'password\'] = \'aa7e25f80cf4f64e990b78a9fc5ebd6c\'; 恢复，然后密码用：admin 登录，再进行设置、更改
$web[\'password\'] = \''.$psw.'\';
//站点名称
$web[\'sitename\'] = \''.(!empty($_POST['sitename']) ? text_filter($_POST['sitename'], '') : '留言吧').'\';
//站点口号
$web[\'sitefirm\'] = \''.(!empty($_POST['sitefirm']) ? text_filter($_POST['sitefirm'], '') : '').'\';
//时区调整
$web[\'time_pos\'] = \''.$_POST['time_pos'].'\';
//每页页码
$web[\'pagesize\'] = '.(is_numeric($_POST['pagesize']) && $_POST['pagesize'] > 0 ? abs($_POST['pagesize']) : 30).';
//排序方式 1倒序 2正序
$web[\'order\']    = '.(abs($_POST['order']) != 1 ? 0 : 1).';
';
	$area_new = array();
	$key = 0;
	$_POST['subarea'] = array_unique(array_filter($_POST['subarea']));
	$area_n = count($_POST['subarea']);
	if ($area_n > 0) {
		if ($area_n >= 9) {
			die('<script> main.addSubmitSafe_(\'i\', \'目前留言分类最多限量10个。想增加分类建设请等待以后版本\'); </script>
		目前留言分类最多限量10个。想增加分类建设请等待以后版本</body></html>');
		}
		foreach ($_POST['subarea'] as $s) {
			text_check($s, 30, '留言分类');
			$area_new[$key++] = text_filter($s);
		}
	}
	$text .= '
//留言分类
$web[\'subarea\'] = '.var_export($area_new, true).';

//PHP_INT_MAX 2147483647
define(\'INT_MAX\', PHP_INT_MAX > 9999999999 ? 9999999999 : PHP_INT_MAX);
//最小数据长度
define(\'LIST_SIZE\', 16);
//留言最少字数
define(\'MIN_SIZE\', '.(is_numeric($_POST['min_size']) && $_POST['min_size'] > 0 ? $_POST['min_size'] : 7).');
//留言主题最多字数
define(\'SUBJ_SIZE\', '.(is_numeric($_POST['subj_size']) && $_POST['subj_size'] <= 100 ? abs($_POST['subj_size']) : 100).');
//单次留言（回复）最多字数
define(\'WORD_SIZE\', 9999);
//索引单位宽度，数据库file.php承载极限2147483647 + WORD_SIZE，约2GB
//define(\'INDEX_WIDTH\', strlen(INT_MAX) + 1 + strlen(WORD_SIZE));
define(\'INDEX_WIDTH\', 15);
//文本区域限高折叠
define(\'MAXHEIGHT_SIZE\', '.(is_numeric($_POST['maxheight_size']) && $_POST['maxheight_size'] > 0 ? $_POST['maxheight_size'] : 0).');
//贴子每日每IP限量
define(\'DAY_SIZE\', '.(is_numeric($_POST['day_size']) && $_POST['day_size'] > 0 ? $_POST['day_size'] : 0).');
//审核 1开启 0关闭
$web[\'review\'] = '.(abs($_POST['review']) != 1 ? 0 : 1).';
//验证码 1关闭 0开启
$web[\'imcode_close\'] = '.(abs($_POST['imcode_close']) != 1 ? 0 : 1).';
//留言IP
$web[\'ip_open\'] = '.($_POST['ip_open'] != 1 ? 0 : ($_POST['ip_show'] == 1 ? 11 : 10)).';
'.(abs($_POST['subj_open']) === 10 || abs($_POST['subj_open']) === 11 ? '//留言主题需要吗？
$web[\'subj_open\'] = '.abs($_POST['subj_open']).';
' : '').'


//管理权限
function confirm_power() {
	return (!empty($_COOKIE[\'manage\']) && file_get_contents(\'data/admin_key.php\') == \'<?php die(); ?>\'.$_COOKIE[\'manage\']) ? true : false;
}
//获取文件大小
function my_filesize($file) {
	$size = 0;
	if ($fp = @fopen($file, \'rb\')) {
		if (is_resource($fp)) {
			if (PHP_INT_SIZE < 8) { // 32bit
				if (0 === fseek($fp, 0, SEEK_END)) {
					$size = 0.0;
					$step = 0x7FFFFFFF;
					while ($step > 0) {
						if (0 === fseek($fp, - $step, SEEK_CUR)) {
							$size += floatval($step);
						} else {
							$step >>= 1;
						}
					}
				}
			} elseif (0 === fseek($fp, 0, SEEK_END)) {// 64bit
				$size = ftell($fp);
			}
		}
		@fclose($fp);
	}
	return $size;
}

function file_size($file) {
	if (!($size = filesize($file))) {
		$size = my_filesize($file);
	}
	clearstatcache();
	return $size;
}

';
	if (file_put_contents('config.php', $text)) {
		die('<script> main.addSubmitSafe_(\'ok\', \'配置成功！\', \'htmlbody.location.reload();\'); </script>
		<a href="./">配置成功！</a></body></html>');
	} else {
		die('<script> main.addSubmitSafe_(\'i\', \'配置失败！请稍后再试\', \'htmlbody.location.reload();\'); </script>
		<a href="./"配置失败！请稍后再试</a></body></html>');
	}




elseif ($_POST['act'] == 'up') :
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\', \'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	if (is_array($_FILES['uploadlogo']) && $_FILES['uploadlogo']['size']) {
		$inis = ini_get_all();
		$uploadmax = $inis['upload_max_filesize'];
		if ($_FILES['uploadlogo']['size'] > 300 * 1024) {
			die('<script> main.addSubmitSafe_(\'i\', \'图片上传不成功！上传的文件请小于30KB\'); </script>
		图片上传不成功！上传的文件请小于300KB</body></html>');
		}
		if (preg_match('/\.(jpg|gif|png)$/i', $_FILES['uploadlogo']['name'], $matches)) {
			if (strtolower($matches[1]) != 'png') {
				die('<script> main.addSubmitSafe_(\'i\', \'图片上传不成功！请选择png格式图片（系统不做转换处理，当前为'.$matches[1].'格式）\'); </script>
		图片上传不成功！请选择png格式图片（系统不做转换处理，当前为'.$matches[1].'格式）</body></html>');
			}
		} else {
			die('<script> main.addSubmitSafe_(\'i\', \'图片上传不成功！请选择png格式图片\'); </script>
		图片上传不成功！请选择png格式图片</body></html>');
		}
		if (!file_exists('img')) {
			mkdir('img');
		}
		if (!file_exists('img')) {
			die('<script> main.addSubmitSafe_(\'i\', \'图片无法上传，上传目录不存在或无法创建\'); </script>
		图片无法上传，上传目录不存在或无法创建</body></html>');
		}
		$img_contents = @file_get_contents($_FILES['uploadlogo']['tmp_name']);
		if (preg_match('/<\?php|eval|POST|base64_decode|base64_encode/i', $img_contents, $m_err)) {
			die('<script> main.addSubmitSafe_(\'i\', \'提示！禁止提交。该文件含有禁止的代码'.str_replace('?', '\?', $m_err[0]).'\'); </script>
		提示！禁止提交。该文件含有禁止的代码'.str_replace('?', '\?', $m_err[0]).'</body></html>');
		}

		if (@move_uploaded_file($_FILES['uploadlogo']['tmp_name'], 'img/logo.'.strtolower($matches[1]))) {
			die('<script> if (htmlbody.document.getElementById(\'logoimg\') != null) { htmlbody.document.getElementById(\'logoimg\').src=\'img/logo.'.strtolower($matches[1]).'?'.time().'\'; } main.addSubmitSafe_(\'ok\', \'图片上传成功（如没变化请刷新缓存）\'); </script>
		图片上传成功（如没变化请刷新缓存）</body></html>');
		} else {
			die('<script> main.addSubmitSafe_(\'i\', \'图片上传不成功！请稍候再试\'); </script>
		图片上传不成功！请稍候再试</body></html>');
		}
	} else {
		die('<script> main.addSubmitSafe_(\'i\', \'图片不存在！路径不正确；或系统出错，请稍候再试\'); </script>
		图片不存在！路径不正确；或系统出错，请稍候再试</body></html>');
	}


//更改分类
elseif ($_POST['act'] == 'area_to') :
	if (!ADMIN_POWER) {
		die('<script> main.addSubmitSafe_(\'i\',\'管理权限不足！\'); </script>
		管理权限不足！</body></html>');
	}
	$_POST['id'] = array_unique(array_filter((array) $_POST['id']));
	if (count($_POST['id']) == 0) {
		die('<script> main.addSubmitSafe_(\'i\',\'参数缺失！或没有选中项\'); </script>
		参数缺失！或没有选中项</body></html>');
	}
	if (!$_POST['area_to'] || !array_key_exists($_POST['area_to'], $web['subarea'])) {
		die('<script> main.addSubmitSafe_(\'i\',\'所选目标类别不存在或出错！\'); </script>
		所选目标类别不存在或出错！</body></html>');
	}


	$rel = 0;
	foreach($_POST['id'] as $id) {
		if (preg_match('/^(\d+)\|(\d+)/', $id, $m)) {
			list( , $loc, $len) = $m;
			if ($fp = @fopen('data/file.php', 'rb+')) {
				f_lock($fp);
				fseek($fp, $loc + 11, SEEK_SET);
				if (fread($fp, 1) != $_POST['area_to']) {
					fseek($fp, -1, SEEK_CUR);
					if (fwrite($fp, $_POST['area_to'])) {
						++$rel;
						echo '<script> 
						var chkchk = htmlbody.document.getElementById("area_'.$id.'");
						if (chkchk != null) {
							chkchk.innerHTML = "'.$web['subarea'][$_POST['area_to']].'"; 
						}
						</script>';
						
					}
				}
				flock($fp, LOCK_UN);
				fclose($fp);
			}
		}
	}
	if ($rel == 0) {
		die('<script> main.addSubmitSafe_(\'i\',\'处理失败！可能是未做更改或目录权限不足\'); </script>
		处理失败！可能是未做更改或目录权限不足</body></html>');
	} else {
		die('<script> main.addSubmitSafe_(\'ok\',\'更改成功！共处理'.$rel.'条\'); </script>
		更改成功！共处理'.$rel.'条</body></html>');
	}

// Legacy online upgrades are deliberately retired. Never fetch old 162100 upgrade packages.
elseif (isset($_REQUEST['act']) && $_REQUEST['act'] == 'upgrade') :
    if (!ADMIN_POWER) die('管理权限不足。</body></html>');
    die('<p>旧升级通道已经停用。请打开 <a href="update.php" target="_top">GitHub 版本更新中心</a>。</p></body></html>');



else :
	die('<script> main.addSubmitSafe_(\'i\', \'错误的命令！\'); </script>
	错误的命令！</body></html>');




endif;








?>
</body>
</html>
