<?php
/**
 * admin/index.php
 *
 * Enkel adminpanel — egen front controller (som chat/index.php), men
 * återanvänder wikins vanliga sid-layout eftersom det bara är en vanlig,
 * skrollande innehållssida. Organiserad i flikar (klient-JS växlar dem
 * utan sidladdning; servern minns senast aktiva flik över ett POST-svar
 * via ett dolt 'active_tab'-fält, se $activeTab nedan) så varje flik
 * håller sig kort istället för en enda lång sida:
 *
 *   - Mitt lösenord   — byt eget lösenord (sparas alltid som bcrypt-hash)
 *   - Användare       — skapa/ta bort inloggningsanvändare, sätt vilka
 *                        grupper (config/acl.php) varje konto tillhör —
 *                        styr läs-/redigeringsrätt per namespace, se
 *                        core/Acl.php/Wiki::canEditNamespace()
 *   - Texter          — alla UI-texter (Helpers::defaultStrings()), skriver
 *                        config/strings.php
 *   - Webbplats       — sitenamn, header-/footer-logotyp (uppladdning),
 *                        auth_enabled, inloggningens livslängd, export av
 *                        /content — skriver config/config.php
 *
 * Bara synlig om man är inloggad (Auth::currentUser()) — OAVSETT
 * 'auth_enabled' i config.php, så en administratör kan slå på den
 * inställningen härifrån första gången utan att redan ha den påslagen.
 */

$root = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($root) {
    $path = $root . '/core/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$config    = require $root . '/config/config.php';
$siteName  = $config['site_name'] ?? 'RuneWiki';
$lang      = $config['language'] ?? 'sv';
$theme     = $config['theme'] ?? 'default';
$templates = new TemplateEngine($root . '/templates', $theme);
$assetUrl  = fn (string $p) => $templates->assetUrl($p);
$auth      = new Auth($root . '/data/users/users.php', (bool) ($config['auth_enabled'] ?? false), (int) ($config['session_lifetime_days'] ?? 30));
$strings   = Helpers::resolveStrings($root, $siteName);

// Gate: måste vara inloggad. Kollar currentUser() direkt (inte canEdit()),
// så adminpanelen alltid kräver session — även om auth_enabled skulle
// vara false, ska inte VEM SOM HELST kunna styra konton/inställningar.
if (!$auth->currentUser()) {
    header('Location: /?do=login&redirect_to=' . rawurlencode('/admin/'));
    exit;
}
$currentUser = $auth->currentUser();

/**
 * ?do=export_content — packar hela /content (alla .md-sidor, oavsett
 * namespace) i en .zip och skickar den som nedladdning. Ren läsning, så
 * en GET räcker (som Wiki::handleDownload för enskilda sidor) — sidan
 * kräver ändå inloggning via auth-gaten ovan.
 */
function exportContentZip(string $contentDir): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'PHP-tillägget "zip" saknas på servern, kan inte skapa zip-filen.';
        return;
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'runewiki_export_');
    $zip = new ZipArchive();
    if ($tmpFile === false || $zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Kunde inte skapa zip-filen.';
        return;
    }

    if (is_dir($contentDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($contentDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($files as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($contentDir))), '/');
            $localPath = 'content/' . $relative;
            if ($file->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($file->getPathname(), $localPath);
            }
        }
    }
    $zip->close();

    $filename = 'runedown_content-' . date('Y-m-d') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
}

if (($_GET['do'] ?? '') === 'export_content') {
    exportContentZip($root . '/content');
    exit;
}

/**
 * Riktad textersättning av en enkel 'nyckel' => värde,-rad i config.php.
 * Bevarar alla kommentarer/formatering i övrigt — till skillnad från att
 * skriva om hela filen med var_export(), vilket skulle radera dem.
 * $phpLiteral ska vara redan PHP-formaterad (t.ex. via var_export()
 * eller bokstavligen "true"/"false").
 */
