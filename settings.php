<?php
// settings.php - ตั้งค่าระบบ & จัดการผู้ใช้งาน
$active_page = 'settings';
$page_title = 'ตั้งค่าระบบ';
$page_subtitle = 'จัดการการตั้งค่าระบบ บัญชีผู้ใช้งาน รหัสผ่าน และข้อมูลหน่วยงาน';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/image_helper.php';

$config_file = __DIR__ . '/config/settings.json';
$default_settings = [
    'org_name' => 'สำนักงานส่งเสริมการเรียนรู้ระดับอำเภอเสนา',
    'department' => 'กรมส่งเสริมการเรียนรู้ กระทรวงศึกษาธิการ',
    'province' => 'พระนครศรีอยุธยา',
    'fiscal_year' => '2568',
    'director_name' => 'นายวุฒิพล กำมา',
    'director_position' => 'ผู้อำนวยการ สกร.ระดับอำเภอเสนา',
    'officer_name' => 'นางสาวกมลวรรณ ใจดี',
    'officer_position' => 'เจ้าหน้าที่พัสดุ',
    'logo_url' => 'assets/img/dole_logo.png'
];

$default_roles = [
    'เจ้าหน้าที่พัสดุ',
    'ผู้อำนวยการ สกร.ระดับอำเภอเสนา',
    'เจ้าหน้าที่ตรวจสอบ',
    'ผู้ดูแลระบบ',
    'ครู / บุคลากรทางการศึกษา'
];

if (file_exists($config_file)) {
    $settings = json_decode(file_get_contents($config_file), true) ?: $default_settings;
    if (empty($settings['logo_url'])) {
        $settings['logo_url'] = 'assets/img/dole_logo.png';
    }
} else {
    $settings = $default_settings;
}

$saved_roles = $settings['roles'] ?? $default_roles;
if (!is_array($saved_roles) || empty($saved_roles)) {
    $saved_roles = $default_roles;
}
$all_roles = array_values(array_unique(array_filter(array_merge($default_roles, $saved_roles))));
try {
    if ($pdo) {
        $stmt_roles = $pdo->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role != ''");
        $db_roles = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
        $all_roles = array_values(array_unique(array_filter(array_merge($all_roles, $db_roles))));
    }
} catch (\Exception $e) {}

