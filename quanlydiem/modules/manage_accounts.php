<?php
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền chỉ dành cho ADMIN
if (function_exists('checkRole')) {
    checkRole(['admin']);
}

$message = '';$error = '';

// 1. TỰ ĐỘNG TÌM BẢNG TÀI KHOẢN TRONG DATABASE
$possible_tables = ['users', 'taikhoan', 'tai_khoan', 'account', 'accounts'];$user_table = null;

foreach ($possible_tables as$tbl) {
    try {
        $check =$pdo->query("SHOW TABLES LIKE '$tbl'")->fetch();
        if ($check) {
            $user_table =$tbl;
            break;
        }
    } catch (Exception $e) {
        // Bỏ qua nếu lỗi
    }
}

if (!$user_table) {
    die("<div style='color:#dc2626; padding:20px; font-family:sans-serif; background: #f1f5f9;'>
            <strong>Lỗi hệ thống:</strong> Không tìm thấy bảng tài khoản người dùng trong Database.
         </div>");
}

// 2. TỰ ĐỘNG XÁC ĐỊNH TÊN CỘT VÀ TẠO CỘT NGÀY HẾT HẠN NẾU CHƯA CÓ
$columns =$pdo->query("DESCRIBE `$user_table`")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('role_expiry', $columns)) {
    try {
        $pdo->exec("ALTER TABLE `$user_table` ADD COLUMN `role_expiry` DATETIME NULL DEFAULT NULL");
        $columns[] = 'role_expiry';
    } catch (PDOException $e) {
        // Bỏ qua nếu không tạo được cột
    }
}

$col_id     = in_array('id', $columns) ? 'id' : (in_array('id_user',$columns) ? 'id_user' : $columns[0]);$col_user   = in_array('username', $columns) ? 'username' : (in_array('tendangnhap',$columns) ? 'tendangnhap' : (in_array('user', $columns) ? 'user' :$columns[1]));
$col_role   = in_array('quyen',$columns) ? 'quyen' : (in_array('role', $columns) ? 'role' : (in_array('phan_quyen',$columns) ? 'phan_quyen' : 'quyen'));
$col_name   = in_array('ten',$columns) ? 'ten' : (in_array('HoTen', $columns) ? 'HoTen' : (in_array('fullname',$columns) ? 'fullname' : null));
$has_expiry = in_array('role_expiry',$columns);

// 3. TỰ ĐỘNG THU HỒI CÁC TÀI KHOẢN ĐÃ HẾT HẠN VỀ GUEST
if ($has_expiry) {$pdo->exec("UPDATE `$user_table` SET `$col_role` = 'guest', `role_expiry` = NULL WHERE `role_expiry` IS NOT NULL AND `role_expiry` <= NOW() AND `$col_role` != 'sinhvien'");
}

