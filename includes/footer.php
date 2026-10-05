<?php
// includes/footer.php
?>
        </main>
        
        <!-- PAGE FOOTER -->
        <footer class="mt-auto px-6 py-4 border-t border-slate-200/80 bg-white/50 text-xs text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-2 no-print">
            <div>
                <span>SENA_Asset - ระบบบริหารจัดการครุภัณฑ์</span> &bull; 
                <span>สกร.ระดับอำเภอเสนา จังหวัดพระนครศรีอยุธยา</span>
            </div>
            <div>
                <span>ประจำปีงบประมาณ พ.ศ. 2568</span>
            </div>
        </footer>

    </div>

    <!-- GLOBAL SEARCH MODAL (Cmd + K) -->
    <div id="search-modal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-start justify-center pt-16 px-4">
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all animate-in fade-in zoom-in-95 duration-150">
            <!-- Search Input Header -->
            <div class="p-4 border-b border-slate-100 flex items-center gap-3">
                <i class="fa-solid fa-magnifying-glass text-indigo-500 text-base"></i>
                <input type="text" id="modal-search-input" oninput="doGlobalSearch(this.value)" 
                       placeholder="ค้นหารหัสครุภัณฑ์, ชื่อรายการ, สถานที่..." 
                       class="w-full text-sm text-slate-800 focus:outline-none placeholder:text-slate-400">
                <kbd class="px-2 py-0.5 text-xs text-slate-400 bg-slate-100 rounded-md border border-slate-200">ESC</kbd>
                <button onclick="closeSearchModal()" class="text-slate-400 hover:text-slate-600 p-1">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Search Results Dropdown List -->
            <div id="search-results-box" class="max-h-96 overflow-y-auto p-2 divide-y divide-slate-100 text-xs">
                <div class="py-8 text-center text-slate-400">
                    <i class="fa-solid fa-keyboard text-2xl text-slate-300 mb-2"></i>
                    <p>พิมพ์คำค้นหาเพื่อดูข้อมูลครุภัณฑ์แบบเรียลไทม์</p>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-3 bg-slate-50 border-t border-slate-100 text-[11px] text-slate-400 flex items-center justify-between">
                <span>เลือกรายการเพื่อดูรายละเอียดในหน้าทะเบียนครุภัณฑ์</span>
                <span>กด <kbd class="px-1 bg-white border border-slate-200 rounded">ESC</kbd> เพื่อปิด</span>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION MODAL -->
    <div id="notification-modal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-start justify-end pt-16 pr-6">
        <div class="bg-white rounded-2xl w-96 shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in duration-150">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-regular fa-bell text-indigo-600 text-sm"></i>
                    <h3 class="font-bold text-slate-800 text-sm">การแจ้งเตือนระบบ</h3>
                </div>
                <button onclick="closeNotificationModal()" class="text-slate-400 hover:text-slate-600 text-xs">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="divide-y divide-slate-100 text-xs max-h-80 overflow-y-auto">
                <div class="p-3.5 hover:bg-slate-50 transition-colors">
                    <div class="flex items-start gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500 mt-1 flex-shrink-0"></span>
                        <div>
                            <p class="font-medium text-slate-800">พบครุภัณฑ์ชำรุด 19 รายการ</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">รอส่งซ่อมหรือดำเนินการเสนอจำหน่าย</p>
                        </div>
                    </div>
                </div>
                <div class="p-3.5 hover:bg-slate-50 transition-colors">
                    <div class="flex items-start gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500 mt-1 flex-shrink-0"></span>
                        <div>
                            <p class="font-medium text-slate-800">มีรายการสถานะซ้ำซ้อน 4 รายการ</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">กรุณาตรวจสอบความถูกต้องในบัญชีรายการตรวจ</p>
                        </div>
                    </div>
                </div>
                <div class="p-3.5 hover:bg-slate-50 transition-colors">
                    <div class="flex items-start gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 mt-1 flex-shrink-0"></span>
                        <div>
                            <p class="font-medium text-slate-800">การตรวจนับประจำปีงบประมาณ 2568</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">ดำเนินการแล้ว 96.52% คงเหลือ 21 รายการ</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-3 bg-slate-50 border-t border-slate-100 text-center">
                <a href="inspection.php" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">ไปที่หน้าตรวจสอบครุภัณฑ์ &rarr;</a>
            </div>
        </div>
    </div>

    <!-- GLOBAL JAVASCRIPT -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        // Global shortcut Cmd+K or Ctrl+K
        document.addEventListener('keydown', function(e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                openSearchModal();
            }
            if (e.key === 'Escape') {
                closeSearchModal();
                closeNotificationModal();
            }
        });

        function openSearchModal() {
            const modal = document.getElementById('search-modal');
            modal.classList.remove('hidden');
            const input = document.getElementById('modal-search-input');
            setTimeout(() => input.focus(), 50);
        }

        function closeSearchModal() {
            document.getElementById('search-modal').classList.add('hidden');
        }

        function openNotificationModal() {
            document.getElementById('notification-modal').classList.remove('hidden');
        }

        function closeNotificationModal() {
            document.getElementById('notification-modal').classList.add('hidden');
        }

        // Live Search implementation
        let searchTimeout = null;
        function doGlobalSearch(val) {
            clearTimeout(searchTimeout);
            const box = document.getElementById('search-results-box');
            if (!val.trim()) {
                box.innerHTML = `
                    <div class="py-8 text-center text-slate-400">
                        <i class="fa-solid fa-keyboard text-2xl text-slate-300 mb-2"></i>
                        <p>พิมพ์คำค้นหาเพื่อดูข้อมูลครุภัณฑ์แบบเรียลไทม์</p>
                    </div>
                `;
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`actions/search.php?q=${encodeURIComponent(val)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.results || data.results.length === 0) {
                            box.innerHTML = `
                                <div class="py-6 text-center text-slate-400">
                                    <p>ไม่พบรายการที่ตรงกับ "${val}"</p>
                                </div>
                            `;
                            return;
                        }

                        let html = '';
                        data.results.forEach(item => {
                            let statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-50 text-emerald-600">ใช้ได้</span>';
                            if (item.status_damaged == 1) {
                                statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] bg-rose-50 text-rose-600">ชำรุด</span>';
                            } else if (item.status_degraded == 1) {
                                statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-50 text-amber-600">เสื่อมคุณภาพ</span>';
                            }

                            html += `
                                <a href="equipment.php?search=${encodeURIComponent(item.asset_code || item.item_name)}" 
                                   class="p-3 hover:bg-indigo-50/50 rounded-xl flex items-center justify-between transition-colors block">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-mono text-xs font-bold">
                                            #${item.item_number}
                                        </div>
                                        <div>
                                            <p class="font-medium text-slate-800">${item.item_name}</p>
                                            <p class="text-[11px] text-slate-400 font-mono">${item.asset_code || '-'} &bull; ${item.location || 'สกร.อำเภอเสนา'}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        ${statusBadge}
                                        <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                                    </div>
                                </a>
                            `;
                        });
                        box.innerHTML = html;
                    })
                    .catch(err => {
                        box.innerHTML = `<div class="p-4 text-center text-rose-500">เกิดข้อผิดพลาดในการค้นหา</div>`;
                    });
            }, 250);
        }

        // User profile dropdown toggle
        function toggleUserDropdown() {
            const menu = document.getElementById('user-dropdown-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const container = document.getElementById('user-profile-menu-container');
            const menu = document.getElementById('user-dropdown-menu');
            if (container && menu && !container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
