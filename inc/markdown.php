<?php
/** Conservative Markdown subset: HTML always escaped; image syntax never loads external assets. */
require_once __DIR__.'/content.php';
function th_md_detect($str) {
    return (bool)preg_match('/(^|\n)\s{0,3}(?:#{1,6}\s|```|~~~|>\s|[-*+]\s|\d+\.\s)|\*\*\S|\[[^\]]+\]\(https?:\/\/|`[^`]+`/um', $str);
}
function th_md_inline($str) {
    $text=text_v2_html($str);
    // Never allow a javascript:, data:, file:, or relative URL through renderer.
    $text=preg_replace_callback('~\[([^\]]{1,150})\]\((https?://[^\s)<>"\']{1,1000})\)~ui',function($m){
        $url=html_entity_decode($m[2],ENT_QUOTES | ENT_HTML5,'UTF-8');
        if (!filter_var($url,FILTER_VALIDATE_URL)) return $m[0];
        return '<a href="'.text_v2_html($url).'" target="_blank" rel="noopener noreferrer nofollow">'.$m[1].'</a>';
    },$text);
    $text=preg_replace('/`([^`\n]+)`/u','<code>$1</code>',$text);
    $text=preg_replace('/\*\*(.+?)\*\*/u','<strong>$1</strong>',$text);
    $text=preg_replace('/__(.+?)__/u','<strong>$1</strong>',$text);
    $text=preg_replace('/~~(.+?)~~/u','<del>$1</del>',$text);
    $text=preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u','<em>$1</em>',$text);
    return $text;
}
function th_md_render($raw) {
    if (!th_md_detect($raw)) return '<div class="entry-raw">'.text_v2_html($raw).'</div>';
    $out='';$code=false;$buffer=array();$list='';
    $lines=preg_split('/\r\n|\r|\n/', $raw);
    foreach($lines as $line){
        if (preg_match('/^\s{0,3}(```|~~~)/u',$line)) {
            if ($list!==''){$out.='</'.$list.'>';$list='';}
            if($code){$out.='<pre><code>'.text_v2_html(implode("\n",$buffer)).'</code></pre>';$buffer=array();$code=false;}
            else $code=true;
            continue;
        }
        if ($code){$buffer[]=$line;continue;}
        if (preg_match('/^\s{0,3}(#{1,6})\s+(.+)$/u',$line,$m)) {
            if($list!==''){$out.='</'.$list.'>';$list='';}
            $n=strlen($m[1]);$out.='<h'.$n.'>'.th_md_inline($m[2]).'</h'.$n.'>';continue;
        }
        if (preg_match('/^\s{0,3}([-*+]\s+|\d+\.\s+)(.*)$/u',$line,$m)){
            $type=preg_match('/^\d/',$m[1])?'ol':'ul';if($list!==$type){if($list!=='')$out.='</'.$list.'>';$out.='<'.$type.'>';$list=$type;}
            $out.='<li>'.th_md_inline($m[2]).'</li>';continue;
        }
        if($list!==''){$out.='</'.$list.'>';$list='';}
        if(trim($line)===''){$out.='<div class="md-break"></div>';continue;}
        if(preg_match('/^\s{0,3}>\s?(.*)$/u',$line,$m)){$out.='<blockquote>'.th_md_inline($m[1]).'</blockquote>';continue;}
        $out.='<p>'.th_md_inline($line).'</p>';
    }
    if($list!=='')$out.='</'.$list.'>';
    if($code)$out.='<pre><code>'.text_v2_html(implode("\n",$buffer)).'</code></pre>';
    return '<div class="markdown-rendered">'.$out.'</div>';
}
