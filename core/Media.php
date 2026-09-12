<?php
/**
 * core/Media.php
 *
 * Hanterar uppladdning, listning och borttagning av mediefiler under
 * /media. Samma namespace-mappning som PageId använder för /content.
 */

class Media
{
    public function __construct(private string $mediaDir, private array $config)
    {
    }

    /**
     * @param array $file  Ett element ur $_FILES, t.ex. $_FILES['upload']
     */
    public function upload(array $file, string $namespace): MediaId
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Uppladdningen misslyckades (felkod ' . ($file['error'] ?? '?') . ')');
        }

        $maxBytes = (int) ($this->config['max_upload_size_mb'] ?? 10) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Filen är för stor (max ' . ($this->config['max_upload_size_mb'] ?? 10) . ' MB)');
        }

        $filename = Helpers::sanitizeFilename($file['name'] ?? 'fil');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = $this->config['allowed_extensions'] ?? [];
        if ($allowed && !in_array($ext, $allowed, true)) {
            throw new RuntimeException('Filtypen ".' . $ext . '" är inte tillåten');
        }

        $mediaId = MediaId::fromUploadPath(Helpers::sanitizePathSegment($namespace), $filename);
        $path = $mediaId->toFilePath($this->mediaDir);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $path)) {
            throw new RuntimeException('Kunde inte spara filen på servern');
        }

        return $mediaId;
    }

    public function delete(MediaId $id): bool
    {
        $path = $id->toFilePath($this->mediaDir);
        return is_file($path) && unlink($path);
    }

    public function exists(MediaId $id): bool
    {
        return is_file($id->toFilePath($this->mediaDir));
    }

    /** Filstorlek i byte, eller 0 om filen inte finns. Används av /media-listan. */
    public function filesize(MediaId $id): int
    {
        $path = $id->toFilePath($this->mediaDir);
        return is_file($path) ? (int) filesize($path) : 0;
    }

    /** @return string[] Medie-ID:n i angivet namespace ("" = roten) */
    public function listNamespace(string $namespace): array
    {
        $dir = rtrim($this->mediaDir, '/') . ($namespace !== '' ? '/' . $namespace : '');
        if (!is_dir($dir)) {
            return [];
        }
        $ids = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || is_dir($dir . '/' . $entry) || str_starts_with($entry, '.')) {
                continue;
            }
            $ids[] = $namespace !== '' ? $namespace . ':' . $entry : $entry;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Listar mediefiler i ALLA namespaces (varje undermapp under /media,
     * plus rotnivåns filer), grupperat per namespace — för en global
     * översikt på /media (utan namespace i URL:en). Namespaces utan några
     * filer utelämnas. Nyckeln '' är rotnivåns filer.
     *
     * @return array<string, string[]> namespace => medie-ID:n
     */
    public function listAllNamespaces(): array
    {
        $root = rtrim($this->mediaDir, '/');
        if (!is_dir($root)) {
            return [];
        }

        $grouped = [];

        $rootFiles = $this->listNamespace('');
        if ($rootFiles) {
            $grouped[''] = $rootFiles;
        }

        foreach (scandir($root) as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.') || !is_dir($root . '/' . $entry)) {
                continue;
            }
            $files = $this->listNamespace($entry);
            if ($files) {
                $grouped[$entry] = $files;
            }
        }

        ksort($grouped);
        return $grouped;
    }
}
