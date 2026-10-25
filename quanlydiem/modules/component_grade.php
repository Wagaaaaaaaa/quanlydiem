<?php
require_once __DIR__ . '/../config/db.php';

if (function_exists('checkRole')) {
    checkRole(['admin', 'giangvien']);
}

// ---------------------------------------------------------
// GIẢNG VIÊN CHỈ ĐƯỢC THAO TÁC ĐIỂM Ở LỚP MÌNH ĐƯỢC PHÂN CÔNG
// ---------------------------------------------------------
function normalizeTenGV($s) {
    $s = trim((string)$s);
    // Bỏ dấu tiếng Việt (iconv hay lỗi với đ/ế trên Windows)
    $map = [
        'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
        'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','đ'=>'d',
        'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
        'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
        'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
        'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
        'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
        'À'=>'a','Á'=>'a','Ả'=>'a','Ã'=>'a','Ạ'=>'a','Ă'=>'a','Ằ'=>'a','Ắ'=>'a','Ẳ'=>'a','Ẵ'=>'a','Ặ'=>'a',
        'Â'=>'a','Ầ'=>'a','Ấ'=>'a','Ẩ'=>'a','Ẫ'=>'a','Ậ'=>'a','Đ'=>'d',
        'È'=>'e','É'=>'e','Ẻ'=>'e','Ẽ'=>'e','Ẹ'=>'e','Ê'=>'e','Ề'=>'e','Ế'=>'e','Ể'=>'e','Ễ'=>'e','Ệ'=>'e',
        'Ì'=>'i','Í'=>'i','Ỉ'=>'i','Ĩ'=>'i','Ị'=>'i',
        'Ò'=>'o','Ó'=>'o','Ỏ'=>'o','Õ'=>'o','Ọ'=>'o','Ô'=>'o','Ồ'=>'o','Ố'=>'o','Ổ'=>'o','Ỗ'=>'o','Ộ'=>'o',
        'Ơ'=>'o','Ờ'=>'o','Ớ'=>'o','Ở'=>'o','Ỡ'=>'o','Ợ'=>'o',
        'Ù'=>'u','Ú'=>'u','Ủ'=>'u','Ũ'=>'u','Ụ'=>'u','Ư'=>'u','Ừ'=>'u','Ứ'=>'u','Ử'=>'u','Ữ'=>'u','Ự'=>'u',
        'Ỳ'=>'y','Ý'=>'y','Ỷ'=>'y','Ỹ'=>'y','Ỵ'=>'y',
    ];
    $s = strtr($s, $map);
    $s = strtolower($s);
    return preg_replace('/[^a-z0-9]/', '', $s);
}

