<?php
require_once __DIR__ . '/../config/db.php';

// Gọi duy nhất 1 lần hàm kiểm tra quyền ngay đầu file
checkRole(['admin', 'daotao']);

// Kiểm tra quyền Admin
$is_admin = isset($_SESSION['user']['quyen']) && $_SESSION['user']['quyen'] === 'admin';

// Quyền SỬA / XÓA học phần: mặc định KHÔNG có, chỉ admin hoặc tài khoản được cấp thêm
$can_edit_hp   = $is_admin || hasExtraPerm($pdo, 'sua_hocphan');
$can_delete_hp = $is_admin || hasExtraPerm($pdo, 'xoa_hocphan');

$message = '';
$error = '';

// Kiểm tra sự tồn tại của bảng khoa
$hasKhoa = false;
try {
    $hasKhoa = $pdo->query("SHOW TABLES LIKE 'khoa'")->rowCount() > 0;
} catch (Exception $e) {}

// ==========================================
// 1. XỬ LÝ FORM: THÊM / CẬP NHẬT HỌC PHẦN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_subject'])) {
    $maHPOld = trim($_POST['ma_hp_old'] ?? '');
    $maHP = trim($_POST['ma_hp'] ?? '');
    $tenHP = trim($_POST['ten_hp'] ?? '');
    $soTC = intval($_POST['so_tc'] ?? 0);
    $hocKy = intval($_POST['hoc_ky'] ?? 1);
    $nganhHoc = trim($_POST['nganh_hoc'] ?? '');
    $maKhoa = trim($_POST['ma_khoa'] ?? '');

    // Nếu là thao tác Cập nhật mà không phải Admin -> Chặn
    if (!empty($maHPOld) && !$can_edit_hp) {
        die("Bạn không có quyền chỉnh sửa học phần!");
    }

    if (!empty($maHP) && !empty($tenHP) && $soTC > 0) {
        try {
            if (!empty($maHPOld) && $can_edit_hp) {
                // Cập nhật học phần (Chỉ Admin)
                if ($hasKhoa && !empty($maKhoa)) {
                    $stmt = $pdo->prepare("UPDATE hocphan SET MaHP = ?, TenHP = ?, SoTC = ?, HocKy = ?, NganhHoc = ?, MaKhoa = ? WHERE MaHP = ?");
                    $stmt->execute([$maHP, $tenHP, $soTC, $hocKy, $nganhHoc ?: null, $maKhoa, $maHPOld]);
                } else {
                    $stmt = $pdo->prepare("UPDATE hocphan SET MaHP = ?, TenHP = ?, SoTC = ?, HocKy = ?, NganhHoc = ? WHERE MaHP = ?");
                    $stmt->execute([$maHP, $tenHP, $soTC, $hocKy, $nganhHoc ?: null, $maHPOld]);
                }
                $message = "Cập nhật học phần thành công!";
            } else {
                // Thêm mới học phần (Cả Admin và Đào tạo)
                if ($hasKhoa && !empty($maKhoa)) {
                    $stmt = $pdo->prepare("INSERT INTO hocphan (MaHP, TenHP, SoTC, HocKy, NganhHoc, MaKhoa) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$maHP, $tenHP, $soTC, $hocKy, $nganhHoc ?: null, $maKhoa]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO hocphan (MaHP, TenHP, SoTC, HocKy, NganhHoc) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$maHP, $tenHP, $soTC, $hocKy, $nganhHoc ?: null]);
                }
                $message = "Thêm học phần mới thành công!";
            }
        } catch (Exception $e) {
            $error = "Mã học phần bị trùng lặp hoặc gặp lỗi CSDL!";
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin Học phần!";
    }
}

// ==========================================
// 2. XỬ LÝ IMPORT HỌC PHẦN TỪ FILE EXCEL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $excel_data = json_decode($_POST['excel_data'] ?? '[]', true);

    if (!empty($excel_data) && is_array($excel_data)) {
        $count_added = 0;
        $count_updated = 0;

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM hocphan WHERE MaHP = ?");
        
        if ($hasKhoa) {
            $stmt_insert = $pdo->prepare("INSERT INTO hocphan (MaHP, TenHP, SoTC, HocKy, NganhHoc, MaKhoa) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_update = $pdo->prepare("UPDATE hocphan SET TenHP = ?, SoTC = ?, HocKy = ?, NganhHoc = ?, MaKhoa = ? WHERE MaHP = ?");
        } else {
            $stmt_insert = $pdo->prepare("INSERT INTO hocphan (MaHP, TenHP, SoTC, HocKy, NganhHoc) VALUES (?, ?, ?, ?, ?)");
            $stmt_update = $pdo->prepare("UPDATE hocphan SET TenHP = ?, SoTC = ?, HocKy = ?, NganhHoc = ? WHERE MaHP = ?");
        }

        foreach ($excel_data as $row) {
            // Đọc tiêu đề cột linh hoạt (hỗ trợ Tiếng Việt có dấu, không dấu hoặc chữ hoa/thường)
            $maHP  = trim($row['Mã HP'] ?? $row['Mã Học Phần'] ?? $row['MaHP'] ?? $row['ma_hp'] ?? '');
            $tenHP = trim($row['Tên Học Phần'] ?? $row['Tên HP'] ?? $row['TenHP'] ?? $row['ten_hp'] ?? '');
            $soTC  = intval($row['Số Tín Chỉ'] ?? $row['Số TC'] ?? $row['SoTC'] ?? $row['so_tc'] ?? 0);
            $hocKy = intval($row['Học Kỳ Chuẩn'] ?? $row['Học Kỳ'] ?? $row['HocKy'] ?? $row['hoc_ky'] ?? 1);
            $nganhHoc = trim($row['Ngành Học'] ?? $row['Ngành'] ?? $row['NganhHoc'] ?? $row['nganh_hoc'] ?? '');
            $maKhoa = trim($row['Mã Khoa'] ?? $row['MaKhoa'] ?? $row['ma_khoa'] ?? '');

            if (!empty($maHP) && !empty($tenHP) && $soTC > 0) {
                $stmt_check->execute([$maHP]);
                $exists = $stmt_check->fetchColumn() > 0;

                if ($exists) {
                    // Cập nhật nếu đã tồn tại và có quyền Admin
                    if ($can_edit_hp) {
                        if ($hasKhoa) {
                            $stmt_update->execute([$tenHP, $soTC, $hocKy, $nganhHoc ?: null, $maKhoa ?: null, $maHP]);
                        } else {
                            $stmt_update->execute([$tenHP, $soTC, $hocKy, $nganhHoc ?: null, $maHP]);
                        }
                        $count_updated++;
                    }
                } else {
                    // Thêm mới nếu chưa tồn tại
                    if ($hasKhoa) {
                        $stmt_insert->execute([$maHP, $tenHP, $soTC, $hocKy, $nganhHoc ?: null, $maKhoa ?: null]);
                    } else {
                        $stmt_insert->execute([$maHP, $tenHP, $soTC, $hocKy, $nganhHoc ?: null]);
                    }
                    $count_added++;
                }
            }
        }
        $message = "Import thành công! (Thêm mới: $count_added, Cập nhật: $count_updated)";
    } else {
        $error = "Dữ liệu Import không hợp lệ!";
    }
}

// ==========================================
// 3. XỬ LÝ XÓA HỌC PHẦN (CHỈ ADMIN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!$can_delete_hp) {
        die("Bạn không có quyền xóa học phần!");
    }
    
    $delMaHP = trim($_POST['ma_hp'] ?? '');
    if (!empty($delMaHP)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM hocphan WHERE MaHP = ?");
            $stmt->execute([$delMaHP]);
            header("Location: courses.php?deleted=1");
            exit;
        } catch (Exception $e) {
            $error = "Không thể xóa học phần này vì đã có dữ liệu điểm hoặc sinh viên đăng ký!";
        }
    }
}

