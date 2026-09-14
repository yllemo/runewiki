<?php
/**
 * core/History.php
 *
 * Valfri versionering av sidor (config('history_enabled')). Sparar en
 * ögonblicksbild av den gamla filen till data/history/<sha256>/<timestamp>-<unik kod>.md
 * innan en sida skrivs över.
 */

class History
{
    public function __construct(private string $historyDir, private bool $enabled)
    {
    }

    public function snapshot(PageId $id, string $oldRawContent, bool $force = false): void
    {
        if ((!$this->enabled && !$force) || trim($oldRawContent) === '') {
            return;
        }
        $dir = $this->directory($id);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Kunde inte skapa historikmappen.');
        }
        $path = $dir . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.md';
        if (file_put_contents($path, $oldRawContent, LOCK_EX) !== strlen($oldRawContent)) {
            @unlink($path);
            throw new RuntimeException('Kunde inte spara historiken.');
        }
    }

    /** @return string[] Sorterade filnamn (nyast sist) */
    public function revisions(PageId $id): array
    {
        $dir = $this->directory($id);
        if (!is_dir($dir)) {
            return [];
        }
        $files = array_values(array_filter(scandir($dir), fn ($file) => $this->validRevision($file) && is_file($dir . '/' . $file) && !is_link($dir . '/' . $file)));
        sort($files);
        return $files;
    }

    public function isEnabled(): bool { return $this->enabled; }

    public function move(PageId $from, PageId $to): void
    {
        $source = $this->directory($from);
        if (!is_dir($source)) return;
        $target = $this->directory($to);
        if (file_exists($target) || !rename($source, $target)) throw new RuntimeException('Kunde inte flytta versionshistoriken.');
    }

    private function directory(PageId $id): string
    {
        // Keep each page separate, including IDs such as a:b and a_b.
        return rtrim($this->historyDir, '/') . '/' . hash('sha256', $id->id());
    }

    private function validRevision(string $revision): bool
    {
        return (bool) preg_match('/^\d{8}-\d{6}-[a-f0-9]{16}\.md$/D', $revision);
    }

    public function read(PageId $id, string $revision): ?string
    {
        if (!$this->validRevision($revision)) return null;
        $path = $this->directory($id) . '/' . $revision;
        if (!is_file($path) || is_link($path)) return null;
        $raw = file_get_contents($path);
        return $raw === false ? null : $raw;
    }
}