function patchConfigValue(string $configPath, string $key, string $phpLiteral): bool
{
    $src = file_get_contents($configPath);
    if ($src === false) {
        return false;
    }
    $pattern = "/'" . preg_quote($key, '/') . "'(\s*)=>(\s*)[^,]+,/";
    if (!preg_match($pattern, $src)) {
        // Existing installations do not yet have the optional favicon setting.
        if (in_array($key, ['favicon', 'external_links_new_tab', 'distinct_link_colors', 'internal_link_color', 'external_link_color', 'session_lifetime_days'], true) && preg_match('/\breturn\s*\[/', $src, $match, PREG_OFFSET_CAPTURE)) {
            $start = $match[0][1] + strlen($match[0][0]);
            $patched = substr($src, 0, $start) . "\n    '" . $key . "' => " . $phpLiteral . ',' . substr($src, $start);
            return file_put_contents($configPath, $patched, LOCK_EX) !== false;
        }
        return false; // nyckeln hittades inte i filen — rör den inte
    }
    $replacement = "'{$key}'\$1=>\$2" . addcslashes($phpLiteral, '\\$') . ',';
    $patched = preg_replace($pattern, $replacement, $src, 1);
    return $patched !== null && file_put_contents($configPath, $patched, LOCK_EX) !== false;
}

/**
 * Skriver om config/strings.php helt (till skillnad från patchConfigValue,
 * som bevarar en handskriven fil) — bara de nycklar som skiljer sig från
 * Helpers::defaultStrings() sparas, så filen förblir minimal och framtida
 * ändringar av standardvärden fortfarande slår igenom för orörda nycklar.
 * Samma generera-om-helt-mönster som Auth::saveUsers() för users.php.
 */
function saveStringOverrides(string $path, array $overrides): bool
{
    $php = "<?php\n/**\n * config/strings.php\n *\n"
        . " * Genererad av adminpanelen (/admin/, fliken \"Texter\") — bara de\n"
        . " * nycklar som skiljer sig från standardvärdena sparas här. Se\n"
        . " * Helpers::defaultStrings() i core/Helpers.php för alla möjliga\n"
        . " * nycklar. Kan även redigeras för hand.\n"
        . " */\nreturn " . var_export($overrides, true) . ";\n";
    return file_put_contents($path, $php, LOCK_EX) !== false;
}

/** Grupperar Helpers::defaultStrings()-nycklar för flikens fieldsets. Nycklar som inte listas hamnar automatiskt under "Övrigt". */
function stringGroups(): array
{
    return [
        'Header & sidfot' => ['tagline', 'footer_brand_text', 'footer_tools_heading', 'copyright_text', 'powered_by_text', 'powered_by_name', 'powered_by_url', 'breadcrumb_home'],
        'Navigering & sök' => ['nav_search', 'nav_media', 'nav_start', 'search_placeholder', 'search_button', 'menu_label', 'nav_toggle_label', 'theme_toggle_label'],
        'Sidverktyg' => ['page_edit_button', 'page_search_similar', 'page_download_button', 'edit_link'],
        'AI Chat' => ['chat_link', 'chat_tooltip_page', 'chat_tooltip_generic'],
        'Sidindex' => ['sidebar_index_label', 'sidebar_show_more', 'root_group_label'],
        'Inloggning' => ['login_link', 'logout_link', 'logged_in_as', 'login_title', 'login_username_label', 'login_password_label', 'login_submit', 'login_error_credentials', 'login_error_csrf', 'login_required_notice'],
        'Mitt konto (lösenordsbyte)' => ['account_link', 'account_title', 'account_current_password_label', 'account_new_password_label', 'account_confirm_password_label', 'account_submit', 'account_success', 'account_error_wrong_current', 'account_error_mismatch', 'account_error_too_short', 'account_plaintext_warning'],
    ];
}

/**
 * De tre logotyp-slotsen: 'header'/'header-dark' växlar live med ljust/
 * mörkt läge (headerns bakgrund byter färg med temat, se assets/js/
 * theme.js), 'footer' är ensam eftersom sidfoten alltid är mörk oavsett
 * tema. 'default' är vad ett reset-klick sätter — tomt för header-dark
 * betyder "ingen egen mörk logga, visa header_logo i båda lägena".
 */
function logoTypeConfig(): array
{
    return [
        'header'      => ['configKey' => 'header_logo',      'default' => 'img/logo.svg', 'label' => 'Header (ljust läge)'],
        'header-dark' => ['configKey' => 'header_logo_dark', 'default' => '',             'label' => 'Header (mörkt läge)'],
        'footer'      => ['configKey' => 'footer_logo',      'default' => 'img/logo.svg', 'label' => 'Footer'],
        'favicon'     => ['configKey' => 'favicon',          'default' => 'img/favicon.svg', 'label' => 'Favicon'],
    ];
}

