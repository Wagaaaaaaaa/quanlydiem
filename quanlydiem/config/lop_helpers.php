<?php
/**
 * Hàm dùng chung cho quản lý lớp học & phân bổ sĩ số.
 * Tách riêng để có thể tái sử dụng ở nhiều trang (classes.php, students.php...).
 */

if (!defined('GIOI_HAN_LOP_MAC_DINH')) {
    define('GIOI_HAN_LOP_MAC_DINH', 50);
}

// Tăng số cuối cùng trong chuỗi (VD: "DHCNKTMK22A2" -> "DHCNKTMK22A3")
if (!function_exists('tangSoCuoi')) {
    function tangSoCuoi($s) {
        return preg_replace_callback('/(\d+)(?!.*\d)/', function ($m) {
            return (string)((int)$m[1] + 1);
        }, $s);
    }
}

// Giới hạn sĩ số của 1 lớp (NULL / 0 => dùng mặc định)
if (!function_exists('gioiHanCuaLop')) {
    function gioiHanCuaLop($lop) {
        $gh = isset($lop['GioiHanSinhVien']) ? (int)$lop['GioiHanSinhVien'] : 0;
        return $gh > 0 ? $gh : GIOI_HAN_LOP_MAC_DINH;
    }
}

// Đếm số sinh viên thật đang thuộc 1 lớp
if (!function_exists('demSinhVienTrongLop')) {
    function demSinhVienTrongLop(PDO $pdo, $maLop) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM sinhvien WHERE MaLop = ?");
        $st->execute([$maLop]);
        return (int)$st->fetchColumn();
    }
}

// Đồng bộ cột SiSo = số sinh viên thật hiện có
if (!function_exists('dongBoSiSoLop')) {
    function dongBoSiSoLop(PDO $pdo, $maLop) {
        $n = demSinhVienTrongLop($pdo, $maLop);
        $pdo->prepare("UPDATE lop SET SiSo = ? WHERE MaLop = ?")->execute([$n, $maLop]);
        return $n;
    }
}

// Suy ra KHÓA của lớp: ưu tiên cột KhoaHoc, nếu trống thì lấy từ mã lớp (VD: DHCTTCK20A1 -> K20)
if (!function_exists('khoaCuaLop')) {
    function khoaCuaLop($lop) {
        $khoa = (string)($lop['KhoaHoc'] ?? '');
        if ($khoa === '' && preg_match('/K(\d{2})/i', (string)($lop['MaLop'] ?? ''), $m)) {
            $khoa = 'K' . $m[1];
        }
        return $khoa;
    }
}

// Liệt kê các lớp CÙNG NGÀNH còn chỗ trống (trả kèm số chỗ trống)
if (!function_exists('lopCungNganhConCho')) {
    function lopCungNganhConCho(PDO $pdo, $nganh, $maLopTru = '') {
        $st = $pdo->prepare("SELECT * FROM lop WHERE NganhHoc <=> ? AND MaLop <> ?");
        $st->execute([$nganh, $maLopTru]);
        $ketQua = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $gh  = gioiHanCuaLop($l);
            $sl  = demSinhVienTrongLop($pdo, $l['MaLop']);
            $con = $gh - $sl;
            if ($con <= 0) {
                continue;
            }
            $ketQua[] = ['ma' => $l['MaLop'], 'khoa' => khoaCuaLop($l), 'conTrong' => $con];
        }
        return $ketQua;
    }
}

// Tạo lớp kế tiếp cùng ngành/khóa dựa trên 1 lớp mẫu. Trả về mã lớp mới.
if (!function_exists('taoLopKeTiep')) {
    function taoLopKeTiep(PDO $pdo, $lopMau, $gioiHan = null) {
        $maMoi = preg_match('/A\d+$/i', $lopMau['MaLop'])
            ? preg_replace_callback('/A(\d+)$/i', function ($m) { return 'A' . ((int)$m[1] + 1); }, $lopMau['MaLop'])
            : tangSoCuoi($lopMau['MaLop']);
        $tenMoi = preg_match('/A\s*\d+\s*$/u', $lopMau['TenLop'])
            ? preg_replace_callback('/A\s*(\d+)\s*$/u', function ($m) { return 'A' . ((int)$m[1] + 1); }, $lopMau['TenLop'])
            : tangSoCuoi($lopMau['TenLop']);

        $qChk = $pdo->prepare("SELECT COUNT(*) FROM lop WHERE MaLop = ?");
        $guard = 0;
        while ($guard++ < 1000) {
            $qChk->execute([$maMoi]);
            if ((int)$qChk->fetchColumn() === 0) { break; }
            $maMoi = tangSoCuoi($maMoi);
        }

        $ghInsert = ((int)$gioiHan > 0) ? (int)$gioiHan : null;
        $pdo->prepare("INSERT INTO lop (MaLop, TenLop, SiSo, GioiHanSinhVien, NganhHoc, KhoaHoc) VALUES (?, ?, 0, ?, ?, ?)")
            ->execute([$maMoi, $tenMoi, $ghInsert, $lopMau['NganhHoc'], $lopMau['KhoaHoc'] ?? null]);
        return $maMoi;
    }
}