// 3b. XỬ LÝ CÁC THAO TÁC QUẢN LÝ TÀI KHOẢN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TẠO TÀI KHOẢN MỚI
    if (isset($_POST['add_account'])) {
        $new_ten = trim($_POST['new_ten'] ?? '');
        $new_pass = trim($_POST['new_matkhau'] ?? '');
        $new_role = strtolower(trim($_POST['new_quyen'] ?? ''));
        $new_masv = trim($_POST['new_masv'] ?? '');

        $allowed = ['giangvien', 'khaothi', 'phongkhaothi', 'daotao', 'congtac', 'congtacsinhvien', 'sinhvien', 'guest'];
        if ($new_ten === '' || $new_pass === '' || !in_array($new_role, $allowed)) {
            $error = "Vui lòng nhập đầy đủ thông tin và chọn quyền hợp lệ (không thể tạo tài khoản ADMIN)!";
        } else {
            try {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM `$user_table` WHERE `$col_user` = ?");
                $chk->execute([$new_ten]);
                if ($chk->fetchColumn() > 0) {
                    $error = "Tên đăng nhập đã tồn tại!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `$user_table` (`$col_user`, `matkhau`, `$col_role`, `MaSV`) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$new_ten, password_hash($new_pass, PASSWORD_DEFAULT), $new_role, $new_masv !== '' ? $new_masv : null]);
                    $message = "Đã tạo tài khoản mới thành công!";
                }
            } catch (PDOException $e) {
                $error = "Lỗi tạo tài khoản: " . $e->getMessage();
            }
        }
    }

    // ĐẶT LẠI MẬT KHẨU
    if (isset($_POST['reset_password'])) {
        $uid = $_POST['user_id'] ?? '';
        $newp = trim($_POST['reset_matkhau'] ?? '');
        if ($newp === '') {
            $error = "Vui lòng nhập mật khẩu mới!";
        } else {
            try {
                // Không cho đặt lại mật khẩu của ADMIN
                $cur = $pdo->prepare("SELECT `$col_role`, `MaSV` FROM `$user_table` WHERE `$col_id` = ?");
                $cur->execute([$uid]);
                $row = $cur->fetch();
                if ($row && strtolower($row[$col_role]) === 'admin') {
                    $error = "Không được phép đặt lại mật khẩu tài khoản ADMIN!";
                } else {
                    $stmt = $pdo->prepare("UPDATE `$user_table` SET `matkhau` = ? WHERE `$col_id` = ?");
                    $stmt->execute([password_hash($newp, PASSWORD_DEFAULT), $uid]);
                    $message = "Đã đặt lại mật khẩu thành công!";
                }
            } catch (PDOException $e) {
                $error = "Lỗi: " . $e->getMessage();
            }
        }
    }

    // XÓA TÀI KHOẢN (không được xóa ADMIN)
    if (isset($_POST['delete_account'])) {
        $uid = $_POST['user_id'] ?? '';
        try {
            $cur = $pdo->prepare("SELECT `$col_role` FROM `$user_table` WHERE `$col_id` = ?");
            $cur->execute([$uid]);
            $roleNow = strtolower((string)$cur->fetchColumn());
            if ($roleNow === 'admin') {
                $error = "Không được phép xóa tài khoản ADMIN!";
            } elseif ($uid == ($_SESSION['user']['id'] ?? -1)) {
                $error = "Không thể tự xóa tài khoản đang đăng nhập!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM `$user_table` WHERE `$col_id` = ?");
                $stmt->execute([$uid]);
                $message = "Đã xóa tài khoản thành công!";
            }
        } catch (PDOException $e) {
            $error = "Lỗi xóa: " . $e->getMessage();
        }
    }
}

// 5. LẤY DANH SÁCH TÀI KHOẢN (kèm thông tin sinh viên nếu có)
$select_fields = "`$user_table`.`$col_id` AS id, `$user_table`.`$col_user` AS username, `$user_table`.`$col_role` AS quyen, `$user_table`.`MaSV` AS masv, `$user_table`.`matkhau` AS matkhau"
    . ($col_name ? ", `$user_table`.`$col_name` AS ten" : "")
    . ($has_expiry ? ", `$user_table`.`role_expiry`" : "");

$join = "";
try {
    $pdo->query("SELECT MaSV FROM sinhvien LIMIT 1");
    $join = " LEFT JOIN sinhvien sv ON sv.MaSV = `$user_table`.MaSV";
    $select_fields .= ", sv.HoTen AS sv_hoten";
} catch (Exception $e) {
    $select_fields .= ", NULL AS sv_hoten";
}

$users = $pdo->query("SELECT $select_fields FROM `$user_table` $join ORDER BY `$user_table`.`$col_id` DESC")->fetchAll();

// 6. THỐNG KÊ SỐ LƯỢNG TÀI KHOẢN THEO TỪNG VAI TRÒ
$role_stats = ['admin' => 0, 'giangvien' => 0, 'khaothi' => 0, 'daotao' => 0, 'congtac' => 0, 'sinhvien' => 0, 'guest' => 0];
foreach ($users as $u) {
    $r = strtolower($u['quyen'] ?: 'guest');
    if (strpos($r, 'admin') !== false) $role_stats['admin']++;
    elseif (strpos($r, 'giangvien') !== false) $role_stats['giangvien']++;
    elseif (strpos($r, 'khaothi') !== false) $role_stats['khaothi']++;
    elseif (strpos($r, 'daotao') !== false) $role_stats['daotao']++;
    elseif (strpos($r, 'congtac') !== false) $role_stats['congtac']++;
    elseif (strpos($r, 'sinhvien') !== false) $role_stats['sinhvien']++;
    else $role_stats['guest']++;
}

