<?php
/* TxtHarbor-build:1.5.4 */

/*
* 程序名称：162100留言本（吧）
* 程序编码：UTF-8
* 作者：162100.com
* 网址：http://bbs.162100.com
* 声明：仅供代码爱好者学习交流，禁用于商业目的，请保留此版权信息
*/

header("content-type: text/html; charset=UTF-8");
require_once('config.php');
require_once __DIR__.'/inc/admin_auth.php';
require_once 'inc/content.php';
require_once 'inc/admin_login.php';
require_once 'inc/github_update.php';
$web = text_v2_config_utf8($web);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (false) { // Legacy cache switch disabled: authenticated HTML must never be cached.

  //ini_set("soap.wsdl_cache_enabled", 0);
  ini_set("opcache.revalidate_freq", 0);
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
  header("Cache-Control: no-cache, must-revalidate");
  header("Pragma: no-cache");

}

$text_admin_run_login = 
$text_admin_run_logout = '';

$th_current_admin = th_admin_authorized();
if ($th_current_admin) {
	$text_admin_run_logout = '<a href="run.php?act=logout">退出管理员</a> ';
} else {
	$text_admin_run_login = th_admin_login_html('admin');
}


?><!DOCTYPE html>
<html lang="zh">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0,user-scalable=no,shrink-to-fit=no" />
<meta name="applicable-device"content="pc,mobile" />
<title><?php echo $web['sitename']; ?> - 管理</title>
<script src="inc/theme.js?v=<?php echo rawurlencode(trim((string)@file_get_contents(__DIR__.'/VERSION'))); ?>&amp;t=<?php echo @filemtime('inc/theme.js'); ?>"></script>
<link id="css" href="inc/style.css?<?php echo filemtime('inc/style.css'); ?>" rel="stylesheet" type="text/css">
<link href="inc/modern.css?<?php echo filemtime('inc/modern.css'); ?>" rel="stylesheet" type="text/css">
<link rel="stylesheet" href="inc/theme.css?v=<?php echo rawurlencode(trim((string)@file_get_contents(__DIR__.'/VERSION'))); ?>&amp;t=<?php echo @filemtime('inc/theme.css'); ?>">
<style type="text/css">
<!--
table#set {
}
table#set td {
	padding-top:5px;
	padding-bottom:5px;
}
table#set td.right {
	text-align:right;
	/*white-space:nowrap;*/
}
-->
</style>
<script language="javascript" type="text/javascript" src="inc/main.js?v=<?php echo rawurlencode(trim((string)@file_get_contents(__DIR__.'/VERSION'))); ?>&amp;t=<?php echo filemtime('inc/main.js'); ?>"></script>
</head>
<body class="app-page admin-page">
<header id="top">
  <div class="body top-inner"><a href="./" id="logo"><span class="brand-symbol">T<span>_</span></span><span class="brand-name"><?php echo htmlspecialchars($web['sitename'], ENT_QUOTES, 'UTF-8'); ?></span></a><nav class="site-nav"><a href="./">浏览内容</a><a href="write.php">新建留言</a><?php if ($th_current_admin): ?><a href="index.php?admin_run=1">留言管理</a><a href="admin.php" class="active">管理设置</a><a href="update.php">版本更新</a><?php echo $text_admin_run_logout; unset($text_admin_run_logout); ?><?php else: ?><a href="admin.php" class="active">管理员登录</a><?php endif; ?></nav><div class="theme-control">
      <button type="button" class="theme-toggle" id="theme-toggle" aria-label="点击切换主题" title="点击切换主题"><span class="theme-icon" aria-hidden="true">◐</span><span id="theme-label">自动</span></button><button type="button" class="theme-menu-toggle" id="theme-menu-toggle" aria-label="选择主题模式" aria-haspopup="true" aria-expanded="false" aria-controls="theme-menu" title="选择自动、浅色或深色模式"><span class="theme-chevron" aria-hidden="true">⌄</span></button>
      <div class="theme-menu" id="theme-menu" role="group" aria-label="显示主题" hidden>
        <button type="button" data-theme-choice="auto">◐ <span>自动</span></button>
        <button type="button" data-theme-choice="light">☀ <span>浅色</span></button>
        <button type="button" data-theme-choice="dark">☾ <span>深色</span></button>
      </div>
    </div></div>
  <iframe id="lastFrame" name="lastFrame" title="操作结果" frameborder="0" style="display:none;"></iframe>
