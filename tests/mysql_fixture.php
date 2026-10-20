<?php
/** Disposable, empty local database. Never create fixtures on a remote configured host. */
function sena_test_database(PDO $pdo,string $host): void {
    if(!in_array($host,['localhost','127.0.0.1'],true))throw new RuntimeException('Tests require a local MySQL host');
    $source=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $test='sena_test_'.bin2hex(random_bytes(8));
    $pdo->exec("CREATE DATABASE `$test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    register_shutdown_function(static function()use($pdo,$test){$pdo->exec("DROP DATABASE IF EXISTS `$test`");});
    $pdo->exec("USE `$test`");
    foreach(['equipment_registry','inspection_items','asset_categories','registry_source_sheets'] as $table)
        $pdo->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");
}
