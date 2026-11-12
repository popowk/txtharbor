<?php
//@ini_set('memory_limit', '-1');
//@ini_set('memory_limit', '20M');
@ header("content-type: text/html; charset=UTF-8");
@ini_set('display_errors', false);
@error_reporting(E_ERROR | E_WARNING | E_PARSE);

if (PHP_VERSION < 5) die(' ERROR! PHP_VERSION 小于 5 ');

$_GET = preg_replace('/[\r\n\'\"\>\<\&]+/i', '', $_GET);


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


$web['cache'] = 1;
//站点状态
$web['site_close'] = 0;
//管理密码 注：忘记密码时以 $web['password'] = 'aa7e25f80cf4f64e990b78a9fc5ebd6c'; 恢复，然后密码用：admin 登录，再进行设置、更改
$web['password'] = 'REPLACE_WITH_YOUR_NEW_PASSWORD_HASH';
//站点名称
$web['sitename'] = 'TxtHarbor';
//站点口号
$web['sitefirm'] = '';
//时区调整
$web['time_pos'] = '8';
//每页页码
$web['pagesize'] = 30;
//排序方式 1倒序 2正序
$web['order']    = 1;

//留言分类
$web['subarea'] = array (
  0 => '留言',
);

//PHP_INT_MAX 2147483647
define('INT_MAX', PHP_INT_MAX > 9999999999 ? 9999999999 : PHP_INT_MAX);
//最小数据长度
define('LIST_SIZE', 16);
//留言最少字数
define('MIN_SIZE', 3);
//留言主题最多字数
define('SUBJ_SIZE', 100);
//单次留言（回复）最多字数
define('WORD_SIZE', 9999);
//索引单位宽度，数据库file.php承载极限2147483647 + WORD_SIZE，约2GB
//define('INDEX_WIDTH', strlen(INT_MAX) + 1 + strlen(WORD_SIZE));
define('INDEX_WIDTH', 15);
//文本区域限高折叠
define('MAXHEIGHT_SIZE', 400);
//贴子每日每IP限量
define('DAY_SIZE', 10);
//审核 1开启 0关闭
$web['review'] = 0;
//验证码 1关闭 0开启
$web['imcode_close'] = 1;
//留言IP
$web['ip_open'] = 11;
//留言主题需要吗？
$web['subj_open'] = 10;



//管理权限
function confirm_power() {
	return (!empty($_COOKIE['manage']) && file_get_contents('data/admin_key.php') == '<?php die(); ?>'.$_COOKIE['manage']) ? true : false;
}
//获取文件大小
function my_filesize($file) {
	$size = 0;
	if ($fp = @fopen($file, 'rb')) {
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

