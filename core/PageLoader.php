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

    /** @return array{meta: array, body: string, raw: string}|null */
    public function load(PageId $id): ?array
    {
        $path = $id->toFilePath($this->contentDir);
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        [$meta, $body] = FrontMatter::parse($raw);

        return ['meta' => $meta, 'body' => $body, 'raw' => $raw];
    }

    public function save(PageId $id, array $meta, string $body): void
    {
        $path = $id->toFilePath($this->contentDir);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, FrontMatter::build($meta, $body));
    }

    public function delete(PageId $id): bool
    {
        $path = $id->toFilePath($this->contentDir);
        return is_file($path) && unlink($path);
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
    public function listAll(): array
    {
        $ids = [];
        if (!is_dir($this->contentDir)) {
            return $ids;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->contentDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md' && !str_starts_with($file->getFilename(), '_')) {
                $ids[] = PageId::fromRelativePath($file->getPathname(), $this->contentDir)->id();
            }
        }
        sort($ids);
        return $ids;
    }
}
