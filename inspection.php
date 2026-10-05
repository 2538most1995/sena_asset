<?php
// inspection.php - บัญชีรายการตรวจประจำปี
$active_page = 'inspection';
$page_title = 'บัญชีรายการตรวจประจำปี';
$page_subtitle = 'บันทึกผลการตรวจครุภัณฑ์ประจำปีงบประมาณ และนำเข้ารายการปีก่อนหน้า';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/pagination.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/inspection_location.php';

$flash_msg = '';
$flash_type = 'success';

// Selected Fiscal Year (default 2568, or from GET/POST)
$year = (int)($_REQUEST['year'] ?? 2568);
if ($year < 2500 || $year > 2650) {
    $year = 2568;
}

// -------------------------------------------------------------
// POST ACTIONS: Copy from previous year, Add, Edit, Delete item
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'copy_from_year') {
        $source_year = (int)($_POST['source_year'] ?? 2568);
        $target_year = (int)($_POST['target_year'] ?? $year);
        $reset_status = !empty($_POST['reset_status']);

        try {
            // Check count in source year
            $stmt = $pdo->prepare("SELECT * FROM inspection_items WHERE fiscal_year = ? ORDER BY item_number ASC");
            $stmt->execute([$source_year]);
            $source_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($source_rows)) {
                $flash_msg = "ไม่พบรายการครุภัณฑ์ในปีก่อนหน้า (ปีงบประมาณ $source_year)";
                $flash_type = 'error';
            } else {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO inspection_items 
                    (item_number, item_name, asset_code, asset_id_code, status_usable, status_damaged, status_degraded, status_lost, status_unused, remarks, fiscal_year, location, inspector, category, price, is_overlap, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $copied_count = 0;
                $u = current_user();
                $current_inspector = $u ? $u['fullname'] : 'เจ้าหน้าที่พัสดุ';

                $pdo->beginTransaction();
                foreach ($source_rows as $sr) {
                    $usable = $reset_status ? 0 : (int)$sr['status_usable'];
                    $damaged = $reset_status ? 0 : (int)$sr['status_damaged'];
                    $degraded = $reset_status ? 0 : (int)$sr['status_degraded'];
                    $lost = $reset_status ? 0 : (int)$sr['status_lost'];
                    $unused = $reset_status ? 0 : (int)$sr['status_unused'];
                    $overlap = $reset_status ? 0 : (int)$sr['is_overlap'];
                    $rm = $reset_status ? '' : ($sr['remarks'] ?? '');

                    $insert_stmt->execute([
                        $sr['item_number'],
                        $sr['item_name'],
                        $sr['asset_code'],
                        $sr['asset_id_code'],
                        $usable,
                        $damaged,
                        $degraded,
                        $lost,
                        $unused,
                        $rm,
                        $target_year,
                        $sr['location'],
                        $current_inspector,
                        $sr['category'],
                        $sr['price'],
                        $overlap,
                        $sr['image_url']
                    ]);
                    $copied_count++;
                }
                $pdo->commit();

                $year = $target_year;
                $flash_msg = "นำเข้าและคัดลอกรายการตรวจครุภัณฑ์จำนวน $copied_count รายการ จากปีงบประมาณ $source_year มายังปีงบประมาณ $target_year เรียบร้อยแล้ว" . ($reset_status ? ' (รีเซ็ตสถานะเป็นรอตรวจนับใหม่)' : '');
                $flash_type = 'success';
            }
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash_msg = "เกิดข้อผิดพลาดในการคัดลอก: " . $e->getMessage();
            $flash_type = 'error';
        }

    } elseif ($action === 'create_item') {
        $item_name = trim($_POST['item_name'] ?? '');
        $asset_code = trim($_POST['asset_code'] ?? '');
        $asset_id_code = trim($_POST['asset_id_code'] ?? '');
        $category = trim($_POST['category'] ?? 'ครุภัณฑ์สำนักงาน');
        $location = trim($_POST['location'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $remarks = trim($_POST['remarks'] ?? '');
        $target_year = (int)($_POST['fiscal_year'] ?? $year);

        // Upload photo if present
        $uploaded_img = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $img_res = processAndSaveEquipmentImage($_FILES['image']);
            if ($img_res['success']) {
                $uploaded_img = $img_res['url'];
            }
        }

        if ($item_name !== '') {
            try {
                // Auto item number for target year
                $max_num = (int)$pdo->query("SELECT MAX(item_number) FROM inspection_items WHERE fiscal_year = $target_year")->fetchColumn();
                $new_num = $max_num + 1;

                $usable = ($status === 'usable') ? 1 : 0;
                $damaged = ($status === 'damaged') ? 1 : 0;
                $degraded = ($status === 'degraded') ? 1 : 0;
                $lost = ($status === 'lost') ? 1 : 0;
                $unused = ($status === 'unused') ? 1 : 0;

                $u = current_user();
                $inspector = $u ? $u['fullname'] : 'เจ้าหน้าที่พัสดุ';

                $stmt = $pdo->prepare("
                    INSERT INTO inspection_items 
                    (item_number, item_name, asset_code, asset_id_code, status_usable, status_damaged, status_degraded, status_lost, status_unused, remarks, fiscal_year, location, inspector, category, price, image_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$new_num, $item_name, $asset_code, $asset_id_code, $usable, $damaged, $degraded, $lost, $unused, $remarks, $target_year, $location, $inspector, $category, $price, $uploaded_img]);

                $year = $target_year;
                $flash_msg = "เพิ่มรายการตรวจครุภัณฑ์ '$item_name' สำหรับปีงบประมาณ $target_year เรียบร้อยแล้ว";
                $flash_type = 'success';
            } catch (\Throwable $e) {
                $flash_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $flash_type = 'error';
            }
        }

    } elseif ($action === 'update_item') {
        $id = (int)($_POST['id'] ?? 0);
        $item_name = trim($_POST['item_name'] ?? '');
        $asset_code = trim($_POST['asset_code'] ?? '');
        $asset_id_code = trim($_POST['asset_id_code'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        // Upload photo if present
        $uploaded_img = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $img_res = processAndSaveEquipmentImage($_FILES['image']);
            if ($img_res['success']) {
                $uploaded_img = $img_res['url'];
            }
        }

        if ($id > 0 && $item_name !== '') {
            try {
                if ($uploaded_img) {
                    $stmt = $pdo->prepare("UPDATE inspection_items SET item_name = ?, asset_code = ?, asset_id_code = ?, category = ?, location = ?, price = ?, remarks = ?, image_url = ? WHERE id = ?");
                    $stmt->execute([$item_name, $asset_code, $asset_id_code, $category, $location, $price, $remarks, $uploaded_img, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE inspection_items SET item_name = ?, asset_code = ?, asset_id_code = ?, category = ?, location = ?, price = ?, remarks = ? WHERE id = ?");
                    $stmt->execute([$item_name, $asset_code, $asset_id_code, $category, $location, $price, $remarks, $id]);
                }
                $flash_msg = "อัปเดตรายละเอียดรายการตรวจเรียบร้อยแล้ว";
                $flash_type = 'success';
            } catch (\Throwable $e) {
                $flash_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $flash_type = 'error';
            }
        }

    } elseif ($action === 'delete_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM inspection_items WHERE id = ?");
                $stmt->execute([$id]);
                $flash_msg = "ลบรายการตรวจครุภัณฑ์ออกจากบัญชีเรียบร้อยแล้ว";
                $flash_type = 'success';
            } catch (\Throwable $e) {
                $flash_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $flash_type = 'error';
            }
        }
    }
}

// -------------------------------------------------------------
// FILTER & QUERY ITEMS FOR CURRENT FISCAL YEAR
// -------------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$filter_loc = trim($_GET['loc'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$per_page = sena_page_limit($_GET['limit'] ?? 25);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$inspection_source=sena_inspection_source_sql();
$where = ["fiscal_year = ?"];
$params = [$year];

if ($search !== '') {
    $where[] = "(item_name LIKE ? OR asset_code LIKE ? OR effective_location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_loc !== '' && $filter_loc !== 'ทั้งหมด') {
    $where[] = "effective_location = ?";
    $params[] = $filter_loc;
}
if ($filter_status === 'usable') {
    $where[] = "status_usable = 1";
} elseif ($filter_status === 'damaged') {
    $where[] = "status_damaged = 1";
} elseif ($filter_status === 'degraded') {
    $where[] = "status_degraded = 1";
} elseif ($filter_status === 'lost') {
    $where[] = "status_lost = 1";
} elseif ($filter_status === 'unused') {
    $where[] = "status_unused = 1";
} elseif ($filter_status === 'overlap') {
    $where[] = "is_overlap = 1";
} elseif ($filter_status === 'pending') {
    $where[] = "(status_usable = 0 AND status_damaged = 0 AND status_degraded = 0 AND status_lost = 0 AND status_unused = 0)";
}

$where_sql = implode(' AND ', $where);

try {
    if (!$pdo) {
        throw new \Exception("Database connection not available");
    }
    // Total count for current filter
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $inspection_source WHERE $where_sql");
    $stmt->execute($params);
    $total_items = (int)$stmt->fetchColumn();
    $paging=sena_page_state($total_items,$per_page,$_GET['page']??1);
    $page=$paging['page'];$offset=$paging['offset'];

    // Paginated rows
    $stmt = $pdo->prepare("SELECT * FROM $inspection_source WHERE $where_sql ORDER BY item_number ASC, id ASC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats for CURRENT FISCAL YEAR
    $stats=$pdo->prepare("SELECT COUNT(*) total,COALESCE(SUM(status_usable),0) usable,COALESCE(SUM(status_damaged),0) damaged,COALESCE(SUM(status_degraded),0) degraded,COALESCE(SUM(status_lost),0) lost,COALESCE(SUM(status_unused),0) unused,COALESCE(SUM(is_overlap),0) overlap,COALESCE(SUM(status_usable=1 OR status_damaged=1 OR status_degraded=1 OR status_lost=1 OR status_unused=1),0) checked FROM inspection_items WHERE fiscal_year=?");
    $stats->execute([$year]);$s=$stats->fetch(PDO::FETCH_ASSOC);
    $stat_total=(int)$s['total'];$stat_usable=(int)$s['usable'];$stat_damaged=(int)$s['damaged'];
    $stat_degraded=(int)$s['degraded'];$stat_lost=(int)$s['lost'];$stat_unused=(int)$s['unused'];
    $stat_overlap=(int)$s['overlap'];$stat_checked=(int)$s['checked'];
    $stat_pending = max(0, $stat_total - $stat_checked);

    // Locations for dropdown
    $loc_stmt=$pdo->prepare("SELECT DISTINCT effective_location FROM $inspection_source WHERE fiscal_year=? AND effective_location IS NOT NULL ORDER BY effective_location");
    $loc_stmt->execute([$year]);$locations=$loc_stmt->fetchAll(PDO::FETCH_COLUMN);
    $category_options = $pdo->query("SELECT category_name FROM asset_categories ORDER BY category_name")->fetchAll(PDO::FETCH_COLUMN);

    // Distinct years in database
    $existing_years = $pdo->query("SELECT DISTINCT fiscal_year FROM inspection_items WHERE fiscal_year IS NOT NULL ORDER BY fiscal_year DESC")->fetchAll(PDO::FETCH_COLUMN);

} catch (\Throwable $e) {
    $total_items = 0;
    $stat_total = 0;
    $stat_usable = 0;
    $stat_damaged = 0;
    $stat_degraded = 0;
    $stat_lost = 0;
    $stat_unused = 0;
    $stat_overlap = 0;
    $stat_checked = 0;
    $stat_pending = 0;
    $items = [];
    $locations = ['ห้องคอมพิวเตอร์', 'ห้องธุรการ', 'ห้องผู้อำนวยการ', 'ห้องประชุม', 'ห้องพัสดุ'];
    $existing_years = [2568];
}

$pct_checked = $stat_total > 0 ? round(($stat_checked / $stat_total) * 100, 1) : 0;
$pct_usable = $stat_total > 0 ? round(($stat_usable / $stat_total) * 100, 1) : 0;
$pct_damaged = $stat_total > 0 ? round(($stat_damaged / $stat_total) * 100, 1) : 0;
$pct_degraded = $stat_total > 0 ? round(($stat_degraded / $stat_total) * 100, 1) : 0;
$pct_lost = $stat_total > 0 ? round(($stat_lost / $stat_total) * 100, 1) : 0;
$pct_unused = $stat_total > 0 ? round(($stat_unused / $stat_total) * 100, 1) : 0;
$pct_pending = $stat_total > 0 ? round(($stat_pending / $stat_total) * 100, 1) : 0;

$year_options = array_unique(array_merge([2571, 2570, 2569, 2568, 2567], $existing_years));
rsort($year_options);

$total_pages = ceil($total_items / $per_page);
if ($total_pages < 1) $total_pages = 1;

include __DIR__ . '/includes/header.php';
?>

<p class="text-xs text-slate-500 px-1">สถานที่ที่บันทึกในบัญชีตรวจจะแสดงตามเดิม ช่องว่างจะอ้างอิงทะเบียนปัจจุบันเฉพาะรหัสที่ตรงและมีสถานที่เดียว ไม่ใช่การยืนยันสถานที่ย้อนหลัง หากจับคู่ไม่ได้จะแสดงว่ายังไม่ระบุสถานที่</p>

<!-- FLASH ALERT -->
<?php if ($flash_msg): ?>
<div class="p-4 rounded-xl <?= $flash_type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?> text-xs flex items-center justify-between shadow-2xs">
    <div class="flex items-center gap-2.5">
        <i class="fa-solid <?= $flash_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600' ?> text-base"></i>
        <span><?= htmlspecialchars($flash_msg) ?></span>
    </div>
    <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600">
        <i class="fa-solid fa-xmark text-sm"></i>
    </button>
</div>
<?php endif; ?>

<!-- TOAST ALERT FOR AJAX UPDATES -->
<div id="toast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
    <div class="bg-slate-900 text-white px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 border border-slate-700 text-xs">
        <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
        <span id="toast-message">บันทึกข้อมูลเรียบร้อย</span>
    </div>
</div>

<!-- 1. TOP FILTER BAR & ANNUAL ROLLOVER ACTIONS -->
<div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs space-y-3">
    
    <div class="space-y-4">
        
        <!-- Filter Form -->
        <form method="GET" action="inspection.php" id="filter-form" class="sena-filter-grid"><label class="sena-filter-field"><span class="sena-filter-label">ปีงบประมาณ</span>
<select name="year" onchange="document.getElementById('filter-form').submit()"
                        class="sena-filter-control">
                    <?php foreach ($year_options as $y): ?>
                        <option value="<?= $y ?>" <?= $year === (int)$y ? 'selected' : '' ?>>พ.ศ. <?= $y ?></option>
                    <?php endforeach; ?>
                </select>
</label>
        <label class="sena-filter-field sena-filter-wide"><span class="sena-filter-label">สถานที่/ผู้รับผิดชอบ</span>
<select name="loc" onchange="document.getElementById('filter-form').submit()" class="sena-filter-control">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= htmlspecialchars($l) ?>" <?= $filter_loc === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                    <?php endforeach; ?>
                </select>
</label>
        <label class="sena-filter-field"><span class="sena-filter-label">สถานะ</span>
<select name="status" onchange="document.getElementById('filter-form').submit()" class="sena-filter-control">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>รอตรวจนับ</option>
                    <option value="usable" <?= $filter_status === 'usable' ? 'selected' : '' ?>>ใช้ได้</option>
                    <option value="damaged" <?= $filter_status === 'damaged' ? 'selected' : '' ?>>ชำรุด</option>
                    <option value="degraded" <?= $filter_status === 'degraded' ? 'selected' : '' ?>>เสื่อมคุณภาพ</option>
                    <option value="lost" <?= $filter_status === 'lost' ? 'selected' : '' ?>>สูญไป</option>
                    <option value="unused" <?= $filter_status === 'unused' ? 'selected' : '' ?>>ไม่ใช้</option>
                    <option value="overlap" <?= $filter_status === 'overlap' ? 'selected' : '' ?>>สถานะซ้ำซ้อน</option>
                </select>
</label>
        <label class="sena-filter-field sena-filter-full"><span class="sena-filter-label">ค้นหารายการตรวจ</span>
<input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="รหัสครุภัณฑ์ ชื่อรายการ หรือผู้รับผิดชอบ" class="sena-filter-control">
</label>
        <div class="sena-filter-actions"><button type="submit" class="sena-filter-submit">ค้นหา / กรอง</button><a href="inspection.php?year=<?= $year ?>" class="sena-filter-reset">ล้างตัวกรอง</a></div>
    </form>

        <!-- Right Action Buttons -->
        <div class="sena-filter-actions">
            
            <!-- BUTTON: Copy from Previous Year -->
            <button type="button" onclick="openCopyModal(<?= $year ?>)" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-all" title="คัดลอกรายการจากปีก่อนหน้ามาตรวจสอบ">
                <i class="fa-solid fa-copy text-xs"></i>
                <span>นำเข้าจากปีก่อนหน้า</span>
            </button>

            <!-- BUTTON: Add New Item -->
            <button type="button" onclick="openAddInspectionModal()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-all">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>เพิ่มรายการ</span>
            </button>

            <!-- Reports link -->
            <a href="reports.php?source=inspection&amp;year=<?= $year ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-medium transition-all">
                <i class="fa-solid fa-print text-xs text-slate-400"></i>
                <span>รายงาน</span>
            </a>
        </div>
    </div>
</div>

<!-- 2. MOBILE & FIELD PROGRESS BANNER -->
<div class="bg-gradient-to-br from-indigo-950 via-indigo-900 to-slate-900 rounded-2xl p-4 sm:p-5 text-white shadow-md border border-indigo-800/60">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-indigo-800/60">
        <div>
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    <i class="fa-solid fa-mobile-screen-button mr-1"></i> โหมดตรวจนับภาคสนาม
                </span>
                <span class="text-xs text-indigo-300">ปีงบประมาณ พ.ศ. <?= $year ?></span>
            </div>
            <h2 class="text-base sm:text-lg font-bold text-white mt-1">ความคืบหน้าการตรวจนับครุภัณฑ์</h2>
        </div>
        <div class="flex items-baseline gap-2 bg-white/10 backdrop-blur-xs px-3.5 py-1.5 rounded-xl border border-white/10 self-start sm:self-auto">
            <span class="text-2xl sm:text-3xl font-black text-emerald-400" id="stat-progress-pct"><?= $pct_checked ?>%</span>
            <span class="text-xs text-indigo-200">
                (<span id="stat-progress-checked"><?= number_format($stat_checked) ?></span>/<span id="stat-progress-total"><?= number_format($stat_total) ?></span> รายการ)
            </span>
        </div>
    </div>

    <!-- Multi-segmented Progress Bar -->
    <div class="mt-3">
        <div class="flex items-center justify-between text-[11px] text-indigo-200 mb-1.5 font-medium">
            <span>ตรวจแล้ว <b class="text-white" id="stat-bar-checked-text"><?= number_format($stat_checked) ?></b> รายการ</span>
            <span>รอตรวจอีก <b class="text-amber-300" id="stat-bar-pending-text"><?= number_format($stat_pending) ?></b> รายการ</span>
        </div>
        <div class="w-full h-3 bg-slate-950/60 rounded-full overflow-hidden flex p-0.5 border border-white/10">
            <div id="bar-usable" class="bg-emerald-500 h-full rounded-l-full transition-all duration-500" style="width: <?= $pct_usable ?>%" title="ใช้ได้: <?= $pct_usable ?>%"></div>
            <div id="bar-degraded" class="bg-amber-500 h-full transition-all duration-500" style="width: <?= $pct_degraded ?>%" title="เสื่อมคุณภาพ: <?= $pct_degraded ?>%"></div>
            <div id="bar-damaged" class="bg-rose-500 h-full transition-all duration-500" style="width: <?= $pct_damaged ?>%" title="ชำรุด: <?= $pct_damaged ?>%"></div>
            <div id="bar-lost" class="bg-sky-500 h-full transition-all duration-500" style="width: <?= $pct_lost ?>%" title="สูญไป: <?= $pct_lost ?>%"></div>
            <div id="bar-unused" class="bg-purple-500 h-full transition-all duration-500" style="width: <?= $pct_unused ?>%" title="ไม่ใช้: <?= $pct_unused ?>%"></div>
        </div>
    </div>

    <!-- Quick Filter Chips (Touch scrollable horizontal pill strip) -->
    <div class="flex items-center gap-1.5 mt-3.5 overflow-x-auto pb-1 no-scrollbar text-xs">
        <span class="text-[11px] text-indigo-300 flex-shrink-0 mr-1"><i class="fa-solid fa-filter text-[10px]"></i> กรองด่วน:</span>
        
        <a href="inspection.php?year=<?= $year ?>&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all <?= empty($filter_status) ? 'bg-white text-indigo-950 font-bold shadow-xs' : 'bg-white/10 text-white hover:bg-white/20' ?>">
            ทั้งหมด (<span id="chip-total"><?= number_format($stat_total) ?></span>)
        </a>

        <a href="inspection.php?year=<?= $year ?>&status=pending&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 <?= $filter_status === 'pending' ? 'bg-amber-400 text-amber-950 font-bold shadow-xs' : 'bg-amber-500/20 text-amber-200 border border-amber-400/30 hover:bg-amber-500/30' ?>">
            <i class="fa-regular fa-hourglass-half text-[10px]"></i>
            <span>รอตรวจ (<span id="chip-pending"><?= number_format($stat_pending) ?></span>)</span>
        </a>

        <a href="inspection.php?year=<?= $year ?>&status=usable&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 <?= $filter_status === 'usable' ? 'bg-emerald-400 text-emerald-950 font-bold shadow-xs' : 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30 hover:bg-emerald-500/30' ?>">
            <i class="fa-solid fa-check text-[10px]"></i>
            <span>ใช้ได้ (<span id="chip-usable"><?= number_format($stat_usable) ?></span>)</span>
        </a>

        <a href="inspection.php?year=<?= $year ?>&status=damaged&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 <?= $filter_status === 'damaged' ? 'bg-rose-500 text-white font-bold shadow-xs' : 'bg-rose-500/20 text-rose-200 border border-rose-400/30 hover:bg-rose-500/30' ?>">
            <i class="fa-solid fa-wrench text-[10px]"></i>
            <span>ชำรุด (<span id="chip-damaged"><?= number_format($stat_damaged) ?></span>)</span>
        </a>

        <a href="inspection.php?year=<?= $year ?>&status=degraded&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 <?= $filter_status === 'degraded' ? 'bg-amber-500 text-white font-bold shadow-xs' : 'bg-amber-500/20 text-amber-200 border border-amber-400/30 hover:bg-amber-500/30' ?>">
            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
            <span>เสื่อมฯ (<span id="chip-degraded"><?= number_format($stat_degraded) ?></span>)</span>
        </a>

        <a href="inspection.php?year=<?= $year ?>&status=lost&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 <?= $filter_status === 'lost' ? 'bg-sky-400 text-sky-950 font-bold shadow-xs' : 'bg-sky-500/20 text-sky-200 border border-sky-400/30 hover:bg-sky-500/30' ?>">
            <i class="fa-solid fa-question text-[10px]"></i>
            <span>สูญไป (<span id="chip-lost"><?= number_format($stat_lost) ?></span>)</span>
        </a>

        <a href="inspection.php?year=<?= $year ?>&status=unused&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>" 
           class="flex-shrink-0 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 <?= $filter_status === 'unused' ? 'bg-indigo-400 text-indigo-950 font-bold shadow-xs' : 'bg-indigo-500/20 text-indigo-200 border border-indigo-400/30 hover:bg-indigo-500/30' ?>">
            <i class="fa-solid fa-ban text-[10px]"></i>
            <span>ไม่ใช้ (<span id="chip-unused"><?= number_format($stat_unused) ?></span>)</span>
        </a>
    </div>
</div>

<!-- 3. TOP 6 STAT CARDS (Calculated for selected year) -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-3.5">
    
    <!-- 1. ตรวจแล้ว -->
    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs">
        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center mb-2">
            <i class="fa-solid fa-circle-check text-sm sm:text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ตรวจแล้ว</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-xl sm:text-2xl font-bold text-slate-800" id="stat-card-checked"><?= number_format($stat_checked) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-slate-400 mt-1 font-light">
            <?= $stat_total > 0 ? round(($stat_checked / $stat_total) * 100, 1) : 0 ?>% จากปี <?= $year ?>
        </p>
    </div>

    <!-- 2. รอการตรวจ -->
    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs">
        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
            <i class="fa-regular fa-hourglass-half text-sm sm:text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">รอการตรวจ</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-xl sm:text-2xl font-bold text-amber-600" id="stat-card-pending"><?= number_format($stat_pending) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-amber-600 mt-1 font-light">
            <?= $stat_total > 0 ? round(($stat_pending / $stat_total) * 100, 1) : 0 ?>% คงเหลือ
        </p>
    </div>

    <!-- 3. ใช้ได้ -->
    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs">
        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center mb-2">
            <i class="fa-solid fa-check text-sm sm:text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ใช้ได้</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-xl sm:text-2xl font-bold text-slate-800" id="stat-card-usable"><?= number_format($stat_usable) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-emerald-600 mt-1 font-medium"><?= $stat_total > 0 ? round(($stat_usable / $stat_total) * 100, 1) : 0 ?>%</p>
    </div>

    <!-- 4. ชำรุด -->
    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs">
        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center mb-2">
            <i class="fa-solid fa-wrench text-sm sm:text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">ชำรุด</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-xl sm:text-2xl font-bold text-slate-800" id="stat-card-damaged"><?= number_format($stat_damaged) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-rose-600 mt-1 font-medium"><?= $stat_total > 0 ? round(($stat_damaged / $stat_total) * 100, 1) : 0 ?>%</p>
    </div>

    <!-- 5. เสื่อมคุณภาพ -->
    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs">
        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
            <i class="fa-solid fa-triangle-exclamation text-sm sm:text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">เสื่อมคุณภาพ</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-xl sm:text-2xl font-bold text-slate-800" id="stat-card-degraded"><?= number_format($stat_degraded) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-amber-600 mt-1 font-medium"><?= $stat_total > 0 ? round(($stat_degraded / $stat_total) * 100, 1) : 0 ?>%</p>
    </div>

    <!-- 6. ข้อมูลต้องตรวจสอบ -->
    <div class="bg-white rounded-2xl p-3.5 sm:p-4 border border-slate-200/80 shadow-2xs">
        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center mb-2">
            <i class="fa-solid fa-circle-info text-sm sm:text-base"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium">สถานะซ้ำซ้อน</p>
        <div class="flex items-baseline gap-1 mt-0.5">
            <span class="text-xl sm:text-2xl font-bold text-rose-600" id="stat-card-overlap"><?= number_format($stat_overlap) ?></span>
            <span class="text-[11px] text-slate-400">รายการ</span>
        </div>
        <p class="text-[10px] text-rose-500 mt-1 font-medium"><?= $stat_total > 0 ? round(($stat_overlap / $stat_total) * 100, 1) : 0 ?>%</p>
    </div>

</div>

<!-- EMPTY STATE FOR SELECTED YEAR (when no items exist) -->
<?php if ($stat_total === 0): ?>
<div class="bg-white border-2 border-dashed border-indigo-200 rounded-3xl p-10 text-center space-y-4 shadow-sm">
    <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl mx-auto shadow-md shadow-indigo-100">
        <i class="fa-solid fa-calendar-plus"></i>
    </div>
    <div class="max-w-md mx-auto">
        <h3 class="text-base font-bold text-slate-800">ยังไม่มีบัญชีรายการตรวจ สำหรับปีงบประมาณ <?= $year ?></h3>
        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
            คุณสามารถนำเข้าหรือคัดลอกรายการครุภัณฑ์จากปีก่อนหน้า (เช่น ปี 2568) มาตรวจสอบอีกครั้งสำหรับปี <?= $year ?> ได้ทันที โดยระบบจะรีเซ็ตสถานะเป็นรอตรวจ หรือเพิ่ม/ลดรายการได้ตลอดเวลา
        </p>
    </div>
    <div class="flex items-center justify-center gap-3 pt-2 flex-wrap">
        <button type="button" onclick="openCopyModal(<?= $year ?>)" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-copy"></i>
            <span>คัดลอกรายการจากปีงบประมาณก่อนหน้า</span>
        </button>
        <button type="button" onclick="openAddInspectionModal()" class="px-5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold transition-all">
            <i class="fa-solid fa-plus text-indigo-600 mr-1"></i>
            <span>+ เพิ่มรายการใหม่ด้วยตนเอง</span>
        </button>
    </div>
</div>
<?php endif; ?>

<!-- 3. DATA CONTAINER (CARD VIEW & TABLE VIEW) -->
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden">
    
    <div class="p-3.5 sm:p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 flex-wrap">
            <i class="fa-solid fa-list-check text-indigo-600 text-sm"></i>
            <h3 class="font-bold text-slate-800 text-sm">รายการตรวจครุภัณฑ์ประจำปีงบประมาณ พ.ศ. <?= $year ?></h3>
            <span class="px-2 py-0.5 rounded-full text-[11px] bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100/80"><?= number_format($total_items) ?> รายการ</span>
        </div>
        
        <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-3">
            <!-- View Mode Switcher: Cards vs Table -->
            <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200/80 text-xs">
                <button type="button" id="btn-view-card" onclick="switchView('card')" 
                        class="px-2.5 sm:px-3 py-1 rounded-lg text-xs font-bold bg-white text-indigo-700 shadow-2xs flex items-center gap-1.5 transition-all">
                    <i class="fa-solid fa-address-card text-xs"></i>
                    <span>การ์ดตรวจนับ</span>
                </button>
                <button type="button" id="btn-view-table" onclick="switchView('table')" 
                        class="px-2.5 sm:px-3 py-1 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1.5 transition-all">
                    <i class="fa-solid fa-table-list text-xs"></i>
                    <span>ตาราง</span>
                </button>
            </div>

            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                <span class="hidden sm:inline">แสดง</span>
                <select onchange="window.location.href='inspection.php?page=1&limit='+this.value+'&search=<?= urlencode($search) ?>&loc=<?= urlencode($filter_loc) ?>&status=<?= urlencode($filter_status) ?>&year=<?= $year ?>'" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-slate-600">
                    <?php foreach(sena_page_sizes() as $size): ?><option value="<?=$size?>" <?=$per_page===$size?'selected':''?>><?=number_format($size)?> รายการ</option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- VIEW 1: SMARTPHONE & FIELD INSPECTION CARDS -->
    <div id="inspection-cards-container" class="p-3 sm:p-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5 sm:gap-4 bg-slate-50/60">
        <?php if (empty($items)): ?>
            <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                <i class="fa-solid fa-folder-open text-4xl mb-3 text-slate-300"></i>
                <p class="text-sm font-medium text-slate-600">
                    <?php if ($stat_total > 0): ?>
                        ไม่พบข้อมูลที่ตรงกับเงื่อนไขการค้นหา
                    <?php else: ?>
                        ยังไม่มีรายการตรวจสำหรับปี <?= $year ?>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-slate-400 mt-1">กดปุ่ม "นำเข้าจากปีก่อนหน้า" ด้านบนเพื่อเริ่มตรวจนับ</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $it): 
                $cur_status = 'pending';
                if ($it['status_usable']) $cur_status = 'usable';
                elseif ($it['status_damaged']) $cur_status = 'damaged';
                elseif ($it['status_degraded']) $cur_status = 'degraded';
                elseif ($it['status_lost']) $cur_status = 'lost';
                elseif ($it['status_unused']) $cur_status = 'unused';
                $has_overlap = (bool)($it['is_overlap'] ?? false);
                $has_img = !empty($it['image_url']);
            ?>
            <div class="inspection-card bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md transition-all p-3.5 sm:p-4 flex flex-col justify-between relative group" 
                 id="card-<?= $it['id'] ?>" data-id="<?= $it['id'] ?>" data-status="<?= $cur_status ?>">
                
                <!-- Top Row: Number, Asset Code & Status Badge -->
                <div>
                    <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-100">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 font-extrabold text-xs flex-shrink-0">
                                #<?= $it['item_number'] ?>
                            </span>
                            <span class="font-mono text-[11px] font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 px-2 py-0.5 rounded-md inline-flex items-center gap-1 cursor-pointer truncate transition-colors" 
                                  onclick="copyAssetCode('<?= htmlspecialchars($it['asset_code']) ?>', event)" title="แตะเพื่อคัดลอกรหัส">
                                <span id="code-text-<?= $it['id'] ?>"><?= htmlspecialchars($it['asset_code'] ?: '-') ?></span>
                                <i class="fa-regular fa-copy text-[10px] text-slate-400"></i>
                            </span>
                        </div>

                        <!-- Current Status Badge -->
                        <div id="card-badge-<?= $it['id'] ?>" class="flex-shrink-0">
                            <?php if ($has_overlap): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> ซ้ำซ้อน
                                </span>
                            <?php elseif ($cur_status === 'usable'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                    <i class="fa-solid fa-check text-[10px]"></i> ใช้ได้
                                </span>
                            <?php elseif ($cur_status === 'damaged'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                                    <i class="fa-solid fa-wrench text-[10px]"></i> ชำรุด
                                </span>
                            <?php elseif ($cur_status === 'degraded'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 border border-amber-200">
                                    <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> เสื่อมฯ
                                </span>
                            <?php elseif ($cur_status === 'lost'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-sky-100 text-sky-700 border border-sky-200">
                                    <i class="fa-solid fa-question text-[10px]"></i> สูญไป
                                </span>
                            <?php elseif ($cur_status === 'unused'): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-700 border border-indigo-200">
                                    <i class="fa-solid fa-ban text-[10px]"></i> ไม่ใช้
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-500 border border-slate-200">
                                    <i class="fa-regular fa-hourglass-half text-[10px]"></i> รอตรวจ
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle Row: Photo + Asset Details -->
                    <div class="flex items-start gap-3 pt-3">
                        <!-- Thumbnail / Camera Trigger -->
                        <div class="relative w-16 h-16 sm:w-18 sm:h-18 rounded-xl overflow-hidden flex-shrink-0 bg-slate-50 border border-slate-200 group-hover:border-indigo-300 transition-colors" id="card-photo-wrapper-<?= $it['id'] ?>">
                            <?php if ($has_img): ?>
                                <img id="card-img-<?= $it['id'] ?>" src="<?= htmlspecialchars($it['image_url']) ?>" alt="รูปครุภัณฑ์" class="w-full h-full object-cover cursor-pointer" onclick='openPhotoModal(<?= $it['id'] ?>, <?= json_encode($it['item_name']) ?>, <?= json_encode($it['image_url']) ?>)'>
                                <button type="button" onclick="triggerCardCamera(<?= $it['id'] ?>)" class="absolute bottom-0 right-0 bg-slate-900/70 text-white w-5 h-5 rounded-tl-lg flex items-center justify-center text-[9px] hover:bg-indigo-600 transition-colors" title="ถ่ายรูปใหม่">
                                    <i class="fa-solid fa-camera"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="triggerCardCamera(<?= $it['id'] ?>)" class="w-full h-full flex flex-col items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-50/50 transition-colors gap-1" title="แตะเพื่อเปิดกล้องมือถือถ่ายภาพทันที">
                                    <i class="fa-solid fa-camera text-base"></i>
                                    <span class="text-[9px] font-medium">ถ่ายรูป</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Asset Text Info -->
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-slate-800 text-sm leading-snug line-clamp-2" title="<?= htmlspecialchars($it['item_name']) ?>">
                                <?= htmlspecialchars($it['item_name']) ?>
                            </h4>

                            <div class="flex items-center gap-1 text-[11px] text-slate-500 mt-1">
                                <i class="fa-solid fa-location-dot text-rose-500 text-[10px] flex-shrink-0"></i>
                                <span class="truncate" title="<?= htmlspecialchars(sena_inspection_location_origin($it)) ?>"><?= htmlspecialchars(sena_inspection_location_label($it)) ?></span>
                            </div>

                            <?php if (!empty($it['asset_id_code'])): ?>
                                <div class="text-[10px] text-slate-400 mt-0.5 truncate font-mono">
                                    ID: <?= htmlspecialchars($it['asset_id_code']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Remarks preview if present -->
                            <div id="card-remarks-container-<?= $it['id'] ?>" class="<?= !empty($it['remarks']) ? '' : 'hidden' ?> mt-1.5">
                                <p class="text-[11px] text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200/60 inline-flex items-center gap-1 max-w-full truncate">
                                    <i class="fa-regular fa-comment-dots text-[10px] flex-shrink-0"></i>
                                    <span id="card-remarks-text-<?= $it['id'] ?>" class="truncate"><?= htmlspecialchars($it['remarks'] ?? '') ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: 5 Thumb-Friendly Touch Status Buttons -->
                <div>
                    <div class="grid grid-cols-5 gap-1 pt-3 mt-3 border-t border-slate-100" id="card-btn-group-<?= $it['id'] ?>">
                        
                        <!-- 1. ใช้ได้ -->
                        <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'usable', <?= $it['item_number'] ?>)" 
                                class="card-btn-usable min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 <?= $cur_status === 'usable' ? 'bg-emerald-600 text-white font-bold shadow-sm ring-2 ring-emerald-300' : 'bg-slate-50 hover:bg-emerald-50 text-slate-600 border border-slate-200' ?>"
                                title="บันทึก: ใช้ได้">
                            <i class="fa-solid fa-check text-xs <?= $cur_status === 'usable' ? 'text-white' : 'text-emerald-500' ?>"></i>
                            <span class="text-[10px] leading-tight">ใช้ได้</span>
                        </button>

                        <!-- 2. เสื่อมฯ -->
                        <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'degraded', <?= $it['item_number'] ?>)" 
                                class="card-btn-degraded min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 <?= $cur_status === 'degraded' ? 'bg-amber-500 text-white font-bold shadow-sm ring-2 ring-amber-300' : 'bg-slate-50 hover:bg-amber-50 text-slate-600 border border-slate-200' ?>"
                                title="บันทึก: เสื่อมคุณภาพ">
                            <i class="fa-solid fa-triangle-exclamation text-xs <?= $cur_status === 'degraded' ? 'text-white' : 'text-amber-500' ?>"></i>
                            <span class="text-[10px] leading-tight">เสื่อมฯ</span>
                        </button>

                        <!-- 3. ชำรุด -->
                        <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'damaged', <?= $it['item_number'] ?>)" 
                                class="card-btn-damaged min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 <?= $cur_status === 'damaged' ? 'bg-rose-600 text-white font-bold shadow-sm ring-2 ring-rose-300' : 'bg-slate-50 hover:bg-rose-50 text-slate-600 border border-slate-200' ?>"
                                title="บันทึก: ชำรุด">
                            <i class="fa-solid fa-wrench text-xs <?= $cur_status === 'damaged' ? 'text-white' : 'text-rose-500' ?>"></i>
                            <span class="text-[10px] leading-tight">ชำรุด</span>
                        </button>

                        <!-- 4. สูญไป -->
                        <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'lost', <?= $it['item_number'] ?>)" 
                                class="card-btn-lost min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 <?= $cur_status === 'lost' ? 'bg-sky-600 text-white font-bold shadow-sm ring-2 ring-sky-300' : 'bg-slate-50 hover:bg-sky-50 text-slate-600 border border-slate-200' ?>"
                                title="บันทึก: สูญไป">
                            <i class="fa-solid fa-circle-question text-xs <?= $cur_status === 'lost' ? 'text-white' : 'text-sky-500' ?>"></i>
                            <span class="text-[10px] leading-tight">สูญไป</span>
                        </button>

                        <!-- 5. ไม่ใช้ -->
                        <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'unused', <?= $it['item_number'] ?>)" 
                                class="card-btn-unused min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 <?= $cur_status === 'unused' ? 'bg-indigo-600 text-white font-bold shadow-sm ring-2 ring-indigo-300' : 'bg-slate-50 hover:bg-indigo-50 text-slate-600 border border-slate-200' ?>"
                                title="บันทึก: ไม่ใช้">
                            <i class="fa-solid fa-ban text-xs <?= $cur_status === 'unused' ? 'text-white' : 'text-indigo-500' ?>"></i>
                            <span class="text-[10px] leading-tight">ไม่ใช้</span>
                        </button>

                    </div>

                    <!-- Footer Action Strip -->
                    <div class="flex items-center justify-between pt-2.5 mt-2 border-t border-slate-100 text-xs text-slate-500">
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="triggerCardCamera(<?= $it['id'] ?>)" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[11px] font-medium transition-colors">
                                <i class="fa-solid fa-camera text-[10px]"></i>
                                <span>ถ่ายรูป</span>
                            </button>
                            <button type="button" onclick="openQuickRemarkModal(<?= $it['id'] ?>, <?= json_encode($it['item_name']) ?>, <?= json_encode($it['remarks'] ?? '') ?>)" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-medium transition-colors">
                                <i class="fa-solid fa-pencil text-[10px]"></i>
                                <span>หมายเหตุ</span>
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-0.5">
                            <button type="button" onclick='openEditInspectionModal(<?= json_encode($it, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-slate-100 transition-colors" title="แก้ไขรายการ">
                                <i class="fa-solid fa-pen text-xs"></i>
                            </button>
                            <form method="POST" action="inspection.php?year=<?= $year ?>" class="inline" onsubmit="return confirm('ต้องการลบรายการ \'<?= htmlspecialchars(addslashes($it['item_name'])) ?>\' ใช่หรือไม่?')">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="id" value="<?= $it['id'] ?>">
                                <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="ลบรายการ">
                                    <i class="fa-regular fa-trash-can text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- VIEW 2: TRADITIONAL DESKTOP DATA TABLE -->
    <div id="inspection-table-container" class="hidden overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-500 uppercase border-b border-slate-200/60">
                <tr>
                    <th class="py-3 px-3 w-12 text-center">ลำดับ</th>
                    <th class="py-3 px-3 w-10 text-center">รูป</th>
                    <th class="py-3 px-3 whitespace-nowrap">รหัสครุภัณฑ์</th>
                    <th class="py-3 px-3 whitespace-nowrap">รายการ</th>
                    <th class="py-3 px-3 whitespace-nowrap">สถานที่/ผู้รับผิดชอบ</th>
                    <th class="py-3 px-2 text-center text-emerald-600 whitespace-nowrap">ใช้ได้</th>
                    <th class="py-3 px-2 text-center text-rose-600 whitespace-nowrap">ชำรุด</th>
                    <th class="py-3 px-2 text-center text-amber-600 whitespace-nowrap">เสื่อมคุณภาพ</th>
                    <th class="py-3 px-2 text-center text-sky-600 whitespace-nowrap">สูญไป</th>
                    <th class="py-3 px-2 text-center text-indigo-600 whitespace-nowrap">ไม่ใช้</th>
                    <th class="py-3 px-3 whitespace-nowrap">หมายเหตุ</th>
                    <th class="py-3 px-3 whitespace-nowrap">ผู้ตรวจ</th>
                    <th class="py-3 px-3 text-center whitespace-nowrap">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="13" class="py-8 text-center text-slate-400">
                            <?php if ($stat_total > 0): ?>
                                ไม่พบข้อมูลที่ตรงกับเงื่อนไขการค้นหา
                            <?php else: ?>
                                ยังไม่มีรายการตรวจสำหรับปี <?= $year ?> (กดปุ่ม "นำเข้าจากปีก่อนหน้า" ด้านบนเพื่อเริ่มตรวจ)
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $it): 
                        $has_overlap = (bool)($it['is_overlap'] ?? false);
                        $has_img = !empty($it['image_url']);
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors" id="row-<?= $it['id'] ?>">
                        <td class="py-3 px-3 text-center font-medium text-slate-400"><?= $it['item_number'] ?></td>
                        
                        <!-- Photo column -->
                        <td class="py-3 px-2 text-center" id="table-photo-cell-<?= $it['id'] ?>">
                            <?php if ($has_img): ?>
                                <button type="button" onclick='openPhotoModal(<?= $it['id'] ?>, <?= json_encode($it['item_name']) ?>, <?= json_encode($it['image_url']) ?>)' 
                                        class="w-7 h-7 rounded-lg overflow-hidden border border-indigo-200 inline-block hover:scale-110 transition-transform shadow-2xs" title="คลิกดู / เปลี่ยนรูป">
                                    <img src="<?= htmlspecialchars($it['image_url']) ?>" alt="ครุภัณฑ์" class="w-full h-full object-cover">
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="triggerCardCamera(<?= $it['id'] ?>)" 
                                        class="w-7 h-7 rounded-lg border border-dashed border-slate-300 text-slate-400 hover:text-indigo-600 hover:border-indigo-400 hover:bg-indigo-50 inline-flex items-center justify-center text-[10px] transition-colors" title="ถ่ายรูป / แนบรูปภาพ">
                                    <i class="fa-solid fa-camera"></i>
                                </button>
                            <?php endif; ?>
                        </td>

                        <td class="py-3 px-3 font-mono font-medium text-slate-800 whitespace-nowrap">
                            <span class="cursor-pointer hover:text-indigo-600" onclick="copyAssetCode('<?= htmlspecialchars($it['asset_code']) ?>', event)" title="แตะเพื่อคัดลอก"><?= htmlspecialchars($it['asset_code'] ?: '-') ?></span>
                        </td>
                        <td class="py-3 px-3 font-medium text-slate-800">
                            <span><?= htmlspecialchars($it['item_name']) ?></span>
                            <?php if ($has_img): ?>
                                <span class="text-[10px] text-indigo-600 ml-1"><i class="fa-solid fa-paperclip"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-3 text-slate-600 whitespace-nowrap"><?= htmlspecialchars(sena_inspection_location_label($it)) ?><small class="block text-[10px] text-slate-400"><?= htmlspecialchars(sena_inspection_location_origin($it)) ?></small></td>
                        
                        <!-- Status Columns -->
                        <td class="py-3 px-2 text-center" id="td-status-<?= $it['id'] ?>-usable">
                            <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'usable', <?= $it['item_number'] ?>)" class="status-btn" title="กำหนดเป็น: ใช้ได้">
                                <?php if ($it['status_usable']): ?>
                                    <span class="w-5 h-5 rounded-md bg-emerald-500 text-white inline-flex items-center justify-center text-[10px] hover:scale-110 transition-transform">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="w-5 h-5 rounded-md border border-slate-200 text-slate-300 inline-flex items-center justify-center text-[10px] hover:border-emerald-400 hover:text-emerald-500 transition-colors">
                                        -
                                    </span>
                                <?php endif; ?>
                            </button>
                        </td>

                        <td class="py-3 px-2 text-center" id="td-status-<?= $it['id'] ?>-damaged">
                            <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'damaged', <?= $it['item_number'] ?>)" class="status-btn" title="กำหนดเป็น: ชำรุด">
                                <?php if ($it['status_damaged']): ?>
                                    <span class="w-5 h-5 rounded-md bg-rose-500 text-white inline-flex items-center justify-center text-[10px] hover:scale-110 transition-transform">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="w-5 h-5 rounded-md border border-slate-200 text-slate-300 inline-flex items-center justify-center text-[10px] hover:border-rose-400 hover:text-rose-500 transition-colors">
                                        -
                                    </span>
                                <?php endif; ?>
                            </button>
                        </td>

                        <td class="py-3 px-2 text-center" id="td-status-<?= $it['id'] ?>-degraded">
                            <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'degraded', <?= $it['item_number'] ?>)" class="status-btn" title="กำหนดเป็น: เสื่อมคุณภาพ">
                                <?php if ($it['status_degraded']): ?>
                                    <span class="w-5 h-5 rounded-md bg-amber-500 text-white inline-flex items-center justify-center text-[10px] hover:scale-110 transition-transform">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="w-5 h-5 rounded-md border border-slate-200 text-slate-300 inline-flex items-center justify-center text-[10px] hover:border-amber-400 hover:text-amber-500 transition-colors">
                                        -
                                    </span>
                                <?php endif; ?>
                            </button>
                        </td>

                        <td class="py-3 px-2 text-center" id="td-status-<?= $it['id'] ?>-lost">
                            <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'lost', <?= $it['item_number'] ?>)" class="status-btn" title="กำหนดเป็น: สูญไป">
                                <?php if ($it['status_lost']): ?>
                                    <span class="w-5 h-5 rounded-md bg-sky-500 text-white inline-flex items-center justify-center text-[10px]">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="w-5 h-5 rounded-md border border-slate-200 text-slate-300 inline-flex items-center justify-center text-[10px]">
                                        -
                                    </span>
                                <?php endif; ?>
                            </button>
                        </td>

                        <td class="py-3 px-2 text-center" id="td-status-<?= $it['id'] ?>-unused">
                            <button type="button" onclick="setInspectionStatus(<?= $it['id'] ?>, 'unused', <?= $it['item_number'] ?>)" class="status-btn" title="กำหนดเป็น: ไม่ใช้">
                                <?php if ($it['status_unused']): ?>
                                    <span class="w-5 h-5 rounded-md bg-indigo-500 text-white inline-flex items-center justify-center text-[10px]">
                                        <i class="fa-solid fa-check"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="w-5 h-5 rounded-md border border-slate-200 text-slate-300 inline-flex items-center justify-center text-[10px]">
                                        -
                                    </span>
                                <?php endif; ?>
                            </button>
                        </td>

                        <!-- Remarks & Overlap Badge -->
                        <td class="py-3 px-3">
                            <?php if ($has_overlap): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-200">
                                    <i class="fa-solid fa-triangle-exclamation text-[9px]"></i>
                                    <span>สถานะซ้ำซ้อน</span>
                                </span>
                            <?php endif; ?>
                            <span id="table-remarks-<?= $it['id'] ?>" class="text-slate-500 text-[11px] ml-1">
                                <?= !empty($it['remarks']) ? htmlspecialchars($it['remarks']) : ($has_overlap ? '' : '-') ?>
                            </span>
                        </td>

                        <td class="py-3 px-3 whitespace-nowrap text-slate-600">
                            <?= htmlspecialchars($it['inspector'] ?: '-') ?>
                        </td>

                        <!-- Actions (Edit, Photo, Delete) -->
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <button type="button" onclick='openEditInspectionModal(<?= json_encode($it, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' 
                                        class="p-1 rounded-md text-slate-400 hover:text-indigo-600 hover:bg-slate-100" title="แก้ไขรายการ">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button type="button" onclick='openPhotoModal(<?= $it['id'] ?>, <?= json_encode($it['item_name']) ?>, <?= json_encode($it['image_url'] ?? "") ?>)' 
                                        class="p-1 rounded-md text-slate-400 hover:text-purple-600 hover:bg-slate-100" title="แนบ / ดูรูปภาพ">
                                    <i class="fa-solid fa-camera text-xs"></i>
                                </button>
                                <form method="POST" action="inspection.php?year=<?= $year ?>" class="inline" onsubmit="return confirm('ต้องการลบรายการ \'<?= htmlspecialchars(addslashes($it['item_name'])) ?>\' ใช่หรือไม่?')">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="id" value="<?= $it['id'] ?>">
                                    <button type="submit" class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50" title="ลบรายการ">
                                        <i class="fa-regular fa-trash-can text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php sena_render_pagination($total_items,$per_page,$page,$_GET); ?>

