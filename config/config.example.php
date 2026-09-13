<?php
/**
 * config/config.php
 *
 * Grundinställningar för wikin. Kopiera denna fil till config.php
 * och justera. Ingen databas — allt är statisk PHP-konfiguration.
 */

return [
    'site_name'        => 'RuneWiki',
    'language'         => 'sv',
    'timezone'         => 'Europe/Stockholm',

    // Aktivt tema (mapp under /templates)
    'theme'            => 'default',

    // Startsida (page-id, motsvarar content/start.md)
    'start_page'       => 'start',

    // Snygga URL:er (kräver .htaccess-rewrite i webroten)
    'pretty_urls'      => true,

    // Cache av renderad HTML (data/cache)
    'cache_enabled'    => true,

    // Sökindex (data/index)
    'search_enabled'   => true,

    // Uppladdad media (se config/media.php för filtyper/storlek)
    'media_dir'        => 'images',

    // Logotyper: /images/logos/... för uppladdade bilder, annars relativt
    // aktivt temas assets/ för standardlogotyper. Byts enklast via
    // /admin/ ("Webbplats"-fliken). Headern byter bakgrund med ljust/
    // mörkt läge och har därför två loggor som växlar live med temat —
    // lämna 'header_logo_dark' tom för samma logga i båda lägena.
    // Sidfoten är alltid mörk och har bara en egen logga.
    'header_logo'      => 'img/logo.svg',
    'header_logo_dark' => 'img/logo-dark.svg',
    'footer_logo'      => 'img/logo.svg',

    // Enkel inloggning (core/Auth.php).
    //   false (standard): helt öppen wiki — alla kan läsa och redigera.
    //   true: läsning är fortfarande öppet för alla, men redigering/
    //         spara/radera/media-uppladdning kräver inloggning.
    // Skapa användare med: php bin/cli.php create-user <användarnamn>
    'auth_enabled'     => false,

    // Versionering av sidor (core/History.php)
    'history_enabled'  => false,
];
