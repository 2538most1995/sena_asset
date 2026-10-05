<?php
// index.php - แดชบอร์ดภาพรวม
$active_page = 'dashboard';
$page_title = 'แดชบอร์ดภาพรวม';
$page_subtitle = 'ภาพรวมข้อมูลครุภัณฑ์ของสำนักงานส่งเสริมการเรียนรู้ อำเภอเสนา';

require_once __DIR__ . '/config/database.php';

$settings_file = __DIR__ . '/config/settings.json';
$app_settings = file_exists($settings_file) ? (json_decode(file_get_contents($settings_file), true) ?: []) : [];
$default_year = (int)($app_settings['fiscal_year'] ?? 2568);

// -------------------------------------------------------------
// 1. ดึงรายการปีงบประมาณทั้งหมดที่มีในระบบ
// -------------------------------------------------------------
$existing_years = [];
$master_equipment_count = 0;

try {
    if ($pdo) {
        $stmt_years = $pdo->query("SELECT DISTINCT fiscal_year FROM inspection_items WHERE fiscal_year IS NOT NULL ORDER BY fiscal_year DESC");
        $existing_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

        $master_equipment_count = (int)$pdo->query("SELECT COUNT(*) FROM equipment_registry")->fetchColumn();
    }
} catch (\Throwable $e) {}

if (empty($existing_years)) {
    $existing_years = [$default_year];
}

// ปีงบประมาณที่เลือกแสดง (ค่าเริ่มต้นคือปีที่ตั้งไว้ หรือปีล่าสุดในระบบ)
$selected_year = $_GET['year'] ?? '';
if ($selected_year !== 'all') {
    if ($selected_year === '' || !in_array((int)$selected_year, array_map('intval', $existing_years))) {
        $selected_year = in_array($default_year, array_map('intval', $existing_years)) ? (string)$default_year : (string)$existing_years[0];
    }
}

// -------------------------------------------------------------
// 2. คำนวณสถิติตามปีงบประมาณที่เลือก
// -------------------------------------------------------------
$total_items = 0;
$usable_items = 0;
$damaged_items = 0;
$degraded_items = 0;
$lost_items = 0;
$unused_items = 0;
$overlap_items = 0;
$checked_items = 0;
$pending_items = 0;

