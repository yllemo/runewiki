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
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->editUrl()) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            <?= Helpers::e($strings['page_edit_button']) ?>
        </a>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url() . '?do=download') ?>" title="<?= Helpers::e($strings['page_download_button']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <?= Helpers::e($strings['page_download_button']) ?>
        </a>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e('/?do=search&q=' . urlencode($pageId->id())) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <?= Helpers::e($strings['page_search_similar']) ?>
        </a>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e('/chat?doc=' . urlencode($pageId->id())) ?>" title="<?= Helpers::e($strings['chat_tooltip_page']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <?= Helpers::e($strings['chat_link']) ?>
        </a>
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

    <p class="gbg-page-id">Sid-ID: <code><?= Helpers::e($pageId->id()) ?></code></p>

</article>
