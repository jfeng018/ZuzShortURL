<?php
if (defined('CONFIG_PHP_INCLUDED')) {
    return;
}
define('CONFIG_PHP_INCLUDED', true);

ob_start('ob_gzhandler');

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
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass, [PDO::ATTR_PERSISTENT => true]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die('数据库连接失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$handler = new DatabaseSessionHandler($pdo);
session_set_save_handler($handler, true);

ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 1000);

session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$settings_stmt = $pdo->query("SELECT \"key\", value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['key']] = $row['value'];
}

$reserved_codes = ['admin', 'help', 'about', 'api', 'login', 'register', 'logout', 'dashboard', 'migrate'];

$current_domain = $_SERVER['HTTP_HOST'];
$official_domain = $settings['official_domain'] ?? $current_domain;
$enable_dual_domain = ($settings['enable_dual_domain'] ?? 'false') === 'true';
$short_domain = $enable_dual_domain ? ($settings['short_domain'] ?? $current_domain) : $official_domain;

$protocol = 'http';
if (isset($_SERVER['HTTP_IN_DOCKER_HTTPS']) && $_SERVER['HTTP_IN_DOCKER_HTTPS'] === 'true') {
    $protocol = 'https';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $protocol = 'https';
}

$official_url = $protocol . '://' . $official_domain;
$short_domain_url = $protocol . '://' . $short_domain;
$base_url = $short_domain_url;
?>