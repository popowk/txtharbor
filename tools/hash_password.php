<?php
// Local setup helper only. Never log plaintext admin passwords.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
fwrite(STDERR, "Enter a NEW administrator password: ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 8) { fwrite(STDERR, "Use at least 8 characters.\n"); exit(1); }
echo substr(sha1(md5($password)), 4, 32), PHP_EOL;
