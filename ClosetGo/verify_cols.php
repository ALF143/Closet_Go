<?php
require_once __DIR__ . '/database/db_config.php';
$conn = (new Database())->getConnection();
foreach (['admin_users', 'system_staffs'] as $tbl) {
    echo "=== $tbl ===\n";
    $res = $conn->query("DESCRIBE $tbl");
    while ($r = $res->fetch_assoc()) {
        echo "{$r['Field']} ({$r['Type']})\n";
    }
}
unlink(__FILE__);
