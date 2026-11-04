<?php
/** Session-owned chunk upload and incremental import; called behind admin + CSRF. */
class ImportUpload
{
    public static function chunkBytes(): int
    {
        $size = 256 * 1024;
        foreach (['upload_max_filesize', 'post_max_size'] as $name) {
            $raw = trim(ini_get($name));
            $bytes = (float) $raw * (match (strtolower(substr($raw, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 });
            if ($bytes > 0) $size = min($size, (int) ($bytes / 2));
        }
        if ($size < 1024) throw new RuntimeException('PHP:s uppladdningsgränser är för låga. Höj upload_max_filesize och post_max_size.');
        return $size;
    }

    public static function handle(string $root, string $user, string $action, array $post, array $uploads): array
    {
        $limits = ContentImport::limits($root);
        if ($action === 'import_begin') {
            if (!class_exists('ZipArchive')) throw new RuntimeException('PHP-tillägget zip saknas på servern.');
            $size = filter_var($post['size'] ?? null, FILTER_VALIDATE_INT);
            if (!$size || $size < 1 || $size > $limits['max_zip_mb'] * 1024 * 1024) throw new RuntimeException('ZIP-filen får vara högst ' . $limits['max_zip_mb'] . ' MB. Gränsen ändras i config/import.php.');
            $chunkSize = self::chunkBytes();
            $dir = $root . '/data/imports';
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Kunde inte skapa data/imports/.');
            foreach (glob($dir . '/*.zip') ?: [] as $old) if (!is_link($old) && filemtime($old) < time() - 86400) @unlink($old);
            $old = $_SESSION['wiki_import'] ?? null;
            if (is_array($old) && isset($old['path']) && is_file($old['path'])) @unlink($old['path']);
            $id = bin2hex(random_bytes(24)); $path = $dir . '/' . $id . '.zip';
            $f = fopen($path, 'xb');
            if (!$f) throw new RuntimeException('Kunde inte skapa temporär ZIP-fil.');
            fclose($f);
            $_SESSION['wiki_import'] = ['id' => $id, 'path' => $path, 'user' => $user, 'size' => $size, 'received' => 0, 'next' => 0, 'total' => 0, 'imported' => 0, 'skipped' => 0, 'done' => false, 'expires' => time() + 86400, 'chunk_size' => $chunkSize];
        } else {
            $job = &$_SESSION['wiki_import'];
            if (!is_array($job) || ($post['id'] ?? '') !== $job['id'] || $job['user'] !== $user || $job['expires'] < time()) throw new RuntimeException('Importsessionen saknas eller har gått ut. Välj filen och starta igen.');
            if ($action === 'import_cancel') {
                if (is_file($job['path'])) unlink($job['path']);
                unset($_SESSION['wiki_import']);
                return ['cancelled' => true];
            }
            if ($action === 'import_chunk') {
                if (!$job['done'] && (int) ($post['offset'] ?? -1) === $job['received']) {
                    $file = $uploads['chunk'] ?? [];
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) throw new RuntimeException('Uppladdningsdelen kunde inte tas emot. Kontrollera PHP- och proxygränser.');
                    $length = filesize($file['tmp_name']);
                    if ($length < 1 || $length > $job['chunk_size'] || $job['received'] + $length > $job['size']) throw new RuntimeException('Fel storlek på uppladdningsdelen.');
                    $f = fopen($job['path'], 'c+b');
                    if (!$f) throw new RuntimeException('Kunde inte skriva uppladdningen.');
                    try {
                        if (!ftruncate($f, $job['received']) || fseek($f, $job['received']) !== 0) throw new RuntimeException('Kunde inte fortsätta uppladdningen.');
                        $data = file_get_contents($file['tmp_name']);
                        if ($data === false || fwrite($f, $data) !== $length) throw new RuntimeException('Disken kunde inte ta emot hela uppladdningsdelen.');
                        $job['received'] += $length;
                    } finally { fclose($f); }
                }
            } elseif ($action === 'import_step') {
                if ($job['received'] !== $job['size']) throw new RuntimeException('ZIP-filen är inte färdiguppladdad.');
                if (!$job['done'] && (int) ($post['offset'] ?? -1) === $job['next']) {
                    try {
                        $part = ContentImport::run($job['path'], $root . '/content', $job['next'], $limits['batch_files'], $limits);
                        foreach (['next', 'total', 'done'] as $key) $job[$key] = $part[$key];
                        $job['imported'] += $part['imported']; $job['skipped'] += $part['skipped'];
                    } finally { (new Cache($root . '/data/cache'))->clear(); }
                    if ($job['done']) unlink($job['path']);
                }
            } else throw new RuntimeException('Okänt importanrop.');
        }
        $result = $_SESSION['wiki_import'];
        unset($result['path'], $result['user'], $result['expires']);
        return $result;
    }
}
