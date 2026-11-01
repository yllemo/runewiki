<?php
/**
 * templates/default/login.php
 *
 * Enkelt inloggningsformulär — visas vid /?do=login. Dit hamnar man
 * antingen via login-länken i headern, eller automatiskt om man försöker
 * redigera/spara/radera/ladda upp media utan att vara inloggad
 * (Wiki::requireLogin()). $redirectTo pekar tillbaka dit man kom ifrån.
 */
$strings = $strings ?? Helpers::defaultStrings();
?>
<div class="gbg-login">
    <h1><?= Helpers::e($strings['login_title']) ?></h1>

    <?php if ($error ?? null): ?>
        <p class="gbg-login-error"><?= Helpers::e($error) ?></p>
    <?php endif; ?>

    <?php if (!empty($externalAuth['oidc']['enabled'])): ?>
    <form class="gbg-form" method="post" action="/?do=login" style="margin-bottom:1.5rem">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="provider" value="oidc">
        <input type="hidden" name="redirect_to" value="<?= Helpers::e($redirectTo ?? '/') ?>">
        <button type="submit" class="gbg-btn gbg-btn-primary"><?= Helpers::e($externalAuth['oidc']['label']) ?></button>
    </form>
    <?php endif; ?>
    <?php if (!empty($externalAuth['ad']['enabled'])): ?>
    <h2><?= Helpers::e($externalAuth['ad']['label']) ?></h2>
    <form class="gbg-form" method="post" action="/?do=login" style="margin-bottom:1.5rem">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="provider" value="ad">
        <input type="hidden" name="redirect_to" value="<?= Helpers::e($redirectTo ?? '/') ?>">
        <label><span>AD-användarnamn (utan domän)</span><input type="text" name="username" autocomplete="username" required></label>
        <label><span>Lösenord</span><input type="password" name="password" autocomplete="current-password" required></label>
        <button type="submit" class="gbg-btn gbg-btn-primary">Logga in med AD</button>
    </form>
    <?php endif; ?>
    <?php if (!empty($externalAuth['oidc']['enabled']) || !empty($externalAuth['ad']['enabled'])): ?><h2>Lokalt wikikonto</h2><?php endif; ?>
    <form class="gbg-form" method="post" action="/?do=login">
        <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
        <input type="hidden" name="redirect_to" value="<?= Helpers::e($redirectTo ?? '/') ?>">

        <label>
            <span><?= Helpers::e($strings['login_username_label']) ?></span>
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>

        <label>
            <span><?= Helpers::e($strings['login_password_label']) ?></span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit" class="gbg-btn gbg-btn-primary"><?= Helpers::e($strings['login_submit']) ?></button>
    </form>
</div>
