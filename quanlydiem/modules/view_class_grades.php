<?php
require_once __DIR__ . '/../config/db.php';

// Kiểm tra đăng nhập
if (function_exists('checkLogin')) {
    checkLogin();
}

$user = $_SESSION['user'] ?? null;
$quyen = strtolower($user['quyen'] ?? 'guest');

// Chặn quyền sinh viên
if ($quyen === 'sinhvien') {
    echo "<div style='color:#ef4444; text-align:center; margin-top:50px; font-family:sans-serif;'>
            <h3>Bạn không có quyền truy cập chức năng này!</h3>
            <a href='../index.php' style='color:#0284c7;'>Quay lại trang chủ</a>
          </div>";
    exit;
}

$message = '';
$message_type = '';

// Lấy tham số lọc từ URL/Form
$is_searched = isset($_GET['submitted']) && $_GET['submitted'] == '1';
$type = $_GET['type'] ?? 'mon';
$ma_hp = $_GET['ma_hp'] ?? '';
$ma_lop = $_GET['ma_lop'] ?? '';
$nganh_hoc = $_GET['nganh_hoc'] ?? '';

// Lấy danh sách để đổ vào Dropdown
$ds_hocphan = $pdo->query("SELECT MaHP, TenHP FROM hocphan ORDER BY TenHP ASC")->fetchAll();
$ds_lop = $pdo->query("SELECT MaLop, TenLop, NganhHoc FROM lop ORDER BY TenLop ASC")->fetchAll();
$ds_nganhhoc = $pdo->query("SELECT DISTINCT NganhHoc FROM lop WHERE NganhHoc IS NOT NULL AND NganhHoc != '' ORDER BY NganhHoc ASC")->fetchAll();

