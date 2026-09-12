<?php
/**
 * templates/default/search.php
 * Sökresultatvy. $results: array{id,title,excerpt,url}
 * $createPageId / $createPageUrl: sätts när söktermen liknar ett sid-ID.
 */
?>
<div class="gbg-search-results">
    <?php if (!empty($tagName)): ?>
        <h1>Sidor taggade: <a href="<?= Helpers::e('/?do=search&q=tag:' . urlencode($tagName)) ?>" class="gbg-tag"><?= Helpers::e($tagName) ?></a></h1>
    <?php else: ?>
        <h1>Sökresultat: "<?= Helpers::e($term) ?>"</h1>
    <?php endif; ?>

    <?php if (empty($results)): ?>
        <p>Inga träffar.</p>
        <?php if ($createPageUrl): ?>
            <div class="gbg-alert gbg-alert-info">
                Sidan <strong><code><?= Helpers::e($createPageId) ?></code></strong> finns inte &mdash;
                <a href="<?= Helpers::e($createPageUrl) ?>">skapa den nu</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <ul class="gbg-result-list">
            <?php foreach ($results as $result): ?>
                <li class="gbg-result-item">
                    <a href="<?= Helpers::e($result['url']) ?>"><?= Helpers::e($result['title']) ?></a>
                    <p><?= Helpers::e($result['excerpt']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($createPageUrl): ?>
            <div class="gbg-alert gbg-alert-info">
                Hittade du inte vad du sökte? <a href="<?= Helpers::e($createPageUrl) ?>">Skapa sida <code><?= Helpers::e($createPageId) ?></code></a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
