<?php
/**
 * config/plugins.php
 *
 * Aktiverade plugins och deras inställningar.
 * PluginManager laddar plugins/<namn>/plugin.php i angiven ordning.
 *
 * Varje plugin-post:
 *   'enabled' => true|false   — aktiverar/inaktiverar pluginet
 *   'options' => [...]        — skickas till plugin->register() som $options
 *
 * Tillgängliga hooks (se core/PluginInterface.php för full dokumentation):
 *   after_parse   — modifiera HTML efter Markdown-parsning
 *   page_view     — körs vid sidvisning (alltid, även från cache)
 *   before_save   — kan modifiera titel/brödtext innan sparning
 *   after_save    — post-save (t.ex. sökindesering, notifieringar)
 */

return [

    'byrakrazy' => ['enabled' => true, 'options' => []],

    'example-plugin' => [
        'enabled' => false,
        'options' => [
            'date_format' => 'Y-m-d',
        ],
    ],

    /*
    // Lägg till egna plugins här:
    'mitt-plugin' => [
        'enabled' => true,
        'options' => [
            'nyckel' => 'värde',
        ],
    ],
    */

];
