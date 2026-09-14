<?php
/** Rename a page and rewrite explicit wiki/Markdown links, preserving code examples. */
class PageMover
{
    public function __construct(private PageLoader $pages, private History $history) {}

    public function prepare(PageId $from, PageId $to): array
    {
        if ($from->id() === $to->id()) throw new RuntimeException('Det nya sid-ID:t är samma som det gamla.');
        foreach ([$from, $to] as $id) {
            if (str_starts_with(implode('.', $id->nameParts()), '_') || in_array($id->namespace() ?: $id->id(), ['admin', 'chat', 'images', 'config', 'core', 'data', 'templates', 'bin', 'plugins', 'skills'], true)) {
                throw new RuntimeException('Styrfiler och reserverade adresser kan inte användas här.');
            }
        }
        $source = $this->pages->load($from);
        if (!$source) throw new RuntimeException('Sidan som ska flyttas finns inte.');
        if ($this->pages->exists($to) || $this->history->revisions($to)) throw new RuntimeException('Målet har redan en sida eller versionshistorik. Välj ett annat sid-ID.');
        $changes = [];
        foreach ($this->pages->listAll(true) as $sourceId) {
            $id = new PageId($sourceId);
            $page = $this->pages->load($id);
            if (!$page) continue;
            $updated = $this->rewrite($page['raw'], $from, $to);
            if ($updated !== $page['raw'] || $id->id() === $from->id()) {
                $changes[$id->id()] = ['before' => $page['raw'], 'after' => $updated];
            }
        }
        return ['changes' => $changes, 'fingerprint' => hash('sha256', serialize([$from->id(), $to->id(), $changes]))];
    }

    private function rewrite(string $raw, PageId $from, PageId $to): string
    {
        $frontmatter = '';
        if (preg_match('/\A---[^\S\r\n]*\r?\n.*?\r?\n---[^\S\r\n]*(?:\r?\n|$)/s', $raw, $m)) {
            $frontmatter = $m[0]; $raw = substr($raw, strlen($frontmatter));
        }
        $pattern = '/^ {0,3}(`{3,}|~{3,})[^\n]*\n.*?^ {0,3}\1[ \t]*(?=\r?$)|`+[^`\n]*`+|!\[[^\]]*\]\([^\n]*?\)|\[\[([^\]\n]+)\]\]|(?<!!)\[([^\]\n]+)\]\(([^\s()]+)([^)\n]*)\)/ms';
        return $frontmatter . preg_replace_callback($pattern, function ($m) use ($from, $to) {
            if (!empty($m[2])) {
                $parts = explode('|', $m[2], 2);
                $target = trim($parts[0]);
                if (str_contains($target, '>') || preg_match('#^[a-z][a-z0-9+.-]*://#i', $target)) return $m[0];
                if ((new PageId($target))->id() !== $from->id()) return $m[0];
                return '[[' . $to->id() . '|' . ($parts[1] ?? $from->title()) . ']]';
            }
            if (empty($m[4])) return $m[0];
            $href = $m[4];
            if ($href[0] === '#' || str_starts_with($href, '//') || preg_match('#^[a-z][a-z0-9+.-]*://|^(mailto|tel):#i', $href)) return $m[0];
            $pieces = preg_split('/(?=[?#])/', $href, 2);
            if ((new PageId(rawurldecode($pieces[0])))->id() !== $from->id()) return $m[0];
            return '[' . $m[3] . '](' . $to->url() . ($pieces[1] ?? '') . ($m[5] ?? '') . ')';
        }, $raw);
    }

    public function execute(PageId $from, PageId $to, string $fingerprint): int
    {
        $plan = $this->prepare($from, $to);
        if (!hash_equals($plan['fingerprint'], $fingerprint)) throw new RuntimeException('Innehållet ändrades efter förhandsvisningen. Förhandsvisa flytten igen.');
        $changes = $plan['changes'];
        foreach ($changes as $id => $change) $this->history->snapshot(new PageId($id), $change['before'], true);
        $created = false; $movedHistory = false; $written = [];
        try {
            $this->pages->createRaw($to, $changes[$from->id()]['after']);
            $created = true;
            foreach ($changes as $id => $change) {
                if ($id === $from->id()) continue;
                if (($this->pages->load(new PageId($id))['raw'] ?? null) !== $change['before']) throw new RuntimeException('En länkande sida ändrades under flytten.');
                $this->pages->saveRaw(new PageId($id), $change['after']);
                $written[] = $id;
            }
            if (($this->pages->load($from)['raw'] ?? null) !== $changes[$from->id()]['before']) throw new RuntimeException('Källsidan ändrades under flytten.');
            $this->history->move($from, $to);
            $movedHistory = true;
            if (!$this->pages->delete($from)) throw new RuntimeException('Kunde inte ta bort den gamla sidfilen.');
        } catch (\Throwable $error) {
            // Backups remain available even if a rollback is interrupted by disk errors.
            try {
                if ($movedHistory) $this->history->move($to, $from);
                foreach ($written as $id) {
                    if (($this->pages->load(new PageId($id))['raw'] ?? null) === $changes[$id]['after']) $this->pages->saveRaw(new PageId($id), $changes[$id]['before']);
                }
                if ($created && ($this->pages->load($to)['raw'] ?? null) === $changes[$from->id()]['after']) $this->pages->delete($to);
            } catch (\Throwable) {
                throw new RuntimeException('Flytten avbröts och kunde inte återställas helt. Original finns i versionshistoriken.');
            }
            throw new RuntimeException('Flytten avbröts: ' . $error->getMessage());
        }
        return count($written);
    }
}