try {
    if ($pdo) {
        if ($selected_year !== 'all') {
            $cur_y = (int)$selected_year;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inspection_items WHERE fiscal_year = ?");
            $stmt->execute([$cur_y]);
            $total_items = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT 
                COALESCE(SUM(status_usable),0),
                COALESCE(SUM(status_damaged),0),
                COALESCE(SUM(status_degraded),0),
                COALESCE(SUM(status_lost),0),
                COALESCE(SUM(status_unused),0),
                COALESCE(SUM(is_overlap),0)
                FROM inspection_items WHERE fiscal_year = ?");
            $stmt->execute([$cur_y]);
            $sum_res = $stmt->fetch(PDO::FETCH_NUM);
            if ($sum_res) {
                list($usable_items, $damaged_items, $degraded_items, $lost_items, $unused_items, $overlap_items) = array_map('intval', $sum_res);
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inspection_items WHERE fiscal_year = ? AND (status_usable = 1 OR status_damaged = 1 OR status_degraded = 1 OR status_lost = 1 OR status_unused = 1)");
            $stmt->execute([$cur_y]);
            $checked_items = (int)$stmt->fetchColumn();
        } else {
            $total_items = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items")->fetchColumn();
            $stmt = $pdo->query("SELECT 
                COALESCE(SUM(status_usable),0),
                COALESCE(SUM(status_damaged),0),
                COALESCE(SUM(status_degraded),0),
                COALESCE(SUM(status_lost),0),
                COALESCE(SUM(status_unused),0),
                COALESCE(SUM(is_overlap),0)
                FROM inspection_items");
            $sum_res = $stmt->fetch(PDO::FETCH_NUM);
            if ($sum_res) {
                list($usable_items, $damaged_items, $degraded_items, $lost_items, $unused_items, $overlap_items) = array_map('intval', $sum_res);
            }

            $checked_items = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items WHERE status_usable = 1 OR status_damaged = 1 OR status_degraded = 1 OR status_lost = 1 OR status_unused = 1")->fetchColumn();
        }
        $pending_items = max(0, $total_items - $checked_items);
    } else {
        throw new \Exception("Database not connected");
    }
} catch (\Throwable $e) {
    $total_items = 0;
    $usable_items = 0;
    $damaged_items = 0;
    $degraded_items = 0;
    $lost_items = 0;
    $unused_items = 0;
    $overlap_items = 0;
    $checked_items = 0;
    $pending_items = 0;
}

// คำนวณร้อยละ
$p_usable = $total_items > 0 ? round(($usable_items / $total_items) * 100, 2) : 0;
$p_damaged = $total_items > 0 ? round(($damaged_items / $total_items) * 100, 2) : 0;
$p_degraded = $total_items > 0 ? round(($degraded_items / $total_items) * 100, 2) : 0;
$p_lost = $total_items > 0 ? round(($lost_items / $total_items) * 100, 2) : 0;
$p_unused = $total_items > 0 ? round(($unused_items / $total_items) * 100, 2) : 0;
$p_overlap = $total_items > 0 ? round(($overlap_items / $total_items) * 100, 2) : 0;
$p_checked = $total_items > 0 ? round(($checked_items / $total_items) * 100, 2) : 0;
$p_pending = $total_items > 0 ? round(($pending_items / $total_items) * 100, 2) : 0;

// -------------------------------------------------------------
// 3. ดึงข้อมูลหมวดหมู่และสถานที่สำหรับกราฟ
// -------------------------------------------------------------
$category_labels = [];
$category_data = [];
$location_labels = [];
$location_data = [];
$trend_labels = [];
$trend_data = [];

try {
    if ($pdo) {
        // หมวดหมู่ครุภัณฑ์
        $cat_sql = "SELECT COALESCE(NULLIF(category, ''), 'ไม่ระบุประเภท') as cat, COUNT(*) as cnt FROM equipment_registry GROUP BY cat ORDER BY cnt DESC LIMIT 5";
        $cat_stmt = $pdo->prepare($cat_sql);
        $cat_stmt->execute();
        while ($r = $cat_stmt->fetch(PDO::FETCH_ASSOC)) {
            $category_labels[] = $r['cat'];
            $category_data[] = (int)$r['cnt'];
        }

        // สถานที่ใช้งาน
        $loc_sql = "SELECT COALESCE(NULLIF(location, ''), 'ไม่ระบุสถานที่') as loc, COUNT(*) as cnt FROM inspection_items " . 
                   ($selected_year !== 'all' ? "WHERE fiscal_year = ? " : "") . 
                   "GROUP BY loc ORDER BY cnt DESC LIMIT 5";
        $loc_stmt = $pdo->prepare($loc_sql);
        $loc_stmt->execute($selected_year !== 'all' ? [(int)$selected_year] : []);
        while ($r = $loc_stmt->fetch(PDO::FETCH_ASSOC)) {
            $location_labels[] = $r['loc'];
            $location_data[] = (int)$r['cnt'];
        }

        // แนวโน้มตามแต่ละปีงบประมาณ
        $trend_stmt = $pdo->query("SELECT fiscal_year, COUNT(*) as cnt FROM inspection_items WHERE fiscal_year IS NOT NULL GROUP BY fiscal_year ORDER BY fiscal_year ASC");
        while ($tr = $trend_stmt->fetch(PDO::FETCH_ASSOC)) {
            $trend_labels[] = 'ปี ' . $tr['fiscal_year'];
            $trend_data[] = (int)$tr['cnt'];
        }
    }
} catch (\Throwable $e) {}


include __DIR__ . '/includes/header.php';
?>

<!-- 0. FISCAL YEAR SELECTOR BANNER -->
<div class="flex items-center justify-between flex-wrap gap-3 mb-5 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-2xs">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base flex-shrink-0">
            <i class="fa-regular fa-calendar-check"></i>
        </div>
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-sm sm:text-base font-bold text-slate-800">
                    <?= $selected_year === 'all' ? 'ผลการตรวจสอบครุภัณฑ์ (รวมทุกปีงบประมาณ)' : 'ผลการตรวจสอบครุภัณฑ์ ประจำปีงบประมาณ พ.ศ. ' . htmlspecialchars($selected_year) ?>
                </h2>
                <?php if ($master_equipment_count > 0): ?>
                <a href="equipment.php" class="text-[11px] px-2.5 py-0.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium inline-flex items-center gap-1 transition-colors" title="ดูทะเบียนครุภัณฑ์หลักทั้งหมดของหน่วยงาน">
                    <i class="fa-solid fa-boxes-stacked text-[10px] text-indigo-500"></i>
                    <span>ทะเบียนครุภัณฑ์หลัก <?= number_format($master_equipment_count) ?> รายการ</span>
                </a>
                <?php endif; ?>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
                <?= $selected_year === 'all' ? 'กำลังแสดงสถิติรวมทั้งหมด ' . count($existing_years) . ' ปีงบประมาณที่มีในระบบ' : 'แสดงสถิติและผลการตรวจนับเฉพาะปีงบประมาณ ' . htmlspecialchars($selected_year) ?>
            </p>
        </div>
    </div>
    
    <div class="flex items-center gap-2">
        <label class="text-xs text-slate-500 font-medium whitespace-nowrap">เลือกปีงบประมาณ:</label>
        <select onchange="window.location.href='index.php?year=' + this.value" class="text-xs bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-700 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer">
            <?php foreach ($existing_years as $y): ?>
            <option value="<?= $y ?>" <?= (string)$selected_year === (string)$y ? 'selected' : '' ?>>ปีงบประมาณ <?= $y ?></option>
            <?php endforeach; ?>
            <option value="all" <?= $selected_year === 'all' ? 'selected' : '' ?>>รวมทุกปีงบประมาณ (<?= count($existing_years) ?> ปี)</option>
        </select>
    </div>
</div>

<!-- 1. TOP 7 STAT CARDS -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3.5">
    
    <!-- 1. ครุภัณฑ์ทั้งหมด -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-cube text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ครุภัณฑ์ทั้งหมด</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($total_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-slate-400 mt-1.5 font-light">
            <?= $selected_year === 'all' ? 'รวมทุกปีงบประมาณ' : 'ปีงบประมาณ ' . htmlspecialchars($selected_year) ?>
        </p>
    </div>

    <!-- 2. ใช้ได้ -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-circle-check text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ใช้ได้</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($usable_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-emerald-600 mt-1.5 font-medium"><?= $p_usable ?>% ของทั้งหมด</p>
    </div>

    <!-- 3. ชำรุด -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-wrench text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ชำรุด</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($damaged_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-rose-600 mt-1.5 font-medium"><?= $p_damaged ?>% ของทั้งหมด</p>
    </div>

    <!-- 4. เสื่อมคุณภาพ -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-triangle-exclamation text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">เสื่อมคุณภาพ</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($degraded_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-amber-600 mt-1.5 font-medium"><?= $p_degraded ?>% ของทั้งหมด</p>
    </div>

    <!-- 5. สูญไป -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-xmark text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">สูญไป</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($lost_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-slate-400 mt-1.5 font-light"><?= $p_lost ?>% ของทั้งหมด</p>
    </div>

    <!-- 6. ไม่ใช้ -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all">
        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-pause text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ไม่ใช้</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-slate-800"><?= number_format($unused_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-slate-400 mt-1.5 font-light"><?= $p_unused ?>% ของทั้งหมด</p>
    </div>

    <!-- 7. ข้อมูลต้องตรวจสอบ -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs hover:shadow-md transition-all col-span-2 sm:col-span-1">
        <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center mb-2.5">
            <i class="fa-solid fa-circle-info text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ข้อมูลต้องตรวจสอบ</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-2xl font-bold text-rose-600"><?= number_format($overlap_items) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-rose-500 mt-1.5 font-medium"><?= $p_overlap ?>% ของทั้งหมด</p>
    </div>

</div>

<!-- 2. MIDDLE ROW CHARTS (2x2 GRID) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">

    <!-- CHART 1: สถานะครุภัณฑ์ (Donut Chart) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">สถานะครุภัณฑ์ (<?= $selected_year === 'all' ? 'ทุกปี' : 'ปี ' . htmlspecialchars($selected_year) ?>)</h3>
            </div>
            <span class="text-xs text-slate-400">รวม <?= number_format($total_items) ?> รายการ</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 items-center gap-4 pt-4">
            <!-- Donut Container with Center Text -->
            <div class="relative w-44 h-44 mx-auto">
                <canvas id="statusDonutChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-extrabold text-slate-800"><?= number_format($total_items) ?></span>
                    <span class="text-[11px] text-slate-400">รายการ</span>
                </div>
            </div>

            <!-- Legend with dynamic values -->
            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>
                        <span class="text-slate-600">ใช้ได้</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format($usable_items) ?> <span class="text-slate-400 font-normal">(<?= $p_usable ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>
                        <span class="text-slate-600">ชำรุด</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format($damaged_items) ?> <span class="text-slate-400 font-normal">(<?= $p_damaged ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                        <span class="text-slate-600">เสื่อมคุณภาพ</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format($degraded_items) ?> <span class="text-slate-400 font-normal">(<?= $p_degraded ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#38bdf8]"></span>
                        <span class="text-slate-600">สูญไป</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format($lost_items) ?> <span class="text-slate-400 font-normal">(<?= $p_lost ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#818cf8]"></span>
                        <span class="text-slate-600">ไม่ใช้</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format($unused_items) ?> <span class="text-slate-400 font-normal">(<?= $p_unused ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#fb923c]"></span>
                        <span class="text-slate-600">ข้อมูลต้องตรวจสอบ</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format($overlap_items) ?> <span class="text-slate-400 font-normal">(<?= $p_overlap ?>%)</span></span>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART 2: ครุภัณฑ์ตามประเภท (Bar Chart) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">ครุภัณฑ์ตามประเภท</h3>
            </div>
            <span class="text-xs text-slate-400"><?= $selected_year === 'all' ? 'ทุกปีงบประมาณ' : 'ปี ' . htmlspecialchars($selected_year) ?></span>
        </div>
        <div class="pt-4 h-60">
            <canvas id="categoryBarChart"></canvas>
        </div>
    </div>

    <!-- CHART 3: ตามสถานที่ใช้งาน (Horizontal Bar Chart) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-location-dot text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">ตามสถานที่ใช้งาน</h3>
            </div>
            <span class="text-xs text-slate-400">5 สถานที่ยอดนิยม</span>
        </div>
        <div class="pt-4 h-60">
            <canvas id="locationBarChart"></canvas>
        </div>
    </div>

    <!-- CHART 4: ผลการตรวจย้อนหลังแต่ละปีงบประมาณ -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">จำนวนครุภัณฑ์เปรียบเทียบตามปีงบประมาณ</h3>
            </div>
            <span class="text-xs text-slate-400"><?= count($trend_labels) ?> ปีงบประมาณ</span>
        </div>
        <div class="pt-4 h-60">
            <canvas id="trendLineChart"></canvas>
        </div>
    </div>

</div>

<!-- 3. BOTTOM ROW (ACTION ITEMS & SUMMARY) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">

    <!-- LEFT: รายการที่ต้องดำเนินการ -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-ul text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">รายการที่ต้องดำเนินการ (<?= $selected_year === 'all' ? 'ทุกปี' : 'ปี ' . htmlspecialchars($selected_year) ?>)</h3>
            </div>
            <a href="inspection.php<?= $selected_year !== 'all' ? '?year=' . $selected_year : '' ?>" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">ดูทั้งหมด &rarr;</a>
        </div>

        <div class="divide-y divide-slate-100 pt-1">
            <!-- 1. ครุภัณฑ์ชำรุด -->
            <a href="inspection.php?status=damaged<?= $selected_year !== 'all' ? '&year=' . $selected_year : '' ?>" class="flex items-center justify-between py-3.5 px-2 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-wrench"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">ครุภัณฑ์ชำรุด</p>
                        <p class="text-[11px] text-slate-400">มีครุภัณฑ์ที่ชำรุด รอการซ่อมแซมหรือจำหน่าย</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-rose-600"><?= number_format($damaged_items) ?> รายการ</span>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </div>
            </a>

            <!-- 2. สถานะซ้ำซ้อน -->
            <a href="inspection.php?filter=overlap<?= $selected_year !== 'all' ? '&year=' . $selected_year : '' ?>" class="flex items-center justify-between py-3.5 px-2 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">สถานะซ้ำซ้อน</p>
                        <p class="text-[11px] text-slate-400">พบรายการที่มีสถานะซ้ำซ้อนในระบบ</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-amber-600"><?= number_format($overlap_items) ?> รายการ</span>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </div>
            </a>

            <!-- 3. ครุภัณฑ์เสื่อมคุณภาพ -->
            <a href="inspection.php?status=degraded<?= $selected_year !== 'all' ? '&year=' . $selected_year : '' ?>" class="flex items-center justify-between py-3.5 px-2 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">ครุภัณฑ์เสื่อมคุณภาพ</p>
                        <p class="text-[11px] text-slate-400">ครุภัณฑ์ที่เริ่มหมดอายุการใช้งานหรือเสื่อมสภาพ</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-orange-600"><?= number_format($degraded_items) ?> รายการ</span>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </div>
            </a>
        </div>
    </div>

    <!-- RIGHT: สรุปข้อมูลสำคัญ -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">สรุปความคืบหน้าการตรวจนับ</h3>
            </div>
            <span class="text-xs font-medium text-indigo-600">
                <?= $selected_year === 'all' ? 'ทุกปีงบประมาณ' : 'ปีงบประมาณ ' . htmlspecialchars($selected_year) ?>
            </span>
        </div>

        <div class="pt-3 space-y-4">
            <!-- 1. จำนวนครุภัณฑ์ทั้งหมดในปีนี้ -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-box-archive text-sm"></i>
                    </div>
                    <span class="text-xs text-slate-600 font-medium">รายการตรวจนับทั้งหมด</span>
                </div>
                <div class="text-right">
                    <span class="text-sm font-bold text-slate-800"><?= number_format($total_items) ?> รายการ</span>
                    <p class="text-[10px] text-slate-400 font-light"><?= $selected_year === 'all' ? 'รวมทุกปี' : 'ประจำปี ' . htmlspecialchars($selected_year) ?></p>
                </div>
            </div>

            <!-- 2. ตรวจสอบแล้ว Progress Bar -->
            <div class="space-y-1.5 p-3 rounded-xl bg-slate-50/80">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-file-lines text-emerald-500"></i>
                        <span class="text-slate-600 font-medium">ตรวจนับแล้ว</span>
                    </div>
                    <span class="font-bold text-slate-800"><?= number_format($checked_items) ?> รายการ <span class="text-slate-400 font-normal">(<?= $p_checked ?>%)</span></span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-emerald-500 h-2 rounded-full transition-all duration-1000" style="width: <?= $p_checked ?>%"></div>
                </div>
            </div>

            <!-- 3. รอการตรวจสอบ Progress Bar -->
            <div class="space-y-1.5 p-3 rounded-xl bg-slate-50/80">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-clock text-amber-500"></i>
                        <span class="text-slate-600 font-medium">รอการตรวจนับ</span>
                    </div>
                    <span class="font-bold text-slate-800"><?= number_format($pending_items) ?> รายการ <span class="text-slate-400 font-normal">(<?= $p_pending ?>%)</span></span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-amber-500 h-2 rounded-full transition-all duration-1000" style="width: <?= $p_pending ?>%"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- CHARTS INITIALIZATION SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. STATUS DONUT CHART
    const ctxDonut = document.getElementById('statusDonutChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['ใช้ได้', 'ชำรุด', 'เสื่อมคุณภาพ', 'สูญไป', 'ไม่ใช้', 'ข้อมูลต้องตรวจสอบ'],
            datasets: [{
                data: [
                    <?= (int)$usable_items ?>,
                    <?= (int)$damaged_items ?>,
                    <?= (int)$degraded_items ?>,
                    <?= (int)$lost_items ?>,
                    <?= (int)$unused_items ?>,
                    <?= (int)$overlap_items ?>
                ],
                backgroundColor: [
                    '#10b981', // green
                    '#ef4444', // red
                    '#f59e0b', // orange
                    '#38bdf8', // sky
                    '#818cf8', // indigo
                    '#fb923c'  // warm orange
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 2. CATEGORY BAR CHART
    const ctxCat = document.getElementById('categoryBarChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'bar',
        data: {
            labels: <?= json_encode($category_labels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode($category_data) ?>,
                backgroundColor: [
                    '#8b5cf6', // purple
                    '#38bdf8', // light blue
                    '#34d399', // mint green
                    '#fb923c', // orange
                    '#a5b4fc'  // soft indigo
                ],
                borderRadius: 8,
                barThickness: 38
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { family: 'Prompt', size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Prompt', size: 11 } }
                }
            }
        }
    });

    // 3. LOCATION HORIZONTAL BAR CHART
    const ctxLoc = document.getElementById('locationBarChart').getContext('2d');
    new Chart(ctxLoc, {
        type: 'bar',
        data: {
            labels: <?= json_encode($location_labels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode($location_data) ?>,
                backgroundColor: [
                    '#8b5cf6', // purple
                    '#60a5fa', // blue
                    '#34d399', // green
                    '#fb923c', // orange
                    '#c084fc'  // light purple
                ],
                borderRadius: 6,
                barThickness: 16
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { family: 'Prompt', size: 10 } }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { family: 'Prompt', size: 11 } }
                }
            }
        }
    });

    // 4. TREND LINE CHART
    const ctxTrend = document.getElementById('trendLineChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($trend_labels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode($trend_data) ?>,
                borderColor: '#8b5cf6',
                backgroundColor: 'rgba(139, 92, 246, 0.12)',
                borderWidth: 3,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#8b5cf6',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { font: { family: 'Prompt', size: 10 } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Prompt', size: 11 } }
                }
            }
        }
    });

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