// Nhãn tiếng Việt cho từng quyền
$role_labels = [
    'admin'     => 'Quản trị viên',
    'giangvien' => 'Giảng viên',
    'khaothi'   => 'Phòng Khảo thí',
    'daotao'    => 'Phòng Đào tạo',
    'congtac'   => 'Phòng Công tác SV',
    'sinhvien'  => 'Sinh viên',
    'guest'     => 'Khách / Chờ duyệt',
];
$role_colors = [
    'admin'     => '#dc2626',
    'giangvien' => '#2563eb',
    'khaothi'   => '#d97706',
    'daotao'    => '#059669',
    'congtac'   => '#9333ea',
    'sinhvien'  => '#0d9488',
    'guest'     => '#64748b',
];

// Phạm vi chức năng được phép khi cấp quyền (kèm thời hạn role_expiry)
$role_scope = [
    'giangvien' => 'Chỉ được sửa/xóa Điểm Bộ Phận trong thời hạn cấp quyền',
    'khaothi'   => 'Chỉ được sửa/xóa Điểm Thi học phần trong thời hạn cấp quyền',
    'phongkhaothi' => 'Chỉ được sửa/xóa Điểm Thi học phần trong thời hạn cấp quyền',
    'daotao'    => 'Chỉ được sửa/xóa Học phần & Mở lớp học phần trong thời hạn cấp quyền',
    'congtac'   => 'Chỉ được sửa/xóa Lớp học & Sinh viên trong thời hạn cấp quyền',
    'congtacsinhvien' => 'Chỉ được sửa/xóa Lớp học & Sinh viên trong thời hạn cấp quyền',
    'sinhvien'  => 'Chỉ được xem điểm cá nhân',
    'guest'     => 'Không có quyền truy cập chức năng',
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Tài Khoản</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-input: #f8fafc;
            --border-color: #e2e8f0;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); min-height: 100vh; padding: 40px 20px; }
        .container { max-width: 1300px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .header h2 { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .btn-back { background: #eef2f7; color: var(--text-muted); padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid var(--border-color); transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { color: #1e293b; background: #e2e8f0; }

        .alert-success { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); padding: 12px 16px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 12px 16px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        .card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3); }
        .card-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px; }
        .search-box { position: relative; width: 320px; max-width: 100%; }
        .search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
        .search-box input { width: 100%; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; padding: 10px 14px 10px 38px; color: #fff; font-size: 13px; outline: none; transition: 0.2s; }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        .user-count { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .table-responsive { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; table-layout: fixed; min-width: 1000px; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background: #eef2f7; color: var(--text-muted); font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: rgba(15, 23, 42, 0.03); }
        
        .text-center { text-align: center !important; }
        .user-id { color: var(--text-muted); font-size: 13px; font-weight: 600; }

        .badge-role { display: inline-flex; align-items: center; justify-content: center; padding: 5px 10px; border-radius: 6px; font-weight: 700; font-size: 11px; text-transform: uppercase; border: 1px solid transparent; width: 110px; text-align: center; }
        .badge-admin { background: rgba(239, 68, 68, 0.15); color: #dc2626; border-color: rgba(239, 68, 68, 0.3); }
        .badge-giangvien { background: rgba(59, 130, 246, 0.15); color: #2563eb; border-color: rgba(59, 130, 246, 0.3); }
        .badge-khaothi { background: rgba(245, 158, 11, 0.15); color: #d97706; border-color: rgba(245, 158, 11, 0.3); }
        .badge-daotao { background: rgba(16, 185, 129, 0.15); color: #059669; border-color: rgba(16, 185, 129, 0.3); }
        .badge-congtac { background: rgba(168, 85, 247, 0.15); color: #9333ea; border-color: rgba(168, 85, 247, 0.3); }
        .badge-sinhvien { background: rgba(20, 184, 166, 0.15); color: #0d9488; border-color: rgba(20, 184, 166, 0.3); }
        .badge-guest { background: rgba(148, 163, 184, 0.15); color: #64748b; border-color: rgba(148, 163, 184, 0.3); }

        .form-update { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        select { background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); padding: 8px 10px; border-radius: 8px; outline: none; font-size: 13px; height: 38px; cursor: pointer; }
        select:focus { border-color: var(--primary); }
        
        .btn-save { background: var(--primary); color: white; border: none; padding: 0 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; height: 38px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-save:hover { background: var(--primary-hover); }
        
        .btn-revoke { background: rgba(239, 68, 68, 0.2); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0 10px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 12px; height: 38px; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .btn-revoke:hover { background: var(--danger); color: #fff; }

        .expiry-info { font-size: 11px; margin-top: 4px; color: #d97706; display: flex; align-items: center; gap: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2><i class="fa-solid fa-users-gear" style="color: #6366f1;"></i> Quản Lý Tài Khoản</h2>
        <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Quay lại Trang chủ</a>
    </div>

    <?php if ($message): ?>
        <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 24px;">
        <h3 style="font-size: 15px; margin-bottom: 14px; color: #1e293b;"><i class="fa-solid fa-user-plus" style="color:#6366f1;"></i> Tạo Tài Khoản Mới</h3>
        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end;" onsubmit="return confirm('Tạo tài khoản mới?');">
            <?php csrf_field(); ?>
            <div>
                <label style="font-size:12px; color: var(--text-muted); font-weight:600;">Tên đăng nhập</label>
                <input type="text" name="new_ten" required style="width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:8px;" placeholder="VD: gv01">
            </div>
            <div>
                <label style="font-size:12px; color: var(--text-muted); font-weight:600;">Mật khẩu</label>
                <input type="text" name="new_matkhau" required style="width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:8px;" placeholder="Mật khẩu khởi tạo">
            </div>
            <div>
                <label style="font-size:12px; color: var(--text-muted); font-weight:600;">Quyền</label>
                <select name="new_quyen" style="width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:8px; height:38px;">
                    <option value="giangvien">Giảng viên</option>
                    <option value="khaothi">Phòng Khảo thí</option>
                    <option value="daotao">Phòng Đào tạo</option>
                    <option value="congtac">Phòng Công tác SV</option>
                    <option value="sinhvien">Sinh viên</option>
                    <option value="guest">Guest</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px; color: var(--text-muted); font-weight:600;">MaSV (nếu là SV)</label>
                <input type="text" name="new_masv" style="width:100%; padding:9px 12px; border:1px solid var(--border-color); border-radius:8px;" placeholder="VD: SV001">
            </div>
            <button type="submit" name="add_account" class="btn-save" style="height:38px;"><i class="fa-solid fa-plus"></i> Tạo</button>
        </form>
    </div>

    <div class="card">
        <!-- THỐNG KÊ PHÂN BỔ QUYỀN -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <?php foreach ($role_stats as $rk => $cnt): ?>
                <div style="border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 14px; background: #f8fafc;">
                    <div style="font-size: 22px; font-weight: 800; color: <?= $role_colors[$rk] ?>;"><?= $cnt ?></div>
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 600;"><?= $role_labels[$rk] ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card-toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" onkeyup="filterUsers()" placeholder="Tìm theo tên đăng nhập hoặc họ tên...">
            </div>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <select id="roleFilter" onchange="filterUsers()" style="height: 40px;">
                    <option value="">-- Tất cả vai trò --</option>
                    <option value="admin">Quản trị viên</option>
                    <option value="giangvien">Giảng viên</option>
                    <option value="khaothi">Phòng Khảo thí</option>
                    <option value="daotao">Phòng Đào tạo</option>
                    <option value="congtac">Phòng Công tác SV</option>
                    <option value="sinhvien">Sinh viên</option>
                    <option value="guest">Khách / Chờ duyệt</option>
                </select>
                <div class="user-count">
                    Tổng số tài khoản: <strong style="color: var(--primary);"><?= count($users) ?></strong>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="userTable">
                <thead>
                    <tr>
                        <th style="width: 60px;" class="text-center">ID</th>
                        <th style="width: 180px;">Tên Đăng Nhập</th>
                        <th style="width: 160px;">Mật Khẩu</th>
                        <th>Họ Và Tên / Liên Kết SV</th>
                        <th style="width: 130px;" class="text-center">Quyền Hiện Tại</th>
                        <th style="width: 200px;">Quản Lý Tài Khoản</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="text-center" style="padding: 30px; color: var(--text-muted);">Chưa có tài khoản nào trong cơ sở dữ liệu.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as$u): 
                            $role = strtolower($u['quyen'] ?: 'guest');
                            $expiry =$u['role_expiry'] ?? null;
                            
                            $badge_class = 'badge-guest';
                            if (strpos($role, 'admin') !== false)$badge_class = 'badge-admin';
                            elseif (strpos($role, 'giangvien') !== false)$badge_class = 'badge-giangvien';
                            elseif (strpos($role, 'khaothi') !== false)$badge_class = 'badge-khaothi';
                            elseif (strpos($role, 'daotao') !== false)$badge_class = 'badge-daotao';
                            elseif (strpos($role, 'congtac') !== false)$badge_class = 'badge-congtac';
                            elseif (strpos($role, 'sinhvien') !== false)$badge_class = 'badge-sinhvien';
                        ?>
                            <tr class="user-row" data-role="<?= htmlspecialchars($role) ?>">
                                <td class="text-center user-id">#<?= htmlspecialchars($u['id']) ?></td>
                                <td class="col-username">
                                    <strong style="color: #1e293b;"><?= htmlspecialchars($u['username']) ?></strong>
                                </td>
                                <td>
                                    <span class="pwd-masked" id="pwd_<?= $u['id'] ?>">••••••••</span>
                                    <button type="button" onclick="togglePwd(<?= $u['id'] ?>, '<?= htmlspecialchars($u['matkhau'] ?? '', ENT_QUOTES, 'UTF-8') ?>')" style="background:none;border:none;color:#6366f1;cursor:pointer;font-size:12px;" title="Hiện/Ẩn mật khẩu">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </td>
                                <td class="col-fullname">
                                    <?php $svName = $u['sv_hoten'] ?? null; ?>
                                    <?= htmlspecialchars($svName ?: ($u['ten'] ?? 'Chưa cập nhật')) ?>
                                    <?php if (!empty($u['masv'])): ?>
                                        <div style="font-size: 11px; color: #0d9488; margin-top: 2px;">
                                            <i class="fa-solid fa-id-badge"></i> <?= htmlspecialchars($u['masv']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge-role <?= $badge_class ?>">
                                        <?= htmlspecialchars(strtoupper($role)) ?>
                                    </span>
                                    <?php if ($expiry && $role !== 'sinhvien' &&$role !== 'guest'): ?>
                                        <div class="expiry-info">
                                            <i class="fa-regular fa-clock"></i> 
                                            Hạn: <?= date('d/m/Y H:i', strtotime($expiry)) ?>
                                            <?php
                                                $remain = strtotime($expiry) - time();
                                                if ($remain > 0) {
                                                    $days = ceil($remain / 86400);
                                                    echo " (còn {$days} ngày)";
                                                } else {
                                                    echo " (đã hết hạn)";
                                                }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($role !== 'admin'): ?>
                                    <form method="POST" style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">
                                        <input type="text" name="reset_matkhau" placeholder="Mật khẩu mới" style="padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 12px; width: 130px;">
                                        <button type="submit" name="reset_password" class="btn-save" style="height: 32px; padding: 0 10px; font-size: 12px;" onclick="return confirm('Đặt lại mật khẩu cho tài khoản này?')" title="Đặt lại mật khẩu">
                                            <i class="fa-solid fa-key"></i> Đổi MK
                                        </button>
                                        <button type="submit" name="delete_account" class="btn-revoke" style="height: 32px;" onclick="return confirm('XÓA VĨNH VIỄN tài khoản này?')" title="Xóa tài khoản">
                                            <i class="fa-solid fa-trash-can"></i> Xóa
                                        </button>
                                    </form>
                                    <?php else: ?>
                                        <span style="font-size:12px; color: var(--text-muted); font-style: italic;">Tài khoản hệ thống (không thao tác)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Hiện/Ẩn mật khẩu lưu trong CSDL
const pwdVisible = {};
function togglePwd(id, hash) {
    const el = document.getElementById('pwd_' + id);
    if (!el) return;
    pwdVisible[id] = !pwdVisible[id];
    el.textContent = pwdVisible[id] ? hash : '••••••••';
}

// Lọc người dùng bằng Javascript
function filterUsers() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toLowerCase();
    const roleFilter = document.getElementById("roleFilter").value;
    const rows = document.querySelectorAll("#userTable tbody .user-row");

    rows.forEach(row => {
        const username = row.querySelector(".col-username").textContent.toLowerCase();
        const fullname = row.querySelector(".col-fullname").textContent.toLowerCase();
        const rowRole = row.getAttribute("data-role") || "";

        const matchText = username.includes(filter) || fullname.includes(filter);
        let matchRole = true;
        if (roleFilter === 'khaothi') matchRole = rowRole.includes('khaothi');
        else if (roleFilter === 'congtac') matchRole = rowRole.includes('congtac');
        else if (roleFilter === 'guest') matchRole = rowRole.includes('guest') || rowRole === '';
        else if (roleFilter !== '') matchRole = rowRole.includes(roleFilter);

        row.style.display = (matchText && matchRole) ? "" : "none";
    });
}
</script>
</body>
</html> 

