<?php
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền sinh viên
if (function_exists('checkRole')) {
    checkRole(['sinhvien']);
}

// Lấy thông tin mã sinh viên từ SESSION
$masv = $_SESSION['user']['MaSV'] ?? '';

// 1. Lấy thông tin cá nhân chi tiết của sinh viên
$stmt_sv = $pdo->prepare("
    SELECT sv.*, l.TenLop, l.NganhHoc 
    FROM sinhvien sv 
    LEFT JOIN lop l ON sv.MaLop = l.MaLop 
    WHERE sv.MaSV = ?
");
$stmt_sv->execute([$masv]);
$student_info = $stmt_sv->fetch();

// 2. Lấy danh sách điểm của sinh viên (nhóm/sắp xếp theo Học Kỳ)
$stmt_diem = $pdo->prepare("
    SELECT d.*, hp.TenHP, hp.SoTC, hp.HocKy 
    FROM diem d 
    JOIN hocphan hp ON d.MaHP = hp.MaHP 
    WHERE d.MaSV = ? 
    ORDER BY hp.HocKy ASC, hp.MaHP ASC
");
$stmt_diem->execute([$masv]);
$grades = $stmt_diem->fetchAll();

// Xác định Học Kỳ Hiện Tại (Học kỳ lớn nhất trong danh sách môn học)
$current_semester = 0;
foreach ($grades as $g) {
    $hk_num = (int) preg_replace('/[^0-9]/', '', $g['HocKy'] ?? '0');
    if ($hk_num > $current_semester) {
        $current_semester = $hk_num;
    }
}

// Phân nhóm danh sách điểm theo Học Kỳ
$grades_by_semester = [];
foreach ($grades as $g) {
    $hk = $g['HocKy'] ?? 'Khác';
    $grades_by_semester[$hk][] = $g;
}

// Hàm quy đổi Điểm Chữ & Thang điểm 4 kèm Class CSS
function quyDoiDiem($diemTK) {
    if ($diemTK === null || $diemTK === '') {
        return ['chu' => '-', 'he4' => null, 'class' => 'letter-default'];
    }
    $val = (float)$diemTK;
    if ($val >= 8.5) return ['chu' => 'A+', 'he4' => 4.0, 'class' => 'letter-a'];
    if ($val >= 8.0) return ['chu' => 'A',  'he4' => 4.0, 'class' => 'letter-a'];
    if ($val >= 7.0) return ['chu' => 'B+', 'he4' => 3.5, 'class' => 'letter-b'];
    if ($val >= 6.5) return ['chu' => 'B',  'he4' => 3.0, 'class' => 'letter-b'];
    if ($val >= 5.5) return ['chu' => 'C+', 'he4' => 2.5, 'class' => 'letter-c'];
    if ($val >= 5.0) return ['chu' => 'C',  'he4' => 2.0, 'class' => 'letter-c'];
    if ($val >= 4.0) return ['chu' => 'D+', 'he4' => 1.5, 'class' => 'letter-d'];
    if ($val >= 3.5) return ['chu' => 'D',  'he4' => 1.0, 'class' => 'letter-d'];
    return ['chu' => 'F', 'he4' => 0.0, 'class' => 'letter-f'];
}

// Hàm Đánh giá Kết quả môn học
function getDanhGia($diemQT, $diemTK, $is_past_due_no_exam = false) {
    if ($diemQT !== null && (float)$diemQT <= 3.0) {
        return ['status' => 'HỌC LẠI', 'class' => 'badge-danger', 'note' => 'Cấm thi'];
    }
    if ($is_past_due_no_exam) {
        return ['status' => 'HỌC LẠI', 'class' => 'badge-danger', 'note' => 'Quá hạn / Chưa thi'];
    }
    if ($diemTK === null || $diemTK === '') {
        return ['status' => 'Chưa có điểm', 'class' => 'badge-secondary', 'note' => ''];
    }
    $tk = (float)$diemTK;
    if ($tk >= 5.0) {
        return ['status' => 'ĐẠT', 'class' => 'badge-success', 'note' => ''];
    } elseif ($tk >= 3.0) {
        return ['status' => 'THI LẠI', 'class' => 'badge-warning', 'note' => ''];
    } else {
        return ['status' => 'HỌC LẠI', 'class' => 'badge-danger', 'note' => 'Dưới 3.0'];
    }
}

// Tính điểm GPA Toàn Khóa
$total_tc_tichluy = 0;
$total_diem_he10_nhan_tc = 0;
$total_diem_he4_nhan_tc = 0;

foreach ($grades as $g) {
    $hk_num = (int) preg_replace('/[^0-9]/', '', $g['HocKy'] ?? '0');
    $is_past_semester = ($hk_num < $current_semester && $current_semester > 0);
    
    $diem_qt = $g['DiemQT'];
    $diem_thi = $g['DiemThi'];
    $diem_tk = $g['DiemTK'];
    $stc = (int)$g['SoTC'];

    // Nếu là kỳ đã qua mà chưa có điểm thi -> Mặc định thi 0 điểm
    if ($is_past_semester && $diem_thi === null && ($diem_qt !== null || $diem_tk === null)) {
        $diem_thi = 0.0;
        $diem_tk = ($diem_qt !== null) ? round(($diem_qt * 0.4) + ($diem_thi * 0.6), 1) : 0.0;
    }
    
    // Xét điều kiện tích lũy môn học
    if ($diem_tk !== null && $diem_tk !== '') {
        $qd = quyDoiDiem($diem_tk);
        $total_tc_tichluy += $stc;
        $total_diem_he10_nhan_tc += ((float)$diem_tk * $stc);
        $total_diem_he4_nhan_tc += ($qd['he4'] * $stc);
    }
}

$gpa_he10 = $total_tc_tichluy > 0 ? round($total_diem_he10_nhan_tc / $total_tc_tichluy, 2) : 0;
$gpa_he4  = $total_tc_tichluy > 0 ? round($total_diem_he4_nhan_tc / $total_tc_tichluy, 2) : 0;

// ==========================================
// XỬ LÝ XUẤT FILE EXCEL KHI NGƯỜI DÙNG CLICK NÚT
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Ket_Qua_Hoc_Tap_" . ($student_info['MaSV'] ?? 'SinhVien') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Xuất UTF-8 BOM hỗ trợ hiển thị tiếng Việt trên MS Excel
    echo "\xEF\xBB\xBF";
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: 'Times New Roman', Arial, sans-serif; font-size: 12pt; }
            table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
            th, td { border: 1px solid #94a3b8; padding: 7px 6px; text-align: center; vertical-align: middle; }
            th { background-color: #e0e7ff; color: #4338ca; font-weight: bold; }
            tbody tr:nth-child(even) td { background-color: #f8fafc; }
            .title { font-size: 16pt; font-weight: bold; text-align: center; margin-bottom: 15px; color: #4f46e5; border-bottom: 2px solid #4f46e5; padding-bottom: 6px; }
            .info-table td { border: none; text-align: left; padding: 4px; background: #ffffff !important; }
            .info-table { background-color: #f8fafc; border: 1px solid #e2e8f0; }
            .header-hk { background-color: #4f46e5; color: #ffffff; font-weight: bold; text-align: left; }
            .text-left { text-align: left; }
            .bold { font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="title">BẢNG KẾT QUẢ HỌC TẬP CÁ NHÂN</div>

        <table class="info-table">
            <tr>
                <td width="15%" class="bold">Mã Sinh Viên:</td>
                <td width="35%"><?= htmlspecialchars($student_info['MaSV'] ?? $masv) ?></td>
                <td width="15%" class="bold">Họ và Tên:</td>
                <td width="35%"><?= htmlspecialchars($student_info['HoTen'] ?? 'Chưa cập nhật') ?></td>
            </tr>
            <tr>
                <td class="bold">Lớp Học:</td>
                <td><?= htmlspecialchars($student_info['TenLop'] ?? 'Chưa cập nhật') ?></td>
                <td class="bold">Ngành Học:</td>
                <td><?= htmlspecialchars($student_info['NganhHoc'] ?? 'Chưa cập nhật') ?></td>
            </tr>
            <tr>
                <td class="bold">Tín Chỉ Tích Lũy:</td>
                <td><?= $total_tc_tichluy ?> TC</td>
                <td class="bold">GPA Toàn Khóa:</td>
                <td><?= number_format($gpa_he10, 2) ?> (Hệ 10) / <?= number_format($gpa_he4, 2) ?> (Hệ 4)</td>
            </tr>
        </table>

        <br>

        <?php if (!empty($grades_by_semester)): ?>
            <?php foreach ($grades_by_semester as $hk => $list_mon): 
                $hk_num = (int) preg_replace('/[^0-9]/', '', $hk);
                $is_past_semester = ($hk_num < $current_semester && $current_semester > 0);

                $hk_tc = 0;
                $hk_diem_he10 = 0;
                $hk_diem_he4 = 0;

                foreach ($list_mon as $m) {
                    $m_diem_qt = $m['DiemQT'];
                    $m_diem_thi = $m['DiemThi'];
                    $m_diem_tk = $m['DiemTK'];
                    $stc = (int)$m['SoTC'];

                    if ($is_past_semester && $m_diem_thi === null) {
                        $m_diem_thi = 0.0;
                        $m_diem_tk = ($m_diem_qt !== null) ? round(($m_diem_qt * 0.4) + ($m_diem_thi * 0.6), 1) : 0.0;
                    }

                    if ($m_diem_tk !== null && $m_diem_tk !== '') {
                        $qd = quyDoiDiem($m_diem_tk);
                        $hk_tc += $stc;
                        $hk_diem_he10 += ((float)$m_diem_tk * $stc);
                        $hk_diem_he4 += ($qd['he4'] * $stc);
                    }
                }

                $tbc_hk_he10 = $hk_tc > 0 ? round($hk_diem_he10 / $hk_tc, 2) : 0;
                $tbc_hk_he4  = $hk_tc > 0 ? round($hk_diem_he4 / $hk_tc, 2) : 0;
            ?>
                <table>
                    <thead>
                        <tr>
                            <th colspan="9" class="header-hk">
                                HỌC KỲ <?= htmlspecialchars($hk) ?> 
                                (TC: <?= $hk_tc ?> | TBC Hệ 10: <?= number_format($tbc_hk_he10, 2) ?> | TBC Hệ 4: <?= number_format($tbc_hk_he4, 2) ?>)
                            </th>
                        </tr>
                        <tr>
                            <th width="5%">STT</th>
                            <th width="12%">Mã HP</th>
                            <th width="30%">Tên Học Phần</th>
                            <th width="8%">Số TC</th>
                            <th width="11%">Đ. Quá Trình (40%)</th>
                            <th width="11%">Đ. Thi (60%)</th>
                            <th width="11%">Đ. Kết Thúc</th>
                            <th width="8%">Đ. Chữ</th>
                            <th width="14%">Đánh Giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_mon as $idx => $g): 
                            $diem_qt = $g['DiemQT'];
                            $diem_thi = $g['DiemThi'];
                            $diem_tk = $g['DiemTK'];
                            
                            $is_cam_thi = ($diem_qt !== null && (float)$diem_qt <= 3.0);
                            $is_past_due_no_exam = ($is_past_semester && $diem_thi === null);

                            if ($is_past_due_no_exam) {
                                $diem_thi = 0.0;
                                $diem_tk = ($diem_qt !== null) ? round(($diem_qt * 0.4) + ($diem_thi * 0.6), 1) : 0.0;
                            }

                            $qd = quyDoiDiem($diem_tk);
                            $diem_chu = ($is_cam_thi || $is_past_due_no_exam) ? 'F' : $qd['chu'];
                            $danh_gia = getDanhGia($diem_qt, $diem_tk, $is_past_due_no_exam);
                        ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><?= htmlspecialchars($g['MaHP']) ?></td>
                                <td class="text-left"><?= htmlspecialchars($g['TenHP']) ?></td>
                                <td><?= $g['SoTC'] ?></td>
                                <td><?= $diem_qt !== null ? number_format($diem_qt, 1) : '-' ?></td>
                                <td><?= $diem_thi !== null ? number_format($diem_thi, 1) : '-' ?></td>
                                <td class="bold"><?= ($diem_tk !== null && $diem_tk !== '') ? number_format($diem_tk, 1) : '-' ?></td>
                                <td class="bold"><?= $diem_chu ?></td>
                                <td><?= $danh_gia['status'] . ($danh_gia['note'] ? ' ('.$danh_gia['note'].')' : '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit; // Dừng thực thi script để xuất file Excel
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết Quả Học Tập Cá Nhân - Student Portal</title>
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JS Xuất PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        :root {
            --bg-body: #f1f5f9;
            --bg-card: #ffffff;
            --bg-card-hover: #f1f5f9;
            --bg-input: #f8fafc;
            --border-color: #e2e8f0;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); min-height: 100vh; padding: 32px 20px; }
        .container { max-width: 1200px; margin: 0 auto; }

        /* HEADER NAV */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        .page-title i { background: rgba(99, 102, 241, 0.15); color: var(--primary); padding: 10px; border-radius: 12px; font-size: 20px; border: 1px solid rgba(99, 102, 241, 0.3); }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .btn-back { background: #eef2f7; color: var(--text-muted); padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid var(--border-color); transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { color: #1e293b; background: #e2e8f0; transform: translateY(-1px); }

        .btn-excel { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 10px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; text-decoration: none; }
        .btn-excel:hover { background: var(--success); color: #fff; transform: translateY(-1px); }

        .btn-pdf { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 10px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; text-decoration: none; }
        .btn-pdf:hover { background: var(--danger); color: #fff; transform: translateY(-1px); }

        /* CARD BOX */
        .card-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 20px; }
        .card-title i { color: var(--primary); }

        /* THÔNG TIN SINH VIÊN GRID */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .info-item { background: var(--bg-input); padding: 14px 18px; border-radius: 12px; border: 1px solid var(--border-color); }
        .info-item span { color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
        .info-item strong { font-size: 15px; color: #0284c7; font-weight: 700; }

        /* TỔNG QUAN GPA GRADIENT CARDS */
        .gpa-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 20px; }
        .gpa-card { border-radius: 14px; padding: 20px; text-align: center; position: relative; overflow: hidden; border: 1px solid rgba(15, 23, 42, 0.08); }
        .gpa-card-1 { background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); border-color: #c7d2fe; }
        .gpa-card-2 { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-color: #a7f3d0; }
        .gpa-card-3 { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-color: #bae6fd; }

        .gpa-card span { font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; opacity: 0.9; }
        .gpa-card h3 { font-size: 32px; color: #1e293b; font-weight: 800; letter-spacing: -1px; }

        /* HEADER HỌC KỲ */
        .semester-block { margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; background: var(--bg-card); }
        .semester-header { display: flex; justify-content: space-between; align-items: center; background: #eef2f7; padding: 16px 20px; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 12px; }
        .semester-title { font-size: 15px; font-weight: 800; color: #4f46e5; display: flex; align-items: center; gap: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .semester-stats { font-size: 13px; color: var(--text-muted); font-weight: 600; display: flex; gap: 18px; flex-wrap: wrap; }
        .semester-stats span strong { color: #0284c7; font-weight: 700; }

        /* BẢNG ĐIỂM HỌC KỲ */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; background: var(--bg-card); font-size: 13px; white-space: nowrap; }
        th, td { padding: 12px 16px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { background: #eef2ff; color: #4338ca; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; text-align: center; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover td { background: var(--bg-card-hover); }

        /* BADGES & CHỮ */
        .badge-code { background: rgba(99, 102, 241, 0.15); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; font-family: monospace; display: inline-block; }
        .badge-tc { background: rgba(56, 189, 248, 0.15); color: #0284c7; border: 1px solid rgba(56, 189, 248, 0.3); padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block; }
        
        .badge-letter { padding: 4px 10px; border-radius: 6px; font-weight: 800; font-size: 12px; display: inline-block; min-width: 36px; text-align: center; }
        .letter-a { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); }
        .letter-b { background: rgba(59, 130, 246, 0.15); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); }
        .letter-c { background: rgba(234, 179, 8, 0.15); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.3); }
        .letter-d { background: rgba(249, 115, 22, 0.15); color: #ea580c; border: 1px solid rgba(249, 115, 22, 0.3); }
        .letter-f { background: rgba(239, 68, 68, 0.2); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.5); }
        .letter-default { background: rgba(148, 163, 184, 0.1); color: #64748b; border: 1px solid rgba(148, 163, 184, 0.2); }

        .badge-success { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); padding: 5px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); padding: 5px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 5px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block; }
        .badge-secondary { background: rgba(148, 163, 184, 0.1); color: #64748b; border: 1px solid rgba(148, 163, 184, 0.2); padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block; }
        
        .note-camthi { color: #dc2626; font-size: 11px; font-weight: 600; display: block; margin-top: 3px; }

        /* TOOLTIP KHI HOVER VÀO Ô ĐIỂM */
        td[data-mon] { cursor: help; }
        #cellTooltip { position: fixed; z-index: 9999; background: #1e293b; color: #ffffff; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; font-weight: 600; pointer-events: none; opacity: 0; transition: opacity 0.15s ease; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25); max-width: 300px; line-height: 1.5; }
        #cellTooltip .tip-col { display: block; color: #a5b4fc; font-size: 11.5px; margin-top: 3px; }
    </style>
    <script>
        function exportStudentPDF() {
            var targetElement = document.getElementById('printableArea');
            if (!targetElement) {
                alert('Không tìm thấy vùng dữ liệu cần xuất!');
                return;
            }

            var cloned = targetElement.cloneNode(true);

            var wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.color = '#000';
            wrapper.style.backgroundColor = '#fff';
            wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

            var header = document.createElement('div');
            header.style.textAlign = 'center';
            header.style.marginBottom = '20px';
            header.style.background = '#4f46e5';
            header.style.color = '#ffffff';
            header.style.padding = '14px 16px';
            header.style.borderRadius = '10px';
            header.innerHTML = `
                <h1 style="font-size: 20px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; color:#ffffff;">BẢNG KẾT QUẢ HỌC TẬP CÁ NHÂN</h1>
                <p style="font-size: 12px; color: #e0e7ff;">Mã SV: ${<?= json_encode($student_info['MaSV'] ?? $masv) ?>} | Họ tên: ${<?= json_encode($student_info['HoTen'] ?? '') ?>} | Lớp: ${<?= json_encode($student_info['TenLop'] ?? '') ?>}</p>
            `;
            wrapper.appendChild(header);

            cloned.querySelectorAll('.card-box, .semester-block').forEach(el => {
                el.style.backgroundColor = '#fff';
                el.style.border = '1px solid #e2e8f0';
                el.style.boxShadow = 'none';
                el.style.marginBottom = '15px';
            });

            cloned.querySelectorAll('.info-item').forEach(el => {
                el.style.backgroundColor = '#f8fafc';
                el.style.border = '1px solid #e2e8f0';
            });

            cloned.querySelectorAll('.semester-header').forEach(el => {
                el.style.backgroundColor = '#eef2f7';
            });

            cloned.querySelectorAll('.gpa-card').forEach(el => {
                el.style.boxShadow = 'none';
            });
            // Gán màu nền đặc cho các thẻ GPA (html2canvas không vẽ được gradient)
            cloned.querySelectorAll('.gpa-card-1').forEach(el => { el.style.background = '#e0e7ff'; el.style.border = '1px solid #c7d2fe'; });
            cloned.querySelectorAll('.gpa-card-2').forEach(el => { el.style.background = '#d1fae5'; el.style.border = '1px solid #a7f3d0'; });
            cloned.querySelectorAll('.gpa-card-3').forEach(el => { el.style.background = '#e0f2fe'; el.style.border = '1px solid #bae6fd'; });
            cloned.querySelectorAll('.gpa-card h3').forEach(el => el.style.color = '#1e293b');
            cloned.querySelectorAll('.gpa-card span').forEach(el => el.style.color = '#64748b');

            cloned.querySelectorAll('table').forEach(tbl => {
                tbl.style.width = '100%';
                tbl.style.borderCollapse = 'collapse';
            });

            cloned.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #94a3b8';
                cell.style.padding = '6px 8px';
                cell.style.fontSize = '10px';
            });

            cloned.querySelectorAll('th').forEach(th => {
                th.style.backgroundColor = '#e0e7ff';
                th.style.color = '#4338ca';
            });

            // Inline màu cho các badge (bản clone nằm ngoài DOM nên không nhận CSS của trang)
            var badgeStyles = {
                'letter-a': ['rgba(34,197,94,0.15)', '#16a34a'],
                'letter-b': ['rgba(59,130,246,0.15)', '#2563eb'],
                'letter-c': ['rgba(234,179,8,0.15)', '#ca8a04'],
                'letter-d': ['rgba(249,115,22,0.15)', '#ea580c'],
                'letter-f': ['rgba(239,68,68,0.2)', '#dc2626'],
                'letter-default': ['rgba(148,163,184,0.1)', '#64748b'],
                'badge-success': ['rgba(34,197,94,0.15)', '#16a34a'],
                'badge-warning': ['rgba(245,158,11,0.15)', '#d97706'],
                'badge-danger': ['rgba(239,68,68,0.15)', '#dc2626'],
                'badge-secondary': ['rgba(148,163,184,0.1)', '#64748b'],
                'badge-code': ['rgba(99,102,241,0.15)', '#4f46e5'],
                'badge-tc': ['rgba(56,189,248,0.15)', '#0284c7']
            };
            Object.keys(badgeStyles).forEach(function(cls) {
                cloned.querySelectorAll('.' + cls).forEach(function(el) {
                    el.style.backgroundColor = badgeStyles[cls][0];
                    el.style.color = badgeStyles[cls][1];
                    el.style.padding = '4px 10px';
                    el.style.borderRadius = '6px';
                    el.style.fontWeight = '700';
                    el.style.display = 'inline-block';
                });
            });
            cloned.querySelectorAll('.info-item strong').forEach(el => el.style.color = '#0284c7');
            cloned.querySelectorAll('.semester-title').forEach(el => el.style.color = '#4f46e5');
            cloned.querySelectorAll('.semester-stats strong').forEach(el => el.style.color = '#0284c7');
            cloned.querySelectorAll('.note-camthi').forEach(el => el.style.color = '#dc2626');

            cloned.querySelectorAll('tbody tr').forEach((tr, i) => {
                tr.querySelectorAll('td').forEach(td => td.style.backgroundColor = (i % 2 === 1) ? '#f8fafc' : '#ffffff');
            });

            wrapper.appendChild(cloned);

            wrapper.style.position = 'absolute';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            wrapper.style.width = '1100px';
            document.body.appendChild(wrapper);

            var opt = {
                margin:       8,
                filename:     'Ket_Qua_Hoc_Tap_' + <?= json_encode($student_info['MaSV'] ?? $masv) ?> + '.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(wrapper).save().then(function() { wrapper.remove(); });
        }
    </script>
</head>
<body>
<div class="container">
    <!-- HEADER NAV -->
    <div class="page-header">
        <div class="page-title">
            <i class="fa-solid fa-graduation-cap"></i>
            <div>
                <div>Kết Quả Học Tập Cá Nhân</div>
                <div style="font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">Bảng tổng hợp điểm các học kỳ, tín chỉ tích lũy và trung bình gpa toàn khóa</div>
            </div>
        </div>
        <div class="header-actions">
            <!-- Nút xuất Excel -->
            <a href="?export=excel" class="btn-excel">
                <i class="fa-solid fa-file-excel"></i> Xuất Excel
            </a>
            <!-- Nút xuất PDF -->
            <button onclick="exportStudentPDF()" class="btn-pdf">
                <i class="fa-solid fa-file-pdf"></i> In / Xuất PDF
            </button>
            <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Trang chủ</a>
        </div>
    </div>

    <!-- VÙNG DỮ LIỆU ĐƯỢC IN / XUẤT PDF -->
    <div id="printableArea">
        <!-- THÔNG TIN CÁ NHÂN SINH VIÊN -->
        <div class="card-box">
            <div class="card-title">
                <i class="fa-solid fa-id-card"></i> Thông Tin Sinh Viên
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span>Mã Sinh Viên</span>
                    <strong><?= htmlspecialchars($student_info['MaSV'] ?? $masv) ?></strong>
                </div>
                <div class="info-item">
                    <span>Họ và Tên</span>
                    <strong><?= htmlspecialchars($student_info['HoTen'] ?? 'Chưa cập nhật') ?></strong>
                </div>
                <div class="info-item">
                    <span>Lớp Học</span>
                    <strong><?= htmlspecialchars($student_info['TenLop'] ?? 'Chưa cập nhật') ?></strong>
                </div>
                <div class="info-item">
                    <span>Ngành Học</span>
                    <strong><?= htmlspecialchars($student_info['NganhHoc'] ?? 'Chưa cập nhật') ?></strong>
                </div>
                <div class="info-item">
                    <span>Ngày Sinh</span>
                    <strong><?= !empty($student_info['NgaySinh']) ? date('d/m/Y', strtotime($student_info['NgaySinh'])) : 'Chưa cập nhật' ?></strong>
                </div>
                <div class="info-item">
                    <span>Email</span>
                    <strong><?= htmlspecialchars($student_info['Email'] ?? 'Chưa cập nhật') ?></strong>
                </div>
            </div>

            <!-- THỐNG KÊ GPA TOÀN BỘ CẢ KHÓA -->
            <div class="gpa-summary">
                <div class="gpa-card gpa-card-1">
                    <span>TỔNG TÍN CHỈ TÍCH LŨY</span>
                    <h3><?= $total_tc_tichluy ?> <span style="font-size: 16px; font-weight: 600;">TC</span></h3>
                </div>
                <div class="gpa-card gpa-card-2">
                    <span>GPA TOÀN KHÓA (HỆ 10)</span>
                    <h3><?= number_format($gpa_he10, 2) ?> <span style="font-size: 16px; font-weight: 600;">/ 10</span></h3>
                </div>
                <div class="gpa-card gpa-card-3">
                    <span>GPA TOÀN KHÓA (HỆ 4)</span>
                    <h3><?= number_format($gpa_he4, 2) ?> <span style="font-size: 16px; font-weight: 600;">/ 4.0</span></h3>
                </div>
            </div>
        </div>

        <!-- DANH SÁCH ĐIỂM CÁC HỌC KỲ -->
        <?php if (empty($grades_by_semester)): ?>
            <div class="card-box" style="text-align: center; color: var(--text-muted); padding: 50px;">
                <i class="fa-solid fa-folder-open" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                Chưa có dữ liệu điểm học phần nào trong hệ thống.
            </div>
        <?php else: ?>
            <?php foreach ($grades_by_semester as $hk => $list_mon): 
                $hk_num = (int) preg_replace('/[^0-9]/', '', $hk);
                $is_past_semester = ($hk_num < $current_semester && $current_semester > 0);

                // Tính toán Điểm trung bình cộng cho từng học kỳ
                $hk_tc = 0;
                $hk_diem_he10 = 0;
                $hk_diem_he4 = 0;

                foreach ($list_mon as $m) {
                    $m_diem_qt = $m['DiemQT'];
                    $m_diem_thi = $m['DiemThi'];
                    $m_diem_tk = $m['DiemTK'];
                    $stc = (int)$m['SoTC'];

                    if ($is_past_semester && $m_diem_thi === null) {
                        $m_diem_thi = 0.0;
                        $m_diem_tk = ($m_diem_qt !== null) ? round(($m_diem_qt * 0.4) + ($m_diem_thi * 0.6), 1) : 0.0;
                    }

                    if ($m_diem_tk !== null && $m_diem_tk !== '') {
                        $qd = quyDoiDiem($m_diem_tk);
                        $hk_tc += $stc;
                        $hk_diem_he10 += ((float)$m_diem_tk * $stc);
                        $hk_diem_he4 += ($qd['he4'] *$stc);
                    }
                }

                $tbc_hk_he10 =$hk_tc > 0 ? round($hk_diem_he10 / $hk_tc, 2) : 0;
                $tbc_hk_he4  =$hk_tc > 0 ? round($hk_diem_he4 / $hk_tc, 2) : 0;
            ?>
                <div class="semester-block">
                    <!-- HEADER HỌC KỲ -->
                    <div class="semester-header">
                        <div class="semester-title">
                            <i class="fa-solid fa-bookmark"></i> HỌC KỲ <?= htmlspecialchars($hk) ?>
                        </div>
                        <div class="semester-stats">
                            <span>Số TC: <strong><?= $hk_tc ?></strong></span>
                            <span>TBC HK (Hệ 10): <strong><?= number_format($tbc_hk_he10, 2) ?></strong></span>
                            <span>TBC HK (Hệ 4): <strong><?= number_format($tbc_hk_he4, 2) ?></strong></span>
                        </div>
                    </div>

                    <!-- BẢNG ĐIỂM HỌC KỲ -->
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;">STT</th>
                                    <th style="width: 110px;">Mã HP</th>
                                    <th>Tên Học Phần</th>
                                    <th style="width: 80px;">Số TC</th>
                                    <th style="width: 160px;">Điểm Quá Trình (40%)</th>
                                    <th style="width: 140px;">Điểm Thi (60%)</th>
                                    <th style="width: 120px;">Điểm Kết Thúc</th>
                                    <th style="width: 100px;">Điểm Chữ</th>
                                    <th style="width: 140px;">Đánh Giá</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list_mon as $idx =>$g): 
                                    $diem_qt =$g['DiemQT'];
                                    $diem_thi =$g['DiemThi'];
                                    $diem_tk =$g['DiemTK'];
                                    
                                    $is_cam_thi = ($diem_qt !== null && (float)$diem_qt <= 3.0);$is_past_due_no_exam = ($is_past_semester &&$diem_thi === null);

                                    // Nếu là kỳ học cũ mà chưa có điểm thi
                                    if ($is_past_due_no_exam) {$diem_thi = 0.0;
                                        $diem_tk = ($diem_qt !== null) ? round(($diem_qt * 0.4) + ($diem_thi * 0.6), 1) : 0.0;
                                    }

                                    $qd = quyDoiDiem($diem_tk);
                                    $diem_chu = ($is_cam_thi || $is_past_due_no_exam) ? 'F' :$qd['chu'];
                                    $class_chu = ($is_cam_thi || $is_past_due_no_exam) ? 'letter-f' :$qd['class'];
                                    $danh_gia = getDanhGia($diem_qt, $diem_tk,$is_past_due_no_exam);
                                ?>
                                    <tr>
                                        <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $idx + 1 ?></td>
                                        <td style="text-align: center;"><span class="badge-code"><?= htmlspecialchars($g['MaHP']) ?></span></td>
                                        <td><strong style="color: #1e293b;"><?= htmlspecialchars($g['TenHP']) ?></strong></td>
                                        <td style="text-align: center;"><span class="badge-tc"><?= $g['SoTC'] ?> TC</span></td>
                                        
                                        <!-- Điểm quá trình -->
                                        <td style="text-align: center;" data-mon="<?= htmlspecialchars($g['TenHP']) ?>" data-col="Điểm Quá Trình (40%)">
                                            <strong><?= $diem_qt !== null ? number_format($diem_qt, 1) : '-' ?></strong>
                                            <?php if ($is_cam_thi): ?>
                                                <span class="note-camthi"><i class="fa-solid fa-triangle-exclamation"></i> Cấm thi</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Điểm thi -->
                                        <td style="text-align: center;" data-mon="<?= htmlspecialchars($g['TenHP']) ?>" data-col="Điểm Thi (60%)">
                                            <strong><?= $diem_thi !== null ? number_format($diem_thi, 1) : '-' ?></strong>
                                        </td>

                                        <!-- Điểm tổng kết -->
                                        <td style="text-align: center;" data-mon="<?= htmlspecialchars($g['TenHP']) ?>" data-col="Điểm Kết Thúc">
                                            <strong style="color: #0284c7; font-size: 15px;">
                                                <?= ($diem_tk !== null && $diem_tk !== '') ? number_format($diem_tk, 1) : '-' ?>
                                            </strong>
                                        </td>

                                        <!-- Điểm chữ -->
                                        <td style="text-align: center;" data-mon="<?= htmlspecialchars($g['TenHP']) ?>" data-col="Điểm Chữ">
                                            <span class="badge-letter <?= $class_chu ?>"><?= $diem_chu ?></span>
                                        </td>

                                        <!-- Đánh giá môn học -->
                                        <td style="text-align: center;">
                                            <span class="<?= $danh_gia['class'] ?>">
                                                <?= $danh_gia['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<div id="cellTooltip"></div>
<script>
    document.addEventListener('mousemove', function(e) {
        var cell = (e.target && e.target.closest) ? e.target.closest('td[data-mon]') : null;
        var tip = document.getElementById('cellTooltip');
        if (cell) {
            tip.innerHTML = '<i class="fa-solid fa-book"></i> ' + cell.getAttribute('data-mon') +
                '<span class="tip-col">Cột điểm: ' + (cell.getAttribute('data-col') || '') + '</span>';
            tip.style.opacity = '1';
            var x = e.clientX + 14, y = e.clientY + 14;
            if (x + 300 > window.innerWidth) x = e.clientX - 310;
            if (y + 80 > window.innerHeight) y = e.clientY - 70;
            tip.style.left = x + 'px';
            tip.style.top = y + 'px';
        } else {
            tip.style.opacity = '0';
        }
    });
</script>
</body>
</html>

