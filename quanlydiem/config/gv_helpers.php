<?php
/**
 * Tự động cấp tài khoản giảng viên khi phân công giảng dạy.
 * Tên đăng nhập: gv + số tăng dần (gv1, gv2, ...); mật khẩu mặc định: 123456.
 * Tên thật của giảng viên được lưu ở cột users.TenGV để khớp với phanlop.giangvien.
 */

if (!defined('GV_MAT_KHAU_MAC_DINH')) {
    define('GV_MAT_KHAU_MAC_DINH', '123456');
}

// Bỏ dấu tiếng Việt -> ASCII (giống logic trong component_grade.php)
if (!function_exists('gvChuanHoaTen')) {
    function gvChuanHoaTen($s) {
        $s = trim((string)$s);
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
}

// Sinh các biến thể khóa tên để so khớp (VD: "ThS. Lê Thị Lan" -> lethilan, ltlan)
if (!function_exists('gvBienTheTen')) {
    function gvBienTheTen($s) {
        $s = trim((string)$s);
        if ($s === '') return [];
        $s = preg_replace('/\b(?:ThS|TS|PGS|GS|CN|KS|Th\.S|PGS\.TS)\b\.?/i', ' ', $s);
        $words = preg_split('/[\s\.\-\/]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $plain = [];
        foreach ((array)$words as $w) {
            $w = gvChuanHoaTen($w);
            if ($w !== '') $plain[] = $w;
        }
        $variants = [];
        if (!empty($plain)) {
            $variants[] = implode('', $plain);
            if (count($plain) > 1) {
                $last = array_pop($plain);
                $init = '';
                foreach ($plain as $w) { $init .= $w[0]; }
                $variants[] = $init . $last;
            }
        }
        $out = [];
        foreach ($variants as $v) {
            $out[] = $v;
            $out[] = preg_replace('/^gv/', '', $v);
        }
        return array_values(array_unique(array_filter($out)));
    }
}

// Giảng viên mang tên $tenGV đã có tài khoản chưa? (so khớp theo biến thể tên)
if (!function_exists('gvCoKhopTenGV')) {
    function gvCoKhopTenGV(PDO $pdo, $tenGV) {
        $tenGV = trim((string)$tenGV);
        if ($tenGV === '') return false;
        $variants = gvBienTheTen($tenGV);
        if (empty($variants)) return false;

        $danhSach = $pdo->query("SELECT ten, TenGV FROM users WHERE quyen = 'giangvien'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($danhSach as $u) {
            $tenAcc   = trim((string)($u['ten'] ?? ''));
            $tenGVAcc = trim((string)($u['TenGV'] ?? ''));
            if (strcasecmp($tenAcc, $tenGV) === 0) return true;
            if ($tenGVAcc !== '' && strcasecmp($tenGVAcc, $tenGV) === 0) return true;
            if (array_intersect($variants, gvBienTheTen($tenAcc))) return true;
            if ($tenGVAcc !== '' && array_intersect($variants, gvBienTheTen($tenGVAcc))) return true;
        }
        return false;
    }
}

// Mã tài khoản giảng viên kế tiếp: gv1, gv2, ...
if (!function_exists('gvMaTiepTheo')) {
    function gvMaTiepTheo(PDO $pdo) {
        $nums = [];
        $st = $pdo->query("SELECT ten FROM users WHERE ten REGEXP '^gv[0-9]+$'");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $nums[] = (int)substr($t, 2);
        }
        $next = (empty($nums) ? 0 : max($nums)) + 1;
        while (true) {
            $ten = 'gv' . $next;
            $st2 = $pdo->prepare("SELECT COUNT(*) FROM users WHERE ten = ?");
            $st2->execute([$ten]);
            if ((int)$st2->fetchColumn() === 0) return $ten;
            $next++;
        }
    }
}

// Cấp tài khoản cho 1 giảng viên nếu chưa có. Trả về mảng thông tin hoặc null nếu tên rỗng.
// Kết quả: ['created'=>true, 'ten'=>..., 'matkhau'=>...]  hoặc  ['created'=>false]
if (!function_exists('gvTaoTaiKhoan')) {
    function gvTaoTaiKhoan(PDO $pdo, $tenGV) {
        $tenGV = trim((string)$tenGV);
        if ($tenGV === '') return null;
        if (gvCoKhopTenGV($pdo, $tenGV)) {
            return ['created' => false];
        }
        $username = gvMaTiepTheo($pdo);
        $hash = password_hash(GV_MAT_KHAU_MAC_DINH, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (ten, TenGV, matkhau, quyen) VALUES (?, ?, ?, 'giangvien')")
            ->execute([$username, $tenGV, $hash]);
        return ['created' => true, 'ten' => $username, 'matkhau' => GV_MAT_KHAU_MAC_DINH];
    }
}

// Cấp tài khoản cho nhiều giảng viên cùng lúc. Trả về tóm tắt:
// ['tao_moi' => [ ['ten'=>..., 'matkhau'=>...], ... ], 'da_co' => int]
if (!function_exists('gvCapTaiKhoanHangLoat')) {
    function gvCapTaiKhoanHangLoat(PDO $pdo, array $dsTen) {
        $taoMoi = [];
        $daCo   = 0;
        foreach (array_unique(array_filter(array_map('trim', $dsTen))) as $ten) {
            $kq = gvTaoTaiKhoan($pdo, $ten);
            if ($kq === null) continue;
            if (!empty($kq['created'])) {
                $taoMoi[] = $kq;
            } else {
                $daCo++;
            }
        }
        return ['tao_moi' => $taoMoi, 'da_co' => $daCo];
    }
}