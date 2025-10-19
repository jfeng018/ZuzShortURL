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

$db_url_str = getenv('DATABASE_URL') ?: '';
if (empty($db_url_str)) {
    http_response_code(500);
    die('DATABASE_URL 环境变量未设置');
}
$db_url = parse_url($db_url_str);
if (!$db_url || empty($db_url['host']) || empty($db_url['path'])) {
    http_response_code(500);
    die('DATABASE_URL 环境变量无效');
}
$host = $db_url['host'];
$port = $db_url['port'] ?? 5432;
$dbname = ltrim($db_url['path'], '/');
$user = $db_url['user'] ?? '';
$pass = $db_url['pass'] ?? '';
$admin_token = getenv('ADMIN_TOKEN') ?: '';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$handler = new DatabaseSessionHandler($pdo);
session_set_save_handler($handler, true);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard', 'migrate'];

$domain = $_SERVER['HTTP_HOST'];
$protocol = 'https';
$base_url = $protocol . '://' . $domain;
?>