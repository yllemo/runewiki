<?php
/**
 * config/strings.php
 *
 * Skriv över valfria av wikins texter i temat (header, sidfot, knappar
 * m.m.) härifrån. Bara de nycklar du anger här används — resten faller
 * tillbaka på standardvärdena i Helpers::defaultStrings() (core/Helpers.php),
 * så filen behöver aldrig hållas i synk med alla möjliga nycklar.
 *
 * {site_name} ersätts med config.php:s 'site_name' och {year} med
 * innevarande år, överallt de förekommer i en text. I 'sidebar_show_more'
 * fylls {count} i separat per sidindex-grupp.
 *
 * Gäller temat (default) samt /chat/index.php, som båda läser samma fil
 * via Helpers::resolveStrings().
 *
 * Redigeras enklast via /admin/ (fliken "Texter") — spara där skriver om
 * hela den här filen (bara ändrade nycklar), så handskrivna kommentarer
 * här nedanför skrivs över nästa gång någon sparar/återställer i panelen.
 */

return [

    // Header — undertext under sitenamnet.
    // 'tagline' => 'Kunskapsbank & samarbete',

    // Sidfot — kort beskrivning bredvid loggan.
    // 'footer_brand_text' => '{site_name} — databasfri wiki byggd med RuneWiki.',

    // Sidfot — upphovsrätts-/powered by-raden.
    // 'copyright_text'  => '© {year} {site_name}',
    // 'powered_by_text' => 'Drivs av',
    // 'powered_by_name' => 'RuneWiki',
    // 'powered_by_url'  => 'https://github.com/',

    // Verktygslänkar i header/sidfot.
    // 'nav_search' => 'Sök i wikin',
    // 'nav_media'  => 'Mediahanterare',
    // 'nav_start'  => 'Startsida',

    // Verktygsknapparna direkt ovanför sidans innehåll (Redigera/Sök
    // liknande/AI Chat) samt AI Chat-knappen i toppbaren.
    // 'page_edit_button'     => 'Redigera',
    // 'page_search_similar'  => 'Sök liknande',
    // 'page_download_button' => 'Ladda ner .md',
    // 'chat_link'            => 'AI Chat',
    // 'chat_tooltip_page'    => 'Chatta med den här sidan',

    // Inloggning (visas bara när 'auth_enabled' är true i config.php).
    // 'login_link'  => 'Logga in',
    // 'logout_link' => 'Logga ut',
    // 'login_title' => 'Logga in',

    // Övriga etiketter — se Helpers::defaultStrings() för alla nycklar
    // (redigera-länk i menyn, sökfält, menyknapp, temaknapp, sidindex m.m.).
];
