<?php
require_once __DIR__ . '/../config/db.php';

// Kiểm tra quyền truy cập (Chỉ Admin và Công tác sinh viên)
if (function_exists('checkRole')) {
    checkRole(['admin', 'congtac', 'congtacsinhvien']);
}

// Kiểm tra xem người dùng hiện tại có phải là Admin hay không
$is_admin = isset($_SESSION['user']['quyen']) && $_SESSION['user']['quyen'] === 'admin';

// Quyền sửa/xóa sinh viên: mặc định KHÔNG có, chỉ admin hoặc tài khoản được cấp thêm
$can_edit_sv   = $is_admin || hasExtraPerm($pdo, 'sua_sinhvien');
$can_delete_sv = $is_admin || hasExtraPerm($pdo, 'xoa_sinhvien');
$can_any_sv    = $can_edit_sv || $can_delete_sv;

/**
 * Tự động tạo / cập nhật tài khoản đăng nhập cho sinh viên.
 * - Tên đăng nhập: Mã sinh viên (MaSV)
 * - Mật khẩu: ngày sinh định dạng ddmmyyyy (VD: 02022002)
 */
function ensureStudentAccount(PDO $pdo, string $maSV, ?string $ngaySinh): void {
    $maSV = trim($maSV);
    if ($maSV === '') {
        return;
    }

    $matkhau = '';
    if (!empty($ngaySinh)) {
        $ts = strtotime($ngaySinh);
        if ($ts !== false) {
            $matkhau = date('dmY', $ts);
        }
    }
    // Nếu sinh viên chưa có ngày sinh, dùng chính mã SV làm mật khẩu mặc định
    if ($matkhau === '') {
        $matkhau = $maSV;
    }

    $hash = password_hash($matkhau, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE ten = ? LIMIT 1");
    $stmt->execute([$maSV]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upd = $pdo->prepare("UPDATE users SET matkhau = ?, quyen = 'sinhvien', MaSV = ? WHERE id = ?");
        $upd->execute([$hash, $maSV, $existing['id']]);
    } else {
        try {
            $ins = $pdo->prepare("INSERT INTO users (ten, matkhau, quyen, MaSV) VALUES (?, ?, 'sinhvien', ?)");
            $ins->execute([$maSV, $hash, $maSV]);
        } catch (PDOException $e) {
            // Tài khoản có thể đã tồn tại với MaSV khác -> chỉ cập nhật MaSV
            error_log('Không tạo được tài khoản cho SV ' . $maSV . ': ' . $e->getMessage());
        }
    }
}

// Thêm sinh viên (Cả admin và congtac đều thực hiện được)

// Quy đổi ngày sinh từ định dạng dd/mm/yyyy sang yyyy-mm-dd để lưu CSDL
function parseDobInput(&$pdo_ignored, $raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : null;
}

// Tăng số cuối cùng trong chuỗi (VD: "DHCNKTMK22A2" -> "DHCNKTMK22A3")
function tangSoCuoi($s) {
    return preg_replace_callback('/(\d+)(?!.*\d)/', function ($m) {
        return (string)((int)$m[1] + 1);
    }, $s);
}

// GIỚI HẠN: mỗi lớp 50 SV, mỗi ngành mỗi khóa tối đa 180 SV
const GIOI_HAN_LOP = 50;
const GIOI_HAN_NGANH_KHOA = 180;

// Tổng sĩ số của tất cả các lớp thuộc NGÀNH + KHÓA
function tongSinhVienNganhKhoa($pdo, $nganh, $kh) {
    $sql = "SELECT COALESCE(SUM(SiSo), 0) FROM lop WHERE 1=1";
    $args = [];
    if ($nganh !== '') { $sql .= " AND NganhHoc = ?"; $args[] = $nganh; }
    if ($kh !== '') {
        // khớp cả lớp chưa ghi KhoaHoc (suy ra từ mã lớp, VD DHCTTCK20A1 -> K20)
        $sql .= " AND (KhoaHoc = ? OR (KhoaHoc IS NULL AND MaLop LIKE ?))";
        $args[] = $kh;
        $args[] = '%' . $kh . '%';
    }
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return (int)$st->fetchColumn();
}

// === PHÂN PHỐI LẠI SĨ SỐ: bảo đảm mỗi lớp không quá GIOI_HAN_LOP ===
// Chỉ chuyển bớt sang các lớp CÙNG ngành + khóa còn chỗ; hết chỗ thì tạo lớp mới cùng ngành/khóa.
// Giữ nguyên tổng sĩ số; sinh viên thật (nếu có) cũng được chuyển theo.
function taoLopKeTiep($pdo, $lopMau) {
    $maMoi = preg_match('/A\d+$/i', $lopMau['MaLop'])
        ? preg_replace_callback('/A(\d+)$/i', function ($m) { return 'A' . ((int)$m[1] + 1); }, $lopMau['MaLop'])
        : tangSoCuoi($lopMau['MaLop']);
    $tenMoi = preg_match('/A\s*\d+\s*$/u', $lopMau['TenLop'])
        ? preg_replace_callback('/A\s*(\d+)\s*$/u', function ($m) { return 'A' . ((int)$m[1] + 1); }, $lopMau['TenLop'])
        : tangSoCuoi($lopMau['TenLop']);

    $qChk = $pdo->prepare("SELECT COUNT(*) FROM lop WHERE MaLop = ?");
    $guard = 0;
    while ($guard++ < 100) {
        $qChk->execute([$maMoi]);
        if ((int)$qChk->fetchColumn() === 0) { break; }
        $maMoi = tangSoCuoi($maMoi);
    }

    $pdo->prepare("INSERT INTO lop (MaLop, TenLop, SiSo, NganhHoc, KhoaHoc) VALUES (?, ?, 0, ?, ?)")
        ->execute([$maMoi, $tenMoi, $lopMau['NganhHoc'], $lopMau['KhoaHoc']]);
    return $maMoi;
}

function phanBoSinhVienThua($pdo) {
    // Lấy toàn bộ lớp rồi nhóm theo NGÀNH + KHÓA (khóa suy ra từ mã lớp nếu trống)
    $lops = $pdo->query("SELECT * FROM lop")->fetchAll();
    $groups = [];
    foreach ($lops as $l) {
        $nganh = (string)($l['NganhHoc'] ?? '');
        $khoa  = (string)($l['KhoaHoc'] ?? '');
        if ($khoa === '' && preg_match('/K(\d{2})/i', $l['MaLop'], $m)) {
            $khoa = 'K' . $m[1];
        }
        $groups[$nganh . '|' . $khoa][] = $l;
    }

    $tongDaChuyen = 0;
    $soLopMoi = 0;

    foreach ($groups as $dsLop) {
        // Ưu tiên lớp đông nhất trước
        usort($dsLop, function ($a, $b) { return (int)$b['SiSo'] <=> (int)$a['SiSo']; });

        $siSo = [];
        foreach ($dsLop as $l) { $siSo[$l['MaLop']] = (int)$l['SiSo']; }

        foreach ($dsLop as $l) {
            $maNguon = $l['MaLop'];
            if (($siSo[$maNguon] ?? 0) <= GIOI_HAN_LOP) { continue; }

            $guard = 0;
            while ($siSo[$maNguon] > GIOI_HAN_LOP && $guard++ < 100) {
                $vuot = $siSo[$maNguon] - GIOI_HAN_LOP;

                // Tìm lớp cùng nhóm còn nhiều chỗ nhất
                $maDich = null; $trong = 0;
                foreach ($siSo as $ma => $ss) {
                    if ($ma === $maNguon) { continue; }
                    $conTrong = GIOI_HAN_LOP - $ss;
                    if ($conTrong > $trong) { $trong = $conTrong; $maDich = $ma; }
                }

                // Hết chỗ trong nhóm -> tạo lớp mới cùng ngành/khóa
                if ($maDich === null) {
                    $maDich = taoLopKeTiep($pdo, $l);
                    $siSo[$maDich] = 0;
                    $soLopMoi++;
                }

                $chuyen = min($vuot, GIOI_HAN_LOP - $siSo[$maDich]);
                if ($chuyen <= 0) { break; }

                // Chuyển sinh viên thật (nếu có) rồi cập nhật sĩ số
                $mv = $pdo->prepare("UPDATE sinhvien SET MaLop = ? WHERE MaLop = ? LIMIT " . (int)$chuyen);
                $mv->execute([$maDich, $maNguon]);

                $siSo[$maNguon] -= $chuyen;
                $siSo[$maDich]   += $chuyen;
                $tongDaChuyen    += $chuyen;
            }
        }

        // Ghi lại sĩ số của nhóm
        $upd = $pdo->prepare("UPDATE lop SET SiSo = ? WHERE MaLop = ?");
        foreach ($siSo as $ma => $ss) { $upd->execute([$ss, $ma]); }
    }

    if ($tongDaChuyen === 0) {
        return "Không có lớp nào vượt quá giới hạn " . GIOI_HAN_LOP . " sinh viên.";
    }
    $msg = "Đã phân phối lại $tongDaChuyen sĩ số khỏi các lớp vượt " . GIOI_HAN_LOP . " sang lớp cùng ngành–khóa còn chỗ";
    if ($soLopMoi > 0) { $msg .= " và tạo thêm $soLopMoi lớp mới"; }
    return $msg . '.';
}

// Chọn lớp tự động theo NGÀNH + KHÓA:
//  - Lớp nào còn trống (SiSo < 50) thì chọn;
//  - Nếu các lớp đều đã >= 50 -> tự chuyển sang lớp tiếp theo;
//  - Nếu khóa chưa có lớp nào -> tạo lớp mới (A1) cho khóa đó.
function chonLopTuDong($pdo, $nganh, $kh, &$daTaoLopMoi = false) {
    $daTaoLopMoi = false;
    // Đã đủ 180 SV cho ngành/khóa này -> không chọn/không tạo lớp thêm nữa
    if (tongSinhVienNganhKhoa($pdo, $nganh, $kh) >= GIOI_HAN_NGANH_KHOA) {
        return null;
    }

    $sql = "SELECT * FROM lop WHERE 1=1";
    $args = [];
    if ($nganh !== '') { $sql .= " AND NganhHoc = ?"; $args[] = $nganh; }
    if ($kh !== '') {
        // khớp cả lớp chưa ghi KhoaHoc (suy ra từ mã lớp)
        $sql .= " AND (KhoaHoc = ? OR (KhoaHoc IS NULL AND MaLop LIKE ?))";
        $args[] = $kh;
        $args[] = '%' . $kh . '%';
    }
    $sql .= " ORDER BY MaLop ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $danhSach = $stmt->fetchAll();

    // 1. Lớp còn trống -> chọn ngay
    foreach ($danhSach as $l) {
        if ((int)$l['SiSo'] < GIOI_HAN_LOP) return $l;
    }

    if (!empty($danhSach)) {
        // 2. Các lớp của khóa/ngành này đã đủ 50 -> tự chuyển sang lớp tiếp theo
        $lopGoc = end($danhSach); // lớp cuối (lớp lớn nhất)
        $maMoi = preg_match('/A\d+$/i', $lopGoc['MaLop'])
            ? preg_replace_callback('/A(\d+)$/i', function ($m) { return 'A' . ((int)$m[1] + 1); }, $lopGoc['MaLop'])
            : tangSoCuoi($lopGoc['MaLop']);
        $tenMoi = preg_match('/A\s*\d+\s*$/u', $lopGoc['TenLop'])
            ? preg_replace_callback('/A\s*(\d+)\s*$/u', function ($m) { return 'A' . ((int)$m[1] + 1); }, $lopGoc['TenLop'])
            : tangSoCuoi($lopGoc['TenLop']);
        $khoaMoi = $lopGoc['KhoaHoc'];
        $nganhMoi = $nganh !== '' ? $nganh : ($lopGoc['NganhHoc'] ?? '');
    } else {
        // 3. Khóa chưa có lớp nào -> tạo lớp mới (A1) cho khóa đó
        $sql2 = "SELECT * FROM lop WHERE 1=1";
        $args2 = [];
        if ($nganh !== '') { $sql2 .= " AND NganhHoc = ?"; $args2[] = $nganh; }
        $sql2 .= " ORDER BY MaLop DESC LIMIT 1";
        $q = $pdo->prepare($sql2);
        $q->execute($args2);
        $lopGoc = $q->fetch() ?: $pdo->query("SELECT * FROM lop ORDER BY MaLop DESC LIMIT 1")->fetch();
        if (!$lopGoc) return null;

        if ($kh !== '' && preg_match('/(\d+)/', $kh, $mKh)) {
            // Tạo lớp A1 của khóa mới (VD: K22 -> K23)
            $soKh = $mKh[1];
            $maMoi = preg_match('/K\d+/', $lopGoc['MaLop'])
                ? preg_replace('/K\d+/', 'K' . $soKh, $lopGoc['MaLop'])
                : $lopGoc['MaLop'];
            $maMoi = preg_replace('/A\d+$/', 'A1', $maMoi);
            $tenMoi = preg_replace('/Khóa\s*\d+/u', 'Khóa ' . $soKh, $lopGoc['TenLop']);
            $tenMoi = preg_replace('/A\s*\d+\s*$/u', 'A1', $tenMoi);
            $khoaMoi = 'K' . $soKh;
        } else {
            // Không xác định được khóa -> tăng lớp tiếp theo của lớp mẫu
            $maMoi = tangSoCuoi($lopGoc['MaLop']);
            $tenMoi = tangSoCuoi($lopGoc['TenLop']);
            $khoaMoi = $lopGoc['KhoaHoc'];
        }
        $nganhMoi = $nganh !== '' ? $nganh : ($lopGoc['NganhHoc'] ?? '');
    }

    // Tránh trùng mã lớp (nếu lớp kế tiếp đã tồn tại thì tăng tiếp)
    $qChk = $pdo->prepare("SELECT COUNT(*) FROM lop WHERE MaLop = ?");
    $trung = true;
    while ($trung) {
        $qChk->execute([$maMoi]);
        $trung = $qChk->fetchColumn() > 0;
        if ($trung) $maMoi = tangSoCuoi($maMoi);
    }

    $pdo->prepare("INSERT INTO lop (MaLop, TenLop, SiSo, NganhHoc, KhoaHoc) VALUES (?, ?, 0, ?, ?)")
        ->execute([$maMoi, $tenMoi, $nganhMoi, $khoaMoi]);
    $daTaoLopMoi = true;

    $q = $pdo->prepare("SELECT * FROM lop WHERE MaLop = ?");
    $q->execute([$maMoi]);
    return $q->fetch();
}

if (isset($_POST['add_student'])) {
    // 1) MÃ SINH VIÊN BỊ KHÓ: không nhận từ form, tự sinh = SV + (tổng số SV + 1)
    $n = (int)$pdo->query("SELECT COUNT(*) FROM sinhvien")->fetchColumn() + 1;
    $qChkMa = $pdo->prepare("SELECT COUNT(*) FROM sinhvien WHERE MaSV = ?");
    do {
        $maSvMoi = 'SV' . $n;
        $qChkMa->execute([$maSvMoi]);
        $trung = $qChkMa->fetchColumn() > 0;
        if ($trung) $n++;
    } while ($trung);

    $ngaySinhDb = parseDobInput($pdo, $_POST['NgaySinh'] ?? '');

    // 2) TỰ ĐỘNG CHỌN LỚP: lớp đã >= 50 -> chuyển lớp tiếp theo hoặc tạo lớp mới
    $kh   = trim($_POST['khoa_sv'] ?? '');
    $nganh = trim($_POST['nganh_sv'] ?? '');
    $maLopChon = trim($_POST['MaLop'] ?? '');

    // 3) GIỚI HẠN MỖI NGÀNH MỖI KHÓA TỐI ĐA 180 SINH VIÊN
    $tongNganhKhoa = tongSinhVienNganhKhoa($pdo, $nganh, $kh);
    if ($tongNganhKhoa >= GIOI_HAN_NGANH_KHOA) {
        $msgQuota = "Ngành \"{$nganh}\" khóa {$kh} đã đủ " . GIOI_HAN_NGANH_KHOA
            . " sinh viên (" . $tongNganhKhoa . "/" . GIOI_HAN_NGANH_KHOA . ")! Không thể thêm mới — hãy chọn khóa khác.";
        echo '<script>alert(' . json_encode($msgQuota, JSON_UNESCAPED_UNICODE) . '); window.history.back();</script>';
        exit();
    }

    $lopChon = null;
    if ($maLopChon !== '' && $maLopChon !== '__AUTO__') {
        $q = $pdo->prepare("SELECT * FROM lop WHERE MaLop = ?");
        $q->execute([$maLopChon]);
        $r = $q->fetch();
        // Chỉ nhận lớp đúng ngành + khóa đang chọn (khóa suy ra từ mã lớp nếu ô KhoaHoc trống)
        if ($r) {
            $khoaCuaLop = (string)($r['KhoaHoc'] ?? '');
            if ($khoaCuaLop === '' && preg_match('/K(\d{2})/i', $r['MaLop'], $mkL)) {
                $khoaCuaLop = 'K' . $mkL[1];
            }
            if (($nganh === '' || $r['NganhHoc'] === $nganh) && ($kh === '' || $khoaCuaLop === $kh)) {
                $lopChon = $r;
            }
        }
    }
    $daTaoLopMoi = false;
    if (!$lopChon || (int)$lopChon['SiSo'] >= GIOI_HAN_LOP) {
        $lopChon = chonLopTuDong($pdo, $nganh, $kh, $daTaoLopMoi);
    }
    if (!$lopChon) {
        $msgLop = "Không thể xác định lớp cho sinh viên mới (ngành \"{$nganh}\" khóa {$kh} "
            . "đã đủ " . GIOI_HAN_LOP . " SV/lớp hoặc đủ " . GIOI_HAN_NGANH_KHOA . " SV/ngành-khóa)!";
        echo '<script>alert(' . json_encode($msgLop, JSON_UNESCAPED_UNICODE) . '); window.history.back();</script>';
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO sinhvien (MaSV, MaLop, HoTen, GioiTinh, NgaySinh, QueQuan) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $maSvMoi,
        $lopChon['MaLop'],
        trim($_POST['HoTen'] ?? ''),
        $_POST['GioiTinh'] ?? 'Nam',
        $ngaySinhDb,
        trim($_POST['QueQuan'] ?? '')
    ]);

    // Cập nhật sĩ số lớp (+1)
    $pdo->prepare("UPDATE lop SET SiSo = SiSo + 1 WHERE MaLop = ?")->execute([$lopChon['MaLop']]);

    // Tự động tạo tài khoản: username = MaSV, password = ngày sinh (ddmmyyyy)
    ensureStudentAccount($pdo, $maSvMoi, $ngaySinhDb);

    // Thông báo nếu phải tạo lớp mới do lớp hiện tại đã đủ sĩ số
    if (!empty($daTaoLopMoi)) {
        header("Location: students.php?added=1&newlop=" . urlencode($lopChon['MaLop'])
            . "&tenlop=" . urlencode($lopChon['TenLop']));
    } else {
        header("Location: students.php?added=1");
    }
    exit();
}

