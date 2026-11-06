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
    foreach (['core', 'admin', 'chat', 'mcp', 'templates', 'plugins'] as $dir) copyTree($repo . '/' . $dir, $fixture . '/' . $dir);
    copy($repo . '/index.php', $fixture . '/index.php');
    mkdir($fixture . '/config'); mkdir($fixture . '/content'); mkdir($fixture . '/data/users', 0700, true);
    file_put_contents($fixture . '/config/config.php', '<?php return ["auth_enabled" => true];');
    file_put_contents($fixture . '/config/namespaces.php', '<?php return ["" => ["acl" => "public"]];');
    file_put_contents($fixture . '/content/start.md', "# Visible\n<ifAuth>\nHTTP_HIDDEN_SECRET\n</ifAuth>");
    file_put_contents($fixture . '/config/plugins.php', '<?php return ["redirect" => ["enabled" => true]];');
    file_put_contents($fixture . '/content/redirect-test.md', "---\nredirect: https://example.com/target\n---\n# Redirect test");
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
return require __DIR__ . '/index.php';
PHP);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    check($socket !== false, 'Allocate test port');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $command = [PHP_BINARY, '-d', 'extension_dir="' . str_replace('\\', '/', ini_get('extension_dir')) . '"',
        '-d', 'extension=mbstring', ... (extension_loaded('zip') ? ['-d', 'extension=zip'] : []), '-S', $address, $fixture . '/router.php'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $fixture . '/server.log', 'a'], 2 => ['file', $fixture . '/server.log', 'a']], $pipes, $fixture);
    check(is_resource($process), 'Start PHP test server'); fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; } usleep(100000);
    }
    check($ready, 'PHP server ready');
    $base = 'http://' . $address;
    $redirect = request($base . '/redirect-test');
    check($redirect['status'] === 302 && in_array('Location: https://example.com/target', $redirect['headers'], true) && $redirect['body'] === '', 'Page redirects immediately without rendered HTML');
    check(request($base . '/redirect-test?do=download')['status'] === 200, 'Download bypasses redirect');
    $focusPage = request($base . '/start?do=focus');
    check($focusPage['status'] === 200 && str_contains($focusPage['body'], 'const initialArticle =') && !str_contains($focusPage['body'], 'HTTP_HIDDEN_SECRET'), 'Focus opens current article and filters ifAuth');
    check(request($base . '/missing?do=focus')['status'] === 404, 'Missing focus article returns 404');
    check(request($base . '/admin/')['status'] === 302, 'Anonymous admin redirects');
    $login = request($base . '/fixture-login');
    $cookie = '';
    foreach ($login['headers'] as $header) if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $m)) $cookie = $m[1];
    check($cookie !== '', 'Fixture session cookie');
    foreach ([[], ['Cookie: ' . $cookie]] as $downloadHeaders) {
        $download = request($base . '/start?do=download', 'GET', $downloadHeaders);
        check($download['status'] === 200 && str_contains($download['body'], '# Visible'), 'Download retains public Markdown');
        check(str_contains($download['body'], 'HTTP_HIDDEN_SECRET') === ($downloadHeaders !== []), 'Downloads include login-only content only for authenticated users');
        check(!str_contains($download['body'], '<ifAuth>') && !str_contains($download['body'], '</ifAuth>'), 'Downloads omit auth control tags');
        check(in_array('Content-Length: ' . strlen($download['body']), $download['headers'], true), 'Download length matches filtered content');
    }
    $page = request($base . '/admin/', 'GET', ['Cookie: ' . $cookie]);
    check($page['status'] === 200 && str_contains($page['body'], 'Min MCP-nyckel'), 'Admin renders MCP tab');
    check((bool) preg_match('/name="csrf_token" value="([^"]+)"/', $page['body'], $m), 'Admin CSRF token');
    $csrf = $m[1];
    $colab = request($base . '/start?do=colab');
    check($colab['status'] === 200 && !str_contains($colab['body'], 'HTTP_HIDDEN_SECRET'), 'Guest collaboration respects ifAuth');
    preg_match('/name="csrf_token" value="([^"]+)"/', $colab['body'], $colabToken);
    $guestCookie = '';
    foreach ($colab['headers'] as $header) if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) $guestCookie = $match[1];
    $guestHeaders = ['Cookie: ' . $guestCookie, 'Content-Type: application/x-www-form-urlencoded'];
    $comment = ['csrf_token' => $colabToken[1], 'action' => 'add', 'name' => 'Guest tester', 'text' => 'Guest review', 'quote' => 'Visible'];
    $posted = request($base . '/start?do=colab', 'POST', $guestHeaders, http_build_query($comment));
    check($posted['status'] === 200 && count(json_decode($posted['body'], true)['comments']) === 1, 'Guest can save comment without account');
    $commentId = json_decode($posted['body'], true)['comments'][0]['id'];
    $denied = ['csrf_token' => $colabToken[1], 'action' => 'delete', 'id' => $commentId];
    check(request($base . '/start?do=colab', 'POST', $guestHeaders, http_build_query($denied))['status'] === 403, 'Guests cannot delete comments');
    unset($denied['csrf_token']);
    check(request($base . '/start?do=colab', 'POST', $guestHeaders, http_build_query($denied))['status'] === 403, 'Comments require CSRF');
    $privateComment = ['csrf_token' => $csrf, 'action' => 'add', 'text' => 'PRIVATE_REVIEW', 'quote' => 'HTTP_HIDDEN_SECRET'];
    $memberHeaders = ['Cookie: ' . $cookie, 'Content-Type: application/x-www-form-urlencoded'];
    check(request($base . '/start?do=colab', 'POST', $memberHeaders, http_build_query($privateComment))['status'] === 200, 'Member can comment on private text');
    foreach (['list', 'export-json', 'export-text'] as $op) {
        $guestComments = request($base . '/start?do=colab&op=' . $op);
        check(str_contains($guestComments['body'], 'Guest review') && !str_contains($guestComments['body'], 'PRIVATE_REVIEW') && !str_contains($guestComments['body'], 'HTTP_HIDDEN_SECRET'), 'Guest list/export excludes private comments');
    }
    $resolve = ['csrf_token' => $csrf, 'action' => 'resolve', 'id' => $commentId];
    $resolved = request($base . '/start?do=colab', 'POST', $memberHeaders, http_build_query($resolve));
    check(json_decode($resolved['body'], true)['comments'][0]['resolved'] === true, 'Editor can resolve thread');
    echo "PASS: anonymous collaboration, CSRF, moderation and private comment exports\n";
    $settings = ['csrf_token' => $csrf, 'action' => 'save_settings', 'auth_enabled' => 'on', 'history_enabled' => 'on', 'colab_require_login' => 'on'];
    request($base . '/admin/', 'POST', $memberHeaders, http_build_query($settings));
    $savedConfig = require $fixture . '/config/config.php';
    check(($savedConfig['colab_require_login'] ?? false) === true, 'Admin can add collaboration login requirement to existing config');
    check(request($base . '/start?do=colab')['status'] === 302, 'Protected collaboration sends guests to login');
    check(!str_contains(request($base . '/start')['body'], 'Samarbeta och kommentera'), 'Collaboration menu hidden from guests when view requires login');
    check(str_contains(request($base . '/start', 'GET', $memberHeaders)['body'], 'Samarbeta och kommentera'), 'Collaboration menu visible to members');
    foreach (['list', 'export-json', 'export-text'] as $op) {
        check(request($base . '/start?do=colab&op=' . $op)['status'] === 401, 'Protected collaboration blocks guest data and exports');
        check(request($base . '/start?do=colab&op=' . $op, 'GET', $memberHeaders)['status'] === 200, 'Protected collaboration allows authenticated readers');
    }
    check(request($base . '/start?do=colab', 'POST', $guestHeaders, http_build_query($comment))['status'] === 401, 'Protected collaboration blocks guest writes');
    check(request($base . '/start?do=colab', 'GET', $memberHeaders)['status'] === 200, 'Protected collaboration allows member view');
    unset($settings['colab_require_login']);
    request($base . '/admin/', 'POST', $memberHeaders, http_build_query($settings));
    check(request($base . '/start?do=colab')['status'] === 200, 'Admin can re-enable guest collaboration');
    $settings['colab_feedback_require_login'] = 'on';
    request($base . '/admin/', 'POST', $memberHeaders, http_build_query($settings));
    check((require $fixture . '/config/config.php')['colab_feedback_require_login'] === true, 'Admin saves separate feedback requirement');
    $readOnly = request($base . '/start?do=colab');
    check($readOnly['status'] === 200 && str_contains($readOnly['body'], 'Logga in för att lämna feedback') && str_contains($readOnly['body'], 'id="colab-form" class="gbg-form" hidden'), 'Guests can view comments with login prompt instead of feedback form');
    check(str_contains(request($base . '/start')['body'], 'Samarbeta och kommentera'), 'Public collaboration menu remains visible with protected feedback');
    foreach (['list', 'export-json', 'export-text'] as $op) check(request($base . '/start?do=colab&op=' . $op)['status'] === 200, 'Feedback requirement preserves public read/export');
    foreach (['', $commentId] as $parent) {
        check(request($base . '/start?do=colab', 'POST', $guestHeaders, http_build_query(array_merge($comment, ['parent' => $parent])))['status'] === 401, 'Guest comments and replies blocked on server');
    }
    // Let the real comment throttle expire before testing authorized writes.
    sleep(5);
    check(request($base . '/start?do=colab', 'POST', $memberHeaders, http_build_query($privateComment))['status'] === 200, 'Members can still give feedback');
    unset($settings['colab_feedback_require_login']);
    request($base . '/admin/', 'POST', $memberHeaders, http_build_query($settings));
    check(request($base . '/start?do=colab', 'POST', $guestHeaders, http_build_query($comment))['status'] === 200, 'Turning off feedback requirement restores guest posting');
    echo "PASS: collaboration login setting, view, API, export and admin persistence\n";
    $editRedirect = request($base . '/redirect-test?do=edit', 'GET', ['Cookie: ' . $cookie]);
    check($editRedirect['status'] === 200 && str_contains($editRedirect['body'], 'https://example.com/target'), 'Redirect page remains editable');
    if (extension_loaded('zip')) {
        $zip = new ZipArchive(); $zip->open($fixture . '/import.zip', ZipArchive::CREATE);
        $zip->addFromString('content/imported/page.md', '# Imported'); $zip->close();
        $archive = file_get_contents($fixture . '/import.zip');
        $importHeaders = ['Cookie: ' . $cookie, 'Content-Type: application/x-www-form-urlencoded'];
        $begin = ['action' => 'import_begin', 'size' => strlen($archive)];
        check(request($base . '/admin/?do=import_api', 'POST', $importHeaders, http_build_query($begin))['status'] === 403, 'Chunk upload requires CSRF');
        $begin['csrf_token'] = $csrf;
        $job = json_decode(request($base . '/admin/?do=import_api', 'POST', $importHeaders, http_build_query($begin))['body'], true)['job'];
        while ($job['received'] < strlen($archive)) {
            $offset = $job['received']; $chunk = substr($archive, $offset, 53); $boundary = 'chunkboundary';
            $body = '';
            foreach (['csrf_token' => $csrf, 'action' => 'import_chunk', 'id' => $job['id'], 'offset' => $offset] as $k => $v) $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"chunk\"; filename=\"part.bin\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $chunk . "\r\n--$boundary--\r\n";
            $headers = ['Cookie: ' . $cookie, 'Content-Type: multipart/form-data; boundary=' . $boundary];
            $job = json_decode(request($base . '/admin/?do=import_api', 'POST', $headers, $body)['body'], true)['job'];
            $retry = json_decode(request($base . '/admin/?do=import_api', 'POST', $headers, $body)['body'], true)['job'];
            check($job['received'] === $offset + strlen($chunk) && $retry['received'] === $job['received'], 'Chunk retry does not duplicate bytes');
        }
        $step = ['csrf_token' => $csrf, 'action' => 'import_step', 'id' => $job['id'], 'offset' => 0];
        $wrongJob = $step; $wrongJob['id'] = 'unknown';
        check(request($base . '/admin/?do=import_api', 'POST', $importHeaders, http_build_query($wrongJob))['status'] === 422, 'Unknown import job rejected');
        $done = json_decode(request($base . '/admin/?do=import_api', 'POST', $importHeaders, http_build_query($step))['body'], true)['job'];
        check($done['done'] && $done['imported'] === 1 && file_get_contents($fixture . '/content/imported/page.md') === '# Imported', 'Chunked ZIP imported to original folder');
        $retry = json_decode(request($base . '/admin/?do=import_api', 'POST', $importHeaders, http_build_query($step))['body'], true)['job'];
        check($retry['done'] && $retry['imported'] === 1 && !glob($fixture . '/data/imports/*.zip'), 'Completed batch retry safe and temporary ZIP removed');
        echo "PASS: chunked ZIP upload, retry offsets, CSRF, job validation and paged import\n";
    }
    // Real multipart uploads exercise PHP's is_uploaded_file/move_uploaded_file,
    // not a mocked filesystem write. Follow the namespace supplied to the editor.
    $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    foreach (['start' => '', 'projekt:api' => 'projekt', 'projekt:api:start' => 'projekt', 'annat:ny' => 'annat'] as $articleId => $expectedNamespace) {
        $pageId = new PageId($articleId);
        $editorPage = request($base . $pageId->url() . '?do=edit', 'GET', ['Cookie: ' . $cookie]);
        check($editorPage['status'] === 200 && str_contains($editorPage['body'], 'window.WIKI_MEDIA_NS  = ' . json_encode($expectedNamespace) . ';'), 'Editor supplies correct upload namespace for ' . $articleId);
        $filename = 'bild-' . str_replace(':', '-', $articleId) . '.png';
        $boundary = 'runewiki-test-' . bin2hex(random_bytes(12));
        $multipart = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n" . $csrf
            . "\r\n--" . $boundary . "\r\nContent-Disposition: form-data; name=\"upload\"; filename=\"" . $filename
            . "\"\r\nContent-Type: image/png\r\n\r\n" . $imageBytes . "\r\n--" . $boundary . "--\r\n";
        $uploadPath = '/images/' . ($expectedNamespace === '' ? '' : $expectedNamespace . '/');
        $uploadHeaders = ['Cookie: ' . $cookie, 'Accept: application/json', 'Content-Type: multipart/form-data; boundary=' . $boundary];
        $uploaded = request($base . $uploadPath . '?do=upload', 'POST', $uploadHeaders, $multipart);
        $result = json_decode($uploaded['body'], true);
        check($uploaded['status'] === 200 && ($result['ok'] ?? false), 'Multipart upload succeeds for ' . $articleId);
        check($result['url'] === $uploadPath . $filename, 'Returned URL matches namespace');
        check(file_get_contents($fixture . $result['url']) === $imageBytes, 'Image bytes saved in correct folder');
        check(request($base . $result['url'], 'GET', ['Cookie: ' . $cookie])['body'] === $imageBytes, 'Returned image URL serves uploaded bytes');
        if ($expectedNamespace !== '') check(!is_file($fixture . '/images/' . $filename), 'Namespaced image never lands in root');
        $rejectedName = 'rejected-' . $filename;
        $badUpload = str_replace([$csrf, $filename], ['invalid-csrf', $rejectedName], $multipart);
        check(request($base . $uploadPath . '?do=upload', 'POST', $uploadHeaders, $badUpload)['status'] === 403, 'Upload requires valid CSRF');
        check(!is_file($fixture . $uploadPath . $rejectedName), 'Rejected upload writes no file');
    }
    echo "PASS: editor namespace, real multipart uploads, exact folders, image URLs and CSRF\n";
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
    $externalPost = ['action' => 'save_external_auth', 'active_tab' => 'auth', 'auth_password' => 'test-password',
        'oidc_enabled' => '1', 'oidc_label' => 'Organisationens konto', 'oidc_issuer' => 'https://id.example',
        'oidc_client_id' => 'wiki', 'oidc_client_secret' => 'fixture-secret-not-for-html',
        'oidc_redirect_uri' => 'https://wiki.example/?do=login&provider=oidc&callback=1',
        'oidc_token_auth' => 'client_secret_basic', 'oidc_users' => '{"subject-123":"alice"}',
        'ad_label' => 'AD', 'ad_users' => '{}'];
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($externalPost));
    check(!is_file($fixture . '/config/auth.php'), 'External auth settings require CSRF');
    $externalPost['csrf_token'] = $csrf;
    $wrong = $externalPost; $wrong['auth_password'] = 'wrong';
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($wrong));
    check(!is_file($fixture . '/config/auth.php'), 'External auth settings require local reauthentication');
    $savedAuth = request($base . '/admin/', 'POST', $formHeaders, http_build_query($externalPost));
    check($savedAuth['status'] === 200 && is_file($fixture . '/config/auth.php'), 'Save external auth settings');
    check(!str_contains($savedAuth['body'], 'fixture-secret-not-for-html'), 'Client secret never rendered');
    $externalPost['oidc_client_secret'] = '';
    request($base . '/admin/', 'POST', $formHeaders, http_build_query($externalPost));
    check(ExternalAuth::settings($fixture)['oidc']['client_secret'] === 'fixture-secret-not-for-html', 'Blank secret retains stored value');
    $loginPage = request($base . '/?do=login');
    check(str_contains($loginPage['body'], 'Organisationens konto') && str_contains($loginPage['body'], 'Lokalt wikikonto'), 'Login shows enabled provider and local fallback');
    $callback = request($base . '/?do=login&provider=oidc&callback=1&state=invalid&code=invalid');
    check(str_contains($callback['body'], 'kunde inte verifieras'), 'Reject unsolicited callback');
    echo "PASS: external auth admin CSRF, reauthentication, secret preservation and login controls\n";
    // Per-page login must work even in an otherwise completely open wiki.
    file_put_contents($fixture . '/config/config.php', '<?php return ["auth_enabled" => false];');
    $protectedRaw = "---\nrequire_login: true\ntags: [PAGE_SECRET_TAG]\n---\n# PAGE_SECRET_TITLE\nPAGE_SECRET_BODY\n[[start]]";
    file_put_contents($fixture . '/content/protected.md', $protectedRaw);
    foreach (['', 'download', 'metadata', 'reader', 'focus', 'colab', 'edit', 'history', 'move'] as $operation) {
        $url = $base . '/protected' . ($operation ? '?do=' . $operation : '');
        $denied = request($url);
        check($denied['status'] === 302 && !str_contains($denied['body'], 'PAGE_SECRET'), 'Guest blocked from page surface: ' . $operation);
        check(request($url, 'GET', $memberHeaders)['status'] === 200, 'Member allowed page surface: ' . $operation);
    }
    foreach (['save', 'delete', 'form', 'restore', 'move', 'colab'] as $operation) {
        check(request($base . '/protected?do=' . $operation, 'POST', $guestHeaders, http_build_query(['csrf_token' => $colabToken[1], 'body' => '# Overwritten']))['status'] === 302, 'Guest write bypass blocked: ' . $operation);
    }
    check(file_get_contents($fixture . '/content/protected.md') === $protectedRaw, 'Protected file unchanged after bypass attempts');
    foreach (['PAGE_SECRET', 'protected', 'tag:PAGE_SECRET_TAG', 'ns:_root'] as $query) {
        $searchBody = request($base . '/?do=search&q=' . rawurlencode($query))['body'];
        check(!str_contains($searchBody, 'PAGE_SECRET_TITLE') && !str_contains($searchBody, 'PAGE_SECRET_BODY'), 'Guest wiki search hides page in every search mode');
    }
    check(!str_contains(request($base . '/start')['body'], 'protected'), 'Guest footer and backlinks hide page');
    check(str_contains(request($base . '/?do=search&q=PAGE_SECRET', 'GET', $memberHeaders)['body'], 'PAGE_SECRET'), 'Member search includes page');
    mkdir($fixture . '/skills/test', 0700, true);
    file_put_contents($fixture . '/skills/test/SKILL.md', "---\nname: Test\ninclude: [protected]\n---\nInstructions");
    foreach (['list-content', 'search-content&q=PAGE_SECRET', 'search-content&q=tag%3APAGE_SECRET_TAG', 'list-tags', 'list-namespaces', 'get-content&id=protected', 'get-skill&skill=test'] as $query) {
        $result = request($base . '/chat/?action=' . $query);
        check($result['status'] === 200 && !str_contains($result['body'], 'PAGE_SECRET'), 'Chat guest surface filtered: ' . $query);
    }
    check(str_contains(request($base . '/chat/?action=get-content&id=protected', 'GET', $memberHeaders)['body'], 'PAGE_SECRET_BODY'), 'Member chat read allowed');
    check(str_contains(request($base . '/chat/?action=get-skill&skill=test', 'GET', $memberHeaders)['body'], 'PAGE_SECRET_BODY'), 'Member skill include allowed');
    file_put_contents($fixture . '/content/_sidebar.md', "---\nrequire_login: true\n---\nSIDEBAR_SECRET");
    file_put_contents($fixture . '/content/_topbar.md', "---\nrequire_login: true\n---\n- [TOPBAR_SECRET](/start)");
    check(!str_contains(request($base . '/start')['body'], 'BAR_SECRET'), 'Protected system pages hidden from guest layout');
    check(!str_contains(request($base . '/chat/')['body'], 'TOPBAR_SECRET'), 'Protected topbar hidden in chat');
    check(str_contains(request($base . '/start', 'GET', $memberHeaders)['body'], 'SIDEBAR_SECRET'), 'Member sidebar readable');
    $history = new History($fixture . '/data/history', true);
    $history->snapshot(new PageId('public-now'), $protectedRaw);
    $revision = $history->revisions(new PageId('public-now'))[0];
    file_put_contents($fixture . '/content/public-now.md', '# Public now');
    check(request($base . '/public-now?do=history&revision=' . $revision)['status'] === 302, 'Protected historical revision remains gated after page becomes public');
    file_put_contents($fixture . '/content/links.md', '# Links' . "\n[[protected]]");
    request($base . '/links', 'GET', $memberHeaders);
    check(!str_contains(request($base . '/links')['body'], 'PAGE_SECRET_TITLE'), 'Member-rendered title cannot leak via guest cache');
    check(in_array('Cache-Control: private, no-store', request($base . '/protected', 'GET', $memberHeaders)['headers'], true), 'Private responses cannot enter shared HTTP cache');
    echo "PASS: page frontmatter protection across views, writes, search, chat, menus, history and cache\n";
    echo "PASS: real HTTP endpoint, admin CSRF, reauthentication, owner isolation, rotation, revocation and ifAuth\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname());
    }
    rmdir($fixture);
}
