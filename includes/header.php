<?php
// includes/header.php
require_once __DIR__ . '/../config/auth.php';
require_login();

$current_user = current_user();
$user_name = $current_user['fullname'] ?? 'เจ้าหน้าที่พัสดุ';
$user_role = $current_user['role'] ?? 'ผู้ดูแลระบบ';
$user_avatar = !empty($current_user['fullname']) ? mb_substr($current_user['fullname'], 0, 1, 'UTF-8') : 'ส';

if (!isset($active_page)) {
    $active_page = 'dashboard';
}
if (!isset($page_title)) {
    $page_title = 'แดชบอร์ดภาพรวม';
}
if (!isset($page_subtitle)) {
    $page_subtitle = 'ภาพรวมข้อมูลครุภัณฑ์ของสำนักงานส่งเสริมการเรียนรู้ อำเภอเสนา';
}

$app_settings_file = __DIR__ . '/../config/settings.json';
$app_settings = file_exists($app_settings_file) ? (json_decode(file_get_contents($app_settings_file), true) ?: []) : [];
$app_logo = !empty($app_settings['logo_url']) ? $app_settings['logo_url'] : 'assets/img/dole_logo.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - SENA_Asset</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- SheetJS CDN for reading and exporting Excel files in browser -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Prompt', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f5f3ff',
                            100: '#ede9fe',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                            dark: '#111432',
                            darker: '#0c0e27',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f1f5f9;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .sidebar-glow {
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.25);
        }
        @media print {
            aside, header, footer, .no-print, #sidebar-backdrop {
                display: none !important;
            }
            body, .flex-1, main {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            #printable-report {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex">

    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebar-backdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden lg:hidden"></div>

    <!-- LEFT SIDEBAR -->
    <aside id="main-sidebar" class="w-72 bg-gradient-to-b from-[#141846] via-[#171b4e] to-[#0d102e] text-white flex-shrink-0 flex flex-col justify-between z-50 fixed lg:static inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out shadow-2xl">
        
        <div>
            <!-- LOGO HEADER -->
            <div class="px-6 py-6 flex items-center gap-3.5 border-b border-indigo-900/40">
                <a href="index.php" class="flex items-center gap-3.5 group">
                    <div class="w-11 h-11 rounded-xl bg-white p-1.5 flex items-center justify-center shadow-lg shadow-indigo-500/20 text-[#1d2671] flex-shrink-0 overflow-hidden transition-transform duration-200 group-hover:scale-105">
                        <img src="<?= htmlspecialchars($app_logo) ?>" 
                             alt="SENA Asset" 
                             class="max-w-full max-h-full object-contain"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <i class="fa-solid fa-landmark text-xl hidden"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-lg text-white tracking-wide leading-tight group-hover:text-indigo-200 transition-colors">SENA_Asset</h1>
                        <p class="text-xs text-indigo-200/70 font-light truncate max-w-[170px]"><?= htmlspecialchars($app_settings['org_name'] ?? 'ระบบบริหารจัดการครุภัณฑ์') ?></p>
                    </div>
                </a>
            </div>

            <!-- MENU NAVIGATION -->
            <nav class="px-4 py-6 space-y-1.5">
                <!-- 1. แดชบอร์ด -->
                <a href="index.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium transition-all <?= $active_page === 'dashboard' ? 'bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-house text-base w-5 text-center"></i>
                    <span>แดชบอร์ด</span>
                </a>

                <!-- 2. ทะเบียนครุภัณฑ์ -->
                <a href="equipment.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium transition-all <?= $active_page === 'equipment' ? 'bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-regular fa-file-lines text-base w-5 text-center"></i>
                    <span>ทะเบียนครุภัณฑ์</span>
                </a>

                <!-- 3. บัญชีรายการตรวจประจำปี -->
                <a href="inspection.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium transition-all <?= $active_page === 'inspection' ? 'bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-regular fa-square-check text-base w-5 text-center"></i>
                    <span>บัญชีรายการตรวจประจำปี</span>
                </a>

                <!-- 4. นำเข้าข้อมูล -->
                <a href="import.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium transition-all <?= $active_page === 'import' ? 'bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-cloud-arrow-up text-base w-5 text-center"></i>
                    <span>นำเข้าข้อมูล</span>
                </a>

                <!-- 5. รายงาน -->
                <a href="reports.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium transition-all <?= $active_page === 'reports' ? 'bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-chart-simple text-base w-5 text-center"></i>
                    <span>รายงาน</span>
                </a>

                <!-- 6. ตั้งค่าระบบ -->
                <a href="settings.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm font-medium transition-all <?= $active_page === 'settings' ? 'bg-gradient-to-r from-indigo-600 to-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <i class="fa-solid fa-gear text-base w-5 text-center"></i>
                    <span>ตั้งค่าระบบ</span>
                </a>

                <!-- 7. ออกจากระบบ -->
                <div class="pt-3 mt-3 border-t border-indigo-900/40">
                    <a href="logout.php" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?')" class="flex items-center gap-3.5 px-4 py-2.5 rounded-xl text-xs font-medium text-rose-300 hover:text-white hover:bg-rose-500/20 transition-all border border-rose-500/20">
                        <i class="fa-solid fa-arrow-right-from-bracket text-sm w-5 text-center text-rose-400"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- SIDEBAR FOOTER (Building graphic + copyright) -->
        <div class="px-6 py-5 border-t border-indigo-900/30 relative overflow-hidden bg-gradient-to-t from-black/40 to-transparent">
            <!-- Subtle Building Silhouette Background -->
            <div class="absolute -right-4 bottom-0 opacity-10 pointer-events-none text-white text-8xl">
                <i class="fa-solid fa-landmark-dome"></i>
            </div>
            
            <div class="relative z-10 space-y-1">
                <p class="text-xs font-medium text-slate-200 leading-relaxed">
                    สำนักงานส่งเสริมการเรียนรู้<br>
                    อำเภอเสนา<br>
                    จังหวัดพระนครศรีอยุธยา
                </p>
                <div class="pt-3 text-[11px] text-slate-400">
                    <p class="font-mono text-indigo-300/80">SENA_Asset v1.0.0</p>
                    <p>© 2567 สำนักงานส่งเสริมการเรียนรู้ อำเภอเสนา</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN WRAPPER -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        
        <!-- TOP NAVBAR -->
        <header class="bg-white/95 backdrop-blur-md sticky top-0 z-30 border-b border-slate-200/80 px-4 sm:px-6 py-3 sm:py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-xl font-bold text-slate-800 leading-tight"><?= htmlspecialchars($page_title) ?></h2>
                    <p class="text-xs text-slate-500 font-normal"><?= htmlspecialchars($page_subtitle) ?></p>
                </div>
            </div>

            <!-- SEARCH & USER PROFILE -->
            <div class="flex items-center gap-4 flex-wrap md:flex-nowrap justify-between md:justify-end">
                <!-- Search input triggering Quick Search Modal -->
                <div class="relative w-full md:w-72">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-sm">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" id="global-search-trigger" onclick="openSearchModal()" 
                           placeholder="ค้นหาครุภัณฑ์, รหัส, สถานที่..." 
                           class="w-full bg-slate-50 border border-slate-200 pl-9 pr-12 py-2 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all text-slate-700 cursor-pointer">
                    <span class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none">
                        <kbd class="px-1.5 py-0.5 text-[10px] font-semibold text-slate-400 bg-white border border-slate-200 rounded-md shadow-2xs">⌘ K</kbd>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Notification Bell with badge -->
                    <button onclick="openNotificationModal()" class="relative p-2.5 rounded-xl text-slate-500 hover:text-indigo-600 hover:bg-slate-100 transition-colors">
                        <i class="fa-regular fa-bell text-base"></i>
                        <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-rose-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white">3</span>
                    </button>

                    <!-- User Profile Dropdown -->
                    <div class="relative pl-3 border-l border-slate-200" id="user-profile-menu-container">
                        <button type="button" onclick="toggleUserDropdown()" class="flex items-center gap-3 hover:opacity-85 transition-opacity focus:outline-none text-left cursor-pointer">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center font-semibold text-sm shadow-sm flex-shrink-0">
                                <?= htmlspecialchars($user_avatar) ?>
                            </div>
                            <div class="hidden sm:block text-left">
                                <p class="text-xs font-semibold text-slate-800 leading-tight"><?= htmlspecialchars($user_name) ?></p>
                                <p class="text-[11px] text-slate-400"><?= htmlspecialchars($user_role) ?></p>
                            </div>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 hidden sm:block ml-0.5"></i>
                        </button>

                        <!-- Dropdown Menu Box -->
                        <div id="user-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-2 z-50 animate-in fade-in zoom-in-95 duration-100">
                            <div class="px-4 py-2.5 border-b border-slate-100">
                                <p class="text-xs font-bold text-slate-800"><?= htmlspecialchars($user_name) ?></p>
                                <p class="text-[11px] text-slate-400">@<?= htmlspecialchars($current_user['username'] ?? 'user') ?> &bull; <?= htmlspecialchars($user_role) ?></p>
                            </div>
                            
                            <a href="settings.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-user-gear text-indigo-500 w-4"></i>
                                <span>ข้อมูลผู้ใช้และตั้งค่า</span>
                            </a>
                            
                            <a href="reports.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-file-invoice text-indigo-500 w-4"></i>
                                <span>รายงานสรุปครุภัณฑ์</span>
                            </a>

                            <div class="my-1 border-t border-slate-100"></div>

                            <a href="logout.php" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?')" class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 transition-colors">
                                <i class="fa-solid fa-arrow-right-from-bracket text-rose-500 w-4"></i>
                                <span>ออกจากระบบ</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- TIMESTAMP SUB-BAR -->
        <div class="bg-white/70 border-b border-slate-200/60 px-4 sm:px-6 py-2 flex items-center justify-between text-xs text-slate-500 flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <i class="fa-regular fa-calendar-check text-indigo-500"></i>
                <span>ข้อมูลล่าสุด 15 ตุลาคม 2567 เวลา 10:24 น.</span>
            </div>
            <button onclick="window.location.reload()" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 hover:text-indigo-600 rounded-lg transition-colors">
                <i class="fa-solid fa-arrows-rotate text-[11px]"></i>
                <span>รีเฟรช</span>
            </button>
        </div>

        <!-- MAIN PAGE CONTENT CONTAINER -->
        <main class="flex-1 p-3.5 sm:p-6 space-y-4 sm:space-y-6">
