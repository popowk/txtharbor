<?php
/**
 * A deliberately small, dependency-free Markdown renderer.
 * All user-authored HTML is escaped. External images are opt-in per click:
 * no third-party fetch is performed simply by viewing the message.
 */
require_once __DIR__.'/content.php';
function th_md_detect($str) {
    return is_string($str) && (bool)preg_match('/(^|\n)\s{0,3}(?:#{1,6}\s|```|~~~|>\s|[-*+]\s|\d+\.\s)|\*\*\S|\[[^\]]+\]\(https?:\/\/|!\[[^\]]*\]\(|`[^`]+`|(^|\n)\s*\|[^\n]*\|/um', $str);
}
/** Restrict link URLs, and require HTTPS and public-domain hostnames for images. */
function th_md_safe_url($url, $image = false) {
    if (!is_string($url) || strlen($url)>1800 || preg_match('/[\x00-\x20\x7f<>"\'`]/', $url)) return null;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;
    $parts = @parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return null;
    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'https' && ($image || $scheme !== 'http')) return null;
    if (isset($parts['user']) || isset($parts['pass'])) return null;
    $host = strtolower(rtrim($parts['host'], '.'));
    if ($image) {
        if (filter_var($host, FILTER_VALIDATE_IP) || strpos($host,'.')===false ||
            preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid)$/D', $host) ||
            preg_match('/\.(?:svg|svgz)(?:\?|#|$)/i', $url)) return null;
    }
    return $url;
}
/** Apply emphasis only to escaped plain-text segments, never to generated tags/URLs. */
function th_md_plain($str) {
    $str = text_v2_html($str);
    $str = preg_replace('/\*\*([^*\n]+)\*\*/u','<strong>$1</strong>',$str);
    $str = preg_replace('/__([^_\n]+)__/u','<strong>$1</strong>',$str);
    $str = preg_replace('/~~([^~\n]+)~~/u','<del>$1</del>',$str);
    $str = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u','<em>$1</em>',$str);
    return $str;
}
function th_md_inline($raw) {
    $pattern = '/(!?)\[([^\]\n]{0,180})\]\(([^\s)<>"\'`]{1,1800})\)|`([^`\n]+)`/u';
    if (!preg_match_all($pattern,$raw,$matches,PREG_OFFSET_CAPTURE|PREG_SET_ORDER)) return th_md_plain($raw);
    $offset=0;$html='';
    foreach ($matches as $m) {
        $start=$m[0][1];$token=$m[0][0];
        $html.=th_md_plain(substr($raw,$offset,$start-$offset));
        if (isset($m[4]) && $m[4][1]!==-1 && $m[4][0]!=='') {
            $html.='<code>'.text_v2_html($m[4][0]).'</code>';
        } else {
            $isImage=$m[1][0]==='!';$label=$m[2][0];$url=$m[3][0];
            $safe=th_md_safe_url($url,$isImage);
            if ($safe===null) $html.=text_v2_html($token);
            elseif ($isImage) {
                // The browser never fetches an arbitrary remote image without a click.
                $escaped=text_v2_html($safe);
                $html.='<span class="md-external-image" data-image-url="'.$escaped.'">'
                    .'<span class="md-image-description">'.($label!==''?text_v2_html($label):'外部图片').'</span>'
                    .'<button type="button" class="md-load-image" aria-label="加载外部图片">加载图片预览</button>'
                    .'<a href="'.$escaped.'" target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer">打开原图</a>'
                    .'</span>';
            } else {
                $html.='<a href="'.text_v2_html($safe).'" target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer">'
                    .text_v2_html($label).'</a>';
            }
        }
        $offset=$start+strlen($token);
    }
    return $html.th_md_plain(substr($raw,$offset));
}
/** Split a GFM-like row, respecting escaped pipes and inline code spans. */
function th_md_table_cells($line) {
    $line=trim($line);
    if ($line!=='' && $line[0]==='|') $line=substr($line,1);
    if ($line!=='' && substr($line,-1)==='|' && !preg_match('/\\\\+\|$/',$line)) $line=substr($line,0,-1);
    $parts=array();$cell='';$length=strlen($line);$inCode=false;
    for($i=0;$i<$length;$i++) {
        $ch=$line[$i];
        if($ch==='\\' && $i+1<$length && $line[$i+1]==='|') {$cell.='|';$i++;continue;}
        if($ch==='`'){$inCode=!$inCode;$cell.=$ch;continue;}
        if($ch==='|' && !$inCode){$parts[]=trim($cell);$cell='';continue;}
        $cell.=$ch;
    }
    $parts[]=trim($cell);
    return $parts;
}
function th_md_table_delimiter($line,$count) {
    $parts=th_md_table_cells($line);
    if ($count<2 || $count>32 || count($parts)!==$count) return false;
    foreach($parts as $p) if (!preg_match('/^:?-{3,}:?$/D',$p)) return false;
    return $parts;
}
function th_md_table_align($rule) {
    return (substr($rule,0,1)===':' && substr($rule,-1)===':')?' md-align-center':
       (substr($rule,-1)===':'?' md-align-right':'');
}
function th_md_render($raw) {
    if (!th_md_detect($raw)) return '<div class="entry-raw">'.text_v2_html($raw).'</div>';
    $lines=preg_split('/\r\n|\r|\n/',$raw);$count=count($lines);
    $out='';$code=false;$buffer=array();$list='';
    for($i=0;$i<$count;$i++){
        $line=$lines[$i];
        if (preg_match('/^\s{0,3}(```|~~~)/u',$line)) {
            if($list!==''){$out.='</'.$list.'>';$list='';}
            if($code){$out.='<pre><code>'.text_v2_html(implode("\n",$buffer)).'</code></pre>';$buffer=array();$code=false;}
            else $code=true;
            continue;
        }
        if($code){$buffer[]=$line;continue;}
        if($i+1<$count && strpos($line,'|')!==false) {
            $header=th_md_table_cells($line);
            $delim=th_md_table_delimiter($lines[$i+1],count($header));
            if($delim!==false) {
                if($list!==''){$out.='</'.$list.'>';$list='';}
                $out.='<div class="md-table-scroll" role="region" aria-label="Markdown 表格" tabindex="0"><table><thead><tr>';
                foreach($header as $c=>$value)$out.='<th scope="col" class="'.trim(th_md_table_align($delim[$c])).'">'.th_md_inline($value).'</th>';
                $out.='</tr></thead><tbody>';
                $i++;
                $rows=0;
                while($i+1<$count && trim($lines[$i+1])!=='' && strpos($lines[$i+1],'|')!==false && $rows<250) {
                    $i++;$rows++;$cells=th_md_table_cells($lines[$i]);$out.='<tr>';
                    foreach($header as $c=>$_) $out.='<td class="'.trim(th_md_table_align($delim[$c])).'">'.th_md_inline($cells[$c]??'').'</td>';
                    $out.='</tr>';
                }
                $out.='</tbody></table></div>';
                continue;
            }
        }
        if(preg_match('/^\s{0,3}(#{1,6})\s+(.+)$/u',$line,$m)){
            if($list!==''){$out.='</'.$list.'>';$list='';}
            $n=strlen($m[1]);$out.='<h'.$n.'>'.th_md_inline($m[2]).'</h'.$n.'>';continue;
        }
        if(preg_match('/^\s{0,3}([-*+]\s+|\d+\.\s+)(.*)$/u',$line,$m)){
            $type=preg_match('/^\d/',$m[1])?'ol':'ul';
            if($list!==$type){if($list!=='')$out.='</'.$list.'>';$out.='<'.$type.'>';$list=$type;}
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
