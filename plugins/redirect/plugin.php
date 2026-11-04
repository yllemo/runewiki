<?php
/** Page-controlled HTTP redirects. Editing and other actions bypass this hook. */
class RedirectPlugin implements PluginInterface
{
    public function register(PluginManager $manager, array $options = []): void
    {
        $manager->on('page_redirect', static function (array $context): array {
            $url = self::target($context['page']['meta']['redirect'] ?? null, $context['url']);
            if ($url !== null) $context['redirect_url'] = $url;
            return $context;
        });
    }

    public static function target(mixed $value, string $currentUrl): ?string
    {
        if (!is_string($value)) return null;
        $url = trim($value);
        // No header injection, browser backslash normalization or protocol-relative URLs.
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $url)) return null;
        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) return null;
            $path = rawurldecode(parse_url($url, PHP_URL_PATH) ?? '');
            if (rtrim($path, '/') === rtrim($currentUrl, '/')) return null;
            return $url;
        }
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || !filter_var($url, FILTER_VALIDATE_URL)) return null;
        return $url;
    }
}
