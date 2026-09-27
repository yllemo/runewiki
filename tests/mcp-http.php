<?php
// End-to-end checks against PHP's HTTP server and an isolated copy of the app.
// Run: php tests/mcp-http.php (requires proc_open and mbstring).
$repo = dirname(__DIR__);
spl_autoload_register(static function ($class) use ($repo) { require_once $repo . '/core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function copyTree(string $from, string $to): void {
    if (!is_dir($to)) mkdir($to, 0700, true);
    foreach (new DirectoryIterator($from) as $item) {
        if ($item->isDot() || $item->isLink()) continue;
        $target = $to . '/' . $item->getFilename();
        if ($item->isDir()) copyTree($item->getPathname(), $target);
        else copy($item->getPathname(), $target);
    }
}
function request(string $url, string $method = 'GET', array $headers = [], string $body = ''): array {
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers),
        'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $text = file_get_contents($url, false, $context);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return ['status' => (int) $match[1], 'headers' => $http_response_header, 'body' => $text];
}
$fixture = sys_get_temp_dir() . '/runewiki-mcp-http-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
$process = null;
try {
    foreach (['core', 'admin', 'mcp', 'templates'] as $dir) copyTree($repo . '/' . $dir, $fixture . '/' . $dir);
    copy($repo . '/index.php', $fixture . '/index.php');
    mkdir($fixture . '/config'); mkdir($fixture . '/content'); mkdir($fixture . '/data/users', 0700, true);
    file_put_contents($fixture . '/config/config.php', '<?php return ["auth_enabled" => true];');
    file_put_contents($fixture . '/config/namespaces.php', '<?php return ["" => ["acl" => "public"]];');
    file_put_contents($fixture . '/content/start.md', "# Visible\n<ifAuth>\nHTTP_HIDDEN_SECRET\n</ifAuth>");
    $auth = new Auth($fixture . '/data/users/users.php', true, 30, false);
    $auth->setPassword('alice', 'test-password');
    $auth->setPassword('bob', 'other-password');
    $keys = new McpKeys($fixture . '/data/users/mcp', $auth);
    $bobKey = $keys->issue('bob');
    // Fixture-only login. Never copied into or served from the real wiki.
    file_put_contents($fixture . '/router.php', <<<'PHP'
<?php
$_SERVER['HTTPS'] = 'on'; // Simulate trusted TLS termination in the fixture only.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/fixture-login') { session_start(); $_SESSION['runewiki_user'] = 'alice'; echo 'ok'; return; }
require __DIR__ . '/index.php';
PHP);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    check($socket !== false, 'Allocate test port');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $command = [PHP_BINARY, '-d', 'extension_dir="' . str_replace('\\', '/', ini_get('extension_dir')) . '"',
        '-d', 'extension=mbstring', '-S', $address, $fixture . '/router.php'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $fixture . '/server.log', 'a'], 2 => ['file', $fixture . '/server.log', 'a']], $pipes, $fixture);
    check(is_resource($process), 'Start PHP test server'); fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; } usleep(100000);
    }
    check($ready, 'PHP server ready');
    $base = 'http://' . $address;
    $focusPage = request($base . '/start?do=focus');
    check($focusPage['status'] === 200 && str_contains($focusPage['body'], 'const initialArticle =') && !str_contains($focusPage['body'], 'HTTP_HIDDEN_SECRET'), 'Focus opens current article and filters ifAuth');
    check(request($base . '/missing?do=focus')['status'] === 404, 'Missing focus article returns 404');
    check(request($base . '/admin/')['status'] === 302, 'Anonymous admin redirects');
    $login = request($base . '/fixture-login');
    $cookie = '';
    foreach ($login['headers'] as $header) if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $m)) $cookie = $m[1];
    check($cookie !== '', 'Fixture session cookie');
    $page = request($base . '/admin/', 'GET', ['Cookie: ' . $cookie]);
    check($page['status'] === 200 && str_contains($page['body'], 'Min MCP-nyckel'), 'Admin renders MCP tab');
    check((bool) preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $m), 'Admin CSRF token');
    $csrf = $m[1];
    $post = ['action' => 'create_mcp_key', 'active_tab' => 'mcp', 'mcp_days' => '30', 'mcp_password' => 'test-password', 'username' => 'bob'];
    $formHeaders = ['Cookie: ' . $cookie, 'Content-Type: application/x-www-form-urlencoded'];
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($post));
    check($keys->status('alice') === null, 'Missing CSRF cannot issue key');
    $post['csrf_token'] = $csrf;
    $wrongPassword = $post; $wrongPassword['mcp_password'] = 'wrong';
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($wrongPassword));
    check($keys->status('alice') === null, 'Wrong password cannot issue key');
    $created = request($base . '/admin/', 'POST', $formHeaders, http_build_query($post));
    check($created['status'] === 200 && preg_match('/rw_mcp_[a-f0-9]{32}\.[a-f0-9]{64}/', $created['body'], $m) === 1, 'Admin displays issued key');
    $aliceKey = $m[0];
    check($keys->authenticate($aliceKey)['user'] === 'alice', 'Posted username cannot change key owner');
    check($keys->authenticate($bobKey) !== null, 'Other users key unchanged');
    check(!str_contains(request($base . '/admin/', 'GET', ['Cookie: ' . $cookie])['body'], $aliceKey), 'Key shown only at issuance');
    $body = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'read_page',
        'arguments' => ['id' => 'start'], '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => new stdClass()]]]);
    $headers = ['Content-Type: application/json', 'Accept: application/json, text/event-stream',
        'MCP-Protocol-Version: 2026-07-28', 'Mcp-Method: tools/call', 'Mcp-Name: read_page'];
    check(request($base . '/mcp/', 'POST', [...$headers, 'Cookie: ' . $cookie], $body)['status'] === 401, 'Real endpoint ignores browser session');
    $read = request($base . '/mcp/index.php', 'POST', [...$headers, 'Authorization: Bearer ' . $aliceKey], $body);
    check($read['status'] === 200 && !str_contains($read['body'], 'HTTP_HIDDEN_SECRET'), 'Endpoint filters ifAuth');
    check((json_decode($read['body'], true)['result']['structuredContent']['id'] ?? '') === 'start', 'Real endpoint returns Markdown');
    check(request($base . '/mcp', 'POST', [...$headers, 'Authorization: Bearer ' . $aliceKey, 'Origin: https://evil.example'], $body)['status'] === 403, 'Real endpoint checks Origin');
    $post['mcp_ifauth'] = '1';
    $created = request($base . '/admin/', 'POST', $formHeaders, http_build_query($post));
    preg_match('/rw_mcp_[a-f0-9]{32}\.[a-f0-9]{64}/', $created['body'], $m); $scopedKey = $m[0];
    check(request($base . '/mcp/', 'POST', [...$headers, 'Authorization: Bearer ' . $aliceKey], $body)['status'] === 401, 'Admin rotation invalidates old key');
    check(str_contains(request($base . '/mcp/', 'POST', [...$headers, 'Authorization: Bearer ' . $scopedKey], $body)['body'], 'HTTP_HIDDEN_SECRET'), 'Admin checkbox enables ifAuth');
    $post['action'] = 'revoke_mcp_key';
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($post));
    check(request($base . '/mcp/', 'POST', [...$headers, 'Authorization: Bearer ' . $scopedKey], $body)['status'] === 401, 'Admin revoke works');
    check(request($base . '/mcp/')['status'] === 405, 'GET not supported');
    check(!is_file($fixture . '/mcp/log.txt'), 'MCP logging off by default');
    $loggingPost = ['action' => 'save_mcp_logging', 'active_tab' => 'mcp', 'mcp_log_requests' => '1'];
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($loggingPost));
    check(!is_file($fixture . '/config/mcp.php'), 'Logging settings require CSRF');
    $loggingPost['csrf_token'] = $csrf;
    $loggingSaved = request($base . '/admin/', 'POST', $formHeaders, http_build_query($loggingPost));
    check($loggingSaved['status'] === 200, 'Logging setting saves');
    request($base . '/mcp/', 'POST', [...$headers, 'API_TOKEN: ' . $scopedKey], $body);
    $log = file_get_contents($fixture . '/mcp/log.txt');
    $entry = json_decode(trim($log), true);
    check($entry['status'] === 401 && $entry['method'] === 'tools/call' && $entry['tool'] === 'read_page' && $entry['api_token_present'], 'Failed authentication logged with method, tool and header presence');
    check(!str_contains($log, $scopedKey) && !str_contains($log, 'arguments') && !str_contains($log, 'HTTP_HIDDEN_SECRET'), 'Log excludes credentials, arguments and content');
    unset($loggingPost['mcp_log_requests']);
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($loggingPost));
    request($base . '/mcp/', 'POST', $headers, $body);
    check(file_get_contents($fixture . '/mcp/log.txt') === $log, 'Disabling logging stops writes');
    $article = "---\nname: Original\ntags: [alpha, beta]\ndate: 2020-01-02\nsources:\n  - id: source\n    title: Nested title\n---\n# Article\nUpdated body";
    $save = request($base . '/start?do=save', 'POST', $formHeaders, http_build_query(['csrf_token' => $csrf, 'body' => $article]));
    check($save['status'] === 302, 'Editor save succeeds');
    $savedRaw = file_get_contents($fixture . '/content/start.md');
    [$savedMeta] = FrontMatter::parse($savedRaw, true);
    check($savedMeta['name'] === 'Original' && $savedMeta['tags'] === ['alpha', 'beta'] && $savedMeta['date'] === '2020-01-02', 'Save preserves names, tags and dates');
    check($savedMeta['sources'][0]['title'] === 'Nested title' && $savedMeta['generated']['by'] === 'human:alice', 'Save preserves nested YAML and stamps author');
    $articlePage = request($base . '/start', 'GET', ['Cookie: ' . $cookie]);
    check($articlePage['status'] === 200 && !str_contains($articlePage['body'], 'data-metadata-toggle') && str_contains($articlePage['body'], 'Visa Metadata') && str_contains($articlePage['body'], 'do=metadata'), 'Article metadata link renders without toggle');
    $metadataPage = request($base . '/start?do=metadata', 'GET', ['Cookie: ' . $cookie]);
    check($metadataPage['status'] === 200 && str_contains($metadataPage['body'], 'Nested title'), 'Standalone metadata page renders nested fields');
    $invalid = request($base . '/start?do=save', 'POST', $formHeaders, http_build_query(['csrf_token' => $csrf, 'body' => "---\ntags: [broken\n---\nText"]));
    check($invalid['status'] === 422 && file_get_contents($fixture . '/content/start.md') === $savedRaw, 'Invalid YAML cannot overwrite article');
    $metadataPost = ['action' => 'save_metadata', 'active_tab' => 'metadata', 'visible_fields' => ['name'], 'editor_fields' => ['tags']];
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($metadataPost));
    check(!is_file($fixture . '/config/metadata.php'), 'Metadata settings require CSRF');
    $metadataPost['csrf_token'] = $csrf;
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($metadataPost));
    $settings = Metadata::settings($fixture);
    check($settings['visible_fields'] === ['name'] && $settings['editor_fields'] === ['tags'] && !$settings['auto_timestamp'], 'Admin persists metadata choices');
    $metadataPage = request($base . '/start?do=metadata', 'GET', ['Cookie: ' . $cookie]);
    check(str_contains($metadataPage['body'], 'Original') && !str_contains($metadataPage['body'], 'Nested title'), 'Display selection applied without deleting stored metadata');
    echo "PASS: metadata HTTP save, nested YAML, timestamps, display controls, invalid YAML and admin settings\n";
    echo "PASS: real HTTP endpoint, admin CSRF, reauthentication, owner isolation, rotation, revocation and ifAuth\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname());
    }
    rmdir($fixture);
}
