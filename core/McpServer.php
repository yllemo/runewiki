<?php
/** Streamable HTTP, JSON response mode; MCP 2026-07-28 and 2025-11-25. */
class McpServer
{
    public const VERSIONS = ['2026-07-28', '2025-11-25'];
    public const MAX_REQUEST_BYTES = 65536;
    private const SERVER = ['name' => 'runewiki', 'version' => '1.0.0'];

    public function __construct(private McpKeys $keys, private McpContent $content, private array $config = []) {}

    /** Pure HTTP adapter: returns status, headers and a JSON-compatible body. */
    public function handle(array $http, string $raw): array
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff', 'Vary' => 'Origin, Authorization, API_TOKEN, API-Token'];
        $id = null;
        $error = static function (int $status, int $code, string $message, ?array $data = null) use (&$id, &$headers) {
            $body = ['jsonrpc' => '2.0', 'error' => ['code' => $code, 'message' => $message]];
            if ($id !== null) $body['id'] = $id;
            if ($data !== null) $body['error']['data'] = $data;
            return ['status' => $status, 'headers' => $headers, 'body' => $body];
        };
        try {
            $origin = $http['HTTP_ORIGIN'] ?? null;
            if ($origin !== null) {
                if (!is_string($origin) || !in_array($origin, $this->config['allowed_origins'] ?? [], true)) {
                    return $error(403, -32001, 'Origin not allowed');
                }
                $headers['Access-Control-Allow-Origin'] = $origin;
                $headers['Access-Control-Allow-Methods'] = 'POST, OPTIONS';
                $headers['Access-Control-Allow-Headers'] = 'Authorization, API_TOKEN, API-Token, Content-Type, Accept, MCP-Protocol-Version, Mcp-Method, Mcp-Name';
            }
            $secure = ($http['HTTPS'] ?? '') === 'on' || ($http['HTTPS'] ?? '') === '1' || (string) ($http['SERVER_PORT'] ?? '') === '443';
            $local = ($this->config['allow_local_http'] ?? false) && in_array($http['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
                && in_array($http['HTTP_HOST'] ?? '', $this->config['local_hosts'] ?? [], true);
            if (!$secure && !$local) return $error(403, -32001, 'HTTPS required');
            $method = $http['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'OPTIONS' && $origin !== null) return ['status' => 204, 'headers' => $headers, 'body' => null];
            if ($method !== 'POST') {
                $headers['Allow'] = 'POST, OPTIONS';
                return $error(405, -32600, 'Use POST for MCP requests');
            }
            $authorization = $http['HTTP_AUTHORIZATION'] ?? $http['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            // PHP normalizes both API_TOKEN and API-Token to HTTP_API_TOKEN.
            // Never fall back from an explicitly supplied invalid Authorization.
            $hasAuthorization = isset($http['HTTP_AUTHORIZATION']) || isset($http['REDIRECT_HTTP_AUTHORIZATION']);
            $token = $hasAuthorization
                ? (is_string($authorization) && preg_match('/^Bearer ([^\s]+)$/iD', $authorization, $match) ? $match[1] : null)
                : ($http['HTTP_API_TOKEN'] ?? null);
            $identity = is_string($token) ? $this->keys->authenticate($token) : null;
            if ($identity === null) {
                $headers['WWW-Authenticate'] = 'Bearer realm="RuneWiki MCP"';
                return $error(401, -32001, 'Valid personal MCP key required');
            }
            if (strlen($raw) > self::MAX_REQUEST_BYTES) return $error(413, -32600, 'Request too large');
            if (strtolower(trim(explode(';', $http['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
                return $error(415, -32600, 'Content-Type must be application/json');
            }
            $accept = strtolower($http['HTTP_ACCEPT'] ?? '');
            if (!str_contains($accept, 'application/json') || !str_contains($accept, 'text/event-stream')) {
                return $error(406, -32600, 'Accept must include application/json and text/event-stream');
            }
            try { $request = json_decode($raw, false, 64, JSON_THROW_ON_ERROR); }
            catch (JsonException $e) { return $error(400, -32700, 'Parse error'); }
            if (!$request instanceof stdClass || ($request->jsonrpc ?? null) !== '2.0' || !is_string($request->method ?? null)
                || (property_exists($request, 'id') && !is_string($request->id) && !is_int($request->id))
                || (property_exists($request, 'params') && !$request->params instanceof stdClass)) {
                return $error(400, -32600, 'Invalid request');
            }
            $id = $request->id ?? null;
            $rpc = $request->method;
            $params = $request->params ?? new stdClass();
            $version = $http['HTTP_MCP_PROTOCOL_VERSION'] ?? '';
            $meta = $params->_meta ?? new stdClass();
            if (!$meta instanceof stdClass) return $error(400, -32602, 'Invalid metadata');
            // Legacy initialization may omit the version header. Never downgrade modern metadata.
            if ($version === '' && $rpc === 'initialize') $version = '2025-11-25';
            if ($version === '') return $error(400, -32020, 'MCP-Protocol-Version required');
            if (!in_array($version, self::VERSIONS, true)) {
                return $error(400, -32022, 'Unsupported protocol version', ['supported' => self::VERSIONS, 'requested' => $version]);
            }
            $modern = $version === self::VERSIONS[0];
            if ($modern || property_exists($meta, 'io.modelcontextprotocol/protocolVersion')) {
                if (($meta->{'io.modelcontextprotocol/protocolVersion'} ?? null) !== $version
                    || ($http['HTTP_MCP_METHOD'] ?? '') !== $rpc) return $error(400, -32020, 'Header mismatch');
                if (!$modern) return $error(400, -32602, 'Modern metadata requires protocol 2026-07-28');
            }
            if ($modern && !(($meta->{'io.modelcontextprotocol/clientCapabilities'} ?? null) instanceof stdClass)) {
                return $error(400, -32602, 'clientCapabilities object required');
            }
            if ($modern && in_array($rpc, ['tools/call', 'resources/read', 'prompts/get'], true)) {
                $name = $rpc === 'resources/read' ? ($params->uri ?? null) : ($params->name ?? null);
                $headerName = $this->decodeName($http['HTTP_MCP_NAME'] ?? '');
                if (!is_string($name) || $headerName !== $name) return $error(400, -32020, 'Mcp-Name header mismatch');
            }
            if ($id === null) {
                if (!$modern && in_array($rpc, ['notifications/initialized', 'notifications/cancelled'], true)) {
                    return ['status' => 202, 'headers' => $headers, 'body' => null];
                }
                return ['status' => 400, 'headers' => $headers, 'body' => null];
            }
            $result = match ($rpc) {
                'server/discover' => $modern ? ['supportedVersions' => self::VERSIONS, 'capabilities' => ['tools' => new stdClass()],
                    'instructions' => 'Read-only wiki. Use list_pages, search_pages and read_page. Access is limited to the current key owner.'] : null,
                'initialize' => !$modern ? $this->initialize($params) : null,
                'ping' => [],
                'tools/list' => $this->listTools($params),
                'tools/call' => $this->callTool($params, $identity),
                default => null,
            };
            if ($result === null) return $error($modern ? 404 : 200, -32601, 'Method not found');
            if ($modern) {
                $result['resultType'] = 'complete';
                $result['_meta'] = ['io.modelcontextprotocol/serverInfo' => self::SERVER];
            }
            return ['status' => 200, 'headers' => $headers, 'body' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) $result]];
        } catch (InvalidArgumentException $e) {
            return $error(400, -32602, $e->getMessage());
        } catch (Throwable $e) {
            // Do not send filesystem paths, account details or credentials to clients.
            return $error(500, -32603, 'Internal server error');
        }
    }

    private function decodeName(string $value): ?string
    {
        if (preg_match('/^=\?base64\?([A-Za-z0-9+\/=]*)\?=$/D', $value, $match)) {
            $decoded = base64_decode($match[1], true);
            return $decoded === false ? null : $decoded;
        }
        return $value !== '' && trim($value) === $value && !preg_match('/[^\x20-\x7e\t]/', $value) ? $value : null;
    }

    private function initialize(stdClass $params): array
    {
        if (!is_string($params->protocolVersion ?? null) || !(($params->capabilities ?? null) instanceof stdClass)
            || !(($params->clientInfo ?? null) instanceof stdClass)) throw new InvalidArgumentException('Invalid initialization parameters');
        return ['protocolVersion' => '2025-11-25', 'serverInfo' => self::SERVER, 'capabilities' => ['tools' => new stdClass()]];
    }

    private function listTools(stdClass $params): array
    {
        if (isset($params->cursor)) throw new InvalidArgumentException('Invalid cursor');
        $paging = ['namespace' => ['type' => 'string', 'description' => 'Exact namespace; empty string means root.'],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100000],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]];
        $definitions = [
            ['list_pages', 'List readable wiki pages. Pass nextOffset as offset to continue.', $paging, []],
            ['search_pages', 'Search readable Markdown; ifAuth content is included only when allowed by the key.',
                ['query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200]] + $paging, ['query']],
            ['read_page', 'Read one wiki page as Markdown. Use a canonical id from list_pages or search_pages.',
                ['id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 256]], ['id']],
        ];
        $tools = [];
        foreach ($definitions as [$name, $description, $properties, $required]) {
            $tools[] = ['name' => $name, 'description' => $description,
                'inputSchema' => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false]];
        }
        return ['tools' => $tools];
    }

    private function callTool(stdClass $params, array $identity): array
    {
        $name = $params->name ?? null;
        $args = $params->arguments ?? new stdClass();
        if (!is_string($name) || !$args instanceof stdClass) throw new InvalidArgumentException('Invalid tool call');
        $definition = null;
        foreach ($this->listTools(new stdClass())['tools'] as $tool) if ($tool['name'] === $name) $definition = $tool['inputSchema'];
        if (!$definition) throw new InvalidArgumentException('Unknown tool');
        foreach ($definition['required'] as $required) if (!property_exists($args, $required)) throw new InvalidArgumentException('Missing required argument');
        foreach (get_object_vars($args) as $key => $value) {
            $rule = $definition['properties'][$key] ?? null;
            if (!$rule) throw new InvalidArgumentException('Unknown argument');
            if ($rule['type'] === 'string' && (!is_string($value) || mb_strlen($value) < ($rule['minLength'] ?? 0) || mb_strlen($value) > ($rule['maxLength'] ?? 256))) {
                throw new InvalidArgumentException('Invalid string argument');
            }
            if ($rule['type'] === 'integer' && (!is_int($value) || $value < $rule['minimum'] || $value > $rule['maximum'])) {
                throw new InvalidArgumentException('Invalid pagination argument');
            }
        }
        if ($name === 'read_page') {
            $data = $this->content->read($args->id, $identity);
            if ($data === null) return ['content' => [['type' => 'text', 'text' => 'Sidan är inte tillgänglig.']], 'isError' => true];
        } else {
            $data = $this->content->listing($identity, $args->query ?? '', $args->namespace ?? null, $args->offset ?? 0, $args->limit ?? 50);
        }
        return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]],
            'structuredContent' => $data, 'isError' => false];
    }
}
