
<?php
/**
 * Database Backup & Restore Handler
 * ---------------------------------
 * Pure-PHP implementation (no mysqldump / exec needed) so it works on both
 * XAMPP localhost and shared hosting (ProFreeHost / Ezyro).
 *
 * Actions:
 *   POST action=backup         mode=download|save   -> create .sql backup
 *   GET  action=download       file=<name>          -> download a saved backup
 *   POST action=restore_upload backup_file=<upload> -> restore from uploaded .sql / .sql.gz
 *   POST action=restore_server file=<name>          -> restore from a saved server backup
 *   POST action=delete         file=<name>          -> delete a saved backup
 */
session_start();

// Admin only
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: adminlogin.php');
    exit();
}

require_once '../database/db_config.php';
$db = new Database();
$conn = $db->getConnection();

@set_time_limit(0);
@ini_set('memory_limit', '512M');

define('CG_BACKUP_DIR', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function cg_redirect($type, $message) {
    $_SESSION[$type === 'success' ? 'success_message' : 'error_message'] = $message;
    header('Location: settings.php');
    exit();
}

/** Make sure the backup folder exists and is NOT publicly downloadable. */
function cg_ensure_backup_dir() {
    if (!is_dir(CG_BACKUP_DIR)) {
        @mkdir(CG_BACKUP_DIR, 0755, true);
    }
    $htaccess = CG_BACKUP_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "# Block direct web access to database backups\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    }
    $index = CG_BACKUP_DIR . 'index.html';
    if (!file_exists($index)) {
        @file_put_contents($index, '');
    }
    return is_dir(CG_BACKUP_DIR) && is_writable(CG_BACKUP_DIR);
}

/** Validate a backup filename (prevents path traversal). Returns full path or false. */
function cg_safe_backup_path($name) {
    $name = basename((string)$name);
    if (!preg_match('/^[A-Za-z0-9_\-\.]+\.sql$/', $name)) {
        return false;
    }
    $path = CG_BACKUP_DIR . $name;
    return is_file($path) ? $path : false;
}

function cg_check_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['backup_csrf']) || !hash_equals($_SESSION['backup_csrf'], $token)) {
        cg_redirect('error', 'Security token expired. Please refresh the page and try again.');
    }
}

/** Convert a single column value into a SQL literal. */
function cg_sql_value($conn, $value, $field) {
    if ($value === null) {
        return 'NULL';
    }

    $numericTypes = [
        MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_INT24,
        MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL,
        MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_YEAR,
    ];
    if (in_array($field->type, $numericTypes, true) && is_numeric($value)) {
        return $value;
    }

    // Binary data (charset 63 = binary) -> hex literal so it survives encoding
    $binaryTypes = [
        MYSQLI_TYPE_TINY_BLOB, MYSQLI_TYPE_MEDIUM_BLOB, MYSQLI_TYPE_LONG_BLOB,
        MYSQLI_TYPE_BLOB, MYSQLI_TYPE_STRING, MYSQLI_TYPE_VAR_STRING,
    ];
    if (isset($field->charsetnr) && (int)$field->charsetnr === 63 && in_array($field->type, $binaryTypes, true)) {
        return $value === '' ? "''" : '0x' . bin2hex($value);
    }

    return "'" . $conn->real_escape_string($value) . "'";
}

/**
 * Write a full SQL dump of the current database to the given file path.
 * Returns ['tables' => int, 'rows' => int].
 */