</header>
<main class="page-main">
  <section class="intro body"><h1>站点设置</h1></section>

<?php echo $text_admin_run_login; unset($text_admin_run_login); ?>
<?php if ($th_current_admin): ?><div class="body admin-quicklinks"><a href="index.php?admin_run=1"><strong>留言管理</strong><span>删除、审核和回复</span></a><a href="update.php"><strong>版本更新</strong><span>检查版本与代理设置</span></a></div><?php endif; ?>
<?php if ($th_current_admin): ?>
<form name="mainform" id="mainform" action="run.php" method="post" enctype="multipart/form-data" target="lastFrame">
  <div class="body rounded admin-settings-card">
    <div class="texts">
      <input type="hidden" name="act" id="act" value="set" />
      <table width="100%" border="0" cellspacing="0" cellpadding="0" id="set">
        <tr>
          <td width="150" class="right">程序版本：</td>
          <td>TxtHarbor v<?php echo htmlspecialchars(th_update_version(), ENT_QUOTES, 'UTF-8'); ?> · <?php $health = th_runtime_health(); echo $health['ok'] ? '运行文件正常' : '<strong style="color:#ff9da9">文件版本不一致，请修复更新</strong>'; ?> · <a href="update.php">更新中心 / 文件修复</a></td>
        </tr>
        <tr>
          <td width="150" class="right">后台缓存：</td>
          <td>
            <label><input type="checkbox" class="checkbox" name="cache" value="0"<?php echo $web['cache']!=1?' checked="checked"':''; ?> /> 页面设置优先实时显示</label>
            <small style="display:block;color:var(--muted);padding:5px 2px">管理员页面始终禁用缓存；如果 CDN 还显示旧版，请在更新中心检查运行文件。</small>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">网站状态：</td>
          <td>
            <label>
              <input type="radio" class="radio" name="site_close" value="1"<?php echo $web['site_close'] === 1 ? ' checked="checked"' : ''; ?> />
              关闭 </label>
            <label>
              <input type="radio" class="radio" name="site_close" value="0"<?php echo $web['site_close'] !== 1 ? ' checked="checked"' : ''; ?> />
              开启 </label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">管理密码：</td>
          <td>
            <label>
              <input type="password" name="password" value="" size="20" autocomplete="new-password" placeholder="留空则不修改管理密码" />
              <span style="color:#999999;">（留空默认原密码，否则更新原密码）</span></label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">站点名称：</td>
          <td>
            <label>
              <input type="text" name="sitename" value="<?php echo $web['sitename']; ?>" size="20" maxlength="" />
            </label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">站点口号：</td>
          <td>
            <label>
              <input type="text" name="sitefirm" value="<?php echo $web['sitefirm']; ?>" size="20" maxlength="" />
              <span style="color:#999999;">（显示在logo旁）</span></label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">网站logo：</td>
          <td style="position:relative;">
            <label>
              <input id="uploadlogo" name="uploadlogo" type="file" onChange="
      main.addSubmitSafe();
      document.getElementById('mainform').target='lastFrame';
      document.getElementById('mainform').act.value='up';
      document.getElementById('mainform').submit();
