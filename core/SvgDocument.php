<?php
/** Static SVG editing: no scripts, external resources, entities or embedded HTML. */
class SvgDocument
{
    public const MAX_BYTES = 1048576;

    public static function validate(string $svg): string
    {
        if (trim($svg) === '') throw new RuntimeException('SVG-koden är tom.');
        if (strlen($svg) > self::MAX_BYTES) throw new RuntimeException('SVG får vara högst 1 MB.');
        if (!class_exists('DOMDocument')) throw new RuntimeException('PHP-tillägget DOM krävs för SVG-redigering.');
        if (preg_match('/<!DOCTYPE|<!ENTITY|<\?(?!xml\s)/i', $svg)) throw new RuntimeException('DTD, entiteter och bearbetningsinstruktioner stöds inte.');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument();
            if (!$doc->loadXML($svg, LIBXML_NONET) || !$doc->documentElement || $doc->documentElement->localName !== 'svg'
                || $doc->documentElement->namespaceURI !== 'http://www.w3.org/2000/svg') throw new RuntimeException('Ogiltig SVG. Roten måste vara <svg xmlns="http://www.w3.org/2000/svg">.');
            $allowed = explode(' ', 'svg g defs title desc path rect circle ellipse line polyline polygon text tspan linearGradient radialGradient stop clipPath mask pattern marker use symbol filter feGaussianBlur feOffset feBlend feColorMatrix feFlood feComposite feMerge feMergeNode feDropShadow');
            foreach ($doc->getElementsByTagName('*') as $node) {
                if ($node->namespaceURI !== 'http://www.w3.org/2000/svg' || !in_array($node->localName, $allowed, true)) throw new RuntimeException('SVG-elementet ' . $node->nodeName . ' stöds inte. Använd statisk SVG utan skript eller inbäddad HTML.');
                foreach ($node->attributes as $attribute) {
                    $name = strtolower($attribute->localName);
                    $value = $attribute->value;
                    if (str_starts_with($name, 'on') || $name === 'base'
                        || ($attribute->namespaceURI && !in_array($attribute->namespaceURI, ['http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace', 'http://www.w3.org/2000/xmlns/'], true))) throw new RuntimeException('SVG-attributet ' . $attribute->name . ' stöds inte.');
                    if (in_array($name, ['href', 'src'], true) && !preg_match('/^#[a-zA-Z_][\w.-]*$/D', $value)) throw new RuntimeException('SVG får bara referera till lokala #id, inte externa resurser.');
                    // CSS escapes/comments can disguise resource URLs; keep presentation simple.
                    if (preg_match('/[\\\\@]|\/\*|expression\s*\(/i', $value)) throw new RuntimeException('Använd enkla SVG-attribut utan CSS-import eller escapes.');
                    $withoutLocalUrls = preg_replace('/url\(\s*[\'"]?#[a-zA-Z_][\w.-]*[\'"]?\s*\)/i', '', $value);
                    if (preg_match('/url\s*\(|javascript\s*:|data\s*:/i', $withoutLocalUrls)) throw new RuntimeException('Externa eller aktiva SVG-resurser stöds inte.');
                }
            }
            return $svg;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    public static function id(string $raw): MediaId
    {
        foreach (explode(':', $raw) as $part) {
            if ($part === '' || str_starts_with($part, '.') || preg_match('/[\\\\\/\x00-\x1F<>"|?*]/', $part) || preg_match('/[. ]$/', $part)
                || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i', $part)) throw new RuntimeException('Ogiltigt SVG-filnamn eller namespace.');
        }
        $id = new MediaId($raw);
        $canonical = ($id->namespace() !== '' ? $id->namespace() . ':' : '') . $id->filename();
        if ($canonical !== $raw) throw new RuntimeException('Använd ett namespace följt av filnamnet, exempelvis projekt:diagram.svg.');
        if (!str_ends_with(strtolower($id->filename()), '.svg')) throw new RuntimeException('Filnamnet måste sluta med .svg.');
        return $id;
    }

    public static function path(string $mediaDir, MediaId $id): string
    {
        self::id($id->id());
        $path = $id->toFilePath($mediaDir);
        if (is_link($mediaDir) || is_link(dirname($path)) || is_link($path)) throw new RuntimeException('Symboliska länkar får inte redigeras.');
        return $path;
    }

    public static function read(string $mediaDir, MediaId $id): array
    {
        $path = self::path($mediaDir, $id);
        if (!is_file($path)) throw new RuntimeException('SVG-filen hittades inte.');
        $source = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if ($source === false || strlen($source) > self::MAX_BYTES) throw new RuntimeException('SVG-filen kunde inte läsas eller är större än 1 MB.');
        return ['source' => $source, 'revision' => hash('sha256', $source), 'id' => $id->id(), 'url' => $id->url()];
    }

    public static function save(string $mediaDir, string $lockDir, MediaId $id, string $source, string $revision): array
    {
        self::validate($source);
        $path = self::path($mediaDir, $id);
        if (!is_dir($lockDir) && !mkdir($lockDir, 0700, true) && !is_dir($lockDir)) throw new RuntimeException('Kunde inte skapa låsmapp.');
        $lock = fopen($lockDir . '/' . hash('sha256', strtolower($id->id())) . '.lock', 'c');
        if (!$lock) throw new RuntimeException('Kunde inte låsa SVG-filen.');
        $temp = null;
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('Kunde inte låsa SVG-filen.');
            $path = self::path($mediaDir, $id);
            $current = is_file($path) ? hash_file('sha256', $path) : '';
            if (!is_string($current) || !hash_equals($current, $revision)) throw new RuntimeException('Filen finns redan eller har ändrats. Öppna den igen innan du sparar.');
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) throw new RuntimeException('Kunde inte skapa mediamappen.');
            $temp = dirname($path) . '/.svg-' . bin2hex(random_bytes(12)) . '.tmp';
            if (file_put_contents($temp, $source, LOCK_EX) !== strlen($source) || !rename($temp, $path)) throw new RuntimeException('Kunde inte spara SVG-filen.');
            return ['id' => $id->id(), 'url' => $id->url(), 'revision' => hash('sha256', $source)];
        } finally {
            if ($temp && is_file($temp)) unlink($temp);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}