// Cập nhật sinh viên (Sửa - CHỈ ADMIN)
if (isset($_POST['update_student'])) {
    if (!$can_edit_sv) {
        die("Bạn không có quyền thực hiện thao tác này!");
    }
    $ngaySinhDbUpd = parseDobInput($pdo, $_POST['NgaySinh'] ?? '');

    // Nếu đổi lớp thì cập nhật lại sĩ số 2 lớp (lớp cũ -1, lớp mới +1)
    $maLopCu = trim($_POST['ma_lop_cu'] ?? '');
    $maLopMoi = trim($_POST['MaLop'] ?? '');
    if ($maLopCu !== '' && $maLopMoi !== '' && $maLopCu !== $maLopMoi) {
        $pdo->prepare("UPDATE lop SET SiSo = GREATEST(SiSo - 1, 0) WHERE MaLop = ?")->execute([$maLopCu]);
        $pdo->prepare("UPDATE lop SET SiSo = SiSo + 1 WHERE MaLop = ?")->execute([$maLopMoi]);
    }

    $stmt = $pdo->prepare("UPDATE sinhvien SET MaLop = ?, HoTen = ?, GioiTinh = ?, NgaySinh = ?, QueQuan = ? WHERE MaSV = ?");
    $stmt->execute([
        $maLopMoi, 
        trim($_POST['HoTen'] ?? ''), 
        $_POST['GioiTinh'] ?? 'Nam', 
        $ngaySinhDbUpd, 
        trim($_POST['QueQuan'] ?? ''), 
        trim($_POST['MaSV'] ?? '')
    ]);

    // Đồng bộ lại mật khẩu tài khoản theo ngày sinh mới
    ensureStudentAccount($pdo, trim($_POST['MaSV'] ?? ''), $ngaySinhDbUpd);

    header("Location: students.php");
    exit();
}

