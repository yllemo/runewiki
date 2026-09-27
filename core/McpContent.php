<?php
/** Read-only wiki access. Apply ACL and ifAuth before metadata, search or output. */
class McpContent
{
    public const MAX_PAGE_BYTES = 1048576;

    public function __construct(private string $contentDir, private Auth $auth,
        private NamespaceResolver $namespaces, private Acl $acl) {}

    private function canRead(PageId $id, array $identity): bool
    {
        if (!$this->auth->userExists($identity['user'])) return false;
        // MCP always enforces namespace rules, even when browser auth is disabled.
        $mode = $this->namespaces->settingsFor($id->namespace())['acl'] ?? 'public';
        return match ($mode) {
            'public', 'login' => true,
            'private' => $this->acl->can($this->auth->groupsFor($identity['user']), $id->namespace(), 'read'),
            default => false,
        };
    }

    public function read(string $rawId, array $identity): ?array
    {
        if ($rawId === '' || strlen($rawId) > 256) return null;
        $id = new PageId($rawId);
        if ($id->id() !== $rawId || !$this->canRead($id, $identity)
            || str_starts_with(implode('.', $id->nameParts()), '_')) return null;
        $path = $id->toFilePath($this->contentDir);
        $root = realpath($this->contentDir);
        $real = realpath($path);
        // No aliases into another namespace, symlinked pages, or external files.
        if (!$root || !$real || !is_file($real) || is_link($path) || is_link(dirname($path))
            || str_replace('\\', '/', $real) !== str_replace('\\', '/', $root . substr($path, strlen(rtrim($this->contentDir, '/'))))) return null;
        $stream = fopen($real, 'rb');
        if (!$stream) return null;
        try { $raw = stream_get_contents($stream, self::MAX_PAGE_BYTES + 1); }
        finally { fclose($stream); }
        if ($raw === false || strlen($raw) > self::MAX_PAGE_BYTES) return null;
        $raw = MarkdownVisibility::filter($raw, ($identity['ifAuth'] ?? false) === true);
        [$meta, $body] = FrontMatter::parse($raw);
        return ['id' => $id->id(), 'title' => PageLoader::displayTitle($id, $meta, $body), 'markdown' => $raw];
    }

    public function listing(array $identity, string $query = '', ?string $namespace = null, int $offset = 0, int $limit = 50): array
    {
        $items = [];
        $matches = 0;
        $hasMore = false;
        foreach ((new PageLoader($this->contentDir))->listAll() as $id) {
            if ($namespace !== null && (new PageId($id))->namespace() !== $namespace) continue;
            $page = $this->read($id, $identity);
            if (!$page) continue;
            if ($query !== '' && mb_stripos($page['title'] . "\n" . $page['markdown'], $query, 0, 'UTF-8') === false) continue;
            if ($matches++ < $offset) continue;
            if (count($items) === $limit) { $hasMore = true; break; }
            $item = ['id' => $page['id'], 'title' => $page['title']];
            if ($query !== '') {
                $position = mb_stripos($page['markdown'], $query, 0, 'UTF-8');
                $item['excerpt'] = mb_substr($page['markdown'], max(0, ($position === false ? 0 : $position) - 80), 240, 'UTF-8');
            }
            $items[] = $item;
        }
        return ['pages' => $items, 'nextOffset' => $hasMore ? $offset + $limit : null];
    }
}
