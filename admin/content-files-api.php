<?php
if (!isset($auth, $currentUser, $root) || !$auth->currentUser()) { http_response_code(404); exit; }
// Included only after admin/index.php has verified the signed-in account.
header('Content-Type: application/json; charset=utf-8');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Helpers::verifyCsrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
        http_response_code(403); throw new RuntimeException('Ogiltig förfrågan. Ladda om adminpanelen.');
    }
    $aclConfig = Helpers::loadConfig($root . '/config/acl.php');
    $groups = $aclConfig['groups'] ?? [];
    $acl = new Acl($groups);
    $allowed = fn(string $ns) => !$auth->isEnabled() || !$groups || $acl->can($auth->groupsFor($currentUser), $ns, 'edit');
    $files = new ContentFiles($root, $allowed, new History($root . '/data/history', true, (int)($config['history_max_versions'] ?? 10)));
    $action = (string)($_POST['operation'] ?? '');
    $name = (string)($_POST['path'] ?? '');
    if ($action === 'list') $result = ['files' => $files->listing(), 'folders' => $files->folders()];
    elseif ($action === 'mkdir') $result = $files->mkdir($name);
    elseif ($action === 'create') $result = $files->write('upload', $name, '', '');
    elseif ($action === 'read') $result = $files->read($name);
    else {
        $raw = (string)($_POST['raw'] ?? '');
        if ($action === 'upload') {
            $upload = $_FILES['file'] ?? [];
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '') || ($upload['size'] ?? 0) > 524288 || !str_ends_with($upload['name'] ?? '', '.md')) throw new RuntimeException('Välj en .md-fil på högst 512 KB.');
            $raw = file_get_contents($upload['tmp_name']);
        }
        $result = $files->write($action, $name, $raw, (string)($_POST['revision'] ?? ''), (string)($_POST['destination'] ?? ''));
    }
    echo json_encode(['ok' => true] + $result, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
}
exit;