/** Skriv-till-fil-namn för en logotyp av given typ ('header'/'header-dark'/'footer'), utan filändelse. */
function logoBaseName(string $type): string
{
    return $type === 'favicon' ? 'favicon' : 'custom-' . $type . '-logo';
}

/** Tar bort ev. tidigare uppladdad logotyp (oavsett .svg/.png) av given typ, så det aldrig ligger kvar en föråldrad fil. */
function removeExistingLogo(string $imgDir, string $type, ?string $keepExtension = null): void
{
    foreach ($type === 'favicon' ? ['svg', 'png', 'ico'] : ['svg', 'png'] as $ext) {
        if ($ext === $keepExtension) continue;
        $path = $imgDir . '/' . logoBaseName($type) . '.' . $ext;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

$errors  = [];
$success = null;

$validTabs = ['konto', 'anvandare', 'texter', 'webbplats'];
$activeTab = in_array($_POST['active_tab'] ?? '', $validTabs, true) ? $_POST['active_tab'] : 'konto';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!Helpers::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = $strings['login_error_csrf'];
    } elseif ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new1    = (string) ($_POST['new_password'] ?? '');
        $new2    = (string) ($_POST['new_password_confirm'] ?? '');
        if (mb_strlen($new1) < 8) {
            $errors[] = $strings['account_error_too_short'];
        } elseif ($new1 !== $new2) {
            $errors[] = $strings['account_error_mismatch'];
        } elseif (!$auth->changeOwnPassword($currentUser, $current, $new1)) {
            $errors[] = $strings['account_error_wrong_current'];
        } else {
            $success = $strings['account_success'];
        }
    } elseif ($action === 'create_user') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $errors[] = 'Ogiltigt användarnamn — tillåtna tecken: a-z A-Z 0-9 _ . -';
        } elseif (mb_strlen($password) < 8) {
            $errors[] = 'Lösenordet måste vara minst 8 tecken.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Lösenorden matchar inte.';
        } elseif (!$auth->setPassword($username, $password)) {
            $errors[] = 'Kunde inte spara användaren (skrivrättigheter till data/users/?).';
        } else {
            $success = 'Sparade användaren "' . $username . '" (lösenord sparat som hash).';
        }
    } elseif ($action === 'save_user_groups') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $groups   = array_values(array_filter(array_map('strval', (array) ($_POST['groups'] ?? []))));
        if ($username === '') {
            $errors[] = 'Inget användarnamn angivet.';
        } elseif (!$auth->setUserGroups($username, $groups)) {
            $errors[] = 'Kunde inte spara grupper för "' . $username . '" (kontot finns inte, eller skrivrättigheter saknas).';
        } else {
            $success = 'Grupper sparade för "' . $username . '".';
        }
    } elseif ($action === 'delete_user') {
        $username = trim((string) ($_POST['username'] ?? ''));
        if ($username === $currentUser) {
            $errors[] = 'Du kan inte ta bort ditt eget konto medan du är inloggad med det.';
        } elseif (!$auth->deleteUser($username)) {
            $errors[] = 'Ingen användare med det namnet hittades.';
        } else {
            $success = 'Tog bort användaren "' . $username . '".';
        }
    } elseif ($action === 'save_strings') {
        $defaults  = Helpers::defaultStrings();
        $overrides = [];
        foreach ($defaults as $key => $default) {
            $posted = $_POST['str_' . $key] ?? null;
            if ($posted === null) {
                continue;
            }
            $posted = (string) $posted;
            if ($posted !== '' && $posted !== $default) {
                $overrides[$key] = $posted;
            }
        }
        if (saveStringOverrides($root . '/config/strings.php', $overrides)) {
            $success = 'Texterna sparades.';
        } else {
            $errors[] = 'Kunde inte skriva till config/strings.php (skrivrättigheter?).';
        }
    } elseif ($action === 'reset_strings') {
        if (saveStringOverrides($root . '/config/strings.php', [])) {
            $success = 'Alla texter återställda till standardvärden.';
        } else {
            $errors[] = 'Kunde inte skriva till config/strings.php (skrivrättigheter?).';
        }
    } elseif ($action === 'save_links') {
        $settings = [
            'external_links_new_tab' => isset($_POST['external_links_new_tab']),
            'distinct_link_colors' => isset($_POST['distinct_link_colors']),
            'internal_link_color' => (string) ($_POST['internal_link_color'] ?? '#0077bc'),
            'external_link_color' => (string) ($_POST['external_link_color'] ?? '#00446b'),
        ];
        if (!preg_match('/^#[0-9a-f]{6}$/i', $settings['internal_link_color']) || !preg_match('/^#[0-9a-f]{6}$/i', $settings['external_link_color'])) {
            $errors[] = 'Välj giltiga färger för länkarna.';
        } else {
            $ok = true;
            foreach ($settings as $key => $value) {
                if (patchConfigValue($root . '/config/config.php', $key, var_export($value, true))) $config[$key] = $value;
                else $ok = false;
            }
            if ($ok) $success = 'Länkinställningarna sparades.';
            else $errors[] = 'Kunde inte spara alla länkinställningar till config/config.php.';
        }
    } elseif ($action === 'save_settings') {
        $newSiteName    = trim((string) ($_POST['site_name'] ?? $siteName));
        $newAuthEnabled = isset($_POST['auth_enabled']);
        $newHistoryEnabled = isset($_POST['history_enabled']);
        $newSessionDays = (int) ($_POST['session_lifetime_days'] ?? ($config['session_lifetime_days'] ?? 30));
        $newSessionDays = max(1, min(365, $newSessionDays));
        $configPath     = $root . '/config/config.php';

        $ok = true;
        if ($newSiteName !== '' && $newSiteName !== $siteName) {
            $ok = $ok && patchConfigValue($configPath, 'site_name', var_export($newSiteName, true));
        }
        if ($newAuthEnabled !== (bool) ($config['auth_enabled'] ?? false)) {
            $ok = $ok && patchConfigValue($configPath, 'auth_enabled', $newAuthEnabled ? 'true' : 'false');
        }
        if ($newHistoryEnabled !== (bool) ($config['history_enabled'] ?? true)) {
            $ok = $ok && patchConfigValue($configPath, 'history_enabled', $newHistoryEnabled ? 'true' : 'false');
        }
        if ($newSessionDays !== (int) ($config['session_lifetime_days'] ?? 30)) {
            $ok = $ok && patchConfigValue($configPath, 'session_lifetime_days', var_export($newSessionDays, true));
        }

        if ($ok) {
            $config['site_name']    = $newSiteName;
            $config['auth_enabled'] = $newAuthEnabled;
            $config['history_enabled'] = $newHistoryEnabled;
            $config['session_lifetime_days'] = $newSessionDays;
            $siteName               = $newSiteName;
            $auth                   = new Auth($root . '/data/users/users.php', $newAuthEnabled, $newSessionDays);
            $success                = 'Inställningarna sparades.';
        } else {
            $errors[] = 'Kunde inte spara till config/config.php (skrivrättigheter?). Ändra värdet för hand där istället.';
        }
    } elseif ($action === 'upload_logo' || $action === 'reset_logo') {
        $type = (string) ($_POST['logo_type'] ?? '');
        $logoTypes = logoTypeConfig();
        if (!isset($logoTypes[$type])) {
            $errors[] = 'Ogiltig logotyp.';
        } else {
            $configKey = $logoTypes[$type]['configKey'];
            $default   = $logoTypes[$type]['default'];
            $label     = $logoTypes[$type]['label'];
            $imgDir    = $root . '/images/logos';

            if ($action === 'reset_logo') {
                if (patchConfigValue($root . '/config/config.php', $configKey, var_export($default, true))) {
                    removeExistingLogo($imgDir, $type);
                    $config[$configKey] = $default;
                    $success = $label . ': återställd till ' . ($default === '' ? 'ingen egen logga (använder header_logo)' : 'standardlogotypen') . '.';
                } else {
                    $errors[] = 'Kunde inte spara till config/config.php (skrivrättigheter?).';
                }
            } else {
                $file = $_FILES['logo_file'] ?? null;
                if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Ingen fil vald.';
                } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Uppladdningen misslyckades (felkod ' . $file['error'] . ').';
                } elseif ($file['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'Filen är för stor (max 2 MB).';
                } else {
                    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = $type === 'favicon' ? ['svg', 'png', 'ico'] : ['svg', 'png'];
                    if (!in_array($ext, $allowedExtensions, true)) {
                        $errors[] = 'Tillåtna format: ' . implode(', ', $allowedExtensions) . '.';
                    } elseif (!is_dir($imgDir) && !mkdir($imgDir, 0775, true) && !is_dir($imgDir)) {
                        $errors[] = 'Kunde inte skapa ' . $imgDir . '.';
                    } else {
                        $target = $imgDir . '/' . logoBaseName($type) . '.' . $ext;
                        if (!is_uploaded_file($file['tmp_name']) || !move_uploaded_file($file['tmp_name'], $target)) {
                            $errors[] = 'Kunde inte spara filen på servern.';
                        } else {
                            $relPath = '/images/logos/' . logoBaseName($type) . '.' . $ext;
                            if ($type === 'favicon') $relPath .= '?v=' . bin2hex(random_bytes(6));
                            if (patchConfigValue($root . '/config/config.php', $configKey, var_export($relPath, true))) {
                                $config[$configKey] = $relPath;
                                removeExistingLogo($imgDir, $type, $ext);
                                $success = $label . ' uppdaterades.';
                            } else {
                                $errors[] = 'Filen sparades men kunde inte skrivas till config/config.php (skrivrättigheter?).';
                            }
                        }
                    }
                }
            }
        }
    }
}

