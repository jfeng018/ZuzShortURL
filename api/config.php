<?php
if (defined('CONFIG_PHP_INCLUDED')) {
    return;
}
define('CONFIG_PHP_INCLUDED', true);

// Custom Session Handler Class
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $lifetime;

    public function __construct(PDO $pdo, int $lifetime = 1440) {
        $this->pdo = $pdo;
        $this->lifetime = $lifetime;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string {
        $stmt = $this->pdo->prepare("SELECT sess_data FROM sessions WHERE sess_id = ? AND sess_lifetime > EXTRACT(EPOCH FROM (NOW() - sess_time))");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['sess_data'] : '';
    }

    public function write($id, $data): bool {
        $stmt = $this->pdo->prepare("INSERT INTO sessions (sess_id, sess_data, sess_lifetime, sess_time) VALUES (?, ?, ?, NOW()) 
                                     ON CONFLICT (sess_id) DO UPDATE SET sess_data = EXCLUDED.sess_data, sess_lifetime = EXCLUDED.sess_lifetime, sess_time = NOW()");
        return $stmt->execute([$id, $data, $this->lifetime]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE sess_id = ?");
        $stmt->execute([$id]);
        return true;
    }

    public function gc($maxlifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE sess_lifetime < EXTRACT(EPOCH FROM (NOW() - sess_time))");
        $stmt->execute();
        return $stmt->rowCount();
    }
}

// Parse environment variables
$db_url = parse_url(getenv('DATABASE_URL'));
$host = $db_url['host'];
$port = $db_url['port'] ?? 5432;
$dbname = ltrim($db_url['path'], '/');
$user = $db_url['user'];
$pass = $db_url['pass'];
$admin_token = getenv('ADMIN_TOKEN');

// Database connection
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Create tables if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS short_links (
            id SERIAL PRIMARY KEY,
            shortcode VARCHAR(10) UNIQUE NOT NULL,
            longurl TEXT NOT NULL,
            user_id INT DEFAULT NULL,
            enable_intermediate_page BOOLEAN DEFAULT FALSE,
            expiration_date TIMESTAMP DEFAULT NULL,
            clicks INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("ALTER TABLE IF EXISTS short_links ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;");
    $pdo->exec("ALTER TABLE IF EXISTS short_links ADD COLUMN IF NOT EXISTS expiration_date TIMESTAMP DEFAULT NULL;");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            ip INET PRIMARY KEY,
            request_count INT DEFAULT 0,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            sess_id VARCHAR(128) PRIMARY KEY,
            sess_data TEXT NOT NULL,
            sess_lifetime INT NOT NULL,
            sess_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Reset sequence
    $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id) FROM short_links), 1), true);");
    $pdo->exec("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 1), true);");
    
    // Register DB Session Handler
    $handler = new DatabaseSessionHandler($pdo);
    session_set_save_handler($handler, true);
    session_start();
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Helper functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function check_rate_limit($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $max_requests = 120;
    $window_seconds = 60;

    $stmt_check_recent = $pdo->prepare("
        SELECT request_count 
        FROM rate_limits 
        WHERE ip = ? 
        AND window_start > NOW() - INTERVAL '1 minute'
    ");
    $stmt_check_recent->execute([$ip]);
    $row_recent = $stmt_check_recent->fetch(PDO::FETCH_ASSOC);

    if ($row_recent && (int)$row_recent['request_count'] >= $max_requests) {
        http_response_code(429);
        die('请求过于频繁，请稍后重试。');
    }

    $stmt_check_any = $pdo->prepare("SELECT 1 FROM rate_limits WHERE ip = ?");
    $stmt_check_any->execute([$ip]);
    $row_any = $stmt_check_any->fetch();

    if ($row_any) {
        if ($row_recent) {
            $stmt = $pdo->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE ip = ?");
            $stmt->execute([$ip]);
        } else {
            $stmt = $pdo->prepare("UPDATE rate_limits SET request_count = 1, window_start = NOW() WHERE ip = ?");
            $stmt->execute([$ip]);
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip, request_count, window_start) VALUES (?, 1, NOW())");
        $stmt->execute([$ip]);
    }
}

function generate_random_code($pdo, $reserved_codes) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
    $max_attempts = 100;
    $attempts = 0;
    do {
        $code = substr(str_shuffle($chars), 0, 5);
        if (in_array(strtolower($code), $reserved_codes)) continue;
        $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
        $stmt->execute([$code]);
        $attempts++;
    } while ($stmt->fetch() && $attempts < $max_attempts);
    if ($attempts >= $max_attempts) {
        throw new Exception('无法生成唯一短码');
    }
    return $code;
}

function validate_custom_code($code, $pdo, $reserved_codes) {
    if (strlen($code) < 5 || strlen($code) > 10) return '短码长度为5-10位';
    if (!preg_match('/^[A-Za-z0-9]+$/', $code)) return '短码仅限字母数字';
    if (in_array(strtolower($code), $reserved_codes)) return '短码被保留';
    $stmt = $pdo->prepare("SELECT id FROM short_links WHERE shortcode = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) return '短码已存在';
    return true;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function require_admin_auth() {
    global $admin_token;
    if (!isset($_SESSION['admin_auth']) || !hash_equals($_SESSION['admin_auth'], hash_hmac('sha256', $admin_token, session_id()))) {
        return false;
    }
    return true;
}

$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard'];

$domain = $_SERVER['HTTP_HOST'];
$protocol = 'https';
$base_url = $protocol . '://' . $domain;
?>