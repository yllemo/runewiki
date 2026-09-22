<?php
/**
 * core/PluginInterface.php
 *
 * Kontrakt som varje plugins/<namn>/plugin.php förväntas implementera.
 * register() anropas en gång vid bootstrap med pluginets egna inställningar
 * från config/plugins.php (nyckeln 'options').
 *
 * Tillgängliga hooks (registreras via $manager->on()):
 *
 *   after_parse   — körs efter att Markdown parsats till HTML.
 *                   Context: ['html' => string, 'markdown' => string]
 *
 *   page_view     — körs när en sida visas (alltid, även från cache).
 *                   Context: ['id' => string, 'html' => string,
 *                             'page_meta' => array, 'file_path' => string,
 *                             'page' => array]
 *   page_markdown — kan ändra Markdown före parsning; gör sidan dynamisk.
 *                   Context: ['id' => string, 'markdown' => string, 'authenticated' => bool]
 *   form_submit   — hanterar POST ?do=form efter inloggnings- och CSRF-kontroll.
 *                   Context: ['source_id', 'post', 'page', 'pages', 'can_read'];
 *                   returnera 'form_result' med target/raw/create eller error.
 *
 *   before_save   — körs innan en sida sparas; kan modifiera 'title'/'body'.
 *                   Context: ['id' => string, 'title' => string,
 *                             'body' => string, 'meta' => array]
 *
 *   after_save    — körs direkt efter att en sida sparats (t.ex. för index).
 *                   Context: ['id' => string, 'title' => string, 'body' => string]
 */

interface PluginInterface
{
    /**
     * @param array $options Plugin-specifika inställningar från config/plugins.php.
     */
    public function register(PluginManager $manager, array $options = []): void;
}