// XỬ LÝ IMPORT SINH VIÊN TỪ FILE EXCEL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $excel_data = json_decode($_POST['excel_data'] ?? '[]', true);
    
    if (!empty($excel_data) && is_array($excel_data)) {
        $count_added = 0;
        $count_updated = 0;

        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM sinhvien WHERE MaSV = ?");
        $stmt_insert = $pdo->prepare("INSERT INTO sinhvien (MaSV, MaLop, HoTen, GioiTinh, NgaySinh, QueQuan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_update = $pdo->prepare("UPDATE sinhvien SET MaLop = ?, HoTen = ?, GioiTinh = ?, NgaySinh = ?, QueQuan = ? WHERE MaSV = ?");

        foreach ($excel_data as $row) {
            // Đọc tiêu đề cột linh hoạt (hỗ trợ cả Tiếng Việt và không dấu)
            $ma_sv   = trim($row['Mã SV'] ?? $row['Mã Sinh Viên'] ?? $row['MaSV'] ?? $row['ma_sv'] ?? '');
            $ho_ten  = trim($row['Họ và Tên'] ?? $row['Họ Tên'] ?? $row['HoTen'] ?? $row['ho_ten'] ?? '');
            $ma_lop  = trim($row['Mã Lớp'] ?? $row['MaLop'] ?? $row['ma_lop'] ?? '');
            $gioi_tinh = trim($row['Giới Tính'] ?? $row['GioiTinh'] ?? $row['gioi_tinh'] ?? 'Nam');
            $que_quan = trim($row['Quê Quán'] ?? $row['QueQuan'] ?? $row['que_quan'] ?? '');
            
            // Xử lý Ngày sinh
            $raw_ngay_sinh = trim($row['Ngày Sinh'] ?? $row['NgaySinh'] ?? $row['ngay_sinh'] ?? '');
            $ngay_sinh = null;
            if (!empty($raw_ngay_sinh)) {
                // Chuyển định dạng DD/MM/YYYY sang YYYY-MM-DD nếu cần
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw_ngay_sinh, $matches)) {
                    $ngay_sinh = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
                } else {
                    $timestamp = strtotime($raw_ngay_sinh);
                    if ($timestamp) {
                        $ngay_sinh = date('Y-m-d', $timestamp);
                    }
                }
            }

            // Chuẩn hóa giới tính
            if (strcasecmp($gioi_tinh, 'Nam') === 0) $gioi_tinh = 'Nam';
            elseif (strcasecmp($gioi_tinh, 'Nữ') === 0 || strcasecmp($gioi_tinh, 'Nu') === 0) $gioi_tinh = 'Nữ';
            else $gioi_tinh = 'Khác';

            if ($ma_sv !== '' && $ho_ten !== '') {
                $stmt_check->execute([$ma_sv]);
                $exists = $stmt_check->fetchColumn() > 0;

                if ($exists) {
                    if ($can_edit_sv) {
                        $stmt_update->execute([$ma_lop, $ho_ten, $gioi_tinh, $ngay_sinh, $que_quan, $ma_sv]);
                        $count_updated++;
                        // Đồng bộ lại mật khẩu tài khoản theo ngày sinh mới
                        ensureStudentAccount($pdo, $ma_sv, $ngay_sinh);
                    }
                } else {
                    $stmt_insert->execute([$ma_sv, $ma_lop, $ho_ten, $gioi_tinh, $ngay_sinh, $que_quan]);
                    $count_added++;
                    // Tự động tạo tài khoản: username = MaSV, password = ngày sinh (ddmmyyyy)
                    ensureStudentAccount($pdo, $ma_sv, $ngay_sinh);
                }
            }
        }
        header("Location: students.php?imported=1&added=$count_added&updated=$count_updated");
        exit();
    }
}

