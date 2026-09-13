<?php
/**
 * upload.php
 *
 * HELT FRISTÅENDE testverktyg — använder INGET av core/ (ingen Router,
 * Auth, CSRF, mallmotor). Enda syftet är att avgöra om PHP själv kan ta
 * emot och spara en uppladdad fil till /images på just den här servern,
 * utan att wikins egen kod (session, auth, redirects m.m.) kan störa
 * resultatet. Om DEN HÄR filen inte fungerar är problemet i PHP-
 * konfigurationen eller filrättigheterna på servern — inte i RuneWiki.
 *
 * ⚠️ INGEN INLOGGNING, INGEN CSRF-KONTROLL — vem som helst som hittar
 * URL:en kan ladda upp filer så länge den här filen ligger kvar. Bara
 * bildfiler tillåts (samma whitelist som config/media.php), men TA BORT
 * DEN HÄR FILEN när du är klar med felsökningen.
 *
 * Ingen header()-redirect används alls (se den stora diskussionen om
 * varför en redirect kan misslyckas tyst) — allt renderas direkt i
 * samma svar, så ett fel alltid syns.
 */

declare(strict_types=1);

$targetDir = __DIR__ . '/images';
$allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

$uploadErrorMessages = [
    UPLOAD_ERR_INI_SIZE   => 'Filen är större än upload_max_filesize i php.ini',
    UPLOAD_ERR_FORM_SIZE  => 'Filen är större än formulärets egen gräns',
    UPLOAD_ERR_PARTIAL    => 'Filen laddades bara upp delvis — försök igen',
    UPLOAD_ERR_NO_FILE    => 'Ingen fil valdes',
    UPLOAD_ERR_NO_TMP_DIR => 'Servern saknar en temp-mapp för uppladdningar',
    UPLOAD_ERR_CANT_WRITE => 'Servern kunde inte skriva den tillfälliga filen till disk',
    UPLOAD_ERR_EXTENSION  => 'En PHP-utökning avbröt uppladdningen',
];

/** @var array<string,string>|null $result null = inget försök gjort än */
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['upload'] ?? null;

    if ($file === null && !empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0 && empty($_POST)) {
        // Hela POST-kroppen större än post_max_size -> $_POST/$_FILES töms
        // helt av PHP, utan felkod. Ser annars ut som "ingen fil vald".
        $result = [
            'ok'      => false,
            'message' => 'Filen är för stor för servern att ta emot (post_max_size i php.ini, mottog '
                . number_format((int) $_SERVER['CONTENT_LENGTH'] / 1048576, 1) . ' MB)',
        ];
    } elseif ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $result = ['ok' => false, 'message' => $uploadErrorMessages[$code] ?? ('Okänt uppladdningsfel (kod ' . $code . ')')];
    } else {
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            $result = ['ok' => false, 'message' => 'Filtypen ".' . $ext . '" är inte tillåten. Tillåtna: ' . implode(', ', $allowedExtensions)];
        } else {
            // Säkert filnamn: bara ord, siffror, - . _ — inget som kan tolkas
            // som en sökväg (../, /) eller skrivas över en befintlig fil av misstag.
            $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $filename = $safeBase . '-' . date('Ymd-His') . '.' . $ext;

            $dirExisted = is_dir($targetDir);
            if (!$dirExisted) {
                @mkdir($targetDir, 0775, true);
            }

            $target = $targetDir . '/' . $filename;
            $isUploadedFile = is_uploaded_file($file['tmp_name']);
            $moved = $isUploadedFile && move_uploaded_file($file['tmp_name'], $target);

            if ($moved) {
                $result = [
                    'ok'      => true,
                    'message' => 'Uppladdad: ' . $filename . ' (' . number_format(filesize($target) / 1024, 1) . ' KB) → images/' . $filename,
                ];
            } else {
                $result = [
                    'ok'      => false,
                    'message' => sprintf(
                        'Kunde INTE spara filen. is_uploaded_file=%s, mål=%s, mapp fanns sedan innan=%s, mapp skrivbar=%s, mapp ägare=%s, PHP körs som=%s',
                        $isUploadedFile ? 'ja' : 'NEJ',
                        $target,
                        $dirExisted ? 'ja' : 'NEJ (skapades nu)',
                        is_writable($targetDir) ? 'ja' : 'NEJ',
                        function_exists('posix_getpwuid') && function_exists('fileowner') ? (posix_getpwuid(fileowner($targetDir))['name'] ?? '?') : '(posix ej tillgängligt)',
                        function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : (get_current_user() ?: '?')
                    ),
                ];
            }
        }
    }
}

