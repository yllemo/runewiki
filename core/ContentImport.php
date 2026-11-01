<?php
/** Import the content/ tree produced by the admin ZIP export. */
class ContentImport
{
    public static function run(string $archive, string $contentDir): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('PHP-tillägget zip saknas på servern.');
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) throw new RuntimeException('Kunde inte läsa ZIP-filen.');
        $files = []; $seen = []; $total = 0;
        try {
            if ($zip->numFiles > 10000) throw new RuntimeException('Arkivet innehåller för många poster (max 10 000).');
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
                if ($stat['size'] > 16 * 1024 * 1024 || $total > 64 * 1024 * 1024) throw new RuntimeException('Arkivet är för stort (max 16 MB per fil och 64 MB uppackat).');
                $data = $zip->getFromIndex($i, 16 * 1024 * 1024 + 1);
                if ($data === false || strlen($data) !== $stat['size']) throw new RuntimeException('En fil i arkivet kunde inte läsas.');
                $files[$target] = $data;
            }
        } finally { $zip->close(); }
        if (!$files) throw new RuntimeException('Arkivet innehåller inga filer i content/.');
        // Validate the complete archive before writing. Existing files are never overwritten.
        $result = ['imported' => 0, 'skipped' => 0];
        foreach ($files as $target => $data) {
            if (file_exists($target)) { $result['skipped']++; continue; }
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
        return $result;
    }
}