$users = $auth->listUsernames();
sort($users);

// Tillgängliga grupper (fliken "Användare") — definieras i config/acl.php,
// tillämpas i core/Acl.php/Wiki::canEditNamespace()/canReadNamespace().
$aclGroups = array_keys(Helpers::loadConfig($root . '/config/acl.php')['groups'] ?? []);

$rawStrings = Helpers::loadConfig($root . '/config/strings.php', Helpers::defaultStrings());
$defaultStrings = Helpers::defaultStrings();
$groups = stringGroups();
$grouped = array_merge(...array_values($groups));
$groups['Övrigt'] = array_values(array_diff(array_keys($defaultStrings), $grouped));
if (empty($groups['Övrigt'])) {
    unset($groups['Övrigt']);
}

$namespaces = new NamespaceResolver(Helpers::loadConfig($root . '/config/namespaces.php'));
$menu       = Helpers::loadTopbarMenu($root . '/content')
    ?? ($namespaces->settingsFor('')['menu'] ?? Helpers::loadConfig($root . '/config/menu.php'));

$headerLogoPath     = $config['header_logo'] ?? 'img/logo.svg';
$headerLogoDarkPath = $config['header_logo_dark'] ?? '';
$footerLogoPath     = $config['footer_logo'] ?? 'img/logo.svg';