$phpUploadOn = ini_get('file_uploads') ? 'På' : 'AV (!)';
?>
<!doctype html>
<html lang="sv">
<head>
<meta charset="utf-8">
<title>Fristående uppladdningstest</title>
<style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 2rem auto; padding: 0 1rem; color: #222; }
    h1 { font-size: 1.3rem; }
    .box { border-radius: 8px; padding: .9rem 1.1rem; margin: 1rem 0; }
    .ok { background: #e6f6e9; border: 1px solid #2e7d32; color: #1b5e20; }
    .err { background: #fdecea; border: 1px solid #c62828; color: #7a1a1a; }
    .info { background: #f1f1f1; border: 1px solid #ccc; }
    .info dl { display: grid; grid-template-columns: auto 1fr; gap: .2rem 1rem; margin: 0; font-size: .85rem; }
    .info dt { font-weight: 600; }
    code { background: #eee; padding: .1rem .3rem; border-radius: 3px; }
    form { margin: 1.5rem 0; }
    button { padding: .5rem 1rem; font-size: 1rem; cursor: pointer; }
</style>
</head>
<body>
<h1>🧪 Fristående uppladdningstest → /images</h1>
<p>Ingen koppling till RuneWikis egen kod (Router/Auth/CSRF/mallar). Testar bara om PHP kan spara en fil till <code><?= htmlspecialchars($targetDir) ?></code> på den här servern.</p>

<?php if ($result !== null): ?>
    <div class="box <?= $result['ok'] ? 'ok' : 'err' ?>">
        <strong><?= $result['ok'] ? '✅ Lyckades' : '❌ Misslyckades' ?>:</strong>
        <?= htmlspecialchars($result['message']) ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="upload" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp" required>
    <button type="submit">Ladda upp testbild</button>
</form>

<div class="box info">
    <strong>Servermiljö:</strong>
    <dl>
        <dt>PHP-version</dt><dd><?= htmlspecialchars(PHP_VERSION) ?></dd>
        <dt>file_uploads</dt><dd><?= $phpUploadOn ?></dd>
        <dt>upload_max_filesize</dt><dd><?= htmlspecialchars((string) ini_get('upload_max_filesize')) ?></dd>
        <dt>post_max_size</dt><dd><?= htmlspecialchars((string) ini_get('post_max_size')) ?></dd>
        <dt>upload_tmp_dir</dt><dd><?= htmlspecialchars((string) (ini_get('upload_tmp_dir') ?: '(systemets standard)')) ?></dd>
        <dt>Måldir finns</dt><dd><?= is_dir($targetDir) ? 'ja (' . htmlspecialchars($targetDir) . ')' : 'NEJ än — skapas vid första uppladdningen' ?></dd>
        <dt>Måldir skrivbar</dt><dd><?= is_dir($targetDir) ? (is_writable($targetDir) ? 'ja' : 'NEJ ⚠️') : '(kan inte kolla — finns inte än)' ?></dd>
        <dt>open_basedir</dt><dd><?= htmlspecialchars((string) (ini_get('open_basedir') ?: '(ej satt)')) ?></dd>
    </dl>
</div>

<p style="color:#a00"><strong>Kom ihåg:</strong> ta bort <code>upload.php</code> från servern när felsökningen är klar — den kräver ingen inloggning.</p>
</body>
</html>
