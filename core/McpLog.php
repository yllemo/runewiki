<?php
/** Diagnostic request metadata only: never credentials, arguments or page content. */
class McpLog
{
    public static function write(string $path, array $http, string $raw, array $response, float $started): void
    {
        $request = json_decode($raw, true, 64);
        $name = static function ($value): ?string {
            return is_string($value) && strlen($value) <= 100
                && preg_match('/^[a-zA-Z][a-zA-Z0-9_.\/-]*$/D', $value)
                && !str_contains($value, 'rw_mcp_') ? $value : null;
        };
        $entry = [
            'time' => gmdate('Y-m-d\TH:i:s\Z'),
            'http_method' => $name($http['REQUEST_METHOD'] ?? null),
            'method' => $name($request['method'] ?? null),
            'tool' => $name($request['params']['name'] ?? null),
            'bytes' => strlen($raw),
            'authorization_present' => isset($http['HTTP_AUTHORIZATION']) || isset($http['REDIRECT_HTTP_AUTHORIZATION']),
            'api_token_present' => isset($http['HTTP_API_TOKEN']),
            'status' => $response['status'],
            'error_code' => $response['body']['error']['code'] ?? null,
            'tool_error' => $response['body']['result']['isError'] ?? false,
            'duration_ms' => round((microtime(true) - $started) * 1000),
        ];
        // Logging failure must never alter the MCP response.
        if (@file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('RuneWiki MCP: could not append diagnostic log.');
        }
    }
}
