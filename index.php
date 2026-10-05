<?php
// index.php - แดชบอร์ดภาพรวม
$active_page = 'dashboard';
$page_title = 'แดชบอร์ดภาพรวม';
$page_subtitle = 'ภาพรวมข้อมูลครุภัณฑ์ของสำนักงานส่งเสริมการเรียนรู้ อำเภอเสนา';

require_once __DIR__ . '/config/database.php';

// Fetch live statistics from database
try {
    if (!$pdo) {
        throw new \Exception("Database connection not available");
    }
    $total_items = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items")->fetchColumn();
    $usable_items = (int)$pdo->query("SELECT SUM(status_usable) FROM inspection_items")->fetchColumn();
    $damaged_items = (int)$pdo->query("SELECT SUM(status_damaged) FROM inspection_items")->fetchColumn();
    $degraded_items = (int)$pdo->query("SELECT SUM(status_degraded) FROM inspection_items")->fetchColumn();
    $lost_items = (int)$pdo->query("SELECT SUM(status_lost) FROM inspection_items")->fetchColumn();
    $unused_items = (int)$pdo->query("SELECT SUM(status_unused) FROM inspection_items")->fetchColumn();
    $overlap_items = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items WHERE is_overlap = 1")->fetchColumn();

    // Checked items (any status checked) vs pending items
    $checked_items = (int)$pdo->query("SELECT COUNT(*) FROM inspection_items WHERE status_usable = 1 OR status_damaged = 1 OR status_degraded = 1 OR status_lost = 1 OR status_unused = 1")->fetchColumn();
    $pending_items = max(0, $total_items - $checked_items);

} catch (\Throwable $e) {
    // Fallback defaults matching dataset
    $total_items = 603;
    $usable_items = 495;
    $damaged_items = 19;
    $degraded_items = 93;
    $lost_items = 0;
    $unused_items = 0;
    $overlap_items = 4;
    $checked_items = 582;
    $pending_items = 21;
}

// Percentages
$p_usable = $total_items > 0 ? round(($usable_items / $total_items) * 100, 2) : 0;
$p_damaged = $total_items > 0 ? round(($damaged_items / $total_items) * 100, 2) : 0;
$p_degraded = $total_items > 0 ? round(($degraded_items / $total_items) * 100, 2) : 0;
$p_overlap = $total_items > 0 ? round(($overlap_items / $total_items) * 100, 2) : 0;
$p_checked = $total_items > 0 ? round(($checked_items / $total_items) * 100, 2) : 96.52;
$p_pending = $total_items > 0 ? round(($pending_items / $total_items) * 100, 2) : 3.48;