// Quy đổi điểm kèm Class CSS màu sắc
function quyDoiDiem($diemTK) {
    if ($diemTK === null || $diemTK === '') {
        return ['chu' => 'F', 'he4' => 0.0, 'class' => 'letter-f'];
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

$students = [];
$mon_list = [];

// =========================================================================
// CHỈ TRUY VẤN DỮ LIỆU KHI NGƯỜI DÙNG BẤM "XEM ĐIỂM"
// =========================================================================
if ($is_searched) {
    $where_clauses = [];
    $params = [];

    if ($type === 'lop' && !empty($ma_lop)) {
        $where_clauses[] = "sv.MaLop = ?";
        $params[] = $ma_lop;
    } elseif ($type === 'nganh' && !empty($nganh_hoc)) {
        $where_clauses[] = "l.NganhHoc = ?";
        $params[] = $nganh_hoc;
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

    // Lấy danh sách Sinh viên
    $sql_sv = "SELECT sv.MaSV, sv.HoTen, l.TenLop, l.NganhHoc 
               FROM sinhvien sv 
               JOIN lop l ON sv.MaLop = l.MaLop 
               $where_sql 
               ORDER BY l.TenLop ASC, sv.MaSV ASC";
    $stmt_sv = $pdo->prepare($sql_sv);
    $stmt_sv->execute($params);
    $raw_sv = $stmt_sv->fetchAll();

    foreach ($raw_sv as $s) {
        $students[$s['MaSV']] = [
            'MaSV' => $s['MaSV'],
            'HoTen' => $s['HoTen'],
            'TenLop' => $s['TenLop'],
            'NganhHoc' => $s['NganhHoc'] ?? '-',
            'diem' => []
        ];
    }

    // Lấy danh sách Học Phần
    $hp_where = $where_clauses;
    $hp_params = $params;

    if (!empty($ma_hp)) {
        $hp_where[] = "hp.MaHP = ?";
        $hp_params[] = $ma_hp;
    }

    $hp_where_sql = count($hp_where) > 0 ? "WHERE " . implode(" AND ", $hp_where) : "";

    $sql_hp = "SELECT DISTINCT hp.MaHP, hp.TenHP, hp.SoTC 
               FROM hocphan hp 
               JOIN diem d ON hp.MaHP = d.MaHP 
               JOIN sinhvien sv ON d.MaSV = sv.MaSV 
               JOIN lop l ON sv.MaLop = l.MaLop 
               $hp_where_sql
               ORDER BY hp.MaHP ASC";
    $stmt_hp = $pdo->prepare($sql_hp);
    $stmt_hp->execute($hp_params);
    $mon_list = $stmt_hp->fetchAll();

    if (!empty($ma_hp) && empty($mon_list)) {
        $stmt_single_hp = $pdo->prepare("SELECT MaHP, TenHP, SoTC FROM hocphan WHERE MaHP = ?");
        $stmt_single_hp->execute([$ma_hp]);
        $mon_list = $stmt_single_hp->fetchAll();
    }

    // Lấy Dữ Liệu Điểm
    $diem_where = $where_clauses;
    $diem_params = $params;

    if (!empty($ma_hp)) {
        $diem_where[] = "d.MaHP = ?";
        $diem_params[] = $ma_hp;
    }
    $diem_where_sql = count($diem_where) > 0 ? "WHERE " . implode(" AND ", $diem_where) : "";

    $sql_diem = "SELECT d.* 
                 FROM diem d 
                 JOIN sinhvien sv ON d.MaSV = sv.MaSV 
                 JOIN lop l ON sv.MaLop = l.MaLop 
                 $diem_where_sql";
    $stmt_diem = $pdo->prepare($sql_diem);
    $stmt_diem->execute($diem_params);
    $raw_diem = $stmt_diem->fetchAll();

    foreach ($raw_diem as $d) {
        if (isset($students[$d['MaSV']])) {
            $students[$d['MaSV']]['diem'][$d['MaHP']] = $d;
        }
    }
}

$header_colors = [
    ['bg' => '#e0e7ff', 'border' => '#c7d2fe', 'text' => '#4338ca'],
    ['bg' => '#d1fae5', 'border' => '#a7f3d0', 'text' => '#047857'],
    ['bg' => '#fef3c7', 'border' => '#fde68a', 'text' => '#b45309'],
    ['bg' => '#fce7f3', 'border' => '#fbcfe8', 'text' => '#be185d'],
    ['bg' => '#cffafe', 'border' => '#a5f3fc', 'text' => '#0e7490'],
    ['bg' => '#ede9fe', 'border' => '#ddd6fe', 'text' => '#6d28d9'],
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tra Cứu Bảng Điểm - Management System</title>
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JS Xuất file Excel & PDF -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
        .container { max-width: 100%; margin: 0 auto; }

        /* HEADER NAV */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        .page-title i { background: rgba(99, 102, 241, 0.15); color: var(--primary); padding: 10px; border-radius: 12px; font-size: 20px; border: 1px solid rgba(99, 102, 241, 0.3); }
        .btn-back { background: #eef2f7; color: var(--text-muted); padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid var(--border-color); transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { color: #1e293b; background: #e2e8f0; transform: translateY(-1px); }

        /* CARDS & FORM */
        .card-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--primary); }

        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 600; color: var(--text-muted); }
        .form-control { background: var(--bg-input); border: 1px solid var(--border-color); color: var(--text-main); padding: 11px 14px; border-radius: 10px; font-size: 14px; outline: none; transition: 0.2s; width: 100%; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }

        .btn { border: none; padding: 11px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-size: 14px; height: 44px; }
        .btn-search { background: var(--primary); color: white; width: 100%; }
        .btn-search:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        
        .btn-excel { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; height: 38px; }
        .btn-excel:hover { background: var(--success); color: #fff; }

        .btn-pdf { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; height: 38px; }
        .btn-pdf:hover { background: var(--danger); color: #fff; }

        /* SEARCH BAR */
        .search-box { position: relative; width: 280px; max-width: 100%; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .search-box input { padding-left: 36px; height: 38px; font-size: 13px; border-radius: 8px; }

        /* TABLE MATRIX */
        .table-container { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; white-space: nowrap; }
        th, td { padding: 10px 14px; border: 1px solid var(--border-color); vertical-align: middle; }
        th { background: #eef2f7; color: var(--text-muted); font-size: 11px; font-weight: 700; text-transform: uppercase; text-align: center; letter-spacing: 0.5px; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover td { background: var(--bg-card-hover); }

        /* BADGES & GRADES */
        .badge-code { background: rgba(99, 102, 241, 0.15); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; font-family: monospace; display: inline-block; }
        .badge-letter { padding: 3px 8px; border-radius: 6px; font-weight: 800; font-size: 12px; display: inline-block; min-width: 32px; text-align: center; }
        .letter-a { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); } 
        .letter-b { background: rgba(59, 130, 246, 0.15); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); } 
        .letter-c { background: rgba(234, 179, 8, 0.15); color: #ca8a04; border: 1px solid rgba(234, 179, 8, 0.3); } 
        .letter-d { background: rgba(249, 115, 22, 0.15); color: #ea580c; border: 1px solid rgba(249, 115, 22, 0.3); } 
        .letter-f { background: rgba(239, 68, 68, 0.2); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.5); } 
        
        .badge-not-reg { color: #64748b; font-weight: 800; font-size: 14px; text-align: center; display: block; }
        .gpa-cell { background: rgba(99, 102, 241, 0.15); color: #0284c7; font-weight: 800; font-size: 14px; text-align: center; border-left: 2px solid var(--primary); }
        
        .alert { padding: 14px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #dc2626; }

        /* TOOLTIP KHI HOVER VÀO Ô ĐIỂM */
        td[data-mon] { cursor: help; }
        #cellTooltip { position: fixed; z-index: 9999; background: #1e293b; color: #ffffff; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; font-weight: 600; pointer-events: none; opacity: 0; transition: opacity 0.15s ease; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25); max-width: 300px; line-height: 1.5; }
        #cellTooltip .tip-col { display: block; color: #a5b4fc; font-size: 11.5px; margin-top: 3px; }
    </style>
    <script>
        function toggleFilterInputs() {
            var type = document.getElementById('filter_type').value;
            document.getElementById('group_lop').style.display = (type === 'lop') ? 'flex' : 'none';
            document.getElementById('group_nganh').style.display = (type === 'nganh') ? 'flex' : 'none';
        }

        function filterGradeTable() {
            const filter = document.getElementById("tableSearch").value.toLowerCase();
            const rows = document.querySelectorAll("#gradeTable tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }

        function exportTableToExcel(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) {
                alert('Không tìm thấy bảng dữ liệu!');
                return;
            }

            var clone = table.cloneNode(true);
            // Gỡ các thẻ span (trừ badge điểm) để hiển thị text thuần gọn gàng trong Excel
            clone.querySelectorAll('span').forEach(s => {
                if (!s.classList.contains('badge-letter') && !s.classList.contains('badge-code')) {
                    s.replaceWith(document.createTextNode(s.textContent));
                }
            });
            var badgeStyles = {
                'letter-a': ['#dcfce7', '#16a34a'],
                'letter-b': ['#dbeafe', '#2563eb'],
                'letter-c': ['#fef9c3', '#ca8a04'],
                'letter-d': ['#ffedd5', '#ea580c'],
                'letter-f': ['#fee2e2', '#dc2626'],
                'badge-code': ['#e0e7ff', '#4f46e5']
            };
            Object.keys(badgeStyles).forEach(function(cls) {
                clone.querySelectorAll('.' + cls).forEach(function(el) {
                    el.style.backgroundColor = badgeStyles[cls][0];
                    el.style.color = badgeStyles[cls][1];
                    el.style.padding = '3px 8px';
                    el.style.borderRadius = '6px';
                    el.style.fontWeight = '700';
                });
            });
            // Đặt style trực tiếp cho từng cell để giữ màu nền pastel của header
            clone.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #94a3b8';
                cell.style.padding = '8px 12px';
                cell.style.fontSize = '11pt';
                cell.style.textAlign = 'center';
                cell.style.verticalAlign = 'middle';
            });
            clone.querySelectorAll('.gpa-cell').forEach(td => {
                td.style.backgroundColor = '#e0e7ff';
                td.style.color = '#4f46e5';
                td.style.fontWeight = '800';
            });
            clone.querySelectorAll('tbody tr').forEach((tr, i) => {
                tr.querySelectorAll('td').forEach(td => {
                    if (!td.style.backgroundColor) td.style.backgroundColor = (i % 2 === 1) ? '#f8fafc' : '#ffffff';
                });
            });

            var html = '\uFEFF<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
            html += '<h2 style="text-align:center; color:#4f46e5; font-family:Arial; margin-bottom:4px;">BẢNG ĐIỂM SINH VIÊN</h2>';
            html += '<p style="text-align:center; color:#64748b; font-family:Arial; font-size:10pt; margin-top:0;">Xuất từ hệ thống quản lý điểm - Ngày ' + new Date().toLocaleDateString('vi-VN') + '</p>';
            html += '<table style="border-collapse:collapse; font-family:Arial; width:100%;">' + clone.querySelector('thead').innerHTML + clone.querySelector('tbody').innerHTML + '</table>';
            html += '</body></html>';

            var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = (filename ? filename : 'Bang_Diem_Sinh_Vien') + '.xls';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportTableToPDF(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) {
                alert('Không tìm thấy bảng dữ liệu!');
                return;
            }

            var wrapper = document.createElement('div');
            wrapper.style.padding = '15px';
            wrapper.style.color = '#000';
            wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

            var clonedTable = table.cloneNode(true);
            clonedTable.style.width = '100%';
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.querySelectorAll('span').forEach(s => {
                if (!s.classList.contains('badge-letter') && !s.classList.contains('badge-code')) {
                    s.replaceWith(document.createTextNode(s.textContent));
                }
            });

            // Inline màu cho các badge điểm chữ (do bản clone nằm ngoài DOM nên cần set cứng)
            var badgeStyles = {
                'letter-a': ['rgba(34,197,94,0.15)', '#16a34a'],
                'letter-b': ['rgba(59,130,246,0.15)', '#2563eb'],
                'letter-c': ['rgba(234,179,8,0.15)', '#ca8a04'],
                'letter-d': ['rgba(249,115,22,0.15)', '#ea580c'],
                'letter-f': ['rgba(239,68,68,0.2)', '#dc2626'],
                'badge-code': ['rgba(99,102,241,0.15)', '#4f46e5']
            };
            Object.keys(badgeStyles).forEach(function(cls) {
                clonedTable.querySelectorAll('.' + cls).forEach(function(el) {
                    el.style.backgroundColor = badgeStyles[cls][0];
                    el.style.color = badgeStyles[cls][1];
                    el.style.padding = '3px 8px';
                    el.style.borderRadius = '6px';
                    el.style.fontWeight = '700';
                    el.style.display = 'inline-block';
                });
            });

            clonedTable.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #94a3b8';
                cell.style.padding = '6px 8px';
                cell.style.fontSize = '9px';
                cell.style.color = '#1e293b';
                cell.style.fontWeight = 'normal';
            });

            // Giữ lại màu nền pastel của header (đã set inline khi render), chỉ chỉnh lại nếu chưa có
            clonedTable.querySelectorAll('thead th').forEach(th => {
                if (!th.style.backgroundColor) {
                    th.style.backgroundColor = '#e0e7ff';
                    th.style.color = '#4338ca';
                }
                th.style.fontWeight = '700';
            });

            // Sọc ngựa vằn cho tbody
            clonedTable.querySelectorAll('tbody tr').forEach((tr, i) => {
                tr.querySelectorAll('td').forEach(td => {
                    td.style.backgroundColor = (i % 2 === 1) ? '#f8fafc' : '#ffffff';
                });
            });

            // Cột GPA nổi bật
            clonedTable.querySelectorAll('.gpa-cell').forEach(td => {
                td.style.backgroundColor = 'rgba(99,102,241,0.15)';
                td.style.color = '#4f46e5';
                td.style.fontWeight = '800';
                td.style.textAlign = 'center';
            });

            var title = document.createElement('div');
            title.innerHTML = '<h2 style="margin:0;">BẢNG ĐIỂM SINH VIÊN</h2><p style="margin:4px 0 0; font-size:10px; opacity:0.85;">Xuất từ hệ thống quản lý điểm - ' + new Date().toLocaleDateString('vi-VN') + '</p>';
            title.style.textAlign = 'center';
            title.style.background = '#4f46e5';
            title.style.color = '#ffffff';
            title.style.padding = '12px 16px';
            title.style.borderRadius = '10px';
            title.style.marginBottom = '14px';
            wrapper.appendChild(title);
            wrapper.appendChild(clonedTable);

            filename = filename ? filename + '.pdf' : 'Bang_Diem_Sinh_Vien.pdf';

            wrapper.style.position = 'absolute';
            wrapper.style.left = '-9999px';
            wrapper.style.top = '0';
            wrapper.style.width = '1400px';
            document.body.appendChild(wrapper);

            var opt = {
                margin:       8,
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };

            html2pdf().set(opt).from(wrapper).save().then(function() { wrapper.remove(); });
        }
    </script>
</head>
<body onload="toggleFilterInputs()">
<div class="container">
    <!-- HEADER NAV -->
    <div class="page-header">
        <div class="page-title">
            <i class="fa-solid fa-list-check"></i>
            <div>
                <div>Tra Cứu & Quản Lý Điểm Sinh Viên</div>
                <div style="font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">Xem ma trận điểm học phần, điểm trung bình tích lũy và xuất dữ liệu báo cáo</div>
            </div>
        </div>
        <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Trang chủ</a>
    </div>

    <!-- THÔNG BÁO -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- BỘ LỌC TÌM KIẾM -->
    <div class="card-box">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-filter"></i> Bộ Lọc Tra Cứu Dữ Liệu Điểm
            </div>
        </div>

        <form method="GET" class="filter-form">
            <input type="hidden" name="submitted" value="1">

            <div class="form-group">
                <label>Xem Điểm Theo:</label>
                <select name="type" id="filter_type" class="form-control" onchange="toggleFilterInputs()">
                    <option value="mon" <?= $type === 'mon' ? 'selected' : '' ?>>Theo Môn (Học Phần)</option>
                    <option value="lop" <?= $type === 'lop' ? 'selected' : '' ?>>Toàn Bộ Một Lớp</option>
                    <option value="nganh" <?= $type === 'nganh' ? 'selected' : '' ?>>Toàn Bộ Ngành Học</option>
                </select>
            </div>

            <!-- CHỌN LỚP -->
            <div class="form-group" id="group_lop" style="display:none;">
                <label>Chọn Lớp Học:</label>
                <select name="ma_lop" class="form-control">
                    <option value="">-- Chọn lớp --</option>
                    <?php foreach ($ds_lop as $l): ?>
                        <option value="<?= $l['MaLop'] ?>" <?= $ma_lop === $l['MaLop'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['TenLop']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CHỌN NGÀNH HỌC -->
            <div class="form-group" id="group_nganh" style="display:none;">
                <label>Chọn Ngành Học:</label>
                <select name="nganh_hoc" class="form-control">
                    <option value="">-- Tất cả ngành học --</option>
                    <?php foreach ($ds_nganhhoc as $n): ?>
                        <option value="<?= htmlspecialchars($n['NganhHoc']) ?>" <?= $nganh_hoc === $n['NganhHoc'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n['NganhHoc']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CHỌN MÔN HỌC / HỌC PHẦN -->
            <div class="form-group" id="group_mon">
                <label>Môn / Học Phần:</label>
                <select name="ma_hp" class="form-control">
                    <option value="">-- Tất cả học phần --</option>
                    <?php foreach ($ds_hocphan as $hp): ?>
                        <option value="<?= $hp['MaHP'] ?>" <?= $ma_hp === $hp['MaHP'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($hp['MaHP'] . ' - ' . $hp['TenHP']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-search"><i class="fa-solid fa-magnifying-glass"></i> Xem Bảng Điểm</button>
            </div>
        </form>
    </div>

    <!-- BẢNG HIỂN THỊ DỮ LIỆU MA TRẬN -->
    <?php if ($is_searched): ?>
        <?php if (!empty($students) && !empty($mon_list)): ?>
            <div class="card-box">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-table"></i> Kết Quả Tra Cứu (<?= count($students) ?> Sinh Viên)
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="tableSearch" class="form-control" onkeyup="filterGradeTable()" placeholder="Lọc mã SV, họ tên, lớp...">
                        </div>

                        <button onclick="exportTableToExcel('gradeTable', 'Bang_Diem_Sinh_Vien')" class="btn btn-excel">
                            <i class="fa-solid fa-file-excel"></i> Xuất Excel
                        </button>
                        <button onclick="exportTableToPDF('gradeTable', 'Bang_Diem_Sinh_Vien')" class="btn btn-pdf">
                            <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table id="gradeTable">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width: 50px;">STT</th>
                                <th rowspan="2" style="width: 110px;">Mã SV</th>
                                <th rowspan="2">Họ Và Tên</th>
                                <th rowspan="2" style="width: 130px;">Lớp</th>
                                <th rowspan="2" style="width: 160px;">Ngành Học</th>
                                
                                <?php foreach ($mon_list as $m_idx => $m): 
                                    $color = $header_colors[$m_idx % count($header_colors)];
                                ?>
                                    <th colspan="4" style="background: <?= $color['bg'] ?>; border-color: <?= $color['border'] ?>; color: <?= $color['text'] ?>;">
                                        <?= htmlspecialchars($m['TenHP']) ?> (<?= $m['SoTC'] ?> TC)
                                    </th>
                                <?php endforeach; ?>

                                <?php if (empty($ma_hp)): ?>
                                    <th rowspan="2" style="width: 100px; background: rgba(99, 102, 241, 0.2); color: #0284c7;">GPA (Hệ 4)</th>
                                <?php endif; ?>
                            </tr>
                            
                            <tr>
                                <?php foreach ($mon_list as $m_idx => $m): 
                                    $color = $header_colors[$m_idx % count($header_colors)];
                                ?>
                                    <th style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>; font-size: 11px;">ĐQT</th>
                                    <th style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>; font-size: 11px;">ĐThi</th>
                                    <th style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>; font-size: 11px;">ĐTK</th>
                                    <th style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>; font-size: 11px;">Chữ</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stt = 1;
                            foreach ($students as $sv_id => $sv): 
                                $tong_tc = 0;
                                $tong_diem_he4 = 0;
                            ?>
                                <tr>
                                    <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $stt++ ?></td>
                                    <td style="text-align: center;"><span class="badge-code"><?= htmlspecialchars($sv['MaSV']) ?></span></td>
                                    <td><strong style="color: #1e293b;"><?= htmlspecialchars($sv['HoTen']) ?></strong></td>
                                    <td style="text-align: center;"><?= htmlspecialchars($sv['TenLop']) ?></td>
                                    <td><?= htmlspecialchars($sv['NganhHoc']) ?></td>

                                    <?php foreach ($mon_list as $m): 
                                        $curr_ma_hp = $m['MaHP'];
                                        $stc = (int)$m['SoTC'];
                                        
                                        if (isset($sv['diem'][$curr_ma_hp])) {
                                            $d = $sv['diem'][$curr_ma_hp];
                                            
                                            $qd = quyDoiDiem($d['DiemTK'] ?? null);
                                            
                                            $d_qt = $d['DiemQT'] !== null ? number_format($d['DiemQT'], 1) : '0.0';
                                            $d_thi = $d['DiemThi'] !== null ? number_format($d['DiemThi'], 1) : '0.0';
                                            $d_tk = $d['DiemTK'] !== null ? number_format($d['DiemTK'], 1) : '0.0';

                                            $tong_tc += $stc;
                                            $tong_diem_he4 += ($qd['he4'] * $stc);
                                    ?>
                                            <td style="text-align: center; color: #475569;" data-mon="<?= htmlspecialchars($m['TenHP']) ?>" data-col="Điểm Quá Trình"><?= $d_qt ?></td>
                                            <td style="text-align: center; color: #475569;" data-mon="<?= htmlspecialchars($m['TenHP']) ?>" data-col="Điểm Thi"><?= $d_thi ?></td>
                                            <td style="text-align: center; font-weight: 700; color: #0284c7;" data-mon="<?= htmlspecialchars($m['TenHP']) ?>" data-col="Điểm Tổng Kết"><?= $d_tk ?></td>
                                            <td style="text-align: center;" data-mon="<?= htmlspecialchars($m['TenHP']) ?>" data-col="Điểm Chữ"><span class="badge-letter <?= $qd['class'] ?>"><?= $qd['chu'] ?></span></td>
                                    <?php 
                                        } else { 
                                    ?>
                                            <td colspan="4" data-mon="<?= htmlspecialchars($m['TenHP']) ?>" data-col="Chưa đăng ký / chưa có điểm"><span class="badge-not-reg">-</span></td>
                                    <?php 
                                        }
                                    endforeach; 

                                    $gpa = $tong_tc > 0 ? round($tong_diem_he4 / $tong_tc, 2) : 0;
                                    ?>

                                    <?php if (empty($ma_hp)): ?>
                                        <td class="gpa-cell"><?= number_format($gpa, 2) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card-box" style="text-align: center; color: #ef4444; padding: 50px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.8;"></i>
                Không tìm thấy dữ liệu điểm phù hợp với bộ lọc đã chọn.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="card-box" style="text-align: center; color: var(--text-muted); padding: 50px;">
            <i class="fa-solid fa-folder-open" style="font-size: 36px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
            Vui lòng chọn điều kiện lọc và bấm <strong>"Xem Bảng Điểm"</strong> để truy xuất dữ liệu.
        </div>
    <?php endif; ?>
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
<script src="../assets/js/export_utils.js"></script>
<script>
if (typeof exportTableToExcel !== 'undefined') { exportTableToExcel = function(t, fn) { improvedExportToExcel(t, fn); }; }
if (typeof exportTableToPDF !== 'undefined') { exportTableToPDF = function(t, fn) { improvedExportToPDF(t, fn); }; }
</script>
</body>
</html>


