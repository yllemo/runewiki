<?php
/**
 * config/media.php
 *
 * Inställningar för filuppladdning till /media. Samma namespace-mappning
 * som /content — en uppladdning till namespace "projekt" hamnar i
 * media/projekt/<filnamn>, hanterat av core/Media.php.
 */

return [
    'max_upload_size_mb' => 10,

    // Endast bilder — /media är ett bildgalleri (se templates/default/media.php),
    // inte allmän fillagring. Utöka listan här om du vill tillåta fler format.
    'allowed_extensions' => [
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
    ],

    // Kräver inloggning för uppladdning/borttagning (om auth_enabled = true
    // i config.php). false = öppet för alla, i linje med en helt fri wiki.
    'require_auth_to_upload' => false,
];
