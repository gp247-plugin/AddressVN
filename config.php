<?php
/**
 * Plugin defaults — overwritten on every update, so nothing here is meant to be edited
 * by the site owner. Which address fields the province / ward feed is chosen on the
 * shop's Address settings tab and kept in the database.
 */
return [
    // Data file of the administrative units shipped with the plugin (two levels since
    // 2025-07-01: province / city → ward / commune). Null = the bundled file.
    'catalog_path' => null,
];