/**
 * Khi 1 lớp vượt giới hạn sĩ số mới, tự động chuyển bớt sinh viên thừa sang:
 *   1) các lớp CÙNG NGÀNH còn chỗ (ưu tiên cùng khóa),
 *   2) nếu hết chỗ thì tạo lớp mới cùng ngành/khóa.
 *
 * @param PDO    $pdo
 * @param string $maLopNguon  Mã lớp bị vượt giới hạn
 * @param int    $gioiHanMoi  Giới hạn sĩ số mới của lớp nguồn
 * @return array{chuyen:int, lop_moi:array<int,string>, chi_tiet:array<string,int>}
 */
if (!function_exists('phanBoVuotGioiHan')) {
    function phanBoVuotGioiHan(PDO $pdo, $maLopNguon, $gioiHanMoi) {
        $st = $pdo->prepare("SELECT * FROM lop WHERE MaLop = ?");
        $st->execute([$maLopNguon]);
        $lopNguon = $st->fetch(PDO::FETCH_ASSOC);
        if (!$lopNguon) {
            return ['chuyen' => 0, 'lop_moi' => [], 'chi_tiet' => []];
        }

        $vuot = demSinhVienTrongLop($pdo, $maLopNguon) - (int)$gioiHanMoi;
        if ($vuot <= 0) {
            return ['chuyen' => 0, 'lop_moi' => [], 'chi_tiet' => []];
        }

        // Chuyển những sinh viên được thêm sau (mã lớn) trước
        $svSt = $pdo->prepare("SELECT MaSV FROM sinhvien WHERE MaLop = ? ORDER BY MaSV DESC LIMIT " . (int)$vuot);
        $svSt->execute([$maLopNguon]);
        $dsSV  = $svSt->fetchAll(PDO::FETCH_COLUMN);
        $tong  = count($dsSV);

        $nganh = (string)($lopNguon['NganhHoc'] ?? '');
        $khoa  = khoaCuaLop($lopNguon);

        $chuyen   = 0;
        $lopMoi   = [];
        $chiTiet  = []; // MaLop nhận => số SV nhận
        $viTri    = 0;
        $guard    = 0;

        while ($viTri < $tong && $guard++ < 500) {
            $conLai = $tong - $viTri;

            // Tìm lớp cùng ngành còn chỗ; ưu tiên cùng khóa, rồi nhiều chỗ nhất
            $ungVien = lopCungNganhConCho($pdo, $nganh, $maLopNguon);
            usort($ungVien, function ($a, $b) use ($khoa) {
                $ka = ($a['khoa'] === $khoa) ? 0 : 1;
                $kb = ($b['khoa'] === $khoa) ? 0 : 1;
                if ($ka !== $kb) { return $ka <=> $kb; }
                return $b['conTrong'] <=> $a['conTrong'];
            });

            if (empty($ungVien)) {
                // Hết chỗ -> tạo lớp mới cùng ngành/khóa, dùng đúng giới hạn mới
                $maMoi = taoLopKeTiep($pdo, $lopNguon, (int)$gioiHanMoi);
                $lopMoi[] = $maMoi;
                $ungVien = [['ma' => $maMoi, 'khoa' => $khoa, 'conTrong' => max(1, (int)$gioiHanMoi)]];
            }

            $dich  = $ungVien[0];
            $nhan  = min($conLai, (int)$dich['conTrong']);
            if ($nhan <= 0) { break; }

            $chunk = array_slice($dsSV, $viTri, $nhan);
            $in    = implode(',', array_fill(0, count($chunk), '?'));
            $pdo->prepare("UPDATE sinhvien SET MaLop = ? WHERE MaSV IN ($in)")
                ->execute(array_merge([$dich['ma']], $chunk));

            $viTri  += $nhan;
            $chuyen += $nhan;
            $chiTiet[$dich['ma']] = ($chiTiet[$dich['ma']] ?? 0) + $nhan;
        }

        // Đồng bộ lại Sĩ Số thật cho lớp nguồn và các lớp nhận
        dongBoSiSoLop($pdo, $maLopNguon);
        foreach (array_keys($chiTiet) as $ma) {
            dongBoSiSoLop($pdo, $ma);
        }

        return ['chuyen' => $chuyen, 'lop_moi' => $lopMoi, 'chi_tiet' => $chiTiet];
    }
}
