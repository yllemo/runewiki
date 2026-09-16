<?php
/**
 * templates/default/header.php
 *
 * Vit topphuvud (logotyp + sitenamn + tagline) + blå huvudmeny med
 * verktygsmeny (sök / tema / meny som dropdowns) + ljust brödsmulefält.
 * I stil med goteborg-dw-template (DokuWiki).
 */
$currentTarget = $currentId ?? '';
$currentView   = $view ?? 'page';
$showEditLink  = in_array($currentView, ['page', 'missing'], true) && !empty($pageId);
// $strings kommer från Wiki::baseData()/chat/index.php (Helpers::resolveStrings())
// — alla texter nedan kan skrivas över i config/strings.php.
$strings       = $strings ?? Helpers::defaultStrings();
$tagline       = $tagline ?? $strings['tagline'];
// $authEnabled/$currentUser kommer från Wiki::baseData() (styr login/logout-
// UI:t nedan) — defensiva fallbacks ifall en vy renderar header.php utan dem.
$authEnabled   = $authEnabled ?? false;
$currentUser   = $currentUser ?? null;
// $headerLogo/$headerLogoDark kommer från Wiki::baseData()/chat/index.php/
// admin/index.php (config.php:s 'header_logo'/'header_logo_dark') — byts
// enklast via /admin/ ("Webbplats"). Headern byter bakgrund med ljust/
// mörkt läge, så båda loggorna renderas och assets/js/theme.js växlar
// vilken som visas (samma teknik som sol/måne-ikonen nedan) — lämnas
// 'header_logo_dark' tom visas samma logga i båda lägena.
$headerLogo     = $headerLogo ?? 'img/logo.svg';
$headerLogoDark = ($headerLogoDark ?? '') !== '' ? $headerLogoDark : $headerLogo;

// Brödsmulor byggs från sid-ID:t, t.ex. "namespace:sida:underrsida".
// Visas bara för faktiska sidvyer (inte sök/media/redigera/fel, som
// alla använder ett fast platshållar-ID internt).
$crumbParts = ($showEditLink && $currentTarget !== '') ? explode(':', $currentTarget) : [];
?>
<header class="gbg-topbar">
    <div class="gbg-container gbg-topbar-inner">
        <a class="gbg-brand" href="/">
            <img class="gbg-logo gbg-logo-light" src="<?= Helpers::e($assetUrl($headerLogo)) ?>" alt="<?= Helpers::e($siteName) ?>">
            <img class="gbg-logo gbg-logo-dark" src="<?= Helpers::e($assetUrl($headerLogoDark)) ?>" alt="<?= Helpers::e($siteName) ?>" hidden>
            <span class="gbg-brand-text">
                <span class="gbg-site-title"><?= Helpers::e($siteName) ?></span>
                <span class="gbg-tagline"><?= Helpers::e($tagline) ?></span>
            </span>
        </a>
    </div>
</header>

