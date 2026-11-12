<?php

/*
* 程序名称：162100留言本（吧）
* 程序编码：UTF-8
* 作者：162100.com
* 网址：http://bbs.162100.com
* 声明：仅供代码爱好者学习交流，禁用于商业目的，请保留此版权信息
*/

header("content-type:text/html; charset=UTF-8");
//header('Cache-Control:no-cache,must-revalidate');  
//header('Pragma:no-cache');

require_once('config.php');
require_once('inc/content.php');
$web = text_v2_config_utf8($web);
header('Content-Type: text/html; charset=UTF-8');
if ($web['site_close'] === 1) {
	header('Location: 0.php');
	die();
}

//函数集-----------------------------------------------------------------------------

//页数
function get_page($n) {
	global $web;
	$_GET['p'] = isset($_GET['p']) ? abs((int)$_GET['p']) : 1;
	if (!$_GET['p'] || $_GET['p'] < 1) {
		return 1;
	} elseif ($_GET['p'] > ceil($n / $web['pagesize'])) {
		return ceil($n / $web['pagesize']);
	} else {
		return floor($_GET['p']);
	}
}

//分页
function get_page_foot($totallists, $pagesize, $p) {
	global $web, $arr_page, $pp;
	$pagestep = 10;
	$totalpages = 1;
	$text = '
<style type="text/css">
/*页码*/
#page { padding-bottom:15px; text-align:center; line-height:24px; color:#666666; clear:both; }
#page a, #page b, #page span { height:24px; line-height:24px; display:inline-block !important; display:inline; zoom:1; vertical-align:middle; margin:1px; padding-left:7.5px; padding-right:7.5px; text-decoration:none; background-color:#F4F4F4; }
#page .page { display:inline-block !important; display:inline; zoom:1; vertical-align:middle; background-color:#FFFFFF; border:1px #DDDDDD solid; padding:1px; border-radius:5px; -moz-border-radius:5px; -webkit-border-radius:5px; font-size:14px; }
#page > .page b { color:#FF6600; background-color:#FFFFFF; }
#page > .page b.totallists { color:#000000; background-color:#ECECEC; }
#page > .page #pagego { padding:0; margin:1px; width:26px; height:22px; border:1px #CCCCCC inset; background:url(inc/images/enter.gif) 6px 6px no-repeat; display:inline-block !important; display:inline; zoom:1; vertical-align:middle; outline:none; }
</style>
';

	$text .= '
  <div id="page">';
	if ($totallists > $pagesize) {
		$text .= '
    <div class="page">';
		$offset = 4; //当前页前面显几页
		$totalpages = @ceil($totallists / $pagesize);
		if ($pagestep > $totalpages) {
			$fr = 1;
			$to = $totalpages;
		} else {
			$fr = $p - $offset;
			$to = $fr + $pagestep - 1;
			if ($fr < 1) {
				$to = $p + 1 - $fr;
				$fr = 1;
				if ($to - $fr < $pagestep) {
					$to = $pagestep;
				}
			} elseif($to > $totalpages) {
				$fr = $totalpages - $pagestep + 1;
				$to = $totalpages;
			}
		}

		$text .= '<b class="totallists" title="总记录数">'.$totallists.'</b>';
		$text .= ($p - $offset > 1 && $totalpages > $pagestep ? '<a target="_self" href="'.pp(SELF_SCRIPT.'?p=1'.$pp).'">1 …</a>': ''). ($p > 1 ? '<a target="_self" href="'.pp(SELF_SCRIPT.'?p='.($p - 1).$pp).'" title="前一页">&lt;&lt;</a>': '');
		for ($i = $fr; $i <= $to; $i++) {
			$text .= $i == $p ? '<b>'.$i.'</b>': '<a target="_self" href="'.pp(SELF_SCRIPT.'?p='.$i.$pp).'" title="第'.$i.'页">'.$i.'</a>';
		}
		$text .= ($p < $totalpages ? '<a target="_self" href="'.SELF_SCRIPT.'?p='.($p + 1).$pp.'" title="后一页">&gt;&gt;</a>': '').($to < $totalpages ? '<a target="_self" href="'.SELF_SCRIPT.'?p='.$totalpages.$pp.'" title="最后页">… '.$totalpages.'</a>': '');
		$text .= ($totalpages > $pagestep ? '<input type="text" name="pagego" id="pagego" size="3" title="按回车直接跳页" onkeydown="if(event.keyCode==13&&!isNaN(this.value)&&this.value>=1&&this.value<='.$totalpages.'){window.location=main.pp(\''.SELF_SCRIPT.'?p=\'+this.value+\''.$pp.'\');}" />': '');
		$text .= '</div>
    <div class="page"> 以每页 ';
		foreach($arr_page as $val) {
			$text .= ''.($_GET['pn'] == $val ? '<b>'.$val.'</b>' : '<a href="'.pp(SELF_SCRIPT.'?p='.$p.(preg_replace('/\&pn=\d*/i', '', $pp)).'&pn='.$val).'" target="_self">'.$val.'</a>').'';
			if ($val >= $totallists) break;
		}
		unset($GLOBALS['arr_page'], $val);
		$text .= '条浏览</div>';
	} else {
		if ($totallists > 0) {
			$text .= '
    <div class="page"><b class="totallists" title="总记录数">'.$totallists.'</b> 共1页</div>';
		}
	}
	$text .= '
  </div>
';
	return $text;
}

