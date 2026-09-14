<?php /** Historical versions are read through History, never via public file URLs. */ ?>
<article class="wiki-page">
    <h1>Versionshistorik: <?= Helpers::e($pageId->title()) ?></h1>
    <p><a href="<?= Helpers::e($pageId->url()) ?>">Till aktuell sida</a></p>
    <?php if ($error): ?><p class="gbg-alert gbg-alert-danger"><?= Helpers::e($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="gbg-alert gbg-alert-success"><?= Helpers::e($success) ?></p><?php endif; ?>
    <?php if (!$historyEnabled): ?><p>Automatisk historik är avstängd. Aktivera den under Admin → Webbplats.</p><?php endif; ?>
    <p>Äldre versioner sparas separat från den aktuella sidan. Välj en version för att läsa eller återställa den. Tider visas i UTC.</p>
    <?php if (!$revisions): ?><p>Inga äldre versioner ännu. När sidan ändras sparas den föregående versionen här.</p><?php endif; ?>
    <ul class="gbg-history-list">
        <?php foreach ($revisions as $revision): ?>
            <?php $date = DateTimeImmutable::createFromFormat('!Ymd-His', substr($revision, 0, 15), new DateTimeZone('UTC')); ?>
            <li><a href="<?= Helpers::e($pageId->url() . '?do=history&revision=' . rawurlencode($revision)) ?>" <?= $selectedRevision === $revision ? 'aria-current="true"' : '' ?>><?= Helpers::e($date ? $date->format('Y-m-d H:i:s') : $revision) ?> UTC · <?= Helpers::e(substr($revision, 16, 8)) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($revisionRaw !== null): ?>
        <h2>Vald version</h2>
        <pre class="gbg-history-source"><code><?= Helpers::e($revisionRaw) ?></code></pre>
        <form method="post" action="<?= Helpers::e($pageId->url()) ?>?do=restore" onsubmit="return confirm('Återställa denna version? Den nuvarande sidan sparas först i historiken.');">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
            <input type="hidden" name="revision" value="<?= Helpers::e($selectedRevision) ?>">
            <button class="gbg-btn gbg-btn-primary" type="submit">Återställ denna version</button>
        </form>
        <details><summary>Visa aktuell sida för jämförelse</summary><pre class="gbg-history-source"><code><?= Helpers::e($currentRaw ?? '(Sidan är borttagen)') ?></code></pre></details>
    <?php endif; ?>
</article>