ob_start();
?>
<h1>Adminpanel</h1>
<p class="gbg-admin-lead">Inloggad som <strong><?= Helpers::e($currentUser) ?></strong>.</p>

<?php foreach ($errors as $e): ?>
    <p class="gbg-login-error"><?= Helpers::e($e) ?></p>
<?php endforeach; ?>
<?php if ($success): ?>
    <p class="gbg-admin-success"><?= Helpers::e($success) ?></p>
<?php endif; ?>

<?php
$tabLabels = ['konto' => 'Mitt lösenord', 'anvandare' => 'Användare', 'texter' => 'Texter', 'webbplats' => 'Webbplats'];
?>
<div class="gbg-admin-tabs" role="tablist">
    <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
        <button type="button" class="gbg-admin-tab<?= $tabKey === $activeTab ? ' active' : '' ?>" data-tab="<?= Helpers::e($tabKey) ?>"><?= Helpers::e($tabLabel) ?></button>
    <?php endforeach; ?>
</div>

<div class="gbg-admin-panel" data-panel="konto" <?= $activeTab === 'konto' ? '' : 'hidden' ?>>
    <?php if ($auth->hasPlaintextPassword($currentUser)): ?>
        <p class="gbg-login-error">⚠️ <?= Helpers::e($strings['account_plaintext_warning']) ?></p>
    <?php endif; ?>
    <form class="gbg-form" method="post" action="/admin/">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="active_tab" value="konto">
        <input type="hidden" name="action" value="change_password">
        <label>
            <span><?= Helpers::e($strings['account_current_password_label']) ?></span>
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label>
            <span><?= Helpers::e($strings['account_new_password_label']) ?></span>
            <input type="password" name="new_password" autocomplete="new-password" required>
        </label>
        <label>
            <span><?= Helpers::e($strings['account_confirm_password_label']) ?></span>
            <input type="password" name="new_password_confirm" autocomplete="new-password" required>
        </label>
        <button type="submit" class="gbg-btn gbg-btn-primary"><?= Helpers::e($strings['account_submit']) ?></button>
    </form>
</div>

