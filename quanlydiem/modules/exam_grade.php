<?php
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền (Admin, Khảo thí)
if (function_exists('checkRole')) {
    checkRole(['admin', 'khaothi', 'phongkhaothi']);
}

$message = '';
$msg_type = 'success';
$is_searched = isset($_GET['view']); // Kiểm tra xem người dùng đã bấm nút "Hiển thị" chưa

$selected_lop = $_GET['malop'] ?? '';
$selected_hp = $_GET['mahp'] ?? '';
$selected_nganh = $_GET['nganh'] ?? '';

// Hàm quy đổi điểm tổng kết sang điểm chữ
function getDiemChu($diemTK) {
    if ($diemTK === null || $diemTK === '') return '';
    $val = (float)$diemTK;
    if ($val >= 8.5) return 'A+';
    if ($val >= 8.0) return 'A';
    if ($val >= 7.0) return 'B+';
    if ($val >= 6.5) return 'B';
    if ($val >= 5.5) return 'C+';
    if ($val >= 5.0) return 'C';
    if ($val >= 4.0) return 'D+';
    if ($val >= 3.5) return 'D';
    return 'F';
}

// ---------------------------------------------------------
// MÔN HỌC "DÙNG CHUNG" & BỘ LỌC HỌC PHẦN THEO NGÀNH
// ---------------------------------------------------------
// Môn dùng chung = các môn áp dụng cho MỌI ngành: giáo dục thể chất (GDTC), các môn
// đại cương, quốc phòng - an ninh (và nhóm lý luận chính trị, ngoại ngữ, kỹ năng).
// Quy tắc:
//  - Môn CHƯA khai báo NganhHoc (NULL/trống) => coi là môn dùng chung, hiển thị ở mọi ngành.
//  - Môn ĐÃ gán NganhHoc => chỉ hiển thị ở đúng ngành đó (trừ khi thuộc nhóm dùng chung).
function laMonHocDungChung($hp) {
    $ma  = strtoupper(trim((string)($hp['MaHP'] ?? '')));
    $ten = trim((string)($hp['TenHP'] ?? ''));
    $ten = function_exists('mb_strtolower') ? mb_strtolower($ten, 'UTF-8') : strtolower($ten);

    // Chưa khai báo ngành => coi là môn dùng chung (tránh mất môn khi chưa gán ngành)
    if (trim((string)($hp['NganhHoc'] ?? '')) === '') {
        return true;
    }

    // Nhóm môn dùng chung theo tiền tố mã HP:
    // ML (Mác-Lênin/Tư tưởng HCM/Lý luận chính trị), TQ (Quốc phòng - An ninh + GDTC),
    // NN (Ngoại ngữ), SP (Kỹ năng mềm/Tiếng Việt nâng cao), DC (các môn đại cương),
    // CDR (chuẩn đầu ra).
    if (preg_match('/^[0-9]+(?:ML|TQ|NN|SP|DC|CDR)/', $ma)) {
        return true;
    }

    // Dự phòng theo tên môn
    $cacTuChung = ['giáo dục thể chất', 'thể dục', 'đại cương', 'quốc phòng', 'quân sự'];
    foreach ($cacTuChung as $tu) {
        if (strpos($ten, $tu) !== false) {
            return true;
        }
    }
    return false;
}

// Học phần có thuộc ngành $nganh không (hoặc là môn dùng chung)
function hocPhanThuocNganh($hp, $nganh) {
    if (laMonHocDungChung($hp)) {
        return true;
    }
    return strcasecmp(trim((string)($hp['NganhHoc'] ?? '')), trim((string)$nganh)) === 0;
}

