<?php $visibleMetadata = Metadata::visible($metadata ?? [], $metadataSettings); ?>
<?php if (!$visibleMetadata): ?>
    <p>Inga metadatafält att visa.</p>
<?php else: ?>
    <dl class="gbg-metadata-fields">
        <?php foreach ($visibleMetadata as $key => $value): ?>
            <div class="gbg-metadata-field">
                <dt><code><?= Helpers::e((string) $key) ?></code></dt>
                <dd><?php if (is_array($value)): ?><pre><?= Helpers::e(FrontMatter::yaml($value)) ?></pre><?php else: ?><?= Helpers::e(is_bool($value) ? ($value ? 'true' : 'false') : ($value === null ? 'null' : (string) $value)) ?><?php endif; ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
<?php endif; ?>
