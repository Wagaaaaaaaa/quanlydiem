<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/gv_helpers.php';

// Kiểm tra quyền truy cập (Chỉ Admin và Đào tạo)
if (function_exists('checkRole')) {
    checkRole(['admin', 'daotao']);
}

// ---------------------------------------------------------
// 1. HÀM TÁCH KHÓA TỪ MÃ LỚP (Ví dụ: DHCTTCK20A1 -> K20)
// ---------------------------------------------------------
function layKhoa($malop) {
    if (preg_match('/K(\d+)/i', $malop, $m)) {
        return 'K' . $m[1];
    }
    return '';
}

// ---------------------------------------------------------
// 2. HÀM KIỂM TRA MÔN THỰC HÀNH HAY LÝ THUYẾT
// ---------------------------------------------------------
function laMonThucHanh($tenHP, $loaiHP = '') {
    if (!empty($loaiHP) && stristr($loaiHP, 'thuc hanh') !== false) {
        return true;
    }
    $keywords = ['thực hành', 'lập trình', 'phát triển', 'đồ án', 'thực tập', 'lab', 'web'];
    foreach ($keywords as $kw) {
        if (mb_strpos(mb_strtolower($tenHP, 'UTF-8'), $kw) !== false) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------
// 2b. CHỈ ADMIN MỚI ĐƯỢC SỬA / XÓA PHÂN CÔNG
// ---------------------------------------------------------
function laAdmin() {
    $role = strtolower($_SESSION['user']['quyen'] ?? $_SESSION['role'] ?? '');
    return $role === 'admin';
}

// Lấy danh sách giảng viên để làm autocomplete gợi ý khi nhập
$dsGiangVien = [];
try {
    $stmtGV = $pdo->query("SELECT DISTINCT giangvien FROM phanlop WHERE giangvien IS NOT NULL AND giangvien <> '' ORDER BY giangvien ASC");
    $dsGiangVien = $stmtGV->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Bỏ qua nếu lỗi
}

// ---------------------------------------------------------
// 3. LẤY DỮ LIỆU BỘ LỌC (Khóa, Kỳ học, Ngành)
// ---------------------------------------------------------
$dsKhoa = [];
$stmtLop = $pdo->query("SELECT MaLop FROM lop ORDER BY MaLop ASC");
while ($row = $stmtLop->fetch(PDO::FETCH_ASSOC)) {
    $k = layKhoa($row['MaLop']);
    if ($k !== '' && !in_array($k, $dsKhoa)) {
        $dsKhoa[] = $k;
    }
}
usort($dsKhoa, function($a, $b) {
    return intval(filter_var($a, FILTER_SANITIZE_NUMBER_INT)) - intval(filter_var($b, FILTER_SANITIZE_NUMBER_INT));
});

$dsKyHoc = range(1, 8);
$dsNganh = $pdo->query("SELECT DISTINCT NganhHoc FROM lop WHERE NganhHoc IS NOT NULL AND NganhHoc <> '' ORDER BY NganhHoc ASC")->fetchAll(PDO::FETCH_COLUMN);

// Giới hạn hiển thị: mỗi kỳ học tối đa 10 môn học
$gioiHanMonMoiKy = 10;

// Danh sách lớp theo Ngành + Khóa để nạp vào ô chọn Lớp
$lopTheoNganhKhoa = [];
try {
    $stmtAllLop = $pdo->query("SELECT MaLop, TenLop, NganhHoc FROM lop ORDER BY MaLop ASC");
    while ($r = $stmtAllLop->fetch(PDO::FETCH_ASSOC)) {
        $n = trim((string)($r['NganhHoc'] ?? ''));
        if ($n === '') continue;
        $k = layKhoa($r['MaLop']);
        $lopTheoNganhKhoa[$n][$k][] = ['ma' => $r['MaLop'], 'ten' => $r['TenLop']];
    }
} catch (Exception $e) {}

$khoa  = $_GET['khoa'] ?? '';
$kyhoc = $_GET['kyhoc'] ?? '';
$nganh = $_GET['nganh'] ?? '';
$lop   = $_GET['lop'] ?? '';

// Tab phụ: 'assign' (mặc định) hoặc 'assigned' (danh sách đã phân công)
$activeTab = $_GET['tab'] ?? 'assign';
if (!in_array($activeTab, ['assign', 'assigned'], true)) {
    $activeTab = 'assign';
}

$msg = '';
$msg_type = '';

// ---------------------------------------------------------
// 4. XỬ LÝ LƯU PHÂN CÔNG (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_phancong') {
    $malop_arr      = $_POST['malop'] ?? [];
    $mahp_arr       = $_POST['mahp'] ?? [];
    $lophocphan_arr = $_POST['lophocphan'] ?? [];
    $giangvien_arr  = $_POST['giangvien'] ?? [];

    // Sửa phân công đã tồn tại => cần quyền 'sua_lop_hp' (admin tự có)
    try {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM phanlop WHERE mahp = ? AND lophocphan = ?");
        $overwrite = false;
        for ($i = 0; $i < count($mahp_arr); $i++) {
            $chk->execute([trim($mahp_arr[$i] ?? ''), trim($lophocphan_arr[$i] ?? '')]);
            if ($chk->fetchColumn() > 0) { $overwrite = true; break; }
        }
        if ($overwrite && !hasExtraPerm($pdo, 'sua_lop_hp')) {
            die("<script>alert('Bạn không có quyền SỬA lớp học phần đã có phân công! (Cần quyền được cấp thêm)'); window.history.back();</script>");
        }
    } catch (PDOException $e) { /* bỏ qua nếu lỗi kiểm tra */ }

    try {
        $pdo->beginTransaction();

        $stmtDel = $pdo->prepare("DELETE FROM phanlop WHERE malop = ? AND mahp = ? AND lophocphan = ?");
        $stmtIns = $pdo->prepare("INSERT INTO phanlop (mahp, malop, giangvien, lophocphan) VALUES (?, ?, ?, ?)");

        $count = 0;
        $dsGV = [];
        for ($i = 0; $i < count($malop_arr); $i++) {
            $ml  = trim($malop_arr[$i] ?? '');
            $mh  = trim($mahp_arr[$i] ?? '');
            $lhp = trim($lophocphan_arr[$i] ?? '');
            $gv  = trim($giangvien_arr[$i] ?? '');

            if (!empty($ml) && !empty($mh) && !empty($lhp)) {
                $stmtDel->execute([$ml, $mh, $lhp]);
                if (!empty($gv)) {
                    $stmtIns->execute([$mh, $ml, $gv, $lhp]);
                    $count++;
                    $dsGV[] = $gv;
                }
            }
        }

        $pdo->commit();
        $msg = "Đã lưu thành công {$count} phân công giảng dạy!";
        $msg_type = "success";

        // Tự động cấp tài khoản (gv1, gv2...) cho giảng viên chưa có tài khoản
        try {
            $kq = gvCapTaiKhoanHangLoat($pdo, $dsGV);
            foreach ($kq['tao_moi'] as $tk) {
                $msg .= " Đã cấp mới tài khoản '{$tk['ten']}' (mật khẩu: {$tk['matkhau']}) cho giảng viên.";
            }
            if ($kq['da_co'] > 0) {
                $msg .= " {$kq['da_co']} giảng viên khác đã có sẵn tài khoản.";
            }
        } catch (Exception $ex) {
            $msg .= " (Không thể cấp tài khoản giảng viên tự động: " . $ex->getMessage() . ")";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "Lỗi khi lưu dữ liệu: " . $e->getMessage();
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 5. XỬ LÝ IMPORT PHÂN CÔNG TỪ EXCEL (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $excel_data = json_decode($_POST['excel_data'] ?? '[]', true);

    if (!empty($excel_data) && is_array($excel_data)) {
        // Import đè lên phân công cũ => cần quyền 'sua_lop_hp'
        try {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM phanlop WHERE malop = ? AND mahp = ? AND lophocphan = ?");
            $overwrite = false;
            foreach ($excel_data as $row) {
                $ml = trim($row['Mã Lớp'] ?? $row['MaLop'] ?? $row['malop'] ?? '');
                $mh = trim($row['Mã HP'] ?? $row['Mã Học Phần'] ?? $row['MaHP'] ?? $row['mahp'] ?? '');
                $lhp = trim($row['Lớp Học Phần Tách'] ?? $row['Lớp Học Phần'] ?? $row['lophocphan'] ?? '');
                $chk->execute([$ml, $mh, $lhp]);
                if ($chk->fetchColumn() > 0) { $overwrite = true; break; }
            }
            if ($overwrite && !hasExtraPerm($pdo, 'sua_lop_hp')) {
                die("<script>alert('Bạn không có quyền SỬA lớp học phần đã có phân công! (Cần quyền được cấp thêm)'); window.history.back();</script>");
            }
        } catch (PDOException $e) { /* bỏ qua */ }

        try {
            $pdo->beginTransaction();
            $stmtDel = $pdo->prepare("DELETE FROM phanlop WHERE malop = ? AND mahp = ? AND lophocphan = ?");
            $stmtIns = $pdo->prepare("INSERT INTO phanlop (mahp, malop, giangvien, lophocphan) VALUES (?, ?, ?, ?)");

            $count = 0;
            $dsGV = [];
            foreach ($excel_data as $row) {
                $ml  = trim($row['Mã Lớp'] ?? $row['MaLop'] ?? $row['malop'] ?? '');
                $mh  = trim($row['Mã HP'] ?? $row['Mã Học Phần'] ?? $row['MaHP'] ?? $row['mahp'] ?? '');
                $lhp = trim($row['Lớp Học Phần Tách'] ?? $row['Lớp Học Phần'] ?? $row['lophocphan'] ?? '');
                $gv  = trim($row['Giảng Viên Giảng Dạy'] ?? $row['Giảng Viên'] ?? $row['giangvien'] ?? '');

                if (!empty($ml) && !empty($mh) && !empty($lhp)) {
                    $stmtDel->execute([$ml, $mh, $lhp]);
                    if (!empty($gv)) {
                        $stmtIns->execute([$mh, $ml, $gv, $lhp]);
                        $count++;
                        $dsGV[] = $gv;
                    }
                }
            }
            $pdo->commit();
            $msg = "Import Excel thành công! Đã cập nhật {$count} phân công.";
            $msg_type = "success";

            // Tự động cấp tài khoản (gv1, gv2...) cho giảng viên chưa có tài khoản
            try {
                $kq = gvCapTaiKhoanHangLoat($pdo, $dsGV);
                foreach ($kq['tao_moi'] as $tk) {
                    $msg .= " Đã cấp mới tài khoản '{$tk['ten']}' (mật khẩu: {$tk['matkhau']}) cho giảng viên.";
                }
                if ($kq['da_co'] > 0) {
                    $msg .= " {$kq['da_co']} giảng viên khác đã có sẵn tài khoản.";
                }
            } catch (Exception $ex) {
                $msg .= " (Không thể cấp tài khoản giảng viên tự động: " . $ex->getMessage() . ")";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Lỗi khi import file Excel: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $msg = "Dữ liệu Import không hợp lệ!";
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 6. XỬ LÝ XÓA TẤT CẢ PHÂN CÔNG CỦA BỘ LỌC HIỆN TẠI
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all' && !empty($_POST['khoa']) && !empty($_POST['kyhoc']) && !empty($_POST['nganh'])) {
    if (!laAdmin()) {
        die("<script>alert('Chỉ admin mới được XÓA phân công giảng dạy!'); window.history.back();</script>");
    }
    $khoa = $_POST['khoa']; $kyhoc = $_POST['kyhoc']; $nganh = $_POST['nganh'];
    $lopXoa = trim($_POST['lop'] ?? '');
    try {
        // Lấy danh sách lớp theo ngành & khóa
        $stmtLops = $pdo->prepare("SELECT MaLop FROM lop WHERE NganhHoc = ?");
        $stmtLops->execute([$nganh]);
        $targetLops = [];
        while ($r = $stmtLops->fetch(PDO::FETCH_ASSOC)) {
            if (layKhoa($r['MaLop']) === $khoa) {
                $targetLops[] = $r['MaLop'];
            }
        }

        if (!empty($lopXoa)) {
            // Chỉ xóa phân công của đúng lớp đã chọn
            $stmtDelAll = $pdo->prepare("DELETE FROM phanlop WHERE malop = ?");
            $stmtDelAll->execute([$lopXoa]);
            $msg = "Đã xóa toàn bộ phân công của lớp {$lopXoa} (Khóa {$khoa} - Kỳ {$kyhoc} - Ngành {$nganh})!";
            $msg_type = "success";
        } elseif (!empty($targetLops)) {
            $inLop = implode(',', array_fill(0, count($targetLops), '?'));
            $stmtDelAll = $pdo->prepare("DELETE FROM phanlop WHERE malop IN ($inLop)");
            $stmtDelAll->execute($targetLops);
            $msg = "Đã xóa toàn bộ phân công của Khóa {$khoa} - Kỳ {$kyhoc} - Ngành {$nganh}!";
            $msg_type = "success";
        }
    } catch (Exception $e) {
        $msg = "Lỗi khi xóa phân công: " . $e->getMessage();
        $msg_type = "error";
    }
}

// ---------------------------------------------------------
// 6b. SỬA 1 PHÂN CÔNG ĐÃ LƯU (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_phancong') {
    if (!laAdmin()) {
        die("<script>alert('Chỉ admin mới được SỬA phân công giảng dạy!'); window.history.back();</script>");
    }
    $mh  = trim($_POST['mahp'] ?? '');
    $ml  = trim($_POST['malop'] ?? '');
    $lhp = trim($_POST['lophocphan'] ?? '');
    $gvMoi = trim($_POST['giangvien'] ?? '');

    if ($mh === '' || $ml === '' || $lhp === '' || $gvMoi === '') {
        $msg = "Dữ liệu sửa phân công không hợp lệ!";
        $msg_type = "error";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE phanlop SET giangvien = ? WHERE mahp = ? AND malop = ? AND lophocphan = ?");
            $stmt->execute([$gvMoi, $mh, $ml, $lhp]);
            if ($stmt->rowCount() > 0) {
                $msg = "Đã cập nhật giảng viên phân công thành '{$gvMoi}'.";
                $msg_type = "success";

                // Tự động cấp tài khoản (gv1, gv2...) nếu giảng viên mới chưa có tài khoản
                try {
                    $tk = gvTaoTaiKhoan($pdo, $gvMoi);
                    if ($tk && !empty($tk['created'])) {
                        $msg .= " Đã cấp mới tài khoản '{$tk['ten']}' (mật khẩu: {$tk['matkhau']}) cho giảng viên.";
                    }
                } catch (Exception $ex) {
                    $msg .= " (Không thể cấp tài khoản giảng viên tự động: " . $ex->getMessage() . ")";
                }
            } else {
                $msg = "Không tìm thấy phân công cần sửa (có thể dữ liệu đã thay đổi).";
                $msg_type = "error";
            }
        } catch (PDOException $e) {
            $msg = "Không thể cập nhật: giảng viên '{$gvMoi}' đã được phân công cho môn/lớp này rồi.";
            $msg_type = "error";
        }
    }
}

// ---------------------------------------------------------
// 6c. XÓA 1 PHÂN CÔNG ĐÃ LƯU (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_phancong') {
    if (!laAdmin()) {
        die("<script>alert('Chỉ admin mới được XÓA phân công giảng dạy!'); window.history.back();</script>");
    }
    $mh  = trim($_POST['mahp'] ?? '');
    $ml  = trim($_POST['malop'] ?? '');
    $lhp = trim($_POST['lophocphan'] ?? '');

    if ($mh === '' || $ml === '' || $lhp === '') {
        $msg = "Dữ liệu xóa phân công không hợp lệ!";
        $msg_type = "error";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM phanlop WHERE mahp = ? AND malop = ? AND lophocphan = ?");
            $stmt->execute([$mh, $ml, $lhp]);
            $msg = "Đã xóa phân công của lớp {$ml} - môn {$mh}.";
            $msg_type = "success";
        } catch (PDOException $e) {
            $msg = "Lỗi khi xóa phân công: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// ---------------------------------------------------------
// 6d. TẢI DANH SÁCH ĐÃ PHÂN CÔNG (sau khi thêm/sửa/xóa)
// ---------------------------------------------------------
$dsDaPhanCong = [];
try {
    $dsDaPhanCong = $pdo->query(
        "SELECT pl.mahp, pl.malop, pl.giangvien, pl.lophocphan,
                lp.TenLop, lp.NganhHoc,
                hp.TenHP, hp.HocKy, hp.SoTC
         FROM phanlop pl
         LEFT JOIN lop lp ON lp.MaLop = pl.malop
         LEFT JOIN hocphan hp ON hp.MaHP = pl.mahp
         WHERE pl.giangvien IS NOT NULL AND pl.giangvien <> ''
         ORDER BY hp.HocKy ASC, pl.malop ASC, pl.mahp ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$tongDaPhanCong    = count($dsDaPhanCong);
$tongLopDaPhanCong = count(array_unique(array_column($dsDaPhanCong, 'malop')));
$gvSet = [];
foreach ($dsDaPhanCong as $r) {
    $gv = trim((string)($r['giangvien'] ?? ''));
    if ($gv !== '') $gvSet[$gv] = true;
}
$tongGVDaPhanCong = count($gvSet);

// ---------------------------------------------------------
// 7. HIỂN THỊ DỮ LIỆU KHI BẤM NÚT "HIỂN THỊ"
// ---------------------------------------------------------
$dsDong = [];
$daHienThi = (!empty($khoa) && !empty($kyhoc) && !empty($nganh) && !empty($lop));

$stat_tong_lhp = 0;
$stat_tong_tc = 0;
$stat_mon_th = 0;
$tongsMonTrongKy = 0;
$soMonHienThi = 0;
$biCatBotMon = false;

if ($daHienThi) {
    $stmt = $pdo->prepare("SELECT * FROM lop WHERE NganhHoc = ? AND MaLop = ? ORDER BY MaLop ASC");
    $stmt->execute([$nganh, $lop]);
    $danhSachLop = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (layKhoa($row['MaLop']) === $khoa) {
            $danhSachLop[] = $row;
        }
    }

    $stmtHP = $pdo->prepare("SELECT * FROM hocphan WHERE HocKy = ? ORDER BY MaHP ASC");
    $stmtHP->execute([$kyhoc]);
    $allHP = $stmtHP->fetchAll(PDO::FETCH_ASSOC);
    $tongsMonTrongKy = count($allHP);
    // Mỗi kỳ học chỉ hiển thị tối đa 10 môn học
    $danhSachHP = array_slice($allHP, 0, $gioiHanMonMoiKy);
    $soMonHienThi = count($danhSachHP);
    $biCatBotMon = $tongsMonTrongKy > $soMonHienThi;

    $phancongDaCo = [];
    $pcResult = $pdo->query("SELECT * FROM phanlop")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pcResult as $pc) {
        $key = $pc['malop'] . '|' . $pc['mahp'] . '|' . $pc['lophocphan'];
        $phancongDaCo[$key] = $pc['giangvien'];
    }

    foreach ($danhSachLop as $lopItem) {
        $siso = intval($lopItem['SiSo'] ?? 0);

        foreach ($danhSachHP as $hp) {
            $tenHP  = $hp['TenHP'];
            $loaiHP = $hp['LoaiHP'] ?? '';
            $isTH   = laMonThucHanh($tenHP, $loaiHP);

            $gioiHan = $isTH ? 40 : 70;

            if ($siso > $gioiHan) {
                $siso_nhom1 = ceil($siso / 2);
                $siso_nhom2 = floor($siso / 2);

                $lhp1 = $tenHP . " 01/" . $lopItem['MaLop'] . " - Nhóm 01";
                $key1 = $lopItem['MaLop'] . '|' . $hp['MaHP'] . '|' . $lhp1;

                $dsDong[] = [
                    'malop'      => $lopItem['MaLop'],
                    'tenlop'     => $lopItem['TenLop'],
                    'siso'       => $siso_nhom1,
                    'nganh'      => $lopItem['NganhHoc'],
                    'mahp'       => $hp['MaHP'],
                    'tenhp'      => $tenHP,
                    'sotinchi'   => $hp['SoTC'],
                    'lophocphan' => $lhp1,
                    'is_th'      => $isTH,
                    'giangvien'  => $phancongDaCo[$key1] ?? ''
                ];

                $lhp2 = $tenHP . " 02/" . $lopItem['MaLop'] . " - Nhóm 02";
                $key2 = $lopItem['MaLop'] . '|' . $hp['MaHP'] . '|' . $lhp2;

                $dsDong[] = [
                    'malop'      => $lopItem['MaLop'],
                    'tenlop'     => $lopItem['TenLop'],
                    'siso'       => $siso_nhom2,
                    'nganh'      => $lopItem['NganhHoc'],
                    'mahp'       => $hp['MaHP'],
                    'tenhp'      => $tenHP,
                    'sotinchi'   => $hp['SoTC'],
                    'lophocphan' => $lhp2,
                    'is_th'      => $isTH,
                    'giangvien'  => $phancongDaCo[$key2] ?? ''
                ];
            } else {
                $lhp = $tenHP . "/" . $lopItem['MaLop'];
                $key = $lopItem['MaLop'] . '|' . $hp['MaHP'] . '|' . $lhp;

                $dsDong[] = [
                    'malop'      => $lopItem['MaLop'],
                    'tenlop'     => $lopItem['TenLop'],
                    'siso'       => $siso,
                    'nganh'      => $lopItem['NganhHoc'],
                    'mahp'       => $hp['MaHP'],
                    'tenhp'      => $tenHP,
                    'sotinchi'   => $hp['SoTC'],
                    'lophocphan' => $lhp,
                    'is_th'      => $isTH,
                    'giangvien'  => $phancongDaCo[$key] ?? ''
                ];
            }
        }
    }

    $stat_tong_lhp = count($dsDong);
    foreach ($dsDong as $d) {
        $stat_tong_tc += intval($d['sotinchi']);
        if ($d['is_th']) $stat_mon_th++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phân Công Giảng Dạy - System Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JS Xuất & Nhập file Excel & PDF -->
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

        /* CARDS */
        .card-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px; padding: 24px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; flex-wrap: wrap; gap: 12px; }
        .card-title { font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .card-title i { color: var(--primary); }

        /* FILTER GRID */
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) auto; gap: 16px; align-items: end; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        select, input[type="text"] { width: 100%; padding: 11px 14px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); font-size: 14px; outline: none; transition: 0.2s; }
        select:focus, input[type="text"]:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        
        /* BUTTONS */
        .btn-submit { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .btn-save { background: var(--success); color: white; border: none; padding: 12px 28px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 15px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-save:hover { background: var(--success-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }

        .btn-import { background: rgba(2, 132, 199, 0.15); color: #0284c7; border: 1px solid rgba(2, 132, 199, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; }
        .btn-import:hover { background: #0284c7; color: #fff; }

        .btn-excel { background: rgba(16, 185, 129, 0.15); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
        .btn-excel:hover { background: var(--success); color: #fff; }

        .btn-pdf { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
        .btn-pdf:hover { background: var(--danger); color: #fff; }

        .btn-clear-all { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); padding: 9px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; text-decoration: none; }
        .btn-clear-all:hover { background: var(--danger); color: #fff; }

        /* ALERTS */
        .alert { padding: 14px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(34, 197, 94, 0.15); color: #16a34a; border: 1px solid rgba(34, 197, 94, 0.3); }
        .alert-error { background: rgba(239, 68, 68, 0.15); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); }

        /* STATS GRID */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border-color); padding: 18px; border-radius: 14px; display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-value { font-size: 20px; font-weight: 800; color: #1e293b; }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 600; }

        /* SEARCH BAR IN TABLE TOOLBAR */
        .search-box { position: relative; width: 250px; max-width: 100%; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .search-box input { padding-left: 36px; height: 38px; font-size: 13px; border-radius: 8px; }

        /* TABLE DESIGN */
        .table-container { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border-color); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th { background: #eef2f7; color: var(--text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 14px 14px; border-bottom: 1px solid var(--border-color); letter-spacing: 0.5px; white-space: nowrap; }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); color: #475569; vertical-align: middle; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: var(--bg-card-hover); }
        
        .badge-code { background: rgba(99, 102, 241, 0.15); color: #4f46e5; border: 1px solid rgba(99, 102, 241, 0.3); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 12px; font-family: monospace; display: inline-block; white-space: nowrap; }
        
        /* BADGE THỰC HÀNH VÀ LỚP HỌC PHẦN */
        .hp-title-box { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .badge-th { 
            background: rgba(245, 158, 11, 0.15); 
            color: #d97706; 
            border: 1px solid rgba(245, 158, 11, 0.4); 
            padding: 3px 8px; 
            border-radius: 6px; 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            white-space: nowrap; 
            display: inline-flex; 
            align-items: center;
            line-height: 1.2;
        }

        .badge-lhp { 
            background: rgba(16, 185, 129, 0.12); 
            color: #059669; 
            border: 1px solid rgba(16, 185, 129, 0.3); 
            padding: 6px 12px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 12.5px; 
            display: inline-block; 
            line-height: 1.4;
            word-break: break-word;
        }

        /* INPUT GIẢNG VIÊN CONTAINER */
        .gv-input-wrapper { position: relative; display: flex; align-items: center; }
        .gv-input-wrapper input { padding-right: 30px; font-size: 13px; height: 38px; }
        .gv-input-wrapper .btn-clear { position: absolute; right: 8px; color: var(--text-muted); cursor: pointer; border: none; background: none; font-size: 12px; padding: 4px; }
        .gv-input-wrapper .btn-clear:hover { color: var(--danger); }
        .row-empty-gv input { border-color: rgba(245, 158, 11, 0.5); }

        /* Tab phụ */
        .page-tabs {
            display: flex;
            gap: 6px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .page-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            border-radius: 8px 8px 0 0;
            transition: all 0.2s;
        }
        .page-tab:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.06);
        }
        .page-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: rgba(99, 102, 241, 0.08);
        }
        .page-tab .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 20px;
            padding: 0 7px;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font-size: 11.5px;
            font-weight: 700;
        }

        /* Nút thao tác nhỏ trong bảng phân công */
        .btn-mini {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 11px;
            font-size: 12.5px;
            font-weight: 600;
            border: 1px solid transparent;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.18s;
            white-space: nowrap;
        }
        .btn-mini-edit {
            background: rgba(99, 102, 241, 0.12);
            color: #4f46e5;
            border-color: rgba(99, 102, 241, 0.3);
        }
        .btn-mini-edit:hover { background: rgba(99, 102, 241, 0.22); }
        .btn-mini-delete {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border-color: rgba(239, 68, 68, 0.3);
        }
        .btn-mini-delete:hover { background: rgba(239, 68, 68, 0.22); }
        .btn-mini-save {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border-color: rgba(16, 185, 129, 0.35);
            padding: 7px 10px;
        }
        .btn-mini-save:hover { background: rgba(16, 185, 129, 0.25); }
        .btn-mini-cancel {
            background: rgba(100, 116, 139, 0.12);
            color: #64748b;
            border-color: rgba(100, 116, 139, 0.3);
            padding: 7px 10px;
        }
        .btn-mini-cancel:hover { background: rgba(100, 116, 139, 0.22); }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fa-solid fa-chalkboard-user"></i>
                <div>
                    <div>Phân Công Giảng Dạy</div>
                    <div style="font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 2px;">Tự động chia lớp học phần & Quản lý phân công khoa</div>
                </div>
            </div>
            <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Trang chủ</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- TAB PHỤ -->
        <div class="page-tabs">
            <a href="open_course_classes.php" class="page-tab <?= $activeTab === 'assign' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i> Phân Công Giảng Dạy
            </a>
            <a href="open_course_classes.php?tab=assigned" class="page-tab <?= $activeTab === 'assigned' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check"></i> Danh Sách Đã Phân Công
                <span class="tab-count"><?= $tongDaPhanCong ?></span>
            </a>
        </div>

        <?php if ($activeTab === 'assigned'): ?>
            <!-- ================= TAB: DANH SÁCH ĐÃ PHÂN CÔNG ================= -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= $tongDaPhanCong ?></div>
                        <div class="stat-label">Tổng Lượt Phân Công</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #059669;">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= $tongLopDaPhanCong ?></div>
                        <div class="stat-label">Lớp Đã Phân Công</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= $tongGVDaPhanCong ?></div>
                        <div class="stat-label">Giảng Viên Tham Gia</div>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-clipboard-check"></i> Danh Sách Đã Phân Công Thành Công
                    </div>

                    <?php if ($tongDaPhanCong > 0): ?>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="assignedSearch" onkeyup="filterAssignedTable()" placeholder="Lọc nhanh lớp, môn, GV...">
                            </div>
                            <button type="button" onclick="exportTableToExcel('daPhanCongTable', 'Danh_Sach_Da_Phan_Cong')" class="btn-excel">
                                <i class="fa-solid fa-file-excel"></i> Xuất Excel
                            </button>
                            <button type="button" onclick="exportTableToPDF('daPhanCongTable', 'Danh_Sach_Da_Phan_Cong')" class="btn-pdf">
                                <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($tongDaPhanCong === 0): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                        <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>Chưa có phân công giảng dạy nào được lưu.</p>
                    </div>
                <?php else: ?>
                    <datalist id="listGiangVienAssigned">
                        <?php foreach ($dsGiangVien as $gvItem): ?>
                            <option value="<?= htmlspecialchars($gvItem) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="table-container">
                        <table id="daPhanCongTable">
                            <thead>
                                <tr>
                                    <th style="width: 50px; text-align: center;">STT</th>
                                    <th style="width: 130px;">Mã Lớp</th>
                                    <th style="width: 220px;">Tên Lớp Chuyên Ngành</th>
                                    <th style="width: 180px;">Ngành Học</th>
                                    <th style="text-align: center; width: 70px;">Khóa</th>
                                    <th style="text-align: center; width: 70px;">Kỳ</th>
                                    <th style="width: 100px;">Mã HP</th>
                                    <th style="width: 220px;">Tên Học Phần</th>
                                    <th style="text-align: center; width: 50px;">TC</th>
                                    <th style="width: 240px;">Lớp Học Phần</th>
                                    <th style="width: 220px;">Giảng Viên Giảng Dạy</th>
                                    <?php if (laAdmin()): ?>
                                    <th style="width: 150px; text-align: center;">Thao Tác</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dsDaPhanCong as $idx => $row):
                                    $khoaRow = layKhoa($row['malop'] ?? '');
                                    $mhRow   = $row['mahp'] ?? '';
                                    $mlRow   = $row['malop'] ?? '';
                                    $lhpRow  = $row['lophocphan'] ?? '';
                                    $gvRow   = $row['giangvien'] ?? '';
                                ?>
                                    <tr>
                                        <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $idx + 1 ?></td>
                                        <td><span class="badge-code"><?= htmlspecialchars($mlRow) ?></span></td>
                                        <td><strong style="color: #1e293b;"><?= htmlspecialchars($row['TenLop'] ?? '') ?></strong></td>
                                        <td style="color: #475569;"><?= htmlspecialchars($row['NganhHoc'] ?? '—') ?></td>
                                        <td style="text-align: center;"><span class="badge-code"><?= htmlspecialchars($khoaRow ?: '—') ?></span></td>
                                        <td style="text-align: center; font-weight: 600; color: #4f46e5;">HK<?= htmlspecialchars((string)($row['HocKy'] ?? '')) ?></td>
                                        <td><span class="badge-code"><?= htmlspecialchars($mhRow) ?></span></td>
                                        <td><?= htmlspecialchars($row['TenHP'] ?? '') ?></td>
                                        <td style="text-align: center; font-weight: 600; color: #4f46e5;"><?= htmlspecialchars((string)($row['SoTC'] ?? '')) ?></td>
                                        <td><span class="badge-lhp"><?= htmlspecialchars($lhpRow) ?></span></td>
                                        <td>
                                            <span class="gv-view" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #059669;">
                                                <i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($gvRow) ?>
                                            </span>
                                            <form method="POST" action="open_course_classes.php?tab=assigned" class="gv-edit" style="display: none;">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_phancong">
                                                <input type="hidden" name="mahp" value="<?= htmlspecialchars($mhRow) ?>">
                                                <input type="hidden" name="malop" value="<?= htmlspecialchars($mlRow) ?>">
                                                <input type="hidden" name="lophocphan" value="<?= htmlspecialchars($lhpRow) ?>">
                                                <div style="display: flex; gap: 6px; align-items: center;">
                                                    <input type="text" name="giangvien" value="<?= htmlspecialchars($gvRow) ?>"
                                                           list="listGiangVienAssigned" required
                                                           style="flex: 1; min-width: 120px; padding: 7px 9px; border: 1px solid var(--border); border-radius: 7px; font-size: 13px;">
                                                    <button type="submit" class="btn-mini btn-mini-save" title="Lưu"><i class="fa-solid fa-check"></i></button>
                                                    <button type="button" class="btn-mini btn-mini-cancel" onclick="huySua(this)" title="Hủy"><i class="fa-solid fa-xmark"></i></button>
                                                </div>
                                            </form>
                                        </td>
                                        <?php if (laAdmin()): ?>
                                        <td style="text-align: center;">
                                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                                <button type="button" class="btn-mini btn-mini-edit" onclick="batDauSua(this)" title="Sửa giảng viên">
                                                    <i class="fa-solid fa-pen"></i> Sửa
                                                </button>
                                                <form method="POST" action="open_course_classes.php?tab=assigned" style="display: inline;"
                                                      onsubmit="return confirm('Xóa phân công của lớp <?= htmlspecialchars($mlRow, ENT_QUOTES, 'UTF-8') ?> - môn <?= htmlspecialchars($mhRow, ENT_QUOTES, 'UTF-8') ?>?')">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_phancong">
                                                    <input type="hidden" name="mahp" value="<?= htmlspecialchars($mhRow) ?>">
                                                    <input type="hidden" name="malop" value="<?= htmlspecialchars($mlRow) ?>">
                                                    <input type="hidden" name="lophocphan" value="<?= htmlspecialchars($lhpRow) ?>">
                                                    <button type="submit" class="btn-mini btn-mini-delete" title="Xóa phân công">
                                                        <i class="fa-solid fa-trash-can"></i> Xóa
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>

        <!-- BỘ LỌC ĐIỀU KIỆN -->
        <div class="card-box">
            <div class="card-header" style="margin-bottom: 16px;">
                <div class="card-title">
                    <i class="fa-solid fa-filter"></i> Lựa Chọn Điều Kiện Phân Công
                </div>
            </div>
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Khóa học:</label>
                        <select name="khoa" id="khoaSelect" required onchange="capNhatLopSelect()">
                            <option value="">-- Chọn Khóa --</option>
                            <?php foreach ($dsKhoa as $k): ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= ($khoa === $k) ? 'selected' : '' ?>>
                                    Khóa <?= htmlspecialchars($k) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Kỳ học:</label>
                        <select name="kyhoc" required>
                            <option value="">-- Chọn Kỳ học --</option>
                            <?php foreach ($dsKyHoc as $ky): ?>
                                <option value="<?= $ky ?>" <?= ($kyhoc == $ky) ? 'selected' : '' ?>>
                                    Học kỳ <?= $ky ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ngành học:</label>
                        <select name="nganh" id="nganhSelect" required onchange="capNhatLopSelect()">
                            <option value="">-- Chọn Ngành --</option>
                            <?php foreach ($dsNganh as $n): ?>
                                <option value="<?= htmlspecialchars($n) ?>" <?= ($nganh === $n) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($n) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Lớp:</label>
                        <select name="lop" id="lopSelect" required <?= ($khoa === '' || $nganh === '') ? 'disabled' : '' ?>>
                            <option value="">-- Chọn Lớp --</option>
                            <?php
                            $lopsTheoBoLoc = ($khoa !== '' && $nganh !== '' && isset($lopTheoNganhKhoa[$nganh][$khoa]))
                                ? $lopTheoNganhKhoa[$nganh][$khoa] : [];
                            foreach ($lopsTheoBoLoc as $l):
                            ?>
                                <option value="<?= htmlspecialchars($l['ma']) ?>" <?= ($lop === $l['ma']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['ma']) ?> - <?= htmlspecialchars($l['ten']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-magnifying-glass"></i> Hiển thị dữ liệu
                    </button>
                </div>
            </form>
        </div>

        <!-- BẢNG PHÂN CÔNG (CHỈ XUẤT HIỆN KHI BẤM HIỂN THỊ) -->
        <?php if ($daHienThi): ?>
            
            <!-- STATS CARDS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= $stat_tong_lhp ?></div>
                        <div class="stat-label">Tổng Lớp Học Phần</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #059669;">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= $stat_tong_tc ?></div>
                        <div class="stat-label">Tổng Số Tín Chỉ</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">
                        <i class="fa-solid fa-laptop-code"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?= $stat_mon_th ?></div>
                        <div class="stat-label">Lớp Thực Hành (Tách 40)</div>
                    </div>
                </div>
            </div>

            <div class="card-box">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-list-check"></i> Danh Sách Lớp Học Phần Cần Phân Công
                    </div>

                    <?php if (!empty($dsDong)): ?>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <!-- Thanh tìm kiếm nhanh -->
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="tableSearch" onkeyup="filterPhanCongTable()" placeholder="Lọc nhanh môn, mã, GV...">
                            </div>

                            <!-- Nhập từ file Excel -->
                            <button type="button" class="btn-import" onclick="document.getElementById('excelFileInput').click()">
                                <i class="fa-solid fa-file-import"></i> Nhập Excel
                            </button>
                            <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv" style="display: none;" onchange="importExcel(this)">

                            <!-- Xuất Excel & PDF -->
                            <button type="button" onclick="exportTableToExcel('phanCongTable', 'Phan_Cong_Giang_Day_<?= htmlspecialchars($khoa) ?>_Ky<?= $kyhoc ?>')" class="btn-excel">
                                <i class="fa-solid fa-file-excel"></i> Xuất Excel
                            </button>

                            <button type="button" onclick="exportTableToPDF('phanCongTable', 'Phan_Cong_Giang_Day_<?= htmlspecialchars($khoa) ?>_Ky<?= $kyhoc ?>')" class="btn-pdf">
                                <i class="fa-solid fa-file-pdf"></i> Xuất PDF
                            </button>

                            <!-- Nút xóa sạch phân công của bộ lọc này (chỉ admin) -->
                            <?php if (laAdmin()): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ phân công của lớp <?= htmlspecialchars($lop, ENT_QUOTES, 'UTF-8') ?> không?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="clear_all">
                                <input type="hidden" name="khoa" value="<?= htmlspecialchars($khoa, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="kyhoc" value="<?= htmlspecialchars($kyhoc, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="nganh" value="<?= htmlspecialchars($nganh, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="lop" value="<?= htmlspecialchars($lop, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="btn-clear-all" title="Xóa tất cả phân công của lớp này">
                                    <i class="fa-solid fa-trash-can"></i> Xóa Tải
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Thông tin bộ lọc + giới hạn 10 môn/kỳ -->
                <div style="background: rgba(99,102,241,0.08); border:1px solid rgba(99,102,241,0.25); color:#4f46e5; padding:12px 16px; border-radius:10px; font-size:13.5px; font-weight:600; margin-bottom:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Lớp <strong><?= htmlspecialchars($lop) ?></strong> — Kỳ <?= htmlspecialchars($kyhoc) ?>: hiển thị <strong><?= $soMonHienThi ?></strong>/<?= $tongsMonTrongKy ?> môn học.</span>
                    <?php if ($biCatBotMon): ?>
                        <span style="background: rgba(245,158,11,0.15); color:#d97706; border:1px solid rgba(245,158,11,0.4); padding:3px 10px; border-radius:6px; font-size:12px;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Chỉ hiển thị tối đa <?= $gioiHanMonMoiKy ?> môn/kỳ
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($dsDong)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                        <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>Không tìm thấy dữ liệu lớp học phần phù hợp với bộ lọc đã chọn.</p>
                    </div>
                <?php else: ?>
                    <!-- Datalist gợi ý giảng viên -->
                    <datalist id="listGiangVien">
                        <?php foreach ($dsGiangVien as $gvItem): ?>
                            <option value="<?= htmlspecialchars($gvItem) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="save_phancong">
                        
                        <div class="table-container">
                            <table id="phanCongTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px; text-align: center;">STT</th>
                                        <th style="width: 130px;">Mã Lớp</th>
                                        <th style="width: 220px;">Tên Lớp Chuyên Ngành</th>
                                        <th style="text-align: center; width: 70px;">Sĩ Số</th>
                                        <th style="width: 100px;">Mã HP</th>
                                        <th style="width: 220px;">Tên Học Phần</th>
                                        <th style="text-align: center; width: 50px;">TC</th>
                                        <th style="width: 280px;">Lớp Học Phần Tách</th>
                                        <th style="width: 250px;">Giảng Viên Giảng Dạy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dsDong as $idx => $row): 
                                        $hasGV = !empty(trim($row['giangvien']));
                                    ?>
                                        <tr class="row-lhp <?= !$hasGV ? 'row-empty-gv' : '' ?>">
                                            <td style="text-align: center; color: var(--text-muted); font-weight: 600;"><?= $idx + 1 ?></td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($row['malop']) ?></span>
                                                <input type="hidden" name="malop[]" value="<?= htmlspecialchars($row['malop']) ?>">
                                            </td>
                                            <td><strong style="color: #1e293b;"><?= htmlspecialchars($row['tenlop']) ?></strong></td>
                                            <td style="text-align: center; font-weight: 700; color: #0284c7;">
                                                <?= $row['siso'] ?>
                                            </td>
                                            <td>
                                                <span class="badge-code"><?= htmlspecialchars($row['mahp']) ?></span>
                                                <input type="hidden" name="mahp[]" value="<?= htmlspecialchars($row['mahp']) ?>">
                                            </td>
                                            <td>
                                                <div class="hp-title-box">
                                                    <span><?= htmlspecialchars($row['tenhp']) ?></span>
                                                    <?php if ($row['is_th']): ?>
                                                        <span class="badge-th">THỰC HÀNH</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="text-align: center; font-weight: 600; color: #4f46e5;"><?= $row['sotinchi'] ?></td>
                                            <td>
                                                <span class="badge-lhp"><?= htmlspecialchars($row['lophocphan']) ?></span>
                                                <input type="hidden" name="lophocphan[]" value="<?= htmlspecialchars($row['lophocphan']) ?>">
                                            </td>
                                            <td>
                                                <div class="gv-input-wrapper">
                                                    <input type="text" name="giangvien[]" 
                                                           list="listGiangVien"
                                                           value="<?= htmlspecialchars($row['giangvien']) ?>" 
                                                           placeholder="Chọn hoặc nhập tên GV..."
                                                           onchange="checkInputState(this)">
                                                    <?php if ($hasGV): ?>
                                                        <button type="button" class="btn-clear" onclick="clearGvInput(this)" title="Xóa tên GV"><i class="fa-solid fa-xmark"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; flex-wrap: wrap; gap: 16px;">
                            <div style="font-size: 13px; color: var(--text-muted);">
                                <i class="fa-solid fa-circle-info" style="color: var(--primary);"></i> Nhấn <strong style="color: #1e293b;">Lưu Tất Cả Phân Công</strong> để cập nhật CSDL.
                            </div>
                            <button type="submit" class="btn-save">
                                <i class="fa-solid fa-floppy-disk"></i> Lưu Tất Cả Phân Công
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php endif; // đóng nhánh tab "assign" ?>
    </div>

    <!-- Form ẩn gửi dữ liệu JSON Import lên Server -->
    <form id="importExcelForm" method="POST" style="display: none;">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="import_excel">
        <input type="hidden" name="excel_data" id="excelDataInput">
    </form>

    <script>
        const LOP_THEO_NGANH_KHOA = <?= json_encode($lopTheoNganhKhoa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const SELECTED_LOP = <?= json_encode($lop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Cập nhật danh sách lớp theo Ngành + Khóa; chỉ cho chọn lớp khi đã chọn Khóa & Ngành
        function capNhatLopSelect() {
            const khoaSel = document.getElementById('khoaSelect');
            const nganhSel = document.getElementById('nganhSelect');
            const lopSel = document.getElementById('lopSelect');
            if (!lopSel || !khoaSel || !nganhSel) return;

            const khoa = khoaSel.value;
            const nganh = nganhSel.value;
            const current = lopSel.value;

            lopSel.innerHTML = '<option value="">-- Chọn Lớp --</option>';
            const ds = (khoa && nganh && LOP_THEO_NGANH_KHOA[nganh] && LOP_THEO_NGANH_KHOA[nganh][khoa])
                ? LOP_THEO_NGANH_KHOA[nganh][khoa] : [];

            if (ds.length === 0) {
                lopSel.disabled = true;
                return;
            }
            ds.forEach(function (l) {
                const opt = document.createElement('option');
                opt.value = l.ma;
                opt.textContent = l.ma + ' - ' + l.ten;
                lopSel.appendChild(opt);
            });
            lopSel.disabled = false;
            if (current) lopSel.value = current;
        }

        document.addEventListener('DOMContentLoaded', function () {
            capNhatLopSelect();
            const lopSel = document.getElementById('lopSelect');
            if (lopSel && SELECTED_LOP) lopSel.value = SELECTED_LOP;
        });

        function filterPhanCongTable() {
            const filter = document.getElementById("tableSearch").value.toLowerCase();
            const rows = document.querySelectorAll("#phanCongTable tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        function filterAssignedTable() {
            const box = document.getElementById("assignedSearch");
            if (!box) return;
            const filter = box.value.toLowerCase();
            const rows = document.querySelectorAll("#daPhanCongTable tbody tr");
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }

        // Bật chế độ sửa giảng viên cho 1 dòng
        function batDauSua(btn) {
            const row = btn.closest('tr');
            if (!row) return;
            const view = row.querySelector('.gv-view');
            const edit = row.querySelector('.gv-edit');
            if (view) view.style.display = 'none';
            if (edit) {
                edit.style.display = 'block';
                const inp = edit.querySelector('input[name="giangvien"]');
                if (inp) { inp.focus(); inp.select(); }
            }
        }

        // Hủy sửa
        function huySua(btn) {
            const row = btn.closest('tr');
            if (!row) return;
            const view = row.querySelector('.gv-view');
            const edit = row.querySelector('.gv-edit');
            if (edit) edit.style.display = 'none';
            if (view) view.style.display = 'inline-flex';
        }

        function clearGvInput(btn) {
            const input = btn.parentNode.querySelector('input');
            input.value = '';
            btn.remove();
            input.closest('tr').classList.add('row-empty-gv');
        }

        function checkInputState(input) {
            const row = input.closest('tr');
            if (input.value.trim() === '') {
                row.classList.add('row-empty-gv');
            } else {
                row.classList.remove('row-empty-gv');
            }
        }

        // Nhập phân công từ file Excel
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

                    if (confirm(`Tìm thấy ${jsonData.length} dòng phân công trong file. Bạn có chắc chắn muốn nhập vào hệ thống?`)) {
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

        function exportTableToExcel(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var clonedTable = table.cloneNode(true);
            
            var inputs = clonedTable.querySelectorAll('input[name="giangvien[]"]');
            inputs.forEach(input => {
                var val = input.value;
                var parentNode = input.closest('td');
                parentNode.textContent = val;
            });

            clonedTable.querySelectorAll('button').forEach(b => b.remove());

            var wb = XLSX.utils.table_to_book(clonedTable, {sheet: "PhanCongGiangDay"});
            filename = filename ? filename + '.xlsx' : 'Phan_Cong_Giang_Day.xlsx';
            XLSX.writeFile(wb, filename);
        }

        function exportTableToPDF(tableID, filename = '') {
            var table = document.getElementById(tableID);
            if (!table) return;

            var wrapper = document.createElement('div');
            wrapper.style.padding = '20px';
            wrapper.style.color = '#000';
            wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

            var title = document.createElement('h2');
            title.innerText = 'DANH SÁCH PHÂN CÔNG GIẢNG DẠY';
            title.style.textAlign = 'center';
            title.style.marginBottom = '10px';
            wrapper.appendChild(title);

            var info = document.createElement('p');
            info.innerHTML = '<strong>Khóa:</strong> <?= htmlspecialchars($khoa) ?> | <strong>Học kỳ:</strong> <?= htmlspecialchars($kyhoc) ?> \vert{} <strong>Ngành:</strong> <?= htmlspecialchars($nganh) ?>';
            info.style.textAlign = 'center';
            info.style.marginBottom = '20px';
            info.style.fontSize = '13px';
            wrapper.appendChild(info);

            var clonedTable = table.cloneNode(true);

            var inputs = clonedTable.querySelectorAll('input[name="giangvien[]"]');
            inputs.forEach(input => {
                var val = input.value;
                var parentNode = input.closest('td');
                parentNode.textContent = val ? val : 'Chưa phân công';
            });

            clonedTable.querySelectorAll('button').forEach(b => b.remove());

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

            filename = filename ? filename + '.pdf' : 'Phan_Cong_Giang_Day.pdf';

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