" style="width:100px; height:24px; position:absolute; z-index:99; overflow:hidden; background-color:#FFFFFF; filter:progid:DXImageTransform.Microsoft.Alpha(opacity=0); -moz-opacity:0; -khtml-opacity:0; opacity:0; cursor:pointer;" />
              <a href="javascript:void(0)">上载Logo</a></label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">时区调整：</td>
          <td>
            <label>
            <select name="time_pos" id="time_pos" style=" max-width:200px; ">
              <option value="-12">(GMT-12.00)国际日期变更线西</option>
              <option value="-11">(GMT-11.00)中途岛，萨摩亚群岛</option>
              <option value="-10">(GMT-10.00)夏威夷</option>
              <option value="-9">(GMT-9.00)阿拉斯加</option>
              <option value="-8">(GMT-8.00)太平洋时间（美国和加拿大）；蒂华纳</option>
              <option value="-7">(GMT-7.00)奇瓦瓦，拉巴斯，马扎特兰</option>
              <option value="-7">(GMT-7.00)山地时间（美国和加拿大）</option>
              <option value="-7">(GMT-7.00)亚利桑那</option>
              <option value="-6">(GMT-6.00)瓜达拉哈拉，墨西哥城，蒙特雷</option>
              <option value="-6">(GMT-6.00)萨斯喀彻温</option>
              <option value="-6">(GMT-6.00)中部时间（美国和加拿大）</option>
              <option value="-6">(GMT-6.00)中美洲</option>
              <option value="-5">(GMT-5.00)波哥大，利马，基多</option>
              <option value="-5">(GMT-5.00)东部时间（美国和加拿大）</option>
              <option value="-5">(GMT-5.00)印第安那州（东部）</option>
              <option value="-4">(GMT-4.00)大西洋时间（加拿大）</option>
              <option value="-4">(GMT-4.00)加拉加斯，拉巴斯</option>
              <option value="-4">(GMT-4.00)圣地亚哥</option>
              <option value="-3">(GMT-3.00)纽芬兰</option>
              <option value="-3">(GMT-3.00)巴西利亚</option>
              <option value="-3">(GMT-3.00)布宜诺斯艾利斯，乔治敦</option>
              <option value="-3">(GMT-3.00)格陵兰</option>
              <option value="-2">(GMT-2.00)中大西洋</option>
              <option value="-1">(GMT-1.00)佛得角群岛</option>
              <option value="-1">(GMT-1.00)亚速尔群岛</option>
              <option value="0">(GMT)格林威治标准时间，都柏林，爱丁堡，伦敦，里斯本</option>
              <option value="0">(GMT)卡萨布兰卡，蒙罗维亚</option>
              <option value="1">(GMT+1.00)阿姆斯特丹，柏林，伯尔尼，罗马，斯德哥尔摩，维也纳</option>
              <option value="1">(GMT+1.00)贝尔格莱德，布拉迪斯拉发，布达佩斯，卢布尔雅那，布拉格</option>
              <option value="1">(GMT+1.00)布鲁塞尔，哥本哈根，马德里，巴黎</option>
              <option value="1">(GMT+1.00)萨拉热窝，斯科普里，华沙，萨格勒布</option>
              <option value="1">(GMT+1.00)中非西部</option>
              <option value="2">(GMT+2.00)布加勒斯特</option>
              <option value="2">(GMT+2.00)哈拉雷，比勒陀利亚</option>
              <option value="2">(GMT+2.00)赫尔辛基，基辅，里加，索非亚，塔林，维尔纽斯</option>
              <option value="2">(GMT+2.00)开罗</option>
              <option value="2">(GMT+2.00)雅典，贝鲁特，伊斯坦布尔，明斯克</option>
              <option value="2">(GMT+2.00)耶路撒冷</option>
              <option value="3">(GMT+3.00)巴格达</option>
              <option value="3">(GMT+3.00)科威特，利雅得</option>
              <option value="3">(GMT+3.00)莫斯科，圣彼得堡，伏尔加格勒</option>
              <option value="3">(GMT+3.00)内罗毕</option>
              <option value="3">(GMT+3.00)德黑兰</option>
              <option value="4">(GMT+4.00)阿布扎比，马斯喀特</option>
              <option value="4">(GMT+4.00)巴库，第比利斯，埃里温</option>
              <option value="4">(GMT+4.30)喀布尔</option>
              <option value="5">(GMT+5.00)叶卡捷琳堡</option>
              <option value="5">(GMT+5.00)伊斯兰堡，卡拉奇，塔什干</option>
              <option value="5">(GMT+5.30)马德拉斯，加尔各答，孟买，新德里</option>
              <option value="5">(GMT+5.45)加德满都</option>
              <option value="6">(GMT+6.00)阿拉木图，新西伯利亚</option>
              <option value="6">(GMT+6.00)阿斯塔纳，达卡</option>
              <option value="6">(GMT+6.00)斯里哈亚华登尼普拉</option>
              <option value="6">(GMT+6.30)仰光</option>
              <option value="7">(GMT+7.00)克拉斯诺亚尔斯克</option>
              <option value="7">(GMT+7.00)曼谷，河内，雅加达</option>
              <option value="8">(GMT+8.00)北京，重庆，香港特别行政区，乌鲁木齐，台北</option>
              <option value="8">(GMT+8.00)吉隆坡，新加坡</option>
              <option value="8">(GMT+8.00)珀斯</option>
              <option value="8">(GMT+8.00)伊尔库茨克，乌兰巴图</option>
              <option value="9">(GMT+9.00)大坂，东京，札幌</option>
              <option value="9">(GMT+9.00)汉城</option>
              <option value="9">(GMT+9.00)雅库茨克</option>
              <option value="9">(GMT+9.30)阿德莱德</option>
              <option value="9">(GMT+9.30)达尔文</option>
              <option value="10">(GMT+10.00)布里斯班</option>
              <option value="10">(GMT+10.00)符拉迪沃斯托克（海参崴）</option>
              <option value="1">(GMT+10.00)关岛，莫尔兹比港</option>
              <option value="10">(GMT+10.00)霍巴特</option>
              <option value="10">(GMT+10.00)堪塔拉，墨尔本，悉尼</option>
              <option value="11">(GMT+11.00)马加丹，索罗门群岛，新喀里多尼亚</option>
              <option value="12">(GMT+12.00)奥克兰，惠灵顿</option>
              <option value="12">(GMT+12.00)斐济，堪察加半岛，马绍尔群岛</option>
              <option value="13">(GMT+13.00)努库阿洛法</option>
            </select>
            <br />
            <table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>这是服务器时区时间：<?php echo $here_date = date('Y/m/d H:i:s'); ?><br />
                  这是所在地时区时间：<?php echo $beijing_date = gmdate('Y/m/d H:i:s', time() + (floatval($web['time_pos']) * 3600)); ?></td>
                <td>&nbsp;</td>
                <td><font color="red">
                  <?php if ($here_date != $beijing_date){ echo '二者时间差别为'.((strtotime($here_date) - strtotime($beijing_date)) / 3600).'小时，可根据此进行调整'; } else { echo '二者相同'; } ?>
                </font></td>
              </tr>
            </table>
            </label>
            <script type="text/javascript">
