<?php
/**
 * core/PluginManager.php
 *
 * Laddar aktiverade plugins och håller ett hook-register.
 * Hooks och deras context-nycklar dokumenteras i PluginInterface.php.
 */

class PluginManager
{
    private array $hooks = [];

    /**
     * Laddar och registrerar alla aktiverade plugins.
     * Varje plugin får sina egna options från config/plugins.php.
     */
    public function loadEnabled(array $pluginConfig, string $pluginsDir): void
    {
        foreach ($pluginConfig as $name => $settings) {
            if (empty($settings['enabled'])) {
                continue;
            }
            $entry = rtrim($pluginsDir, '/') . '/' . $name . '/plugin.php';
            if (!is_file($entry)) {
                continue;
            }
            require_once $entry;
            $class = $this->classNameFor($name);
            if (class_exists($class) && is_subclass_of($class, PluginInterface::class)) {
                $options = $settings['options'] ?? [];
                (new $class())->register($this, $options);
            }
        }
    }

    /** Registrerar en callback för en namngiven hook. */
    public function on(string $hook, callable $callback): void
    {
        $this->hooks[$hook][] = $callback;
    }

    /**
     * Kör alla callbacks för en hook och matar vidare $context genom kedjan.
     * Returnerar alltid en array — modifierade callbacks kan lägga till/ändra nycklar.
     */
    public function trigger(string $hook, array $context = []): array
    {
        foreach ($this->hooks[$hook] ?? [] as $callback) {
            $result = $callback($context);
            if (is_array($result)) {
                $context = $result;
            }
        }
        return $context;
    }

    /** Returnerar true om minst en callback är registrerad för hooken. */
    public function hasHook(string $hook): bool
    {
        return !empty($this->hooks[$hook]);
    }

    /**
     * Skannar plugins-mappen och returnerar metadata för alla tillgängliga plugins
     * (oavsett om de är aktiverade). Läser plugin.json per plugin.
     */
    public function listAvailable(string $pluginsDir): array
    {
        $plugins = [];
        foreach (glob(rtrim($pluginsDir, '/') . '/*/plugin.json') ?: [] as $f) {
            $data = json_decode(file_get_contents($f), true);
            if (is_array($data)) {
                $plugins[basename(dirname($f))] = $data;
            }
        }
        ksort($plugins);
        return $plugins;
    }

    /** Konverterar mappnamn till klassnamn: "my-plugin" → "MyPlugin". */
    private function classNameFor(string $pluginDirName): string
    {
        $studly = implode('', array_map('ucfirst', explode('-', $pluginDirName)));
        return str_ends_with($studly, 'Plugin') ? $studly : $studly . 'Plugin';
    }
}
