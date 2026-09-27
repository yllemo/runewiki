<article class="wiki-page">
    <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url()) ?>">Tillbaka till artikeln</a>
    <h1>Metadata – <?= Helpers::e($title) ?></h1>
    <p>Artikelns YAML-frontmatter, ett fält per rad.</p>
    <?php include __DIR__ . '/metadata-fields.php'; ?>
</article>
