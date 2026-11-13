<?php
/** Replace one unambiguous Mermaid fence; never rewrite surrounding content. */
class MermaidDocument
{
    public static function replace(string $raw, mixed $original, mixed $source): string
    {
        if (!is_string($original) || !is_string($source) || trim($source) === '' || strlen($source) > 131072) throw new InvalidArgumentException('Mermaid-koden saknas eller är för stor (max 128 KB).');
        if (preg_match('/^ {0,3}(?:`{3,}|~{3,})/m', $source)) throw new InvalidArgumentException('Ange endast Mermaid-kod, utan Markdown-kodstaket.');
        $normalize = static fn($s) => trim(str_replace("\r\n", "\n", $s));
        $offset = 0; $fence = null; $matches = [];
        foreach (preg_split('/(?<=\n)/', $raw) as $line) {
            if (preg_match('/^ {0,3}(`{3,}|~{3,})([^\r\n]*)/', $line, $m)) {
                if ($fence === null) {
                    $fence = $m[1]; $mermaid = trim($m[2]) === 'mermaid'; $start = $offset + strlen($line);
                } elseif ($m[1][0] === $fence[0] && strlen($m[1]) >= strlen($fence) && trim($m[2]) === '') {
                    if ($mermaid && $normalize(substr($raw, $start, $offset - $start)) === $normalize($original)) $matches[] = [$start, $offset - $start];
                    $fence = null;
                }
            }
            $offset += strlen($line);
        }
        if (count($matches) !== 1) throw new InvalidArgumentException('Diagrammet kunde inte identifieras entydigt. Använd artikelredigeraren om flera diagram har samma kod eller ligger i en lista.');
        $newline = str_contains($raw, "\r\n") ? "\r\n" : "\n";
        $result = substr_replace($raw, str_replace("\n", $newline, $normalize($source)) . $newline, $matches[0][0], $matches[0][1]);
        if (strlen($result) > 524288) throw new InvalidArgumentException('Sidan blir för stor (max 512 KB).');
        return $result;
    }
}
