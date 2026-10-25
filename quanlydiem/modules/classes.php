<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/lop_helpers.php';

// Kiểm tra quyền truy cập (Chỉ Admin và Công tác sinh viên)
checkRole(['admin', 'congtac', 'congtacsinhvien']);

// Kiểm tra quyền Admin
$is_admin = isset($_SESSION['user']['quyen']) &&$_SESSION['user']['quyen'] === 'admin';

// Quyền SỬA / XÓA lớp học: mặc định KHÔNG có, chỉ admin hoặc tài khoản được cấp thêm
$can_edit_lop   = $is_admin || hasExtraPerm($pdo, 'sua_lophoc');
$can_delete_lop = $is_admin || hasExtraPerm($pdo, 'xoa_lophoc');

// XỬ LÝ LƯU / CẬP NHẬT LỚP HỌC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_class') {
    $ma_lop_old = trim($_POST['ma_lop_old'] ?? '');
    $ma_lop     = trim($_POST['ma_lop'] ?? '');
    $ten_lop    = trim($_POST['ten_lop'] ?? '');
    $si_so      = (int)($_POST['si_so'] ?? 0);
    $nganh_hoc  = trim($_POST['nganh_hoc'] ?? '');

    // Giới hạn sĩ số (để trống / 0 => dùng mặc định 50)
    $gioi_han = isset($_POST['gioi_han']) && trim($_POST['gioi_han']) !== '' ? (int)$_POST['gioi_han'] : 0;
    if ($gioi_han < 0) { $gioi_han = 0; }
    $gioi_han_db = $gioi_han > 0 ? $gioi_han : null;

    // Nếu cố tình gửi request sửa mà không phải Admin -> Chặn lại
    if ($ma_lop_old !== '' && !$can_edit_lop) {
        die("Bạn không có quyền sửa thông tin lớp học!");
    }

    if ($ma_lop &&$ten_lop) {
        $moved   = 0;
        $lop_moi = [];

        if ($ma_lop_old !== '' &&$can_edit_lop) {
            // Sửa lớp học (Chỉ Admin) — bao gồm cả giới hạn sĩ số
            $pdo->beginTransaction();
            try {
                // Đổi mã lớp => chuyển luôn sinh viên / lớp học phần đang thuộc
                if ($ma_lop !== $ma_lop_old) {
                    $pdo->prepare("UPDATE sinhvien SET MaLop = ? WHERE MaLop = ?")->execute([$ma_lop, $ma_lop_old]);
                    $pdo->prepare("UPDATE phanlop SET malop = ? WHERE malop = ?")->execute([$ma_lop, $ma_lop_old]);
                }
                $sql = "UPDATE lop SET MaLop = ?, TenLop = ?, SiSo = ?, GioiHanSinhVien = ?, NganhHoc = ? WHERE MaLop = ?";
                $pdo->prepare($sql)->execute(array($ma_lop, $ten_lop, $si_so, $gioi_han_db, $nganh_hoc, $ma_lop_old));

                // Nếu giới hạn mới nhỏ hơn sĩ số thật => tự chuyển bớt SV sang lớp cùng ngành hoặc tạo lớp mới
                if ($gioi_han > 0) {
                    $kq       = phanBoVuotGioiHan($pdo, $ma_lop, $gioi_han);
                    $moved    = (int)$kq['chuyen'];
                    $lop_moi  = $kq['lop_moi'];
                }
                $pdo->commit();
            } catch (Exception $ex) {
                $pdo->rollBack();
                throw $ex;
            }
        } else {
            // Thêm mới lớp học (Cả Admin và Congtac)
            $sql = "INSERT INTO lop (MaLop, TenLop, SiSo, GioiHanSinhVien, NganhHoc) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute(array($ma_lop, $ten_lop, $si_so, $gioi_han_db, $nganh_hoc));
        }

        $qs = 'success=1';
        if ($moved > 0) {
            $qs .= '&moved=' . $moved;
            if (!empty($lop_moi)) {
                $qs .= '&newclass=' . urlencode(implode(', ', $lop_moi));
            }
        }
        header("Location: classes.php?$qs");
        exit;
    }
}

