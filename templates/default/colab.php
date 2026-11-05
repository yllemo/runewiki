<section class="gbg-colab" id="colab" data-url="<?= Helpers::e($pageId->url() . '?do=colab') ?>" data-moderate="<?= $canModerate ? '1' : '0' ?>">
    <div class="gbg-colab-toolbar">
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url()) ?>">← Till artikeln</a>
        <button type="button" class="gbg-btn gbg-btn-primary" id="colab-general">Kommentera artikeln</button>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url() . '?do=colab&op=export-text') ?>">Exportera kommentarer</a>
        <a class="gbg-btn gbg-btn-outline" href="<?= Helpers::e($pageId->url() . '?do=colab&op=export-json') ?>">JSON</a>
    </div>
    <p>Kommentera hela artikeln eller välj pratbubblan vid ett stycke. Gäster kan delta utan konto. Kommentarer sparas separat från artikeltexten.</p>
    <div class="gbg-colab-columns">
        <article class="wiki-body gbg-colab-article" id="colab-article"><?= $articleHtml ?></article>
        <aside class="gbg-colab-panel" aria-label="Kommentarer">
            <h2>Kommentarer <small id="colab-count"></small></h2>
            <div class="gbg-colab-toolbar">
                <label>Visa <select id="colab-filter"><option value="all">Alla</option><option value="open">Öppna</option><option value="resolved">Lösta</option></select></label>
                <button type="button" class="gbg-btn gbg-btn-outline" id="colab-refresh">Uppdatera</button>
            </div>
            <p id="colab-context">Hela artikeln</p>
            <button type="button" class="gbg-btn gbg-btn-outline" id="colab-clear" hidden>Visa alla kommentarer</button>
            <div id="colab-list"></div>
            <form id="colab-form" class="gbg-form">
                <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
                <?php if ($currentUser === null): ?><label><span>Ditt namn (valfritt, visas som gäst)</span><input name="name" maxlength="80" autocomplete="nickname"></label><?php else: ?><p>Kommenterar som <?= Helpers::e($currentUser) ?></p><?php endif; ?>
                <label><span>Kommentar</span><textarea name="text" rows="4" maxlength="5000" required></textarea></label>
                <p id="colab-reply" hidden></p>
                <button type="button" class="gbg-btn gbg-btn-outline" id="colab-cancel-reply" hidden>Avbryt svar</button>
                <button type="submit" class="gbg-btn gbg-btn-primary">Skicka kommentar</button>
            </form>
            <p id="colab-status" role="status" aria-live="polite"></p>
        </aside>
    </div>
    <script type="application/json" id="colab-data"><?= json_encode($comments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</section>
<script src="<?= Helpers::e($assetUrl('js/colab.js')) ?>" defer></script>
