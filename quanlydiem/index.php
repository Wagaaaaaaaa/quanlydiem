<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

// Kiểm tra đăng nhập trực tiếp & an toàn (Hỗ trợ nhiều dạng lưu Session)
$is_logged_in = !empty($_SESSION['user_id']) || !empty($_SESSION['user']) || !empty($_SESSION['loggedin']);

if (!$is_logged_in) {
    header("Location: login.php");
    exit();
}

// Lấy thông tin người dùng từ Session
$user = $_SESSION['user'] ?? $_SESSION;
$displayName = $user['ten'] ?? $user['HoTen'] ?? $user['username'] ?? $_SESSION['username'] ?? 'Người dùng';
$quyen = strtolower($_SESSION['role'] ?? $user['quyen'] ?? 'guest');

// Ánh xạ tên vai trò sang tiếng Việt thân thiện
$roleNames = [
    'admin'           => 'Quản trị viên',
    'giangvien'       => 'Giảng viên',
    'sinhvien'        => 'Sinh viên',
    'khaothi'         => 'Phòng Khảo thí',
    'phongkhaothi'    => 'Phòng Khảo thí',
    'daotao'          => 'Phòng Đào tạo',
    'congtac'         => 'Phòng Công tác sinh viên',
    'congtacsinhvien' => 'Phòng Công tác sinh viên',
    'guest'           => 'Khách'
];
$displayRole = $roleNames[$quyen] ?? strtoupper($quyen);

// Lấy thông tin chi tiết sinh viên nếu vai trò là sinh viên
$student_extra = null;
if ($quyen === 'sinhvien') {
    $maSV = $user['MaSV'] ?? $_SESSION['user_id'] ?? null;
    if ($maSV) {
        try {
            $stmt_sv_home = $pdo->prepare("SELECT sv.MaSV, sv.HoTen, l.TenLop 
                                           FROM sinhvien sv 
                                           LEFT JOIN lop l ON sv.MaLop = l.MaLop 
                                           WHERE sv.MaSV = ? OR sv.id = ?");
            $stmt_sv_home->execute([$maSV, $maSV]);
            $student_extra = $stmt_sv_home->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Xử lý lỗi nếu truy vấn thất bại
            error_log("Lỗi lấy thông tin sinh viên: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Trang Chủ - Hệ Thống Quản Lý Điểm</title>
    <!-- Google Font & FontAwesome Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Style CSS Dùng Chung -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <!-- Header User Card -->
        <div class="header-card">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fa-solid <?= ($quyen === 'sinhvien') ? 'fa-user-graduate' : 'fa-user-shield' ?>"></i>
                </div>
                <div class="user-details">
                    <span class="user-name">Xin chào, <?= htmlspecialchars($student_extra['HoTen'] ?? $displayName, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($student_extra): ?>
                        <span class="user-subtext">Mã SV: <?= htmlspecialchars($student_extra['MaSV'], ENT_QUOTES, 'UTF-8') ?> | Lớp: <?= htmlspecialchars($student_extra['TenLop'] ?? 'Chưa xếp lớp', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="role-badge"><?= htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="logout.php" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
            </a>
        </div>

        <div class="section-title">
            <i class="fa-solid fa-layer-group" style="color: #6366f1;"></i> Bảng điều khiển chức năng
        </div>

        <!-- Menu List -->
        <div class="menu-list">
            <?php if ($quyen === 'sinhvien'): ?>
                <a href="modules/view_grade.php" class="menu-card">
                    <div class="card-content">
                        <div class="icon-box"><i class="fa-solid fa-graduation-cap"></i></div>
                        <div class="card-info">
                            <div class="card-title-text">Xem Bảng Điểm Cá Nhân</div>
                            <div class="card-desc">Tra cứu điểm bộ phận, điểm thi, điểm trung bình học kỳ và GPA tích lũy</div>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>

            <?php else: ?>
                <!-- Chức năng xem/tra cứu điểm chung -->
                <a href="modules/view_class_grades.php" class="menu-card">
                    <div class="card-content">
                        <div class="icon-box"><i class="fa-solid fa-list-check"></i></div>
                        <div class="card-info">
                            <div class="card-title-text">Tra Cứu / Xem Điểm Sinh Viên</div>
                            <div class="card-desc">Xem danh sách điểm theo môn học, lớp học hoặc toàn bộ khóa học</div>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right arrow-icon"></i>
                </a>

                <?php if ($quyen === 'admin'): ?>
                    <a href="modules/manage_accounts.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-users-gear"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Quản Lý Tài Khoản</div>
                                <div class="card-desc">Tạo, xóa tài khoản, đặt lại mật khẩu cho người dùng hệ thống</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>

                    <a href="modules/manage_permissions.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-user-shield"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Quản Lý Phân Quyền</div>
                                <div class="card-desc">Cấp quyền, thu hồi quyền và giới hạn thời hạn truy cập theo từng vai trò</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>
                <?php endif; ?>

                <?php if (in_array($quyen, ['giangvien', 'admin'])): ?>
                    <a href="modules/component_grade.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-pen-to-square"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Nhập Điểm Bộ Phận</div>
                                <div class="card-desc">Nhập, cập nhật và quản lý điểm quá trình/bộ phận của sinh viên</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>
                <?php endif; ?>

                <?php if (in_array($quyen, ['khaothi', 'phongkhaothi', 'admin'])): ?>
                    <a href="modules/exam_grade.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-file-signature"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Nhập Điểm Thi Học Phần</div>
                                <div class="card-desc">Nhập, cập nhật điểm thi kết thúc học phần cho sinh viên</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>
                <?php endif; ?>

                <?php if (in_array($quyen, ['daotao', 'admin'])): ?>
                    <a href="modules/courses.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-book"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Quản lý Học phần</div>
                                <div class="card-desc">Thêm, sửa, xóa và quản lý thông tin các môn học, học phần</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>

                    <a href="modules/open_course_classes.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-folder-plus"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Mở Lớp Học Phần</div>
                                <div class="card-desc">Mở lớp học phần mới và phân công giảng viên giảng dạy</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>
                <?php endif; ?>

                <?php if (in_array($quyen, ['congtac', 'congtacsinhvien', 'admin'])): ?>
                    <a href="modules/classes.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-school"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Quản lý Lớp học</div>
                                <div class="card-desc">Thêm, sửa, xóa và quản lý danh sách các lớp học trong hệ thống</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>

                    <a href="modules/students.php" class="menu-card">
                        <div class="card-content">
                            <div class="icon-box"><i class="fa-solid fa-users"></i></div>
                            <div class="card-info">
                                <div class="card-title-text">Quản lý Sinh viên</div>
                                <div class="card-desc">Quản lý thông tin, danh sách và hồ sơ chi tiết của sinh viên</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow-icon"></i>
                    </a>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>