<div class="gbg-admin-panel" data-panel="anvandare" <?= $activeTab === 'anvandare' ? '' : 'hidden' ?>>
    <?php if (empty($users)): ?>
        <p class="gbg-admin-lead">Inga användare ännu.</p>
    <?php else: ?>
    <?php if (empty($aclGroups)): ?>
        <p class="gbg-login-error">Inga grupper definierade i config/acl.php — lägg till minst en grupp där för att kunna sätta behörighet per namespace.</p>
    <?php endif; ?>
    <ul class="gbg-admin-user-list">
        <?php foreach ($users as $u): ?>
            <li class="gbg-admin-user-row">
                <div class="gbg-admin-user-head">
                    <span><?= Helpers::e($u) ?></span>
                    <?php if ($auth->hasPlaintextPassword($u)): ?>
                        <span class="gbg-admin-flag" title="Lösenordet ligger i klartext, inte hashat">klartext</span>
                    <?php endif; ?>
                    <?php if ($u !== $currentUser): ?>
                        <form method="post" action="/admin/" onsubmit="return confirm('Ta bort användaren &quot;<?= Helpers::e($u) ?>&quot;?');">
                            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
                            <input type="hidden" name="active_tab" value="anvandare">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="username" value="<?= Helpers::e($u) ?>">
                            <button type="submit" class="btn btn-danger" style="padding:.3rem .7rem; font-size:.78rem">Ta bort</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty($aclGroups)): ?>
                <?php $userGroups = $auth->groupsFor($u); ?>
                <form method="post" action="/admin/" class="gbg-admin-user-groups">
                    <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
                    <input type="hidden" name="active_tab" value="anvandare">
                    <input type="hidden" name="action" value="save_user_groups">
                    <input type="hidden" name="username" value="<?= Helpers::e($u) ?>">
                    <?php foreach ($aclGroups as $g): ?>
                        <label class="gbg-checkbox-label">
                            <input type="checkbox" name="groups[]" value="<?= Helpers::e($g) ?>" <?= in_array($g, $userGroups, true) ? 'checked' : '' ?>>
                            <span><?= Helpers::e($g) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="gbg-btn gbg-btn-outline" style="padding:.3rem .7rem; font-size:.78rem">Spara grupper</button>
                </form>
                <?php if (empty($userGroups)): ?>
                    <p class="gbg-admin-flag" style="margin:0">Inga grupper — kan varken läsa "login"/"private"-namespaces eller redigera något.</p>
                <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="gbg-admin-lead">
        Grupper avgör vilka namespaces ett konto får läsa/redigera
        (<code>config/acl.php</code>, kombinerat med <code>'acl'</code>-läget
        per namespace i <code>config/namespaces.php</code>). Nya konton och
        äldre konton som aldrig fått egna grupper visas här som
        <strong>editor</strong> (kan redigera överallt, samma som innan
        grupper fanns) — bocka i/ur och spara för att ändra. Sparar du utan
        någon ibockad grupp alls tappar kontot ALL läs-/redigeringsrätt
        till namespaces med striktare ACL.
    </p>
    <?php endif; ?>

    <h3>Ny användare</h3>
    <form class="gbg-form" method="post" action="/admin/">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="active_tab" value="anvandare">
        <input type="hidden" name="action" value="create_user">
        <label>
            <span>Användarnamn</span>
            <input type="text" name="username" autocomplete="off" required>
        </label>
        <label>
            <span>Lösenord</span>
            <input type="password" name="password" autocomplete="new-password" required>
        </label>
        <label>
            <span>Bekräfta lösenord</span>
            <input type="password" name="password_confirm" autocomplete="new-password" required>
        </label>
        <button type="submit" class="gbg-btn gbg-btn-primary">Skapa användare</button>
    </form>
</div>

<div class="gbg-admin-panel" data-panel="texter" <?= $activeTab === 'texter' ? '' : 'hidden' ?>>
    <p class="gbg-admin-lead">
        Skriver <code>config/strings.php</code> — bara ändrade värden sparas
        dit, resten fortsätter följa standardtexterna. Töm ett fält och spara
        för att återställa just den texten.
    </p>
    <form class="gbg-form" method="post" action="/admin/">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="active_tab" value="texter">
        <input type="hidden" name="action" value="save_strings">

        <?php foreach ($groups as $groupLabel => $keys): ?>
        <fieldset class="gbg-admin-fieldset">
            <legend><?= Helpers::e($groupLabel) ?></legend>
            <?php foreach ($keys as $key): ?>
                <label>
                    <span><?= Helpers::e($key) ?></span>
                    <input type="text" name="str_<?= Helpers::e($key) ?>"
                           value="<?= Helpers::e($rawStrings[$key] ?? $defaultStrings[$key] ?? '') ?>"
                           placeholder="<?= Helpers::e($defaultStrings[$key] ?? '') ?>">
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php endforeach; ?>

        <button type="submit" class="gbg-btn gbg-btn-primary">Spara texter</button>
    </form>
    <form method="post" action="/admin/" style="margin-top:.75rem" onsubmit="return confirm('Återställ ALLA texter till standardvärden?');">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="active_tab" value="texter">
        <input type="hidden" name="action" value="reset_strings">
        <button type="submit" class="btn btn-danger">Återställ alla texter</button>
    </form>