</div>

<!-- HIDDEN CAMERA INPUT FOR DIRECT SMARTPHONE CAPTURE -->
<input type="file" id="card-camera-input" accept="image/*" capture="environment" class="hidden" onchange="uploadCardPhoto(this)">

<!-- QUICK REMARK MODAL FOR ON-THE-GO NOTE TAKING -->
<div id="quick-remark-modal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4 border border-slate-100">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-comment-dots"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm">บันทึกหมายเหตุ</h3>
                    <p class="text-[11px] text-slate-400" id="quick-remark-item-name">รายการครุภัณฑ์</p>
                </div>
            </div>
            <button type="button" onclick="closeQuickRemarkModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <input type="hidden" id="quick-remark-id" value="0">
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">รายละเอียด / สภาพที่พบ</label>
            <textarea id="quick-remark-text" rows="3" placeholder="ระบุเหตุผล เช่น ใบพัดชำรุด, รอซ่อมแซม, ปลดระวาง..." 
                      class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:ring-2 focus:ring-indigo-500 text-slate-800"></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <button type="button" onclick="closeQuickRemarkModal()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-medium hover:bg-slate-50">
                ยกเลิก
            </button>
            <button type="button" onclick="saveQuickRemark()" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs">
                บันทึกหมายเหตุ
            </button>
        </div>
    </div>
