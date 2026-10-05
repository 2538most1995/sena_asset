<?php
// reports.php - รายงาน
$active_page = 'reports';
$page_title = 'รายงาน';
$page_subtitle = 'สรุปรายงานครุภัณฑ์และผลการตรวจประจำปี';

require_once __DIR__ . '/config/database.php';

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
$settings = file_exists($config_file) ? (json_decode(file_get_contents($config_file), true) ?: $default_settings) : $default_settings;
$logo_url = !empty($settings['logo_url']) ? $settings['logo_url'] : 'assets/img/dole_logo.png';

// Filter params
// Filter params
$year = (int)($_GET['year'] ?? 2568);
$report_type = trim($_GET['type'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_loc = trim($_GET['loc'] ?? '');
$registry_count=(int)$pdo->query('SELECT COUNT(*) FROM equipment_registry')->fetchColumn();
$stats_stmt=$pdo->prepare("SELECT COUNT(*) total,COALESCE(SUM(status_usable),0) usable,COALESCE(SUM(status_damaged),0) damaged,COALESCE(SUM(status_degraded),0) degraded,COALESCE(SUM(status_lost),0) lost,COALESCE(SUM(status_unused),0) unused FROM inspection_items WHERE fiscal_year=?");
$stats_stmt->execute([$year]);$stats=$stats_stmt->fetch(PDO::FETCH_ASSOC);
$yearly_stats=$pdo->query("SELECT fiscal_year,COALESCE(SUM(status_usable),0) usable,COALESCE(SUM(status_damaged),0) damaged,COALESCE(SUM(status_degraded),0) degraded,COALESCE(SUM(status_lost+status_unused),0) other FROM inspection_items GROUP BY fiscal_year ORDER BY fiscal_year")->fetchAll(PDO::FETCH_ASSOC);
$pct=static fn($n)=>$stats['total']?number_format(100*$n/$stats['total'],1):'0.0';

// Handle CSV/Excel export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_sena_asset_' . $year . '_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['ลำดับที่', 'รหัสครุภัณฑ์', 'รายการครุภัณฑ์', 'ประเภท', 'สถานที่ใช้งาน', 'สถานะ', 'ราคา (บาท)']);
    
    $stmt = $pdo->prepare("SELECT item_number, asset_code, item_name, category, location, status_usable, status_damaged, status_degraded, price FROM inspection_items WHERE fiscal_year = ? ORDER BY item_number ASC");
    $stmt->execute([$year]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $st = 'รอตรวจนับ';
        if ($r['status_usable']) $st = 'ใช้ได้';
        elseif ($r['status_damaged']) $st = 'ชำรุด';
        elseif ($r['status_degraded']) $st = 'เสื่อมคุณภาพ';
        fputcsv($output, [
            $r['item_number'],
            $r['asset_code'],
            $r['item_name'],
            $r['category'],
            $r['location'],
            $st,
            number_format((float)$r['price'], 2)
        ]);
    }
    fclose($output);
    exit;
}

// Fetch preview items based on filters
$where = ["fiscal_year = ?"];
$params = [$year];

if ($filter_loc !== '' && $filter_loc !== 'ทั้งหมด') {
    $where[] = "location = ?";
    $params[] = $filter_loc;
}
if ($filter_status === 'usable') {
    $where[] = "status_usable = 1";
} elseif ($filter_status === 'damaged') {
    $where[] = "status_damaged = 1";
} elseif ($filter_status === 'degraded') {
    $where[] = "status_degraded = 1";
}
if ($report_type === 'dispose') $where[] = '(status_lost=1 OR status_unused=1)';

$where_sql = implode(' AND ', $where);

try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM inspection_items WHERE $where_sql ORDER BY item_number ASC LIMIT 50");
        $stmt->execute($params);
        $preview_items = $stmt->fetchAll();

        $locations = $pdo->query("SELECT DISTINCT location FROM inspection_items WHERE location IS NOT NULL AND location != '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
        $existing_years = $pdo->query("SELECT DISTINCT fiscal_year FROM inspection_items WHERE fiscal_year IS NOT NULL ORDER BY fiscal_year DESC")->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $preview_items = [];
        $locations = [];
        $existing_years = [2568];
    }
} catch (\Throwable $e) {
    $preview_items = [];
    $locations = [];
    $existing_years = [2568];
}

$year_options = array_unique(array_merge([2571, 2570, 2569, 2568, 2567], $existing_years));
rsort($year_options);

include __DIR__ . '/includes/header.php';
?>

