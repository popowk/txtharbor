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
require_once('inc/entry_view.php');
require_once('inc/admin_login.php');
$web = text_v2_config_utf8($web);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
define('ADMIN_POWER', confirm_power());
if (isset($_GET['id']) && is_string($_GET['id']) && preg_match('/^[a-f0-9]{24}$/D', $_GET['id'])) {
    header('Location: entry.php?id='.$_GET['id'], true, 302);
    exit;
}
try { $th_meta = th_metadata_read(); }
catch (Throwable $e) { http_response_code(503); exit('留言访问设置不可用，已安全暂停显示。请管理员检查 data/entry_meta.php。'); }
th_access_session();
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

// 分页：仅在存在多页时显示，记录总数已在标题栏展示。
function get_page_foot($totallists, $pagesize, $p) {
    global $arr_page, $pp;
    $pagesize = max(1, (int)$pagesize);
    $totalpages = (int)ceil($totallists / $pagesize);
    if ($totalpages <= 1) return '';
    $p = max(1, min((int)$p, $totalpages));
    $page_url = function ($page) use ($pp) {
        return htmlspecialchars(pp(SELF_SCRIPT.'?p='.(int)$page.$pp), ENT_QUOTES, 'UTF-8');
    };
    $text = '<nav id="page" class="archive-pager" aria-label="留言分页"><div class="page-links">';
    if ($p > 1) $text .= '<a class="page-nav" href="'.$page_url($p - 1).'" aria-label="上一页">‹</a>';
    $first = max(1, $p - 2);
    $last = min($totalpages, $p + 2);
    if ($first > 1) {
        $text .= '<a href="'.$page_url(1).'">1</a>';
        if ($first > 2) $text .= '<span class="page-ellipsis" aria-hidden="true">…</span>';
    }
    for ($i = $first; $i <= $last; $i++) {
        $text .= $i === $p
            ? '<span class="page-current" aria-current="page">'.$i.'</span>'
            : '<a href="'.$page_url($i).'" aria-label="第'.$i.'页">'.$i.'</a>';
    }
    if ($last < $totalpages) {
        if ($last < $totalpages - 1) $text .= '<span class="page-ellipsis" aria-hidden="true">…</span>';
        $text .= '<a href="'.$page_url($totalpages).'">'.$totalpages.'</a>';
    }
    if ($p < $totalpages) $text .= '<a class="page-nav" href="'.$page_url($p + 1).'" aria-label="下一页">›</a>';
    $text .= '</div><span class="page-summary">'.$p.' / '.$totalpages.' 页</span>';
    if (is_array($arr_page) && count($arr_page) > 1) {
        $pp_without_page_size = preg_replace('/&pn=\d*/i', '', $pp);
        $text .= '<div class="page-size"><label for="page-size">每页</label><select id="page-size" aria-label="每页显示条数" onchange="location.href=this.value">';
        foreach ($arr_page as $val) {
            $val = (int)$val;
            if ($val <= 0) continue;
            $href = htmlspecialchars(pp(SELF_SCRIPT.'?p=1'.$pp_without_page_size.'&pn='.$val), ENT_QUOTES, 'UTF-8');
            $selected = $pagesize === $val ? ' selected' : '';
            $text .= '<option value="'.$href.'"'.$selected.'>'.$val.' 条</option>';
        }
        $text .= '</select></div>';
    }
    return $text.'</nav>';
}