// ---------------------------------------------------------
// 1. XỬ LÝ XÓA ĐIỂM THI (CỦA 1 SV HOẶC XÓA TẤT CẢ LỚP)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['masv']) && isset($_POST['mahp'])) {
    $masv = trim($_POST['masv']);
    $mahp = trim($_POST['mahp']);

    // Xóa điểm thi => cần quyền 'xoa_diem_thi' (admin tự có)
    if (!hasExtraPerm($pdo, 'xoa_diem_thi')) {
        die("<script>alert('Bạn không có quyền XÓA điểm thi! (Cần quyền được cấp thêm)'); window.history.back();</script>");
    }

    // Đặt điểm thi và điểm tổng kết về NULL
    $stmt = $pdo->prepare("UPDATE diem SET DiemThi = NULL, DiemTK = NULL WHERE MaSV = ? AND MaHP = ?");
    $stmt->execute([$masv, $mahp]);
    
    $malop = $_POST['malop'] ?? '';
    header("Location: exam_grade.php?view=1&malop=" . urlencode($malop) . "&mahp=" . urlencode($mahp) . "&msg=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all' && !empty($_POST['malop']) && !empty($_POST['mahp'])) {
    $malop = $_POST['malop'];
    $mahp = $_POST['mahp'];

    // Xóa toàn bộ điểm thi => cần quyền 'xoa_diem_thi'
    if (!hasExtraPerm($pdo, 'xoa_diem_thi')) {
        die("<script>alert('Bạn không có quyền XÓA điểm thi! (Cần quyền được cấp thêm)'); window.history.back();</script>");
    }

    try {
        $stmtClear = $pdo->prepare("UPDATE diem d 
                                    JOIN sinhvien sv ON d.MaSV = sv.MaSV 
                                    SET d.DiemThi = NULL, d.DiemTK = NULL 
                                    WHERE sv.MaLop = ? AND d.MaHP = ?");
        $stmtClear->execute([$malop, $mahp]);
        header("Location: exam_grade.php?view=1&malop=" . urlencode($malop) . "&mahp=" . urlencode($mahp) . "&msg=cleared");
        exit();
    } catch (Exception $e) {
        $message = "Lỗi khi xóa điểm thi: " . $e->getMessage();
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 2. XỬ LÝ LƯU / CẬP NHẬT ĐIỂM THI (POST FORM)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $diem_thi_list = $_POST['diem_thi'] ?? [];
    $mahp = $_POST['mahp'] ?? '';

    // Cập nhật điểm thi ĐÃ CÓ => cần quyền 'sua_diem_thi'; điểm mới nhập bình thường
    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM diem WHERE MaSV = ? AND MaHP = ? AND DiemThi IS NOT NULL");
        $isUpdate = false;
        foreach ($diem_thi_list as $msv => $v) {
            $chk->execute([$msv, $mahp]);
            if ($chk->fetchColumn() > 0) { $isUpdate = true; break; }
        }
        if ($isUpdate && !hasExtraPerm($pdo, 'sua_diem_thi')) {
            die("<script>alert('Bạn không có quyền SỬA điểm thi đã nhập! (Cần quyền được cấp thêm)'); window.history.back();</script>");
        }
    } catch (PDOException $e) { /* bỏ qua */ }

    try {
        $pdo->beginTransaction();
        foreach ($diem_thi_list as $masv => $val_thi) {
            // Lấy điểm quá trình hiện tại
            $stmt_check = $pdo->prepare("SELECT DiemQT FROM diem WHERE MaSV = ? AND MaHP = ?");
            $stmt_check->execute([$masv, $mahp]);
            $res_check = $stmt_check->fetch();
            
            $diem_qt = $res_check['DiemQT'] ?? null;

            if ($val_thi !== '') {
                $diem_thi_num = (float)$val_thi;
                $diem_tk = null;

                if ($diem_qt !== null && (float)$diem_qt > 3.0) {
                    $diem_qt_num = (float)$diem_qt;
                    $diem_tk = round(($diem_qt_num * 0.4) + ($diem_thi_num * 0.6), 2);
                }

                $stmt_up = $pdo->prepare("INSERT INTO diem (MaSV, MaHP, DiemQT, DiemThi, DiemTK) 
                                          VALUES (?, ?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE DiemThi = VALUES(DiemThi), DiemTK = VALUES(DiemTK)");
                $stmt_up->execute([$masv, $mahp, $diem_qt, $diem_thi_num, $diem_tk]);
            }
        }
        $pdo->commit();
        $message = "Lưu danh sách điểm thi thành công!";
        $msg_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Lỗi khi lưu điểm thi: " . $e->getMessage();
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 3. XỬ LÝ IMPORT ĐIỂM THI TỪ FILE EXCEL (POST JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $excel_data = json_decode($_POST['excel_data'] ?? '[]', true);
    $mahp = $_POST['mahp'] ?? '';

    if (!empty($excel_data) && is_array($excel_data) && !empty($mahp)) {
        // Import đè điểm thi đã có => cần quyền 'sua_diem_thi'
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM diem WHERE MaSV = ? AND MaHP = ? AND DiemThi IS NOT NULL");
            $isUpdate = false;
            foreach ($excel_data as $row) {
                $msv = trim($row['Mã SV'] ?? $row['MaSV'] ?? $row['masv'] ?? '');
                if ($msv === '') continue;
                $chk->execute([$msv, $mahp]);
                if ($chk->fetchColumn() > 0) { $isUpdate = true; break; }
            }
            if ($isUpdate && !hasExtraPerm($pdo, 'sua_diem_thi')) {
                die("<script>alert('Bạn không có quyền SỬA điểm thi đã nhập! (Cần quyền được cấp thêm)'); window.history.back();</script>");
            }
        } catch (PDOException $e) { /* bỏ qua */ }

        try {
            $pdo->beginTransaction();
            $count = 0;

            foreach ($excel_data as $row) {
                $masv = trim($row['Mã SV'] ?? $row['MaSV'] ?? $row['masv'] ?? '');
                $val_thi = $row['Điểm Thi'] ?? $row['DiemThi'] ?? $row['diemthi'] ?? '';

                if (empty($masv) || $val_thi === '') continue;

                // Lấy Điểm QT hiện tại từ CSDL
                $stmt_check = $pdo->prepare("SELECT DiemQT FROM diem WHERE MaSV = ? AND MaHP = ?");
                $stmt_check->execute([$masv, $mahp]);
                $res_check = $stmt_check->fetch();
                $diem_qt = $res_check['DiemQT'] ?? null;

                // Nếu không bị cấm thi
                if ($diem_qt === null || (float)$diem_qt > 3.0) {
                    $diem_thi_num = (float)$val_thi;
                    $diem_tk = null;

                    if ($diem_qt !== null) {
                        $diem_tk = round(((float)$diem_qt * 0.4) + ($diem_thi_num * 0.6), 2);
                    }

                    $stmt_up = $pdo->prepare("INSERT INTO diem (MaSV, MaHP, DiemQT, DiemThi, DiemTK) 
                                              VALUES (?, ?, ?, ?, ?) 
                                              ON DUPLICATE KEY UPDATE DiemThi = VALUES(DiemThi), DiemTK = VALUES(DiemTK)");
                    $stmt_up->execute([$masv, $mahp, $diem_qt, $diem_thi_num, $diem_tk]);
                    $count++;
                }
            }
            $pdo->commit();
            $message = "Import điểm thi từ Excel thành công! Đã cập nhật {$count} sinh viên.";
            $msg_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Lỗi khi Import điểm thi: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $message = "Dữ liệu Import không hợp lệ!";
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 4. LẤY DANH SÁCH LỚP VÀ HỌC PHẦN
// ---------------------------------------------------------
$lops = $pdo->query("SELECT * FROM lop ORDER BY MaLop")->fetchAll();
$hocphans = $pdo->query("SELECT * FROM hocphan ORDER BY MaHP")->fetchAll();

// Danh sách ngành học (gom lớp theo ngành) để chọn Ngành trước khi chọn Lớp
$lopsByNganh = [];
foreach ($lops as $l) {
    $nganh = trim((string)($l['NganhHoc'] ?? ''));
    if ($nganh === '') { $nganh = 'Chưa xác định'; }
    $lopsByNganh[$nganh][] = ['ma' => $l['MaLop'], 'ten' => $l['TenLop']];
}
$dsNganh = array_keys($lopsByNganh);
sort($dsNganh, SORT_NATURAL | SORT_FLAG_CASE);

// Danh sách học phần theo từng ngành (môn dùng chung hiển thị ở MỌI ngành)
$hocphansByNganh = [];
foreach ($dsNganh as $n) {
    $hocphansByNganh[$n] = [];
    foreach ($hocphans as $hp) {
        if (hocPhanThuocNganh($hp, $n)) {
            $hocphansByNganh[$n][] = ['ma' => $hp['MaHP'], 'ten' => $hp['TenHP']];
        }
    }
}

// URL cũ không có tham số ngành: suy ra ngành từ lớp đã chọn
if ($selected_nganh === '' && $selected_lop !== '') {
    foreach ($lopsByNganh as $nganh => $dsLopTheoNganh) {
        foreach ($dsLopTheoNganh as $it) {
            if ($it['ma'] === $selected_lop) { $selected_nganh = $nganh; break 2; }
        }
    }
}

// ---------------------------------------------------------
// 5. TRUY VẤN DANH SÁCH SINH VIÊN KHI ĐÃ BẤM "HIỂN THỊ"
// ---------------------------------------------------------
$students = [];
if ($is_searched && $selected_lop && $selected_hp) {
    // Kiểm tra tính hợp lệ: lớp phải thuộc ngành đã chọn và học phần phải thuộc ngành
    // của lớp (hoặc là môn dùng chung: GDTC, đại cương, QP-AN,...)
    $lopInfo = null;
    foreach ($lops as $l) {
        if ($l['MaLop'] === $selected_lop) { $lopInfo = $l; break; }
    }
    if ($lopInfo) {
        $lopNganh = trim((string)($lopInfo['NganhHoc'] ?? ''));
        if ($selected_nganh !== '' && $lopNganh !== '' && strcasecmp($selected_nganh, $lopNganh) !== 0) {
            $message = "Lớp \"" . htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8')
                     . "\" không thuộc ngành đã chọn. Vui lòng chọn lại!";
            $msg_type = 'error';
            $is_searched = false;
        }
    }
    if ($is_searched) {
        $hpInfo = null;
        foreach ($hocphans as $hv) {
            if ($hv['MaHP'] === $selected_hp) { $hpInfo = $hv; break; }
        }
        $nganhDeKiemTra = $lopInfo ? trim((string)($lopInfo['NganhHoc'] ?? '')) : $selected_nganh;
        if ($hpInfo && $nganhDeKiemTra !== '' && !hocPhanThuocNganh($hpInfo, $nganhDeKiemTra)) {
            $message = "Học phần \"" . htmlspecialchars($selected_hp, ENT_QUOTES, 'UTF-8')
                     . "\" không thuộc ngành của lớp đã chọn. Vui lòng chọn lại!";
            $msg_type = 'error';
            $is_searched = false;
        }
    }
    if ($is_searched) {
        $sql = "SELECT sv.MaSV, sv.HoTen, d.DiemQT, d.DiemThi, d.DiemTK 
                FROM sinhvien sv
                LEFT JOIN diem d ON d.MaSV = sv.MaSV AND d.MaHP = ?
                WHERE sv.MaLop = ?
                ORDER BY sv.MaSV";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selected_hp, $selected_lop]);
        $students = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập Điểm Thi Kết Thúc Học Phần</title>
    <!-- Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JS Xuất & Nhập File Excel & PDF -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --bg-body: #f1f5f9;
            --bg-card: #eef2f7;
            --bg-input: #f8fafc;
            --border-color: #e2e8f0;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --success: #10b981;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); padding: 30px 15px; min-height: 100vh; }
        
        .main-wrapper { max-width: 1350px; margin: 0 auto; }
        
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-size: 22px; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 12px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); background: #e2e8f0; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all 0.2s; }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }

        .ui-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 20px rgba(0,0,0,0.25); }
        .card-header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 16px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 10px; }
        
        /* Grid Form */
        .filter-form { display: grid; grid-template-columns: repeat(3, 1fr) auto; gap: 16px; align-items: flex-end; }
        @media (max-width: 768px) { .filter-form { grid-template-columns: 1fr; } }
        
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 600; color: var(--text-muted); }
        
        select, input[type="number"], input[type="text"] { height: 44px; background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); padding: 0 14px; border-radius: 10px; font-size: 14px; outline: none; transition: all 0.2s; width: 100%; }
        select:focus, input[type="number"]:focus, input[type="text"]:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        select:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .btn-primary { height: 44px; background: var(--primary); color: white; border: none; padding: 0 24px; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 14px; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; white-space: nowrap; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-success { height: 46px; background: var(--success); color: white; border: none; padding: 0 32px; border-radius: 10px; cursor: pointer; font-weight: 800; font-size: 15px; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        
        .btn-import { background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3); padding: 9px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
        .btn-import:hover { background: #0284c7; color: #fff; }

        .btn-excel { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 9px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; text-decoration: none; }
        .btn-excel:hover { background: #10b981; color: #fff; }
        
        .btn-pdf { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 9px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; text-decoration: none; }
        .btn-pdf:hover { background: #ef4444; color: #fff; }

        .btn-clear-all { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 9px 14px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; text-decoration: none; }
        .btn-clear-all:hover { background: #ef4444; color: #fff; }

        .btn-warning { background: rgba(245, 158, 11, 0.15); color: #d97706; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; cursor: pointer; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700; transition: 0.2s; }
        .btn-warning:hover { background: #f59e0b; color: #fff; }
        .btn-danger { background: rgba(239, 68, 68, 0.15); color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700; transition: 0.2s; }
        .btn-danger:hover { background: #ef4444; color: #fff; }

        /* SEARCH BOX */
        .search-box { position: relative; width: 220px; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .search-box input { padding-left: 36px; height: 38px; font-size: 13px; border-radius: 8px; }

        /* TABLE */
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 10px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; table-layout: fixed; min-width: 900px; }
        
        th { background: #f8fafc; color: var(--text-muted); font-weight: 700; padding: 14px 12px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; vertical-align: middle; }
        td { padding: 12px; border-bottom: 1px solid var(--border-color); color: #475569; vertical-align: middle; }
        tbody tr:hover { background: rgba(51, 65, 85, 0.4); }
        
        .text-center { text-align: center !important; }
        .text-left { text-align: left !important; }
        
        .input-grade { height: 38px; width: 85px; text-align: center; font-weight: 700; margin: 0 auto; display: block; border-radius: 6px; color: #0284c7; }
        .input-grade:disabled { background: #f8fafc !important; color: #64748b !important; border-color: #e2e8f0 !important; cursor: not-allowed; }
        
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; margin-top: 4px; display: inline-block; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; margin-top: 4px; display: inline-block; }
        .badge-letter { background: rgba(99, 102, 241, 0.15); color: #6366f1; border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 12px; border-radius: 6px; font-weight: 800; font-size: 13px; display: inline-block; }
        .badge-code { background: rgba(99, 102, 241, 0.15); color: #6366f1; padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; }

        .alert { padding: 14px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); }
        .placeholder-text { text-align: center; padding: 40px 20px; color: #64748b; font-size: 15px; }
        
        .btn-action-wrapper { display: flex; gap: 6px; justify-content: center; align-items: center; }
    </style>
</head>
<body>
<div class="main-wrapper">
    <!-- HEADER -->
    <div class="header-bar">
        <div class="page-title">
            <i class="fa-solid fa-graduation-cap" style="color: #6366f1;"></i> Nhập Điểm Thi Kết Thúc Học Phần
        </div>
        <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Trang chủ</a>
    </div>

    <?php if ($message || isset($_GET['msg'])): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($message ?: 'Thao tác cập nhật thành công!', ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- FORM LỌC LỚP VÀ HỌC PHẦN -->
    <div class="ui-card">
        <form method="GET" class="filter-form">
            <input type="hidden" name="view" value="1">
            <div class="form-group">
                <label>Ngành Học:</label>
                <select id="nganhSelect" name="nganh" required onchange="onNganhChange()">
                    <option value="">-- Chọn Ngành Học --</option>
                    <?php foreach ($dsNganh as $n): ?>
                        <option value="<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>" <?= $selected_nganh === $n ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Lớp Học:</label>
                <select id="lopSelect" name="malop" required <?= $selected_nganh === '' ? 'disabled' : '' ?>>
                    <option value="">-- Chọn Lớp Học --</option>
                </select>
            </div>
            <div class="form-group">
                <label>Học Phần:</label>
                <select id="hpSelect" name="mahp" required <?= $selected_nganh === '' ? 'disabled' : '' ?>>
                    <option value="">-- Chọn Học Phần --</option>
                    <?php if ($selected_nganh !== '' && !empty($hocphansByNganh[$selected_nganh])): ?>
                        <?php foreach ($hocphansByNganh[$selected_nganh] as $hpOpt): ?>
                            <option value="<?= htmlspecialchars($hpOpt['ma']) ?>" <?= $selected_hp == $hpOpt['ma'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($hpOpt['ma']) ?> - <?= htmlspecialchars($hpOpt['ten']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-eye"></i> Hiển thị bảng điểm</button>
        </form>
    </div>

    <!-- KHU VỰC BẢNG ĐIỂM -->
    <div class="ui-card">
        <?php if (!$is_searched): ?>
            <div class="placeholder-text">
                <i class="fa-solid fa-filter" style="font-size: 36px; margin-bottom: 12px; display: block; color: #475569;"></i>
                Vui lòng chọn <strong>Lớp</strong>, <strong>Học phần</strong> và nhấn nút <strong>"Hiển thị"</strong> để nhập/xem điểm.
            </div>
        <?php else: ?>
            <?php if (!empty($students)): ?>
                <div class="card-header-bar">
                    <div class="card-title">
                        <i class="fa-solid fa-list-check" style="color: #0284c7;"></i> Lớp: <span style="color:#0284c7;"><?= htmlspecialchars($selected_lop) ?></span> — Mã HP: <span style="color:#0284c7;"><?= htmlspecialchars($selected_hp) ?></span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <!-- Search Box -->
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="tableSearch" onkeyup="filterExamTable()" placeholder="Tìm SV, Mã SV...">
                        </div>

                        <!-- Import Excel Button -->
                        <button type="button" class="btn-import" onclick="document.getElementById('excelFileInput').click()">
                            <i class="fa-solid fa-file-import"></i> Nhập Excel
                        </button>
                        <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display: none;" onchange="importExcel(this)">

                        <!-- Export Excel & PDF Buttons -->
                        <button type="button" onclick="exportTableToExcel('examGradeTable', 'Diem_Thi_<?= htmlspecialchars($selected_lop) ?>')" class="btn-excel">
                            <i class="fa-solid fa-file-excel"></i> Xuất Excel
                        </button>
                        <button type="button" onclick="exportTableToPDF('examGradeTable', 'Diem_Thi_<?= htmlspecialchars($selected_lop) ?>')" class="btn-pdf">
                            <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                        </button>

                        <!-- Clear All -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ điểm thi của lớp này?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="clear_all">
                            <input type="hidden" name="malop" value="<?= htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-clear-all" title="Xóa tất cả điểm thi lớp này">
                                <i class="fa-solid fa-trash-can"></i> Xóa Điểm Lớp
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp) ?>">
                <div class="table-responsive">
                    <table id="examGradeTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;" class="text-center">STT</th>
                                <th style="width: 110px;" class="text-center">Mã SV</th>
                                <th style="width: 220px;" class="text-left">Họ và Tên Sinh Viên</th>
                                <th style="width: 160px;" class="text-center">Điểm QT (40%)</th>
                                <th style="width: 140px;" class="text-center">Điểm Thi (60%)</th>
                                <th style="width: 130px;" class="text-center">Điểm Tổng Kết</th>
                                <th style="width: 110px;" class="text-center">Điểm Chữ</th>
                                <th style="width: 140px;" class="text-center" data-exclude="true">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="8" class="text-center" style="padding: 30px; color: #64748b;">Không tìm thấy sinh viên nào trong lớp này.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $idx => $sv): 
                                    $diem_qt = $sv['DiemQT'];
                                    $has_no_qt = ($diem_qt === null);
                                    $is_cam_thi = ($diem_qt !== null && (float)$diem_qt <= 3.0);
                                    
                                    $is_disabled =$is_cam_thi; 
                                    
                                    $diem_thi_val = $sv['DiemThi'];$diem_tk_val = $sv['DiemTK'];$diem_chu_val = $is_cam_thi ? 'F' : getDiemChu($diem_tk_val);
                                    $has_thi_grade = ($diem_thi_val !== null);
                                ?>
                                    <tr id="row-<?= htmlspecialchars($sv['MaSV']) ?>">
                                        <td class="text-center" style="color: #64748b; font-weight: 600;"><?= $idx + 1 ?></td>
                                        <td class="text-center"><span class="badge-code"><?= htmlspecialchars($sv['MaSV']) ?></span></td>
                                        <td class="text-left"><strong style="color: #1e293b;"><?= htmlspecialchars($sv['HoTen']) ?></strong></td>
                                        
                                        <!-- Điểm Quá Trình -->
                                        <td class="text-center">
                                            <strong><?= $diem_qt !== null ? number_format($diem_qt, 2) : '-' ?></strong>
                                            <?php if ($is_cam_thi): ?>
                                                <br><span class="badge-danger">Cấm thi (&le;3.0)</span>
                                            <?php elseif ($has_no_qt): ?>
                                                <br><span class="badge-warning">Chưa có điểm QT</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Ô Nhập Điểm Thi -->
                                        <td class="text-center">
                                            <?php if ($is_disabled): ?>
                                                <input type="text" class="input-grade" value="Cấm thi" disabled style="color: #dc2626 !important;">
                                            <?php else: ?>
                                                <input type="number" 
                                                       step="0.1" min="0" max="10" 
                                                       name="diem_thi[<?= htmlspecialchars($sv['MaSV']) ?>]" 
                                                       value="<?= $diem_thi_val !== null ? $diem_thi_val : '' ?>" 
                                                       class="input-grade" 
                                                       placeholder="0 - 10"
                                                       <?= $has_thi_grade ? 'disabled' : '' ?>
                                                       oninput="tinhDiem(this, <?= $diem_qt !== null ? $diem_qt : 'null' ?>)">
                                            <?php endif; ?>
                                        </td>

                                        <!-- Điểm Tổng Kết -->
                                        <td class="text-center">
                                            <span class="val-tk" style="font-weight: 800; color: #0284c7; font-size: 15px;">
                                                <?= $diem_tk_val !== null ? number_format($diem_tk_val, 2) : ($is_cam_thi ? number_format((float)$diem_qt * 0.4, 2) : '-') ?>
                                            </span>
                                        </td>

                                        <!-- Điểm Chữ -->
                                        <td class="text-center">
                                            <span class="badge-letter val-chu"><?= $diem_chu_val ?: '-' ?></span>
                                        </td>

                                        <!-- Thao Tác -->
                                        <td class="text-center" data-exclude="true">
                                            <div class="btn-action-wrapper">
                                                <?php if ($has_thi_grade && !$is_disabled): ?>
                                                    <button type="button" class="btn-warning" onclick="enableRowEdit('<?= htmlspecialchars($sv['MaSV']) ?>', this)" title="Sửa điểm">
                                                        <i class="fa-solid fa-pen"></i> Sửa
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có muốn xóa điểm thi của sinh viên <?= htmlspecialchars($sv['HoTen'], ENT_QUOTES, 'UTF-8') ?>?');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="masv" value="<?= htmlspecialchars($sv['MaSV'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="malop" value="<?= htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="btn-danger">
                                                            <i class="fa-solid fa-trash"></i> Xóa
                                                        </button>
                                                    </form>
                                                <?php elseif ($is_disabled): ?>
                                                    <span style="color: #ef4444; font-size: 12px; font-weight: 600;">Cấm thi</span>
                                                <?php else: ?>
                                                    <span style="color: #64748b; font-size: 12px;">Chưa nhập</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($students)): ?>
                    <div style="margin-top: 24px; text-align: center;">
                        <button type="submit" name="save_grades" class="btn-success">
                            <i class="fa-solid fa-floppy-disk"></i> Lưu Bảng Điểm Thi
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Form ẩn gửi dữ liệu JSON Import Excel -->
<form id="importExcelForm" method="POST" style="display: none;">
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="import_excel">
    <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp) ?>">
    <input type="hidden" name="excel_data" id="excelDataInput">
</form>

<script>
const LOPS_BY_NGANH = <?= json_encode($lopsByNganh, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const HOCPHANS_BY_NGANH = <?= json_encode($hocphansByNganh, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const SELECTED_LOP = <?= json_encode($selected_lop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const SELECTED_HP = <?= json_encode($selected_hp, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// Cập nhật danh sách lớp & học phần theo ngành; chỉ cho chọn sau khi đã chọn ngành
function onNganhChange() {
    const nganh = document.getElementById('nganhSelect').value;
    const lopSel = document.getElementById('lopSelect');
    const hpSel = document.getElementById('hpSelect');

    lopSel.innerHTML = '<option value="">-- Chọn Lớp Học --</option>';
    hpSel.innerHTML = '<option value="">-- Chọn Học Phần --</option>';

    if (!nganh || !LOPS_BY_NGANH[nganh]) {
        lopSel.disabled = true;
        hpSel.disabled = true;
        return;
    }
    LOPS_BY_NGANH[nganh].forEach(function (l) {
        const opt = document.createElement('option');
        opt.value = l.ma;
        opt.textContent = l.ma + ' - ' + l.ten;
        lopSel.appendChild(opt);
    });
    (HOCPHANS_BY_NGANH[nganh] || []).forEach(function (h) {
        const opt = document.createElement('option');
        opt.value = h.ma;
        opt.textContent = h.ma + ' - ' + h.ten;
        hpSel.appendChild(opt);
    });
    lopSel.disabled = false;
    hpSel.disabled = false;
}

document.addEventListener('DOMContentLoaded', function () {
    onNganhChange();
    if (SELECTED_LOP) {
        document.getElementById('lopSelect').value = SELECTED_LOP;
    }
    if (SELECTED_HP) {
        document.getElementById('hpSelect').value = SELECTED_HP;
    }
});

function filterExamTable() {
    const filter = document.getElementById("tableSearch").value.toLowerCase();
    const rows = document.querySelectorAll("#examGradeTable tbody tr");

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
}

function enableRowEdit(masv, btn) {
    const row = document.getElementById('row-' + masv);
    const input = row.querySelector('input[name^="diem_thi"]');
    if (input) {
        input.removeAttribute('disabled');
        input.style.backgroundColor = '#f8fafc';
        input.style.border = '1px solid #0284c7';
        input.focus();
    }
    btn.style.display = 'none';
}

function getDiemChuJS(val) {
    if (val === null || val === '') return '';
    if (val >= 8.5) return 'A+';
    if (val >= 8.0) return 'A';
    if (val >= 7.0) return 'B+';
    if (val >= 6.5) return 'B';
    if (val >= 5.5) return 'C+';
    if (val >= 5.0) return 'C';
    if (val >= 4.0) return 'D+';
    if (val >= 3.5) return 'D';
    return 'F';
}

function tinhDiem(input, diemQT) {
    const row = input.closest('tr');
    const valThi = parseFloat(input.value);
    const spanTK = row.querySelector('.val-tk');
    const spanChu = row.querySelector('.val-chu');

    if (diemQT !== null && !isNaN(valThi) && valThi >= 0 && valThi <= 10) {
        const diemTK = (diemQT * 0.4) + (valThi * 0.6);
        spanTK.innerText = diemTK.toFixed(2);
        spanChu.innerText = getDiemChuJS(diemTK);
    } else {
        spanTK.innerText = '-';
        spanChu.innerText = '-';
    }
}

// Import điểm từ file Excel
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

            const jsonData = XLSX.utils.sheet_to_json(worksheet, { raw: false });

            if (jsonData.length === 0) {
                alert('File Excel không chứa dữ liệu hợp lệ!');
                input.value = '';
                return;
            }

            if (confirm(`Tìm thấy ${jsonData.length} dòng dữ liệu trong file. Bạn có chắc chắn muốn nhập điểm thi vào hệ thống?`)) {
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

// Xuất file Excel
function exportTableToExcel(tableID, filename = '') {
    var table = document.getElementById(tableID);
    if (!table) return;

    var clonedTable = table.cloneNode(true);
    var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
    excludedElements.forEach(el => el.remove());

    var inputs = clonedTable.querySelectorAll('input');
    inputs.forEach(input => {
        var val = input.value;
        var parentNode = input.parentNode;
        parentNode.textContent = (val !== '' && val !== 'Cấm thi') ? val : (val === 'Cấm thi' ? 'Cấm thi' : '-');
    });

    var wb = XLSX.utils.table_to_book(clonedTable, {sheet: "DiemThi"});
    filename = filename ? filename + '.xlsx' : 'Diem_Thi.xlsx';
    XLSX.writeFile(wb, filename);
}

// Xuất file PDF
function exportTableToPDF(tableID, filename = '') {
    var table = document.getElementById(tableID);
    if (!table) return;

    var wrapper = document.createElement('div');
    wrapper.style.padding = '20px';
    wrapper.style.color = '#000';
    wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

    var title = document.createElement('h2');
    title.innerText = 'BẢNG ĐIỂM THI KẾT THÚC HỌC PHẦN';
    title.style.textAlign = 'center';
    title.style.marginBottom = '15px';
    wrapper.appendChild(title);

    var info = document.createElement('p');
    info.innerHTML = '<strong>Lớp:</strong> <?= htmlspecialchars($selected_lop) ?> \vert{} <strong>Mã HP:</strong> <?= htmlspecialchars($selected_hp) ?>';
    info.style.textAlign = 'center';
    info.style.marginBottom = '20px';
    wrapper.appendChild(info);

    var clonedTable = table.cloneNode(true);
    var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
    excludedElements.forEach(el => el.remove());

    var inputs = clonedTable.querySelectorAll('input');
    inputs.forEach(input => {
        var val = input.value;
        var parentNode = input.parentNode;
        parentNode.textContent = (val !== '' && val !== 'Cấm thi') ? val : (val === 'Cấm thi' ? 'Cấm thi' : '-');
    });

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

    filename = filename ? filename + '.pdf' : 'Diem_Thi.pdf';

    var opt = {
        margin:       10,
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
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


