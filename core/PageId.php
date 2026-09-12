<?php
/**
 * core/PageId.php
 *
 * Värdesobjekt för ett sid-ID, t.ex. "namespace:page1:start".
 *
 * Filmappningsregel:
 *   - Den FÖRSTA kolon-separerade delen blir en mapp under /content
 *     (namespacet).
 *   - Alla ÖVRIGA delar slås ihop med punkt "." till själva filnamnet.
 *
 *   "start"                     -> content/start.md
 *   "projekt:api"                -> content/projekt/api.md
 *   "namespace:page1:start"      -> content/namespace/page1.start.md
 *   "a:b:c:d"                    -> content/a/b.c.d.md
 *
 * Detta skiljer sig medvetet från "ren" DokuWiki (där varje kolon blir
 * en egen undermapp) — här får varje toppnivå-namespace EN mapp, och
 * djupare hierarki uttrycks som punktseparerade filnamn i den mappen.
 * Det håller mappdjupet lågt samtidigt som ID-strukturen känns igen.
 */

class PageId
{
    private string $id;
    private array $parts;

    public function __construct(string $id)
    {
        $id = trim($id, ": \t\n\r\0\x0B");
        $id = $id === '' ? 'start' : $id;

        $parts = array_values(array_filter(
            explode(':', $id),
            fn ($p) => $p !== ''
        ));

        // Sanitera varje del: ta bort null-byte, backslash, snedstreck och
        // path-traversal-sekvenser (.. och varianter) för att förhindra att
        // ett sid-ID kan peka utanför content/-mappen.
        $this->parts = array_values(array_filter(
            array_map(function (string $part): string {
                $part = str_replace(["\0", '\\', '/', '..'], '', $part);
                return trim($part);
            }, $parts),
            fn ($p) => $p !== ''
        ));

        if (empty($this->parts)) {
            $this->parts = ['start'];
        }
        $this->id = implode(':', $this->parts);
    }

    public static function fromRelativePath(string $relativePath, string $contentDir): self
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = preg_replace('#^' . preg_quote(rtrim($contentDir, '/'), '#') . '/#', '', $relativePath);
        $relativePath = preg_replace('/\.md$/', '', $relativePath);

        $segments = explode('/', $relativePath);
        $last = array_pop($segments); // "page1.start" eller "start"
        $lastParts = $last === '' ? [] : explode('.', $last);

        $idParts = array_merge($segments, $lastParts);

        return new self(implode(':', $idParts));
    }

    public function id(): string
    {
        return $this->id;
    }

    /** Namespace = den första delen av ID:t (motsvarar mappnamnet). */
    public function namespace(): string
    {
        return count($this->parts) > 1 ? $this->parts[0] : '';
    }

    /** De delar som slås ihop till filnamnet (allt utom namespace-delen). */
    public function nameParts(): array
    {
        return count($this->parts) > 1 ? array_slice($this->parts, 1) : $this->parts;
    }

    public function toFilePath(string $contentDir): string
    {
        $contentDir = rtrim($contentDir, '/');

        if (count($this->parts) === 1) {
            return $contentDir . '/' . $this->parts[0] . '.md';
        }

        $namespace = $this->parts[0];
        $filename  = implode('.', array_slice($this->parts, 1)) . '.md';

        return $contentDir . '/' . $namespace . '/' . $filename;
    }

    /** Sidopanel för namespacet: content/<namespace>/_sidebar.md (fallback: content/_sidebar.md) */
    public function sidebarPath(string $contentDir): string
    {
        $contentDir = rtrim($contentDir, '/');
        $ns = $this->namespace();
        return $ns === '' ? $contentDir . '/_sidebar.md' : $contentDir . '/' . $ns . '/_sidebar.md';
    }

    public function url(): string
    {
        return '/' . str_replace(':', '/', $this->id);
    }

    public function editUrl(): string
    {
        return '/' . str_replace(':', '/', $this->id) . '?do=edit';
    }

    public function title(): string
    {
        $parts = $this->nameParts();
        $last = end($parts);
        return ucfirst(str_replace(['_', '-'], ' ', $last));
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