<nav class="gbg-navbar">
    <div class="gbg-container gbg-navbar-inner">
        <?php if (!empty($menu)): ?>
        <button type="button" class="gbg-nav-toggle" id="gbg-nav-toggle" aria-expanded="false" aria-controls="gbg-nav-links" aria-label="<?= Helpers::e($strings['nav_toggle_label']) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
        </button>
        <ul class="gbg-nav-links" id="gbg-nav-links">
            <?php foreach ($menu as $item): ?>
                <?php
                $target = $item['target'] ?? '';
                // Extern URL eller absolut sökväg (t.ex. från _topbar.md) används
                // som den är — annars tolkas target som ett sid-ID.
                $href = (preg_match('#^https?://#i', $target) || str_starts_with($target, '/'))
                    ? $target
                    : '/' . str_replace(':', '/', $target);
                ?>
                <li>
                    <a href="<?= Helpers::e($href) ?>"
                       <?= ($currentTarget === $target) ? 'class="is-active"' : '' ?>>
                        <?= Helpers::e($item['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="gbg-tools">
            <!-- AI Chat — fristående, synlig knapp (inte gömd i menyn) så man
                 kan chatta med den öppna sidan med ett enda klick. -->
            <a class="gbg-ai-chat-btn"
               href="<?= Helpers::e($showEditLink ? '/chat?doc=' . urlencode($pageId->id()) : '/chat') ?>"
               title="<?= Helpers::e($showEditLink ? $strings['chat_tooltip_page'] : $strings['chat_tooltip_generic']) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span class="gbg-ai-chat-label"><?= Helpers::e($strings['chat_link']) ?></span>
            </a>

            <!-- Sök -->
            <div class="gbg-dropdown" data-dropdown="search">
                <button type="button" class="gbg-tool-btn" aria-haspopup="true" aria-expanded="false" title="<?= Helpers::e($strings['nav_search']) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <span class="gbg-sr-only"><?= Helpers::e($strings['nav_search']) ?></span>
                </button>
                <div class="gbg-dropdown-menu gbg-dropdown-menu--search">
                    <form class="gbg-search-form" action="/" method="get">
                        <input type="hidden" name="do" value="search">
                        <input type="search" name="q" placeholder="<?= Helpers::e($strings['search_placeholder']) ?>" aria-label="<?= Helpers::e($strings['nav_search']) ?>"
                               value="<?= isset($_GET['q']) ? Helpers::e($_GET['q']) : '' ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="gbg-search-suggestions" aria-describedby="gbg-search-status">
                        <button type="submit"><?= Helpers::e($strings['search_button']) ?></button>
                    </form>
                    <div id="gbg-search-suggestions" role="listbox" aria-label="Sidförslag" hidden></div>
                    <p id="gbg-search-status" class="gbg-search-status" role="status">Skriv för att hitta sidor. Välj med ↓ ↑ och Enter.</p>
                </div>
            </div>

            <?php if ($currentUser): ?>
                <span class="gbg-user-badge" title="<?= Helpers::e(Helpers::interpolate($strings['logged_in_as'], ['user' => $currentUser])) ?>">
                    <svg class="gbg-user-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.58-7 8-7s8 3 8 7"/></svg>
                    <span class="gbg-user-name"><?= Helpers::e($currentUser) ?></span>
                </span>
            <?php endif; ?>

            <!-- Meny (redigera / tema / admin / logga in-ut m.m.) — samlar de
                 mer sällan använda verktygsknapparna under en "..."-meny så
                 verktygsraden hålls kompakt. Sök och AI Chat är egna, synliga
                 knappar (se ovan) eftersom de används oftast. -->
            <div class="gbg-dropdown" data-dropdown="menu">
                <button type="button" class="gbg-tool-btn" aria-haspopup="true" aria-expanded="false" title="<?= Helpers::e($strings['menu_label']) ?>">
                    <!-- Tre punkter (kebab-meny) istället för hamburgarlinjer — skiljer den
                         från huvudnavigeringens hamburgare (.gbg-nav-toggle) på mobil. -->
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.75" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.75" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.75" fill="currentColor" stroke="none"/></svg>
                    <span class="gbg-sr-only"><?= Helpers::e($strings['menu_label']) ?></span>
                </button>
                <div class="gbg-dropdown-menu">
                    <?php if ($showEditLink): ?>
                        <a class="gbg-dropdown-item" href="<?= Helpers::e($pageId->editUrl()) ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            <?= Helpers::e($strings['edit_link']) ?>
                        </a>
                    <?php endif; ?>
                    <a class="gbg-dropdown-item" href="/?do=search">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <?= Helpers::e($strings['nav_search']) ?>
                    </a>
                    <a class="gbg-dropdown-item" href="/images">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
                        <?= Helpers::e($strings['nav_media']) ?>
                    </a>
                    <!-- AI Chat har en egen synlig knapp i .gbg-tools (se ovan)
                         istället för att gömmas här. -->

                    <!-- Ljust/mörkt läge -->
                    <button type="button" class="gbg-dropdown-item" id="gbg-theme-toggle">
                        <svg class="gbg-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
                        <svg class="gbg-icon-sun" viewBox="0 0 24 24" aria-hidden="true" hidden><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
                        <?= Helpers::e($strings['theme_toggle_label']) ?>
                    </button>

                    <?php if ($authEnabled || $currentUser): ?>
                        <?php if ($currentUser): ?>
                            <a class="gbg-dropdown-item" href="/admin/">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                Adminpanel
                            </a>
                            <form class="gbg-dropdown-form" method="post" action="/?do=logout">
                                <input type="hidden" name="csrf_token" value="<?= Helpers::e(Helpers::csrfToken()) ?>">
                                <button type="submit" class="gbg-dropdown-item">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    <?= Helpers::e($strings['logout_link']) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a class="gbg-dropdown-item" href="/?do=login&redirect_to=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                <?= Helpers::e($strings['login_link']) ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<?php if (!empty($crumbParts)): ?>
<div class="gbg-breadcrumbs">
    <div class="gbg-container gbg-breadcrumb-trail">
        <a href="/"><?= Helpers::e($strings['breadcrumb_home']) ?></a>
        <?php foreach ($crumbParts as $i => $part): ?>
            <span class="gbg-breadcrumb-sep">›</span>
            <?php if ($i === array_key_last($crumbParts)): ?>
                <span class="gbg-breadcrumb-current"><?= Helpers::e($page['title'] ?? ucfirst(str_replace(['_', '-'], ' ', $part))) ?></span>
            <?php else: ?>
                <span><?= Helpers::e(ucfirst(str_replace(['_', '-'], ' ', $part))) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
