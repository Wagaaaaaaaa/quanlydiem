<?php
// 1. Khởi động session để truy cập các biến hiện tại
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Xóa sạch tất cả các biến lưu trong $_SESSION (user_id, role, username, user, loggedin...)
$_SESSION = array();

// 3. Xóa Cookie Session ở phía trình duyệt (Nếu có)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 4. Hủy bỏ hoàn toàn Session trên server
session_destroy();

// 5. Chuyển hướng người dùng về lại trang login
header("Location: login.php");
exit();
?>

