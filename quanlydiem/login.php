<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// Chỉ chuyển hướng về index.php nếu đã đăng nhập thành công
$is_already_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['user']) || !empty($_SESSION['loggedin']);
if ($is_already_logged_in) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten = trim($_POST['ten'] ?? '');
    $matkhau = trim($_POST['matkhau'] ?? '');

    if (!empty($ten) && !empty($matkhau)) {
        // Lấy thông tin user dựa trên tên đăng nhập
        $stmt = $pdo->prepare("SELECT * FROM users WHERE ten = ? LIMIT 1");
        $stmt->execute([$ten]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $is_valid = false;

            // 1. Kiểm tra mật khẩu mã hóa (hash)
            if (password_verify($matkhau, $user['matkhau'])) {
                $is_valid = true;
            }
            // 2. Hỗ trợ tài khoản cũ còn lưu mật khẩu thô -> đối chiếu an toàn rồi NÂNG CẤP NGAY
            else if (hash_equals((string)$user['matkhau'], (string)$matkhau)) {
                $is_valid = true;

                // Tự động nâng cấp mật khẩu sang dạng Hash trong CSDL
                $new_hash = password_hash($matkhau, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET matkhau = ? WHERE id = ?");
                $update_stmt->execute([$new_hash, $user['id']]);
            }

            if ($is_valid) {
                // Chống Session Fixation
                session_regenerate_id(true);

                // Lấy ID chuẩn của user
                $user_id = $user['id'] ?? $user['MaUser'] ?? $user['ma_user'] ?? $user['ten'];

                // ĐỒNG BỘ ĐẦY ĐỦ TẤT CẢ BIẾN SESSION MÀ INDEX.PHP CẦN
                $_SESSION['user_id']  = $user_id;
                $_SESSION['user']     = $user; // Lưu toàn bộ mảng dữ liệu user
                $_SESSION['username'] = $user['ten'];
                $_SESSION['role']     = $user['quyen'] ?? $user['role'] ?? 'user';
                $_SESSION['loggedin'] = true;

                // Xử lý chuyển hướng sau khi đăng nhập thành công
                $redirect_target = 'index.php';
                if (!empty($_SESSION['redirect_url'])) {
                    $target = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);

                    if (filter_var($target, FILTER_VALIDATE_URL) === false && strpos($target, '//') !== 0) {
                        $redirect_target = $target;
                    }
                }

                header("Location: " . $redirect_target);
                exit();
            } else {
                $_SESSION['error_flash'] = "Tài khoản hoặc mật khẩu không chính xác!";
                $_SESSION['old_ten'] = $ten;
            }
        } else {
            $_SESSION['error_flash'] = "Tài khoản hoặc mật khẩu không chính xác!";
            $_SESSION['old_ten'] = $ten;
        }
    } else {
        $_SESSION['error_flash'] = "Vui lòng nhập đầy đủ thông tin!";
        $_SESSION['old_ten'] = $ten;
    }

    // Chuyển hướng lại login.php theo chuẩn PRG
    header("Location: login.php");
    exit();
}

// Lấy thông báo lỗi từ Session Flash và xóa ngay
$error = $_SESSION['error_flash'] ?? '';
$old_ten = $_SESSION['old_ten'] ?? '';
unset($_SESSION['error_flash'], $_SESSION['old_ten']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Đăng Nhập Hệ Thống</title>
    <!-- Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Style CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="login-card">
        <div class="brand-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="login-header">
            <h2>Đăng Nhập</h2>
            <p>Vui lòng nhập thông tin tài khoản của bạn</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?php csrf_field(); ?>
            <div class="form-group">
                <label for="ten">Tên đăng nhập</label>
                <div class="input-wrapper">
                    <input type="text" id="ten" name="ten" placeholder="Nhập tên đăng nhập" required autocomplete="username" value="<?= htmlspecialchars($old_ten, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa-regular fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="matkhau">Mật khẩu</label>
                <div class="input-wrapper">
                    <input type="password" id="matkhau" name="matkhau" placeholder="Nhập mật khẩu" required autocomplete="current-password">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Hiện/Ẩn mật khẩu">
                        <i class="fa-regular fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <span>Đăng nhập</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="footer-text">
            &copy; <?= date('Y') ?> Hệ thống Quản trị. Bảo mật & An toàn.
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('#togglePassword');
            const passwordInput = document.querySelector('#matkhau');
            const eyeIcon = document.querySelector('#eyeIcon');

            if (togglePassword && passwordInput && eyeIcon) {
                togglePassword.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    
                    eyeIcon.classList.toggle('fa-eye', !isPassword);
                    eyeIcon.classList.toggle('fa-eye-slash', isPassword);
                });
            }
        });
    </script>
</body>
</html>