<!--
document.getElementById('time_pos').value='<?php echo $web['time_pos']; ?>';
-->
  </script>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">每页页码：</td>
          <td>
            <label>
              <input type="text" name="pagesize" value="<?php echo $web['pagesize']; ?>" size="8" maxlength="3" />
            </label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">排序方式：</td>
          <td>
            <label>
              <input type="radio" class="radio" name="order" value="2"<?php echo $web['order'] === 2 ? ' checked="checked"' : ''; ?> />
              正序 </label>
            <label>
              <input type="radio" class="radio" name="order" value="1"<?php echo $web['order'] === 1 ? ' checked="checked"' : ''; ?> />
              倒序 </label>
          </td>
        </tr>
        <script type="text/javascript">
<!--
//添加链接
main.addKw = function() {
	var par = document.getElementById('add_keyword');
	var allArea = document.getElementsByName('subarea[]');
	if (allArea.length >= 10) {
		alert('目前留言分类最多限量10个。想增加分类建设请等待以后版本');
		return;
	}
	var newDiv = document.createElement('SPAN');
	newDiv.innerHTML = '<input name="subarea[]" type="text" size="8" />  <a href="javascript:void(0)" onclick="main.removeKw(this);return false;" title="删除">╳</a> ';
	newDiv.style.paddingBottom = '10px';
	par.insertBefore(newDiv, null);
}
//删除栏目
main.removeKw = function(obj) {
	var tar = obj.parentNode;
	var par = tar.parentNode;
	//if(confirm('确定删除此频道吗？！')){
	par.removeChild(tar);
	//}
}

-->
</script>
        <tr>
          <td width="150" class="right">留言分类：</td>
          <td>
            <label><span id="add_keyword">
              <?php
	if (count($web['subarea']) > 0) {
		foreach ($web['subarea'] as $key => $val) {
			echo '
			 <span style="white-space:nowrap;"><input name="subarea[]" type="text" size="8" value="'.$val.'" />  <a href="javascript:void(0)" onclick="main.removeKw(this);return false;" title="删除">╳</a></span>';
		}
	} else {
	    echo '当前设置为没有分类';
	}
