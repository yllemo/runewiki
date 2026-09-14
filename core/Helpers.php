<?php
/**
 * core/Helpers.php
 *
 * Delade fristående hjälpfunktioner: slugify, sökvägs-sanering,
 * escaping m.m. Samlade som statiska metoder istället för globala
 * funktioner.
 */

class Helpers
{
    /** Public link preferences only; never expose the full site configuration. */
    public static function linkSettingsAttributes(array $config): string
    {
        $attributes = ' data-external-new-tab="' . (($config['external_links_new_tab'] ?? true) ? 'true' : 'false') . '"';
        if ($config['distinct_link_colors'] ?? true) {
            foreach (['internal' => '#0077bc', 'external' => '#00446b'] as $kind => $default) {
                $color = $config[$kind . '_link_color'] ?? $default;
                if (!is_string($color) || !preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = $default;
                $attributes .= ' data-' . $kind . '-color="' . $color . '"';
            }
        }
        return $attributes;
    }

    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $map = ['å' => 'a', 'ä' => 'a', 'ö' => 'o', 'é' => 'e', 'ü' => 'u'];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /** Skyddar mot path traversal i ID:n som kommer från URL/formulär. */
    public static function sanitizePathSegment(string $segment): string
    {
        $segment = str_replace(['..', '\\', "\0"], '', $segment);
        return trim($segment, "/ \t\n\r\0\x0B");
    }

    /** Säkert filnamn för uppladdad media — behåller ändelsen. */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = str_replace(['..', "\0"], '', $filename);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = self::slugify($base) ?: 'fil';
        return $ext ? $base . '.' . strtolower($ext) : $base;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** Läsbar filstorlek, t.ex. "482 B", "12.4 KB", "3.1 MB". Används av /images. */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    /** Läser en config-fil (array-return) på ett förlåtande sätt. */
    public static function loadConfig(string $path, array $default = []): array
    {
        if (!is_file($path)) {
            return $default;
        }
        $data = require $path;
        return is_array($data) ? array_replace($default, $data) : $default;
    }

    /**
     * Returnerar CSRF-token för aktuell session.
     * Skapar en ny token om ingen finns sedan tidigare.
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifierar att ett inkommet CSRF-token matchar sessionens.
     * Använder hash_equals() för att motverka timing-attacker.
     */
    public static function verifyCsrf(?string $submitted): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!$submitted || !$expected) {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    /**
     * Läser content/_topbar.md om den finns och tolkar dess punktlista till
     * samma format som config/menu.php: [['label' => .., 'target' => ..], ...].
     *
     * Varje rad i listan ska vara en länk:
     *   - [[namespace:sida]]                wiki-länk, etikett = sid-titeln
     *   - [[namespace:sida|Egen etikett]]    wiki-länk med egen etikett
     *   - [Etikett](https://exempel.se)      extern (eller absolut) länk
     *
     * HTML-kommentarer (<!-- ... -->, även flerradiga) tas bort innan
     * parsning, så filen kan dokumentera sin egen syntax utan att
     * exempelrader tolkas som riktiga knappar. Övriga rader som inte
     * matchar någon av länkformerna (rubriker, brödtext m.m.) ignoreras
     * tyst. Returnerar null om filen saknas eller inte innehåller några
     * giltiga länkar — då används config/menu.php istället.
     *
     * Delad mellan Wiki (roten) och chat/index.php så bägge front
     * controllers alltid visar samma toppmeny.
     */
    public static function loadTopbarMenu(string $contentDir): ?array
    {
        $path = rtrim($contentDir, '/') . '/_topbar.md';
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path) ?: '';
        $raw = preg_replace('/<!--.*?-->/s', '', $raw); // ta bort HTML-kommentarer (även flerradiga)

        $items = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if (!preg_match('/^[-*]\s+(.+)$/', trim($line), $m)) {
                continue;
            }
            $content = trim($m[1]);

            // [[target]] eller [[target|Etikett]]
            if (preg_match('/^\[\[([^\]|]+)(?:\|([^\]]+))?\]\]$/', $content, $wm)) {
                $target = str_replace('/', ':', trim($wm[1], '/: '));
                if ($target === '') {
                    continue;
                }
                $items[] = ['label' => isset($wm[2]) ? trim($wm[2]) : $target, 'target' => $target];
                continue;
            }

            // [Etikett](url) — extern länk eller absolut sökväg
            if (preg_match('/^\[([^\]]+)\]\(([^)]+)\)$/', $content, $mm)) {
                $items[] = ['label' => trim($mm[1]), 'target' => trim($mm[2])];
            }
        }

