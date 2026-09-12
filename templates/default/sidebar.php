<?php
/**
 * templates/default/sidebar.php
 * Sidopanel: renderar $sidebarHtml (från namespacets _sidebar.md) i kortstil.
 * Inkluderas av layout.php endast när $sidebarHtml inte är tomt.
 */
?>
<aside class="gbg-sidebar">
    <div class="gbg-card gbg-sidebar-card">
        <?= $sidebarHtml ?? '' ?>
    </div>
</aside>