//处理页码等参数
function pp($s) {
	$s = preg_replace('/([?&])p=1(?=&|$)/i', '$1', $s);
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

	$area = (isset($web['subarea'][$row[1]]) || $ip) ? '<a href="write.php?subarea='.$row[1].'" class="area"'.$ip_guest.'><img src="img/area.png" alt="发表版区" />'.text_v2_html(isset($web['subarea'][$row[1]])?$web['subarea'][$row[1]]:'').''.$ip_admin.'</a>' : '';
    $row_count = count($row);
    $entry = isset($GLOBALS['th_entry_by_pos'][$pos]) ? $GLOBALS['th_entry_by_pos'][$pos] : null;
    if (!$entry) return '';
    $is_pending = $entry['pending'];
    $content = th_entry_body_html($entry, ADMIN_POWER);
    if ($is_pending && ADMIN_POWER) $content = $text_admin_run_check($loc.'|'.$len.'|'.$pos, $content);
    $copy_button = th_entry_controls($entry, ADMIN_POWER);
    $delete_button = ADMIN_POWER ? '<button type="button" class="delete-entry-btn" onclick="return thDeleteOne(\''.$loc.'|'.$len.'|'.$pos.'\');">删除留言</button>' : '';

	$date = gmdate("Y-m-d H:i:s", substr($row[0], 0, 10) + floatval($web["time_pos"]) * 3600);

	if (strpos($date, $thisyear) !== false) {
		$date = substr($date, 5, 11);
	}
	$pos2 = strpos($date, ' ') + 1;
	$h = substr($date, $pos2, 2) % 12;
	$i = substr($date, $pos2 + 3, 2);

    $reply_html = '';
    if ($entry['reply'] !== '' && th_entry_public_content($entry)) {
        $reply_id = 'replycopy_'.(int)$pos;
        $reply_html = '<div class="ad_reply"><div class="reply-head"><strong>管理员回复</strong><button type="button" class="copy-btn" onclick="main.copyEntry(\''.$reply_id.'\', this)">复制回复</button></div><div class="entry-raw" id="'.$reply_id.'">'.text_v2_html($entry['reply']).'</div></div>';
    }
    return '
  <article class="body rounded message-card" id="area_'.$loc.'|'.$len.'|'.$pos.'">
    <div class="title"><label for="m_'.$loc.'|'.$len.'|'.$pos.'" class="m"><input name="id[]" id="m_'.$loc.'|'.$len.'|'.$pos.'" type="checkbox" value="'.$loc.'|'.$len.'|'.$pos.'" /></label></div>
    <div class="texts">
      <div class="title_bottom"><div class="message-meta"><span class="message-dot"></span>'.$area.'<span class="message-date">'.$date.'</span></div>'.($entry['pinned']?'<span class="entry-pin">📌 置顶</span>':'').'<span class="entry-buttons">'.$copy_button.$delete_button.'</span></div>
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

// Metadata-driven index ordering: pin first, then selected order; exclude expired for guests.
try { $th_entries = th_entries_scan($th_meta); }
catch (Throwable $e) { http_response_code(503); exit('留言索引读取失败，请检查文件完整性。'); }
$th_visible_entries = array_values(array_filter($th_entries, function ($entry) { return th_entry_visible($entry); }));
$th_entry_by_pos = array();
foreach ($th_visible_entries as $entry) $th_entry_by_pos[$entry['pos']] = $entry;
$sort_order = isset($_GET['order']) && in_array((int)$_GET['order'], array(1, 2), true)
    ? (int)$_GET['order'] : (int)$web['order'];
usort($th_visible_entries, function ($a, $b) use ($sort_order) {
    if ($a['pinned'] !== $b['pinned']) return $a['pinned'] ? -1 : 1;
    // Old index sequence is stable even when multiple posts share the same timestamp.
    return $sort_order === 2 ? $a['pos'] <=> $b['pos'] : $b['pos'] <=> $a['pos'];
});
$n = count($th_visible_entries);
$p = max(1, min((int)($_GET['p'] ?? 1), max(1, (int)ceil($n / $web['pagesize']))));
$recent_focus_id = '';
if (isset($_GET['focus']) && is_string($_GET['focus']) && preg_match('/^(\d{1,12})-(\d{1,10})$/D', $_GET['focus'], $matched)) {
    foreach ($th_visible_entries as $i => $entry) {
        if ($entry['loc'] == (int)$matched[1] && $entry['len'] == (int)$matched[2]) {
            $recent_focus_id = 'area_'.th_entry_key($entry);
            $p = (int)floor($i / $web['pagesize']) + 1;
            break;
        }
    }
}
if (isset($_GET['focus_id']) && is_string($_GET['focus_id'])) {
    foreach ($th_visible_entries as $i => $entry) {
        if ($entry['id'] === $_GET['focus_id']) {
            $recent_focus_id = 'area_'.th_entry_key($entry);
            $p = (int)floor($i / $web['pagesize']) + 1;
            break;
        }
    }
}
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
		$text_admin_run_login = th_admin_login_html('index');

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
<script src="inc/theme.js?v=1.4.1&amp;t=<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link id="css" href="inc/style.css?<?php echo filemtime('inc/style.css'); ?>" rel="stylesheet" type="text/css">
<link href="inc/modern.css?<?php echo filemtime('inc/modern.css'); ?>" rel="stylesheet" type="text/css">
<link href="inc/entries.css?<?php echo @filemtime('inc/entries.css'); ?>" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="inc/theme.css?v=1.4.1&amp;t=<?php echo @filemtime('inc/theme.css'); ?>">
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
	s = s.replace(/([?&])p=1(?=&|$)/ig, '$1');
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
    <nav class="site-nav" aria-label="主要导航"><a href="./" class="active">浏览内容</a><a href="write.php">新建留言</a><?php echo ADMIN_POWER ? '<a href="index.php?admin_run=1">留言管理</a><a href="update.php">版本更新</a>'.$text_admin_exit.'<a href="run.php?act=logout">退出管理员</a>' : ''; ?></nav><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="点击切换主题" title="点击切换主题"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span></button><button type="button" class="theme-menu-toggle" id="theme-menu-toggle" aria-label="选择主题模式" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu" title="选择自动、浅色或深色模式"><span class="theme-chevron" aria-hidden="true">⌄</span></button>
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
  <?php
  $current_order = isset($_GET['order']) && in_array((int)$_GET['order'], array(1, 2), true)
      ? (int)$_GET['order'] : (int)$web['order'];
  $sort_base_url = SELF_SCRIPT.'?p='.(int)$p.preg_replace('/&order=\d*/i', '', $pp);
  ?>
  <div class="body archive-heading">
    <h2>全部留言 <span class="archive-total"><?php echo (int)$n; ?></span></h2>
    <?php if ($n > 0): ?>
    <div class="archive-sort" id="order">
      <label class="sort-caption" for="archive-order">排序</label>
      <select id="archive-order" aria-label="留言排序方式" data-base-url="<?php echo htmlspecialchars($sort_base_url, ENT_QUOTES, 'UTF-8'); ?>"
          onchange="location.href=main.pp(this.getAttribute('data-base-url')+'&order='+this.value)">
        <option value="1"<?php echo $current_order !== 2 ? ' selected' : ''; ?>>最新优先</option>
        <option value="2"<?php echo $current_order === 2 ? ' selected' : ''; ?>>最早优先</option>
      </select>
    </div>
    <?php endif; ?>
  </div>

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
    $order = $sort_order;
    if (($fp2 = @fopen('data/file.php', 'rb'))) {
        $text .= $text_admin_run_hand;
        unset($text_admin_run_hand);
        $thisyear = gmdate("Y", time() + floatval($web["time_pos"]) * 3600);
        $page_items = array_slice($th_visible_entries, ($p - 1) * $web['pagesize'], $web['pagesize']);
        foreach ($page_items as $entry) {
            $text .= get_list($entry['loc'].'|'.$entry['len'], $entry['pos'], $fp2);
        }
        fclose($fp2);
        $text .= get_page_foot($n, $web['pagesize'], $p);
    } else {
        $text .= '<div class="body rounded"><div class="texts"><p>数据库读取失败</p></div></div>';
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
window.addEventListener('DOMContentLoaded', function () {
    var card = document.getElementById(<?php echo json_encode($recent_focus_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    if (!card) return;
    card.classList.add('just-posted');
    if (window.requestAnimationFrame) {
        window.requestAnimationFrame(function () { card.scrollIntoView({behavior: 'auto', block: 'center'}); });
    } else {
        card.scrollIntoView();
    }
});
</script>
<?php } ?>
</body>
</html>
