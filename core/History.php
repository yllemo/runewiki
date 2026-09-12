<?php
/**
 * core/History.php
 *
 * Valfri versionering av sidor (config('history_enabled')). Sparar en
 * ögonblicksbild av den gamla filen till data/history/<id>/<timestamp>.md
 * innan en sida skrivs över.
 */

class History
{
    public function __construct(private string $historyDir, private bool $enabled)
    {
    }

    public function snapshot(PageId $id, string $oldRawContent): void
    {
        if (!$this->enabled || trim($oldRawContent) === '') {
            return;
        }
        $dir = rtrim($this->historyDir, '/') . '/' . str_replace(':', '_', $id->id());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . date('Ymd-His') . '.md', $oldRawContent);
    }

    /** @return string[] Sorterade filnamn (nyast sist) */
    public function revisions(PageId $id): array
    {
        $dir = rtrim($this->historyDir, '/') . '/' . str_replace(':', '_', $id->id());
        if (!is_dir($dir)) {
            return [];
        }
        $files = array_values(array_diff(scandir($dir), ['.', '..']));
        sort($files);
        return $files;
    }
}
