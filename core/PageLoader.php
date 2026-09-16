<?php
/**
 * core/PageLoader.php
 *
 * Läser, skriver och kontrollerar existens för .md-filer i /content,
 * utifrån ett PageId. Ansvarar för filsystemsdelen — inte rendering.
 */

class PageLoader
{
    public function __construct(private string $contentDir)
    {
    }

    public function exists(PageId $id): bool
    {
        return is_file($id->toFilePath($this->contentDir));
    }

    /** @return array{meta: array, body: string, raw: string, title: string}|null */
    public function load(PageId $id): ?array
    {
        $path = $id->toFilePath($this->contentDir);
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        [$meta, $body] = FrontMatter::parse($raw);

        return ['meta' => $meta, 'body' => $body, 'raw' => $raw, 'title' => self::displayTitle($id, $meta, $body)];
    }

    /** H1 är visningstitel; metadata och filnamn används som reserv. */
    public static function displayTitle(PageId $id, array $meta, string $body): string
    {
        $fence = null;
        foreach (preg_split('/\R/u', $body) as $line) {
            if (preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $marker)) {
                if ($fence === null) $fence = $marker[1];
                elseif ($marker[1][0] === $fence[0] && strlen($marker[1]) >= strlen($fence) && trim($marker[2]) === '') $fence = null;
                continue;
            }
            if ($fence !== null || !preg_match('/^ {0,3}#[ \t]+(.+?)\s*$/u', $line, $heading)) continue;
            $title = preg_replace('/[ \t]+#+[ \t]*$/', '', $heading[1]);
            // Titlar är ren text, inte HTML eller Markdown.
            $title = preg_replace('/!?\[([^\[\]]+)\]\([^)]*\)/u', '$1', $title);
            $title = preg_replace('/\[\[(?:[^|\]]+\|)?([^\]]+)\]\]/u', '$1', $title);
            $title = preg_replace('/(\*\*|__|~~|`+)(.*?)\1/u', '$2', $title);
            $title = preg_replace('/(?<!\w)([*_])([^*_]+)\1(?!\w)/u', '$2', $title);
            $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== '') return $title;
        }
        return isset($meta['title']) && is_scalar($meta['title']) && trim((string) $meta['title']) !== ''
            ? trim((string) $meta['title']) : $id->title();
    }

    public function save(PageId $id, array $meta, string $body): void
    {
        $this->saveRaw($id, FrontMatter::build($meta, $body));
    }

    public function saveRaw(PageId $id, string $raw): void
    {
        $path = $id->toFilePath($this->contentDir);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Kunde inte skapa sidans mapp.');
        }
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $raw, LOCK_EX) !== strlen($raw)) {
            @unlink($temporary);
            throw new RuntimeException('Kunde inte spara sidan.');
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Kunde inte ersätta sidan.');
        }
    }

    public function delete(PageId $id): bool
    {
        $path = $id->toFilePath($this->contentDir);
        return is_file($path) && unlink($path);
    }

    public function createRaw(PageId $id, string $raw): void
    {
        $path = $id->toFilePath($this->contentDir);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Kunde inte skapa målmappen.');
        $file = @fopen($path, 'x');
        if (!$file) throw new RuntimeException('Målfilen finns redan eller kan inte skapas.');
        $written = fwrite($file, $raw);
        fclose($file);
        if ($written !== strlen($raw)) {
            @unlink($path);
            throw new RuntimeException('Kunde inte skriva målfilen.');
        }
    }

    public function sidebar(PageId $id): ?string
    {
        $path = $id->sidebarPath($this->contentDir);
        if (!is_file($path)) {
            $rootSidebar = rtrim($this->contentDir, '/') . '/_sidebar.md';
            $path = is_file($rootSidebar) ? $rootSidebar : null;
        }
        return $path ? file_get_contents($path) : null;
    }

    /** Listar alla sid-ID:n i wikin (för sökning/namespace-listor). */
    public function listAll(bool $includeSystem = false): array
    {
        $ids = [];
        if (!is_dir($this->contentDir)) {
            return $ids;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->contentDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md' && ($includeSystem || !str_starts_with($file->getFilename(), '_'))) {
                $ids[] = PageId::fromRelativePath($file->getPathname(), $this->contentDir)->id();
            }
        }
        sort($ids);
        return $ids;
    }
}