// Xóa sinh viên (CHỈ ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!$can_delete_sv) {
        die("Bạn không có quyền thực hiện thao tác này!");
    }
    $del_sv = trim($_POST['ma_sv'] ?? '');
    if ($del_sv) {
        try {
            // Giữ sĩ số lớp luôn đúng (-1 khi xóa)
            $qLop = $pdo->prepare("SELECT MaLop FROM sinhvien WHERE MaSV = ?");
            $qLop->execute([$del_sv]);
            $lopCuaSv = $qLop->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM sinhvien WHERE MaSV = ?");
            $stmt->execute([$del_sv]);

            if ($lopCuaSv) {
                $pdo->prepare("UPDATE lop SET SiSo = GREATEST(SiSo - 1, 0) WHERE MaLop = ?")->execute([$lopCuaSv]);
            }
            header("Location: students.php");
            exit();
        } catch (PDOException $e) {
            echo "<script>alert('Không thể xóa sinh viên này vì có dữ liệu điểm hoặc đăng ký học phần liên quan!'); window.location.href='students.php';</script>";
            exit();
        }
    }
}

// LẤY THÔNG TIN SINH VIÊN CẦN SỬA (CHỈ ADMIN)
$edit_sv = null;
if (isset($_GET['edit_sv']) && $can_edit_sv) {
    $stmt = $pdo->prepare("SELECT * FROM sinhvien WHERE MaSV = ?");
    $stmt->execute([$_GET['edit_sv']]);
    $edit_sv = $stmt->fetch();
}

$lops = $pdo->query("SELECT * FROM lop ORDER BY TenLop ASC")->fetchAll();
$sinhviens = $pdo->query("SELECT sv.*, l.TenLop FROM sinhvien sv LEFT JOIN lop l ON sv.MaLop = l.MaLop ORDER BY sv.MaSV ASC")->fetchAll();

// --- Dữ liệu cho form THÊM MỚI: khóa / ngành / lớp tự chọn ---
$danhSachNganh = $pdo->query("SELECT DISTINCT NganhHoc FROM lop WHERE NganhHoc IS NOT NULL AND NganhHoc <> '' ORDER BY NganhHoc ASC")->fetchAll(PDO::FETCH_COLUMN);

// Lớp có mã lớn nhất (tham khảo)
$lopMoiNhat = $pdo->query("SELECT * FROM lop ORDER BY MaLop DESC LIMIT 1")->fetch();

