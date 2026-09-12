<?php
/**
 * core/Cache.php
 *
 * Filbaserad cache för renderad HTML per sida (data/cache/).
 *
 * Filstruktur: data/cache/{sha1(stableKey)}/{mtime}.html
 *   - Underkatalogens namn baseras på sid-ID (stabilt).
 *   - Filnamnet är filemtime — bara den senaste versionen behövs.
 *
 * Skrivning sker atomärt via en temporärfil + rename() så att en
 * läsande request aldrig ser en halvskriven fil (race condition).
 *
 * Nyckeln till get()/set() är fortfarande "page:{id}:{mtime}";
 * klassen separerar internt på ":" för att hitta rätt underkatalog.
 */

class Cache
{
    public function __construct(private string $cacheDir, private bool $enabled = true)
    {
        if ($this->enabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function get(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }
        [$dir, $file] = $this->pathFor($key);
        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            return null;
        }
        $html = file_get_contents($path);
        return $html !== false ? $html : null;
    }

    public function set(string $key, string $html): void
    {
        if (!$this->enabled) {
            return;
        }
        [$dir, $file] = $this->pathFor($key);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // Ta bort gamla versioner av samma sida
        foreach (glob($dir . '/*.html') ?: [] as $old) {
            if (basename($old) !== $file) {
                @unlink($old);
            }
        }

        // Atomär skrivning: skriv till tmp-fil, döp sedan om
        $tmp = $dir . '/' . $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $html, LOCK_EX) !== false) {
            rename($tmp, $dir . '/' . $file);
        }
    }

    public function clear(): void
    {
        $base = rtrim($this->cacheDir, '/');
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
            foreach (glob($subDir . '/*.html') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($subDir);
        }
        // Gamla platta .html-filer (format före migrering)
        foreach (glob($base . '/*.html') ?: [] as $file) {
            unlink($file);
        }
    }

    /**
     * Returnerar [underkatalog, filnamn] för nyckeln.
     * Förväntat nyckelformat: "page:{id}:{mtime}" eller liknande.
     * Allt utom sista segmentet (efter sista ":") bildar den stabila delen.
     */
    private function pathFor(string $key): array
    {
        $lastColon = strrpos($key, ':');
        if ($lastColon !== false) {
            $stableKey = substr($key, 0, $lastColon);
            $version   = substr($key, $lastColon + 1);
        } else {
            $stableKey = $key;
            $version   = '0';
        }

        $dir  = rtrim($this->cacheDir, '/') . '/' . sha1($stableKey);
        $file = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $version) . '.html';
        return [$dir, $file];
    }
}
