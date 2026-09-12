<?php
/**
 * templates/default/footer.php
 *
 * Ljust, kollapsbart sidindex (som ett eget band, likt brödsmulefältet)
 * följt av en mörk sidfot med två spalter + en "powered by"-rad —
 * i stil med goteborg-dw-template.
 *
 * Varje namespace-grupp visar högst $indexPageLimit sidor direkt — resten
 * göms bakom en "Visa fler"-disclosure (ren HTML/CSS <details>, ingen JS).
 */
$indexPageLimit = 8;
// $strings kommer från Wiki::baseData()/chat/index.php (Helpers::resolveStrings())
// — alla texter nedan kan skrivas över i config/strings.php.
$strings = $strings ?? Helpers::defaultStrings();
// $footerLogo kommer från Wiki::baseData()/chat/index.php/admin/index.php
// (config.php:s 'footer_logo') — byts enklast via /admin/ ("Webbplats").
$footerLogo = $footerLogo ?? 'img/logo.svg';

/** Renderar en <li>-lista av sidor. */
function gbgIndexList(array $pages): string
{
    $html = '';
    foreach ($pages as $p) {
        $html .= '<li><a href="' . Helpers::e($p['url']) . '">' . Helpers::e($p['title']) . '</a></li>';
    }
    return $html;
}

/** Renderar en hel grupp: de första $limit sidorna + ev. "Visa fler"-disclosure för resten. */
function gbgIndexGroup(array $pages, int $limit, string $showMoreTpl): string
{
    $visible = array_slice($pages, 0, $limit);
    $rest    = array_slice($pages, $limit);

    $html = '<ul>' . gbgIndexList($visible) . '</ul>';
    if (!empty($rest)) {
        $html .= '<details class="gbg-index-more">'
            . '<summary>' . Helpers::e(Helpers::interpolate($showMoreTpl, ['count' => count($rest)])) . '</summary>'
            . '<ul>' . gbgIndexList($rest) . '</ul>'
            . '</details>';
    }
    return $html;
}
?>
<div class="gbg-index-band">
    <div class="gbg-container">
        <details>
            <summary class="gbg-index-toggle"><?= Helpers::e($strings['sidebar_index_label']) ?></summary>
            <div class="gbg-index-grid">
                <?php if (!empty($pageTree['_root'])): ?>
                <div class="gbg-index-group">
                    <span class="gbg-index-ns-label">&#x1F4C4; <?= Helpers::e($strings['root_group_label']) ?></span>
                    <?= gbgIndexGroup($pageTree['_root'], $indexPageLimit, $strings['sidebar_show_more']) ?>
                </div>
                <?php endif; ?>
                <?php foreach ($pageTree as $ns => $pages): ?>
                    <?php if ($ns === '_root') continue; ?>
                    <div class="gbg-index-group">
                        <details open>
                            <summary class="gbg-index-ns-label">&#x1F4C2; <?= Helpers::e(ucfirst($ns)) ?>/</summary>
                            <?= gbgIndexGroup($pages, $indexPageLimit, $strings['sidebar_show_more']) ?>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>
</div>

<footer class="gbg-footer">
    <div class="gbg-container gbg-footer-columns">
        <div class="gbg-footer-brand">
            <img src="<?= Helpers::e($assetUrl($footerLogo)) ?>" alt="<?= Helpers::e($siteName) ?>" class="gbg-footer-logo">
            <p><?= Helpers::e($strings['footer_brand_text']) ?></p>
        </div>
        <div class="gbg-footer-links">
            <h2><?= Helpers::e($strings['footer_tools_heading']) ?></h2>
            <ul>
                <li><a href="/?do=search"><?= Helpers::e($strings['nav_search']) ?></a></li>
                <li><a href="/media"><?= Helpers::e($strings['nav_media']) ?></a></li>
                <li><a href="/start"><?= Helpers::e($strings['nav_start']) ?></a></li>
            </ul>
        </div>
    </div>
    <div class="gbg-footer-bar">
        <div class="gbg-container">
            <p><?= Helpers::e($strings['copyright_text']) ?> &middot; <?= Helpers::e($strings['powered_by_text']) ?> <a href="<?= Helpers::e($strings['powered_by_url']) ?>"><?= Helpers::e($strings['powered_by_name']) ?></a></p>
        </div>
    </div>
</footer>
