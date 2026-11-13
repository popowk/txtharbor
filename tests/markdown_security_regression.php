<?php
/** v1.5.7 Markdown rendering + limit security regression. */
require dirname(__DIR__).'/inc/markdown.php';
function check($condition,$description){if(!$condition){fwrite(STDERR,"FAIL: $description\n");exit(1);}echo "PASS: $description\n";}
$source="| 标题 | 数字 | 说明 |\n| :--- | ---: | :---: |\n| 测试 | 3 | **重点** |\n| x\\|y | 4 | `a|b` |";
$html=th_md_render($source);
check(strpos($html,'<table>')!==false && strpos($html,'<thead>')!==false && strpos($html,'<td')!==false,'GFM-style table recognized');
check(strpos($html,'md-align-right')!==false && strpos($html,'md-align-center')!==false,'Table alignment rendered safely');
check(strpos($html,'x|y')!==false && strpos($html,'<code>a|b</code>')!==false,'Escaped pipes and code pipes preserved');
$html=th_md_render('![示例图](https://files.example.com/share/1.jpg)');
check(strpos($html,'md-load-image')!==false && strpos($html,'<img')===false,'External image recognized without auto-loading');
check(strpos($html,'data-image-url="https://files.example.com/share/1.jpg"')!==false,'External HTTPS image stored as encoded data only');
foreach(array('javascript:alert(1)','data:image/svg+xml;base64,PHN2Zz4=','file:///etc/passwd','http://example.com/pic.png','https://127.0.0.1/secret','https://internal/secret','https://example.com/a.svg') as $unsafe) {
 $html=th_md_render('![x]('.$unsafe.')');
 check(strpos($html,'data-image-url=')===false,'Reject unsafe image URL '.$unsafe);
}
$html=th_md_render("![\" onerror=alert(1)](https://files.example.com/x.png)\n<script>alert(1)</script>");
check(strpos($html,'<script>')===false && strpos($html,'onerror=')!==false && strpos($html,'&quot; onerror')!==false,'Markdown image labels cannot inject attributes or script tags');
$html=th_md_render('[click](javascript:alert(1))');
check(strpos($html,'href=')===false,'Reject JavaScript link');
check(text_v2_problem(str_repeat('a',9999),9999,'正文')==='','9999 Unicode characters allowed');
check(text_v2_problem(str_repeat('a',10000),9999,'正文')!=='','10000 Unicode characters rejected');
check(text_v2_problem(str_repeat('中',9999),9999,'正文')==='','Unicode codepoint count not UTF-8 byte count');
check(strpos(file_get_contents(dirname(__DIR__).'/index.php'),'data-preview-limit')!==false,'Height limit wired to list rendering');
check(strpos(file_get_contents(dirname(__DIR__).'/run.php'),'HTTP_X_FORWARDED_FOR')===false,'Rate-limit source no longer trusts client-controlled forwarding header');