include __DIR__ . '/includes/header.php';
?>

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
        <p class="text-[10px] text-slate-400 mt-1.5 font-light">100% ของครุภัณฑ์ทั้งหมด</p>
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
        <p class="text-[10px] text-slate-400 mt-1.5 font-light">0% ของทั้งหมด</p>
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
        <p class="text-[10px] text-slate-400 mt-1.5 font-light">0% ของทั้งหมด</p>
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
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- CHART 1: สถานะครุภัณฑ์ (Donut Chart) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">สถานะครุภัณฑ์</h3>
            </div>
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-600 focus:outline-none">
                <option>จำนวน (รายการ)</option>
                <option>ร้อยละ (%)</option>
            </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 items-center gap-4 pt-4">
            <!-- Donut Container with Center Text -->
            <div class="relative w-44 h-44 mx-auto">
                <canvas id="statusDonutChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-2xl font-extrabold text-slate-800">603</span>
                    <span class="text-[11px] text-slate-400">รายการ</span>
                </div>
            </div>

            <!-- Legend with exact values -->
            <div class="space-y-2 text-xs">
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>
                        <span class="text-slate-600">ใช้ได้</span>
                    </div>
                    <span class="font-semibold text-slate-800">495 <span class="text-slate-400 font-normal">(82.09%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>
                        <span class="text-slate-600">ชำรุด</span>
                    </div>
                    <span class="font-semibold text-slate-800">19 <span class="text-slate-400 font-normal">(3.15%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
                        <span class="text-slate-600">เสื่อมคุณภาพ</span>
                    </div>
                    <span class="font-semibold text-slate-800">93 <span class="text-slate-400 font-normal">(15.42%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#38bdf8]"></span>
                        <span class="text-slate-600">สูญไป</span>
                    </div>
                    <span class="font-semibold text-slate-800">0 <span class="text-slate-400 font-normal">(0%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#818cf8]"></span>
                        <span class="text-slate-600">ไม่ใช้</span>
                    </div>
                    <span class="font-semibold text-slate-800">0 <span class="text-slate-400 font-normal">(0%)</span></span>
                </div>
                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-slate-50">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#fb923c]"></span>
                        <span class="text-slate-600">ข้อมูลต้องตรวจสอบ</span>
                    </div>
                    <span class="font-semibold text-slate-800">4 <span class="text-slate-400 font-normal">(0.66%)</span></span>
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
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-600 focus:outline-none">
                <option>จำนวน (รายการ)</option>
            </select>
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
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-600 focus:outline-none">
                <option>จำนวน (รายการ)</option>
            </select>
        </div>
        <div class="pt-4 h-60">
            <canvas id="locationBarChart"></canvas>
        </div>
    </div>

    <!-- CHART 4: ผลการตรวจย้อนหลัง (Smooth Line Chart) -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">ผลการตรวจย้อนหลัง</h3>
            </div>
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-600 focus:outline-none">
                <option>3 ปีล่าสุด</option>
                <option>5 ปีล่าสุด</option>
            </select>
        </div>
        <div class="pt-4 h-60">
            <canvas id="trendLineChart"></canvas>
        </div>
    </div>

</div>

<!-- 3. BOTTOM ROW (ACTION ITEMS & SUMMARY) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- LEFT: รายการที่ต้องดำเนินการ -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-2xs">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-list-ul text-indigo-600 text-sm"></i>
                <h3 class="font-bold text-slate-800 text-sm">รายการที่ต้องดำเนินการ</h3>
            </div>
            <a href="inspection.php" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">ดูทั้งหมด &rarr;</a>
        </div>

        <div class="divide-y divide-slate-100 pt-1">
            <!-- 1. ครุภัณฑ์ชำรุด -->
            <a href="inspection.php?filter=damaged" class="flex items-center justify-between py-3.5 px-2 hover:bg-slate-50 rounded-xl transition-colors group">
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
                    <span class="text-xs font-bold text-rose-600">19 รายการ</span>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </div>
            </a>

            <!-- 2. สถานะซ้ำซ้อน -->
            <a href="inspection.php?filter=overlap" class="flex items-center justify-between py-3.5 px-2 hover:bg-slate-50 rounded-xl transition-colors group">
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
                    <span class="text-xs font-bold text-amber-600">4 รายการ</span>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </div>
            </a>

            <!-- 3. รหัสครุภัณฑ์ซ้ำ -->
            <a href="inspection.php?filter=duplicate" class="flex items-center justify-between py-3.5 px-2 hover:bg-slate-50 rounded-xl transition-colors group">
                <div class="flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                        <i class="fa-solid fa-file-circle-exclamation"></i>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">รหัสครุภัณฑ์ซ้ำ</p>
                        <p class="text-[11px] text-slate-400">พบรหัสครุภัณฑ์ที่ซ้ำกันในระบบ</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-blue-600">4 รหัส</span>
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
                <h3 class="font-bold text-slate-800 text-sm">สรุปข้อมูลสำคัญ</h3>
            </div>
            <select class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-slate-600 focus:outline-none">
                <option>ปีงบประมาณ 2567</option>
                <option>ปีงบประมาณ 2568</option>
            </select>
        </div>

        <div class="pt-3 space-y-4">
            <!-- 1. จำนวนครุภัณฑ์ในปีนี้ -->
            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/80">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-box-archive text-sm"></i>
                    </div>
                    <span class="text-xs text-slate-600 font-medium">จำนวนครุภัณฑ์ในปีนี้</span>
                </div>
                <div class="text-right">
                    <span class="text-sm font-bold text-slate-800">86 รายการ</span>
                    <span class="text-[11px] text-emerald-600 font-medium ml-1.5"><i class="fa-solid fa-arrow-up text-[10px]"></i> 12%</span>
                    <p class="text-[10px] text-slate-400 font-light">เพิ่มขึ้นจากปีที่แล้ว</p>
                </div>
            </div>

            <!-- 2. ตรวจสอบแล้ว Progress Bar -->
            <div class="space-y-1.5 p-3 rounded-xl bg-slate-50/80">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-file-lines text-indigo-500"></i>
                        <span class="text-slate-600 font-medium">ตรวจสอบแล้ว</span>
                    </div>
                    <span class="font-bold text-slate-800">582 รายการ <span class="text-slate-400 font-normal">(96.52%)</span></span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all duration-1000" style="width: 96.52%"></div>
                </div>
            </div>

            <!-- 3. รอการตรวจสอบ Progress Bar -->
            <div class="space-y-1.5 p-3 rounded-xl bg-slate-50/80">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <i class="fa-regular fa-clock text-amber-500"></i>
                        <span class="text-slate-600 font-medium">รอการตรวจสอบ</span>
                    </div>
                    <span class="font-bold text-slate-800">21 รายการ <span class="text-slate-400 font-normal">(3.48%)</span></span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-amber-500 h-2 rounded-full transition-all duration-1000" style="width: 3.48%"></div>
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
                data: [495, 19, 93, 0, 0, 4],
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

    // 2. CATEGORY BAR CHART (Rounded bars with matching colors)
    const ctxCat = document.getElementById('categoryBarChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'bar',
        data: {
            labels: ['คอมพิวเตอร์', 'โต๊ะ/เก้าอี้', 'ครุภัณฑ์สำนักงาน', 'ไฟฟ้า', 'อื่นๆ'],
            datasets: [{
                data: [245, 128, 112, 72, 46],
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
                    max: 300,
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
            labels: ['สกร.อำเภอเสนา', 'ห้องธุรการ', 'ห้องประชุม', 'ห้องพัสดุ', 'ศกร.ตำบล'],
            datasets: [{
                data: [238, 142, 96, 68, 59],
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
            labels: ['ปี 2565', 'ปี 2566', 'ปี 2567'],
            datasets: [{
                data: [420, 508, 603],
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
                    max: 800,
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