// JSON danh sách lớp + sĩ số cho JS tự chọn lớp
$lopData = [];
foreach ($lops as $l) {
    // Nếu lớp chưa ghi KhoaHoc thì tự suy ra từ mã lớp (VD: DHCNKTMK22A1 -> K22)
    $khoaCuaLop = (string)($l['KhoaHoc'] ?? '');
    if ($khoaCuaLop === '' && preg_match('/K(\d{2})/i', $l['MaLop'], $mKL)) {
        $khoaCuaLop = 'K' . $mKL[1];
    }
    $lopData[] = [
        'malop' => $l['MaLop'],
        'tenlop' => $l['TenLop'],
        'khoa'  => $khoaCuaLop,
        'nganh' => (string)($l['NganhHoc'] ?? ''),
        'siso'  => (int)$l['SiSo'],
    ];
}

// Danh sách khóa (suy ra từ cả KhoaHoc lẫn mã lớp), mới nhất lên đầu
$danhSachKhoa = array_values(array_unique(array_filter(array_column($lopData, 'khoa'))));
usort($danhSachKhoa, function ($a, $b) {
    return (int)preg_replace('/\D/', '', $b) <=> (int)preg_replace('/\D/', '', $a);
});
$khoaMoiNhat = $danhSachKhoa[0] ?? 'K1';
// Mặc định là khóa mới nhất (sau khi đã sắp xếp)
$khoaMacDinh = $khoaMoiNhat;
// Tùy chọn "tự động tăng" (VD: K22 -> K23)
if (preg_match('/^K(\d+)$/', $khoaMacDinh, $mK)) {
    $khoaTang = 'K' . ((int)$mK[1] + 1);
} else {
    $khoaTang = $khoaMacDinh;
}

// Ngành mặc định = ngành CÓ LỚP CÒN TRỐNG ở khóa mới nhất (để khi tải trang là thấy lớp ngay)
$nganhMacDinh = '';
foreach (array_reverse($lopData) as $ld) {
    if ($ld['khoa'] === $khoaMoiNhat && $ld['nganh'] !== '' && $ld['siso'] < GIOI_HAN_LOP) {
        $nganhMacDinh = $ld['nganh'];
        break;
    }
}
if ($nganhMacDinh === '') { // mọi lớp của khóa mới nhất đều đủ 50 -> lấy ngành bất kỳ
    foreach (array_reverse($lopData) as $ld) {
        if ($ld['khoa'] === $khoaMoiNhat && $ld['nganh'] !== '') {
            $nganhMacDinh = $ld['nganh'];
            break;
        }
    }
}
if ($nganhMacDinh === '') {
    $nganhMacDinh = $lopMoiNhat['NganhHoc'] ?? ($danhSachNganh[0] ?? '');
}