</div>

<!-- MOBILE FLOATING ACTION BAR -->
<div id="mobile-action-bar" class="fixed bottom-4 left-4 right-4 z-40 md:hidden bg-slate-900/90 backdrop-blur-md text-white rounded-2xl p-2.5 shadow-2xl border border-slate-700/80 flex items-center justify-between">
    <div class="flex items-center gap-2 pl-2">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
        <div class="text-[11px] leading-tight">
            <div class="font-semibold text-slate-200">ปี พ.ศ. <?= $year ?></div>
            <div class="text-[10px] text-amber-300">รอตรวจอีก <span id="mobile-bar-pending"><?= number_format($stat_pending) ?></span> รายการ</div>
        </div>
    </div>
    <div class="flex items-center gap-1.5">
        <button type="button" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="px-2.5 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-medium flex items-center gap-1">
            <i class="fa-solid fa-arrow-up text-[10px]"></i>
            <span>บนสุด</span>
        </button>
        <button type="button" onclick="openAddInspectionModal()" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold flex items-center gap-1 shadow-sm">
            <i class="fa-solid fa-plus text-[10px]"></i>
            <span>เพิ่ม</span>
        </button>
    </div>
</div>

<!-- 4. BOTTOM SECTION: DONUT SUMMARY & ANOMALIES -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- LEFT: สรุปผลการตรวจ (lg:col-span-6) -->
    <div class="lg:col-span-6 bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">สรุปผลการตรวจนับ ปีงบประมาณ <?= $year ?></h3>
            </div>
            <span class="text-xs text-slate-400 font-medium">รวม <?= number_format($stat_total) ?> รายการ</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-12 items-center gap-4 pt-4">
            <!-- Donut -->
            <div class="sm:col-span-5 relative w-40 h-40 mx-auto">
                <canvas id="inspectDonutChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center">
                    <span class="text-xl font-extrabold text-slate-800 leading-tight"><?= number_format($stat_checked) ?></span>
                    <span class="text-[10px] text-slate-400">ตรวจแล้ว</span>
                    <span class="text-[10px] text-emerald-600 font-semibold"><?= $stat_total > 0 ? round(($stat_checked / $stat_total) * 100, 1) : 0 ?>%</span>
                </div>
            </div>

            <!-- Stats Table -->
            <div class="sm:col-span-7">
                <table class="w-full text-xs text-left">
                    <thead class="text-[10px] text-slate-400 uppercase border-b border-slate-100">
                        <tr>
                            <th class="pb-1.5 font-semibold">สถานะ</th>
                            <th class="pb-1.5 font-semibold text-right">จำนวน</th>
                            <th class="pb-1.5 font-semibold text-right">ร้อยละ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr>
                            <td class="py-1.5 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[#10b981]"></span>
                                <span class="text-slate-700">ใช้ได้</span>
                            </td>
                            <td class="py-1.5 text-right font-medium text-slate-800"><?= number_format($stat_usable) ?></td>
                            <td class="py-1.5 text-right text-slate-400"><?= $stat_total > 0 ? round(($stat_usable / $stat_total) * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[#ef4444]"></span>
                                <span class="text-slate-700">ชำรุด</span>
                            </td>
                            <td class="py-1.5 text-right font-medium text-slate-800"><?= number_format($stat_damaged) ?></td>
                            <td class="py-1.5 text-right text-slate-400"><?= $stat_total > 0 ? round(($stat_damaged / $stat_total) * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-[#f59e0b]"></span>
                                <span class="text-slate-700">เสื่อมคุณภาพ</span>
                            </td>
                            <td class="py-1.5 text-right font-medium text-slate-800"><?= number_format($stat_degraded) ?></td>
                            <td class="py-1.5 text-right text-slate-400"><?= $stat_total > 0 ? round(($stat_degraded / $stat_total) * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                                <span class="text-slate-700">รอการตรวจ</span>
                            </td>
                            <td class="py-1.5 text-right font-medium text-amber-600"><?= number_format($stat_pending) ?></td>
                            <td class="py-1.5 text-right text-slate-400"><?= $stat_total > 0 ? round(($stat_pending / $stat_total) * 100, 1) : 0 ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: ข้อมูลการตรวจประจำปี -->
    <div class="lg:col-span-6 bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs space-y-3">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">การจัดการปีงบประมาณและข้อมูล</h3>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-slate-400 text-[11px]">ปีงบประมาณปัจจุบันที่เลือก</span>
                <p class="font-bold text-slate-800 text-base mt-0.5">พ.ศ. <?= $year ?></p>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-slate-400 text-[11px]">รายการทั้งหมดในบัญชี</span>
                <p class="font-bold text-indigo-600 text-base mt-0.5"><?= number_format($stat_total) ?> รายการ</p>
            </div>
        </div>

        <div class="pt-2 flex flex-col gap-2">
            <button type="button" onclick="openCopyModal(<?= $year ?>)" class="w-full py-2.5 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold border border-indigo-200 text-center transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-copy"></i>
                <span>คัดลอกรายการจากปีก่อนหน้ามาตรวจสอบ</span>
            </button>
            <a href="reports.php?year=<?= $year ?>&export=excel" class="w-full py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200 text-center transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-export text-emerald-600"></i>
                <span>ส่งออกรายงานปี <?= $year ?> เป็น Excel</span>
            </a>
        </div>
    </div>

</div>

<!-- MODAL 1: คัดลอกรายการจากปีก่อนหน้า (ROLLOVER MODAL) -->
<div id="copy-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-copy"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-sm">นำเข้ารายการจากปีงบประมาณก่อนหน้า</h3>
            </div>
            <button onclick="closeCopyModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="inspection.php" class="p-5 space-y-4 text-xs">
            <input type="hidden" name="action" value="copy_from_year">

            <div>
                <label class="block text-slate-600 font-medium mb-1">คัดลอกข้อมูลจากปีงบประมาณต้นทาง *</label>
                <select name="source_year" class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 font-semibold focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($existing_years as $ey): ?>
                        <option value="<?= $ey ?>" <?= (int)$ey === 2568 ? 'selected' : '' ?>>ปีงบประมาณ พ.ศ. <?= $ey ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">ไปยังปีงบประมาณเป้าหมาย *</label>
                <input type="number" name="target_year" id="copy-target-year" value="<?= $year ?>" required 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 font-bold focus:ring-2 focus:ring-indigo-500">
                <p class="text-[10px] text-slate-400 mt-1">เช่น 2570, 2569</p>
            </div>

            <div class="p-3 rounded-xl bg-amber-50 border border-amber-200/80 space-y-2">
                <label class="flex items-start gap-2.5 cursor-pointer text-amber-900">
                    <input type="checkbox" name="reset_status" value="1" checked class="mt-0.5 rounded border-amber-300 text-indigo-600 focus:ring-0">
                    <div>
                        <p class="font-semibold text-xs leading-tight">รีเซ็ตสถานะการตรวจทั้งหมด</p>
                        <p class="text-[11px] text-amber-700 font-light mt-0.5">ล้างเครื่องหมายเช็คถูก เพื่อเตรียมพร้อมสำหรับการตรวจนับใหม่ประจำปีงบประมาณเป้าหมาย</p>
                    </div>
                </label>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeCopyModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">
                    ยกเลิก
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-xs transition-colors">
                    ยืนยันนำเข้ารายการ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: เพิ่มรายการตรวจใหม่ (ADD INSPECTION ITEM) -->
<div id="add-inspect-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-sm">เพิ่มรายการตรวจครุภัณฑ์ใหม่</h3>
            </div>
            <button onclick="closeAddInspectionModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="inspection.php" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
            <input type="hidden" name="action" value="create_item">
            <input type="hidden" name="fiscal_year" value="<?= $year ?>">

            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อรายการครุภัณฑ์ *</label>
                <input type="text" name="item_name" required placeholder="เช่น เครื่องพิมพ์เลเซอร์, โต๊ะทำงาน" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 font-medium mb-1">รหัสครุภัณฑ์</label>
                    <input type="text" name="asset_code" placeholder="เช่น 7440-001-0001/1" 
                           class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-slate-800 font-mono focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-slate-600 font-medium mb-1">ประเภท</label>
                    <input type="text" name="category" list="cat-list" placeholder="เช่น ครุภัณฑ์สำนักงาน" 
                           class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    <datalist id="cat-list">
                        <?php foreach ($category_options ?? [] as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 font-medium mb-1">สถานที่/ผู้รับผิดชอบ</label>
                    <input type="text" name="location" list="loc-list" placeholder="เช่น ห้องธุรการ หรือ ฉันทนา"
                           class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    <datalist id="loc-list">
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= htmlspecialchars($l) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-slate-600 font-medium mb-1">สถานะเริ่มต้น</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                        <option value="pending">รอการตรวจ</option>
                        <option value="usable">ใช้ได้</option>
                        <option value="damaged">ชำรุด</option>
                        <option value="degraded">เสื่อมคุณภาพ</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">รูปภาพครุภัณฑ์ (ย่อและบีบอัดอัตโนมัติ ไม่เปลืองความจุ)</label>
                <input type="file" name="image" accept="image/*" 
                       class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">หมายเหตุ</label>
                <input type="text" name="remarks" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)" 
                       class="w-full bg-slate-50 border border-slate-200 px-3.5 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeAddInspectionModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 transition-colors">
                    ยกเลิก
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors">
                    บันทึกรายการ
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: แก้ไขรายการตรวจ (EDIT INSPECTION ITEM) -->
<div id="edit-inspect-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-slate-800 text-sm">แก้ไขข้อมูลรายการตรวจครุภัณฑ์</h3>
            <button onclick="closeInspectModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form method="POST" action="inspection.php?year=<?= $year ?>" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="id" id="edit-ins-id">

            <div>
                <label class="block text-slate-600 font-medium mb-1">ชื่อรายการ *</label>
                <input type="text" name="item_name" id="edit-ins-name" required class="w-full bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-slate-600 font-medium mb-1">รหัสครุภัณฑ์</label>
                    <input type="text" name="asset_code" id="edit-ins-code" class="w-full bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl font-mono text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </div>
                <div><label class="block text-slate-600 font-medium mb-1">หมวด</label><input type="text" name="category" id="edit-ins-category" list="cat-list" class="w-full bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl text-slate-800"></div>
                <div><label class="block text-slate-600 font-medium mb-1">รหัสสินทรัพย์</label><input type="text" name="asset_id_code" id="edit-ins-asset-id" class="w-full bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl text-slate-800"></div>
                <div>
                    <label class="block text-slate-600 font-medium mb-1">สถานที่/ผู้รับผิดชอบ</label>
                    <input type="text" name="location" id="edit-ins-loc" class="w-full bg-slate-50 border border-slate-200 px-3 py-2 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">เปลี่ยนรูปภาพ (บีบอัดอัตโนมัติ)</label>
                <input type="file" name="image" accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer">
            </div>

            <div>
                <label class="block text-slate-600 font-medium mb-1">หมายเหตุ</label>
                <textarea name="remarks" id="edit-ins-remarks" rows="2" class="w-full bg-slate-50 border border-slate-200 p-2.5 rounded-xl text-slate-800 focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                <button type="button" onclick="closeInspectModal()" class="px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600">ยกเลิก</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 4: ดูรูปภาพ & ถ่ายรูปด่วน (PHOTO PREVIEW & QUICK UPLOAD) -->
<div id="photo-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-slate-800 text-xs truncate max-w-[220px]" id="photo-modal-title">รูปภาพครุภัณฑ์</h3>
            <button onclick="closePhotoModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <div class="p-5 flex flex-col items-center justify-center space-y-3">
            <div class="w-full h-52 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-center overflow-hidden">
                <img id="photo-modal-img" src="" alt="รูปภาพครุภัณฑ์" class="hidden w-full h-full object-contain p-1">
                <div id="photo-modal-empty" class="text-center text-slate-400">
                    <i class="fa-solid fa-image text-4xl mb-1 text-slate-300"></i>
                    <p class="text-xs">ยังไม่มีรูปภาพสำหรับรายการนี้</p>
                </div>
            </div>

            <!-- Upload input -->
            <label for="photo-modal-input" class="w-full py-2 px-3 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold transition-colors border border-indigo-200/80 shadow-2xs flex items-center justify-center gap-2 cursor-pointer">
                <i class="fa-solid fa-camera"></i>
                <span id="photo-upload-label">ถ่ายรูป / แนบรูปภาพใหม่</span>
            </label>
            <input type="file" id="photo-modal-input" accept="image/*" class="hidden" onchange="uploadInspectionPhoto(this)">

            <div id="photo-upload-status" class="hidden text-xs font-medium text-center"></div>
            <p class="text-[10px] text-slate-400 text-center">
                <i class="fa-solid fa-shield-halved text-indigo-400 mr-1"></i> ย่อขนาดอัตโนมัติ ≤ 800px ไม่เปลืองความจุเซิร์ฟเวอร์
            </p>
        </div>
    </div>
</div>

<!-- CHARTS & INTERACTIVE SCRIPT -->
<script>
let inspectChartInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Donut Chart
    const canvas = document.getElementById('inspectDonutChart');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        inspectChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['ใช้ได้', 'ชำรุด', 'เสื่อมคุณภาพ', 'รอการตรวจ'],
                datasets: [{
                    data: [<?= $stat_usable ?>, <?= $stat_damaged ?>, <?= $stat_degraded ?>, <?= $stat_pending ?>],
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#cbd5e1'],
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
    }

    // 2. View Switcher Initialization (Default to Card View on Mobile)
    const savedView = localStorage.getItem('sena_inspection_view');
    const isMobile = window.innerWidth < 768;
    if (savedView === 'table' && !isMobile) {
        switchView('table');
    } else {
        switchView('card');
    }
});

// View Switcher: Card View vs Table View
function switchView(view) {
    const cardsEl = document.getElementById('inspection-cards-container');
    const tableEl = document.getElementById('inspection-table-container');
    const btnCard = document.getElementById('btn-view-card');
    const btnTable = document.getElementById('btn-view-table');

    if (view === 'table') {
        if (cardsEl) cardsEl.classList.add('hidden');
        if (tableEl) tableEl.classList.remove('hidden');
        if (btnCard) {
            btnCard.className = "px-2.5 sm:px-3 py-1 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1.5 transition-all";
        }
        if (btnTable) {
            btnTable.className = "px-2.5 sm:px-3 py-1 rounded-lg text-xs font-bold bg-white text-indigo-700 shadow-2xs flex items-center gap-1.5 transition-all";
        }
        localStorage.setItem('sena_inspection_view', 'table');
    } else {
        if (cardsEl) cardsEl.classList.remove('hidden');
        if (tableEl) tableEl.classList.add('hidden');
        if (btnCard) {
            btnCard.className = "px-2.5 sm:px-3 py-1 rounded-lg text-xs font-bold bg-white text-indigo-700 shadow-2xs flex items-center gap-1.5 transition-all";
        }
        if (btnTable) {
            btnTable.className = "px-2.5 sm:px-3 py-1 rounded-lg text-xs font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1.5 transition-all";
        }
        localStorage.setItem('sena_inspection_view', 'card');
    }
}

// Copy Asset Code to Clipboard with tactile feedback
function copyAssetCode(code, event) {
    if (event) event.stopPropagation();
    if (!code || code === '-') return;
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(() => {
            showToast(`📋 คัดลอกรหัส "${code}" แล้ว`);
            if (navigator.vibrate) navigator.vibrate(25);
        }).catch(() => fallbackCopy(code));
    } else {
        fallbackCopy(code);
    }
}

function fallbackCopy(text) {
    const t = document.createElement('textarea');
    t.value = text;
    t.style.position = 'fixed';
    t.style.opacity = '0';
    document.body.appendChild(t);
    t.select();
    try {
        document.execCommand('copy');
        showToast(`📋 คัดลอกรหัส "${text}" แล้ว`);
        if (navigator.vibrate) navigator.vibrate(25);
    } catch(e) {}
    document.body.removeChild(t);
}

// Thai Status Names & Badge Helpers
const statusThaiMap = {
    'usable': 'ใช้ได้',
    'damaged': 'ชำรุด',
    'degraded': 'เสื่อมคุณภาพ',
    'lost': 'สูญไป',
    'unused': 'ไม่ใช้',
    'pending': 'รอการตรวจ'
};

// Optimistic In-Place Status Update
function setInspectionStatus(id, status, itemNumber) {
    // 1. Optimistically update Card View UI
    updateCardUI(id, status);

    // 2. Optimistically update Table View UI
    updateTableRowUI(id, status);

    // 3. Tactile Vibration Feedback for mobile devices
    if (navigator.vibrate) {
        navigator.vibrate(35);
    }

    // 4. Instant Toast
    const thLabel = statusThaiMap[status] || status;
    const numText = itemNumber ? ` #${itemNumber}` : '';
    showToast(`✓ บันทึก${numText}: "${thLabel}" เรียบร้อย`);

    // 5. Send background AJAX request to server
    fetch('actions/update_inspection.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, status: status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.stats) {
            // Apply live statistics across the page without reloading!
            applyLiveStats(data.stats);
        } else if (!data.success) {
            showToast('⚠️ ไม่สามารถบันทึกได้: ' + (data.error || ''));
        }
    })
    .catch(err => {
        console.error(err);
        showToast('⚠️ การเชื่อมต่อขัดข้อง');
    });
}

// Update Card Status Buttons & Badge In-Place
function updateCardUI(id, status) {
    const card = document.getElementById(`card-${id}`);
    if (!card) return;

    card.setAttribute('data-status', status);

    // Update Status Badge on Card
    const badgeEl = document.getElementById(`card-badge-${id}`);
    if (badgeEl) {
        if (status === 'usable') {
            badgeEl.innerHTML = `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-check text-[10px]"></i> ใช้ได้</span>`;
        } else if (status === 'damaged') {
            badgeEl.innerHTML = `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700 border border-rose-200"><i class="fa-solid fa-wrench text-[10px]"></i> ชำรุด</span>`;
        } else if (status === 'degraded') {
            badgeEl.innerHTML = `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700 border border-amber-200"><i class="fa-solid fa-triangle-exclamation text-[10px]"></i> เสื่อมฯ</span>`;
        } else if (status === 'lost') {
            badgeEl.innerHTML = `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-sky-100 text-sky-700 border border-sky-200"><i class="fa-solid fa-question text-[10px]"></i> สูญไป</span>`;
        } else if (status === 'unused') {
            badgeEl.innerHTML = `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-100 text-indigo-700 border border-indigo-200"><i class="fa-solid fa-ban text-[10px]"></i> ไม่ใช้</span>`;
        }
    }

    // Update 5 Status Buttons styling
    const btnGroup = document.getElementById(`card-btn-group-${id}`);
    if (btnGroup) {
        const buttons = {
            'usable': {
                btn: btnGroup.querySelector('.card-btn-usable'),
                activeClass: 'bg-emerald-600 text-white font-bold shadow-sm ring-2 ring-emerald-300',
                inactiveClass: 'bg-slate-50 hover:bg-emerald-50 text-slate-600 border border-slate-200',
                iconColorActive: 'text-white',
                iconColorInactive: 'text-emerald-500'
            },
            'degraded': {
                btn: btnGroup.querySelector('.card-btn-degraded'),
                activeClass: 'bg-amber-500 text-white font-bold shadow-sm ring-2 ring-amber-300',
                inactiveClass: 'bg-slate-50 hover:bg-amber-50 text-slate-600 border border-slate-200',
                iconColorActive: 'text-white',
                iconColorInactive: 'text-amber-500'
            },
            'damaged': {
                btn: btnGroup.querySelector('.card-btn-damaged'),
                activeClass: 'bg-rose-600 text-white font-bold shadow-sm ring-2 ring-rose-300',
                inactiveClass: 'bg-slate-50 hover:bg-rose-50 text-slate-600 border border-slate-200',
                iconColorActive: 'text-white',
                iconColorInactive: 'text-rose-500'
            },
            'lost': {
                btn: btnGroup.querySelector('.card-btn-lost'),
                activeClass: 'bg-sky-600 text-white font-bold shadow-sm ring-2 ring-sky-300',
                inactiveClass: 'bg-slate-50 hover:bg-sky-50 text-slate-600 border border-slate-200',
                iconColorActive: 'text-white',
                iconColorInactive: 'text-sky-500'
            },
            'unused': {
                btn: btnGroup.querySelector('.card-btn-unused'),
                activeClass: 'bg-indigo-600 text-white font-bold shadow-sm ring-2 ring-indigo-300',
                inactiveClass: 'bg-slate-50 hover:bg-indigo-50 text-slate-600 border border-slate-200',
                iconColorActive: 'text-white',
                iconColorInactive: 'text-indigo-500'
            }
        };

        Object.keys(buttons).forEach(key => {
            const config = buttons[key];
            if (!config.btn) return;
            const icon = config.btn.querySelector('i');
            if (key === status) {
                config.btn.className = `card-btn-${key} min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 ${config.activeClass}`;
                if (icon) icon.className = icon.className.replace(/text-\w+-\d+/, config.iconColorActive);
            } else {
                config.btn.className = `card-btn-${key} min-h-[46px] rounded-xl flex flex-col items-center justify-center gap-0.5 transition-all active:scale-95 ${config.inactiveClass}`;
                if (icon) icon.className = icon.className.replace(/text-white/, config.iconColorInactive);
            }
        });
    }
}

// Update Table View Checkmarks In-Place
function updateTableRowUI(id, status) {
    const keys = ['usable', 'damaged', 'degraded', 'lost', 'unused'];
    const colors = {
        'usable': 'bg-emerald-500',
        'damaged': 'bg-rose-500',
        'degraded': 'bg-amber-500',
        'lost': 'bg-sky-500',
        'unused': 'bg-indigo-500'
    };

    keys.forEach(k => {
        const td = document.getElementById(`td-status-${id}-${k}`);
        if (!td) return;
        const btn = td.querySelector('button');
        if (!btn) return;

        if (k === status) {
            btn.innerHTML = `<span class="w-5 h-5 rounded-md ${colors[k]} text-white inline-flex items-center justify-center text-[10px] hover:scale-110 transition-transform"><i class="fa-solid fa-check"></i></span>`;
        } else {
            btn.innerHTML = `<span class="w-5 h-5 rounded-md border border-slate-200 text-slate-300 inline-flex items-center justify-center text-[10px]">-</span>`;
        }
    });
}

// Apply Recalculated Live Stats Across Page
function applyLiveStats(s) {
    if (!s) return;

    // Mobile field banner percentage & counts
    const pctEl = document.getElementById('stat-progress-pct');
    if (pctEl) pctEl.textContent = s.pct_checked + '%';

    const chkEl = document.getElementById('stat-progress-checked');
    if (chkEl) chkEl.textContent = Number(s.checked).toLocaleString();

    const totEl = document.getElementById('stat-progress-total');
    if (totEl) totEl.textContent = Number(s.total).toLocaleString();

    const barChk = document.getElementById('stat-bar-checked-text');
    if (barChk) barChk.textContent = Number(s.checked).toLocaleString();

    const barPnd = document.getElementById('stat-bar-pending-text');
    if (barPnd) barPnd.textContent = Number(s.pending).toLocaleString();

    // Multi-segment progress bar widths
    const tot = s.total > 0 ? s.total : 1;
    const barUsable = document.getElementById('bar-usable');
    if (barUsable) barUsable.style.width = ((s.usable / tot) * 100) + '%';

    const barDeg = document.getElementById('bar-degraded');
    if (barDeg) barDeg.style.width = ((s.degraded / tot) * 100) + '%';

    const barDam = document.getElementById('bar-damaged');
    if (barDam) barDam.style.width = ((s.damaged / tot) * 100) + '%';

    const barLst = document.getElementById('bar-lost');
    if (barLst) barLst.style.width = ((s.lost / tot) * 100) + '%';

    const barUnu = document.getElementById('bar-unused');
    if (barUnu) barUnu.style.width = ((s.unused / tot) * 100) + '%';

    // Quick filter chips count
    const chipPnd = document.getElementById('chip-pending');
    if (chipPnd) chipPnd.textContent = Number(s.pending).toLocaleString();

    const chipUsb = document.getElementById('chip-usable');
    if (chipUsb) chipUsb.textContent = Number(s.usable).toLocaleString();

    const chipDam = document.getElementById('chip-damaged');
    if (chipDam) chipDam.textContent = Number(s.damaged).toLocaleString();

    const chipDeg = document.getElementById('chip-degraded');
    if (chipDeg) chipDeg.textContent = Number(s.degraded).toLocaleString();

    const chipLst = document.getElementById('chip-lost');
    if (chipLst) chipLst.textContent = Number(s.lost).toLocaleString();

    const chipUnu = document.getElementById('chip-unused');
    if (chipUnu) chipUnu.textContent = Number(s.unused).toLocaleString();

    // Top Stat Cards
    const scChecked = document.getElementById('stat-card-checked');
    if (scChecked) scChecked.textContent = Number(s.checked).toLocaleString();

    const scPending = document.getElementById('stat-card-pending');
    if (scPending) scPending.textContent = Number(s.pending).toLocaleString();

    const scUsable = document.getElementById('stat-card-usable');
    if (scUsable) scUsable.textContent = Number(s.usable).toLocaleString();

    const scDamaged = document.getElementById('stat-card-damaged');
    if (scDamaged) scDamaged.textContent = Number(s.damaged).toLocaleString();

    const scDegraded = document.getElementById('stat-card-degraded');
    if (scDegraded) scDegraded.textContent = Number(s.degraded).toLocaleString();

    // Mobile floating bar pending count
    const mobPnd = document.getElementById('mobile-bar-pending');
    if (mobPnd) mobPnd.textContent = Number(s.pending).toLocaleString();

    // Donut chart live update
    if (inspectChartInstance) {
        inspectChartInstance.data.datasets[0].data = [s.usable, s.damaged, s.degraded, s.pending];
        inspectChartInstance.update();
    }
}

// -------------------------------------------------------------
// DIRECT SMARTPHONE CAMERA CAPTURE
// -------------------------------------------------------------
let currentCardPhotoInspectionId = 0;

function triggerCardCamera(id) {
    currentCardPhotoInspectionId = id;
    const camInput = document.getElementById('card-camera-input');
    if (camInput) {
        camInput.click();
    }
}

function uploadCardPhoto(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const id = currentCardPhotoInspectionId;

    const wrapper = document.getElementById(`card-photo-wrapper-${id}`);
    const originalWrapperContent = wrapper ? wrapper.innerHTML : '';

    if (wrapper) {
        wrapper.innerHTML = `
            <div class="w-full h-full flex flex-col items-center justify-center bg-indigo-50/90 text-indigo-600 text-[10px]">
                <i class="fa-solid fa-spinner fa-spin text-base"></i>
                <span class="font-medium mt-1">กำลังย่อรูป...</span>
            </div>
        `;
    }

    const formData = new FormData();
    formData.append('image', file);
    formData.append('inspection_id', id);

    fetch('actions/upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        input.value = '';
        if (res.success) {
            const cacheBustUrl = res.url + '?t=' + Date.now();
            
            // 1. Update Card View photo wrapper
            if (wrapper) {
                wrapper.innerHTML = `
                    <img id="card-img-${id}" src="${cacheBustUrl}" alt="รูปครุภัณฑ์" class="w-full h-full object-cover cursor-pointer" 
                         onclick="openPhotoModal(${id}, 'รูปภาพครุภัณฑ์', '${res.url}')">
                    <button type="button" onclick="triggerCardCamera(${id})" class="absolute bottom-0 right-0 bg-slate-900/70 text-white w-5 h-5 rounded-tl-lg flex items-center justify-center text-[9px] hover:bg-indigo-600 transition-colors" title="ถ่ายรูปใหม่">
                        <i class="fa-solid fa-camera"></i>
                    </button>
                `;
            }

            // 2. Update Table View photo cell if present
            const tblCell = document.getElementById(`table-photo-cell-${id}`);
            if (tblCell) {
                tblCell.innerHTML = `
                    <button type="button" onclick="openPhotoModal(${id}, 'รูปภาพครุภัณฑ์', '${res.url}')" 
                            class="w-7 h-7 rounded-lg overflow-hidden border border-indigo-200 inline-block hover:scale-110 transition-transform shadow-2xs" title="คลิกดู / เปลี่ยนรูป">
                        <img src="${cacheBustUrl}" alt="ครุภัณฑ์" class="w-full h-full object-cover">
                    </button>
                `;
            }

            showToast(`📸 บันทึกรูปภาพสำเร็จ (${res.compressed_size_kb} KB)`);
            if (navigator.vibrate) navigator.vibrate([25, 40, 25]);
        } else {
            if (wrapper) wrapper.innerHTML = originalWrapperContent;
            showToast('⚠️ ไม่สามารถอัปโหลดรูปภาพได้: ' + (res.error || ''));
        }
    })
    .catch(err => {
        input.value = '';
        if (wrapper) wrapper.innerHTML = originalWrapperContent;
        showToast('⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อ');
    });
}

