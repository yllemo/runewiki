<?php
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/** YAML frontmatter, including OKF's nested mappings and lists. */
class FrontMatter
{
    /** @return array{0: array, 1: string} */
    public static function parse(string $rawContent, bool $strict = false): array
    {
        if (str_starts_with($rawContent, "\xEF\xBB\xBF")) $rawContent = substr($rawContent, 3);
        $rawContent = ltrim($rawContent);
        if (!preg_match('/\A---[ \t]*\r?\n(.*?)^---[ \t]*(?:\r?\n|$)(.*)\z/ms', $rawContent, $m)) {
            return [[], $rawContent];
        }
        try {
            $meta = Yaml::parse($m[1], Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_DATE_AS_STRING, 32, 20) ?? [];
            if (!is_array($meta) || ($meta !== [] && array_is_list($meta))) throw new InvalidArgumentException('Frontmatter måste vara en YAML-mappning med fältnamn.');
            if (isset($meta['tags']) && is_array($meta['tags'])) {
                if (!array_is_list($meta['tags']) || array_filter($meta['tags'], static fn ($tag) => !is_scalar($tag))) {
                    throw new InvalidArgumentException('tags ska vara en lista med enkla taggnamn.');
                }
                $meta['tags'] = array_values(array_map(static fn ($tag) => is_bool($tag) ? ($tag ? 'true' : 'false') : (string) $tag, $meta['tags']));
            }
            if (isset($meta['draft']) && is_string($meta['draft'])) {
                $meta['draft'] = match (strtolower($meta['draft'])) { 'yes', 'on' => true, 'no', 'off' => false, default => $meta['draft'] };
            }
            return [$meta, $m[2]];
        } catch (Throwable $e) {
            if ($strict) throw new InvalidArgumentException('Ogiltig YAML: ' . $e->getMessage(), 0, $e);
            return [[], $m[2]];
        }
    }

    public static function yaml(array $meta): string
    {
        return Yaml::dump($meta, 20, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
    }

    public static function build(array $meta, string $body): string
    {
        if (!$meta) return ltrim($body);
        return "---\n" . self::yaml($meta) . "---\n\n" . ltrim($body);
    }
}