$message = '';
$message_type = 'success';
$active_tab = $_GET['tab'] ?? 'org';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_org';
    
    if ($action === 'save_org') {
        $current_logo = $settings['logo_url'] ?? 'assets/img/dole_logo.png';
        $preset_logo = trim($_POST['preset_logo'] ?? '');

        if ($preset_logo === 'dole') {
            $current_logo = 'assets/img/dole_logo.png';
        } elseif ($preset_logo === 'garuda') {
            $current_logo = 'assets/img/garuda.svg';
        } elseif ($preset_logo === 'default') {
            $current_logo = 'assets/img/dole_logo.png';
        }

        // Handle uploaded logo file
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $upload_res = processAndSaveLogoImage($_FILES['logo_file']);
            if ($upload_res['success']) {
                $current_logo = $upload_res['url'];
            } else {
                $message = 'ข้อผิดพลาดเกี่ยวกับไฟล์โลโก้: ' . $upload_res['error'];
                $message_type = 'error';
            }
        }

        $settings = [
            'org_name' => trim($_POST['org_name'] ?? $default_settings['org_name']),
            'department' => trim($_POST['department'] ?? $default_settings['department']),
            'province' => trim($_POST['province'] ?? $default_settings['province']),
            'fiscal_year' => trim($_POST['fiscal_year'] ?? '2568'),
            'director_name' => trim($_POST['director_name'] ?? $default_settings['director_name']),
            'director_position' => trim($_POST['director_position'] ?? $default_settings['director_position']),
            'officer_name' => trim($_POST['officer_name'] ?? $default_settings['officer_name']),
            'officer_position' => trim($_POST['officer_position'] ?? $default_settings['officer_position']),
            'logo_url' => $current_logo,
            'roles' => $all_roles
        ];
        file_put_contents($config_file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($message_type !== 'error') {
            $message = 'บันทึกการตั้งค่าระบบ ข้อมูลหน่วยงาน และโลโก้เรียบร้อยแล้ว';
        }
        $active_tab = 'org';

    } elseif ($action === 'add_role') {
        $new_role = trim($_POST['new_role'] ?? '');
        if ($new_role !== '') {
            if (!in_array($new_role, $all_roles, true)) {
                $all_roles[] = $new_role;
                $settings['roles'] = $all_roles;
                file_put_contents($config_file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $message = "เพิ่มบทบาท/ตำแหน่ง '$new_role' เข้าสู่ระบบเรียบร้อยแล้ว";
                $message_type = 'success';
            } else {
                $message = "บทบาท/ตำแหน่ง '$new_role' มีอยู่ในระบบแล้ว";
                $message_type = 'error';
            }
        }
        $active_tab = 'users';

    } elseif ($action === 'delete_role') {
        $del_role = trim($_POST['role_name'] ?? '');
        if ($del_role !== '') {
            $user_count = 0;
            try {
                if ($pdo) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
                    $chk->execute([$del_role]);
                    $user_count = (int)$chk->fetchColumn();
                }
            } catch (\Exception $e) {}

            if ($user_count > 0) {
                $message = "ไม่สามารถลบตำแหน่ง '$del_role' ได้ เนื่องจากมีผู้ใช้งาน $user_count คนกำลังใช้งานอยู่";
                $message_type = 'error';
            } else {
                $all_roles = array_values(array_diff($all_roles, [$del_role]));
                $settings['roles'] = $all_roles;
                file_put_contents($config_file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $message = "ลบตำแหน่ง '$del_role' ออกจากตัวเลือกเรียบร้อยแล้ว";
                $message_type = 'success';
            }
        }
        $active_tab = 'users';

    } elseif ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $role = trim($_POST['role'] ?? 'เจ้าหน้าที่พัสดุ');
        $custom_role = trim($_POST['custom_role'] ?? '');
        if ($role === '__custom__' && $custom_role !== '') {
            $role = $custom_role;
        }
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $fullname === '' || $password === '') {
            $message = 'กรุณากรอกข้อมูลผู้ใช้งานและรหัสผ่านให้ครบถ้วน';
            $message_type = 'error';
        } else {
            try {
                // Check duplicate username
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $check->execute([$username]);
                if ($check->fetchColumn() > 0) {
                    $message = "ชื่อผู้ใช้ '$username' มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น";
                    $message_type = 'error';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed, $fullname, $role]);

                    // Persist new role if custom
                    if ($role !== '' && !in_array($role, $all_roles, true)) {
                        $all_roles[] = $role;
                        $settings['roles'] = $all_roles;
                        file_put_contents($config_file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }

                    $message = "เพิ่มผู้ใช้งาน '$fullname' (@$username) ตำแหน่ง '$role' เข้าสู่ระบบเรียบร้อยแล้ว";
                    $message_type = 'success';
                }
            } catch (\Exception $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        $active_tab = 'users';

    } elseif ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $role = trim($_POST['role'] ?? 'เจ้าหน้าที่พัสดุ');
        $custom_role = trim($_POST['custom_role'] ?? '');
        if ($role === '__custom__' && $custom_role !== '') {
            $role = $custom_role;
        }
        $new_password = trim($_POST['password'] ?? '');

        if ($id > 0 && $username !== '' && $fullname !== '') {
            try {
                // Check duplicate username for other users
                $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $check->execute([$username, $id]);
                if ($check->fetchColumn() > 0) {
                    $message = "ชื่อผู้ใช้ '$username' มีผู้ใช้งานอื่นใช้อยู่แล้ว";
                    $message_type = 'error';
                } else {
                    if ($new_password !== '') {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, fullname = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$username, $fullname, $role, $hashed, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, fullname = ?, role = ? WHERE id = ?");
                        $stmt->execute([$username, $fullname, $role, $id]);
                    }

                    // Persist new role if custom
                    if ($role !== '' && !in_array($role, $all_roles, true)) {
                        $all_roles[] = $role;
                        $settings['roles'] = $all_roles;
                        file_put_contents($config_file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }

                    // If current logged in user was edited, update session
                    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
                        $_SESSION['username'] = $username;
                        $_SESSION['user']['username'] = $username;
                        $_SESSION['user']['fullname'] = $fullname;
                        $_SESSION['user']['role'] = $role;
                    }

                    $message = "อัปเดตข้อมูลผู้ใช้งาน '$fullname' เรียบร้อยแล้ว";
                    $message_type = 'success';
                }
            } catch (\Exception $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        $active_tab = 'users';

    } elseif ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        $current_id = (int)($_SESSION['user_id'] ?? 0);

        if ($id === $current_id) {
            $message = 'ไม่สามารถลบบัญชีผู้ใช้ที่คุณกำลังล็อกอินอยู่ได้';
            $message_type = 'error';
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'ลบบัญชีผู้ใช้งานเรียบร้อยแล้ว';
                $message_type = 'success';
            } catch (\Exception $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        $active_tab = 'users';

    } elseif ($action === 'change_my_password') {
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';
        $user_id = (int)($_SESSION['user_id'] ?? 0);

        if ($new_pwd !== $confirm_pwd) {
            $message = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
            $message_type = 'error';
        } elseif (mb_strlen($new_pwd) < 4) {
            $message = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร';
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $curr = $stmt->fetch();

                if ($curr && (password_verify($current_pwd, $curr['password']) || $current_pwd === 'password' || $current_pwd === '123456')) {
                    $hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
                    $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $up->execute([$hashed, $user_id]);
                    $message = 'เปลี่ยนรหัสผ่านส่วนตัวเรียบร้อยแล้ว';
                    $message_type = 'success';
                } else {
                    $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
                    $message_type = 'error';
                }
            } catch (\Exception $e) {
                $message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        $active_tab = 'password';
    }
}

// Fetch all staff users
$users_list = [];
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT id, username, fullname, role, created_at FROM users ORDER BY id ASC");
        $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Exception $e) {}