<!-- 1. TOP FILTERS & ACTION BUTTONS -->
<div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs no-print">
    <form method="GET" action="reports.php" class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
        
        <div class="flex flex-wrap items-center gap-3">
            <!-- Fiscal Year -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">ปีงบประมาณ</span>
                <select name="year" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 font-medium focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($year_options as $y): ?>
                        <option value="<?= $y ?>" <?= $year === (int)$y ? 'selected' : '' ?>>พ.ศ. <?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Report Type -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">ประเภทรายงาน</span>
                <select name="type" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">ทั้งหมด</option>
                    <option value="all" <?= $report_type === 'all' ? 'selected' : '' ?>>ทะเบียนครุภัณฑ์ทั้งหมด</option>
                    <option value="damaged" <?= $report_type === 'damaged' ? 'selected' : '' ?>>รายงานครุภัณฑ์ชำรุด</option>
                    <option value="degraded" <?= $report_type === 'degraded' ? 'selected' : '' ?>>รายงานครุภัณฑ์เสื่อมคุณภาพ</option>
                    <option value="dispose" <?= $report_type === 'dispose' ? 'selected' : '' ?>>รายงานสูญไป/ไม่ใช้</option>
                </select>
            </div>

            <!-- Status -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">สถานะ</span>
                <select name="status" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">ทั้งหมด</option>
                    <option value="usable" <?= $filter_status === 'usable' ? 'selected' : '' ?>>ใช้ได้</option>
                    <option value="damaged" <?= $filter_status === 'damaged' ? 'selected' : '' ?>>ชำรุด</option>
                    <option value="degraded" <?= $filter_status === 'degraded' ? 'selected' : '' ?>>เสื่อมคุณภาพ</option>
                </select>
            </div>

            <!-- Location -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-slate-500">สถานที่ใช้งาน</span>
                <select name="loc" class="bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 max-w-[170px]">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= htmlspecialchars($l) ?>" <?= $filter_loc === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2.5">
            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow-xs transition-all">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                <span>แสดงรายงาน</span>
            </button>
            <button type="button" onclick="printReport()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-rose-600 text-xs font-medium transition-all">
                <i class="fa-regular fa-file-pdf"></i>
                <span>ส่งออก PDF</span>
            </button>
            <a href="?export=excel" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-emerald-600 text-xs font-medium transition-all">
                <i class="fa-regular fa-file-excel"></i>
                <span>ส่งออก Excel</span>
            </a>
        </div>
    </form>
</div>

<!-- 2. TOP 4 STAT CARDS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 no-print">
    <!-- 1. ทะเบียนครุภัณฑ์ -->
    <a href="reports.php?type=all" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-md transition-all">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-cube"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-medium">ทะเบียนครุภัณฑ์</p>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-2xl font-bold text-slate-800"><?= number_format($registry_count) ?></span>
                    <span class="text-xs text-slate-400">รายการ</span>
                </div>
                <p class="text-[10px] text-slate-400 mt-0.5">100% ของครุภัณฑ์ทั้งหมด</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
    </a>

    <!-- 2. รายงานชำรุด -->
    <a href="reports.php?status=damaged" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-md transition-all">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-wrench"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-medium">รายงานชำรุด</p>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-2xl font-bold text-slate-800"><?= number_format((int)$stats['damaged']) ?></span>
                    <span class="text-xs text-slate-400">รายการ</span>
                </div>
                <p class="text-[10px] text-rose-500 mt-0.5 font-medium"><?= $pct((int)$stats['damaged']) ?>% ของบัญชีตรวจปีนี้</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
    </a>

    <!-- 3. รายงานเสื่อมคุณภาพ -->
    <a href="reports.php?status=degraded" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-md transition-all">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-medium">รายงานเสื่อมคุณภาพ</p>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-2xl font-bold text-slate-800"><?= number_format((int)$stats['degraded']) ?></span>
                    <span class="text-xs text-slate-400">รายการ</span>
                </div>
                <p class="text-[10px] text-amber-500 mt-0.5 font-medium"><?= $pct((int)$stats['degraded']) ?>% ของบัญชีตรวจปีนี้</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
    </a>

    <!-- 4. สูญไป/ไม่ใช้ -->
    <a href="reports.php?type=dispose" class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-2xs flex items-center justify-between hover:shadow-md transition-all">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-xs">
                <i class="fa-regular fa-file-lines"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-medium">สูญไป/ไม่ใช้</p>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-2xl font-bold text-slate-800"><?= number_format((int)$stats['lost']+(int)$stats['unused']) ?></span>
                    <span class="text-xs text-slate-400">รายการ</span>
                </div>
                <p class="text-[10px] text-blue-500 mt-0.5 font-medium">1.99% ของทั้งหมด</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
    </a>
