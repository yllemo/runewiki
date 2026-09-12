<?php
/**
 * config/menu.php
 *
 * Huvudnavigering, renderas av templates/<tema>/header.php.
 * 'target' är ett page-id (namespace:page) eller en extern URL.
 */

return [
    ['label' => 'Start', 'target' => 'start'],
    ['label' => 'Hjälp',  'target' => 'hjalp:syntax'],
    // ['label' => 'Om wikin', 'target' => 'https://exempel.se'],
];