?>
              </span> <a href="javascript:void(0)" onClick="main.addKw();">增加分类</a></label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">留言主题？</td>
          <td>
            <label>
              <input type="radio" class="radio" name="subj_open" value=""<?php echo $web['subj_open'] !== 10 && $web['subj_open'] !== 11 ? ' checked="checked"' : ''; ?> />
              不用 </label>
            <label>
              <input type="radio" class="radio" name="subj_open" value="10"<?php echo $web['subj_open'] === 10 ? ' checked="checked"' : ''; ?> />
              需要，但可以空着不填写</label>
              <label><input type="radio" class="radio" name="subj_open" value="11"<?php echo $web['subj_open'] === 11 ? ' checked="checked"' : ''; ?> />
              需要，必填</label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">内容限制：</td>
          <td><label>输入不少于
              <input name="min_size" type="text" size="4" value="<?php echo defined('MIN_SIZE') && is_numeric(MIN_SIZE) && MIN_SIZE > 0 ? MIN_SIZE : 7; ?>" />
              字符</label>
			  <label>主题若填写不多于
              <input name="subj_size" type="text" size="4" value="<?php echo SUBJ_SIZE; ?>" />
              字符</label>
            <label>内容不多于
              <input name="word_size" type="text" size="4" value="<?php echo WORD_SIZE; ?>" readonly="readonly" disabled="disabled" />
              字符</label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">文高限制：</td>
          <td>
            <label>文本区域限
              <input name="maxheight_size" type="text" size="4" value="<?php echo abs(MAXHEIGHT_SIZE) > 0 ? abs(MAXHEIGHT_SIZE) : 0; ?>" />
              px高度，超出高度文本内容折叠，显示“此文较长滑动阅读” <span style="color:#999999;">（0为不限制）</span></label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">贴数限制：</td>
          <td>
            <label>每IP日限发
              <input name="day_size" type="text" size="4" value="<?php echo abs(DAY_SIZE) > 0 ? abs(DAY_SIZE) : 0; ?>" />
              条 <span style="color:#999999;">（0为不限制/管理员不限）</span></label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">留言审核：</td>
          <td>
            <label>
              <input type="radio" class="radio" name="review" value="0"<?php echo $web['review'] !== 1 ? ' checked="checked"' : ''; ?> />
              关闭 </label>
            <label>
              <input type="radio" class="radio" name="review" value="1"<?php echo $web['review'] === 1 ? ' checked="checked"' : ''; ?> />
              开启<span style="color:#999999;">（管理员不限）</span> </label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">验证码：</td>
          <td>
            <label>
              <input type="radio" class="radio" name="imcode_close" value="1"<?php echo $web['imcode_close'] === 1 ? ' checked="checked"' : ''; ?> />
              关闭 </label>
            <label>
              <input type="radio" class="radio" name="imcode_close" value="0"<?php echo $web['imcode_close'] !== 1 ? ' checked="checked"' : ''; ?> />
              开启<span style="color:#999999;">（管理员不限）</span> </label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">留言IP：</td>
          <td>
            <label>
              <input type="radio" class="radio" name="ip_open" value="0"<?php echo $web['ip_open'] !== 10 && $web['ip_open'] !== 11 ? ' checked="checked"' : ''; ?> />
              关闭 </label>
            <label>
              <input type="radio" class="radio" name="ip_open" id="ip_open_1" value="1"<?php echo $web['ip_open'] === 10 || $web['ip_open'] === 11? ' checked="checked"' : ''; ?> />
              开启
              <span style="color:#999999;">（<input type="checkbox" class="checkbox" name="ip_show" value="1"<?php echo $web['ip_open']==11?' checked="checked"':''; ?> onClick="document.getElementById('ip_open_1').checked=true;" />
              前台显示，结尾以*替换/管理员不限）</span> </label>
          </td>
        </tr>
        <tr>
          <td width="150" class="right">&nbsp;</td>
          <td style="padding-top:5px;">
            <!--button type="submit" onClick="if(confirm('确定提交吗？！')){main.addSubmitSafe();return true;}else{return false;}" class="send2">提交</button-->
            <button type="submit" class="send2">提交</button>
          </td>
        </tr>
      </table>
    </div>
  </div>
</form>
<?php endif; ?>
</main>
<div id="foot">
  <div class="body">Copyright &copy; <?php echo gmdate("Y", time() + floatval($web["time_pos"]) * 3600); ?> <b><?php echo $web['sitename']; ?></b> All Rights Reserved</div>
  <noscript>
  <div class="warning">您的浏览器不支持javascript，部分功能无法使用！</div>
  </noscript>
</div>
<script language="javascript" type="text/javascript">
<!--

<?php
if (isset($text_js)) echo $text_js;
?>

-->
</script>
</body>
</html>
