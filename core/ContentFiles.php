<?php
/** Raw Markdown file operations confined to content, without following links. */
class ContentFiles
{
    public function __construct(private string $root, private Closure $allowed, private History $history) {}
    private function path(string $name): string
    {
        if ($name === '' || strlen($name) > 400 || str_contains($name, '\\') || preg_match('/[\x00-\x1f:*?"<>|]/', $name) || !str_ends_with($name, '.md')) throw new RuntimeException('Ange en relativ sökväg till en .md-fil.');
        $parts = explode('/', $name);
        foreach ($parts as $part) if ($part === '' || $part === '.' || $part === '..' || preg_match('/[. ]$/', $part) || preg_match('/^(con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/i', $part)) throw new RuntimeException('Ogiltigt fil- eller mappnamn.');
        $ns = count($parts) > 1 ? $parts[0] : '';
        if (!(($this->allowed)($ns))) throw new RuntimeException('Du saknar behörighet till mappen.');
        $path = $this->root . '/content';
        if (is_link($path)) throw new RuntimeException('Länkad innehållsmapp stöds inte.');
        foreach ($parts as $part) { $path .= '/' . $part; if (is_link($path)) throw new RuntimeException('Länkade filer eller mappar stöds inte.'); }
        return $path;
    }
    public function listing(): array
    {
        $base = $this->root . '/content'; $files = [];
        if (!is_dir($base) || is_link($base)) return [];
        $walk = function ($dir, $prefix) use (&$walk, &$files) {
            foreach (new DirectoryIterator($dir) as $entry) {
                if ($entry->isDot() || $entry->isLink()) continue;
                $name = $prefix . $entry->getFilename();
                if ($entry->isDir()) {
                    if ($prefix === '' && !(($this->allowed)($entry->getFilename()))) continue;
                    $walk($entry->getPathname(), $name . '/');
                } elseif ($entry->isFile() && str_ends_with($name, '.md')) {
                    try { $this->path($name); $files[] = $name; } catch (RuntimeException $e) {}
                }
            }
        };
        $walk($base, ''); sort($files, SORT_NATURAL | SORT_FLAG_CASE); return $files;
    }
    public function folders(): array
    {
        $base = $this->root . '/content'; $result = [];
        if (!is_dir($base) || is_link($base)) return [];
        $walk = function ($dir, $prefix) use (&$walk, &$result) {
            foreach (new DirectoryIterator($dir) as $entry) {
                if ($entry->isDot() || $entry->isLink() || !$entry->isDir()) continue;
                $name = $prefix . $entry->getFilename();
                try { $this->path($name . '/placeholder.md'); } catch (RuntimeException $e) { continue; }
                $result[] = $name; $walk($entry->getPathname(), $name . '/');
            }
        };
        $walk($base, ''); sort($result, SORT_NATURAL | SORT_FLAG_CASE); return $result;
    }
    public function mkdir(string $name): array
    {
        $path = dirname($this->path($name . '/placeholder.md'));
        if (file_exists($path)) throw new RuntimeException('Mappen finns redan.');
        if (!mkdir($path, 0775, true)) throw new RuntimeException('Kunde inte skapa mappen.');
        return ['folder' => $name];
    }
    public function read(string $name): array
    {
        $path = $this->path($name);
        if (!is_file($path) || filesize($path) > 524288) throw new RuntimeException('Filen saknas eller är större än 512 KB.');
        $raw = file_get_contents($path);
        if ($raw === false) throw new RuntimeException('Filen kunde inte läsas.');
        $this->validate($raw);
        return ['path' => $name, 'raw' => $raw, 'revision' => hash('sha256', $raw)];
    }
    private function validate(string $raw): void
    {
        if (strlen($raw) > 524288 || str_contains($raw, "\0") || !mb_check_encoding($raw, 'UTF-8')) throw new RuntimeException('Endast UTF-8-text stöds, högst 512 KB per fil.');
    }
    public function write(string $action, string $name, string $raw, string $revision, string $destination = ''): array
    {
        $lockDir = $this->root . '/data';
        if (!is_dir($lockDir)) mkdir($lockDir, 0775, true);
        $lock = fopen($lockDir . '/content-files.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Kunde inte låsa filhanteraren.');
        try {
            $path = $this->path($name);
            if (!in_array($action, ['save', 'move', 'upload'], true)) throw new RuntimeException('Okänd filåtgärd.');
            if ($action !== 'upload') {
                $old = $this->read($name);
                if (!hash_equals($old['revision'], $revision)) throw new RuntimeException('Filen har ändrats. Kopiera dina ändringar och öppna filen igen.');
            }
            $targetName = $action === 'move' ? $destination : $name;
            $target = $this->path($targetName);
            if ($action !== 'save' && file_exists($target)) throw new RuntimeException('Målfilen finns redan. Välj ett annat namn.');
            if ($action === 'move') $raw = $old['raw'];
            $this->validate($raw);
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) throw new RuntimeException('Kunde inte skapa målmappen.');
            $this->path($targetName);
            if ($action !== 'upload') $this->history->snapshot(new PageId(substr(str_replace('/', ':', $name), 0, -3)), $old['raw'], true);
            if ($action === 'move') {
                if (!rename($path, $target)) throw new RuntimeException('Kunde inte flytta filen.');
            } else {
                $temp = tempnam(dirname($target), '.content-');
                try {
                    if (!$temp || file_put_contents($temp, $raw) !== strlen($raw) || !rename($temp, $target)) throw new RuntimeException('Kunde inte spara filen.');
                } finally { if ($temp && is_file($temp)) unlink($temp); }
            }
            (new Cache($this->root . '/data/cache'))->clear();
            return $this->read($targetName);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
