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

// Cột extra_perms (quyền sửa/xóa được cấp thêm) - kiểm tra sớm để POST handler dùng được
$has_extra = true;
try {
    $pdo->query("SELECT extra_perms FROM `$user_table` LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE `$user_table` ADD COLUMN extra_perms TEXT NULL");
    } catch (PDOException $e2) {
        $has_extra = false;
    }
}

// Quyền SỬA / XÓA admin có thể CẤP THÊM (không phải mặc định), tách riêng từng cái
$extra_perms_def = [
    'giangvien' => ['sua_diem_bophan' => 'Sửa điểm bộ phận', 'xoa_diem_bophan' => 'Xóa điểm bộ phận'],
    'khaothi'   => ['sua_diem_thi' => 'Sửa điểm thi học phần', 'xoa_diem_thi' => 'Xóa điểm thi học phần'],
    'phongkhaothi' => ['sua_diem_thi' => 'Sửa điểm thi học phần', 'xoa_diem_thi' => 'Xóa điểm thi học phần'],
    'daotao'    => ['sua_hocphan' => 'Sửa học phần', 'xoa_hocphan' => 'Xóa học phần', 'sua_lop_hp' => 'Sửa lớp học phần', 'xoa_lop_hp' => 'Xóa lớp học phần'],
    'congtac'   => ['sua_sinhvien' => 'Sửa sinh viên', 'xoa_sinhvien' => 'Xóa sinh viên', 'sua_lophoc' => 'Sửa lớp học', 'xoa_lophoc' => 'Xóa lớp học'],
    'congtacsinhvien' => ['sua_sinhvien' => 'Sửa sinh viên', 'xoa_sinhvien' => 'Xóa sinh viên', 'sua_lophoc' => 'Sửa lớp học', 'xoa_lophoc' => 'Xóa lớp học'],
];

