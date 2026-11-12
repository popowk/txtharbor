<?php
/**
 * Versioned text records. The existing data/file.php contains GB2312/GBK
 * byte-offset-indexed entries. NEVER recode that file in place: its index
 * references byte positions, not Unicode character positions.
 *
 * V2 writes use UTF-8 JSON inside base64; slashes, backslashes, pipes,
 * leading/trailing spaces, newlines, tabs and HTML are lossless.
 */
function text_v2_valid_utf8($value) {
    return is_string($value) && preg_match('//u', $value) === 1;
}
function text_v2_length($value) {
    // Counts Unicode code points, including emoji, instead of bytes.
    return preg_match_all('/./us', $value, $m);
}
function text_v2_html($value) {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function text_v2_pack($title, $content) {
    return 'V2JSON:'.base64_encode(json_encode(array('subject' => $title, 'content' => $content), JSON_UNESCAPED_UNICODE));
}
function text_v2_unpack($raw) {
    if (strncmp($raw, 'V2JSON:', 7) !== 0) return false;
    $bytes = base64_decode(substr($raw, 7), true);
    if ($bytes === false) return false;
    $parsed = json_decode($bytes, true);
    if (!is_array($parsed) || !isset($parsed['subject']) || !isset($parsed['content']) ||
        !is_string($parsed['subject']) || !is_string($parsed['content'])) return false;
    return $parsed;
}
function text_v2_to_utf8($value) {
    if (text_v2_valid_utf8($value)) return $value;
    $converted = @iconv('GB18030', 'UTF-8//IGNORE', $value);
    return $converted !== false ? $converted : '';
}
/** Decode legacy GBK config values in memory without touching site secrets. */
function text_v2_config_utf8($web) {
    foreach (array('sitename', 'sitefirm') as $field) {
        if (isset($web[$field]) && is_string($web[$field])) {
            $web[$field] = text_v2_to_utf8($web[$field]);
        }
    }
    if (isset($web['subarea']) && is_array($web['subarea'])) {
        foreach ($web['subarea'] as $key => $value) {
            if (is_string($value)) $web['subarea'][$key] = text_v2_to_utf8($value);
        }
    }
    return $web;
}
function text_v2_legacy($value) {
    // In legacy records only a literal '>' represents a line break; a real
    // greater-than sign was saved as &gt;. Decode entities AFTER replacement.
    $value = text_v2_to_utf8($value);
    $value = str_replace('>', "\n", $value);
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function text_v2_message($raw) {
    $v2 = text_v2_unpack($raw);
    if ($v2 !== false) return $v2;
    $split = strpos($raw, '<');
    if ($split !== false) {
        return array('subject' => text_v2_legacy(substr($raw, 0, $split)),
                     'content' => text_v2_legacy(substr($raw, $split + 1)));
    }
    return array('subject' => '', 'content' => text_v2_legacy($raw));
}
function text_v2_reply($raw) {
    // Legacy approved records end with a single spacer after the last pipe.
    if (strncmp($raw, ' V2REPLY:', 9) === 0) $raw = substr($raw, 1);
    if (strncmp($raw, 'V2REPLY:', 8) === 0) {
        $decoded = base64_decode(substr($raw, 8), true);
        if ($decoded !== false && text_v2_valid_utf8($decoded)) return $decoded;
    }
    return text_v2_legacy($raw);
}
function text_v2_problem($value, $limit, $type) {
    if (!text_v2_valid_utf8($value)) return '文本编码有误，请使用 UTF-8 提交';
    $n = text_v2_length($value);
    if (trim($value) === '' || $n < 1) return $type.'至少需要 1 个有效字符';
    if ($limit > 0 && $n > $limit) return $type.'最多允许 '.$limit.' 个字符';
    return '';
}
function text_v2_check($value, $limit, $type) {
    $error = text_v2_problem($value, $limit, $type);
    if ($error !== '') {
        $safe = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP |
            JSON_HEX_APOS | JSON_HEX_QUOT);
        die('<script>main.addSubmitSafe_("i", '.$safe.');</script>'.
            htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));
    }
}
/** Token for state-changing message deletion, tied to the existing admin session. */
function text_v2_delete_token() {
    $cookie = isset($_COOKIE['manage']) ? (string)$_COOKIE['manage'] : '';
    return hash_hmac('sha256', 'delete-message-v1', $cookie.'|'.(string)@file_get_contents('data/admin_key.php'));
}
?>