// -------------------------------------------------------------
// QUICK REMARKS MODAL
// -------------------------------------------------------------
function openQuickRemarkModal(id, name, remarks) {
    document.getElementById('quick-remark-id').value = id;
    document.getElementById('quick-remark-item-name').textContent = name || 'รายการครุภัณฑ์';
    document.getElementById('quick-remark-text').value = remarks || '';
    document.getElementById('quick-remark-modal').classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('quick-remark-text').focus();
    }, 100);
}

function closeQuickRemarkModal() {
    document.getElementById('quick-remark-modal').classList.add('hidden');
}

function saveQuickRemark() {
    const id = parseInt(document.getElementById('quick-remark-id').value);
    const remarks = document.getElementById('quick-remark-text').value.trim();

    fetch('actions/update_inspection.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, type: 'update_remarks', remarks: remarks })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            // Update Card View remarks
            const cardRemarksText = document.getElementById(`card-remarks-text-${id}`);
            const cardRemarksContainer = document.getElementById(`card-remarks-container-${id}`);
            if (cardRemarksText && cardRemarksContainer) {
                if (remarks !== '') {
                    cardRemarksText.textContent = remarks;
                    cardRemarksContainer.classList.remove('hidden');
                } else {
                    cardRemarksText.textContent = '';
                    cardRemarksContainer.classList.add('hidden');
                }
            }

            // Update Table View remarks
            const tblRemarks = document.getElementById(`table-remarks-${id}`);
            if (tblRemarks) {
                tblRemarks.textContent = remarks !== '' ? remarks : '-';
            }

            closeQuickRemarkModal();
            showToast('✓ บันทึกหมายเหตุเรียบร้อย');
            if (navigator.vibrate) navigator.vibrate(25);
        } else {
            showToast('⚠️ ไม่สามารถบันทึกหมายเหตุได้: ' + (res.error || ''));
        }
    })
    .catch(() => showToast('⚠️ เกิดข้อผิดพลาดในการเชื่อมต่อ'));
}

