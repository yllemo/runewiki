<?php
/**
 * templates/default/missing.php
 * Visas när en wikilänk pekar på en sida som inte finns — motsvarar
 * DokuWikis "röda länkar". Bjuder in till att skapa sidan direkt.
 */
?>
<article class="wiki-page">
    <h1><?= Helpers::e($pageId->title()) ?></h1>
    <div class="gbg-alert gbg-alert-info">
        <p>Sidan <code><?= Helpers::e($pageId->id()) ?></code> finns inte ännu.</p>
        <a class="gbg-btn gbg-btn-primary" href="<?= Helpers::e($pageId->editUrl()) ?>">Skapa sidan</a>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url() . '?do=history') ?>">Versionshistorik / återställ</a>
    </div>
</article>
