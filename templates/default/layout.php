<?php
/**
 * templates/default/layout.php
 *
 * Yttre HTML-skal i stil med goteborg-dw-template (DokuWiki):
 * vit topphuvud, blå huvudmeny med verktygsmeny, ljust brödsmulefält,
 * kort-baserat innehåll och mörk sidfot.
 *
 * Får $page, $pageId, $menu, $sidebarHtml, $bodyHtml, $siteName, $lang,
 * $assetUrl (closure), $currentId, $pageTree från Wiki::baseData().
 */
$hasSidebar = trim($sidebarHtml ?? '') !== '';
?>
<!DOCTYPE html>
<html lang="<?= Helpers::e($lang ?? 'sv') ?>" data-theme="light" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Helpers::e($page['title'] ?? $siteName) ?> — <?= Helpers::e($siteName) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= Helpers::e($assetUrl('img/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= Helpers::e($assetUrl('css/style.css')) ?>">
    <script>(function(h){h.className=h.className.replace(/\bno-js\b/,'js');})(document.documentElement);</script>
</head>
<body>
    <div class="gbg-site">
        <?php include __DIR__ . '/header.php'; ?>

        <div class="gbg-main<?= $hasSidebar ? '' : ' gbg-main--full' ?>">
            <?php if ($hasSidebar): ?>
                <?php include __DIR__ . '/sidebar.php'; ?>
            <?php endif; ?>

            <main class="gbg-content">
                <div class="gbg-card gbg-content-card">
                    <?= $bodyHtml ?? '' ?>
                </div>
            </main>
        </div>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>

    <script src="<?= Helpers::e($assetUrl('js/theme.js')) ?>"></script>
    <?php if (str_contains($bodyHtml ?? '', 'language-mermaid')): ?>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@latest/dist/mermaid.min.js"></script>
    <script src="<?= Helpers::e($assetUrl('js/mermaid.js')) ?>"></script>
    <?php endif; ?>
</body>
</html>