// XỬ LÝ IMPORT TỪ FILE EXCEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $excel_data = json_decode($_POST['excel_data'] ?? '[]', true);
    
    if (!empty($excel_data) && is_array($excel_data)) {$count_added = 0;
        $count_updated = 0;

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM lop WHERE MaLop = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO lop (MaLop, TenLop, SiSo, NganhHoc) VALUES (?, ?, ?, ?)");
        $stmt_update = $pdo->prepare("UPDATE lop SET TenLop = ?, SiSo = ?, NganhHoc = ? WHERE MaLop = ?");

        foreach ($excel_data as $row) {
            // Tìm theo tiêu đề cột Tiếng Việt hoặc tiêu đề viết thường/viết liền
            $ma_lop   = trim($row['Mã Lớp'] ?? $row['MaLop'] ?? $row['ma_lop'] ?? '');
            $ten_lop  = trim($row['Tên Lớp'] ?? $row['TenLop'] ?? $row['ten_lop'] ?? '');
            $si_so    = (int)($row['Sĩ Số'] ?? $row['SiSo'] ?? $row['si_so'] ?? 0);
            $nganh_hoc= trim($row['Ngành Học'] ?? $row['NganhHoc'] ?? $row['nganh_hoc'] ?? '');

            if ($ma_lop !== '' &&$ten_lop !== '') {
                $stmt_check->execute([$ma_lop]);
                $exists = $stmt_check->fetchColumn() > 0;

                if ($exists) {
                    if ($can_edit_lop) {
                        $stmt_update->execute([$ten_lop, $si_so, $nganh_hoc, $ma_lop]); $count_updated++;
                    }
                } else {
                    $stmt_insert->execute([$ma_lop, $ten_lop, $si_so, $nganh_hoc]); $count_added++;
                }
            }
        }
        header("Location: classes.php?imported=1&added=$count_added&updated=$count_updated");
        exit;
    }
}

// XỬ LÝ XÓA LỚP HỌC (CHỈ ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!$can_delete_lop) {
        die("Bạn không có quyền xóa lớp học!");
    }
    
    $del_ma_lop = trim($_POST['ma_lop'] ?? '');
    if ($del_ma_lop) {
        try {
            $sql = "DELETE FROM lop WHERE MaLop = ?";
            $pdo->prepare($sql)->execute(array($del_ma_lop));
            header("Location: classes.php?deleted=1");
            exit;
        } catch (PDOException $e) {
            echo "<script>alert('Không thể xóa lớp này vì đang chứa sinh viên hoặc dữ liệu liên quan!'); window.location.href='classes.php';</script>";
            exit;
        }
    }
}

// LẤY DANH SÁCH LỚP HỌC
$ds_lop = $pdo->query("SELECT * FROM lop ORDER BY MaLop ASC")->fetchAll(PDO::FETCH_ASSOC);

// DANH SÁCH NGÀNH HỌC (để chọn thay vì nhập tay)
$ds_nganh = $pdo->query("SELECT DISTINCT NganhHoc FROM lop WHERE NganhHoc IS NOT NULL AND NganhHoc <> '' ORDER BY NganhHoc ASC")->fetchAll(PDO::FETCH_COLUMN);

