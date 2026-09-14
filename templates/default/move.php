<?php /** Preview and confirm a page rename/namespace move. */ ?>
<article class="wiki-page">
    <h1>Byt namn / flytta sida</h1>
    <?php if ($error): ?><p class="gbg-alert gbg-alert-danger"><?= Helpers::e($error) ?></p><?php endif; ?>
    <?php if ($success): ?>
        <p class="gbg-alert gbg-alert-success"><?= Helpers::e($success) ?></p>
        <a class="gbg-btn gbg-btn-primary" href="<?= Helpers::e($target->url()) ?>">Öppna <?= Helpers::e($target->id()) ?></a>
    <?php else: ?>
        <p>Nuvarande sid-ID: <code><?= Helpers::e($pageId->id()) ?></code></p>
        <p>Ange exempelvis <code>projekt:nytt_namn</code> för att flytta sidan till namespace projekt. Titel och innehåll behålls. Interna wiki- och Markdown-länkar uppdateras, även i sidopaneler och toppmeny.</p>
        <form class="gbg-form" method="post" action="<?= Helpers::e($pageId->url()) ?>?do=move">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
            <label>Nytt sid-ID <input type="text" name="target_id" value="<?= Helpers::e($targetInput) ?>" required></label>
            <button type="submit" class="gbg-btn gbg-btn-outline">Förhandsvisa flytt</button>
        </form>
        <?php if ($plan): ?>
            <h2>Förhandsvisning</h2>
            <p><code><?= Helpers::e($pageId->id()) ?></code> → <code><?= Helpers::e($target->id()) ?></code></p>
            <p>Ny fil: <code><?= Helpers::e($target->toFilePath('content')) ?></code>. Versionshistoriken följer med i sin separata mapp.</p>
            <p>Följande sidor berörs:</p>
            <ul><?php foreach ($plan['changes'] as $sourceId => $change): ?><li><?= Helpers::e($sourceId) ?></li><?php endforeach; ?></ul>
            <p>Bilder ligger kvar på samma adresser. Bokmärken och länkar från andra webbplatser behöver uppdateras separat.</p>
            <form method="post" action="<?= Helpers::e($pageId->url()) ?>?do=move">
                <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
                <input type="hidden" name="target_id" value="<?= Helpers::e($target->id()) ?>">
                <input type="hidden" name="fingerprint" value="<?= Helpers::e($plan['fingerprint']) ?>">
                <input type="hidden" name="confirm_move" value="1">
                <button type="submit" class="gbg-btn gbg-btn-primary">Bekräfta flytt och uppdatera länkar</button>
            </form>
        <?php endif; ?>
        <p><a href="<?= Helpers::e($pageId->url()) ?>">Tillbaka till sidan</a></p>
    <?php endif; ?>
</article>