// Thống kê nhanh
$stat_tong = count($sinhviens);
$stat_nam = 0;
$stat_nu = 0;
foreach ($sinhviens as $s) {
    if (($s['GioiTinh'] ?? '') === 'Nam') $stat_nam++;
    elseif (($s['GioiTinh'] ?? '') === 'Nữ') $stat_nu++;
}
// XỬ LÝ PHÂN PHÓ SINH VIÊN THỨA (Sĩ số lớp > 50)
if (isset($_POST['action']) && $_POST['action'] === 'phan_bo_sv_thua') {
    $message = phanBoSinhVienThua($pdo);
    // Reload page to refresh data
    header("Location: students.php" . ($message ? "?message=" . urlencode($message) : ''));
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sinh viên - System Management</title>
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
            --success-hover: #059669;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); min-height: 100vh; padding: 32px 20px; }
        .container { max-width: 1400px; margin: 0 auto; }

        /* HEADER NAV */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        .page-title i { background: rgba(99, 102, 241, 0.15); color: var(--primary); padding: 10px; border-radius: 12px; font-size: 20px; border: 1px solid rgba(99, 102, 241, 0.3); }
        .btn-back { background: #eef2f7; color: var(--text-muted); padding: 10px 18px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid var(--border-color); transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { color: #1e293b; background: #e2e8f0; transform: translateY(-1px); }

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); padding: 18px 20px; border-radius: 14px; display: flex; align-items: center; gap: 16px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .stat-value { font-size: 22px; font-weight: 800; color: #1e293b; }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; margin-top: 2px; }

        /* CARDS & FORM */
        .card-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--primary); }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px 18px;
            align-items: start;
        }

        .form-group { display: flex; flex-direction: column; min-width: 0; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        .form-group input, .form-group select {
            width: 100% !important;
            height: 44px;
            padding: 0 14px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--bg-input);
            color: var(--text-main);
            font-size: 14px;
            line-height: 44px;
            outline: none;
            transition: 0.2s;
        }
        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 38px;
            cursor: pointer;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .form-group input[readonly], .field-readonly {
            background: #f1f5f9;
            cursor: not-allowed;
            font-weight: 700;
        }
        .form-group input.field-warn { color: #d97706; border-color: #f59e0b; background: #fffbeb; }
        .form-hint { display: block; font-size: 11.5px; color: var(--text-muted); margin-top: 6px; line-height: 1.4; }
        .form-hint.warn { color: #d97706; font-weight: 700; }

        .form-actions { display: flex; gap: 10px; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 11px 22px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 44px; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .btn-cancel { background: #e2e8f0; color: white; border: none; padding: 11px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; transition: 0.2s; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; height: 44px; }
        .btn-cancel:hover { background: #475569; }

        .btn-excel { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
        .btn-excel:hover { background: var(--success); color: #fff; }

        .btn-import { background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(56, 189, 248, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
        .btn-import:hover { background: #0284c7; color: #fff; }

        .btn-pdf { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
        .btn-pdf:hover { background: var(--danger); color: #fff; }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        /* SEARCH BAR */
        .search-box { position: relative; width: 280px; max-width: 100%; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .search-box input { padding-left: 36px; height: 38px; font-size: 13px; border-radius: 8px; }

        /* TABLE STYLES */
        .table-container { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th { background: #eef2f7; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 14px 16px; border-bottom: 1px solid var(--border-color); letter-spacing: 0.5px; white-space: nowrap; }
        td { padding: 12px 16px; border-bottom: 1px solid var(--border-color); color: #475569; vertical-align: middle; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: var(--bg-card-hover); }

        /* BADGES */
        .badge-code { background: rgba(99, 102, 241, 0.15); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; font-family: monospace; display: inline-block; }
        .badge-gender-nam { background: rgba(56, 189, 248, 0.15); color: #0284c7; border: 1px solid rgba(56, 189, 248, 0.3); padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-block; }
        .badge-gender-nu { background: rgba(244, 114, 182, 0.15); color: #db2777; border: 1px solid rgba(244, 114, 182, 0.3); padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-block; }

        .btn-action-edit { color: #0284c7; background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.2); padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 12px; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; margin-right: 6px; }
        .btn-action-edit:hover { background: #0284c7; color: #fff; }

        .btn-action-delete { color: #dc2626; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 6px 12px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 12px; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action-delete:hover { background: #dc2626; color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER NAV -->
        <div class="page-header">
            <div class="page-title">
                <i class="fa-solid fa-user-graduate"></i>
                <div>
                    <div>Quản Lý Sinh Viên</div>
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">Danh sách hồ sơ sinh viên & Quản lý thông tin lớp</div>
                </div>
            </div>
            <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Trang chủ</a>
        </div>

        <?php if (isset($_GET['imported'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Import danh sách sinh viên thành công! 
                (Thêm mới: <strong><?= (int)($_GET['added'] ?? 0) ?></strong>, 
                Cập nhật: <strong><?= (int)($_GET['updated'] ?? 0) ?></strong>)
            </div>
        <?php elseif (isset($_GET['added'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Thêm sinh viên mới thành công!
                <?php if (!empty($_GET['newlop'])): ?>
                    <br>Lớp đã đủ sĩ số nên hệ thống đã tự động tạo lớp mới:
                    <strong><?= htmlspecialchars($_GET['tenlop'] ?? $_GET['newlop']) ?></strong>.
                <?php endif; ?>
            </div>
        <?php elseif (!empty($_GET['message'])): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($_GET['message']) ?>
            </div>
        <?php endif; ?>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stat_tong ?></div>
                    <div class="stat-label">Tổng Sinh Viên</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(56, 189, 248, 0.15); color: #0284c7;">
                    <i class="fa-solid fa-mars"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stat_nam ?></div>
                    <div class="stat-label">Sinh Viên Nam</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(244, 114, 182, 0.15); color: #db2777;">
                    <i class="fa-solid fa-venus"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $stat_nu ?></div>
                    <div class="stat-label">Sinh Viên Nữ</div>
                </div>
            </div>
        </div>

        <!-- NUT PHAN PHOI SINH VIEN THUA -->
        <div style="margin-bottom: 24px;">
            <form method="POST" action="students.php" style="display:inline;">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="phan_bo_sv_thua">
                <button type="submit" class="btn-import" style="background: #a855f7; color: #ffffff;">
                    <i class="fa-solid fa-exchange-alt"></i> Phân Phối Lại Sinh Viên Quá Khớp
                </button>
                <small style="color: #64748b; font-size: 12px; margin-left: 10px;">Chuyển sinh viên khỏi lớp vượt quá 50 SV sang lớp khác</small>
            </form>
        </div>

        <!-- FORM THÊM / SỬA SINH VIÊN -->
        <div class="card-box">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid <?= ($edit_sv &&$can_edit_sv) ? 'fa-user-pen' : 'fa-user-plus' ?>"></i>
                    <?= ($edit_sv &&$can_edit_sv) ? "Chỉnh Sửa Thông Tin Sinh Viên" : "Thêm Sinh Viên Mới" ?>
                </div>
            </div>
            
            <form method="POST" onsubmit="return confirm('<?= ($edit_sv && $can_edit_sv) ? 'Xác nhận LƯU THAY ĐỔI sinh viên này?' : 'Xác nhận THÊM sinh viên mới vào hệ thống?' ?>');">
                <?php csrf_field(); ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Mã Sinh Viên:</label>
                        <?php if ($edit_sv &&$can_edit_sv): ?>
                            <input type="hidden" name="MaSV" value="<?= htmlspecialchars($edit_sv['MaSV']) ?>">
                            <input type="text" value="<?= htmlspecialchars($edit_sv['MaSV']) ?>" disabled style="opacity: 0.7; font-weight: 700;">
                        <?php else: ?>
                            <input type="text" name="MaSV" id="maSvAuto" value="SV<?= $stat_tong + 1 ?>" readonly
                               style="background: #f1f5f9; cursor: not-allowed; font-weight: 700; letter-spacing: 1px;"
                               title="Mã sinh viên tự động, không thể sửa">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Họ và Tên:</label>
                        <input type="text" name="HoTen" placeholder="Nhập họ tên sinh viên" value="<?= htmlspecialchars($edit_sv['HoTen'] ?? '') ?>" required>
                    </div>

                    <?php if ($edit_sv && $can_edit_sv): ?>
                        <div class="form-group">
                            <label>Lớp Chuyên Ngành:</label>
                            <input type="hidden" name="ma_lop_cu" value="<?= htmlspecialchars($edit_sv['MaLop']) ?>">
                            <select name="MaLop" required>
                                <?php foreach ($lops as$l): ?>
                                    <option value="<?= htmlspecialchars($l['MaLop']) ?>" <?= $edit_sv['MaLop'] == $l['MaLop'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['TenLop']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Ngành / Khóa:</label>
                            <?php
                                $khoaSv = '';
                                $nganhSv = '';
                                foreach ($lops as $lc) {
                                    if ($lc['MaLop'] === $edit_sv['MaLop']) {
                                        $khoaSv = $lc['KhoaHoc'] ?? '';
                                        $nganhSv = $lc['NganhHoc'] ?? '';
                                        break;
                                    }
                                }
                            ?>
                            <input type="text" value="<?= htmlspecialchars(trim($nganhSv . ' ' . $khoaSv)) ?>" disabled style="opacity: 0.7;">
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="khoaSvSel" title="Hệ thống mặc định khóa mới nhất. Chọn mục cuối để tự động tăng khóa.">Khóa Sinh Viên</label>
                            <select name="khoa_sv" id="khoaSvSel" required title="Hệ thống mặc định khóa mới nhất; chọn mục cuối để tự động tăng khóa.">
                                <?php foreach ($danhSachKhoa as$k): ?>
                                    <option value="<?= htmlspecialchars($k) ?>" <?= $k === $khoaMoiNhat ? 'selected' : '' ?>><?= htmlspecialchars($k) ?></option>
                                <?php endforeach; ?>
                                <option value="<?= htmlspecialchars($khoaTang) ?>"><?= htmlspecialchars($khoaTang) ?></option>
                            </select>
                            <small class="form-hint">Mặc định chọn khóa mới nhất; mục cuối dùng để tự động tăng khóa.</small>
                        </div>

                        <div class="form-group">
                            <label for="nganhSvSel" title="Chọn ngành học của sinh viên.">Ngành Học</label>
                            <select name="nganh_sv" id="nganhSvSel" required title="Chọn ngành học của sinh viên.">
                                <?php foreach ($danhSachNganh as$ng): ?>
                                    <option value="<?= htmlspecialchars($ng) ?>" <?= $ng === $nganhMacDinh ? 'selected' : '' ?>><?= htmlspecialchars($ng) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="lopSvDisplay" title="Lớp được hệ thống tự xếp theo ngành và khóa nên không chỉnh sửa được.">Lớp Chuyên Ngành</label>
                            <input type="hidden" name="MaLop" id="lopSvValue" value="__AUTO__">
                            <input type="text" id="lopSvDisplay" class="field-readonly" readonly title="Lớp do hệ thống tự xếp theo ngành và khóa." aria-label="Lớp chuyên ngành tự động">
                            <small id="quotaInfo" class="form-hint"></small>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Giới Tính:</label>
                        <select name="GioiTinh">
                            <option value="Nam" <?= ($edit_sv && ($edit_sv['GioiTinh'] ?? '') == 'Nam') ? 'selected' : '' ?>>Nam</option>
                            <option value="Nữ" <?= ($edit_sv && ($edit_sv['GioiTinh'] ?? '') == 'Nữ') ? 'selected' : '' ?>>Nữ</option>
                            <option value="Khác" <?= ($edit_sv && ($edit_sv['GioiTinh'] ?? '') == 'Khác') ? 'selected' : '' ?>>Khác</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ngày Sinh:</label>
                        <input type="text" name="NgaySinh" id="ngaySinhInput" placeholder="dd/mm/yyyy" pattern="\d{2}/\d{2}/\d{4}" title="Nhập ngày sinh theo định dạng dd/mm/yyyy" maxlength="10" value="<?= $edit_sv && !empty($edit_sv['NgaySinh']) ? htmlspecialchars(date('d/m/Y', strtotime($edit_sv['NgaySinh']))) : '' ?>" oninput="validateDOB(this)">
                        <small id="dobError" class="form-hint" style="color: #dc3545; display: none;">Ngày sinh phải trong khoảng 18 đến 60 tuổi.</small>
                    </div>

                    <script>
                        function validateDOB(input) {
                            const value = input.value.replace(/\D/g, '');
                            if (value.length < 8) {
                                input.value = '';
                                document.getElementById('dobError').style.display = 'none';
                                return;
                            }
                            
                            // Format dd/mm/yyyy
                            let v = value.slice(0, 8);
                            if (v.length >= 5) v = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
                            else if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
                            input.value = v;
                            
                            // Validate age
                            const dobInput = document.getElementById('ngaySinhInput');
                            const dobValue = dobInput.value;
                            if (!dobValue || !/^\d{2}\/\d{2}\/\d{4}$/.test(dobValue)) {
                                document.getElementById('dobError').style.display = 'block';
                                input.setCustomValidity('Ngày sinh không hợp lệ');
                                input.reportValidity();
                                return;
                            }
                            
                            const parts = dobValue.split('/');
                            const day = parseInt(parts[0]);
                            const month = parseInt(parts[1]);
                            const year = parseInt(parts[2]);
                            
                            if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > 2026) {
                                document.getElementById('dobError').style.display = 'block';
                                input.setCustomValidity('Ngày sinh không hợp lệ');
                                input.reportValidity();
                                return;
                            }
                            
                            const birthDate = new Date(year, month - 1, day);
                            const today = new Date();
                            // 18 years ago from 2026: 2008-01-01
                            // 60 years ago from 2026: 1966-01-01
                            const minDate = new Date(1966, 0, 1); // 60 years old minimum
                            const maxDate = new Date(2008, 11, 31); // 18 years old maximum
                            
                            if (birthDate < minDate || birthDate > maxDate) {
                                document.getElementById('dobError').style.display = 'block';
                                input.setCustomValidity('Ngày sinh phải trong khoảng 18 đến 60 tuổi.');
                                input.reportValidity();
                            } else {
                                document.getElementById('dobError').style.display = 'none';
                                input.setCustomValidity('');
                            }
                        }
                    </script>

                    <div class="form-group">
                        <label>Quê Quán:</label>
                        <input type="text" name="QueQuan" placeholder="Nhập quê quán" value="<?= htmlspecialchars($edit_sv['QueQuan'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <div class="form-actions">
                            <?php if ($edit_sv &&$can_edit_sv): ?>
                                <button type="submit" name="update_student" class="btn-submit">
                                    <i class="fa-solid fa-floppy-disk"></i> Lưu
                                </button>
                                <a href="students.php" class="btn-cancel">Hủy</a>
                            <?php else: ?>
                                <button type="submit" name="add_student" class="btn-submit">
                                    <i class="fa-solid fa-plus"></i> Thêm Mới
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- BẢNG DANH SÁCH SINH VIÊN -->
        <div class="card-box">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-list"></i> Danh Sách Sinh Viên Hệ Thống
                </div>

                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="tableSearch" onkeyup="filterStudentTable()" placeholder="Lọc mã, tên, lớp, quê quán...">
                    </div>

                    <!-- Button Import File Excel -->
                    <button type="button" class="btn-import" onclick="document.getElementById('excelFileInput').click()">
                        <i class="fa-solid fa-file-import"></i> Nhập Từ Excel
                    </button>
                    <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display: none;" onchange="importExcel(this)">

                    <?php if (count($sinhviens) > 0): ?>
                        <button onclick="exportTableToExcel('studentTable', 'Danh_Sach_Sinh_Vien')" class="btn-excel">
                            <i class="fa-solid fa-file-excel"></i> Xuất Excel
                        </button>
                        <button onclick="exportTableToPDF('studentTable', 'Danh_Sach_Sinh_Vien')" class="btn-pdf">
                            <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-container">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">STT</th>
                            <th style="width: 120px;">Mã SV</th>
                            <th>Họ và Tên</th>
                            <th style="width: 220px;">Lớp Chuyên Ngành</th>
                            <th style="width: 100px; text-align: center;">Giới Tính</th>
                            <th style="width: 120px; text-align: center;">Ngày Sinh</th>
                            <th>Quê Quán</th>
                            <?php if ($can_any_sv): ?>
                                <th style="width: 150px; text-align: center;" data-exclude="true">Thao Tác</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sinhviens) > 0): ?>
                            <?php foreach ($sinhviens as $idx =>$sv): ?>
                            <tr>
                                <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $idx + 1 ?></td>
                                <td><span class="badge-code"><?= htmlspecialchars($sv['MaSV']) ?></span></td>
                                <td><strong style="color: #1e293b;"><?= htmlspecialchars($sv['HoTen']) ?></strong></td>
                                <td><?= htmlspecialchars($sv['TenLop'] ?? 'Chưa phân lớp') ?></td>
                                <td style="text-align: center;">
                                    <?php if ($sv['GioiTinh'] === 'Nam'): ?>
                                        <span class="badge-gender-nam"><i class="fa-solid fa-mars"></i> Nam</span>
                                    <?php elseif ($sv['GioiTinh'] === 'Nữ'): ?>
                                        <span class="badge-gender-nu"><i class="fa-solid fa-venus"></i> Nữ</span>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($sv['GioiTinh']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; color: #4f46e5; font-weight: 500;">
                                    <?= $sv['NgaySinh'] ? date('d/m/Y', strtotime($sv['NgaySinh'])) : '-' ?>
                                </td>
                                <td><?= htmlspecialchars($sv['QueQuan'] ?? '-') ?></td>
                                <?php if ($can_any_sv): ?>
                                    <td style="text-align: center;" data-exclude="true">
                                        <?php if ($can_edit_sv): ?>
                                        <a href="students.php?edit_sv=<?= urlencode($sv['MaSV']) ?>" class="btn-action-edit">
                                            <i class="fa-solid fa-pen-to-square"></i> Sửa
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($can_delete_sv): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có CHẮC CHẮN muốn XÓA sinh viên [<?= htmlspecialchars($sv['HoTen'], ENT_QUOTES, 'UTF-8') ?>] không?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="ma_sv" value="<?= htmlspecialchars($sv['MaSV'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn-action-delete">
                                                <i class="fa-solid fa-trash-can"></i> Xóa
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $can_any_sv ? 8 : 7 ?>" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                    <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 12px; opacity: 0.5; display: block;"></i>
                                    Chưa có sinh viên nào trong hệ thống.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Form ẩn để gửi dữ liệu Excel dạng JSON lên server -->
    <form id="importExcelForm" action="students.php" method="POST" style="display: none;">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="import_excel">
        <input type="hidden" name="excel_data" id="excelDataInput">
    </form>

    <script>
        // === TỰ ĐỘNG CHỌN LỚP CHO SINH VIÊN MỚI ===
        const LOP_DATA = <?= json_encode($lopData, JSON_UNESCAPED_UNICODE) ?>;
        const MAX_SIZE = 50;    // Tối đa 50 SV/lớp
        const MAX_TOTAL = 180;  // Tối đa 180 SV mỗi ngành mỗi khóa

        function fillLopSelect() {
            const khSel = document.getElementById('khoaSvSel');
            const ngSel = document.getElementById('nganhSvSel');
            const disp = document.getElementById('lopSvDisplay');
            const val = document.getElementById('lopSvValue');
            const quotaEl = document.getElementById('quotaInfo');
            const submitBtn = document.querySelector('button[name="add_student"]');
            if (!khSel || !ngSel || !disp) return;

            const kh = khSel.value;
            const ng = ngSel.value;
            const list = LOP_DATA
                .filter(l => (!kh || l.khoa === kh) && (!ng || l.nganh === ng))
                .sort((a, b) => a.malop.localeCompare(b.malop));
            const total = list.reduce((s, l) => s + l.siso, 0);
            const available = list.find(l => l.siso < MAX_SIZE);

            // Lớp luôn do hệ thống tự xếp (không cho chỉnh sửa)
            if (val) val.value = '__AUTO__';
            disp.classList.remove('field-warn');
            if (quotaEl) quotaEl.classList.remove('warn');

            // Đã đủ 180 SV cho ngành/khóa -> không thể thêm mới
            if (total >= MAX_TOTAL) {
                disp.value = 'Đã đủ ' + MAX_TOTAL + ' SV — hãy chọn khóa khác';
                disp.classList.add('field-warn');
                if (submitBtn) { submitBtn.disabled = true; submitBtn.style.opacity = '0.5'; submitBtn.style.cursor = 'not-allowed'; }
                if (quotaEl) {
                    quotaEl.textContent = 'Ngành/khóa đã dùng ' + total + '/' + MAX_TOTAL + ' SV nên không thể thêm mới.';
                    quotaEl.classList.add('warn');
                }
                return;
            }
            if (submitBtn) { submitBtn.disabled = false; submitBtn.style.opacity = '1'; submitBtn.style.cursor = 'pointer'; }

            if (available) {
                // Còn lớp trống -> hiển thị lớp sẽ được xếp tự động
                disp.value = available.tenlop;
                if (quotaEl) {
                    quotaEl.textContent = 'Lớp còn ' + (MAX_SIZE - available.siso) + ' chỗ. Ngành/khóa đã dùng '
                        + total + '/' + MAX_TOTAL + ' SV.';
                }
            } else {
                // Tất cả lớp đều đủ 50 -> hệ thống sẽ tạo lớp mới khi lưu
                disp.value = 'Sẽ tự tạo lớp mới khi lưu';
                disp.classList.add('field-warn');
                if (quotaEl) {
                    quotaEl.textContent = 'Các lớp đã đủ ' + MAX_SIZE + ' SV — hệ thống sẽ tạo lớp mới cho sinh viên. '
                        + 'Ngành/khóa đã dùng ' + total + '/' + MAX_TOTAL + ' SV.';
                    quotaEl.classList.add('warn');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const khSel = document.getElementById('khoaSvSel');
            const ngSel = document.getElementById('nganhSvSel');
            if (khSel) khSel.addEventListener('change', fillLopSelect);
            if (ngSel) ngSel.addEventListener('change', fillLopSelect);
            fillLopSelect();

            // Tự động chèn dấu '/' khi nhập ngày sinh dd/mm/yyyy
            const dob = document.getElementById('ngaySinhInput');
            if (dob) {
                dob.addEventListener('input', function () {
                    let v = this.value.replace(/\D/g, '').slice(0, 8);
                    if (v.length >= 5) v = v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
                    else if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
                    this.value = v;
                });
            }
        });

        // Lọc nhanh dữ liệu bảng trực tiếp bằng JS
        function filterStudentTable() {
            const filter = document.getElementById("tableSearch").value.toLowerCase();
            const rows = document.querySelectorAll("#studentTable tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }

        // Đọc và xử lý Import File Excel
        function importExcel(input) {
            const file = input.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    
                    // Chuyển Sheet thành JSON
                    const jsonData = XLSX.utils.sheet_to_json(worksheet, { raw: false });

                    if (jsonData.length === 0) {
                        alert('File Excel không có dữ liệu hợp lệ!');
                        input.value = '';
                        return;
                    }

                    if (confirm(`Tìm thấy ${jsonData.length} sinh viên trong file. Bạn có chắc chắn muốn import vào hệ thống?`)) {
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

        // Xuất file Excel (Loại bỏ cột Thao tác)
        function exportTableToExcel(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var clonedTable = table.cloneNode(true);
            var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
            excludedElements.forEach(el => el.remove());

            var wb = XLSX.utils.table_to_book(clonedTable, {sheet: "SinhVien"});
            filename = filename ? filename + '.xlsx' : 'Danh_Sach_Sinh_Vien.xlsx';
            XLSX.writeFile(wb, filename);
        }

        // Xuất file PDF chuẩn in ấn (Nền trắng, chữ đen, ẩn cột Thao tác)
        function exportTableToPDF(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.color = '#000';
            wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

            var title = document.createElement('h2');
            title.innerText = 'DANH SÁCH SINH VIÊN';
            title.style.textAlign = 'center';
            title.style.marginBottom = '15px';
            wrapper.appendChild(title);

            var clonedTable = table.cloneNode(true);

            // Loại bỏ cột Thao tác
            var excludedElements = clonedTable.querySelectorAll('[data-exclude="true"]');
            excludedElements.forEach(el => el.remove());

            clonedTable.style.width = '100%';
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #333';
                cell.style.padding = '8px';
                cell.style.fontSize = '11px';
                cell.style.color = '#000';
                cell.style.backgroundColor = '#fff';
            });

            wrapper.appendChild(clonedTable);

            filename = filename ? filename + '.pdf' : 'Danh_Sach_Sinh_Vien.pdf';

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