// DANH SÁCH SINH VIÊN THEO TỪNG LỚP (để xem nhanh)
$sinhVienTheoLop = [];
try {
    $stmtSV = $pdo->query("SELECT MaSV, MaLop, HoTen, GioiTinh, NgaySinh, QueQuan FROM sinhvien ORDER BY MaSV ASC");
    while ($sv = $stmtSV->fetch(PDO::FETCH_ASSOC)) {
        $sinhVienTheoLop[$sv['MaLop']][] = $sv;
    }
} catch (Exception $e) { /* bỏ qua nếu chưa có bảng sinh viên */ }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Lớp Học - Hệ Thống Quản Lý Điểm</title>
    <!-- Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="/quanlydiem/assets/css/style.css?v=2.0">
    
    <!-- JS Xuất file Excel & PDF -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- ÉP GIAO DIỆN DARK MODE KHÔNG LO LỖI CACHE -->
    <style>
        body {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            padding: 24px 16px !important;
        }
        .container {
            max-width: 1100px !important;
            margin: 0 auto !important;
        }
        .btn-back {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            color: #64748b !important;
            text-decoration: none !important;
            margin-bottom: 20px !important;
            font-weight: 500 !important;
        }
        .btn-back:hover { color: #6366f1 !important; }
        
        .card {
            background: #ffffff !important;
            border-radius: 16px !important;
            padding: 24px !important;
            margin-bottom: 24px !important;
            border: 1px solid rgba(15, 23, 42, 0.08) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
        }
        .card-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 20px !important;
            flex-wrap: wrap !important;
            gap: 12px !important;
        }
        .card-title {
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            margin-bottom: 16px !important;
        }
        .card-header .card-title { margin-bottom: 0 !important; }

        .form-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
            gap: 16px !important;
        }
        .form-control {
            background-color: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
            padding: 11px 14px !important;
            border-radius: 10px !important;
            outline: none !important;
            width: 100% !important;
            font-size: 14px !important;
        }
        .form-control:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important;
        }
        .form-actions {
            grid-column: 1 / -1 !important;
            display: flex !important;
            gap: 12px !important;
            margin-top: 8px !important;
        }

        .btn-submit {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 10px 22px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35) !important;
        }
        .btn-cancel {
            background: #475569 !important;
            color: #ffffff !important;
            border: none !important;
            padding: 10px 20px !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            display: none;
        }

        .btn-excel {
            background: #10b981 !important;
            color: white !important;
            border: none !important;
            padding: 9px 16px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 13.5px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        .btn-import {
            background: #0284c7 !important;
            color: white !important;
            border: none !important;
            padding: 9px 16px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 13.5px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        .btn-pdf {
            background: #ef4444 !important;
            color: white !important;
            border: none !important;
            padding: 9px 16px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 13.5px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .table-responsive {
            width: 100% !important;
            overflow-x: auto !important;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin-top: 8px !important;
        }
        th, td {
            padding: 14px 16px !important;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08) !important;
            font-size: 14px !important;
            text-align: center !important;
        }
        th {
            background-color: #eef2f7 !important;
            color: #64748b !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            font-size: 12px !important;
            letter-spacing: 0.5px !important;
        }
        td { color: #475569 !important; }
        tbody tr:hover { background-color: rgba(15, 23, 42, 0.03) !important; }
        .text-left { text-align: left !important; }

        .btn-action {
            padding: 5px 12px !important;
            border-radius: 6px !important;
            text-decoration: none !important;
            font-size: 12.5px !important;
            font-weight: 600 !important;
            display: inline-block !important;
            margin-right: 4px !important;
        }
        .btn-edit { background-color: rgba(59, 130, 246, 0.2) !important; color: #2563eb !important; border: 1px solid rgba(59, 130, 246, 0.3) !important; }
        .btn-delete { background-color: rgba(239, 68, 68, 0.2) !important; color: #dc2626 !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; }
    </style>
</head>
<body>
    <div class="container">
        <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Quay lại trang chủ</a>

        <?php if (isset($_GET['imported'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Import dữ liệu thành công! 
                (Thêm mới: <strong><?= (int)($_GET['added'] ?? 0) ?></strong> lớp, 
                Cập nhật: <strong><?= (int)($_GET['updated'] ?? 0) ?></strong> lớp)
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?php if (isset($_GET['moved'])): ?>
                    Đã lưu giới hạn sĩ số. Lớp vượt giới hạn nên <strong><?= (int)$_GET['moved'] ?></strong> sinh viên đã được tự động chuyển sang lớp cùng ngành còn chỗ<?php if (!empty($_GET['newclass'])): ?> / lớp mới: <strong><?= htmlspecialchars(urldecode($_GET['newclass']), ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>.
                <?php else: ?>
                    Lưu thông tin lớp học thành công!
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- FORM THÊM LỚP HỌC -->
        <div class="card">
            <div class="card-title" id="formTitle">Thêm Lớp Học Mới</div>
            <form action="classes.php" method="POST" class="form-grid" id="classForm">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="save_class">
                <input type="hidden" name="ma_lop_old" id="ma_lop_old" value="">

                <div>
                    <input type="text" name="ma_lop" id="ma_lop" class="form-control" placeholder="Mã Lớp (VD: CNTT01)" required>
                </div>
                <div>
                    <input type="text" name="ten_lop" id="ten_lop" class="form-control" placeholder="Tên Lớp" required>
                </div>
                <div>
                    <input type="number" name="si_so" id="si_so" class="form-control" placeholder="Sĩ Số" min="0" value="0">
                </div>
                <div>
                    <input type="number" name="gioi_han" id="gioi_han" class="form-control" placeholder="Giới hạn sĩ số (để trống = 50)" min="0" value="" oninput="kiemTraGioiHan()">
                </div>
                <div id="gioiHanCanhBao" style="display:none; grid-column:1/-1; background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.4); color:#b91c1c; padding:10px 14px; border-radius:10px; font-size:13.5px; font-weight:500;"></div>
                <div>
                    <select name="nganh_hoc" id="nganh_hoc" class="form-control" required>
                        <option value="">-- Chọn Ngành Học --</option>
                        <?php foreach ($ds_nganh as $n): ?>
                            <option value="<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($n) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" id="btnSubmit">Thêm Lớp Học</button>
                    <button type="button" class="btn-cancel" id="btnCancel" onclick="resetForm()">Hủy</button>
                </div>
            </form>
        </div>

        <!-- BẢNG DANH SÁCH LỚP HỌC -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Danh Sách Lớp Học</div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <!-- Nút bấm chọn file Excel -->
                    <button type="button" class="btn-import" onclick="document.getElementById('excelFileInput').click()">
                        <i class="fa-solid fa-file-import"></i> Nhập Từ Excel
                    </button>
                    <!-- Input ẩn chứa file Excel -->
                    <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display: none;" onchange="importExcel(this)">

                    <?php if (count($ds_lop) > 0): ?>
                        <button onclick="exportTableToExcel('classTable', 'Danh_Sach_Lop_Hoc')" class="btn-excel">
                            <i class="fa-solid fa-file-excel"></i> Xuất File Excel
                        </button>
                        <button onclick="exportTableToPDF('classTable', 'Danh_Sach_Lop_Hoc')" class="btn-pdf">
                            <i class="fa-solid fa-file-pdf"></i> Xuất File PDF
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table id="classTable">
                    <thead>
                        <tr>
                            <th>Mã Lớp</th>
                            <th class="text-left">Tên Lớp</th>
                            <th>Sĩ Số</th>
                            <th>Giới Hạn</th>
                            <th class="text-left">Ngành Học</th>
                            <?php if ($can_edit_lop || $can_delete_lop): ?>
                                <th data-exclude="true">Thao Tác</th>
                            <?php endif; ?>
                            <th data-exclude="true" style="width: 110px; text-align:center;">DS SV</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ds_lop) > 0): ?>
                            <?php foreach ($ds_lop as$row): ?>
                                <?php
                                $mlopLop  = (string)($row['MaLop'] ?? '');
                                $soSV     = isset($sinhVienTheoLop[$mlopLop]) ? count($sinhVienTheoLop[$mlopLop]) : 0;
                                $ghLop    = gioiHanCuaLop($row);
                                $quaGioiHan = $soSV > $ghLop;
                                ?>
                                <tr<?= $quaGioiHan ? ' style="background: rgba(239,68,68,0.06) !important;"' : '' ?>>
                                    <td><strong style="color: #6366f1;"><?= htmlspecialchars($row['MaLop']) ?></strong></td>
                                    <td class="text-left"><?= htmlspecialchars($row['TenLop']) ?></td>
                                    <td><?= htmlspecialchars($row['SiSo']) ?></td>
                                    <td>
                                        <?= (int)$ghLop ?>
                                        <?php if ($quaGioiHan): ?>
                                            <br><span style="color: #dc2626; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-triangle-exclamation"></i> Vượt giới hạn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-left"><?= htmlspecialchars($row['NganhHoc'] ?? '-') ?></td>
                                    <?php if ($can_edit_lop || $can_delete_lop): ?>
                                        <td data-exclude="true">
                                            <?php if ($can_edit_lop): ?>
                                            <a href="javascript:void(0)" 
                                               onclick="editClass('<?= htmlspecialchars($row['MaLop']) ?>', '<?= htmlspecialchars(addslashes($row['TenLop'])) ?>', '<?= $row['SiSo'] ?>', '<?= (int)$ghLop ?>', '<?= htmlspecialchars(addslashes($row['NganhHoc'] ?? '')) ?>', <?= (int)$soSV ?>)" 
                                               class="btn-action btn-edit">Sửa</a>
                                            <?php endif; ?>
                                            <?php if ($can_delete_lop): ?>
                                            <form method="POST" action="classes.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa lớp này?')">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="ma_lop" value="<?= htmlspecialchars($row['MaLop'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn-action btn-delete">Xóa</button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td data-exclude="true" style="text-align:center;">
                                        <a href="javascript:void(0)" class="btn-action btn-edit"
                                           data-malop="<?= htmlspecialchars($row['MaLop'], ENT_QUOTES, 'UTF-8') ?>" onclick="xemDanhSachSV(this.dataset.malop)"
                                           title="Xem danh sách sinh viên">Xem (<?= $soSV ?>)</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= ($can_edit_lop || $can_delete_lop) ? 7 : 6 ?>" style="text-align: center; color: #64748b;">Chưa có dữ liệu lớp học.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form ẩn để submit dữ liệu Excel lên PHP -->
    <form id="importExcelForm" action="classes.php" method="POST" style="display: none;">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="import_excel">
        <input type="hidden" name="excel_data" id="excelDataInput">
    </form>

    <script>
        let editingMaLop = '';
        let editingSoSV  = 0;

        function editClass(maLop, tenLop, siSo, gioiHan, nganhHoc, soSV) {
            editingMaLop = maLop;
            editingSoSV  = parseInt(soSV || 0, 10);

            document.getElementById('formTitle').innerText = 'Cập Nhật Lớp Học'; 
            document.getElementById('ma_lop_old').value = maLop;
            document.getElementById('ma_lop').value = maLop;
            document.getElementById('ten_lop').value = tenLop;
            document.getElementById('si_so').value = siSo;
            document.getElementById('gioi_han').value = (parseInt(gioiHan, 10) > 0) ? gioiHan : '';
            document.getElementById('nganh_hoc').value = nganhHoc;

            document.getElementById('btnSubmit').innerText = 'Cập Nhật';
            document.getElementById('btnCancel').style.display = 'inline-block';

            kiemTraGioiHan();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            editingMaLop = '';
            editingSoSV  = 0;
            document.getElementById('formTitle').innerText = 'Thêm Lớp Học Mới';
            document.getElementById('ma_lop_old').value = '';
            document.getElementById('classForm').reset();
            document.getElementById('btnSubmit').innerText = 'Thêm Lớp Học';
            document.getElementById('btnCancel').style.display = 'none';
            document.getElementById('gioiHanCanhBao').style.display = 'none';
        }

        // Cảnh báo NGAY khi người dùng chỉnh sửa giới hạn sĩ số mà sĩ số hiện tại vượt quá
        function kiemTraGioiHan() {
            const warn = document.getElementById('gioiHanCanhBao');
            const gioiHan = parseInt(document.getElementById('gioi_han').value, 10);
            if (!editingMaLop || isNaN(gioiHan) || gioiHan <= 0 || editingSoSV <= gioiHan) {
                warn.style.display = 'none';
                return;
            }
            const soThua = editingSoSV - gioiHan;
            warn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Lớp <strong>' + escapeHtml(editingMaLop) + '</strong> hiện có <strong>' + editingSoSV + '</strong> sinh viên nhưng giới hạn mới là <strong>' + gioiHan + '</strong> (vượt <strong>' + soThua + '</strong>). Khi lưu, hệ thống sẽ <strong>tự động chuyển ' + soThua + ' sinh viên</strong> sang lớp cùng ngành còn chỗ hoặc tạo lớp mới để bổ sung.';
            warn.style.display = 'block';
        }

        // Xác nhận trước khi lưu nếu lớp sẽ bị vượt giới hạn
        document.getElementById('classForm').addEventListener('submit', function (e) {
            if (!editingMaLop) return; // Thêm mới: không cần xác nhận này
            const gioiHan = parseInt(document.getElementById('gioi_han').value, 10);
            if (isNaN(gioiHan) || gioiHan <= 0 || editingSoSV <= gioiHan) return;
            const soThua = editingSoSV - gioiHan;
            const ok = confirm(
                '⚠️ LỚP VƯỢT GIỚI HẠN SĨ SỐ\n\n' +
                'Lớp ' + editingMaLop + ' hiện có ' + editingSoSV + ' sinh viên, vượt giới hạn mới ' + gioiHan + '.\n\n' +
                'Hệ thống sẽ tự động chuyển ' + soThua + ' sinh viên sang lớp cùng ngành còn chỗ, ' +
                'hoặc tạo lớp mới để bổ sung.\n\nBạn có chắc chắn muốn lưu thay đổi này?'
            );
            if (!ok) { e.preventDefault(); }
        });

        // Hàm xử lý Import Excel từ đĩa
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
                    
                    // Chuyển dữ liệu Sheet thành JSON
                    const jsonData = XLSX.utils.sheet_to_json(worksheet);

                    if (jsonData.length === 0) {
                        alert('File Excel rỗng hoặc không có dữ liệu hợp lệ!');
                        input.value = '';
                        return;
                    }

                    if (confirm(`Tìm thấy ${jsonData.length} hàng dữ liệu. Bạn có chắc muốn import vào hệ thống?`)) {
                        document.getElementById('excelDataInput').value = JSON.stringify(jsonData);
                        document.getElementById('importExcelForm').submit();
                    } else {
                        input.value = '';
                    }
                } catch (error) {
                    alert('Lỗi đọc file Excel: ' + error.message);
                    input.value = '';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        // Hàm xuất Excel
        function exportTableToExcel(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var clonedTable = table.cloneNode(true);
            var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
            excludedElements.forEach(el => el.remove());

            var wb = XLSX.utils.table_to_book(clonedTable, {sheet: "LopHoc"});
            filename = filename ? filename + '.xlsx' : 'Danh_Sach_Lop_Hoc.xlsx';
            XLSX.writeFile(wb, filename);
        }

        // Hàm xuất PDF
        function exportTableToPDF(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.color = '#000';
            wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

            var title = document.createElement('h2');
            title.innerText = 'DANH SÁCH LỚP HỌC';
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

            filename = filename ? filename + '.pdf' : 'Danh_Sach_Lop_Hoc.pdf';

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


<div id="modalDSSV" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:flex-start; justify-content:center; padding-top:40px;">
    <div style="background:#fff; width:min(1100px, 94vw); max-height:88vh; border-radius:16px; box-shadow:0 20px 60px rgba(15,23,42,0.35); overflow:hidden; display:flex; flex-direction:column;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #e2e8f0;">
            <div style="font-weight:800; color:#1e293b; font-size:16px;">Danh Sách Sinh Viên - <span id="modalTenLop"></span></div>
            <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" class="btn-excel" onclick="exportDSVVExcel()" style="padding:6px 12px;"><i class="fa-solid fa-file-excel"></i> Xuất Excel</button>
                <button type="button" class="btn-pdf" onclick="exportDSSVPDF()" style="padding:6px 12px;"><i class="fa-solid fa-file-pdf"></i> Xuất PDF</button>
                <button type="button" onclick="dongDSSV()" style="border:none; background:transparent; font-size:18px; cursor:pointer; color:#475569;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div style="padding:16px 20px; flex:1; overflow:auto;">
            <div class="table-responsive">
                <table id="dsSVTable">
                    <thead>
                        <tr>
                            <th style="width:90px;">STT</th>
                            <th style="width:140px;">Mã SV</th>
                            <th class="text-left">Họ Tên</th>
                            <th style="width:90px;">Giới Tính</th>
                            <th style="width:120px;">Ngày Sinh</th>
                            <th class="text-left">Quê Quán</th>
                        </tr>
                    </thead>
                    <tbody id="dsSVBody"></tbody>
                </table>
            </div>
            <div id="dsSVEmpty" style="text-align:center; color:#64748b; padding:30px; display:none;">
                <i class="fa-solid fa-inbox" style="font-size:32px; opacity:0.5; margin-bottom:10px;"></i>
                <div>Không có sinh viên trong lớp này.</div>
            </div>
        </div>
        <div style="padding:12px 20px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn-cancel" style="display:inline-block;" onclick="dongDSSV()">Đóng</button>
        </div>
    </div>
</div>

<script id="dssv-data" type="application/json"><?= json_encode($sinhVienTheoLop, JSON_UNESCAPED_UNICODE) ?></script>

<script>
const DSSV_DATA = JSON.parse(document.getElementById('dssv-data').textContent);
let currentLop = '';
function xemDanhSachSV(maLop) {
    currentLop = maLop;
    document.getElementById('modalTenLop').textContent = maLop;
    const body = document.getElementById('dsSVBody');
    body.innerHTML = '';
    const list = DSSV_DATA[maLop] || [];
    if (list.length === 0) {
        document.getElementById('dsSVEmpty').style.display = 'block';
        document.getElementById('dsSVTable').style.display = 'none';
    } else {
        document.getElementById('dsSVEmpty').style.display = 'none';
        document.getElementById('dsSVTable').style.display = 'table';
        list.forEach((sv, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td>' + (idx+1) + '</td>' +
                          '<td><strong style="color:#6366f1;">' + escapeHtml(sv.MaSV) + '</strong></td>' +
                          '<td class="text-left">' + escapeHtml(sv.HoTen) + '</td>' +
                          '<td>' + escapeHtml(sv.GioiTinh) + '</td>' +
                          '<td>' + (sv.NgaySinh || '') + '</td>' +
                          '<td class="text-left">' + escapeHtml(sv.QueQuan) + '</td>';
            body.appendChild(tr);
        });
    }
    document.getElementById('modalDSSV').style.display = 'flex';
}
function dongDSSV() {
    document.getElementById('modalDSSV').style.display = 'none';
}
function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});
}
function exportDSVVExcel() {
    if (currentLop === '') return;
    const list = DSSV_DATA[currentLop] || [];
    const wsData = [['STT','Mã SV','Họ Tên','Giới Tính','Ngày Sinh','Quê Quán']];
    list.forEach((sv, idx) => {
        wsData.push([idx+1, sv.MaSV, sv.HoTen, sv.GioiTinh, sv.NgaySinh || '', sv.QueQuan || '']);
    });
    const ws = XLSX.utils.aoa_to_sheet(wsData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'DanhSachSV');
    XLSX.writeFile(wb, 'Danh_Sach_SV_' + currentLop + '.xlsx');
}
function exportDSSVPDF() {
    const table = document.getElementById('dsSVTable');
    if (!table) return;
    const wrapper = document.createElement('div');
    wrapper.style.padding='20px';
    wrapper.style.color='#000';
    const title = document.createElement('h2');
    title.innerText = 'DANH SÃCH SINH VIÃŠN - ' + currentLop;
    title.style.textAlign='center';
    wrapper.appendChild(title);
    const clone = table.cloneNode(true);
    clone.style.width='100%';
    clone.style.borderCollapse='collapse';
    clone.querySelectorAll('th,td').forEach(c => { c.style.border='1px solid #333'; c.style.padding='6px'; c.style.fontSize='11px'; c.style.color='#000'; });
    wrapper.appendChild(clone);
    html2pdf().set({margin:8, filename:'Danh_Sach_SV_' + currentLop + '.pdf', html2canvas:{scale:2}, jsPDF:{unit:'mm', format:'a4', orientation:'portrait'}}).from(wrapper).save();
}
</script>
