<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'quanlydiem';
$user = 'root';
$pass = ''; // Mật khẩu mặc định của XAMPP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// 0. BẢO VỆ CSRF CHO MỌI FORM POST
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token() {
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf) || $csrf === '' || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        die('Yêu cầu không hợp lệ (CSRF token). Vui lòng tải lại trang và thử lại.');
    }
}

// 1. HÀM KIỂM TRA ĐĂNG NHẬP & CHỐNG BẢO MẬT URL
if (!function_exists('checkLogin')) {
    function checkLogin() {
        // Xóa sạch Cache để chặn nút Back trình duyệt khi đã logout
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");

        // Nếu chưa đăng nhập
        if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
            // Lưu lại đường dẫn người dùng vừa paste vào trình duyệt
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '';
            
            // Đẩy về đúng file login.php ở thư mục gốc quanlydiem
            header('Location: /quanlydiem/login.php');
            exit(); // Bắt buộc phải có exit() để ngắt mã bên dưới
        }
    }
}

// 3. KIỂM TRA QUYỀN BỔ SUNG (admin cấp thêm: sửa/xóa từng phạm vi)
if (!function_exists('hasExtraPerm')) {
    function hasExtraPerm(PDO $pdo, string $perm): bool {
        $role = strtolower($_SESSION['user']['quyen'] ?? $_SESSION['role'] ?? '');
        if ($role === 'admin') {
            return true; // Admin luôn có toàn quyền
        }
        $uid = $_SESSION['user']['id'] ?? null;
        if (!$uid) {
            return false;
        }
        try {
            // Đảm bảo cột extra_perms tồn tại (JSON danh sách quyền được cấp thêm)
            try {
                $pdo->query("SELECT extra_perms FROM users LIMIT 1");
            } catch (PDOException $e) {
                $pdo->exec("ALTER TABLE users ADD COLUMN extra_perms TEXT NULL");
            }
            $stmt = $pdo->prepare("SELECT extra_perms FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $raw = $stmt->fetchColumn();
            $list = $raw ? json_decode($raw, true) : [];
            return is_array($list) && in_array($perm, $list, true);
        } catch (PDOException $e) {
            return false;
        }
    }
}

// 2. HÀM KIỂM TRA QUYỀN
if (!function_exists('checkRole')) {
    function checkRole($allowed_roles = []) {
        // Gọi checkLogin trước
        checkLogin();
        
        $user_role = $_SESSION['user']['quyen'] ?? $_SESSION['role'] ?? '';
        
        if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
            echo "<script>
                alert('Bạn không có quyền truy cập vào chức năng này!');
                window.location.href = '/quanlydiem/index.php';
            </script>";
            exit();
        }
    }
}