//处理页码等参数
function pp($s) {
	$s = preg_replace('/\&*p=1/i', '', $s);
	$s = preg_replace('/\&+/', '&', $s);
	$s = preg_replace('/\?+\&+/', '?', $s);
	return preg_replace('/\?+$/', '', $s);
}

function get_list_content_guest() {
	return '<span class="approved">内容审核中</span>';
}
function get_list_content_admin($id, $content) {
	return '<span class="approved" id="chk_'.$id.'">内容审核中 <a href="run.php?act=chk&id='.$id.'" target="lastFrame" onclick="main.addSubmitSafe();">核准</a></span>'.$content;
}

function get_h2_and_br($text) {
    // Used by older administrative views only; escape user content.
    return '<div class="entry-raw">'.text_v2_html(text_v2_legacy($text)).'</div>';
}

function get_list($loc_len, $pos, $fp2) {
	global $web, $text_admin_run_check, $thisyear;
	list($loc, $len) = @explode('|', $loc_len);
	$len = abs($len);
	fseek($fp2, $loc, SEEK_SET);
	$row = @explode('|', fread($fp2, $len));

	$ip_admin = $ip_guest = $ip = '';
	if ($web['ip_open'] !== 0) {
		if ($ip = substr($row[0], 10)) {
			if (ADMIN_POWER) {
				$ip_admin = '<span title="留言者IP"> - 来自'.$ip.'</span>';
			} else {
				if ($web['ip_open'] === 11) {
					$ip_guest = ' title="留言者IP：'.preg_replace('/\.\d+$/', '.*', $ip).'"';
				}
			}
		}
	}

	$area = (isset($web['subarea'][$row[1]]) || $ip) ? '<a href="write.php?subarea='.$row[1].'" class="area"'.$ip_guest.'><img src="img/area.png" alt="发表版区" />'.$web['subarea'][$row[1]].''.$ip_admin.'</a>' : '';
    $row_count = count($row);
    $record = text_v2_message(isset($row[2]) ? $row[2] : '');
    $entry_id = 'entry_' . (int)$pos;
    $body = '<div class="entry-raw" id="'.$entry_id.'">'.text_v2_html($record['content']).'</div>';
    $content = ($record['subject'] !== '' ? '<h2 class="entry-subject">'.text_v2_html($record['subject']).'</h2>' : '') . $body;
    $is_pending = $row_count == 5 && trim($row[3]) === '' && trim($row[4]) === '';
    if ($is_pending) $content = $text_admin_run_check($loc.'|'.$len.'|'.$pos, $content);
    $copy_button = $is_pending && !ADMIN_POWER ? '' : '<button type="button" class="copy-btn" onclick="main.copyEntry(\''.$entry_id.'\', this)" aria-label="复制这条留言">一键复制</button>';
    $delete_button = ADMIN_POWER ? '<button type="button" class="delete-entry-btn" onclick="return thDeleteOne(\''.$loc.'|'.$len.'|'.$pos.'\');">删除留言</button>' : '';

	$date = gmdate("Y-m-d H:i:s", substr($row[0], 0, 10) + floatval($web["time_pos"]) * 3600);

	if (strpos($date, $thisyear) !== false) {
		$date = substr($date, 5, 11);
	}
	$pos2 = strpos($date, ' ') + 1;
	$h = substr($date, $pos2, 2) % 12;
	$i = substr($date, $pos2 + 3, 2);

    $reply_html = '';
    if ($row_count == 4 && trim($row[3]) !== '') {
        $reply_id = 'replycopy_'.(int)$pos;
        $reply_html = '<div class="ad_reply"><div class="reply-head"><strong>管理员回复</strong><button type="button" class="copy-btn" onclick="main.copyEntry(\''.$reply_id.'\', this)">复制回复</button></div><div class="entry-raw" id="'.$reply_id.'">'.text_v2_html(text_v2_reply($row[3])).'</div></div>';
    }
    return '
  <article class="body rounded message-card" id="area_'.$loc.'|'.$len.'|'.$pos.'">
    <div class="title"><label for="m_'.$loc.'|'.$len.'|'.$pos.'" class="m"><input name="id[]" id="m_'.$loc.'|'.$len.'|'.$pos.'" type="checkbox" value="'.$loc.'|'.$len.'|'.$pos.'" /></label></div>
    <div class="texts">
      <div class="title_bottom"><div class="message-meta"><span class="message-dot"></span>'.$area.'<span class="message-date">'.$date.'</span></div>'.$copy_button.$delete_button.'</div>
      <div class="text">'.$content.'</div>
      '.$reply_html.'
    </div>
    <span id="reply_'.$loc.'|'.$len.'|'.$pos.'"></span>
  </article>';

}
//----------------------------------------------------------------------------------

