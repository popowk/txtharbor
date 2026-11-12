<?php
require dirname(__DIR__).'/inc/github_update.php';
function assert_up($pass, $label) {
    if (!$pass) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
$settings = th_update_defaults();
$repo = th_update_repo($settings['project_url']);
assert_up($repo && $repo['owner'] === 'popowk' && $repo['repo'] === 'txtharbor', 'GitHub default project');
assert_up(th_update_proxy($settings, $repo['url']) === 'https://git.wksite.cn/popowk/txtharbor', 'Repository mirror short path');
assert_up(th_update_proxy($settings, 'https://github.com/popowk/txtharbor/releases/download/v1.3.0/txtharbor-update.zip') === 'https://git.wksite.cn/popowk/txtharbor/releases/download/v1.3.0/txtharbor-update.zip', 'Release short proxy path');
assert_up(th_update_proxy($settings, 'https://api.github.com/repos/popowk/txtharbor/releases/latest') === 'https://git.wksite.cn/https://api.github.com/repos/popowk/txtharbor/releases/latest', 'GitHub API relay path');
assert_up(th_update_proxy($settings, 'http://localhost/data') === null, 'Reject offsite proxy destinations');
assert_up(th_update_proxy_repo('https://git.wksite.cn/other/repo', $repo) === null, 'Reject proxy repository mismatch');
assert_up(th_update_repo('https://github.com/popowk/txtharbor.evil/extraneous') === null, 'Reject injected repository paths');
$settings['download_mode'] = 'auto';
assert_up(count(th_update_candidates($repo['url'], $settings)) === 2, 'Auto direct and proxy');
$settings['download_mode'] = 'proxy';
assert_up(th_update_candidates($repo['url'], $settings) === ['https://git.wksite.cn/popowk/txtharbor'], 'Proxy only mode');
