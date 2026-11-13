<?php
/* TxtHarbor-build:1.5.7 */
/** Shared accessible administrator login panel. */
function th_admin_login_html($from = 'index') {
    $errors = array('empty'=>'请输入管理密钥','invalid'=>'管理密钥不正确，请重试','storage'=>'登录密钥正确，但服务器无法保存认证会话；请检查 data 目录或 PHP 临时目录写权限','locked'=>'密码尝试次数过多，请 15 分钟后重试','cookie'=>'服务器已接受管理密钥，但浏览器未保存登录 Cookie；请检查站点 Cookie、反向代理和 CDN 缓存');
    $code = isset($_GET['login_error']) && is_string($_GET['login_error']) ? $_GET['login_error'] : '';
    if ($code === '' && isset($_GET['login_check']) && $_GET['login_check'] === '1') $code = 'cookie';
    $message = $errors[$code] ?? '';
    $returnTo = $from === 'admin' ? 'admin' : 'index';
    return '<div id="submit_safe" class="admin-login-overlay" role="presentation"><div id="submit_safe_in"></div><table id="submit_safe_in2" role="presentation"><tr><td><div id="submit_safe_in22" class="admin-login-card">'
        .'<div class="admin-login-icon" aria-hidden="true">✦</div><h2 id="admin-login-heading">管理员登录</h2><p class="admin-login-help">输入管理密钥以访问站点管理功能</p>'
        .($message!==''?'<p class="admin-login-error" role="alert">'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>':'')
        .'<form action="run.php" method="post" id="admin-login-form" accept-charset="UTF-8">'
        .'<input type="hidden" name="act" value="login"><input type="hidden" name="login_from" value="'.$returnTo.'">'
        .'<label class="admin-login-label" for="admin-password">管理密钥</label>'
        .'<input type="password" name="password" id="admin-password" class="admin-login-password" required autocomplete="current-password" placeholder="请输入管理密钥" autofocus>'
        .'<div class="admin-login-footer"><label class="admin-remember"><input type="checkbox" name="save_cookie" value="1" checked> 记住登录</label><a href="./" class="admin-cancel">返回首页</a></div>'
        .'<button class="admin-login-submit" type="submit">登录管理后台 <span aria-hidden="true">→</span></button>'
        .'</form></div></td></tr></table></div>';
}
