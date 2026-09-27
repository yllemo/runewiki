<?php
/** MCP Streamable HTTP endpoint. Browser sessions never authorize API requests. */
declare(strict_types=1);
ini_set('display_errors', '0');
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $class) && is_file($root . '/core/' . $class . '.php')) {
        require_once $root . '/core/' . $class . '.php';
    }
});
$started = microtime(true);
$raw = '';
$mcpConfig = Helpers::loadConfig($root . '/config/mcp.php');
try {
    $auth = new Auth($root . '/data/users/users.php', true, 30, false);
    $keys = new McpKeys($root . '/data/users/mcp', $auth);
    $content = new McpContent($root . '/content', $auth,
        new NamespaceResolver(Helpers::loadConfig($root . '/config/namespaces.php')),
        new Acl(Helpers::loadConfig($root . '/config/acl.php')['groups'] ?? []));
    $server = new McpServer($keys, $content, $mcpConfig);
    $input = fopen('php://input', 'rb');
    $raw = $input ? stream_get_contents($input, McpServer::MAX_REQUEST_BYTES + 1) : '';
    if ($input) fclose($input);
    $response = $server->handle($_SERVER, $raw === false ? '' : $raw);
} catch (Throwable $e) {
    $response = ['status' => 500, 'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'],
        'body' => ['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal server error']]];
}
if (!empty($mcpConfig['log_requests'])) {
    try { McpLog::write(__DIR__ . '/log.txt', $_SERVER, is_string($raw) ? $raw : '', $response, $started); }
    catch (Throwable $e) { error_log('RuneWiki MCP: diagnostic logging failed.'); }
}
http_response_code($response['status']);
foreach ($response['headers'] as $name => $value) header($name . ': ' . $value);
if ($response['body'] !== null) echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
