<?php
/**
 * core/TemplateEngine.php
 *
 * Laddar aktivt tema från /templates/<tema>/ och renderar vyer med given data.
 * Om angivet tema inte existerar faller det tillbaka till 'default'.
 * Rena PHP-mallar (include), inget eget mallspråk.
 */

class TemplateEngine
{
    private string $theme;

    public function __construct(private string $templatesDir, string $requestedTheme)
    {
        $themeDir = rtrim($templatesDir, '/') . '/' . $requestedTheme;
        $this->theme = is_dir($themeDir) ? $requestedTheme : 'default';
    }

    public function render(string $view, array $data = []): string
    {
        $path = rtrim($this->templatesDir, '/') . '/' . $this->theme . '/' . $view . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("Mallvyn saknas: {$view} (tema: {$this->theme})");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    public function assetUrl(string $relativePath): string
    {
        // Uploaded logos are shared by themes; bundled defaults remain theme assets.
        if (str_starts_with($relativePath, '/images/')) {
            return $relativePath;
        }
        return '/templates/' . $this->theme . '/assets/' . ltrim($relativePath, '/');
    }

    public function activeTheme(): string
    {
        return $this->theme;
    }

    /**
     * Returnerar metadata (från template.json) för alla tillgängliga teman.
     * Används t.ex. för en admin-vy med temaväljare.
     */
    public function listThemes(): array
    {
        $themes = [];
        foreach (glob(rtrim($this->templatesDir, '/') . '/*/template.json') ?: [] as $f) {
            $data = json_decode(file_get_contents($f), true);
            if (is_array($data)) {
                $dir = basename(dirname($f));
                $themes[$dir] = array_merge($data, ['dir' => $dir, 'active' => $dir === $this->theme]);
            }
        }
        ksort($themes);
        return $themes;
    }
}