$text = 
$text_error = 
$text_order = 
$text_admin_run_css =
$text_admin_run_hand = 
$text_admin_exit = 
$text_admin_run_login = '';
$text_admin_run_check = 'get_list_content_guest';


$size = file_size('data/index.txt');
if ($size % INDEX_WIDTH !== 0) {
	$text_error = '<div class="warning">程序故障！——数据库索引步长与配置不符，可能配置文件或数据库索引中途被修改/破坏，需要上次正确的配置来恢复正常</div>';
}

$arr_page = array(10, 30, 50, 100, 200);
$pp = isset($_GET['order']) && $_GET['order'] == 2 ? '&order=2' : '';

if (isset($_GET['pn'])) {
	if (in_array($_GET['pn'], $arr_page)) {
		$web['pagesize'] = $_GET['pn']; //分页记录数
		$pp .= '&pn='.$web['pagesize'];
	}
}

$n = ceil($size / INDEX_WIDTH); //数量
$p = get_page($n); //页码

// A successful post redirects here with its exact storage key. Find its index
// position before rendering, so pagination and reverse display order work.
$recent_focus_id = '';
if ($n > 0 && !empty($web['pagesize']) && isset($_GET['focus']) && is_string($_GET['focus']) &&
    preg_match('/^([0-9]{1,12})-([0-9]{1,10})$/D', $_GET['focus'], $focus_match)) {
    $wanted_record = $focus_match[1].'|'.$focus_match[2];
    $focus_fp = @fopen('data/index.txt', 'rb');
    if ($focus_fp) {
        // New posts normally match on the very first read.
        for ($focus_i = (int)$n - 1; $focus_i >= 0; $focus_i--) {
            $focus_pos = $focus_i * INDEX_WIDTH;
            if (fseek($focus_fp, $focus_pos, SEEK_SET) !== 0) break;
            if (trim(fread($focus_fp, INDEX_WIDTH)) === $wanted_record) {
                $recent_focus_id = 'area_'.$focus_match[1].'|'.$focus_match[2].'|'.$focus_pos;
                $p = (int)floor(($n - 1 - $focus_i) / $web['pagesize']) + 1;
                break;
            }
        }
        fclose($focus_fp);
    }
}