// 4. XỬ LÝ POST REQUEST (CẬP NHẬT / THU HỒI QUYỀN)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id =$_POST['user_id'] ?? '';

    // Thao tác THU HỒI QUYỀN
    if (isset($_POST['revoke_role'])) {
        try {
            if ($has_expiry) {
                $stmt =$pdo->prepare("UPDATE `$user_table` SET `$col_role` = 'guest', `role_expiry` = NULL" . ($has_extra ? ", `extra_perms` = NULL" : "") . " WHERE `$col_id` = ?");
            } else {
                $stmt =$pdo->prepare("UPDATE `$user_table` SET `$col_role` = 'guest'" . ($has_extra ? ", `extra_perms` = NULL" : "") . " WHERE `$col_id` = ?");
            }
            $stmt->execute([$user_id]);$message = "Đã thu hồi quyền thành công! Tài khoản được đưa về trạng thái Guest.";
        } catch (PDOException $e) {$error = "Lỗi thu hồi: " . $e->getMessage();
        }
    }
    // Thao tác CẬP NHẬT QUYỀN
    elseif (isset($_POST['update_role'])) {
        $new_role = strtolower(trim($_POST['quyen'] ?? ''));

        // Danh sách quyền ĐƯỢC PHÉP cấp: KHÔNG có 'admin' và KHÔNG có 'sinhvien'.
        $valid_roles = ['giangvien', 'khaothi', 'phongkhaothi', 'daotao', 'congtac', 'congtacsinhvien', 'guest'];

        // Lấy quyền hiện tại của tài khoản đích
        $st_cur = $pdo->prepare("SELECT `$col_role` FROM `$user_table` WHERE `$col_id` = ?");
        $st_cur->execute([$user_id]);
        $cur_role = strtolower((string)$st_cur->fetchColumn());

        if ($new_role === 'admin') {
            $error = "Không được phép cấp quyền ADMIN tại trang này!";
        } elseif ($cur_role === 'sinhvien') {
            $error = "Tài khoản sinh viên không được chỉnh sửa quyền, chỉ có thể thu hồi!";
        } elseif (!in_array($new_role, $valid_roles)) {
            $error = "Quyền được chọn không hợp lệ!";
        } else {
            // Tính toán thời hạn: 'Vĩnh viễn' (checkbox forever) hoặc thời điểm cụ thể (giờ, ngày, tháng, năm)
            $forever      = !empty($_POST['forever']);
            $expiry_date  = null;
            if ($new_role !== 'guest' && !$forever) {
                $rawDateTime = str_replace('T', ' ', trim($_POST['expiry_datetime'] ?? ''));
                if ($rawDateTime === '') {
                    $error = "Vui lòng nhập thời gian hết hạn cụ thể (giờ, ngày, tháng, năm) hoặc tích chọn 'Vĩnh viễn'!";
                } else {
                    $ts = strtotime($rawDateTime);
                    if ($ts === false || $ts <= time()) {
                        $error = "Thời gian hết hạn không hợp lệ — phải là một thời điểm trong tương lai!";
                    } else {
                        $expiry_date = date('Y-m-d H:i:s', $ts);
                    }
                }
            }

            if ($error === '') {
                try {
                    if ($has_expiry) {
                        $stmt = $pdo->prepare("UPDATE `$user_table` SET `$col_role` = ?, `role_expiry` = ? WHERE `$col_id` = ?");
                        $stmt->execute([$new_role, $expiry_date, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE `$user_table` SET `$col_role` = ? WHERE `$col_id` = ?");
                        $stmt->execute([$new_role, $user_id]);
                    }

                    // Lưu các quyền sửa/xóa được cấp THÊM (checkbox)
                    if ($has_extra) {
                        $posted = $_POST['extra_perms'] ?? [];
                        $allowed = $extra_perms_def[$new_role] ?? [];
                        $chosen = [];
                        if (is_array($posted)) {
                            foreach ($posted as $k) {
                                if (isset($allowed[$k])) $chosen[] = $k;
                            }
                        }
                        $stmt2 = $pdo->prepare("UPDATE `$user_table` SET `extra_perms` = ? WHERE `$col_id` = ?");
                        $stmt2->execute([json_encode($chosen), $user_id]);
                    }

                    $message = "Cập nhật quyền tài khoản thành công!";
                } catch (PDOException $e) {
                    $error = "Lỗi cập nhật: " . $e->getMessage();
                }
            }
        }
    }
}

// 5. LẤY DANH SÁCH TÀI KHOẢN (kèm thông tin sinh viên nếu có)
$select_fields = "`$user_table`.`$col_id` AS id, `$user_table`.`$col_user` AS username, `$user_table`.`$col_role` AS quyen, `$user_table`.`MaSV` AS masv"
    . ($col_name ? ", `$user_table`.`$col_name` AS ten" : "")
    . ($has_expiry ? ", `$user_table`.`role_expiry`" : "");

if ($has_extra) {
    $select_fields .= ", `$user_table`.`extra_perms`";
}

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

// Quyền MẶC ĐỊNH của từng vai trò (chỉ nhập liệu)
$role_defaults = [
    'giangvien' => ['Nhập điểm bộ phận'],
    'khaothi'   => ['Nhập điểm thi học phần'],
    'phongkhaothi' => ['Nhập điểm thi học phần'],
    'daotao'    => ['Nhập học phần', 'Nhập lớp học phần'],
    'congtac'   => ['Nhập sinh viên', 'Nhập lớp học'],
    'congtacsinhvien' => ['Nhập sinh viên', 'Nhập lớp học'],
    'sinhvien'  => ['Xem điểm cá nhân'],
    'admin'     => ['Toàn quyền hệ thống'],
    'guest'     => ['Không có quyền truy cập chức năng'],
];

// Bản đồ quyền cấp thêm cho JS (dựng lại checkbox khi đổi vai trò trên form)
$extra_def_js = [];
foreach ($extra_perms_def as $r => $perms) {
    $arr = [];
    foreach ($perms as $k => $lbl) $arr[] = ['key' => $k, 'label' => $lbl];
    $extra_def_js[$r] = $arr;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Phân Quyền</title>
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
        .search-box input { width: 100%; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; padding: 10px 14px 10px 38px; color: #1e293b; font-size: 13px; outline: none; transition: 0.2s; }
        .search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        .user-count { font-size: 13px; color: var(--text-muted); font-weight: 500; }

        .table-responsive { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; table-layout: fixed; min-width: 1100px; }
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

        .perm-list { list-style: none; font-size: 11.5px; color: #475569; }
        .perm-list li { padding: 3px 0; display: flex; align-items: center; gap: 6px; }
        .perm-list li i { color: #10b981; font-size: 9px; }

        .form-update { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        select { background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); padding: 8px 10px; border-radius: 8px; outline: none; font-size: 13px; height: 38px; cursor: pointer; }
        select:focus { border-color: var(--primary); }

        .btn-save { background: var(--primary); color: white; border: none; padding: 0 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; height: 38px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-save:hover { background: var(--primary-hover); }

        .btn-revoke { background: rgba(239, 68, 68, 0.2); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0 10px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 12px; height: 38px; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .btn-revoke:hover { background: var(--danger); color: #fff; }

        .expiry-info { font-size: 11px; margin-top: 4px; color: #d97706; display: flex; align-items: center; gap: 4px; }

        /* QUYỀN CHÍNH (select dropdown) */
        .role-select { width: 100%; margin-bottom: 8px; height: 38px; font-weight: 600; }
        .role-select option { font-weight: 500; }

        /* THỜI HẠN: Vĩnh viễn / nhập giờ-ngày-tháng cụ thể */
        .expiry-line { flex-basis: 100%; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 2px; background: rgba(217, 119, 6, 0.06); border: 1px dashed rgba(217, 119, 6, 0.35); border-radius: 8px; padding: 8px 12px; }
        .forever-check { font-size: 13px; font-weight: 700; color: #059669; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; }
        .forever-check input { accent-color: #059669; cursor: pointer; margin: 0; }
        .expiry-datetime-label { font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px; margin: 0; }
        .expiry-datetime { border: 1px solid var(--border-color); border-radius: 8px; padding: 7px 9px; font-size: 12.5px; background: #fff; color: var(--text-main); outline: none; min-width: 190px; }
        .expiry-datetime:focus { border-color: #d97706; box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2); }
        .expiry-datetime:disabled { background: #f1f5f9; color: #94a3b8; }

        /* Preset thời hạn nhanh (+1/+7/+30/+90 ngày) */
        .expiry-quick { display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap; margin: 0; }
        .expiry-quick-label { font-size: 11px; font-weight: 700; color: var(--text-muted); }
        .btn-quick { background: #fff; color: #d97706; border: 1px solid rgba(217, 119, 6, 0.45); border-radius: 999px; padding: 3px 10px; font-size: 11px; font-weight: 700; cursor: pointer; transition: 0.15s; }
        .btn-quick:hover { background: #d97706; color: #fff; }

        /* QUYỀN CẤP THÊM (multiple select) */
        .extras-container { flex-basis: 100%; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 2px 10px; }
        .perm-check-head { flex-basis: 100%; font-size: 11px; font-weight: 700; color: var(--text-muted); margin: 4px 0 2px; display: flex; align-items: center; gap: 6px; }
        .perm-check-head i { color: var(--primary); }
        .extra-count { font-size: 11px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 4px; margin-left: auto; }
        .extra-count b { color: var(--primary); }
        .extra-select-row { display: flex; align-items: flex-start; gap: 8px; width: 100%; }
        .extra-multi { flex: 1; min-width: 180px; height: auto; min-height: 84px; cursor: pointer; }
        .extra-multi:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
        .extra-actions { display: flex; flex-direction: column; gap: 5px; }
        .btn-extra { background: rgba(99, 102, 241, 0.12); color: var(--primary-hover); border: 1px solid rgba(99, 102, 241, 0.35); padding: 5px 12px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 11.5px; white-space: nowrap; transition: 0.15s; }
        .btn-extra:hover { background: var(--primary); color: #fff; }
        .btn-extra-null { background: rgba(239, 68, 68, 0.08); color: #dc2626; border-color: rgba(239, 68, 68, 0.3); }
        .btn-extra-null:hover { background: var(--danger); color: #fff; }
        .extra-tags { flex-basis: 100%; display: flex; flex-wrap: wrap; gap: 5px; }
        .extra-tag { background: rgba(99, 102, 241, 0.12); color: var(--primary-hover); border: 1px solid rgba(99, 102, 241, 0.3); border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .extra-tag i { cursor: pointer; font-size: 10px; color: var(--text-muted); }
        .extra-tag i:hover { color: var(--danger); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2><i class="fa-solid fa-user-shield" style="color: #6366f1;"></i> Quản Lý Phân Quyền</h2>
        <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Quay lại Trang chủ</a>
    </div>

    <?php if ($message): ?>
        <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
                        <th style="width: 50px;" class="text-center">ID</th>
                        <th style="width: 130px;">Tên Đăng Nhập</th>
                        <th style="width: 150px;">Họ Và Tên / Liên Kết SV</th>
                        <th style="width: 130px;" class="text-center">Quyền Hiện Tại</th>
                        <th>Quyền Được Cấp</th>
                        <th style="width: 380px;">Cấp Quyền / Thời Hạn</th>
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

                            $scopeKey = strpos($role, 'khaothi') !== false ? 'khaothi' : (strpos($role, 'congtac') !== false ? 'congtac' : $role);
                        ?>
                            <tr class="user-row" data-role="<?= htmlspecialchars($role) ?>">
                                <td class="text-center user-id">#<?= htmlspecialchars($u['id']) ?></td>
                                <td class="col-username">
                                    <strong style="color: #1e293b;"><?= htmlspecialchars($u['username']) ?></strong>
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
                                    <?php
                                        $scopeKey2 = strpos($role, 'khaothi') !== false ? 'khaothi' : (strpos($role, 'congtac') !== false ? 'congtac' : $role);
                                        $defaults = $role_defaults[$scopeKey2] ?? $role_defaults['guest'];
                                        $userExtras = [];
                                        if ($has_extra && !empty($u['extra_perms'])) {
                                            $dec = json_decode($u['extra_perms'], true);
                                            if (is_array($dec)) $userExtras = $dec;
                                        }
                                        $scopeDef = $extra_perms_def[$scopeKey2] ?? [];
                                    ?>
                                    <ul class="perm-list">
                                        <?php foreach ($defaults as $p): ?>
                                            <li><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($p) ?> <span style="color:var(--text-muted); font-size:10px;">(mặc định)</span></li>
                                        <?php endforeach; ?>
                                        <?php foreach ($userExtras as $ex): ?>
                                            <?php if (isset($scopeDef[$ex])): ?>
                                                <li><i class="fa-solid fa-circle-plus" style="color:#6366f1;"></i> <?= htmlspecialchars($scopeDef[$ex]) ?> <span style="color:#6366f1; font-size:10px;">(được cấp thêm)</span></li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                                <td>
                                    <form method="POST" class="form-update">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($u['id']) ?>">

                                        <?php if ($role === 'sinhvien'): ?>
                                            <button type="submit" name="revoke_role" class="btn-revoke" onclick="return confirm('Bạn có chắc muốn thu hồi quyền của tài khoản sinh viên này?')" title="Thu hồi quyền">
                                                <i class="fa-solid fa-user-slash"></i> Thu hồi
                                            </button>
                                        <?php else: ?>
                                        <?php
                                            $expiryValue  = $expiry ? date('Y-m-d\TH:i', strtotime($expiry)) : '';
                                            $hasExpirySet = !empty($expiry);
                                            $optSel = function ($val) use ($role) {
                                                if ($val === 'khaothi') return ($role === 'khaothi' || $role === 'phongkhaothi');
                                                if ($val === 'congtac') return ($role === 'congtac' || $role === 'congtacsinhvien');
                                                if ($val === 'guest')   return ($role === 'guest' || $role === '' || $role === 'admin');
                                                return $role === $val;
                                            };
                                        ?>
                                        <!-- QUYỀN CHÍNH (select) -->
                                        <select name="quyen" class="role-select" onchange="applyRole(this)">
                                            <option value="giangvien" <?= $optSel('giangvien') ? 'selected' : '' ?>>Giảng viên (Điểm bộ phận)</option>
                                            <option value="khaothi" <?= $optSel('khaothi') ? 'selected' : '' ?>>Khảo thí (Điểm thi)</option>
                                            <option value="daotao" <?= $optSel('daotao') ? 'selected' : '' ?>>Đào tạo (Học phần & Lớp HP)</option>
                                            <option value="congtac" <?= $optSel('congtac') ? 'selected' : '' ?>>Công tác SV (Lớp & Sinh viên)</option>
                                            <option value="guest" <?= $optSel('guest') ? 'selected' : '' ?>>Guest (Chờ / Khóa)</option>
                                        </select>

                                        <!-- THỜI HẠN: Vĩnh viễn / nhập giờ-ngày-tháng cụ thể + preset nhanh -->
                                        <div class="expiry-line" style="<?= $role === 'guest' ? 'display:none;' : '' ?>">
                                            <label class="forever-check">
                                                <input type="checkbox" name="forever" value="1" <?= !$hasExpirySet ? 'checked' : '' ?> onchange="toggleForever(this)">
                                                <i class="fa-solid fa-infinity"></i> Vĩnh viễn
                                            </label>
                                            <label class="expiry-datetime-label">
                                                Hết hạn lúc:
                                                <input type="datetime-local" name="expiry_datetime" class="expiry-datetime"
                                                       value="<?= htmlspecialchars($expiryValue, ENT_QUOTES, 'UTF-8') ?>"
                                                       <?= !$hasExpirySet ? 'disabled' : '' ?>>
                                            </label>
                                            <div class="expiry-quick">
                                                <span class="expiry-quick-label">Nhanh:</span>
                                                <button type="button" class="btn-quick" onclick="quickExpiry(this, 1)" title="Hết hạn sau 1 ngày">+1 ngày</button>
                                                <button type="button" class="btn-quick" onclick="quickExpiry(this, 7)" title="Hết hạn sau 7 ngày">+7 ngày</button>
                                                <button type="button" class="btn-quick" onclick="quickExpiry(this, 30)" title="Hết hạn sau 30 ngày">+30 ngày</button>
                                                <button type="button" class="btn-quick" onclick="quickExpiry(this, 90)" title="Hết hạn sau 90 ngày">+90 ngày</button>
                                            </div>
                                        </div>

                                        <!-- QUYỀN CẤP THÊM (multiple select) -->
                                        <div class="extras-container">
                                            <?php $formExtras = $extra_perms_def[$scopeKey] ?? []; ?>
                                            <?php
                                                $userExtrasForm = [];
                                                if ($has_extra && !empty($u['extra_perms'])) {
                                                    $dec2 = json_decode($u['extra_perms'], true);
                                                    if (is_array($dec2)) $userExtrasForm = $dec2;
                                                }
                                                $userExtrasForm = array_values(array_intersect($userExtrasForm, array_keys($formExtras)));
                                            ?>
                                            <?php if (!empty($formExtras)): ?>
                                                <div class="perm-check-head">
                                                    <i class="fa-solid fa-circle-plus"></i> Quyền sửa/xóa cấp thêm
                                                    <span class="extra-count"><i class="fa-solid fa-list-check"></i> Đã chọn <b class="extra-nsel"><?= count($userExtrasForm) ?></b>/<b class="extra-ntot"><?= count($formExtras) ?></b> quyền</span>
                                                </div>
                                                <div class="extra-select-row">
                                                    <select name="extra_perms[]" class="extra-multi" multiple size="4" onchange="updateExtraUi(this)">
                                                        <?php foreach ($formExtras as $k => $label): ?>
                                                            <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($k, $userExtrasForm) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="extra-actions">
                                                        <button type="button" class="btn-extra" onclick="selectAllExtras(this)" title="Chọn tất cả quyền cấp thêm"><i class="fa-solid fa-check-double"></i> Chọn tất cả</button>
                                                        <button type="button" class="btn-extra btn-extra-null" onclick="clearExtras(this)" title="Bỏ chọn tất cả"><i class="fa-solid fa-eraser"></i> Bỏ hết</button>
                                                    </div>
                                                </div>
                                                <div class="extra-tags"></div>
                                            <?php else: ?>
                                                <select name="extra_perms[]" class="extra-multi" multiple style="display:none;" onchange="updateExtraUi(this)"></select>
                                            <?php endif; ?>
                                        </div>

                                        <button type="submit" name="update_role" class="btn-save" title="Lưu phân quyền">
                                            <i class="fa-solid fa-floppy-disk"></i> Lưu
                                        </button>

                                        <!-- Nút Thu Hồi Quyền -->
                                        <?php if ($role !== 'guest'): ?>
                                            <button type="submit" name="revoke_role" class="btn-revoke" onclick="return confirm('Bạn có chắc muốn thu hồi quyền của tài khoản này về Guest?')" title="Thu hồi quyền lập tức">
                                                <i class="fa-solid fa-user-slash"></i> Thu hồi
                                            </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </form>
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
// Bảng quyền sửa/xóa cấp thêm theo từng vai trò (dùng để dựng lại khi đổi vai trò trên form)
const EXTRA_DEF = <?= json_encode($extra_def_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function applyRole(sel) {
    const form = sel.form;
    const role = sel.value;
    const isGuest = (role === 'guest');

    // Ẩn/hiện ô thời hạn (Guest thì không có thời hạn)
    const expiryLine = form.querySelector('.expiry-line');
    if (expiryLine) expiryLine.style.display = isGuest ? 'none' : '';

    const foreverCb = form.querySelector('input[name="forever"]');
    if (isGuest && foreverCb) foreverCb.checked = true;
    if (foreverCb) updateForeverState(foreverCb, false);

    // Dựng lại danh sách quyền cấp thêm (multiple select) theo vai trò
    const multi = form.querySelector('.extra-multi');
    if (!multi) return;
    const perms = EXTRA_DEF[role] || [];
    multi.innerHTML = '';
    const frag = document.createDocumentFragment();
    perms.forEach(function (p) {
        const opt = document.createElement('option');
        opt.value = p.key;
        opt.textContent = p.label;
        frag.appendChild(opt);
    });
    multi.appendChild(frag);
    multi.style.display = perms.length ? '' : 'none';
    updateExtraUi(multi);
}

function findOption(sel, val) {
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === val) return sel.options[i];
    }
    return null;
}

// Cập nhật bộ đếm "Đã chọn X/N" và thẻ (tags) các quyền đang chọn
function updateExtraUi(sel) {
    const form = sel.form;
    const selectedVals = Array.from(sel.selectedOptions || []).map(function (o) { return o.value; });

    const nselEl = form.querySelector('.extra-nsel');
    const ntotEl = form.querySelector('.extra-ntot');
    if (nselEl) nselEl.textContent = selectedVals.length;
    if (ntotEl) ntotEl.textContent = sel.options.length;

    const head = form.querySelector('.perm-check-head');
    if (head) head.style.display = sel.options.length ? '' : 'none';

    const tagsEl = form.querySelector('.extra-tags');
    if (tagsEl) {
        tagsEl.innerHTML = '';
        selectedVals.forEach(function (v) {
            const opt = findOption(sel, v);
            const label = opt ? opt.textContent : v;
            const tag = document.createElement('span');
            tag.className = 'extra-tag';
            const ic = document.createElement('i');
            ic.className = 'fa-solid fa-xmark';
            ic.title = 'Bỏ quyền: ' + label;
            ic.addEventListener('click', function () {
                const o = findOption(sel, v);
                if (o) o.selected = false;
                updateExtraUi(sel);
            });
            tag.appendChild(document.createTextNode(label));
            tag.appendChild(ic);
            tagsEl.appendChild(tag);
        });
        tagsEl.style.display = selectedVals.length ? '' : 'none';
    }
}

// Chọn tất cả / Bỏ hết cho ô multiple select quyền cấp thêm
function selectAllExtras(btn) {
    const multi = btn.form.querySelector('.extra-multi');
    if (!multi) return;
    Array.prototype.forEach.call(multi.options, function (o) { o.selected = true; });
    updateExtraUi(multi);
}

function clearExtras(btn) {
    const multi = btn.form.querySelector('.extra-multi');
    if (!multi) return;
    Array.prototype.forEach.call(multi.options, function (o) { o.selected = false; });
    updateExtraUi(multi);
}

// Preset thời hạn nhanh (+N ngày) — tự điền giờ/ngày/tháng và bỏ "Vĩnh viễn"
function quickExpiry(btn, days) {
    const form = btn.form;
    const dt = form.querySelector('input[name="expiry_datetime"]');
    if (!dt) return;
    const foreverCb = form.querySelector('input[name="forever"]');
    const now = new Date();
    now.setDate(now.getDate() + days);
    const pad = function (n) { return ('0' + n).slice(-2); };
    dt.value = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    if (foreverCb) foreverCb.checked = false;
    updateForeverState(foreverCb, false);
}

function updateForeverState(cb, focus) {
    const form = cb.form;
    const dt = form.querySelector('input[name="expiry_datetime"]');
    if (!dt) return;
    if (cb.checked) {
        dt.disabled = true;
        dt.value = '';
    } else {
        dt.disabled = false;
        if (focus) dt.focus();
    }
}

// Tích 'Vĩnh viễn' => bỏ giá trị ngày giờ; bỏ tích => nhập thời điểm hết hạn cụ thể
function toggleForever(cb) {
    updateForeverState(cb, true);
}

// Đồng bộ bộ đếm & thẻ quyền cấp thêm khi trang tải xong
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.extra-multi').forEach(function (sel) { updateExtraUi(sel); });
});

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
