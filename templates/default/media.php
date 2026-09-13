<?php
/**
 * templates/default/media.php
 *
 * Mediahanterare: vid /images/<namespace> visas (och kan laddas upp till) det
 * namespacet; vid /images (utan namespace i URL:en) visas en global
 * översikt över ALLA namespaces med media på en gång, grupperat med en
 * rubrik per namespace. Bilder visas som miniatyrer i ett rutnät; övriga
 * filtyper som ikonkort. Uppladdningsformuläret visas bara om $canUpload
 * är true (Auth::canEdit() — kräver inloggning när auth_enabled = true).
 *
 * Bildväljaren i editorn (edit.php:s "Bläddra i media"-knapp) är en egen
 * inbäddad modal där, byggd direkt från $allMedia — den återanvänder INTE
 * den här sidan.
 *
 * $groupedFiles kommer från Wiki::handleMediaList():
 *   [namespace => [{id, mediaId (MediaId), isImage (bool), size (bytes)}, ...], ...]
 * (rotnivåns filer ligger under nyckeln '').
 *
 * $uploadMessage: sätts av Wiki::handleMediaUpload(), som
 * renderar DEN HÄR mallen direkt i samma svar efter ett uppladdnings-
 * försök (ingen redirect).
 */
$isGlobalView = $namespace === '';
// Match directory URLs directly so a DirectorySlash redirect cannot discard the POST body.
$uploadTarget = '/images/' . ($namespace !== '' ? str_replace(':', '/', $namespace) . '/' : '');
$hasAnyFiles  = false;
foreach ($groupedFiles as $groupItems) {
    if (!empty($groupItems)) {
        $hasAnyFiles = true;
        break;
    }
}
?>
<div class="wiki-media">
    <h1><?= $isGlobalView ? 'Mediahanterare — alla namespaces' : 'Mediahanterare: ' . Helpers::e($namespace) ?></h1>

    <?php if ($canUpload ?? false): ?>
    <form class="gbg-form" method="post" action="<?= Helpers::e($uploadTarget) ?>?do=upload" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="file" name="upload" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp" required>
        <button type="submit" class="gbg-btn gbg-btn-primary">Ladda upp<?= $isGlobalView ? ' (till roten)' : '' ?></button>
    </form>
    <p class="gbg-admin-lead">Endast bilder: PNG, JPG, GIF, SVG, WEBP.</p>
    <?php else: ?>
        <p class="gbg-admin-lead">
            <a href="/?do=login&redirect_to=<?= urlencode($uploadTarget) ?>">Logga in</a> för att kunna ladda upp filer — bläddring är alltid öppet.
        </p>
    <?php endif; ?>

    <?php if (!empty($uploadMessage)): ?>
        <?php $uploadOk = !str_starts_with($uploadMessage, 'Fel:') && !str_starts_with($uploadMessage, 'AVBRUTEN'); ?>
        <div class="gbg-alert <?= $uploadOk ? 'gbg-alert-success' : 'gbg-alert-danger' ?>"><?= Helpers::e($uploadMessage) ?></div>
    <?php endif; ?>

    <?php if (!$hasAnyFiles): ?>
        <p class="gbg-media-empty"><?= $isGlobalView ? 'Inga filer uppladdade någonstans ännu.' : 'Inga filer uppladdade i det här namespacet ännu.' ?></p>
    <?php else: ?>
        <?php foreach ($groupedFiles as $ns => $files): ?>
            <?php if (empty($files)): continue; endif; ?>
            <?php if ($isGlobalView): ?>
                <h2 class="gbg-media-ns-heading">
                    <a href="/images/<?= Helpers::e(str_replace(':', '/', $ns)) ?>"><?= $ns !== '' ? Helpers::e($ns) : '(rot)' ?></a>
                    <span class="gbg-admin-flag"><?= count($files) ?></span>
                </h2>
            <?php endif; ?>
            <div class="gbg-media-grid">
                <?php foreach ($files as $f): ?>
                    <?php $mediaId = $f['mediaId']; ?>
                    <div class="gbg-media-item">
                        <a class="gbg-media-thumb" href="<?= Helpers::e($mediaId->url()) ?>" target="_blank" rel="noopener" title="Öppna <?= Helpers::e($mediaId->filename()) ?> i ny flik">
                            <?php if ($f['isImage']): ?>
                                <img class="gbg-lightbox-img" src="<?= Helpers::e($mediaId->url()) ?>" alt="<?= Helpers::e($mediaId->filename()) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="gbg-media-icon" aria-hidden="true">📄</span>
                            <?php endif; ?>
                        </a>
                        <div class="gbg-media-meta">
                            <span class="gbg-media-name" title="<?= Helpers::e($mediaId->filename()) ?>"><?= Helpers::e($mediaId->filename()) ?></span>
                            <span class="gbg-media-size"><?= Helpers::e(Helpers::formatBytes($f['size'])) ?></span>
                        </div>
                        <code class="gbg-media-embed" title="Klistra in i sidans Markdown för att bädda in filen">{{<?= Helpers::e($f['id']) ?>}}</code>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