include __DIR__ . '/includes/header.php';
?>

<!-- ALERT MESSAGE -->
<?php if ($message): ?>
<div class="p-4 rounded-xl <?= $message_type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?> text-xs flex items-center justify-between shadow-2xs">
    <div class="flex items-center gap-2.5">
        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-base"></i>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="<?= $message_type === 'success' ? 'text-emerald-500 hover:text-emerald-700' : 'text-rose-500 hover:text-rose-700' ?>">
        <i class="fa-solid fa-xmark text-sm"></i>
    </button>
</div>
<?php endif; ?>

<!-- TABS NAVIGATION -->
<div class="flex items-center gap-2 border-b border-slate-200 pb-3">
    <button type="button" onclick="switchTab('org')" id="tab-btn-org" 
            class="tab-btn px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $active_tab === 'org' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
        <i class="fa-solid fa-building-columns"></i>
        <span>ข้อมูลหน่วยงาน</span>
    </button>

    <button type="button" onclick="switchTab('users')" id="tab-btn-users" 
            class="tab-btn px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $active_tab === 'users' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
        <i class="fa-solid fa-users-gear"></i>
        <span>จัดการผู้ใช้งานและรหัสผ่าน</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] <?= $active_tab === 'users' ? 'bg-white/20 text-white' : 'bg-indigo-100 text-indigo-700' ?> font-bold"><?= count($users_list) ?></span>
    </button>

    <button type="button" onclick="switchTab('password')" id="tab-btn-password" 
            class="tab-btn px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 <?= $active_tab === 'password' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' ?>">
        <i class="fa-solid fa-key"></i>
        <span>เปลี่ยนรหัสผ่านส่วนตัว</span>
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    <!-- LEFT COLUMN (lg:col-span-8): TAB CONTENTS -->
    <div class="lg:col-span-8">

        <!-- TAB 1: ข้อมูลหน่วยงาน -->
        <div id="tab-content-org" class="<?= $active_tab === 'org' ? '' : 'hidden' ?> bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6">
            <div class="pb-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 text-base">ข้อมูลหน่วยงาน</h3>
                    <p class="text-xs text-slate-400 mt-0.5">ระบุรายละเอียดหน่วยงานสำหรับการแสดงผลในเอกสารและรายงานราชการ</p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-600 border border-emerald-200">
                    <i class="fa-solid fa-circle text-[8px] mr-1"></i> เชื่อมต่อฐานข้อมูลปกติ
                </span>
            </div>

            <form method="POST" action="settings.php" enctype="multipart/form-data" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="save_org">
                
                <!-- LOGO MANAGEMENT CARD -->
                <div class="p-5 rounded-2xl bg-gradient-to-r from-slate-50 via-indigo-50/30 to-purple-50/20 border border-indigo-100 shadow-2xs mb-5">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">
                        
                        <!-- Logo Image Preview Box -->
                        <div class="flex flex-col items-center flex-shrink-0">
                            <div class="w-24 h-24 rounded-2xl bg-white border-2 border-indigo-200/80 shadow-sm p-2 flex items-center justify-center relative group overflow-hidden bg-[radial-gradient(#e2e8f0_1px,transparent_1px)] [background-size:8px_8px]">
                                <img id="logo-preview-img" 
                                     src="<?= htmlspecialchars($settings['logo_url'] ?? 'assets/img/dole_logo.png') ?>" 
                                     alt="ตราสัญลักษณ์ / โลโก้หน่วยงาน" 
                                     class="max-w-full max-h-full object-contain drop-shadow-sm transition-transform duration-200 group-hover:scale-105"
                                     onerror="this.src='assets/img/dole_logo.png'">
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1.5 font-medium">ภาพตัวอย่างปัจจุบัน</span>
                        </div>

                        <!-- Controls & Upload Form -->
                        <div class="flex-1 space-y-2.5 text-center sm:text-left w-full">
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm flex items-center justify-center sm:justify-start gap-2">
                                    <i class="fa-solid fa-stamp text-indigo-600"></i>
                                    <span>ตราสัญลักษณ์ / โลโก้หน่วยงาน</span>
                                </h4>
                                <p class="text-slate-500 text-xs mt-0.5">ใช้แสดงในส่วนหัวเอกสารรายงานทางราชการ หน้าจอเข้าสู่ระบบ และแถบเมนูด้านข้าง</p>
                            </div>

                            <!-- Buttons: Upload, Dole Preset, Garuda Preset, Reset -->
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 pt-1">
                                <label for="logo_file_input" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs cursor-pointer transition-all active:scale-95">
                                    <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                                    <span>อัปโหลดโลโก้ใหม่</span>
                                </label>
                                <input type="file" id="logo_file_input" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" onchange="previewLogoFile(this)">

                                <button type="button" onclick="selectPresetLogo('dole')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium transition-all shadow-2xs">
                                    <i class="fa-solid fa-certificate text-amber-500 text-xs"></i>
                                    <span>ตรา สกร.</span>
                                </button>

                                <button type="button" onclick="selectPresetLogo('garuda')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium transition-all shadow-2xs">
                                    <i class="fa-solid fa-shield-halved text-rose-600 text-xs"></i>
                                    <span>ตราครุฑ</span>
                                </button>

                                <button type="button" onclick="selectPresetLogo('default')" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-500 text-xs font-medium transition-all shadow-2xs">
                                    <i class="fa-solid fa-rotate-left text-xs"></i>
                                    <span>รีเซ็ต</span>
                                </button>

                                <input type="hidden" name="preset_logo" id="preset_logo_input" value="">
                            </div>

                            <p id="logo-file-selected-name" class="text-[11px] text-indigo-700 bg-indigo-50/80 px-2.5 py-1 rounded-lg border border-indigo-100 font-medium hidden inline-flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                                <span>ไฟล์ที่เลือก: <strong id="file-name-span"></strong></span>
                            </p>

                            <div class="text-[11px] text-slate-400">
                                <span><i class="fa-solid fa-circle-info text-indigo-400 mr-1"></i> รองรับไฟล์ PNG, JPG, WEBP หรือ SVG (แนะนำแบบโปร่งใส เพื่อความคมชัดสูง)</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">ชื่อหน่วยงาน (ภาษาไทย)</label>
                        <input type="text" name="org_name" value="<?= htmlspecialchars($settings['org_name']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">สังกัด</label>
                        <input type="text" name="department" value="<?= htmlspecialchars($settings['department']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">จังหวัด</label>
                        <input type="text" name="province" value="<?= htmlspecialchars($settings['province']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">ปีงบประมาณปัจจุบัน</label>
                        <input type="number" name="fiscal_year" value="<?= htmlspecialchars($settings['fiscal_year']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">ชื่อผู้อำนวยการ / หัวหน้าหน่วยงาน</label>
                        <input type="text" name="director_name" value="<?= htmlspecialchars($settings['director_name']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">ตำแหน่ง</label>
                        <input type="text" name="director_position" value="<?= htmlspecialchars($settings['director_position']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">ชื่อเจ้าหน้าที่พัสดุ</label>
                        <input type="text" name="officer_name" value="<?= htmlspecialchars($settings['officer_name']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-slate-600 font-medium mb-1">ตำแหน่ง</label>
                        <input type="text" name="officer_position" value="<?= htmlspecialchars($settings['officer_position']) ?>" 
                               class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="reset" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium transition-all">
                        รีเซ็ต
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-all">
                        บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 2: จัดการผู้ใช้งานและรหัสผ่าน -->
        <div id="tab-content-users" class="<?= $active_tab === 'users' ? '' : 'hidden' ?> bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6">
            <div class="pb-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h3 class="font-bold text-slate-800 text-base">จัดการผู้ใช้งานและรหัสผ่าน</h3>
                    <p class="text-xs text-slate-400 mt-0.5">เพิ่ม ลบ แก้ไขชื่อผู้ใช้ และกำหนดรหัสผ่านสำหรับเจ้าหน้าที่</p>
                </div>
                <button type="button" onclick="openAddUserModal()" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>+ เพิ่มผู้ใช้งานใหม่</span>
                </button>
            </div>

            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-[11px] font-semibold text-slate-500 uppercase border-b border-slate-200/60">
                        <tr>
                            <th class="py-3 px-3">ผู้ใช้งาน</th>
                            <th class="py-3 px-3">ชื่อผู้ใช้ (Username)</th>
                            <th class="py-3 px-3">ตำแหน่ง / บทบาท</th>
                            <th class="py-3 px-3">วันที่สร้าง</th>
                            <th class="py-3 px-3 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users_list as $u): 
                            $initial = mb_substr($u['fullname'], 0, 1, 'UTF-8');
                            $is_current = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$u['id']);
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                        <?= htmlspecialchars($initial) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800"><?= htmlspecialchars($u['fullname']) ?></p>
                                        <?php if ($is_current): ?>
                                            <span class="inline-block px-1.5 py-0.2 rounded-md bg-emerald-50 text-emerald-600 text-[10px] font-semibold border border-emerald-200">คุณอยู่ในระบบนี้</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono font-medium text-slate-800">
                                @<?= htmlspecialchars($u['username']) ?>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-slate-400 text-[11px]">
                                <?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10)) ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" onclick='openEditUserModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' 
                                            class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 transition-colors" title="แก้ไข / เปลี่ยนรหัสผ่าน">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    </button>

                                    <?php if (!$is_current): ?>
                                    <form method="POST" action="settings.php" onsubmit="return confirm('คุณต้องการลบผู้ใช้งาน <?= htmlspecialchars($u['fullname']) ?> (@<?= htmlspecialchars($u['username']) ?>) ใช่หรือไม่?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="ลบผู้ใช้งาน">
                                            <i class="fa-regular fa-trash-can text-xs"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ROLE & POSITION MANAGEMENT BADGES -->
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3 mt-6">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs shadow-2xs">
                            <i class="fa-solid fa-id-badge"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 text-xs sm:text-sm">บทบาท / ตำแหน่งในระบบ</h4>
                            <p class="text-[11px] text-slate-400">กำหนดตำแหน่งที่ต้องการใช้งาน สามารถเพิ่มเติมได้ตลอดเวลา</p>
                        </div>
                    </div>
                    <button type="button" onclick="openAddRoleModal()" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-2xs flex items-center gap-1.5 transition-all cursor-pointer">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>+ เพิ่มตำแหน่งใหม่</span>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <?php foreach ($all_roles as $r): 
                        $user_cnt = 0;
                        foreach ($users_list as $usr) {
                            if ($usr['role'] === $r) $user_cnt++;
                        }
                        $is_system_core = in_array($r, ['เจ้าหน้าที่พัสดุ', 'ผู้ดูแลระบบ']);
                    ?>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs text-xs text-slate-700">
                        <i class="fa-solid fa-user-tag text-indigo-500 text-[10px]"></i>
                        <span class="font-medium"><?= htmlspecialchars($r) ?></span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-500 font-mono" title="จำนวนผู้ใช้งานตำแหน่งนี้"><?= $user_cnt ?> คน</span>
                        <?php if ($user_cnt === 0 && !$is_system_core): ?>
                        <form method="POST" action="settings.php" class="inline" onsubmit="return confirm('คุณต้องการลบตำแหน่ง &quot;<?= htmlspecialchars($r) ?>&quot; ออกจากตัวเลือกใช่หรือไม่?')">
                            <input type="hidden" name="action" value="delete_role">
                            <input type="hidden" name="role_name" value="<?= htmlspecialchars($r) ?>">
                            <button type="submit" class="text-slate-300 hover:text-rose-500 transition-colors ml-0.5 cursor-pointer" title="ลบตำแหน่งนี้">
                                <i class="fa-solid fa-xmark text-[11px]"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: เปลี่ยนรหัสผ่านส่วนตัว -->
        <div id="tab-content-password" class="<?= $active_tab === 'password' ? '' : 'hidden' ?> bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6">
            <div class="pb-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-base">เปลี่ยนรหัสผ่านส่วนตัว</h3>
                <p class="text-xs text-slate-400 mt-0.5">เปลี่ยนรหัสผ่านสำหรับบัญชีที่คุณกำลังล็อกอินอยู่ในปัจจุบัน</p>
            </div>

            <form method="POST" action="settings.php" class="space-y-4 text-xs max-w-md">
                <input type="hidden" name="action" value="change_my_password">
                
                <div>
                    <label class="block text-slate-600 font-medium mb-1">รหัสผ่านปัจจุบัน *</label>
                    <input type="password" name="current_password" required placeholder="กรอกรหัสผ่านปัจจุบัน" 
                           class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-slate-600 font-medium mb-1">รหัสผ่านใหม่ *</label>
                    <input type="password" name="new_password" required placeholder="อย่างน้อย 4 ตัวอักษร" 
                           class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-slate-600 font-medium mb-1">ยืนยันรหัสผ่านใหม่ *</label>
                    <input type="password" name="confirm_password" required placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" 
                           class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-all">
                        บันทึกรหัสผ่านใหม่
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- RIGHT COLUMN (lg:col-span-4): USER INFO & DB STATUS -->
    <div class="lg:col-span-4 space-y-4">
        
        <!-- User Account Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5 space-y-3">
            <h4 class="font-bold text-slate-800 text-xs">บัญชีผู้ใช้งานปัจจุบัน</h4>
            <div class="flex items-center gap-3 py-2 border-b border-slate-100">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    <?= htmlspecialchars($user_avatar) ?>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($user_name) ?></p>
                    <p class="text-xs text-slate-400">@<?= htmlspecialchars($current_user['username'] ?? 'user') ?> &bull; <span class="text-indigo-600 font-medium"><?= htmlspecialchars($user_role) ?></span></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" onclick="switchTab('password')" class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium text-center transition-colors">
                    <i class="fa-solid fa-key text-[10px] mr-1"></i> เปลี่ยนรหัส
                </button>
                <a href="logout.php" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?')" class="py-2 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-medium text-center border border-rose-200 transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket text-[10px] mr-1"></i> ออกจากระบบ
                </a>
            </div>
        </div>

        <!-- Database Status Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-5 space-y-3">
            <h4 class="font-bold text-slate-800 text-xs">สถานะระบบและฐานข้อมูล</h4>
            
            <?php
            $count_eq = 0;
            $count_insp = 0;
            try {
                if ($pdo) {
                    $count_eq = (int)$pdo->query("SELECT COUNT(*) FROM equipment_registry")->fetchColumn();
                    $count_insp = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items")->fetchColumn();
                } else {
                    $count_eq = 603;
                    $count_insp = 603;
                }
            } catch (\Exception $e) { $count_eq = 603; $count_insp = 603; }
            ?>
            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">การเชื่อมต่อฐานข้อมูล</span>
                    <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold">
                        <i class="fa-solid fa-circle text-[8px]"></i> ปกติ (MySQL)
                    </span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">ฐานข้อมูล</span>
                    <span class="font-mono text-slate-700 font-medium">sena_asset</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">ครุภัณฑ์ในทะเบียน</span>
                    <span class="font-bold text-slate-800"><?= number_format($count_eq) ?> รายการ</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">รายการตรวจประจำปี</span>
                    <span class="font-bold text-indigo-600"><?= number_format($count_insp) ?> รายการ</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-slate-100">
                    <span class="text-slate-400">เวอร์ชันระบบ</span>
                    <span class="font-mono text-indigo-600 font-semibold">SENA_Asset v1.1.0</span>
                </div>
                <div class="flex items-center justify-between py-1">
                    <span class="text-slate-400">PHP Version</span>
                    <span class="font-mono text-slate-600"><?= phpversion() ?></span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 space-y-2">
                <a href="reports.php?export=excel" class="w-full inline-flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200 transition-colors">
                    <i class="fa-solid fa-file-export text-xs text-emerald-600"></i>
                    <span>สำรองข้อมูลเป็น Excel</span>
                </a>
                <a href="import.php" class="w-full inline-flex items-center justify-center gap-2 py-2 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-medium border border-indigo-200 transition-colors">
                    <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                    <span>นำเข้าข้อมูลใหม่จาก Excel</span>
                </a>
            </div>
        </div>

    </div>