if (isset($_GET['deleted'])) {
    $message = "Xóa học phần thành công!";
}

// Lấy thông tin học phần cần sửa (nếu có - Chỉ Admin)
$edit_hp = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && $can_edit_hp) {
    $editMaHP = trim($_GET['ma_hp'] ?? '');
    if (!empty($editMaHP)) {
        $stmt = $pdo->prepare("SELECT * FROM hocphan WHERE MaHP = ?");
        $stmt->execute([$editMaHP]);
        $edit_hp = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// ==========================================
// 4. TRUY VẤN DỮ LIỆU BẢNG
// ==========================================
$danhSachKhoa = [];
if ($hasKhoa) {
    try { $danhSachKhoa = $pdo->query("SELECT * FROM khoa ORDER BY TenKhoa ASC")->fetchAll(); } catch (Exception $e) {}
}

// Danh sách ngành học (lấy từ các lớp trong hệ thống)
$danhSachNganh = [];
try {
    $danhSachNganh = $pdo->query("SELECT DISTINCT NganhHoc FROM lop WHERE NganhHoc IS NOT NULL AND NganhHoc <> '' ORDER BY NganhHoc ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
// Nếu học phần đang sửa có ngành chưa nằm trong danh sách thì bổ sung để hiển thị đúng
if ($edit_hp && !empty($edit_hp['NganhHoc']) && !in_array($edit_hp['NganhHoc'], $danhSachNganh, true)) {
    $danhSachNganh[] = $edit_hp['NganhHoc'];
    sort($danhSachNganh, SORT_NATURAL | SORT_FLAG_CASE);
}

$danhSachHocPhan = [];
try { 
    $danhSachHocPhan = $pdo->query("SELECT * FROM hocphan ORDER BY TenHP ASC")->fetchAll(); 
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Học Phần</title>
    <!-- Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JS Xuất & Nhập file Excel / PDF -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #f8fafc; color: #1e293b; padding: 30px 0; }
        
        .main-wrapper { width: 95%; max-width: 1300px; margin: 0 auto; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 22px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #64748b; background: #e2e8f0; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }

        .ui-card { background: #eef2f7; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        .form-control { width: 100%; height: 42px; background: #f8fafc; border: 1px solid #cbd5e1; color: #1e293b; padding: 0 14px; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: #6366f1; }
        
        .grid-3 { display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 16px; margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
        .full-row { margin-bottom: 16px; }
        @media (max-width: 900px) { .grid-4 { grid-template-columns: 1fr 1fr; } }
        
        .form-actions { display: flex; gap: 12px; justify-content: flex-end; }
        .btn-submit { background: #6366f1; color: white; border: none; height: 42px; padding: 0 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background: #4f46e5; }
        .btn-add { background: #10b981; }
        .btn-add:hover { background: #059669; }
        .btn-cancel { background: #475569; color: white; text-decoration: none; height: 42px; padding: 0 24px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; }
        .btn-cancel:hover { background: #e2e8f0; }

        .alert { padding: 14px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid #10b981; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid #ef4444; }
        
        /* CSS Bảng Dữ Liệu */
        .table-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .btn-import { background: #0284c7; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
        .btn-import:hover { background: #0369a1; }
        .btn-excel { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
        .btn-excel:hover { background: #059669; }
        .btn-pdf { background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
        .btn-pdf:hover { background: #dc2626; }

        .search-box { position: relative; width: 260px; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 13px; }
        .search-box input { padding-left: 36px; height: 38px; }

        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; padding: 14px 16px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
        td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; color: #475569; }
        tbody tr:hover { background: #e2e8f0; }
        
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        .btn-action { text-decoration: none; font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 6px; margin: 0 2px; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-edit { color: #ffffff; background: #3b82f6; }
        .btn-edit:hover { background: #2563eb; }
        .btn-delete { color: #ffffff; background: #ef4444; }
        .btn-delete:hover { background: #dc2626; }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- HEADER -->
        <div class="header-bar">
            <div class="page-title">
                <i class="fa-solid fa-book-open" style="color: #6366f1;"></i> Quản Lý Học Phần
            </div>
            <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Quay lại Trang chủ</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- CARD FORM: THÊM / CHỈNH SỬA HỌC PHẦN -->
        <div class="ui-card">
            <div class="card-title">
                <i class="fa-solid <?= ($edit_hp &&$can_edit_hp) ? 'fa-pen-to-square' : 'fa-plus-circle' ?>" style="color: #10b981;"></i> 
                <?= ($edit_hp &&$can_edit_hp) ? "Cập Nhật Học Phần" : "Thêm Học Phần Mới" ?>
            </div>
            <form method="POST" action="courses.php">
                <?php csrf_field(); ?>
                <input type="hidden" name="save_subject" value="1">
                <input type="hidden" name="ma_hp_old" value="<?= htmlspecialchars($edit_hp['MaHP'] ?? '') ?>">

                <div class="grid-4">
                    <div>
                        <select name="nganh_hoc" class="form-control" required>
                            <option value="">-- Chọn Ngành Học --</option>
                            <?php foreach ($danhSachNganh as $n): ?>
                                <option value="<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>" <?= ($edit_hp && isset($edit_hp['NganhHoc']) && $edit_hp['NganhHoc'] === $n) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($n) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <input type="text" name="ma_hp" class="form-control" placeholder="Mã Học Phần (VD: INT101)" value="<?= htmlspecialchars($edit_hp['MaHP'] ?? '') ?>" required>
                    </div>
                    <div>
                        <input type="number" name="so_tc" class="form-control" placeholder="Số Tín Chỉ" min="1" max="10" value="<?= htmlspecialchars($edit_hp['SoTC'] ?? '') ?>" required>
                    </div>
                    <div>
                        <select name="hoc_ky" class="form-control" required>
                            <option value="">-- Kỳ Học (1-8) --</option>
                            <?php for($i=1; $i<=8; $i++): ?>
                                <option value="<?= $i ?>" <?= ($edit_hp && $edit_hp['HocKy'] ==$i) ? 'selected' : '' ?>>Học kỳ <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="full-row">
                    <input type="text" name="ten_hp" class="form-control" placeholder="Tên Học Phần (VD: Phát triển ứng dụng Web)" value="<?= htmlspecialchars($edit_hp['TenHP'] ?? '') ?>" required>
                </div>
                <?php if ($hasKhoa): ?>
                <div class="full-row">
                    <select name="ma_khoa" class="form-control">
                        <option value="">-- Thuộc Khoa / Ngành --</option>
                        <?php foreach ($danhSachKhoa as$k): ?>
                            <option value="<?= htmlspecialchars($k['MaKhoa']) ?>" <?= ($edit_hp && isset($edit_hp['MaKhoa']) && $edit_hp['MaKhoa'] ==$k['MaKhoa']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['TenKhoa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <?php if ($edit_hp &&$can_edit_hp): ?>
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> Cập Nhật Học Phần</button>
                        <a href="courses.php" class="btn-cancel">Hủy Bỏ</a>
                    <?php else: ?>
                        <button type="submit" class="btn-submit btn-add"><i class="fa-solid fa-plus"></i> Tạo Học Phần Mới</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- CARD BẢNG DANH SÁCH HỌC PHẦN -->
        <div class="ui-card">
            <div class="table-header-bar">
                <div class="card-title" style="margin-bottom: 0;">
                    <i class="fa-solid fa-list-check" style="color: #0284c7;"></i> Danh Sách Học Phần Hiện Có
                </div>

                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="tableSearch" class="form-control" onkeyup="filterSubjectTable()" placeholder="Lọc mã, tên học phần...">
                    </div>

                    <!-- Button & File Input Import Excel -->
                    <button type="button" class="btn-import" onclick="document.getElementById('excelFileInput').click()">
                        <i class="fa-solid fa-file-import"></i> Nhập Từ Excel
                    </button>
                    <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display: none;" onchange="importExcel(this)">

                    <?php if (count($danhSachHocPhan) > 0): ?>
                        <button onclick="exportTableToExcel('subjectTable', 'Danh_Sach_Hoc_Phan')" class="btn-excel">
                            <i class="fa-solid fa-file-excel"></i> Xuất File Excel
                        </button>
                        <button onclick="exportTableToPDF('subjectTable', 'Danh_Sach_Hoc_Phan')" class="btn-pdf">
                            <i class="fa-solid fa-file-pdf"></i> Xuất File PDF
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table id="subjectTable">
                    <thead>
                        <tr>
                            <th style="width: 120px;" class="text-center">Mã HP</th>
                            <th class="text-left">Tên Học Phần</th>
                            <th style="width: 120px;" class="text-center">Số Tín Chỉ</th>
                            <th style="width: 150px;" class="text-center">Học Kỳ Chuẩn</th>
                            <th style="width: 220px;" class="text-left">Ngành Học</th>
                            <?php if ($can_edit_hp || $can_delete_hp): ?>
                                <th style="width: 160px;" class="text-center" data-exclude="true">Thao Tác</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($danhSachHocPhan) > 0): ?>
                            <?php foreach ($danhSachHocPhan as$hp): ?>
                                <tr>
                                    <td class="text-center"><strong style="color: #6366f1;"><?= htmlspecialchars($hp['MaHP']) ?></strong></td>
                                    <td class="text-left"><strong style="color: #1e293b;"><?= htmlspecialchars($hp['TenHP']) ?></strong></td>
                                    <td class="text-center"><?= htmlspecialchars($hp['SoTC']) ?></td>
                                    <td class="text-center">Học kỳ <?= htmlspecialchars($hp['HocKy']) ?></td>
                                    <td class="text-left"><?= !empty($hp['NganhHoc']) ? htmlspecialchars($hp['NganhHoc']) : '—' ?></td>
                                    <?php if ($can_edit_hp || $can_delete_hp): ?>
                                        <td class="text-center" data-exclude="true">
                                            <?php if ($can_edit_hp): ?>
                                            <a href="courses.php?action=edit&ma_hp=<?= urlencode($hp['MaHP']) ?>" class="btn-action btn-edit"><i class="fa-solid fa-pen"></i> Sửa</a>
                                            <?php endif; ?>
                                            <?php if ($can_delete_hp): ?>
                                            <form method="POST" action="courses.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa học phần này không?')">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="ma_hp" value="<?= htmlspecialchars($hp['MaHP'], ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn-action btn-delete"><i class="fa-solid fa-trash"></i> Xóa</button>
</form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?= ($can_edit_hp || $can_delete_hp) ? 6 : 5 ?>" class="text-center" style="color: #64748b; padding: 30px;">Chưa có học phần nào trong hệ thống.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form ẩn gửi dữ liệu JSON Import lên Server -->
    <form id="importExcelForm" action="courses.php" method="POST" style="display: none;">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="import_excel">
        <input type="hidden" name="excel_data" id="excelDataInput">
    </form>

    <script>
        // Hàm Lọc nhanh danh sách học phần bằng JS
        function filterSubjectTable() {
            const filter = document.getElementById("tableSearch").value.toLowerCase();
            const rows = document.querySelectorAll("#subjectTable tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }

        // Đọc và Import dữ liệu từ file Excel
        function importExcel(input) {
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];

                    // Chuyển Sheet thành JSON
                    const jsonData = XLSX.utils.sheet_to_json(worksheet, { raw: false });

                    if (jsonData.length === 0) {
                        alert('File Excel không chứa dữ liệu hợp lệ!');
                        input.value = '';
                        return;
                    }

                    if (confirm(`Tìm thấy ${jsonData.length} học phần trong file. Bạn có chắc chắn muốn nhập vào hệ thống?`)) {
                        document.getElementById('excelDataInput').value = JSON.stringify(jsonData);
                        document.getElementById('importExcelForm').submit();
                    } else {
                        input.value = '';
                    }
                } catch (error) {
                    alert('Lỗi khi đọc file Excel: ' + error.message);
                    input.value = '';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        // Hàm xuất bảng dữ liệu ra file Excel
        function exportTableToExcel(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var clonedTable = table.cloneNode(true);
            var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
            excludedElements.forEach(el => el.remove());

            var wb = XLSX.utils.table_to_book(clonedTable, {sheet: "HocPhan"});
            filename = filename ? filename + '.xlsx' : 'Danh_Sach_Hoc_Phan.xlsx';
            XLSX.writeFile(wb, filename);
        }

        // Hàm xuất bảng dữ liệu ra file PDF
        function exportTableToPDF(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.color = '#000';
            wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

            var title = document.createElement('h2');
            title.innerText = 'DANH SÁCH HỌC PHẦN';
            title.style.textAlign = 'center';
            title.style.marginBottom = '20px';
            wrapper.appendChild(title);

            var clonedTable = table.cloneNode(true);
            var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
            excludedElements.forEach(el => el.remove());

            clonedTable.style.width = '100%';
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #333';
                cell.style.padding = '8px';
                cell.style.fontSize = '12px';
                cell.style.color = '#000';
                cell.style.backgroundColor = '#fff';
            });

            wrapper.appendChild(clonedTable);

            filename = filename ? filename + '.pdf' : 'Danh_Sach_Hoc_Phan.pdf';

            var opt = {
                margin:       10,
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(wrapper).save();
        }
    </script>
<script src="../assets/js/export_utils.js"></script>
<script>
if (typeof exportTableToExcel !== 'undefined') { exportTableToExcel = function(t, fn) { improvedExportToExcel(t, fn); }; }
if (typeof exportTableToPDF !== 'undefined') { exportTableToPDF = function(t, fn) { improvedExportToPDF(t, fn); }; }
</script>
</body>
</html>


