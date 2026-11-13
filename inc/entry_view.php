<?php
/* TxtHarbor-build:1.5.7 */
require_once __DIR__.'/entries.php';
require_once __DIR__.'/markdown.php';
function th_entry_body_html($entry, $show_admin=false) {
    if (!th_entry_visible($entry) && !$show_admin) return '<p class="entry-muted">该留言已过期。</p>';
    if ($entry['pending'] && !$show_admin) return '<p class="entry-muted">内容审核中</p>';
    if (!th_entry_unlocked($entry)) return '<div class="entry-locked">🔒 这是一条密码保护的留言。<a href="'.text_v2_html(th_entry_share($entry)).'">输入密码查看</a></div>';
    $raw_id='raw_'.$entry['id'];
    $content='<div class="entry-copy-source" id="'.$raw_id.'" hidden>'.text_v2_html($entry['content']).'</div>';
    if ($entry['subject']!=='') $content.='<h2 class="entry-subject">'.text_v2_html($entry['subject']).'</h2>';
    $content.='<div class="entry-content">'.th_md_render($entry['content']).'</div>';
    if (th_md_detect($entry['content'])) $content.='<details class="entry-source-view"><summary>查看 Markdown 原文</summary><pre>'.text_v2_html($entry['content']).'</pre></details>';
    return $content;
}
function th_entry_controls($entry, $admin=false) {
    $url=th_entry_share($entry);
    $locked=($entry['password_hash'] !== '');
    $remembered=$locked ? th_share_secret($entry) : '';
    $quickShare=($locked && is_string($remembered)) ? th_entry_share($entry).'#key='.rawurlencode($remembered) : '';
    $quickAttribute=$quickShare!==''?' data-share-ready="'.text_v2_html($quickShare).'"':'';
    $html='<a class="entry-action" href="'.text_v2_html($url).'">查看留言</a><button type="button" class="entry-action" data-share-url="'.text_v2_html($url).'"'
        .' data-share-protected="'.($locked?'1':'0').'" data-share-csrf="'.text_v2_html(th_access_csrf()).'"'.$quickAttribute
        .' onclick="main.shareEntry(this)">复制分享链接</button>';
    if (th_entry_public_content($entry) || $admin) {
        $html.='<button type="button" class="copy-btn" onclick="main.copyEntry(\'raw_'.$entry['id'].'\',this)">复制原文 / MD</button>';
    }
    if ($admin) $html.='<a class="entry-action" href="'.text_v2_html($url).'&amp;edit=1">编辑 / 置顶</a>';
    return $html;
}