</div>

<div class="gbg-admin-panel" data-panel="webbplats" <?= $activeTab === 'webbplats' ? '' : 'hidden' ?>>
    <form class="gbg-form" method="post" action="/admin/">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="active_tab" value="webbplats">
        <input type="hidden" name="action" value="save_settings">
        <label>
            <span>Sitenamn</span>
            <input type="text" name="site_name" value="<?= Helpers::e($siteName) ?>" required>
        </label>
        <label class="gbg-checkbox-label">
            <input type="checkbox" name="auth_enabled" <?= !empty($config['auth_enabled']) ? 'checked' : '' ?>>
            <span>Kräv inloggning för att redigera (läsning är öppet, om inte ett namespace har striktare ACL — se fliken "Användare" och config/namespaces.php)</span>
        </label>
        <label><input type="checkbox" name="history_enabled" <?= ($config['history_enabled'] ?? true) ? 'checked' : '' ?>> Spara versionshistorik separat i data/history/</label>
        <label>
            <span>Håll mig inloggad (dagar)</span>
            <input type="number" name="session_lifetime_days" min="1" max="365" value="<?= Helpers::e((string) ($config['session_lifetime_days'] ?? 30)) ?>" style="max-width:8rem">
        </label>
        <p class="gbg-admin-lead">Hur länge en inloggning håller sig utan ny inloggning — glidande fönster, förnyas vid varje besök. Gäller nya inloggningar (redan inloggade sessioner uppdateras vid nästa sidladdning).</p>
        <button type="submit" class="gbg-btn gbg-btn-primary">Spara inställningar</button>
    </form>

    <fieldset class="gbg-admin-fieldset" style="margin-top:1.5rem">
        <legend>Länkar</legend>
        <form class="gbg-form" method="post" action="/admin/">
            <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
            <input type="hidden" name="active_tab" value="webbplats">
            <input type="hidden" name="action" value="save_links">
            <label><input type="checkbox" name="external_links_new_tab" <?= ($config['external_links_new_tab'] ?? true) ? 'checked' : '' ?>> Öppna externa webblänkar i ny flik</label>
            <p>Interna länkar öppnas i samma flik. En extern länk leder till en annan webbplats.</p>
            <label><input type="checkbox" name="distinct_link_colors" <?= ($config['distinct_link_colors'] ?? true) ? 'checked' : '' ?>> Använd separata färger för interna och externa innehållslänkar</label>
            <label>Intern länkfärg <input type="color" name="internal_link_color" value="<?= Helpers::e($config['internal_link_color'] ?? '#0077bc') ?>"></label>
            <label>Extern länkfärg <input type="color" name="external_link_color" value="<?= Helpers::e($config['external_link_color'] ?? '#00446b') ?>"></label>
            <p>Utan separata färger används temats färger. Länkar till sidor som saknas behåller sin varningsfärg.</p>
            <button type="submit" class="gbg-btn gbg-btn-primary">Spara länkinställningar</button>
        </form>
    </fieldset>

    <fieldset class="gbg-admin-fieldset" style="margin-top:1.5rem">
        <legend>Logotyper och favicon</legend>
        <p class="gbg-admin-lead">
            Headern byter bakgrund med ljust/mörkt läge (vit / nästan
            svart) och har därför två egna loggor som växlar live med
            temat — låt inte ljust läge visa en logga som drunknar mot
            en vit bakgrund. Sidfoten är alltid mörk oavsett tema och har
            bara en egen logga. Favicon visas i webbläsarfliken och kan vara
            SVG, PNG eller ICO (max 2 MB).
        </p>
        <?php
        $logoCurrentPaths = ['header' => $headerLogoPath, 'header-dark' => $headerLogoDarkPath, 'footer' => $footerLogoPath, 'favicon' => $config['favicon'] ?? 'img/favicon.svg'];
        foreach (logoTypeConfig() as $logoType => $logoMeta):
            $currentPath = $logoCurrentPaths[$logoType];
            // header-dark utan egen uppladdning visar header_logo som förhandsvisning (det den faktiskt faller tillbaka på).
            $previewPath = $currentPath !== '' ? $currentPath : $headerLogoPath;
        ?>
        <div class="gbg-admin-logo-row">
            <div class="gbg-admin-logo-preview">
                <img src="<?= Helpers::e($assetUrl($previewPath)) ?>" alt="<?= Helpers::e($logoMeta['label']) ?>">
                <span><?= Helpers::e($logoMeta['label']) ?></span>
            </div>
            <form class="gbg-form gbg-admin-logo-form" method="post" action="/admin/" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
                <input type="hidden" name="active_tab" value="webbplats">
                <input type="hidden" name="action" value="upload_logo">
                <input type="hidden" name="logo_type" value="<?= Helpers::e($logoType) ?>">
                <input type="file" name="logo_file" accept="<?= $logoType === 'favicon' ? '.svg,.png,.ico' : '.svg,.png' ?>" required>
                <div class="gbg-admin-logo-actions">
                    <button type="submit" class="gbg-btn gbg-btn-primary">Ladda upp</button>
                    <?php if ($currentPath !== $logoMeta['default']): ?>
                        <button type="submit" formaction="/admin/" name="action" value="reset_logo" class="btn btn-danger" onclick="this.form.logo_file.removeAttribute('required')">Återställ</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
        <p class="gbg-admin-lead">Logotyper: SVG eller PNG. Favicon: SVG, PNG eller ICO. Max 2 MB per fil.</p>
    </fieldset>

    <fieldset class="gbg-admin-fieldset" style="margin-top:1.5rem">
        <legend>Exportera innehåll</legend>
        <p class="gbg-admin-lead">
            Ladda ner hela <code>/content</code> (alla sidors .md-filer, i
            alla namespace) som en zip-fil — t.ex. för backup eller för att
            flytta innehållet till en annan installation.
        </p>
        <a class="gbg-btn gbg-btn-primary" href="/admin/?do=export_content">Ladda ner .zip</a>
    </fieldset>

    <fieldset class="gbg-admin-fieldset" style="margin-top:1.5rem">
        <legend>Meny &amp; sidopanel</legend>
        <p class="gbg-admin-lead">
            Toppmenyn och sidopanelen styrs (om filerna finns) av två
            särskilda sidor i innehållsroten. De är avsiktligt dolda från
            sökningen och sidträdet (liksom alla filnamn som börjar med
            "_") eftersom de inte är vanligt innehåll — redigera dem här
            istället.
        </p>
        <p><a href="/_topbar?do=edit">Redigera _topbar.md</a> — punktlista som styr toppmenyns länkar.</p>
        <p><a href="/_sidebar?do=edit">Redigera _sidebar.md</a> — innehållet i sidopanelen (rotens, används där ett namespace saknar en egen).</p>
    </fieldset>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('.gbg-admin-tab');
    var panels = document.querySelectorAll('.gbg-admin-panel');
    function activate(name) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === name); });
        panels.forEach(function (p) { p.hidden = p.dataset.panel !== name; });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.dataset.tab); }); });
    activate(<?= json_encode($activeTab) ?>);
})();
</script>
<?php
$bodyHtml = ob_get_clean();

echo $templates->render('layout', [
    'siteName'    => $siteName,
    'lang'        => $lang,
    'menu'        => $menu,
    'strings'     => $strings,
    'assetUrl'    => $assetUrl,
    'currentId'   => '',
    'view'        => 'admin',
    'page'        => ['title' => 'Adminpanel'],
    'pageId'      => new PageId('start'),
    'pageTree'    => Helpers::buildPageTree(new PageLoader($root . '/content')),
    'authEnabled' => $auth->isEnabled(),
    'currentUser' => $currentUser,
    'headerLogo'     => $headerLogoPath,
    'headerLogoDark' => $headerLogoDarkPath,
    'footerLogo'     => $footerLogoPath,
    'favicon'        => $config['favicon'] ?? 'img/favicon.svg',
    'linkSettings'   => $config,
    'bodyHtml'    => '<div class="gbg-admin">' . $bodyHtml . '</div>',
]);