define('ADMIN_POWER', confirm_power());
define('SELF_SCRIPT', preg_replace('/(\?.*)*$/', '', $_SERVER['REQUEST_URI']));


//导入管理模块
if (isset($_GET['admin_run']) && $_GET['admin_run'] == 1) {
	if (ADMIN_POWER) {
		$pp .= '&admin_run=1';
		$text_admin_run_css = '
<style type="text/css">
.title { display:block !important; }
</style>
<script language="javascript" type="text/javascript">
<!--
(function() {
	//获取元素（导航栏）距离浏览器上沿的高度
	function getOffsetTop(o) { 
		var t = o.offsetTop;
		var p = o.offsetParent;
		while (p != null) {
			t += p.offsetTop;
			p = p.offsetParent;
		}
		return t;
	}
	function fix_nav() {
		var scrollTop = document.documentElement.scrollTop || window.pageYOffset || document.body.scrollTop;
		//触发悬停
		if (scrollTop > navTop) {
			manage.className = "nav_bar";
		}
		//位置还原
		else {
			manage.className = "";
		}
	}
	window.onload = function() {
		manage = document.getElementById("manage"); //导航栏对象，ID自己定
		navTop = getOffsetTop(manage);
		window.onresize = fix_nav; //切换浏览器窗口大小时也要计算触发
		window.onscroll = fix_nav; //滚动条滑动时计算触发
	};
})();

-->
</script>
';


		$text_admin_run_reply =  '
	var chkchk = document.getElementById(\'chk_\'+id+\'\');
	if (chkchk != null && chkchk.innerHTML != \'\') {
		main.addSubmitSafe_(\'i\', \'内容审核中，无法回复\');
		return false;
	}
	var replyObj = document.getElementById("replydiv");
	if (replyObj != null) {
		try{
			//replyObj.parentNode.innerHTML = "";
			replyObj.parentNode.removeChild(replyObj);
		} catch(e){}
	}
	if (document.getElementById(\'reply_\'+id) != null) {
		document.getElementById(\'reply_\'+id).innerHTML = \'<div id="replydiv" name="replydiv">\
    <input type="hidden" name="reply_id" value="\'+id+\'" />\
    <div class="textarea_box">\
      <fieldset><legend><b>回复：</b></legend>\
      <div><textarea id="content" name="content" rows="10" style="width:100%;" onKeyUp="main.runGetWordCount();"></textarea>\
      <div><font id="max_words" class="redword"></font>请将您的发表控制好，更多内容请您分次提交</div>\
      <div><button type="submit" class="send2" onclick="main.addSubmitSafe();">回复</button></div></fieldset>\
    </div>\
  </div>\';
		document.getElementById(\'act\').value=\'reply\';
	}
    return true;
';
		$text_admin_run_check = 'get_list_content_admin';
		$text_admin_run_hand = '<div id="manage">
  <div class="body">
    <div class="nav">
      <span>
        <label><img src="img/batch.gif" title="批量管理" alt="批量管理" /> <a href="javascript:void(0)" onclick="main.allChoose(\'id[]\',1,1);return false;">全选</a> · <a href="javascript:void(0)" onclick="main.allChoose(\'id[]\',1,0);return false;">反选</a> · <a href="javascript:void(0)" onclick="main.allChoose(\'id[]\',0,0);return false;">不选</a></label>
      </span>
      <span>
        <label class="yellowbg"><a href="javascript:void(0);" onclick="main.addSubmitSafe(); document.getElementById(\'act\').value=\'chk\'; document.getElementById(\'mainform\').submit(); return false;">核准</a></label><label class="yellowbg"><a href="javascript:void(0);" onclick="return thDeleteSelected();">删除</a></label><label class="yellowbg"><a href="javascript:void(0);" onclick="document.getElementById(\'manage_area\').style.display=\'block\'; document.getElementById(\'act\').value=\'area_to\';">更改分类</a></label>
      </span> 
    </div>
  </div>';
		$text_admin_run_hand .=  '
  <div class="body" id="manage_area" style="display:none;">
    <div class="nav"><span>更改分类到：</span><span id="subarea">';
		if (count($web['subarea']) > 0) {
			foreach ($web['subarea'] as $key => $val) {
				$text_admin_run_hand .= ' <label for="p'.$key.'" onclick="main.confirmSubarea(this); htmlbody.document.getElementById(\'area_to\').value=\''.$key.'\';"><span>'.$val.'</span></label> ';
			}
		}
		$text_admin_run_hand .= '</span><span><button type="button" class="send2" onClick="main.addSubmitSafe(); document.getElementById(\'mainform\').submit();">提交</button>  <a href="javascript:void(0);" onclick="document.getElementById(\'manage_area\').style.display=\'none\'; document.getElementById(\'act\').value=\'\';">取消</a></span> </div>
  </div></div>';

		$text_admin_exit .= '<label><a href="'.pp(SELF_SCRIPT.preg_replace('/\&*admin_run=1/i', '', '?p='.$p.$pp)).'">退出</a></label>';

	} else {
		$text_admin_run_login = '<div id="submit_safe"><div id="submit_safe_in"></div>
                   <table width="100%" height="100%" id="submit_safe_in2"><tr><td><div id="submit_safe_in22">
  <form action="run.php" method="post" name="mainform" id="mainform" target="lastFrame">
    <input type="hidden" name="act" id="act" value="login" />
    <div>管理密钥：</div>
    <div><input type="text" name="password" id="password" style="width:150px;" value="" /> <button type="submit" class="send2">提交</button></div>
    <div><label><input type="checkbox" class="checkbox" name="save_cookie" value="1" checked="checked" /> 一直保持登陆</label> <a href="./">&lt;&lt;返回</a></div>
    <div></div>
  </form>
				   </div></td></tr></table></div>';

	}
} else {
	$text_admin_run_reply = '
	//var con = window.confirm("管理留言——\n管理员权限！需要导入管理模块");
	//if (con != 1) {
    //	return false;
	//} else {
		location.href = main.pp("'.SELF_SCRIPT.'?admin_run=1'.($p > 1 ? '&p='.$p.$pp : $pp).'");
		return false;
	//}
    ';
}
?><!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=no,shrink-to-fit=no" />
<meta name="applicable-device" content="pc,mobile" />
<meta name="color-scheme" content="light dark" />
<title><?php echo $web['sitename']; ?></title>
<script src="inc/theme.js?<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link id="css" href="inc/style.css?<?php echo filemtime('inc/style.css'); ?>" rel="stylesheet" type="text/css">
<link href="inc/modern.css?<?php echo filemtime('inc/modern.css'); ?>" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="inc/theme.css?<?php echo @filemtime('inc/theme.css'); ?>">
<?php echo $text_admin_run_css; unset($text_admin_run_css); ?>
<script language="javascript" type="text/javascript" src="inc/main.js?<?php echo filemtime('inc/main.js'); ?>"></script>
<script language="javascript" type="text/javascript">
<!--
main.allChoose = function(o, v1, v2) {
	var a = document.getElementsByName(o);
	for (var i = 0; i < a.length; i++) {
		if (a[i].checked == false) a[i].checked = v1;
		else a[i].checked = v2;
	}
}
//回复
main.reply = function(id) {
<?php echo $text_admin_run_reply; unset($text_admin_run_reply); ?>

}

main.pp = function(s) {
	s = s.replace(/\&*p=1/ig, '');
	s = s.replace(/\&+/g, '&');
	s = s.replace(/\?+\&+/, '?');
	return s.replace(/\?+$/, '');
}
-->
</script>

<script>
function thDeleteSelected() {
  var form = document.getElementById('mainform');
  var count = form ? form.querySelectorAll('input[name="id[]"]:checked').length : 0;
  if (!count) { alert('请先勾选需要删除的留言。'); return false; }
  if (!confirm('确定永久删除选中的 ' + count + ' 条留言吗？此操作不可撤销。')) return false;
  document.getElementById('act').value = 'del';
  main.addSubmitSafe();
  form.submit();
  return false;
}
function thDeleteOne(id) {
  if (!confirm('确定永久删除这条留言吗？删除后不可恢复。')) return false;
  var form = document.getElementById('mainform');
  if (!form) return false;
  var boxes = form.querySelectorAll('input[name="id[]"]');
  var found = false;
  for (var i=0; i<boxes.length; i++) {
    boxes[i].checked = boxes[i].value === id;
    if (boxes[i].checked) found = true;
  }
  if (!found) { alert('没有找到这条留言，请刷新页面再试。'); return false; }
  document.getElementById('act').value = 'del';
  main.addSubmitSafe();
  form.submit();
  return false;
}
</script>
</head>
<body class="app-page archive-page">
<?php echo $text_error; unset($text_error); ?>
<header id="top">
  <div class="body top-inner">
    <a href="./" id="logo"><span class="brand-symbol">T<span>_</span></span><span class="brand-name"><?php echo text_v2_html($web['sitename']); ?></span></a>
    <nav class="site-nav" aria-label="主要导航"><a href="./" class="active">浏览内容</a><a href="write.php">新建留言</a><?php echo ADMIN_POWER ? '<a href="index.php?admin_run=1">留言管理</a><a href="update.php">版本更新</a>'.$text_admin_exit.'<a href="run.php?act=logout" onclick="main.addSubmitSafe();" target="lastFrame">退出管理</a>' : ''; ?></nav><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="选择显示主题" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span><span class="theme-chevron" aria-hidden="true">⌄</span></button>
      <div class="theme-menu" id="theme-menu" role="group" aria-label="显示主题" hidden>
        <button type="button" data-theme-choice="auto">◐ <span>自动</span></button>
        <button type="button" data-theme-choice="light">☀ <span>浅色</span></button>
        <button type="button" data-theme-choice="dark">☾ <span>深色</span></button>
      </div>
    </div>
  </div>
  <iframe id="lastFrame" name="lastFrame" title="操作结果" frameborder="0" style="display:none;"></iframe>
</header>
<main class="page-main">
  <section class="intro body">
    <div class="intro-flex"><div><h1>留言列表</h1></div><a class="primary-link" href="write.php">＋ 发布留言</a></div>
  </section>
  <?php if (ADMIN_POWER): ?><div class="body management-entry"><a href="index.php?admin_run=1" class="manage-link">留言管理 · 批量操作</a><a href="admin.php" class="manage-link">站点设置</a><a href="update.php" class="manage-link">版本更新</a></div><?php endif; ?>
  <div class="body archive-heading"><div><h2>全部留言 <span class="archive-total"><?php echo (int)$n; ?></span></h2></div></div>

<?php
echo $text_admin_run_login;
unset($text_admin_run_login);

if (!file_exists('data') || !file_exists('data/index.txt') || !file_exists('data/file.php')) {
	die('查不到数据库文件！</body></html>');
}
?>
<form action="run.php" method="post" name="mainform" id="mainform" target="lastFrame" onSubmit="return main.postChk();">
  <input type="hidden" name="act" id="act" value="" />
  <input type="hidden" name="area_to" id="area_to" value="" />
  <input type="hidden" name="delete_token" value="<?php echo text_v2_delete_token(); ?>" />
  <?php

if ($n > 0) {
	$order = isset($_GET['order']) && in_array($_GET['order'], array(1, 2)) ? (int)$_GET['order'] : $web['order'];
	
	if (($fp = @fopen('data/index.txt', 'rb')) && ($fp2 = @fopen('data/file.php', 'rb'))) {
		$text .= '<div class="body">
  <div id="order">
    <div class="body"><span class="sort-caption">排序方式</span><select name="order" onchange="location.href=main.pp(\''.SELF_SCRIPT.'?p='.$p.preg_replace('/\&order=\d*/i', '', $pp).'\'+(this.value==2?\'&order=2\':\'\'));">
      <option value="1"'.($order != 2 ? ' selected': '').'>默认顺序</option>
      <option value="2"'.($order == 2 ? ' selected': '').'>反序查看</option>
    </select></div></div>
  </div>';
		$text .= $text_admin_run_hand;
		unset($text_admin_run_hand);
		$thisyear = gmdate("Y", time() + floatval($web["time_pos"]) * 3600);

		if ($order == 1) {
			$pos = $size - INDEX_WIDTH * $web['pagesize'] * ($p - 1) - INDEX_WIDTH;
			$pos = $pos > $size - INDEX_WIDTH ? $size - INDEX_WIDTH: $pos;
			$end = $size - INDEX_WIDTH * $web['pagesize'] * $p;
			$end = $end < 0 ? 0 : $end;
			while ($pos >= $end) {
				fseek($fp, $pos, SEEK_SET);
				$line = fread($fp, INDEX_WIDTH);
				$text .= get_list($line, $pos, $fp2);
				$pos = ftell($fp) - 2 * INDEX_WIDTH;
			}
		} else {
			$pos = $size - INDEX_WIDTH * $web['pagesize'] * $p;
			$pos = $pos < 0 ? 0 : $pos;
			$end = $size - INDEX_WIDTH * $web['pagesize'] * ($p - 1) - INDEX_WIDTH;
			$end = $end > $size ? $size: $end;
			while ($pos <= $end) {
				fseek($fp, $pos, SEEK_SET);
				$text .= get_list(fread($fp, INDEX_WIDTH), $pos, $fp2);
				$pos = ftell($fp);
			}
		}
		@fclose($fp);
		@fclose($fp2);
		unset($text_admin_run_check, $size, $thisyear, $order, $line, $end, $pos);
		unset($fp, $fp2);

		$text .= get_page_foot($n, $web['pagesize'], $p);
		unset($n, $p);
	} else {
		$text .= '
  <div class="body rounded">
    <div class="texts">
      <p>数据库读取失败！请稍后再试</p>
    </div>
  </div>';
		
	}
	


} else {
	$text .= '
  <div class="body rounded">
    <div class="texts">
      <p>暂无留言，发布第一条内容吧。</p>
      <p id="subarea">';
	if (count($web['subarea']) > 0) {
		foreach ($web['subarea'] as $key => $val) {
			$text .= '<label><a href="write.php?subarea='.$key.'"><span>'.$val.'</span></a></label>';
		}
	} else {
		$text .= '<label><a href="write.php"><span><b>发表留言</b></span></a></label>';
	}
	$text .= '
      </p>
    </div>
  </div>
';
}

echo $text;
unset($text);
?>
</form>
</main>
<div id="foot">
  <div class="body"><span>© <?php echo gmdate("Y", time() + floatval($web["time_pos"]) * 3600); ?> <?php echo isset($web['sitename']) ? text_v2_html($web['sitename']) : ''; ?></span><span></span></div>
  <noscript><div class="warning">您的浏览器不支持javascript，部分功能无法使用！</div></noscript>
</div>
<?php if ($recent_focus_id !== '') { ?>
<script>
// Select and scroll the specific post only after the archive has been rendered.
window.addEventListener('load', function () {
    var card = document.getElementById(<?php echo json_encode($recent_focus_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    if (!card) return;
    card.classList.add('just-posted');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (window.requestAnimationFrame) {
        window.requestAnimationFrame(function () { card.scrollIntoView({behavior: reduceMotion ? 'auto' : 'smooth', block: 'center'}); });
    } else {
        card.scrollIntoView();
    }
});
</script>
<?php } ?>
</body>
</html>
