<?php
/**
 * core/FrontMatter.php
 *
 * Parsar YAML-frontmatter i toppen av en .md-fil:
 *
 *   ---
 *   title: Min sida
 *   date: 2024-06-01
 *   author: Erik Andersson
 *   status: publicerad
 *   draft: false
 *   priority: 3
 *   tags: [wiki, exempel]
 *   description: En kortare beskrivning
 *   ---
 *   Brödtext...
 *
 * Stödjer: strängar (med eller utan citationstecken), listor [a, b],
 * booleans (true/false/yes/no), heltal, decimaltal, null/~.
 * Medvetet begränsat till platta nycklar — inget stöd för nästade objekt.
 */

class FrontMatter
{
    /** @return array{0: array<string,mixed>, 1: string} [meta, body] */
    public static function parse(string $rawContent): array
    {
        $rawContent = ltrim($rawContent);
        if (!str_starts_with($rawContent, '---')) {
            return [[], $rawContent];
        }

        // Stöd för Windows (\r\n) och Unix (\n) radslut
        if (!preg_match('/^---[ \t]*\r?\n(.*?)\r?\n---[ \t]*(?:\r?\n|$)(.*)/s', $rawContent, $m)) {
            return [[], $rawContent];
        }

        $meta = [];
        foreach (preg_split('/\r?\n/', $m[1]) as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with(ltrim($line), '#')) {
                continue; // tom rad eller YAML-kommentar
            }
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $rest] = array_map('trim', explode(':', $line, 2));
            if ($key === '') {
                continue;
            }
            $value = self::parseValue($rest);
            if ($value !== null) {
                $meta[$key] = $value;
            }
        }

        return [$meta, $m[2]];
    }

    private static function parseValue(string $raw): mixed
    {
        $raw = trim($raw);

        // Citerad sträng: "..." eller '...'
        if (preg_match('/^"(.*)"$/s', $raw, $m) || preg_match("/^'(.*)'$/s", $raw, $m)) {
            return $m[1];
        }

        // Lista: [a, b, c] eller [a, "b", c]
        if (preg_match('/^\[(.*)\]$/s', $raw, $m)) {
            $items = [];
            foreach (explode(',', $m[1]) as $item) {
                $item = trim($item, " \t\"'");
                if ($item !== '') {
                    $items[] = $item;
                }
            }
            return $items ?: null;
        }

        // Boolean
        if (in_array(strtolower($raw), ['true', 'yes', 'on'], true))  return true;
        if (in_array(strtolower($raw), ['false', 'no', 'off'], true)) return false;

        // Null / tom
        if ($raw === '' || in_array(strtolower($raw), ['null', '~'], true)) return null;

        // Heltal
        if (preg_match('/^-?\d+$/', $raw)) return (int) $raw;

        // Decimaltal
        if (preg_match('/^-?\d+\.\d+$/', $raw)) return (float) $raw;

        return $raw;
    }

    public static function build(array $meta, string $body): string
    {
        if (empty($meta)) {
            return ltrim($body);
        }
        $lines = ['---'];
        foreach ($meta as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                $lines[] = $key . ': [' . implode(', ', $value) . ']';
            } elseif (is_bool($value)) {
                $lines[] = $key . ': ' . ($value ? 'true' : 'false');
            } else {
                // Ta bort nyrader (förhindrar injektion av extra YAML-nycklar)
                // och citera strängar som innehåller kolon
                $str = str_replace(["\n", "\r"], ' ', (string) $value);
                $lines[] = str_contains($str, ':')
                    ? $key . ': "' . addcslashes($str, '"\\') . '"'
                    : $key . ': ' . $str;
            }
        }
        $lines[] = '---';
        return implode("\n", $lines) . "\n\n" . ltrim($body);
    }
}
