<?php
// php tests/mcp.php — isolated fixtures; no production accounts or keys are touched.
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/runewiki-mcp-test-' . bin2hex(random_bytes(8));
mkdir($root . '/content/private', 0700, true);
mkdir($root . '/content/login', 0700, true);
mkdir($root . '/users', 0700, true);
try {
    $auth = new Auth($root . '/users/users.php', false, 30, false);
    $auth->setPassword('alice', 'example-password');
    $auth->setUserGroups('alice', []);
    $auth->setPassword('bob', 'different-password');
    $auth->setUserGroups('bob', ['private-reader']);
    $keys = new McpKeys($root . '/users/mcp', $auth);
    $alice = $keys->issue('alice');
    $bob = $keys->issue('bob', true);
    check(!str_contains(file_get_contents($root . '/users/mcp/' . substr(hash('sha256', 'alice'), 0, 32) . '.php'), $alice), 'Never store bearer keys');
    file_put_contents($root . '/content/start.md', "# Public title\nPublic text\n<ifAuth>\n# Hidden title\nHIDDEN_NEEDLE\n<ifAuth>\nNESTED_SECRET\n</ifAuth>\n</ifAuth>\nAfter\n");
    file_put_contents($root . '/content/private/secret.md', "# PRIVATE_TITLE\nPRIVATE_NEEDLE");
    file_put_contents($root . '/content/login/members.md', '# Members');
    file_put_contents($root . '/content/_sidebar.md', '# System file');
    file_put_contents($root . '/outside.md', 'OUTSIDE_SECRET');
    $namespaces = new NamespaceResolver(['' => ['acl' => 'public'], 'private' => ['acl' => 'private'], 'login' => ['acl' => 'login']]);
    $content = new McpContent($root . '/content', $auth, $namespaces, new Acl(['private-reader' => ['private:read']]));
    $server = new McpServer($keys, $content);
    function rpc(McpServer $server, string $key, string $method, array $params = [], array $headers = [], string $version = '2026-07-28'): array {
        if ($version === '2026-07-28') $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => $version, 'io.modelcontextprotocol/clientCapabilities' => new stdClass()];
        $http = ['REQUEST_METHOD' => 'POST', 'HTTPS' => 'on', 'HTTP_AUTHORIZATION' => 'Bearer ' . $key,
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_PROTOCOL_VERSION' => $version, 'HTTP_MCP_METHOD' => $method];
        if (isset($params['name'])) $http['HTTP_MCP_NAME'] = $params['name'];
        return $server->handle(array_replace($http, $headers), json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]));
    }
    function call(McpServer $server, string $key, string $tool, array $args = []): array {
        return rpc($server, $key, 'tools/call', ['name' => $tool, 'arguments' => (object) $args]);
    }
    $discovery = rpc($server, $alice, 'server/discover');
    check($discovery['status'] === 200 && $discovery['body']['result']->resultType === 'complete', 'Modern discovery');
    check(count(rpc($server, $alice, 'tools/list')['body']['result']->tools) === 3, 'Three read-only tools');
    check(rpc($server, $alice, 'ping')['body']['result']->resultType === 'complete', 'Modern ping');
    check(rpc($server, $alice, 'unknown')['status'] === 404, 'Modern unknown method is 404');
    check(rpc($server, $alice, 'tools/list', [], ['HTTP_MCP_METHOD' => 'ping'])['body']['error']['code'] === -32020, 'Method header mismatch');
    check(rpc($server, $alice, 'tools/list', [], ['HTTP_MCP_PROTOCOL_VERSION' => '1900-01-01'])['body']['error']['code'] === -32022, 'Unsupported version');
    check(rpc($server, $alice, 'tools/list', [], ['HTTP_ORIGIN' => 'https://evil.example'])['status'] === 403, 'Reject foreign origin');
    check(rpc($server, $alice, 'tools/list', [], ['HTTPS' => 'off'])['status'] === 403, 'Require HTTPS');
    check(rpc($server, $alice, 'tools/list', [], ['HTTPS' => 'off', 'HTTP_X_FORWARDED_PROTO' => 'https'])['status'] === 403, 'Do not trust spoofable forwarded headers');
    $_SESSION['runewiki_user'] = 'bob';
    check(rpc($server, '', 'tools/list')['status'] === 401, 'Browser session never substitutes for API key');
    check(rpc($server, $alice . '0', 'tools/list')['status'] === 401, 'Reject incorrect key');
    $list = call($server, $alice, 'list_pages');
    check(!str_contains(json_encode($list), 'PRIVATE') && !str_contains(json_encode($list), 'Hidden'), 'Listing hides protected metadata');
    check(count($list['body']['result']->structuredContent['pages']) === 2, 'Login allowed; private and system pages excluded');
    $public = call($server, $alice, 'read_page', ['id' => 'start']);
    check(!str_contains(json_encode($public), 'HIDDEN_NEEDLE') && !str_contains(json_encode($public), 'NESTED_SECRET'), 'ifAuth excluded for unscoped key');
    check(str_contains(json_encode(call($server, $bob, 'read_page', ['id' => 'start'])), 'HIDDEN_NEEDLE'), 'ifAuth allowed for scoped key');
    check(call($server, $alice, 'search_pages', ['query' => 'HIDDEN_NEEDLE'])['body']['result']->structuredContent['pages'] === [], 'No hidden-content search side channel');
    check(call($server, $alice, 'search_pages', ['query' => 'PRIVATE_NEEDLE'])['body']['result']->structuredContent['pages'] === [], 'Search respects ACL');
    $denied = call($server, $alice, 'read_page', ['id' => 'private:secret']);
    check($denied['body']['result']->isError === true, 'Private read denied');
    check(json_encode($denied) === json_encode(call($server, $alice, 'read_page', ['id' => 'missing'])), 'No distinction between missing and forbidden');
    check(call($server, $bob, 'read_page', ['id' => 'private:secret'])['body']['result']->isError === false, 'Private group grants access');
    $auth->setUserGroups('bob', []);
    check(call($server, $bob, 'read_page', ['id' => 'private:secret'])['body']['result']->isError === true, 'Group removal takes effect immediately');
    foreach (['../outside', '..:outside', '/start', 'private/secret', '_sidebar', '%2e%2e/outside'] as $id) {
        check(call($server, $bob, 'read_page', ['id' => $id])['body']['result']->isError === true, 'Reject noncanonical/system path: ' . $id);
    }
    if (@symlink($root . '/outside.md', $root . '/content/alias.md')) {
        check(call($server, $bob, 'read_page', ['id' => 'alias'])['body']['result']->isError === true, 'Reject external symlink');
    }
    check(call($server, $alice, 'read_page', ['id' => 'start', 'user' => 'bob'])['body']['error']['code'] === -32602, 'Cannot impersonate via arguments');
    check(call($server, $alice, 'list_pages', ['limit' => -1])['body']['error']['code'] === -32602, 'Validate pagination');
    check(call($server, $alice, 'list_pages', ['limit' => 1])['body']['result']->structuredContent['nextOffset'] === 1, 'Pagination');
    file_put_contents($root . '/content/hidden-title.md', "<ifAuth>\n# SECRET_HEADING\n</ifAuth>\nOrdinary text");
    check(!str_contains(json_encode(call($server, $alice, 'list_pages')), 'SECRET_HEADING'), 'Titles computed only after ifAuth filtering');
    $encoded = '=?base64?' . base64_encode('read_page') . '?=';
    check(rpc($server, $alice, 'tools/call', ['name' => 'read_page', 'arguments' => (object) ['id' => 'start']], ['HTTP_MCP_NAME' => $encoded])['status'] === 200, 'Base64 name header');
    check(rpc($server, $alice, 'tools/call', ['name' => 'read_page'], ['HTTP_MCP_NAME' => 'search_pages'])['body']['error']['code'] === -32020, 'Name header mismatch');
    $legacy = rpc($server, $alice, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => new stdClass(), 'clientInfo' => (object) ['name' => 'test', 'version' => '1']], ['HTTP_MCP_PROTOCOL_VERSION' => ''], '2025-11-25');
    check($legacy['body']['result']->protocolVersion === '2025-11-25', 'Legacy handshake');
    check(count(rpc($server, $alice, 'tools/list', [], [], '2025-11-25')['body']['result']->tools) === 3, 'Legacy tools');
    $headers = ['HTTPS' => 'on', 'REQUEST_METHOD' => 'POST', 'HTTP_AUTHORIZATION' => 'Bearer ' . $alice,
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25'];
    check($server->handle($headers, '{')['body']['error']['code'] === -32700, 'Parse error');
    $tokenHeaders = $headers;
    unset($tokenHeaders['HTTP_AUTHORIZATION']);
    $tokenHeaders['HTTP_API_TOKEN'] = $alice;
    $notification = '{"jsonrpc":"2.0","method":"notifications/initialized"}';
    check($server->handle($tokenHeaders, $notification)['status'] === 202, 'API_TOKEN authenticates personal key');
    check($server->handle(array_merge($tokenHeaders, ['HTTP_API_TOKEN' => 'invalid']), $notification)['status'] === 401, 'Invalid API_TOKEN rejected');
    check($server->handle(array_merge($tokenHeaders, ['HTTP_AUTHORIZATION' => 'Bearer invalid']), $notification)['status'] === 401, 'Invalid Authorization cannot fall back to API_TOKEN');
    check($server->handle($headers, '[]')['body']['error']['code'] === -32600, 'No batches');
    check($server->handle($headers, str_repeat('x', 65537))['status'] === 413, 'Bound request size');
    check($server->handle($headers, '{"jsonrpc":"2.0","method":"notifications/initialized"}')['status'] === 202, 'Legacy notification has empty 202');
    check(!str_contains(MarkdownVisibility::filter("Before\n<ifAuth>\nSecret to EOF", false), 'Secret'), 'Unclosed ifAuth stays hidden');
    check(str_contains(MarkdownVisibility::filter("```md\n<ifAuth>\nExample\n</ifAuth>\n```", false), 'Example'), 'Fenced examples preserved');
    check(!str_contains(MarkdownVisibility::filter("<ifAuth>\n```text\nHidden code\n```\n</ifAuth>", false), 'Hidden code'), 'Code inside ifAuth stays hidden');
    $next = $keys->issue('alice', true);
    check($keys->authenticate($alice) === null && $keys->authenticate($next) !== null, 'Rotation invalidates old key');
    $keys->revoke('alice');
    check($keys->authenticate($next) === null, 'Revocation immediate');
    $expired = $keys->issue('alice');
    $keyPath = $root . '/users/mcp/' . substr(hash('sha256', 'alice'), 0, 32) . '.php';
    $stored = file_get_contents($keyPath);
    $stored = preg_replace('/"expires":\d+/', '"expires":1', $stored);
    file_put_contents($keyPath, $stored);
    check($keys->authenticate($expired) === null, 'Expired key denied');
    $next = $keys->issue('alice');
    $auth->setPassword('alice', 'changed-password');
    check($keys->authenticate($next) === null, 'Password change invalidates key');
    $next = $keys->issue('alice');
    $auth->deleteUser('alice');
    check($keys->authenticate($next) === null, 'Deletion invalidates key');
    $auth->setPassword('alice', 'changed-password');
    check($keys->authenticate($next) === null, 'Recreation does not restore old key');
    echo "PASS: MCP protocol, ACL, ifAuth, paths, key lifecycle and HTTP validation\n";
} finally {
    // Delete only files inside the unique test fixture directory.
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname()); else unlink($file->getPathname());
    }
    rmdir($root);
}
