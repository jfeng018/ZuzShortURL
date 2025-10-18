<?php
if (defined('CONFIG_PHP_INCLUDED')) {
    return;
}
define('CONFIG_PHP_INCLUDED', true);

require_once __DIR__ . '/../vendor/autoload.php';

use Chillerlan\QRCode\QRCode;
use Chillerlan\QRCode\QROptions;

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

$db_url = parse_url(getenv('DATABASE_URL'));
$host = $db_url['host'];
$port = $db_url['port'] ?? 5432;
$dbname = ltrim($db_url['path'], '/');
$user = $db_url['user'];
$pass = $db_url['pass'];
$admin_token = getenv('ADMIN_TOKEN');

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS short_links (
        id SERIAL PRIMARY KEY,
        shortcode VARCHAR(10) UNIQUE NOT NULL,
        longurl TEXT NOT NULL,
        user_id INT DEFAULT NULL,
        enable_intermediate_page BOOLEAN DEFAULT FALSE,
        redirect_delay INT DEFAULT 0,
        link_password TEXT DEFAULT NULL,
        expiration_date TIMESTAMP DEFAULT NULL,
        clicks INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL;");
    $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS expiration_date TIMESTAMP DEFAULT NULL;");
    $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS redirect_delay INT DEFAULT 0;");
    $pdo->exec("ALTER TABLE short_links ADD COLUMN IF NOT EXISTS link_password TEXT DEFAULT NULL;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        ip INET PRIMARY KEY,
        request_count INT DEFAULT 0,
        window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        sess_id VARCHAR(128) PRIMARY KEY,
        sess_data TEXT NOT NULL,
        sess_lifetime INT NOT NULL,
        sess_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        \"key\" VARCHAR(50) PRIMARY KEY,
        value BOOLEAN DEFAULT true
    )");
    $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('allow_guest', false) ON CONFLICT (\"key\") DO NOTHING");
    $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('allow_register', true) ON CONFLICT (\"key\") DO NOTHING");
    $pdo->exec("INSERT INTO settings (\"key\", value) VALUES ('private_mode', false) ON CONFLICT (\"key\") DO NOTHING");
    $pdo->exec("SELECT setval('short_links_id_seq', COALESCE((SELECT MAX(id) FROM short_links), 1), true);");
    $pdo->exec("SELECT setval('users_id_seq', COALESCE((SELECT MAX(id) FROM users), 1), true);");
    
    $handler = new DatabaseSessionHandler($pdo);
    session_set_save_handler($handler, true);
    session_start();
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard'];

$domain = $_SERVER['HTTP_HOST'];
$protocol = 'https';
$base_url = $protocol . '://' . $domain;
?>