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

    /** Läsbara felmeddelanden för PHPs UPLOAD_ERR_*-koder, se upload(). */
    private const UPLOAD_ERROR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE   => 'Filen är större än vad servern tillåter (upload_max_filesize i php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Filen är större än vad formuläret tillåter',
        UPLOAD_ERR_PARTIAL    => 'Filen laddades bara upp delvis — försök igen',
        UPLOAD_ERR_NO_FILE    => 'Ingen fil valdes',
        UPLOAD_ERR_NO_TMP_DIR => 'Servern saknar en temp-mapp för uppladdningar',
        UPLOAD_ERR_CANT_WRITE => 'Servern kunde inte skriva den tillfälliga filen till disk',
        UPLOAD_ERR_EXTENSION  => 'En PHP-utökning avbröt uppladdningen',
    ];

    /**
     * @param array $file  Ett element ur $_FILES, t.ex. $_FILES['upload']
     */
    public function upload(array $file, string $namespace): MediaId
    {
        // Om HELA POST-kroppen (inte bara den här filen) är större än
        // post_max_size i php.ini tömmer PHP $_POST OCH $_FILES helt utan
        // någon felkod alls — $file blir då [] och ser identisk ut med
        // "inget filfält skickades", vilket annars visar den missvisande
        // "Ingen fil valdes" trots att man faktiskt valde en (för stor) fil.
        if ($file === [] && !empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST)) {
            throw new RuntimeException(
                'Filen är för stor för servern att ta emot (post_max_size i php.ini, mottog '
                . Helpers::formatBytes((int) $_SERVER['CONTENT_LENGTH']) . ')'
            );
        }

        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::UPLOAD_ERROR_MESSAGES[$errorCode] ?? ('Uppladdningen misslyckades (felkod ' . $errorCode . ')'));
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

        $isUploaded = is_uploaded_file($file['tmp_name']);
        $moved      = $isUploaded && move_uploaded_file($file['tmp_name'], $path);
        if (!$moved) {
            // Detaljerna i meddelandet (inte bara "det gick inte") är
            // tillfällig felsökning — de visas direkt på /media (se
            // Wiki::handleMediaUpload() + media.php). Ta bort igen när
            // felet är hittat.
            throw new RuntimeException(sprintf(
                'Kunde inte spara filen på servern (target=%s, is_uploaded_file=%s, dir_writable=%s, dir_exists=%s)',
                $path,
                $isUploaded ? 'ja' : 'NEJ',
                is_writable($dir) ? 'ja' : 'NEJ',
                is_dir($dir) ? 'ja' : 'NEJ'
            ));
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

    /** Absolut sökväg på disk för ett medie-ID — används för att strömma filens rådata direkt, se Wiki::serveMediaFile(). */
    public function absolutePath(MediaId $id): string
    {
        return $id->toFilePath($this->mediaDir);
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