// -------------------------------------------------------------
// STANDARD MODALS (COPY, ADD, EDIT, PHOTO VIEW)
// -------------------------------------------------------------
function openCopyModal(year) {
    document.getElementById('copy-target-year').value = year;
    document.getElementById('copy-modal').classList.remove('hidden');
}
function closeCopyModal() {
    document.getElementById('copy-modal').classList.add('hidden');
}

function openAddInspectionModal() {
    document.getElementById('add-inspect-modal').classList.remove('hidden');
}
function closeAddInspectionModal() {
    document.getElementById('add-inspect-modal').classList.add('hidden');
}

function openEditInspectionModal(data) {
    document.getElementById('edit-ins-id').value = data.id;
    document.getElementById('edit-ins-name').value = data.item_name;
    document.getElementById('edit-ins-code').value = data.asset_code || '';
    document.getElementById('edit-ins-category').value = data.category || '';
    document.getElementById('edit-ins-asset-id').value = data.asset_id_code || '';
    document.getElementById('edit-ins-loc').value = data.location || '';
    document.getElementById('edit-ins-remarks').value = data.remarks || '';
    document.getElementById('edit-inspect-modal').classList.remove('hidden');
}
function closeInspectModal() {
    document.getElementById('edit-inspect-modal').classList.add('hidden');
}

