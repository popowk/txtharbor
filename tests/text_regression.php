<?php
require dirname(__DIR__).'/inc/content.php';
function check($ok, $description) {
    if (!$ok) { fwrite(STDERR, 'FAIL: '.$description."\n"); exit(1); }
    fwrite(STDOUT, 'PASS: '.$description."\n");
}
$sample = "  C:\\path\\name /api/v1 | <span>&</span> \t123\n\n😀  ";
$subject = "0 /\\ | 标题";
$unpacked = text_v2_unpack(text_v2_pack($subject, $sample));
check(is_array($unpacked) && $unpacked['subject'] === $subject && $unpacked['content'] === $sample, 'Exact UTF-8 body + title round-trip');
$legacy = iconv('UTF-8', 'GB18030', '标题<第一行>第二行 &gt; \\server');
$decoded = text_v2_message($legacy);
check($decoded['subject'] === '标题' && $decoded['content'] === "第一行\n第二行 > \\server", 'Legacy GB18030 text decoding');
$reply = " \\server\\share\n\t  /api | " . '"';
check(text_v2_reply(' V2REPLY:'.base64_encode($reply)) === $reply, 'Replies preserve leading spaces and symbols');
check(text_v2_html('<img src=x onerror=1>') === '&lt;img src=x onerror=1&gt;', 'HTML safe output escaping');
check(text_v2_length('A中😀') === 3, 'Unicode character length');
