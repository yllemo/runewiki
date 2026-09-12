<?php
/**
 * config/interwiki.php
 *
 * Interwiki-genvägar, motsvarande DokuWikis conf/interwiki.conf.
 * Används i wikitext som [[shortcut>Referens]], t.ex. [[wp>Göteborg]].
 * "%s" i URL:en ersätts med den urlencodade referensen (mellanslag blir _).
 *
 * Lägg till egna genvägar för interna system, t.ex. ärendehanteringen
 * eller ALMBoK.
 */

return [
    'wp'       => 'https://sv.wikipedia.org/wiki/%s',
    'wpen'     => 'https://en.wikipedia.org/wiki/%s',
    'google'   => 'https://www.google.com/search?q=%s',
    'php'      => 'https://www.php.net/%s',
    'github'   => 'https://github.com/%s',

    // Exempel på interna genvägar — justera efter eget behov:
    // 'almbok'   => 'https://almbok.com/wiki/%s',
    // 'jira'     => 'https://jira.example.se/browse/%s',
];
