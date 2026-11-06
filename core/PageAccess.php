<?php
/** Shared page-level login policy. Never treat malformed frontmatter as public. */
class PageAccess
{
    public static function canReadFile(string $path, bool $authenticated): bool
    {
        if ($authenticated || !is_file($path)) return true;
        $stream = @fopen($path, 'rb');
        if (!$stream) return false;
        try {
            // Read only frontmatter, with a bounded buffer even for large articles.
            $header = ''; $started = false;
            while (($line = fgets($stream, 65537)) !== false) {
                $header .= $line;
                if (strlen($header) > 1048576) return false;
                if (!$started) {
                    $first = trim(ltrim($header, "\xEF\xBB\xBF"));
                    if ($first === '') continue;
                    if ($first !== '---') return true;
                    $started = true;
                    continue;
                }
                if (preg_match('/^---[ \t]*(?:\r?\n)?$/D', $line)) return self::canReadRaw($header, false);
            }
            return !$started;
        } finally { fclose($stream); }
    }

    public static function canReadRaw(string $raw, bool $authenticated): bool
    {
        if ($authenticated) return true;
        $normalized = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $raw));
        if (preg_match('/\A---[ \t]*(?:\r?\n|$)/', $normalized)
            && !preg_match('/\A---[ \t]*\r?\n.*?^---[ \t]*(?:\r?\n|$)/ms', $normalized)) return false;
        try { [$meta] = FrontMatter::parse($raw, true); }
        catch (InvalidArgumentException) { return false; }
        // Only an absent field or the YAML boolean false makes the page public.
        return !array_key_exists('require_login', $meta) || $meta['require_login'] === false;
    }
}