        return $items ?: null;
    }

    /**
     * Standardtexter för temats header/footer (tagline, sidfotstexter,
     * knappetiketter m.m.) — allt en användare rimligen vill kunna skriva
     * om utan att röra temafilerna. config/strings.php kan skriva över
     * valfria av dessa nycklar; övriga behåller sitt standardvärde
     * (se resolveStrings()).
     *
     * {site_name} och {year} ersätts automatiskt i resolveStrings().
     * {count} i 'sidebar_show_more' fylls i per användningsplats i
     * footer.php (en disclosure per namespace kan ha olika antal dolda
     * sidor) och lämnas därför orörd här.
     */
    public static function defaultStrings(): array
    {
        return [
            'tagline'              => 'Kunskapsbank & samarbete',
            'footer_brand_text'    => '{site_name} — databasfri wiki byggd med RuneWiki.',
            'footer_tools_heading' => 'Verktyg',
            'nav_search'           => 'Sök i wikin',
            'nav_media'            => 'Mediahanterare',
            'nav_start'            => 'Startsida',
            'copyright_text'       => '© {year} {site_name}',
            'powered_by_text'      => 'Drivs av',
            'powered_by_name'      => 'RuneWiki',
            'powered_by_url'       => 'https://github.com/',
            'search_placeholder'   => 'Sök…',
            'search_button'        => 'Sök',
            'edit_link'            => 'Redigera sida',
            'page_edit_button'     => 'Redigera',
            'page_search_similar'  => 'Sök liknande',
            'page_download_button' => 'Ladda ner .md',
            'chat_link'            => 'AI Chat',
            'chat_tooltip_page'    => 'Chatta med den här sidan',
            'chat_tooltip_generic' => 'AI Chat',
            'menu_label'           => 'Meny',
            'nav_toggle_label'     => 'Huvudmeny',
            'theme_toggle_label'   => 'Byt tema',
            'sidebar_index_label'  => 'Sidindex',
            'sidebar_show_more'    => 'Visa fler ({count})',
            'breadcrumb_home'      => 'Hem',
            'root_group_label'     => 'Rot',

            // Inloggning (visas bara när config.php:s 'auth_enabled' är true).
            'login_link'              => 'Logga in',
            'logout_link'             => 'Logga ut',
            'logged_in_as'            => 'Inloggad som {user}',
            'login_title'             => 'Logga in',
            'login_username_label'    => 'Användarnamn',
            'login_password_label'    => 'Lösenord',
            'login_submit'            => 'Logga in',
            'login_error_credentials' => 'Fel användarnamn eller lösenord.',
            'login_error_csrf'        => 'Ogiltig förfrågan — ladda om sidan och försök igen.',
            'login_required_notice'   => 'Du måste logga in för att redigera den här wikin.',

            // Självbetjäning: byt eget lösenord på /?do=account (kräver ingen CLI-åtkomst).
            'account_link'                    => 'Byt lösenord',
            'account_title'                   => 'Byt lösenord',
            'account_current_password_label'  => 'Nuvarande lösenord',
            'account_new_password_label'      => 'Nytt lösenord',
            'account_confirm_password_label'  => 'Bekräfta nytt lösenord',
            'account_submit'                  => 'Spara nytt lösenord',
            'account_success'                 => 'Lösenordet är bytt och sparat som hash.',
            'account_error_wrong_current'     => 'Nuvarande lösenord stämmer inte.',
            'account_error_mismatch'          => 'De nya lösenorden matchar inte varandra.',
            'account_error_too_short'         => 'Det nya lösenordet måste vara minst 8 tecken.',
            'account_plaintext_warning'       => 'Ditt lösenord ligger just nu i klartext på servern (data/users/users.php). Byt det här så sparas det som en säker hash istället.',
        ];
    }

    /** Ersätter {nyckel}-platshållare i en sträng med värden ur $vars. */
    public static function interpolate(string $template, array $vars): string
    {
        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['{' . $key . '}'] = (string) $value;
        }
        return strtr($template, $pairs);
    }

    /**
     * Läser config/strings.php (om den finns) ovanpå defaultStrings() och
     * interpolerar {site_name}/{year} överallt de förekommer. Delad mellan
     * Wiki (roten) och chat/index.php så texterna blir identiska oavsett
     * front controller.
     */
    public static function resolveStrings(string $rootDir, string $siteName): array
    {
        $strings = self::loadConfig(rtrim($rootDir, '/') . '/config/strings.php', self::defaultStrings());
        $vars    = ['site_name' => $siteName, 'year' => date('Y')];
        foreach ($strings as $key => $value) {
            if (is_string($value)) {
                $strings[$key] = self::interpolate($value, $vars);
            }
        }
        return $strings;
    }

    /**
     * Bygger sidträdet (namespace => [{id,url,title}, ...]) som används av
     * footer.php för "Sidindex". Delad mellan Wiki (roten) och
     * chat/index.php så footern blir identisk oavsett front controller.
     */
    public static function buildPageTree(PageLoader $pages): array
    {
        $tree = [];
        foreach ($pages->listAll() as $rawId) {
            $pid = new PageId($rawId);
            $ns  = $pid->namespace() ?: '_root';
            $tree[$ns][] = [
                'id'    => $rawId,
                'url'   => $pid->url(),
                'title' => $pid->title(),
            ];
        }
        ksort($tree);
        return $tree;
    }
}