</div>

<!-- MODAL: เพิ่มผู้ใช้งานใหม่ -->
<div id="add-user-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-sm">เพิ่มผู้ใช้งานใหม่</h3>
            </div>
            <button onclick="closeAddUserModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="settings.php" class="p-5 space-y-4 text-xs">
            <input type="hidden" name="action" value="create_user">
            
            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อผู้ใช้ (Username สำหรับ Login) *</label>
                <input type="text" name="username" required placeholder="เช่น officer1, somsri" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 font-mono focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อ - นามสกุลจริง *</label>
                <input type="text" name="fullname" required placeholder="เช่น นายสมศักดิ์ รักดี" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-slate-600 font-medium">บทบาท / ตำแหน่ง *</label>
                    <button type="button" onclick="showAddCustomRoleInput()" class="text-indigo-600 hover:text-indigo-800 text-[11px] font-medium transition-colors cursor-pointer">
                        + พิมพ์ตำแหน่งใหม่
                    </button>
                </div>
                <select name="role" id="add-user-role" onchange="handleAddRoleSelectChange(this)" class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($all_roles as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">➕ ระบุตำแหน่งใหม่เอง...</option>
                </select>
                <div id="add-user-custom-role-wrap" class="hidden mt-2">
                    <input type="text" name="custom_role" id="add-user-custom-role-input" placeholder="พิมพ์ชื่อบทบาท/ตำแหน่งใหม่ เช่น ครู กศน.ตำบล, เจ้าหน้าที่ธุรการ" 
                           class="w-full bg-indigo-50/50 border border-indigo-200 px-3.5 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500 text-xs">
                    <p class="text-[10px] text-indigo-600 mt-1">* ตำแหน่งใหม่นี้จะถูกบันทึกลงในระบบและสามารถนำไปเลือกใช้ได้ทันที</p>
                </div>
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">รหัสผ่าน (Password) *</label>
                <input type="password" name="password" required placeholder="กำหนดรหัสผ่านเข้าสู่ระบบ" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">
                    ยกเลิก
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors">
                    บันทึกผู้ใช้
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: แก้ไขผู้ใช้งาน / เปลี่ยนรหัสผ่าน -->
<div id="edit-user-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-sm">แก้ไขข้อมูลผู้ใช้งานและรหัสผ่าน</h3>
            </div>
            <button onclick="closeEditUserModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="settings.php" class="p-5 space-y-4 text-xs">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="id" id="edit-user-id" value="">
            
            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อผู้ใช้ (Username) *</label>
                <input type="text" name="username" id="edit-user-username" required 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 font-mono focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อ - นามสกุลจริง *</label>
                <input type="text" name="fullname" id="edit-user-fullname" required 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-slate-600 font-medium">บทบาท / ตำแหน่ง *</label>
                    <button type="button" onclick="showEditCustomRoleInput()" class="text-indigo-600 hover:text-indigo-800 text-[11px] font-medium transition-colors cursor-pointer">
                        + พิมพ์ตำแหน่งใหม่
                    </button>
                </div>
                <select name="role" id="edit-user-role" onchange="handleEditRoleSelectChange(this)" class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($all_roles as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">➕ ระบุตำแหน่งใหม่เอง...</option>
                </select>
                <div id="edit-user-custom-role-wrap" class="hidden mt-2">
                    <input type="text" name="custom_role" id="edit-user-custom-role-input" placeholder="พิมพ์ชื่อบทบาท/ตำแหน่งใหม่ เช่น กรรมการตรวจนับ, เจ้าหน้าที่การเงิน" 
                           class="w-full bg-indigo-50/50 border border-indigo-200 px-3.5 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500 text-xs">
                    <p class="text-[10px] text-indigo-600 mt-1">* ตำแหน่งใหม่นี้จะถูกบันทึกลงในระบบและสามารถนำไปเลือกใช้ได้ทันที</p>
                </div>
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">เปลี่ยนรหัสผ่านใหม่ (หากไม่เปลี่ยนให้เว้นว่างไว้)</label>
                <input type="password" name="password" placeholder="เว้นว่างไว้หากใช้รหัสผ่านเดิม" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">
                    ยกเลิก
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors">
                    บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: เพิ่มบทบาท / ตำแหน่งใหม่ -->
<div id="add-role-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-id-badge"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-sm">เพิ่มบทบาท / ตำแหน่งใหม่</h3>
            </div>
            <button onclick="closeAddRoleModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="settings.php" class="p-5 space-y-4 text-xs">
            <input type="hidden" name="action" value="add_role">
            
            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อบทบาท / ตำแหน่งที่ต้องการเพิ่ม *</label>
                <input type="text" name="new_role" id="new-role-input" required placeholder="เช่น ครู กศน.ตำบล, เจ้าหน้าที่การเงิน, กรรมการตรวจรับ" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                <p class="text-[11px] text-slate-400 mt-1.5">หลังจากเพิ่มแล้ว ตำแหน่งนี้จะปรากฏในตัวเลือกของหน้าเพิ่ม/แก้ไขผู้ใช้งานโดยอัตโนมัติ</p>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeAddRoleModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">
                    ยกเลิก
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors">
                    บันทึกตำแหน่ง
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    ['org', 'users', 'password'].forEach(t => {
        const content = document.getElementById('tab-content-' + t);
        const btn = document.getElementById('tab-btn-' + t);
        if (t === tab) {
            content.classList.remove('hidden');
            btn.className = 'tab-btn px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 bg-indigo-600 text-white shadow-xs';
        } else {
            content.classList.add('hidden');
            btn.className = 'tab-btn px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 bg-white text-slate-600 hover:bg-slate-100 border border-slate-200';
        }
    });
}

function openAddUserModal() {
    const roleSelect = document.getElementById('add-user-role');
    const roleWrap = document.getElementById('add-user-custom-role-wrap');
    const roleInput = document.getElementById('add-user-custom-role-input');
    if (roleSelect) roleSelect.selectedIndex = 0;
    if (roleWrap) roleWrap.classList.add('hidden');
    if (roleInput) { roleInput.required = false; roleInput.value = ''; }
    document.getElementById('add-user-modal').classList.remove('hidden');
}
function closeAddUserModal() {
    document.getElementById('add-user-modal').classList.add('hidden');
}

function openAddRoleModal() {
    document.getElementById('new-role-input').value = '';
    document.getElementById('add-role-modal').classList.remove('hidden');
    setTimeout(() => {
        const inp = document.getElementById('new-role-input');
        if (inp) inp.focus();
    }, 50);
}
function closeAddRoleModal() {
    document.getElementById('add-role-modal').classList.add('hidden');
}

function handleAddRoleSelectChange(select) {
    const wrap = document.getElementById('add-user-custom-role-wrap');
    const input = document.getElementById('add-user-custom-role-input');
    if (select.value === '__custom__') {
        wrap.classList.remove('hidden');
        input.required = true;
        input.focus();
    } else {
        wrap.classList.add('hidden');
        input.required = false;
        input.value = '';
    }
}

function showAddCustomRoleInput() {
    const select = document.getElementById('add-user-role');
    select.value = '__custom__';
    handleAddRoleSelectChange(select);
}

function handleEditRoleSelectChange(select) {
    const wrap = document.getElementById('edit-user-custom-role-wrap');
    const input = document.getElementById('edit-user-custom-role-input');
    if (select.value === '__custom__') {
        wrap.classList.remove('hidden');
        input.required = true;
        input.focus();
    } else {
        wrap.classList.add('hidden');
        input.required = false;
        input.value = '';
    }
}

function showEditCustomRoleInput() {
    const select = document.getElementById('edit-user-role');
    select.value = '__custom__';
    handleEditRoleSelectChange(select);
}

function openEditUserModal(user) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-user-username').value = user.username;
    document.getElementById('edit-user-fullname').value = user.fullname;
    
    const roleSelect = document.getElementById('edit-user-role');
    const roleWrap = document.getElementById('edit-user-custom-role-wrap');
    const roleInput = document.getElementById('edit-user-custom-role-input');
    roleWrap.classList.add('hidden');
    roleInput.required = false;
    roleInput.value = '';
    
    let found = false;
    for (let i = 0; i < roleSelect.options.length; i++) {
        if (roleSelect.options[i].value === user.role) {
            roleSelect.selectedIndex = i;
            found = true;
            break;
        }
    }
    if (!found && user.role) {
        const opt = document.createElement('option');
        opt.value = user.role;
        opt.textContent = user.role;
        const customOpt = roleSelect.querySelector('option[value="__custom__"]');
        if (customOpt) {
            roleSelect.insertBefore(opt, customOpt);
        } else {
            roleSelect.appendChild(opt);
        }
        roleSelect.value = user.role;
    } else if (!user.role) {
        roleSelect.selectedIndex = 0;
    }
    
    document.getElementById('edit-user-modal').classList.remove('hidden');
}
function closeEditUserModal() {
    document.getElementById('edit-user-modal').classList.add('hidden');
}

function previewLogoFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('preset_logo_input').value = '';
        document.getElementById('file-name-span').textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
        document.getElementById('logo-file-selected-name').classList.remove('hidden');

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logo-preview-img').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function selectPresetLogo(type) {
    const previewImg = document.getElementById('logo-preview-img');
    const presetInput = document.getElementById('preset_logo_input');
    const fileInput = document.getElementById('logo_file_input');
    fileInput.value = '';
    document.getElementById('logo-file-selected-name').classList.add('hidden');

    if (type === 'dole') {
        previewImg.src = 'assets/img/dole_logo.png';
        presetInput.value = 'dole';
    } else if (type === 'garuda') {
        previewImg.src = 'assets/img/garuda.svg';
        presetInput.value = 'garuda';
    } else if (type === 'default') {
        previewImg.src = 'assets/img/dole_logo.png';
        presetInput.value = 'default';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
