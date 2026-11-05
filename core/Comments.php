<?php
/** Locked per-page JSON storage, outside the publicly served content tree. */
class Comments
{
    public function __construct(private string $directory) {}

    public static function visible(array $items, bool $authenticated): array
    {
        return array_values(array_filter($items, static fn ($c) => $authenticated || empty($c['private'])));
    }

    public function read(string $page): array
    {
        $path = $this->directory . '/' . hash('sha256', $page) . '.json';
        if (!is_file($path)) return [];
        $f = fopen($path, 'rb');
        if (!$f || !flock($f, LOCK_SH)) throw new RuntimeException('Kunde inte läsa kommentarer.');
        try { return json_decode(stream_get_contents($f), true, 32, JSON_THROW_ON_ERROR)['comments']; }
        finally { flock($f, LOCK_UN); fclose($f); }
    }

    public function change(string $page, callable $mutate): array
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) throw new RuntimeException('Kunde inte skapa kommentarsmappen.');
        $path = $this->directory . '/' . hash('sha256', $page) . '.json';
        $f = fopen($path, 'c+b');
        if (!$f || !flock($f, LOCK_EX)) throw new RuntimeException('Kunde inte låsa kommentarerna.');
        try {
            $raw = stream_get_contents($f);
            $items = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR)['comments'];
            $items = $mutate($items);
            $json = json_encode(['page' => $page, 'comments' => array_values($items)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (count($items) > 1000 || strlen($json) > 4 * 1024 * 1024) throw new RuntimeException('Artikeln har nått gränsen för kommentarer.');
            rewind($f);
            if (fwrite($f, $json) !== strlen($json) || !ftruncate($f, strlen($json)) || !fflush($f)) throw new RuntimeException('Kunde inte spara kommentarerna.');
            return $items;
        } finally { flock($f, LOCK_UN); fclose($f); }
    }

    public static function text(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function add(array $items, array $input, ?string $user, string $visibleText, string $publicText): array
    {
        $text = trim((string) ($input['text'] ?? '')); $name = trim((string) ($input['name'] ?? ''));
        $quote = trim(preg_replace('/\s+/u', ' ', (string) ($input['quote'] ?? '')));
        if ($text === '' || mb_strlen($text) > 5000 || mb_strlen($name) > 80 || mb_strlen($quote) > 1000) throw new RuntimeException('Skriv en kommentar (max 5 000 tecken), namn max 80 tecken.');
        $parent = (string) ($input['parent'] ?? ''); $private = false;
        if ($parent !== '') {
            $matches = array_values(array_filter(self::visible($items, $user !== null), static fn ($c) => $c['id'] === $parent && $c['parent'] === ''));
            if (!$matches) throw new RuntimeException('Kommentarstråden finns inte.');
            $quote = $matches[0]['quote']; $private = $matches[0]['private'];
        } else {
            if ($quote !== '' && !str_contains($visibleText, $quote)) throw new RuntimeException('Textstycket har ändrats. Ladda om artikeln innan du kommenterar.');
            $private = $quote !== '' && !str_contains($publicText, $quote);
            if ($private && $user === null) throw new RuntimeException('Textstycket är inte tillgängligt.');
        }
        $items[] = ['id' => bin2hex(random_bytes(16)), 'parent' => $parent, 'text' => $text, 'quote' => $quote,
            'author' => $user ?? ($name === '' ? 'Anonym' : $name), 'authenticated' => $user !== null,
            'private' => $private, 'created' => gmdate('Y-m-d\TH:i:s\Z'), 'resolved' => false];
        return $items;
    }

    public static function moderate(array $items, string $id, string $action): array
    {
        foreach ($items as &$item) {
            if ($item['id'] !== $id) continue;
            if ($action === 'delete') return array_values(array_filter($items, static fn ($c) => $c['id'] !== $id && $c['parent'] !== $id));
            if ($item['parent'] !== '') throw new RuntimeException('Endast trådar kan markeras som lösta.');
            $item['resolved'] = !$item['resolved'];
            return $items;
        }
        throw new RuntimeException('Kommentaren finns inte.');
    }
}
