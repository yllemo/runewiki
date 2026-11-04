<?php
/** Import the content/ tree produced by the admin ZIP export. */
class ContentImport
{
    public static function limits(string $root): array
    {
        $defaults = ['max_zip_mb' => 256, 'max_unpacked_mb' => 512, 'max_file_mb' => 32, 'max_entries' => 50000, 'batch_files' => 25];
        $config = is_file($root . '/config/import.php') ? require $root . '/config/import.php' : [];
        foreach ($defaults as $key => $value) $defaults[$key] = max(1, min(1000000, (int) ($config[$key] ?? $value)));
        return $defaults;
    }

    public static function run(string $archive, string $contentDir, int $offset = 0, int $batchSize = PHP_INT_MAX, ?array $limits = null): array
    {
        $limits ??= self::limits(dirname(__DIR__));
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP-tillägget zip saknas på servern.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Kunde inte läsa ZIP-filen.');
        $files = []; $seen = []; $total = 0;
        try {
            if ($zip->numFiles > $limits['max_entries']) throw new RuntimeException('Arkivet innehåller för många poster (max ' . $limits['max_entries'] . ').');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'];
                if ($name === 'content/') continue;
                if (!str_starts_with($name, 'content/')) throw new RuntimeException('Arkivet måste vara en wikiexport med mappen content/.');
                $relative = substr($name, 8);
                $directory = str_ends_with($relative, '/');
                $relative = rtrim($relative, '/');
                foreach (explode('/', $relative) as $part) {
                    if ($part === '' || $part === '.' || $part === '..' || preg_match('/[\\\\:\x00-\x1f\x7f<>"|?*]/', $part)
                        || preg_match('/[. ]$/', $part) || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i', $part)) {
                        throw new RuntimeException('Arkivet innehåller en ogiltig sökväg.');
                    }
                }
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                $type = ($attributes >> 16) & 0170000;
                if ($type && !in_array($type, [0100000, 0040000], true)) throw new RuntimeException('Länkar och specialfiler får inte importeras.');
                $key = strtolower($relative);
                if (isset($seen[$key])) throw new RuntimeException('Arkivet innehåller dubbla sökvägar.');
                $seen[$key] = true;
                $target = rtrim($contentDir, '/\\');
                if (is_link($target)) throw new RuntimeException('Innehållsmappen får inte vara en symbolisk länk.');
                foreach (explode('/', $relative) as $part) {
                    $target .= '/' . $part;
                    if (is_link($target)) throw new RuntimeException('Målsökvägen innehåller en symbolisk länk.');
                }
                if ($directory) continue;
                $total += $stat['size'];
                if ($stat['size'] > $limits['max_file_mb'] * 1024 * 1024 || $total > $limits['max_unpacked_mb'] * 1024 * 1024) throw new RuntimeException('Arkivet överskrider gränsen: ' . $limits['max_file_mb'] . ' MB per fil eller ' . $limits['max_unpacked_mb'] . ' MB uppackat. Ändra config/import.php vid behov.');
                $files[$target] = ['index' => $i, 'size' => $stat['size']];
            }
        } finally { $zip->close(); }
        if (!$files) throw new RuntimeException('Arkivet innehåller inga filer i content/.');
        // Validate the complete archive before writing. Existing files are never overwritten.
        $result = ['imported' => 0, 'skipped' => 0, 'next' => $offset, 'total' => count($files), 'done' => false];
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Kunde inte öppna arkivet för import.');
        $started = microtime(true);
        try {
        foreach (array_slice($files, $offset, $batchSize, true) as $target => $entry) {
            if ($batchSize !== PHP_INT_MAX && $result['next'] > $offset && microtime(true) - $started > 3) break;
            $result['next']++;
            if (file_exists($target)) { $result['skipped']++; continue; }
            $data = $zip->getFromIndex($entry['index'], $limits['max_file_mb'] * 1024 * 1024 + 1);
            if ($data === false || strlen($data) !== $entry['size']) throw new RuntimeException('En fil kunde inte läsas. Importen kan vara delvis genomförd.');
            $dir = dirname($target);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Kunde inte skapa en innehållsmapp. Importen kan vara delvis genomförd.');
            $handle = @fopen($target, 'xb');
            if (!$handle) throw new RuntimeException('Kunde inte skapa en fil. Importen kan vara delvis genomförd.');
            $written = fwrite($handle, $data);
            fclose($handle);
            if ($written !== strlen($data)) {
                unlink($target);
                throw new RuntimeException('Kunde inte skriva hela filen. Importen kan vara delvis genomförd.');
            }
            $result['imported']++;
        }
        } finally { $zip->close(); }
        $result['done'] = $result['next'] >= count($files);
        return $result;
    }
}