// Sinh các biến thể khóa tên để so khớp tài khoản <-> tên giảng viên trong phanlop
// VD: "ThS. Lê Thị Lan" -> lethilan, ltlan; "gv_tranvanc" -> gvtranvanc, tranvanc
function cacBienTheTenGV($s) {
    $s = trim((string)$s);
    if ($s === '') return [];

    // Bỏ học hàm / học vị thường gặp
    $s = preg_replace('/\b(?:ThS|TS|PGS|GS|CN|KS|Th\.S|PGS\.TS)\b\.?/i', ' ', $s);

    $words = preg_split('/[\s\.\-\/]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    $plain = [];
    foreach ((array)$words as $w) {
        $w = normalizeTenGV($w);
        if ($w !== '') $plain[] = $w;
    }

    $variants = [];
    if (!empty($plain)) {
        $variants[] = implode('', $plain);          // dạng đầy đủ: tranvanc, nguyenvana
        if (count($plain) > 1) {
            $last = array_pop($plain);
            $init = '';
            foreach ($plain as $w) { $init .= $w[0]; }
            $variants[] = $init . $last;            // dạng viết tắt: ltlan, nvhung
        }
    }

    $out = [];
    foreach ($variants as $v) {
        $out[] = $v;
        $out[] = preg_replace('/^gv/', '', $v);
    }
    return array_values(array_unique(array_filter($out)));
}

// Khớp tên giảng viên $gv với tài khoản đang đăng nhập
function gvTrungKhop($gv) {
    $me = trim($_SESSION['user']['ten'] ?? '');
    // Tên thật của giảng viên (tài khoản tự cấp kiểu gv1/gv2 lưu tên ở cột TenGV)
    $tenGVThuc = trim($_SESSION['user']['TenGV'] ?? '');
    if ($me === '' && $tenGVThuc === '') return false;

    // Khớp chính xác tên đăng nhập hoặc tên thật
    if (strcasecmp(trim($gv), $me) === 0) return true;
    if ($tenGVThuc !== '' && strcasecmp(trim($gv), $tenGVThuc) === 0) return true;

    // Khớp linh hoạt theo biến thể tên (có/không dấu, viết tắt, có/không tiền tố gv)
    $meVariants = cacBienTheTenGV($me);
    foreach (cacBienTheTenGV($tenGVThuc) as $v) {
        if (!in_array($v, $meVariants, true)) $meVariants[] = $v;
    }
    foreach ((array)cacBienTheTenGV($gv) as $vGV) {
        if (in_array($vGV, $meVariants, true)) return true;
    }
    return false;
}

// Kiểm tra lớp $malop có thuộc phân công của tài khoản hiện tại không (admin toàn quyền)
function lopDuocPhanCong($pdo, $malop) {
    if (strtolower($_SESSION['user']['quyen'] ?? '') === 'admin') return true;
    $malop = trim((string)$malop);
    if ($malop === '') return false;

    $stmt = $pdo->prepare("SELECT giangvien FROM phanlop WHERE malop = ?");
    $stmt->execute([$malop]);
    $danhSach = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($danhSach as $gv) {
        if (gvTrungKhop($gv)) return true;
    }
    return false;
}

// Kiểm tra ĐÚNG MÔN (mahp) ở ĐÚNG LỚP (malop) đó có thuộc phân công của tài khoản không
function hocPhanDuocPhanCong($pdo, $malop, $mahp) {
    if (strtolower($_SESSION['user']['quyen'] ?? '') === 'admin') return true;
    $malop = trim((string)$malop);
    $mahp  = trim((string)$mahp);
    if ($malop === '' || $mahp === '') return false;

    $stmt = $pdo->prepare("SELECT giangvien FROM phanlop WHERE malop = ? AND mahp = ?");
    $stmt->execute([$malop, $mahp]);
    $danhSach = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($danhSach as $gv) {
        if (gvTrungKhop($gv)) return true;
    }
    return false;
}

// Chặn thao tác nếu không được phân công lớp (và nếu có mahp thì phải đúng MÔN được phân công)
function chanNeuKhongDuocPhanCong($pdo, $malop, $mahp = '') {
    if (!lopDuocPhanCong($pdo, $malop)) {
        die("<script>alert('Bạn chỉ được thao tác điểm ở lớp MÌNH được phân công giảng dạy!'); window.history.back();</script>");
    }
    $mahp = trim((string)$mahp);
    if ($mahp !== '' && !hocPhanDuocPhanCong($pdo, $malop, $mahp)) {
        die("<script>alert('Bạn chỉ được nhập điểm MÔN mình được phân công giảng dạy ở lớp này!'); window.history.back();</script>");
    }
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

$message = '';
$msg_type = 'success';
$is_searched = isset($_GET['view']); // Kiểm tra đã bấm nút "Hiển thị" chưa

$selected_lop = $_GET['malop'] ?? '';
$selected_hp = $_GET['mahp'] ?? '';
$selected_nganh = $_GET['nganh'] ?? '';

// ---------------------------------------------------------
// 1. XỬ LÝ XÓA ĐIỂM THÀNH PHẦN (CỦA 1 SV HOẶC XÓA TẤT CẢ LỚP)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['masv']) && isset($_POST['mahp'])) {
    $masv = trim($_POST['masv']);
    $mahp = trim($_POST['mahp']);
    $malop = trim($_POST['malop'] ?? '');

    // Giảng viên chỉ được thao tác ở lớp mình được phân công
    chanNeuKhongDuocPhanCong($pdo, $malop, $mahp);

    // Xóa điểm bộ phận => cần quyền 'xoa_diem_bophan' (admin tự có)
    if (!hasExtraPerm($pdo, 'xoa_diem_bophan')) {
        die("<script>alert('Bạn không có quyền XÓA điểm bộ phận! (Cần quyền được cấp thêm)'); window.history.back();</script>");
    }

    $stmt = $pdo->prepare("UPDATE diem SET DiemCC = NULL, DiemKT = NULL, DiemTL = NULL, DiemGK = NULL, DiemQT = NULL WHERE MaSV = ? AND MaHP = ?");
    $stmt->execute([$masv, $mahp]);

    header("Location: component_grade.php?view=1&malop=" . urlencode($malop) . "&mahp=" . urlencode($mahp) . "&msg=deleted");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all' && !empty($_POST['malop']) && !empty($_POST['mahp'])) {
    $malop = $_POST['malop'];
    $mahp = $_POST['mahp'];

    // Giảng viên chỉ được thao tác ở lớp mình được phân công
    chanNeuKhongDuocPhanCong($pdo, $malop, $mahp);

    // Xóa toàn bộ điểm bộ phận => cần quyền 'xoa_diem_bophan'
    if (!hasExtraPerm($pdo, 'xoa_diem_bophan')) {
        die("<script>alert('Bạn không có quyền XÓA điểm bộ phận! (Cần quyền được cấp thêm)'); window.history.back();</script>");
    }

    try {
        $stmtClear = $pdo->prepare("UPDATE diem d 
                                    JOIN sinhvien sv ON d.MaSV = sv.MaSV 
                                    SET d.DiemCC = NULL, d.DiemKT = NULL, d.DiemTL = NULL, d.DiemGK = NULL, d.DiemQT = NULL 
                                    WHERE sv.MaLop = ? AND d.MaHP = ?");
        $stmtClear->execute([$malop, $mahp]);
        header("Location: component_grade.php?view=1&malop=" . urlencode($malop) . "&mahp=" . urlencode($mahp) . "&msg=cleared");
        exit();
    } catch (Exception $e) {
        $message = "Lỗi khi xóa điểm: " . $e->getMessage();
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 2. XỬ LÝ LƯU / CẬP NHẬT ĐIỂM THÀNH PHẦN (POST FORM)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_component_grades'])) {
    $mahp = $_POST['mahp'] ?? '';
    $malopSave = trim($_POST['malop'] ?? '');
    $diem_cc = $_POST['diem_cc'] ?? [];
    $diem_kt = $_POST['diem_kt'] ?? [];
    $diem_tl = $_POST['diem_tl'] ?? [];
    $diem_gk = $_POST['diem_gk'] ?? [];

    // Giảng viên chỉ được nhập/sửa điểm ở lớp mình được phân công
    chanNeuKhongDuocPhanCong($pdo, $malopSave, $mahp);

    // Chặn ghi điểm cho sinh viên KHÔNG thuộc lớp được phân công
    if ($malopSave !== '' && !empty($diem_cc)) {
        $qSv = $pdo->prepare("SELECT COUNT(*) FROM sinhvien WHERE MaSV = ? AND MaLop = ?");
        foreach (array_keys($diem_cc) as $msvKiem) {
            $qSv->execute([$msvKiem, $malopSave]);
            if ($qSv->fetchColumn() < 1) {
                die("<script>alert('Sinh viên " . htmlspecialchars($msvKiem, ENT_QUOTES, 'UTF-8') . " không thuộc lớp được phân công!'); window.history.back();</script>");
            }
        }
    }

    // Cập nhật điểm ĐÃ CÓ => cần quyền 'sua_diem_bophan'; điểm mới nhập bình thường
    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM diem WHERE MaSV = ? AND MaHP = ? AND (DiemCC IS NOT NULL OR DiemKT IS NOT NULL OR DiemTL IS NOT NULL OR DiemGK IS NOT NULL OR DiemQT IS NOT NULL)");
        $isUpdate = false;
        foreach ($diem_cc as $msv => $v) {
            $chk->execute([$msv, $mahp]);
            if ($chk->fetchColumn() > 0) { $isUpdate = true; break; }
        }
        if ($isUpdate && !hasExtraPerm($pdo, 'sua_diem_bophan')) {
            die("<script>alert('Bạn không có quyền SỬA điểm bộ phận đã nhập! (Cần quyền được cấp thêm)'); window.history.back();</script>");
        }
    } catch (PDOException $e) { /* bỏ qua */ }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO diem (MaSV, MaHP, DiemCC, DiemKT, DiemTL, DiemGK, DiemQT) 
                               VALUES (?, ?, ?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE 
                               DiemCC = VALUES(DiemCC), 
                               DiemKT = VALUES(DiemKT), 
                               DiemTL = VALUES(DiemTL), 
                               DiemGK = VALUES(DiemGK), 
                               DiemQT = VALUES(DiemQT)");

        foreach ($diem_cc as $masv => $val_cc) {
            $cc = ($val_cc !== '') ? (float)$val_cc : null;
            $kt = (isset($diem_kt[$masv]) && $diem_kt[$masv] !== '') ? (float)$diem_kt[$masv] : null;
            $tl = (isset($diem_tl[$masv]) && $diem_tl[$masv] !== '') ? (float)$diem_tl[$masv] : null;
            $gk = (isset($diem_gk[$masv]) && $diem_gk[$masv] !== '') ? (float)$diem_gk[$masv] : null;

            $diem_qt = null;
            if ($cc !== null || $kt !== null || $tl !== null || $gk !== null) {
                $v_cc = $cc ?? 0;
                $v_gk = $gk ?? 0;
                $v_bt = (($kt ?? 0) + ($tl ?? 0)) / ($kt !== null && $tl !== null ? 2 : 1);
                
                $diem_qt = round(($v_cc * 0.3) + ($v_gk * 0.5) + ($v_bt * 0.2), 2);
            }

            $stmt->execute([$masv, $mahp, $cc, $kt, $tl, $gk, $diem_qt]);
        }
        $pdo->commit();
        $message = "Lưu danh sách điểm thành phần thành công!";
        $msg_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Lỗi khi lưu điểm: " . $e->getMessage();
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 3. XỬ LÝ IMPORT ĐIỂM TỪ FILE EXCEL (POST JSON)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $excel_data = json_decode($_POST['excel_data'] ?? '[]', true);
    $mahp = $_POST['mahp'] ?? '';

    // Giảng viên chỉ được import điểm ở lớp mình được phân công
    chanNeuKhongDuocPhanCong($pdo, trim($_POST['malop'] ?? ''), $mahp);

    if (!empty($excel_data) && is_array($excel_data) && !empty($mahp)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO diem (MaSV, MaHP, DiemCC, DiemKT, DiemTL, DiemGK, DiemQT) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   DiemCC = VALUES(DiemCC), 
                                   DiemKT = VALUES(DiemKT), 
                                   DiemTL = VALUES(DiemTL), 
                                   DiemGK = VALUES(DiemGK), 
                                   DiemQT = VALUES(DiemQT)");

            $count = 0;
            foreach ($excel_data as $row) {
                $masv = trim($row['Mã SV'] ?? $row['MaSV'] ?? $row['masv'] ?? '');
                if (empty($masv)) continue;

                $cc = isset($row['Chuyên Cần']) || isset($row['DiemCC']) ? (float)($row['Chuyên Cần'] ?? $row['DiemCC']) : null;
                $kt = isset($row['KT / Bài Tập']) || isset($row['DiemKT']) ? (float)($row['KT / Bài Tập'] ?? $row['DiemKT']) : null;
                $tl = isset($row['Thảo Luận']) || isset($row['DiemTL']) ? (float)($row['Thảo Luận'] ?? $row['DiemTL']) : null;
                $gk = isset($row['Giữa Kỳ']) || isset($row['DiemGK']) ? (float)($row['Giữa Kỳ'] ?? $row['DiemGK']) : null;

                $diem_qt = null;
                if ($cc !== null || $kt !== null || $tl !== null || $gk !== null) {
                    $v_cc = $cc ?? 0;
                    $v_gk = $gk ?? 0;
                    $v_bt = (($kt ?? 0) + ($tl ?? 0)) / ($kt !== null && $tl !== null ? 2 : 1);
                    $diem_qt = round(($v_cc * 0.3) + ($v_gk * 0.5) + ($v_bt * 0.2), 2);
                }

                $stmt->execute([$masv, $mahp, $cc, $kt, $tl, $gk, $diem_qt]);
                $count++;
            }
            $pdo->commit();
            $message = "Import Excel thành công! Đã cập nhật điểm cho {$count} sinh viên.";
            $msg_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Lỗi khi import file Excel: " . $e->getMessage();
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
// Giảng viên chỉ thấy các lớp MÌNH được phân công giảng dạy
if (strtolower($_SESSION['user']['quyen'] ?? '') === 'giangvien') {
    $lops = array_values(array_filter($lops, function ($l) use ($pdo) {
        return lopDuocPhanCong($pdo, $l['MaLop']);
    }));
}
$hocphans = $pdo->query("SELECT * FROM hocphan ORDER BY MaHP")->fetchAll();

// Danh sách ngành học (chỉ gồm ngành của các lớp người dùng được phép dùng)
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

// Danh sách học phần theo từng LỚP.
// Giảng viên CHỈ thấy các môn mình được phân công giảng dạy ở lớp đó;
// Admin/khác thấy toàn bộ môn của ngành của lớp.
$laGiangVien = (strtolower($_SESSION['user']['quyen'] ?? '') === 'giangvien');
$hocphansByLop = [];
if ($laGiangVien) {
    $stmtPhanCongTheoLop = $pdo->prepare("SELECT mahp, giangvien FROM phanlop WHERE malop = ?");
    foreach ($lops as $l) {
        $stmtPhanCongTheoLop->execute([$l['MaLop']]);
        $cacDongPhanCong = $stmtPhanCongTheoLop->fetchAll(PDO::FETCH_ASSOC);
        $hocphansByLop[$l['MaLop']] = [];
        $dsMaHPNop = [];
        foreach ($cacDongPhanCong as $dongPhanCong) {
            if (!gvTrungKhop($dongPhanCong['giangvien'] ?? '')) continue;
            $maHPNop = trim((string)($dongPhanCong['mahp'] ?? ''));
            if ($maHPNop === '' || in_array($maHPNop, $dsMaHPNop, true)) continue;
            $dsMaHPNop[] = $maHPNop;
            foreach ($hocphans as $hp) {
                if ($hp['MaHP'] === $maHPNop && hocPhanThuocNganh($hp, trim((string)($l['NganhHoc'] ?? '')))) {
                    $hocphansByLop[$l['MaLop']][] = ['ma' => $hp['MaHP'], 'ten' => $hp['TenHP']];
                    break;
                }
            }
        }
    }
} else {
    foreach ($lops as $l) {
        $nganhCuaLop = trim((string)($l['NganhHoc'] ?? ''));
        $hocphansByLop[$l['MaLop']] = $hocphansByNganh[$nganhCuaLop] ?? [];
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
// 5. LẤY DANH SÁCH SINH VIÊN KHI ĐÃ BẤM "HIỂN THỊ"
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
        // Lớp không thuộc ngành đã chọn trên form
        if ($selected_nganh !== '' && $lopNganh !== '' && strcasecmp($selected_nganh, $lopNganh) !== 0) {
            $message = "Lớp \"" . htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8')
                     . "\" không thuộc ngành đã chọn. Vui lòng chọn lại!";
            $msg_type = 'error';
            $is_searched = false;
        }
    }
    // Học phần phải thuộc ngành của lớp (hoặc là môn dùng chung)
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
    // Giảng viên chỉ được xem/nhập điểm ở ĐÚNG MÔN được phân công ở lớp mình
    if ($is_searched) {
        if (!hocPhanDuocPhanCong($pdo, $selected_lop, $selected_hp)) {
            $message = "Bạn chỉ được xem/nhập điểm ở MÔN mình được phân công giảng dạy ở lớp này!";
            $msg_type = 'error';
            $is_searched = false;
        } else {
            $sql = "SELECT sv.MaSV, sv.HoTen, d.DiemCC, d.DiemKT, d.DiemTL, d.DiemGK, d.DiemQT 
                    FROM sinhvien sv
                    LEFT JOIN diem d ON d.MaSV = sv.MaSV AND d.MaHP = ?
                    WHERE sv.MaLop = ?
                    ORDER BY sv.MaSV";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$selected_hp, $selected_lop]);
            $students = $stmt->fetchAll();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhập Điểm Thành Phần / Quá Trình</title>
    <!-- Font & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Nhúng Style CSS Dùng Chung -->
    <link rel="stylesheet" href="/quanlydiem/assets/css/style.css">
    
    <!-- JS Xuất & Nhập file Excel & PDF -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

<div class="main-wrapper">
    <!-- TIÊU ĐỀ & NÚT QUAY LẠI -->
    <div class="ui-header">
        <div class="ui-title">
            <i class="fa-solid fa-pen-to-square" style="color: #6366f1;"></i> 
            Nhập Điểm Thành Phần / Quá Trình
        </div>
        <a href="../index.php" class="btn-home"><i class="fa-solid fa-arrow-left"></i> Trang chủ</a>
    </div>

    <?php if ($message || isset($_GET['msg'])): ?>
        <div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($message ?: 'Thao tác thành công!', ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- CARD 1: FORM LỌC -->
    <div class="ui-card">
        <form method="GET" class="form-filter-grid">
            <input type="hidden" name="view" value="1">
            <div>
                <label class="form-label">Chọn Ngành Học:</label>
                <select id="nganhSelect" name="nganh" class="custom-select" required onchange="onNganhChange()">
                    <option value="">-- Chọn Ngành --</option>
                    <?php foreach ($dsNganh as $n): ?>
                        <option value="<?= htmlspecialchars($n, ENT_QUOTES, 'UTF-8') ?>" <?= $selected_nganh === $n ? 'selected' : '' ?>>
                            <?= htmlspecialchars($n) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Chọn Lớp Học:</label>
                <select id="lopSelect" name="malop" class="custom-select" required <?= $selected_nganh === '' ? 'disabled' : '' ?> onchange="onLopChange()">
                    <option value="">-- Chọn Lớp --</option>
                </select>
            </div>
            <div>
                <label class="form-label">Chọn Học Phần:</label>
                <select id="hpSelect" name="mahp" class="custom-select" required <?= $selected_nganh === '' ? 'disabled' : '' ?>>
                    <option value="">-- Chọn Học Phần --</option>
                    <?php
                    // Để nguyên hành vi cũ: render sẵn các option học phần.
                    // Nếu đã chọn lớp thì ưu tiên danh sách theo lớp (giảng viên chỉ thấy môn được phân công).
                    $hpOptionsRender = [];
                    if ($selected_lop !== '' && isset($hocphansByLop[$selected_lop])) {
                        $hpOptionsRender = $hocphansByLop[$selected_lop];
                    } elseif ($selected_nganh !== '' && !empty($hocphansByNganh[$selected_nganh])) {
                        $hpOptionsRender = $hocphansByNganh[$selected_nganh];
                    }
                    foreach ($hpOptionsRender as $hpOpt): ?>
                        <option value="<?= htmlspecialchars($hpOpt['ma']) ?>" <?= $selected_hp == $hpOpt['ma'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($hpOpt['ma']) ?> - <?= htmlspecialchars($hpOpt['ten']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search"><i class="fa-solid fa-filter"></i> Hiển Thị</button>
        </form>
    </div>

    <!-- CARD 2: BẢNG ĐIỂM -->
    <div class="ui-card">
        <?php if (!$is_searched): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted, #64748b);">
                <i class="fa-solid fa-square-poll-vertical" style="font-size: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                <p>Vui lòng chọn <strong>Lớp</strong> và <strong>Học Phần</strong> ở phía trên để tải bảng nhập điểm.</p>
            </div>
        <?php else: ?>
            <div class="ui-header" style="margin-bottom: 16px;">
                <div style="font-weight: 700; color: #475569; font-size: 15px;">
                    <i class="fa-solid fa-table-list" style="color: #0284c7;"></i> Bảng Điểm Lớp: <span style="color: #0284c7;"><?= htmlspecialchars($selected_lop) ?></span> — Mã HP: <span style="color: #0284c7;"><?= htmlspecialchars($selected_hp) ?></span>
                </div>
                
                <?php if (!empty($students)): ?>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <!-- Tìm kiếm sinh viên -->
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="tableSearch" onkeyup="filterGradeTable()" placeholder="Tìm SV, Mã SV...">
                        </div>

                        <!-- Nhập file Excel -->
                        <button type="button" class="btn-import" onclick="document.getElementById('excelFileInput').click()">
                            <i class="fa-solid fa-file-import"></i> Nhập Excel
                        </button>
                        <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display: none;" onchange="importExcel(this)">

                        <!-- Xuất Excel & PDF -->
                        <button type="button" onclick="exportTableToExcel('gradeTable', 'Diem_Thanh_Phan_<?= htmlspecialchars($selected_lop) ?>')" class="btn-excel-export">
                            <i class="fa-solid fa-file-excel"></i> Xuất Excel
                        </button>
                        <button type="button" onclick="exportTableToPDF('gradeTable', 'Diem_Thanh_Phan_<?= htmlspecialchars($selected_lop) ?>')" class="btn-pdf-export">
                            <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                        </button>

                        <!-- Xóa sạch điểm lớp -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ điểm thành phần của lớp này?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="action" value="clear_all">
                            <input type="hidden" name="malop" value="<?= htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-clear-all" title="Xóa tất cả điểm lớp này">
                                <i class="fa-solid fa-trash-can"></i> Xóa Điểm Lớp
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="malop" value="<?= htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp) ?>">
                <div style="overflow-x: auto;">
                    <table class="grade-table" id="gradeTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">STT</th>
                                <th style="width: 120px;">Mã SV</th>
                                <th style="text-align: left;">Họ và Tên Sinh Viên</th>
                                <th style="width: 100px;">Chuyên Cần</th>
                                <th style="width: 110px;">KT / Bài Tập</th>
                                <th style="width: 100px;">Thảo Luận</th>
                                <th style="width: 100px;">Giữa Kỳ</th>
                                <th style="width: 110px;">ĐIỂM QT</th>
                                <th style="width: 130px;" data-exclude="true">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="9" style="text-align: center; color: var(--text-muted, #64748b); padding: 30px;">Không tìm thấy sinh viên nào thuộc lớp này.</td></tr>
                            <?php else: ?>
                                <?php foreach ($students as $idx => $sv): 
                                    $has_grade = ($sv['DiemCC'] !== null || $sv['DiemKT'] !== null || $sv['DiemTL'] !== null ||$sv['DiemGK'] !== null);
                                    $diem_qt =$sv['DiemQT'];
                                ?>
                                    <tr id="row-<?= htmlspecialchars($sv['MaSV']) ?>">
                                        <td style="text-align: center; color: var(--text-muted, #64748b); font-weight: 600;"><?= $idx + 1 ?></td>
                                        <td style="text-align: center;"><strong style="color: #6366f1;"><?= htmlspecialchars($sv['MaSV']) ?></strong></td>
                                        <td><strong style="color: var(--text-primary, #f1f5f9);"><?= htmlspecialchars($sv['HoTen']) ?></strong></td>
                                        
                                        <!-- Nhập Chuyên cần -->
                                        <td>
                                            <input type="number" step="0.1" min="0" max="10" 
                                                   name="diem_cc[<?= htmlspecialchars($sv['MaSV']) ?>]" 
                                                   value="<?= $sv['DiemCC'] !== null ? $sv['DiemCC'] : '' ?>" 
                                                   class="input-grade" placeholder="-" 
                                                   <?= $has_grade ? 'disabled' : '' ?> oninput="tinhDiemQT(this)">
                                        </td>

                                        <!-- Nhập KT/BT -->
                                        <td>
                                            <input type="number" step="0.1" min="0" max="10" 
                                                   name="diem_kt[<?= htmlspecialchars($sv['MaSV']) ?>]" 
                                                   value="<?= $sv['DiemKT'] !== null ? $sv['DiemKT'] : '' ?>" 
                                                   class="input-grade" placeholder="-" 
                                                   <?= $has_grade ? 'disabled' : '' ?> oninput="tinhDiemQT(this)">
                                        </td>

                                        <!-- Nhập Thảo luận -->
                                        <td>
                                            <input type="number" step="0.1" min="0" max="10" 
                                                   name="diem_tl[<?= htmlspecialchars($sv['MaSV']) ?>]" 
                                                   value="<?= $sv['DiemTL'] !== null ? $sv['DiemTL'] : '' ?>" 
                                                   class="input-grade" placeholder="-" 
                                                   <?= $has_grade ? 'disabled' : '' ?> oninput="tinhDiemQT(this)">
                                        </td>

                                        <!-- Nhập Giữa kỳ -->
                                        <td>
                                            <input type="number" step="0.1" min="0" max="10" 
                                                   name="diem_gk[<?= htmlspecialchars($sv['MaSV']) ?>]" 
                                                   value="<?= $sv['DiemGK'] !== null ? $sv['DiemGK'] : '' ?>" 
                                                   class="input-grade" placeholder="-" 
                                                   <?= $has_grade ? 'disabled' : '' ?> oninput="tinhDiemQT(this)">
                                        </td>

                                        <!-- Điểm Quá trình -->
                                        <td style="text-align: center;">
                                            <span class="val-qt badge-qt"><?= $diem_qt !== null ? number_format($diem_qt, 2) : '-' ?></span>
                                        </td>

                                        <!-- Nút Thao tác -->
                                        <td style="text-align: center;" data-exclude="true">
                                            <?php if ($has_grade): ?>
                                                <div class="action-group" style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                                    <button type="button" class="btn-action btn-edit" onclick="enableRowEdit('<?= htmlspecialchars($sv['MaSV']) ?>', this)" style="background: #3b82f6; color:#fff; border:none; padding:5px 10px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px;" title="Sửa điểm">
                                                        <i class="fa-solid fa-pen"></i> Sửa
                                                    </button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa điểm của sinh viên này?');">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="masv" value="<?= htmlspecialchars($sv['MaSV'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="mahp" value="<?= htmlspecialchars($selected_hp, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="malop" value="<?= htmlspecialchars($selected_lop, ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" style="background: #ef4444; color:#fff; padding:5px 10px; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;">
                                                            <i class="fa-solid fa-trash"></i> Xóa
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted, #64748b); font-size: 12px; font-weight: 500;">Chưa lưu</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($students)): ?>
                    <div style="margin-top: 24px; text-align: center;">
                        <button type="submit" name="save_component_grades" class="btn-save-all">
                            <i class="fa-solid fa-floppy-disk"></i> Lưu Tất Cả Điểm Thành Phần
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
    <input type="hidden" name="malop" value="<?= htmlspecialchars($selected_lop) ?>">
    <input type="hidden" name="excel_data" id="excelDataInput">
</form>

<script>
const LOPS_BY_NGANH = <?= json_encode($lopsByNganh, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const HOCPHANS_BY_NGANH = <?= json_encode($hocphansByNganh, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const HOCPHANS_BY_LOP = <?= json_encode($hocphansByLop, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const IS_GIANG_VIEN = <?= $laGiangVien ? 'true' : 'false' ?>;
const SELECTED_LOP = <?= json_encode($selected_lop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const SELECTED_HP = <?= json_encode($selected_hp, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function fillHPSel(hpList, enable) {
    const hpSel = document.getElementById('hpSelect');
    hpSel.innerHTML = '<option value="">-- Chọn Học Phần --</option>';
    (hpList || []).forEach(function (h) {
        const opt = document.createElement('option');
        opt.value = h.ma;
        opt.textContent = h.ma + ' - ' + h.ten;
        hpSel.appendChild(opt);
    });
    hpSel.disabled = !enable;
}

// Cập nhật danh sách lớp & học phần theo ngành; chỉ cho chọn sau khi đã chọn ngành
function onNganhChange() {
    const nganh = document.getElementById('nganhSelect').value;
    const lopSel = document.getElementById('lopSelect');
    const hpSel = document.getElementById('hpSelect');

    lopSel.innerHTML = '<option value="">-- Chọn Lớp --</option>';
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
    lopSel.disabled = false;
    if (IS_GIANG_VIEN) {
        // Giảng viên: đợi chọn lớp rồi mới lọc đúng các môn được phân công ở lớp đó
        hpSel.disabled = true;
    } else {
        fillHPSel(HOCPHANS_BY_NGANH[nganh] || [], true);
    }
}

// Khi giảng viên đổi lớp => chỉ hiển thị các môn mình được phân công giảng dạy ở lớp đó
function onLopChange() {
    if (!IS_GIANG_VIEN) return;
    const lop = document.getElementById('lopSelect').value;
    if (!lop || !HOCPHANS_BY_LOP[lop]) {
        fillHPSel([], false);
        return;
    }
    fillHPSel(HOCPHANS_BY_LOP[lop], true);
}

document.addEventListener('DOMContentLoaded', function () {
    onNganhChange();
    if (SELECTED_LOP) {
        document.getElementById('lopSelect').value = SELECTED_LOP;
        onLopChange();
    }
    if (SELECTED_HP) {
        document.getElementById('hpSelect').value = SELECTED_HP;
    }
});

function filterGradeTable() {
    const filter = document.getElementById("tableSearch").value.toLowerCase();
    const rows = document.querySelectorAll("#gradeTable tbody tr");

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
}

function enableRowEdit(masv, btn) {
    const row = document.getElementById('row-' + masv);
    const inputs = row.querySelectorAll('input[type="number"]');
    inputs.forEach(input => {
        input.removeAttribute('disabled');
        input.style.border = '1px solid #0284c7';
    });
    inputs[0].focus();
    
    const actionGroup = btn.closest('.action-group');
    if (actionGroup) {
        actionGroup.style.display = 'none';
    } else {
        btn.style.display = 'none';
    }
}

function tinhDiemQT(input) {
    const row = input.closest('tr');
    const cc = parseFloat(row.querySelector('input[name^="diem_cc"]').value);
    const kt = parseFloat(row.querySelector('input[name^="diem_kt"]').value);
    const tl = parseFloat(row.querySelector('input[name^="diem_tl"]').value);
    const gk = parseFloat(row.querySelector('input[name^="diem_gk"]').value);
    
    let v_cc = isNaN(cc) ? 0 : cc;
    let v_gk = isNaN(gk) ? 0 : gk;

    let bt = 0;
    if (!isNaN(kt) && !isNaN(tl)) {
        bt = (kt + tl) / 2;
    } else if (!isNaN(kt)) {
        bt = kt;
    } else if (!isNaN(tl)) {
        bt = tl;
    }

    const spanQT = row.querySelector('.val-qt');

    if (!isNaN(cc) || !isNaN(kt) || !isNaN(tl) || !isNaN(gk)) {
        const diemQT = (v_cc * 0.3) + (v_gk * 0.5) + (bt * 0.2);
        spanQT.innerText = diemQT.toFixed(2);
    } else {
        spanQT.innerText = '-';
    }
}

// Import bảng điểm từ file Excel
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

            if (confirm(`Tìm thấy ${jsonData.length} dòng điểm trong file. Bạn có chắc chắn muốn nhập vào hệ thống?`)) {
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
    reader.readAsBuffer(file);
}

// Xuất file Excel
function exportTableToExcel(tableID, filename = '') {
    var table = document.getElementById(tableID);
    if (!table) return;

    var clonedTable = table.cloneNode(true);
    var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
    excludedElements.forEach(el => el.remove());

    var inputs = clonedTable.querySelectorAll('input[type="number"]');
    inputs.forEach(input => {
        var val = input.value;
        var parentNode = input.parentNode;
        parentNode.textContent = val !== '' ? val : '-';
    });

    var wb = XLSX.utils.table_to_book(clonedTable, {sheet: "DiemThanhPhan"});
    filename = filename ? filename + '.xlsx' : 'Diem_Thanh_Phan.xlsx';
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

    var title = document.createElement('h3');
    title.innerText = 'BẢNG ĐIỂM THÀNH PHẦN / QUÁ TRÌNH';
    title.style.textAlign = 'center';
    title.style.marginBottom = '5px';
    wrapper.appendChild(title);

    var subTitle = document.createElement('p');
    subTitle.innerText = 'Lớp: <?= htmlspecialchars($selected_lop) ?> \vert{} Mã HP: <?= htmlspecialchars($selected_hp) ?>';
    subTitle.style.textAlign = 'center';
    subTitle.style.fontSize = '12px';
    subTitle.style.marginBottom = '20px';
    wrapper.appendChild(subTitle);

    var clonedTable = table.cloneNode(true);
    var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
    excludedElements.forEach(el => el.remove());

    var inputs = clonedTable.querySelectorAll('input[type="number"]');
    inputs.forEach(input => {
        var val = input.value;
        var parentNode = input.parentNode;
        parentNode.textContent = val !== '' ? val : '-';
    });

    clonedTable.style.width = '100%';
    clonedTable.style.borderCollapse = 'collapse';
    clonedTable.querySelectorAll('th, td').forEach(cell => {
        cell.style.border = '1px solid #333';
        cell.style.padding = '8px';
        cell.style.fontSize = '12px';
        cell.style.color = '#000';
        cell.style.backgroundColor = '#fff';
        cell.style.textAlign = 'center';
    });

    var nameCells = clonedTable.querySelectorAll('tr td:nth-child(3)');
    nameCells.forEach(cell => cell.style.textAlign = 'left');

    wrapper.appendChild(clonedTable);

    filename = filename ? filename + '.pdf' : 'Diem_Thanh_Phan.pdf';

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


