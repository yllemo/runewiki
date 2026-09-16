<?php
/**
 * templates/default/page.php
 *
 * Sidinnehållet. Verktygsrad (redigera) högerställd ovanför titeln,
 * frontmatter som metadata-rad, taggar som badges längst ner — i stil
 * med goteborg-dw-template.
 */

// $strings kommer från Wiki::baseData()/handleView() (Helpers::resolveStrings())
// — alla texter nedan kan skrivas över i config/strings.php.
$strings = $strings ?? Helpers::defaultStrings();

$hasOwnH1 = (bool) preg_match('/<h1[\s>]/i', $bodyHtml ?? '');

// Fält som hanteras på annat sätt och inte ska visas i meta-raden
$skipFields = ['title', 'template'];

$tags     = (array) ($page['tags'] ?? []);
$metaRest = array_diff_key($page ?? [], array_flip(array_merge($skipFields, ['tags'])));
$hasMeta  = !empty($metaRest);

/**
 * Formaterar ett värde för visning i meta-raden.
 */
function fmFormat(string $key, mixed $value): string
{
    static $dateKeys = ['date', 'datum', 'created', 'updated', 'modified', 'published'];
    if (is_array($value)) {
        return implode(', ', array_map('htmlspecialchars', $value));
    }
    if (is_bool($value)) {
        return $value ? 'Ja' : 'Nej';
    }
    $str = (string) $value;
    if (in_array(strtolower($key), $dateKeys, true) && ($ts = strtotime($str))) {
        return '<time datetime="' . htmlspecialchars($str) . '">'
            . date('j M Y', $ts) . '</time>';
    }
    return htmlspecialchars($str);
}
?>
<article class="wiki-page">

    <div class="gbg-page-tools">
        <!-- Mer sällan använda sidverktyg samlas under en "..."-meny så
             raden hålls kompakt. Redigera och AI Chat är egna, synliga
             knappar, placerade före menyn eftersom de används oftast. -->
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->editUrl()) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            <?= Helpers::e($strings['page_edit_button']) ?>
        </a>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e('/chat?doc=' . urlencode($pageId->id())) ?>" title="<?= Helpers::e($strings['chat_tooltip_page']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <?= Helpers::e($strings['chat_link']) ?>
        </a>
        <div class="gbg-dropdown" data-dropdown="page-tools">
            <button type="button" class="gbg-btn gbg-btn-outline" aria-haspopup="true" aria-expanded="false" title="<?= Helpers::e($strings['menu_label']) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><circle cx="12" cy="5" r="1.75" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.75" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.75" fill="currentColor" stroke="none"/></svg>
                <span class="gbg-sr-only"><?= Helpers::e($strings['menu_label']) ?></span>
            </button>
            <div class="gbg-dropdown-menu">
                <a class="gbg-dropdown-item" href="<?= Helpers::e($pageId->url() . '?do=reader') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></svg>
                    Läs- och exportläge
                </a>
                <a class="gbg-dropdown-item" href="<?= Helpers::e($pageId->url() . '?do=move') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h18v18H3z" opacity="0"/><path d="M9 3H5a2 2 0 0 0-2 2v4"/><path d="M15 3h4a2 2 0 0 1 2 2v4"/><path d="M9 21H5a2 2 0 0 1-2-2v-4"/><path d="M15 21h4a2 2 0 0 0 2-2v-4"/></svg>
                    Byt namn / flytta
                </a>
                <a class="gbg-dropdown-item" href="<?= Helpers::e($pageId->url() . '?do=history') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
                    Versionshistorik
                </a>
                <a class="gbg-dropdown-item" href="<?= Helpers::e($pageId->url() . '?do=download') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?= Helpers::e($strings['page_download_button']) ?>
                </a>
                <a class="gbg-dropdown-item" href="<?= Helpers::e('/?do=search&q=' . urlencode($pageId->id())) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <?= Helpers::e($strings['page_search_similar']) ?>
                </a>
            </div>
        </div>
    </div>

    <?php if (!$hasOwnH1): ?>
        <h1><?= Helpers::e($page['title'] ?? '') ?></h1>
    <?php endif; ?>

    <?php if ($hasMeta): ?>
    <ul class="gbg-meta">
        <?php foreach ($metaRest as $key => $val): ?>
            <?php if ($val === null || $val === false || $val === '') continue; ?>
            <li>
                <span class="gbg-fm-key"><?= Helpers::e(ucfirst($key)) ?></span>
                <span class="gbg-fm-val"><?= fmFormat($key, $val) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="wiki-body">
        <?= $bodyHtml ?? '' ?>
    </div>

    <?php if (!empty($tags)): ?>
    <div class="gbg-tags-row">
        <?php foreach ($tags as $tag): ?>
            <a href="<?= Helpers::e('/?do=search&q=tag:' . urlencode(trim($tag))) ?>"
               class="gbg-tag" title="Visa alla sidor med taggen <?= Helpers::e(trim($tag)) ?>"><?= Helpers::e(trim($tag)) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($backlinks)): ?>
    <section class="gbg-backlinks" aria-label="Bakåtlänkar">
        <h2>Bakåtlänkar <span>(<?= count($backlinks) ?>)</span></h2>
        <ul>
            <?php foreach ($backlinks as $source): ?>
                <li><a href="<?= Helpers::e($source['url']) ?>"><?= Helpers::e($source['title']) ?></a> <small><?= Helpers::e($source['id']) ?></small></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <p class="gbg-page-id">Sid-ID: <code><?= Helpers::e($pageId->id()) ?></code></p>

</article>