let currentPhotoInspectionId = 0;
function openPhotoModal(id, name, imgUrl) {
    currentPhotoInspectionId = id;
    document.getElementById('photo-modal-title').textContent = name || 'รูปภาพครุภัณฑ์';
    const imgEl = document.getElementById('photo-modal-img');
    const emptyEl = document.getElementById('photo-modal-empty');
    const statusEl = document.getElementById('photo-upload-status');
    if (statusEl) statusEl.classList.add('hidden');

    if (imgUrl && imgUrl.trim() !== '') {
        imgEl.src = imgUrl;
        imgEl.classList.remove('hidden');
        emptyEl.classList.add('hidden');
        document.getElementById('photo-upload-label').textContent = 'เปลี่ยนรูปภาพ';
    } else {
        imgEl.src = '';
        imgEl.classList.add('hidden');
        emptyEl.classList.remove('hidden');
        document.getElementById('photo-upload-label').textContent = 'ถ่ายรูป / แนบรูปภาพ';
    }
    document.getElementById('photo-modal').classList.remove('hidden');
}
function closePhotoModal() {
    document.getElementById('photo-modal').classList.add('hidden');
}

function uploadInspectionPhoto(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const statusEl = document.getElementById('photo-upload-status');

    statusEl.className = 'text-xs font-medium text-indigo-600 text-center block';
    statusEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> กำลังย่อและบีบอัดรูปภาพ...';
    statusEl.classList.remove('hidden');

    const formData = new FormData();
    formData.append('image', file);
    formData.append('inspection_id', currentPhotoInspectionId);

    fetch('actions/upload_image.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        input.value = '';
        if (res.success) {
            const cacheBustUrl = res.url + '?t=' + Date.now();
            const imgEl = document.getElementById('photo-modal-img');
            const emptyEl = document.getElementById('photo-modal-empty');
            imgEl.src = cacheBustUrl;
            imgEl.classList.remove('hidden');
            emptyEl.classList.add('hidden');

            statusEl.className = 'text-xs font-medium text-emerald-600 text-center block';
            statusEl.innerHTML = `<i class="fa-solid fa-check mr-1"></i> อัปโหลดสำเร็จ (${res.compressed_size_kb} KB)`;

            // Update card thumbnail in background
            const cardImg = document.getElementById(`card-img-${currentPhotoInspectionId}`);
            if (cardImg) cardImg.src = cacheBustUrl;

            setTimeout(() => {
                closePhotoModal();
            }, 900);
        } else {
            statusEl.className = 'text-xs font-medium text-rose-600 text-center block';
            statusEl.textContent = res.error || 'เกิดข้อผิดพลาดในการอัปโหลด';
        }
    })
    .catch(() => {
        input.value = '';
        statusEl.className = 'text-xs font-medium text-rose-600 text-center block';
        statusEl.textContent = 'การเชื่อมต่อขัดข้อง';
    });
}

function showToast(msg) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    const msgEl = document.getElementById('toast-message');
    if (msgEl) msgEl.textContent = msg;
    toast.classList.remove('translate-y-20', 'opacity-0');
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
    }, 2400);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
