<?php
/**
 * core/MediaId.php
 *
 * Värdesobjekt för en mediefil, t.ex. "projekt:diagram.png".
 * Samma mappningsregel som PageId: första delen = namespace-mapp
 * under /images, resten (inkl. filändelsen) blir filnamnet.
 *
 *   "logo.png"              -> images/logo.png
 *   "projekt:diagram.png"   -> images/projekt/diagram.png
 */

class MediaId
{
    /** Filändelser som visas som bildminiatyr i /images och embeddas som <img> av Parser. */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'bmp', 'ico'];

    private string $id;
    private array $parts;

    public function __construct(string $id)
    {
        $id = trim($id, ": \t\n\r\0\x0B");
        $this->parts = array_values(array_filter(explode(':', $id), fn ($p) => $p !== ''));
        if (empty($this->parts)) {
            throw new InvalidArgumentException('Tomt medie-ID');
        }
        $this->id = implode(':', $this->parts);
    }

    public static function fromUploadPath(string $namespace, string $filename): self
    {
        $filename = Helpers::sanitizeFilename($filename);
        $namespace = trim($namespace, ': ');
        return new self($namespace === '' ? $filename : $namespace . ':' . $filename);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function namespace(): string
    {
        return count($this->parts) > 1 ? $this->parts[0] : '';
    }

    public function filename(): string
    {
        return count($this->parts) > 1
            ? implode('.', array_slice($this->parts, 1))
            : $this->parts[0];
    }

    /** True om filändelsen är en bildtyp — styr om /images visar en miniatyr eller en filikon. */
    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->filename(), PATHINFO_EXTENSION));
        return in_array($ext, self::IMAGE_EXTENSIONS, true);
    }

    public function toFilePath(string $mediaDir): string
    {
        $mediaDir = rtrim($mediaDir, '/');
        $ns = $this->namespace();
        return $ns === ''
            ? $mediaDir . '/' . $this->filename()
            : $mediaDir . '/' . $ns . '/' . $this->filename();
    }

    public function url(): string
    {
        $ns = $this->namespace();
        return $ns === ''
            ? '/images/' . rawurlencode($this->filename())
            : '/images/' . $ns . '/' . rawurlencode($this->filename());
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
