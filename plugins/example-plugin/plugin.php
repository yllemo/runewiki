<?php
/**
 * plugins/example-plugin/plugin.php
 *
 * Exempelplugin: visar "Senast ändrad: datum" längst ner på varje sida.
 * Används som boilerplate för egna plugins.
 *
 * Aktivera i config/plugins.php:
 *   'example-plugin' => ['enabled' => true, 'options' => ['date_format' => 'Y-m-d']]
 *
 * Hook: page_view — körs alltid, även vid cache-träff.
 */

class ExamplePlugin implements PluginInterface
{
    private array $options;

    public function register(PluginManager $manager, array $options = []): void
    {
        $this->options = $options;
        $manager->on('page_view', [$this, 'appendLastModified']);
    }

    public function appendLastModified(array $context): array
    {
        $filePath = $context['file_path'] ?? null;
        if (!$filePath || !is_file($filePath)) {
            return $context;
        }

        $format = $this->options['date_format'] ?? 'Y-m-d';
        $date   = date($format, filemtime($filePath));

        $context['html'] .= "\n<p class=\"page-modified\"><small>Senast ändrad: {$date}</small></p>";
        return $context;
    }
}