function cg_dump_database($conn, $filePath) {
    $fh = fopen($filePath, 'wb');
    if (!$fh) {
        throw new Exception('Unable to create backup file. Check folder permissions for /backups.');
    }

    $dbRes = $conn->query('SELECT DATABASE() AS db');
    $dbName = $dbRes ? ($dbRes->fetch_assoc()['db'] ?? '') : '';

    fwrite($fh, "-- ------------------------------------------------------------\n");
    fwrite($fh, "-- ClosetGoo Database Backup\n");
    fwrite($fh, "-- Database   : `{$dbName}`\n");
    fwrite($fh, "-- Generated  : " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "-- Server     : MySQL " . $conn->server_info . "\n");
    fwrite($fh, "-- ------------------------------------------------------------\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\n");
    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 0;\n");
    fwrite($fh, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($fh, "SET time_zone = '+00:00';\n\n");

    $tables = [];
    $views = [];
    $res = $conn->query('SHOW FULL TABLES');
    if ($res) {
        while ($row = $res->fetch_row()) {
            if (isset($row[1]) && strtoupper($row[1]) === 'VIEW') {
                $views[] = $row[0];
            } else {
                $tables[] = $row[0];
            }
        }
        $res->free();
    }

    $totalRows = 0;

    foreach ($tables as $table) {
        $qt = '`' . str_replace('`', '``', $table) . '`';

        $createRes = $conn->query("SHOW CREATE TABLE {$qt}");
        if (!$createRes) continue;
        $createRow = $createRes->fetch_row();
        $createRes->free();

        fwrite($fh, "-- ------------------------------------------------------------\n");
        fwrite($fh, "-- Table structure for {$qt}\n");
        fwrite($fh, "-- ------------------------------------------------------------\n");
        fwrite($fh, "DROP TABLE IF EXISTS {$qt};\n");
        fwrite($fh, $createRow[1] . ";\n\n");

        // Unbuffered read keeps memory low for big tables
        $dataRes = $conn->query("SELECT * FROM {$qt}", MYSQLI_USE_RESULT);
        if (!$dataRes) continue;

        $fields = $dataRes->fetch_fields();
        $colList = implode(', ', array_map(function ($f) {
            return '`' . str_replace('`', '``', $f->name) . '`';
        }, $fields));

        $batch = [];
        $batchSize = 100;
        $wroteHeader = false;

        while ($row = $dataRes->fetch_row()) {
            if (!$wroteHeader) {
                fwrite($fh, "-- Data for {$qt}\n");
                $wroteHeader = true;
            }
            $vals = [];
            foreach ($row as $i => $v) {
                $vals[] = cg_sql_value($conn, $v, $fields[$i]);
            }
            $batch[] = '(' . implode(', ', $vals) . ')';
            $totalRows++;

            if (count($batch) >= $batchSize) {
                fwrite($fh, "INSERT INTO {$qt} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if (!empty($batch)) {
            fwrite($fh, "INSERT INTO {$qt} ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        $dataRes->free();
        fwrite($fh, "\n");
    }

    foreach ($views as $view) {
        $qv = '`' . str_replace('`', '``', $view) . '`';
        $vRes = $conn->query("SHOW CREATE VIEW {$qv}");
        if (!$vRes) continue;
        $vRow = $vRes->fetch_row();
        $vRes->free();
        // Strip DEFINER so the view restores on any host/user
        $createView = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/i', '', $vRow[1]);
        fwrite($fh, "-- View {$qv}\n");
        fwrite($fh, "DROP VIEW IF EXISTS {$qv};\n");
        fwrite($fh, $createView . ";\n\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($fh, "-- End of backup\n");
    fclose($fh);

    return ['tables' => count($tables) + count($views), 'rows' => $totalRows];
}

/**
 * Split an SQL script into individual statements.
 * Handles quotes, escapes, comments and DELIMITER changes.
 */
function cg_split_sql($sql) {
    // Strip UTF-8 BOM
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }
    $sql = str_replace("\r\n", "\n", $sql);

    $statements = [];
    $current = '';
    $delimiter = ';';
    $len = strlen($sql);
    $inString = false;
    $quoteChar = '';
    $i = 0;

    while ($i < $len) {
        $ch = $sql[$i];

        if ($inString) {
            $current .= $ch;
            if ($ch === '\\' && $quoteChar !== '`' && $i + 1 < $len) {
                $current .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            if ($ch === $quoteChar) {
                // Doubled quote ('' or "") is an escaped quote
                if ($i + 1 < $len && $sql[$i + 1] === $quoteChar) {
                    $current .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                $inString = false;
            }
            $i++;
            continue;
        }

        // Line start: check for DELIMITER command
        $atLineStart = ($i === 0 || $sql[$i - 1] === "\n");
        if ($atLineStart && trim($current) === '' && strncasecmp(substr($sql, $i, 10), 'DELIMITER ', 10) === 0) {
            $eol = strpos($sql, "\n", $i);
            if ($eol === false) $eol = $len;
            $delimiter = trim(substr($sql, $i + 10, $eol - $i - 10));
            if ($delimiter === '') $delimiter = ';';
            $current = '';
            $i = $eol + 1;
            continue;
        }

        // -- comment / # comment
        if (($ch === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) || $ch === '#') {
            $eol = strpos($sql, "\n", $i);
            $i = ($eol === false) ? $len : $eol + 1;
            $current .= "\n";
            continue;
        }

        // /* block comment */  (keep MySQL conditional /*! ... */ comments)
        if ($ch === '/' && substr($sql, $i, 2) === '/*' && substr($sql, $i, 3) !== '/*!') {
            $end = strpos($sql, '*/', $i + 2);
            $i = ($end === false) ? $len : $end + 2;
            $current .= ' ';
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $inString = true;
            $quoteChar = $ch;
            $current .= $ch;
            $i++;
            continue;
        }

        if ($ch === $delimiter[0] && substr($sql, $i, strlen($delimiter)) === $delimiter) {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            $i += strlen($delimiter);
            continue;
        }

        $current .= $ch;
        $i++;
    }

    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }
    return $statements;
}

/** Execute an SQL script. Returns ['executed' => int, 'errors' => string[]]. */
function cg_restore_sql($conn, $sql) {
    $statements = cg_split_sql($sql);
    if (empty($statements)) {
        throw new Exception('The backup file is empty or contains no SQL statements.');
    }

    $executed = 0;
    $errors = [];

    try { $conn->query('SET FOREIGN_KEY_CHECKS = 0'); } catch (Throwable $e) {}

    foreach ($statements as $stmt) {
        try {
            if ($conn->query($stmt) === false) {
                $errors[] = $conn->error . ' :: ' . mb_substr($stmt, 0, 120);
            } else {
                $executed++;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage() . ' :: ' . mb_substr($stmt, 0, 120);
        }
        // Flush any pending multi-results
        while ($conn->more_results() && $conn->next_result()) { /* discard */ }
    }

    try { $conn->query('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $e) {}

    return ['executed' => $executed, 'errors' => $errors, 'total' => count($statements)];
}

function cg_new_backup_name($prefix = 'backup') {
    return 'closetgoo_' . $prefix . '_' . date('Y-m-d_His') . '.sql';
}

function cg_format_restore_result($result, $sourceLabel, $safetyName) {
    $msg = "Database restored from <strong>" . htmlspecialchars($sourceLabel) . "</strong> — "
         . number_format($result['executed']) . " of " . number_format($result['total']) . " statements executed.";
    if ($safetyName) {
        $msg .= " A safety backup of the previous data was saved as <strong>" . htmlspecialchars($safetyName) . "</strong>.";
    }
    return $msg;
}

/** Restore + automatic safety backup + messaging. */
function cg_do_restore($conn, $sql, $sourceLabel) {
    // Safety net: snapshot the current DB before overwriting it
    $safetyName = null;
    if (cg_ensure_backup_dir()) {
        $safetyName = cg_new_backup_name('pre_restore');
        try {
            cg_dump_database($conn, CG_BACKUP_DIR . $safetyName);
        } catch (Throwable $e) {
            $safetyName = null;
        }
    }

    $result = cg_restore_sql($conn, $sql);

    if (empty($result['errors'])) {
        cg_redirect('success', cg_format_restore_result($result, $sourceLabel, $safetyName));
    }

    $errCount = count($result['errors']);
    $firstErr = htmlspecialchars(mb_substr($result['errors'][0], 0, 220));
    $msg = cg_format_restore_result($result, $sourceLabel, $safetyName)
         . " However, {$errCount} statement(s) failed. First error: <em>{$firstErr}</em>";
    cg_redirect($result['executed'] > 0 ? 'success' : 'error', $msg);
}

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'backup':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_redirect('error', 'Invalid request.');
            cg_check_csrf();
            if (!cg_ensure_backup_dir()) {
                cg_redirect('error', 'The /backups folder could not be created or is not writable.');
            }

            $mode = $_POST['mode'] ?? 'download';
            $fileName = cg_new_backup_name('backup');
            $filePath = CG_BACKUP_DIR . $fileName;
            $stats = cg_dump_database($conn, $filePath);

            if ($mode === 'save') {
                cg_redirect('success', "Backup <strong>{$fileName}</strong> saved on the server ("
                    . $stats['tables'] . " tables, " . number_format($stats['rows']) . " rows).");
            }

            // Stream file as a download
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            while (ob_get_level()) ob_end_clean();
            readfile($filePath);
            exit();

        case 'download':
            $path = cg_safe_backup_path($_GET['file'] ?? '');
            if (!$path) cg_redirect('error', 'Backup file not found.');
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            while (ob_get_level()) ob_end_clean();
            readfile($path);
            exit();

        case 'restore_upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_redirect('error', 'Invalid request.');
            cg_check_csrf();
            if (strtoupper(trim($_POST['confirm_text'] ?? '')) !== 'RESTORE') {
                cg_redirect('error', 'Restore cancelled: please type RESTORE to confirm.');
            }

            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                $code = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
                $reason = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload_max_filesize limit (' . ini_get('upload_max_filesize') . ').',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds the allowed form size.',
                    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_FILE    => 'No file was selected.',
                ][$code] ?? 'Upload failed (code ' . $code . ').';
                cg_redirect('error', 'Restore failed: ' . $reason);
            }

            $origName = $_FILES['backup_file']['name'];
            $lower = strtolower($origName);
            $raw = file_get_contents($_FILES['backup_file']['tmp_name']);

            if (substr($lower, -7) === '.sql.gz' || substr($lower, -3) === '.gz') {
                if (!function_exists('gzdecode')) cg_redirect('error', 'This server cannot read .gz files. Upload an uncompressed .sql file.');
                $raw = @gzdecode($raw);
                if ($raw === false) cg_redirect('error', 'The .gz file is corrupted or invalid.');
            } elseif (substr($lower, -4) !== '.sql') {
                cg_redirect('error', 'Invalid file type. Please upload a .sql or .sql.gz backup file.');
            }

            cg_do_restore($conn, $raw, $origName);
            break;

        case 'restore_server':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_redirect('error', 'Invalid request.');
            cg_check_csrf();
            $path = cg_safe_backup_path($_POST['file'] ?? '');
            if (!$path) cg_redirect('error', 'Backup file not found.');
            cg_do_restore($conn, file_get_contents($path), basename($path));
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') cg_redirect('error', 'Invalid request.');
            cg_check_csrf();
            $path = cg_safe_backup_path($_POST['file'] ?? '');
            if (!$path) cg_redirect('error', 'Backup file not found.');
            if (@unlink($path)) {
                cg_redirect('success', 'Backup <strong>' . htmlspecialchars(basename($path)) . '</strong> deleted.');
            }
            cg_redirect('error', 'Unable to delete the backup file.');
            break;

        default:
            cg_redirect('error', 'Unknown backup action.');
    }
} catch (Throwable $e) {
    cg_redirect('error', 'Backup/Restore error: ' . htmlspecialchars($e->getMessage()));
}