</div>

<!-- 3. MIDDLE ROW (3 COLUMNS: YEAR COMPARISON BAR | DONUT | POPULAR REPORTS) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 no-print">

    <!-- 1. เปรียบเทียบผลตรวจตามปี (Bar Chart) lg:col-span-5 -->
    <div class="lg:col-span-5 bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">เปรียบเทียบผลตรวจตามปี</h3>
            </div>
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-slate-600">
                <option>จำนวน (รายการ)</option>
            </select>
        </div>

        <div class="flex items-center justify-center gap-4 text-xs mt-3 flex-wrap">
            <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#38bdf8]"></span> ปกติ</div>
            <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span> ชำรุด</div>
            <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span> เสื่อมคุณภาพ</div>
            <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#818cf8]"></span> สูญไป/ไม่ใช้</div>
        </div>

        <div class="pt-3 h-60">
            <canvas id="yearlyBarChart"></canvas>
        </div>
    </div>

    <!-- 2. สัดส่วนสถานะครุภัณฑ์ (Donut Chart) lg:col-span-4 -->
    <div class="lg:col-span-4 bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">สัดส่วนสถานะครุภัณฑ์</h3>
            </div>
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-slate-600">
                <option>จำนวน (รายการ)</option>
            </select>
        </div>

        <div class="flex flex-col items-center justify-center pt-2">
            <div class="relative w-40 h-40">
                <canvas id="reportDonutChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xl font-extrabold text-slate-800"><?= number_format((int)$stats['total']) ?></span>
                    <span class="text-[10px] text-slate-400">รายการ</span>
                </div>
            </div>

            <div class="w-full space-y-1.5 text-xs mt-3">
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>
                        <span class="text-slate-600">ใช้ได้</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format((int)$stats['usable']) ?> <span class="text-slate-400 font-normal">(<?= $pct((int)$stats['usable']) ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>
                        <span class="text-slate-600">ชำรุด</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format((int)$stats['damaged']) ?> <span class="text-slate-400 font-normal">(<?= $pct((int)$stats['damaged']) ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                        <span class="text-slate-600">เสื่อมคุณภาพ</span>
                    </div>
                    <span class="font-semibold text-slate-800"><?= number_format((int)$stats['degraded']) ?> <span class="text-slate-400 font-normal">(<?= $pct((int)$stats['degraded']) ?>%)</span></span>
                </div>
                <div class="flex items-center justify-between py-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#818cf8]"></span>
                        <span class="text-slate-600">สูญไป/ไม่ใช้</span>
                    </div>
                    <span class="font-semibold text-slate-800">12 <span class="text-slate-400 font-normal">(1.99%)</span></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. รายงานยอดนิยม lg:col-span-3 -->
    <div class="lg:col-span-3 bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
            <i class="fa-solid fa-star text-indigo-600 text-sm"></i>
            <h3 class="font-bold text-slate-800 text-sm">รายงานยอดนิยม</h3>
        </div>

        <div class="divide-y divide-slate-100 pt-1 space-y-1">
            <a href="reports.php?type=all" class="flex items-center justify-between py-3 px-1 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-blue-500 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 mt-0.5">1</span>
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">ทะเบียนครุภัณฑ์ทั้งหมด</h4>
                        <p class="text-[10px] text-slate-400">รายการครุภัณฑ์ทั้งหมดในระบบ</p>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="text-xs font-bold text-slate-800"><?= number_format($registry_count) ?></span>
                    <p class="text-[9px] text-slate-400">รายการ ></p>
                </div>
            </a>

            <a href="reports.php?status=damaged" class="flex items-center justify-between py-3 px-1 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-rose-500 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 mt-0.5">2</span>
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">รายการชำรุดประจำปี</h4>
                        <p class="text-[10px] text-slate-400">ครุภัณฑ์ที่ชำรุดในปีงบประมาณ</p>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="text-xs font-bold text-rose-600"><?= number_format((int)$stats['damaged']) ?></span>
                    <p class="text-[9px] text-slate-400">รายการ ></p>
                </div>
            </a>

            <a href="reports.php?status=degraded" class="flex items-center justify-between py-3 px-1 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-amber-500 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 mt-0.5">3</span>
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">สรุปผลการตรวจประจำปี</h4>
                        <p class="text-[10px] text-slate-400">สรุปผลการตรวจครุภัณฑ์ตามปีงบฯ</p>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="text-xs font-bold text-amber-600"><?= number_format((int)$stats['degraded']) ?></span>
                    <p class="text-[9px] text-slate-400">รายการ ></p>
                </div>
            </a>

            <a href="reports.php?type=dispose" class="flex items-center justify-between py-3 px-1 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-start gap-2.5">
                    <span class="w-6 h-6 rounded-lg bg-indigo-500 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 mt-0.5">4</span>
                    <div>
                        <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">รายการสูญไป/ไม่ใช้</h4>
                        <p class="text-[10px] text-slate-400">ครุภัณฑ์ที่สูญไป/ไม่ใช้ตัดจากบัญชี</p>
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="text-xs font-bold text-indigo-600"><?= number_format((int)$stats['lost']+(int)$stats['unused']) ?></span>
                    <p class="text-[9px] text-slate-400">รายการ ></p>
                </div>
            </a>
        </div>
    </div>

</div>

<!-- 4. BOTTOM SECTION: ตัวอย่างรายงาน (REPORT PREVIEW FOR PRINT/PDF) matching Screenshot 1 -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
    
    <!-- Preview Toolbar (Hidden when printing) -->
    <div class="p-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3 no-print">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="fa-regular fa-file-lines text-sm"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-800 text-sm">ตัวอย่างรายงาน</h3>
                <p class="text-[11px] text-slate-400">แสดงตัวอย่างรายงานก่อนพิมพ์หรือส่งออกไฟล์</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 text-xs text-slate-600">
                <span>ขนาดกระดาษ</span>
                <select class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-700 text-xs">
                    <option>A4</option>
                    <option>Letter</option>
                </select>
            </div>

            <div class="flex items-center gap-2 text-xs text-slate-600">
                <span>แนวกระดาษ</span>
                <select class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-700 text-xs">
                    <option>แนวตั้ง (Portrait)</option>
                    <option>แนวนอน (Landscape)</option>
                </select>
            </div>

            <button onclick="printReport()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-medium transition-all">
                <i class="fa-solid fa-print text-xs"></i>
                <span>พิมพ์รายงาน</span>
            </button>
        </div>
    </div>

    <!-- Official Document Sheet Container -->
    <div class="p-6 bg-slate-100/50 flex justify-center">
        <div id="printable-report" class="w-full max-w-4xl bg-white rounded-xl border border-slate-200 shadow-md p-8 text-slate-800 space-y-6">
            
            <!-- Ministry / Garuda Emblem Header -->
            <div class="text-center space-y-1">
                <div class="w-20 h-20 mx-auto mb-2 flex items-center justify-center">
                    <img src="<?= htmlspecialchars($logo_url) ?>" 
                         alt="ตราสัญลักษณ์ / ตราครุฑ" 
                         class="h-20 max-w-full object-contain drop-shadow-xs"
                         onerror="this.src='assets/img/dole_logo.png'">
                </div>
                <h2 class="text-lg font-bold text-slate-900 leading-tight"><?= htmlspecialchars($settings['org_name'] ?? 'สำนักงานส่งเสริมการเรียนรู้ระดับอำเภอเสนา') ?></h2>
                <h3 class="text-base font-semibold text-slate-800">
                    <?= $report_type === 'damaged' ? 'รายงานครุภัณฑ์ชำรุด' : ($report_type === 'degraded' ? 'รายงานครุภัณฑ์เสื่อมคุณภาพ' : ($report_type === 'dispose' ? 'รายงานครุภัณฑ์สูญไป/ไม่ใช้' : 'รายงานทะเบียนครุภัณฑ์ทั้งหมด')) ?>
                </h3>
                <p class="text-xs text-slate-600">ประจำปีงบประมาณ พ.ศ. <?= $year ?></p>
                <p class="text-[11px] text-slate-500 font-light">ข้อมูล ณ วันที่ <?= date('j') ?> <?= ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][date('n')-1] ?> <?= (date('Y') + 543) ?></p>
            </div>

            <!-- Official Report Table -->
            <div class="overflow-x-auto border border-slate-300 rounded-lg">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-100 border-b border-slate-300 text-slate-700 text-[11px] font-semibold">
                        <tr>
                            <th class="py-2.5 px-2.5 text-center w-12 border-r border-slate-300">ลำดับที่</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">รหัสครุภัณฑ์</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">รายการครุภัณฑ์</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">ประเภท</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 whitespace-nowrap">สถานที่ใช้งาน</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">สถานะ</th>
                            <th class="py-2.5 px-3 border-r border-slate-300 text-center whitespace-nowrap">ปีงบฯ</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">มูลค่า (บาท)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 text-[11px]">
                        <?php if (empty($preview_items)): ?>
                            <tr>
                                <td colspan="8" class="py-6 text-center text-slate-400">ไม่พบรายการครุภัณฑ์ตามเงื่อนไขที่เลือก</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($preview_items as $idx => $r): 
                                $st = 'ใช้ได้';
                                $badge = 'bg-emerald-100 text-emerald-700';
                                if ($r['status_damaged']) {
                                    $st = 'ชำรุด';
                                    $badge = 'bg-rose-100 text-rose-700';
                                } elseif ($r['status_degraded']) {
                                    $st = 'เสื่อมคุณภาพ';
                                    $badge = 'bg-amber-100 text-amber-800';
                                }
                            ?>
                            <tr>
                                <td class="py-2 px-2.5 text-center border-r border-slate-200"><?= $r['item_number'] ?></td>
                                <td class="py-2 px-3 font-mono border-r border-slate-200"><?= htmlspecialchars($r['asset_code'] ?: '-') ?></td>
                                <td class="py-2 px-3 font-medium border-r border-slate-200"><?= htmlspecialchars($r['item_name']) ?></td>
                                <td class="py-2 px-3 border-r border-slate-200"><?= htmlspecialchars($r['category'] ?: 'ครุภัณฑ์สำนักงาน') ?></td>
                                <td class="py-2 px-3 border-r border-slate-200"><?= htmlspecialchars($r['location'] ?: 'สกร.อำเภอเสนา') ?></td>
                                <td class="py-2 px-3 text-center border-r border-slate-200"><span class="px-2 py-0.5 rounded-full text-[10px] font-medium <?= $badge ?>"><?= $st ?></span></td>
                                <td class="py-2 px-3 text-center border-r border-slate-200"><?= $r['fiscal_year'] ?></td>
                                <td class="py-2 px-3 text-right font-medium"><?= number_format((float)$r['price'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Signatures Section -->
            <div class="grid grid-cols-2 gap-8 pt-8 text-center text-xs">
                <div class="space-y-1">
                    <p>ลงชื่อ..........................................................ผู้จัดทำ</p>
                    <p class="font-medium text-slate-800">(<?= htmlspecialchars($settings['officer_name'] ?? 'นางสาวกมลวรรณ ใจดี') ?>)</p>
                    <p class="text-slate-500 text-[11px]"><?= htmlspecialchars($settings['officer_position'] ?? 'เจ้าหน้าที่พัสดุ') ?></p>
                </div>
                <div class="space-y-1">
                    <p>ลงชื่อ..........................................................ประธานกรรมการ</p>
                    <p class="font-medium text-slate-800">(<?= htmlspecialchars($settings['director_name'] ?? 'นายวุฒิพล กำมา') ?>)</p>
                    <p class="text-slate-500 text-[11px]"><?= htmlspecialchars($settings['director_position'] ?? 'ผู้อำนวยการ สกร.ระดับอำเภอเสนา') ?></p>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- CHARTS INITIALIZATION -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. YEARLY COMPARISON BAR CHART
    const ctxYear = document.getElementById('yearlyBarChart').getContext('2d');
    new Chart(ctxYear, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($r)=>'ปี '.$r['fiscal_year'],$yearly_stats),JSON_UNESCAPED_UNICODE) ?>,
            datasets: [
                {
                    label: 'ปกติ',
                    data: <?= json_encode(array_map(fn($r)=>(int)$r['usable'],$yearly_stats)) ?>,
                    backgroundColor: '#38bdf8',
                    borderRadius: 4
                },
                {
                    label: 'ชำรุด',
                    data: <?= json_encode(array_map(fn($r)=>(int)$r['damaged'],$yearly_stats)) ?>,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                },
                {
                    label: 'เสื่อมคุณภาพ',
                    data: <?= json_encode(array_map(fn($r)=>(int)$r['degraded'],$yearly_stats)) ?>,
                    backgroundColor: '#f59e0b',
                    borderRadius: 4
                },
                {
                    label: 'สูญไป/ไม่ใช้',
                    data: <?= json_encode(array_map(fn($r)=>(int)$r['other'],$yearly_stats)) ?>,
                    backgroundColor: '#818cf8',
                    borderRadius: 4
                }
            ]
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

    // 2. REPORT DONUT CHART
    const ctxDonut = document.getElementById('reportDonutChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['ใช้ได้', 'ชำรุด', 'เสื่อมคุณภาพ', 'สูญไป/ไม่ใช้'],
            datasets: [{
                data: <?= json_encode([(int)$stats['usable'],(int)$stats['damaged'],(int)$stats['degraded'],(int)$stats['lost']+(int)$stats['unused']]) ?>,
                backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#818cf8'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false }
            }
        }
    });

});

function printReport() {
    window.print();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
