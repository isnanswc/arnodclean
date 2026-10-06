<?php
// admin/auto_content.php
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

// Self-healing: Check if tables exist
try {
    $pdo->query("SELECT 1 FROM auto_content_settings LIMIT 1");
} catch (PDOException $e) {
    // Table missing, run migration logic
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS auto_content_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS auto_content_keywords (
            id INT AUTO_INCREMENT PRIMARY KEY,
            keyword VARCHAR(255) NOT NULL,
            status ENUM('pending', 'processing', 'done', 'failed') DEFAULT 'pending',
            error_message TEXT,
            article_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME
        )");
        $defaults = [
            'ai_api_key' => '',
            'ai_provider' => 'gemini',
            'ai_model' => 'gemini-1.5-flash',
            'ai_system_instruction' => "Anda adalah pakar kebersihan furniture profesional (Sofa, Springbed, Karpet) dari Arno D Clean. Anda ahli dalam memberikan tips perawatan, cara membersihkan noda, dan edukasi kesehatan rumah di wilayah Tangerang dan sekitarnya. Gunakan gaya bahasa Indonesia yang profesional, ramah, dan solutif. Selalu kaitkan solusi dengan keunggulan layanan Arno D Clean.",
            'ai_prompt_template' => "Tulis artikel blog menarik tentang: {keyword}.\n\nWajib menyertakan bagian berikut dalam format HTML:\n1. Judul yang memancing klik (tanpa tag h1).\n2. Pembukaan yang menarik tentang urgensi topik ini.\n3. Beberapa sub-judul (h2/h3) yang berisi tips atau informasi detail.\n4. Kesimpulan dan Call to Action (ajakan) untuk menghubungi WhatsApp Arno D Clean.\n5. Gunakan format JSON: {\"title\": \"...\", \"content\": \"...\"}",
            'auto_publish' => '1',
            'ai_generate_image' => '1'
        ];
        foreach ($defaults as $key => $val) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
        }
    } catch (PDOException $migrationError) {
        die("Error creating database tables: " . $migrationError->getMessage());
    }
}

// Handle Post Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_keyword'])) {
        $keywords_input = explode("\n", $_POST['keywords']);
        foreach ($keywords_input as $kw) {
            $kw = trim($kw);
            if (!empty($kw)) {
                $stmt = $pdo->prepare("INSERT INTO auto_content_keywords (keyword) VALUES (?)");
                $stmt->execute([$kw]);
            }
        }
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Kata kunci ditambahkan ke antrian.', 'icon' => 'success'];
        header("Location: auto_content.php");
        exit;
    }

    if (isset($_POST['reset_failed'])) {
        $pdo->exec("UPDATE auto_content_keywords SET status = 'pending', error_message = NULL WHERE status = 'failed'");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Status gagal telah di-reset ke pending.', 'icon' => 'success'];
        header("Location: auto_content.php");
        exit;
    }

    if (isset($_POST['clear_queue'])) {
        $pdo->exec("DELETE FROM auto_content_keywords WHERE status != 'done'");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Antrian telah dibersihkan.', 'icon' => 'success'];
        header("Location: auto_content.php");
        exit;
    }

    if (isset($_POST['delete_id'])) {
        $stmt = $pdo->prepare("DELETE FROM auto_content_keywords WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if (isset($_POST['save_schedule'])) {
        $configs = [
            'ai_schedule_enabled' => $_POST['ai_schedule_enabled'] ?? '0',
            'ai_schedule_mode' => $_POST['ai_schedule_mode'] ?? 'smart',
            'ai_schedule_frequency' => $_POST['ai_schedule_frequency'] ?? '3',
            'ai_schedule_interval' => $_POST['ai_schedule_interval'] ?? '1',
            'ai_schedule_time' => $_POST['ai_schedule_time'] ?? '08:00',
            'ai_schedule_days' => isset($_POST['ai_schedule_days']) ? json_encode($_POST['ai_schedule_days']) : '[]'
        ];

        foreach ($configs as $key => $val) {
            $stmt = $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $val]);
        }
        
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Jadwal otomatis diperbarui.', 'icon' => 'success'];
        header("Location: auto_content.php");
        exit;
    }
}

// Fetch Settings for initial view (schedule status)
$settings = [];
$stmt = $pdo->query("SELECT * FROM auto_content_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

require_once 'includes/header.php';
?>
<!-- SortableJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>

<style>
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    .btn-white { background: #fff; color: #374151; border: 1px solid #e5e7eb; }
    .btn-white:hover { background: #f9fafb; color: #111827; }
    
    /* Layout & Tabs */
    .nav-tabs .nav-link {
        color: #6b7280;
        font-weight: 500;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
    }
    .nav-tabs .nav-link:hover {
        color: #111827;
        isolation: isolate;
        background: rgba(0,0,0,0.02);
    }
    .nav-tabs .nav-link.active {
        color: #2563eb;
        background: transparent;
        border-bottom: 2px solid #2563eb;
    }
    .badge-count {
        font-size: 0.7em;
        padding: 0.25em 0.6em;
        border-radius: 999px;
        margin-left: 6px;
        background: #e5e7eb;
        color: #4b5563;
        transition: all 0.2s;
    }
    .nav-link.active .badge-count {
        background: #eff6ff;
        color: #2563eb;
    }
    
    /* Table Styling */
    .table thead th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        color: #6b7280;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    .badge-status {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .bg-soft-warning { background: #fffbeb; color: #b45309; }
    .bg-soft-success { background: #f0fdf4; color: #15803d; }
    .bg-soft-danger { background: #fef2f2; color: #b91c1c; }
    .bg-soft-info { background: #eff6ff; color: #1d4ed8; }
    
    /* Drag Handle */
    .drag-handle { cursor: grab; color: #adb5bd; }
    .drag-handle:active { cursor: grabbing; color: #6b7280; }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h3 fw-bold text-gray-800">Auto Content AI</h1>
        <p class="text-muted small mb-0">Kelola dan otomatisasi pembuatan konten blog dengan AI.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <!-- New Config Button -->
        <a href="settings.php?tab=ai_brain" class="btn btn-sm btn-white shadow-sm fw-medium">
            <i class="fa-solid fa-gears me-1 text-secondary"></i> Konfigurasi AI
        </a>

        <!-- Schedule Button -->
        <button type="button" class="btn btn-sm btn-white shadow-sm fw-medium" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            <i class="fa-solid fa-calendar-clock me-1 text-primary"></i> Penjadwalan
            <?php if(($settings['ai_schedule_enabled'] ?? '0') == '1'): ?>
                <span class="badge bg-success ms-1 rounded-pill" style="font-size: 0.6rem;">ON</span>
            <?php endif; ?>
        </button>

        <!-- Turbo Mode Toggle -->
        <div class="d-inline-flex align-items-center bg-white border rounded ps-2 pe-1 shadow-sm" style="height: 31px;" title="Turbo Mode: Cron Job akan memproses antrian setiap menit sampai habis">
            <div class="form-check form-switch m-0 d-flex align-items-center">
                <input class="form-check-input mt-0" type="checkbox" role="switch" id="turboModeSwitch" <?php echo ($settings['worker_turbo_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                <label class="form-check-label small fw-bold ms-2 me-2 text-secondary" for="turboModeSwitch" style="cursor:pointer; font-size: 0.8rem;">
                    <i class="fa-solid fa-rocket me-1 <?php echo ($settings['worker_turbo_mode'] ?? '0') == '1' ? 'text-danger animate-pulse' : ''; ?>" id="turboIcon"></i> TURBO
                </label>
            </div>
            <!-- Delay Input -->
            <div class="border-start ps-2 d-flex align-items-center" style="height: 20px;" title="Jeda antar artikel (Menit)">
                <i class="fa-regular fa-clock text-muted me-1" style="font-size: 0.7rem;"></i>
                <input type="number" id="turboDelayInput" class="form-control form-control-sm border-0 bg-transparent p-0 text-center fw-bold text-dark" style="width: 36px; height: 20px; font-size: 0.8rem;" min="3" max="360" value="<?php echo $settings['worker_delay_minutes'] ?? 3; ?>">
                <span class="text-muted ms-1" style="font-size: 0.7rem;">m</span>
            </div>
        </div>

        <!-- Actions -->
        <div class="dropdown">
             <button class="btn btn-sm btn-white shadow-sm dropdown-toggle fw-medium" type="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-wrench me-1 text-secondary"></i> Tools
             </button>
             <ul class="dropdown-menu shadow border-0">
                <li>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Reset semua status gagal menjadi pending?')">
                        <button type="submit" name="reset_failed" class="dropdown-item small">
                            <i class="fa-solid fa-rotate-left me-2 text-warning"></i> Reset Gagal
                        </button>
                    </form>
                </li>
                <li>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Hapus semua antrian (kecuali yang sudah selesai)?')">
                        <button type="submit" name="clear_queue" class="dropdown-item small text-danger">
                            <i class="fa-solid fa-trash-can me-2"></i> Bersihkan Antrian
                        </button>
                    </form>
                </li>
             </ul>
        </div>

        <button type="button" class="btn btn-sm btn-primary shadow fw-bold px-3" data-bs-toggle="modal" data-bs-target="#runBotModal">
            <i class="fa-solid fa-bolt me-1"></i> Jalankan Bot
        </button>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <!-- Tabs Header -->
    <div class="card-header bg-white border-bottom px-4 pt-3 pb-0">
        <ul class="nav nav-tabs card-header-tabs" id="statusTabs">
            <li class="nav-item">
                <a class="nav-link active" href="#" data-status="">
                    Semua <span class="badge-count" id="count-all">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-status="pending">
                    Pending <span class="badge-count" id="count-pending">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-status="processing">
                    Processing <span class="badge-count" id="count-processing">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-status="done">
                    Selesai <span class="badge-count" id="count-done">0</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-status="failed">
                    Gagal <span class="badge-count" id="count-failed">0</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Toolbar -->
    <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="input-group input-group-sm" style="max-width: 300px;">
            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" id="topicSearch" class="form-control bg-light border-start-0" placeholder="Cari topik...">
        </div>
        <div>
            <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addKeywordModal">
                <i class="fa-solid fa-plus me-1"></i> Tambah Topik Baru
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th class="ps-4" style="width: 40%;">Topic / Keyword</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 25%;">Diproses Pada</th>
                    <th style="width: 10%;">Preview</th>
                    <th class="text-end pe-4" style="width: 10%;">Aksi</th>
                </tr>
            </thead>
            <tbody id="topicTableBody">
                <tr><td colspan="5" class="text-center py-5 text-muted">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted" id="paginationInfo">Menampilkan ... data</div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0 shadow-none" id="topicPagination"></ul>
        </nav>
    </div>
</div>

<!-- Modal Run Bot Loop -->
<div class="modal fade" id="runBotModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-robot me-2"></i>AI Processor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="closeBotModal" onclick="stopBotLoop()"></button>
            </div>
            
            <!-- Config Stage -->
            <div class="modal-body p-4" id="botConfigView">
                <div class="text-center mb-4">
                    <div class="avatar-lg bg-soft-primary text-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px; height:64px; font-size:28px;">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <h5 class="fw-bold text-gray-800">Eksekusi Antrian</h5>
                    <p class="text-muted small">Jalankan pembuatan artikel secara manual. Proses ini akan berjalan di browser Anda.</p>
                </div>
                
                <div class="form-floating mb-4">
                    <select class="form-select bg-light border-0 fw-medium" id="botInterval">
                        <option value="0">⚡ Instan (Tanpa Jeda)</option>
                        <option value="1">⏱️ 1 Menit (Aman)</option>
                        <option value="2">🍵 2 Menit (Santai)</option>
                        <option value="5">🛡️ 5 Menit (Sangat Aman)</option>
                    </select>
                    <label>Jeda Antar Artikel</label>
                </div>
                
                <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-sm" onclick="startBotLoop()">
                    <i class="fa-solid fa-play me-2"></i> Mulai Proses
                </button>
            </div>

            <!-- Running Stage -->
            <div class="modal-body p-0 d-none" id="botRunView">
                <div class="bg-dark text-white p-4 text-center position-relative overflow-hidden">
                    <div class="spinner-border text-info mb-3" role="status" id="botSpinner" style="width: 3rem; height: 3rem;"></div>
                    <h5 id="botStatusTitle" class="fw-bold mb-1">Memproses AI...</h5>
                    <p class="text-white-50 small mb-0 font-monospace" id="botStatusDesc">Menghubungi Provider...</p>
                    
                    <!-- Aesthetic Background Circles -->
                    <div class="position-absolute top-0 start-0 translate-middle rounded-circle bg-white opacity-10" style="width: 200px; height: 200px; opacity: 0.05;"></div>
                    <div class="position-absolute bottom-0 end-0 translate-middle rounded-circle bg-info opacity-10" style="width: 150px; height: 150px; opacity: 0.1;"></div>
                </div>
                
                <div class="p-0 bg-light border-bottom">
                    <div class="progress" style="height: 6px; border-radius: 0;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" id="botProgressBar" style="width: 100%"></div>
                    </div>
                </div>
                
                <div class="p-3 bg-black" style="height: 250px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 0.8rem; line-height: 1.5;" id="botLog">
                    <div class="text-success">> System Ready.</div>
                </div>
                
                <div class="p-3 text-center border-top bg-white">
                    <button class="btn btn-outline-danger btn-sm rounded-pill px-4 btn-stop-bot" onclick="stopBotLoop()">
                        <i class="fa-solid fa-stop me-1"></i> Hentikan Proses
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="addKeywordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Tambah Topic Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pb-0">
                    <p class="text-muted small mb-3">Masukkan ide topik atau kata kunci yang ingin dibuatkan artikelnya. (Satu topik per baris)</p>
                    <textarea name="keywords" class="form-control p-3 bg-light" rows="6" placeholder="Contoh:&#10;Cara membersihkan sofa kain&#10;Tips merawat springbed agar awet" required style="resize: none;"></textarea>
                </div>
                <div class="modal-footer border-top-0 pt-3 pb-4 px-3">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_keyword" class="btn btn-primary rounded-pill px-4">Tambah Antrian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Schedule Settings -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-clock me-2 text-primary"></i>Jadwal Otomatis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <div class="mb-4">
                        <div class="form-check form-switch card p-3 shadow-sm border bg-light d-flex flex-row align-items-center gap-3">
                            <input type="hidden" name="ai_schedule_enabled" value="0">
                            <input class="form-check-input ms-0 mt-0" style="font-size: 1.5rem;" type="checkbox" name="ai_schedule_enabled" id="schedToggle" value="1" <?php echo ($settings['ai_schedule_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <div>
                                <label class="form-check-label fw-bold d-block" for="schedToggle">Bot Terjadwal</label>
                                <span class="text-muted small">Bot akan bekerja otomatis di latar belakang.</span>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <!-- Mode Selector -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted">Mode Penjadwalan</label>
                            <select name="ai_schedule_mode" class="form-select bg-light border-0 fw-medium" id="scheduleMode" onchange="toggleScheduleOptions()">
                                <option value="smart" <?php echo ($settings['ai_schedule_mode'] ?? 'smart') == 'smart' ? 'selected' : ''; ?>>🔥 Smart Schedule (Jam Sibuk)</option>
                                <option value="interval" <?php echo ($settings['ai_schedule_mode'] ?? '') == 'interval' ? 'selected' : ''; ?>>⏱️ Interval Harian</option>
                            </select>
                            <div class="form-text small" id="smartDesc">Posting otomatis di jam ramai: 09:00, 12:00, 17:00, 20:00</div>
                        </div>

                        <!-- Frequency Cap -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Maks. Artikel / Hari</label>
                            <input type="number" name="ai_schedule_frequency" class="form-control" value="<?php echo htmlspecialchars($settings['ai_schedule_frequency'] ?? '3'); ?>" min="1" max="20">
                        </div>

                        <!-- Interval Days (Only for Interval Mode) -->
                        <div class="col-md-6 d-none" id="intervalInput">
                            <label class="form-label small fw-bold text-uppercase text-muted">Interval Jeda</label>
                            <div class="input-group">
                                <input type="number" name="ai_schedule_interval" class="form-control" value="<?php echo htmlspecialchars($settings['ai_schedule_interval'] ?? '1'); ?>" min="1">
                                <span class="input-group-text bg-white text-muted">Hari</span>
                            </div>
                        </div>

                        <!-- Execution Time (Only for Interval Mode) -->
                        <div class="col-md-6 d-none" id="timeInput">
                            <label class="form-label small fw-bold text-uppercase text-muted">Waktu Eksekusi</label>
                            <input type="time" name="ai_schedule_time" class="form-control" value="<?php echo htmlspecialchars($settings['ai_schedule_time'] ?? '08:00'); ?>">
                        </div>
                        
                        <!-- Active Days -->
                        <div class="col-12">
                            <label class="form-label small fw-bold text-uppercase text-muted d-block">Hari Aktif</label>
                            <div class="btn-group w-100" role="group">
                                <?php 
                                    $activeDays = json_decode($settings['ai_schedule_days'] ?? '[]', true) ?: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                                    $days = ['Mon'=>'Sen', 'Tue'=>'Sel', 'Wed'=>'Rab', 'Thu'=>'Kam', 'Fri'=>'Jum', 'Sat'=>'Sab', 'Sun'=>'Min'];
                                    foreach($days as $key => $label): 
                                        $checked = in_array($key, $activeDays) ? 'checked' : '';
                                ?>
                                    <input type="checkbox" class="btn-check" name="ai_schedule_days[]" id="day_<?php echo $key; ?>" value="<?php echo $key; ?>" <?php echo $checked; ?>>
                                    <label class="btn btn-outline-primary btn-sm" for="day_<?php echo $key; ?>"><?php echo $label; ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-sm btn-white border" id="btnTestSchedule">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Cek Status Cron
                    </button>
                    <button type="submit" name="save_schedule" class="btn btn-primary px-4 ms-auto">Simpan Konfigurasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleScheduleOptions() {
        const mode = document.getElementById('scheduleMode').value;
        const smartDesc = document.getElementById('smartDesc');
        const intervalInput = document.getElementById('intervalInput');
        const timeInput = document.getElementById('timeInput');
        
        if (mode === 'smart') {
            smartDesc.classList.remove('d-none');
            intervalInput.classList.add('d-none');
            timeInput.classList.add('d-none');
        } else {
            smartDesc.classList.add('d-none');
            intervalInput.classList.remove('d-none');
            timeInput.classList.remove('d-none');
        }
    }
    // Run on load
    document.addEventListener("DOMContentLoaded", toggleScheduleOptions);
</script>

<script>
    // --- Aesthetic Bot Loop Logic ---
    let botRunning = false;
    let botTimer = null;

    function logBot(msg, type = 'text-white') {
        const time = new Date().toLocaleTimeString('id-ID', {hour12:false});
        $('#botLog').prepend(`<div class="${type} mb-1"><span class="text-secondary">[${time}]</span> ${msg}</div>`);
    }

    function startBotLoop() {
        $('#botConfigView').addClass('d-none');
        $('#botRunView').removeClass('d-none');
        $('#botLog').html(''); // Clear log
        botRunning = true;
        logBot('> Memulai proses antrian...', 'text-info');
        processNextItem();
    }

    function stopBotLoop() {
        botRunning = false;
        clearTimeout(botTimer);
        $('#botStatusTitle').text('Dihentikan');
        $('#botStatusDesc').text('Proses dihentikan oleh pengguna.');
        $('#botSpinner').addClass('d-none');
        $('.btn-stop-bot').prop('disabled', true);
        logBot('> Proses dimatikan manual.', 'text-danger');
        
        setTimeout(() => {
            // Reset UI after 2s
            $('#runBotModal').modal('hide');
            setTimeout(() => {
                $('#botConfigView').removeClass('d-none');
                $('#botRunView').addClass('d-none');
                $('.btn-stop-bot').prop('disabled', false);
                $('#botSpinner').removeClass('d-none');
                loadTopics(); updateCounts();
            }, 500);
        }, 2000);
    }

    function processNextItem() {
        if (!botRunning) return;

        // Visual Reset
        $('#botStatusTitle').text('Memproses AI...');
        $('#botStatusDesc').text('Sedang menulis artikel baru...');
        $('#botProgressBar').css('width', '100%').addClass('progress-bar-animated');
        
        $.ajax({
            url: 'api/process_auto_content.php',
            method: 'POST',
            dataType: 'json',
            success: function(res) {
                if (!botRunning) return;

                if (res.status === 'success') {
                    logBot(`✅ ${res.message}`, 'text-success');
                    scheduleNext();
                } else {
                    // Check if queue empty or error
                    if (res.message.includes('Antrian kosong')) {
                        logBot('🏁 Antrian Selesai.', 'text-warning');
                        finishBot();
                    } else {
                        logBot(`❌ Error: ${res.message}`, 'text-danger');
                        // On error, maybe wait a bit then retry or stop? Let's stop to be safe.
                         logBot('⚠️ Menghentikan karena error.', 'text-danger');
                        finishBot();
                    }
                }
            },
            error: function(xhr) {
                logBot(`❌ Connection Error: ${xhr.statusText}`, 'text-danger');
                finishBot();
            }
        });
    }

    function scheduleNext() {
        const minutes = parseInt($('#botInterval').val());
        if (minutes <= 0) {
            logBot('> Lanjut ke item berikutnya...', 'text-muted');
            setTimeout(processNextItem, 1000); // 1s safe delay
            return;
        }

        let secondsLeft = minutes * 60;
        $('#botStatusTitle').text('Menunggu Jeda...');
        $('#botProgressBar').removeClass('progress-bar-animated');
        
        const totalSeconds = secondsLeft;
        
        // Countdown Loop
        const countLoop = () => {
             if (!botRunning) return;
             if (secondsLeft <= 0) {
                 processNextItem();
                 return;
             }
             
             $('#botStatusDesc').text(`Lanjut dalam ${secondsLeft} detik...`);
             const pct = (secondsLeft / totalSeconds) * 100;
             $('#botProgressBar').css('width', `${pct}%`);
             
             secondsLeft--;
             botTimer = setTimeout(countLoop, 1000);
        };
        countLoop();
    }

    function finishBot() {
        botRunning = false;
        $('#botStatusTitle').text('Selesai');
        $('#botStatusDesc').text('Semua tugas rampung.');
        $('#botSpinner').addClass('d-none');
        logBot('> Tugas selesai! Menutup...', 'text-success');
        
        loadTopics(); updateCounts();

        setTimeout(() => {
            $('#runBotModal').modal('hide');
            // Reset views
            setTimeout(() => {
                $('#botConfigView').removeClass('d-none');
                $('#botRunView').addClass('d-none');
                $('#botSpinner').removeClass('d-none');
            }, 500);
        }, 3000);
    }

    // --- Turbo Mode & Delay Logic ---
    function updateTurboSettings(silent = false) {
        const isOn = $('#turboModeSwitch').is(':checked');
        const delay = $('#turboDelayInput').val();
        const icon = $('#turboIcon');

        // Visual Feedback
        if (isOn) icon.addClass('text-danger animate-pulse');
        else icon.removeClass('text-danger animate-pulse');

        $.post('api/update_worker_mode.php', { mode: isOn ? 1 : 0, delay: delay }, function(res) {
            if (res.status === 'success') {
                if (!silent) {
                    const msg = isOn 
                        ? `<b>🚀 Turbo Mode ON</b><br>Delay: ${delay} menit.<br>Server memproses antrian di background.` 
                        : '<b>💤 Turbo Mode OFF</b><br>Server kembali ke mode jadwal normal.';
                    
                    Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 }).fire({ icon: 'success', html: msg });
                }
            } else {
                 if (!silent) Swal.fire('Error', 'Gagal menyimpan status.', 'error');
            }
        }, 'json');
    }

    $('#turboModeSwitch').on('change', function() { updateTurboSettings(false); });
    $('#turboDelayInput').on('change', function() { updateTurboSettings(true); });

    // --- Global Logic ---
    $('#btnRunNow').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Checking...');
        $.get('api/cron_auto_content.php', function(res) {
            Swal.fire({
                title: 'Cron Status',
                html: '<div class="text-start small bg-light p-3 rounded"><code>' + res.replace(/\n/g, '<br>') + '</code></div>',
                icon: 'info'
            });
            btn.prop('disabled', false).html('<i class="fa-solid fa-clock-rotate-left me-1"></i> Cek Status Cron');
        });
    });

    // --- Topic Table AJAX & Pagination ---
    let currentTopicPage = 1;
    let currentStatus = ''; // Default all
    const topicLimit = 10;
    
    $(document).ready(function() {
        loadTopics();
        
        // Search Logic
        let searchTimeout;
        $('#topicSearch').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentTopicPage = 1;
                loadTopics();
            }, 500);
        });

        // Tabs Logic
        $('#statusTabs .nav-link').on('click', function(e) {
            e.preventDefault();
            $('#statusTabs .nav-link').removeClass('active');
            $(this).addClass('active');
            
            currentStatus = $(this).data('status');
            currentTopicPage = 1;
            loadTopics();
        });
    });

    function loadTopics() {
        const search = $('#topicSearch').val();
        $.ajax({
            url: 'api/get_data.php',
            data: { 
                type: 'auto_content_keywords', 
                page: currentTopicPage, 
                limit: topicLimit, 
                search: search,
                status: currentStatus 
            },
            dataType: 'json',
            success: function(response) {
                renderTopicTable(response.data);
                renderTopicPagination(response.pagination);
                if (response.extras && response.extras.counts) {
                    updateBadgeCounts(response.extras.counts);
                }
                
                // Initialize Sortable if in Pending tab
                initSortable();
            },
            error: function() {
                $('#topicTableBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat data.</td></tr>');
            }
        });
    }

    function updateBadgeCounts(counts) {
        $('#count-all').text(counts.all || 0);
        $('#count-pending').text(counts.pending || 0);
        $('#count-processing').text(counts.processing || 0);
        $('#count-done').text(counts.done || 0);
        $('#count-failed').text(counts.failed || 0);
    }

    function renderTopicTable(data) {
        const tbody = $('#topicTableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada data ditemukan untuk filter ini.</td></tr>');
            return;
        }

        data.forEach((item, index) => {
            let badgeClass, badgeText;
            switch(item.status) {
                case 'done': badgeClass = 'bg-soft-success'; badgeText = 'SUCCESS'; break;
                case 'failed': badgeClass = 'bg-soft-danger'; badgeText = 'FAILED'; break;
                case 'processing': badgeClass = 'bg-soft-info'; badgeText = 'PROCESSING'; break;
                default: badgeClass = 'bg-soft-warning'; badgeText = 'PENDING';
            }

            let resultHtml = '-';
            if (item.article_slug) {
                resultHtml = `<a href="../article.php?slug=${item.article_slug}" target="_blank" class="btn btn-sm btn-light border btn-icon-sm" title="Lihat Artikel"><i class="fa-solid fa-external-link-alt text-primary"></i></a>`;
            } else if (item.article_id) {
                 resultHtml = `<a href="../article.php?id=${item.article_id}" target="_blank" class="btn btn-sm btn-light border btn-icon-sm" title="Lihat Artikel (Legacy)"><i class="fa-solid fa-external-link-alt text-primary"></i></a>`;
            } else if (item.status == 'failed') {
                const err = (item.error_message || '').replace(/'/g, "\\'");
                resultHtml = `<button class="btn btn-sm btn-light text-danger border btn-icon-sm" title="Error Log" onclick="Swal.fire('Error Detail', '${err}', 'error')"><i class="fa-solid fa-triangle-exclamation"></i></button>`;
            }

            const processedDate = item.processed_at ? formatDate(item.processed_at) : '<span class="text-muted small">-</span>';
            
            // Calculate Row Number
            let rowNumber = ((currentTopicPage - 1) * topicLimit + index + 1);
            let orderCol = `<span class="fw-bold text-secondary small">${rowNumber}</span>`;
            
            if (currentStatus === 'pending') {
                // Show Handle + Number for Pending
                orderCol = `
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <i class="fa-solid fa-bars drag-handle text-secondary" style="cursor: grab;" title="Geser untuk urutkan"></i>
                        <span class="fw-bold text-dark small">${rowNumber}</span>
                    </div>
                `;
            }

            const row = `
                <tr data-id="${item.id}">
                    <td class="text-center align-middle">${orderCol}</td>
                    <td class="ps-4">
                        <div class="fw-medium text-dark">${escapeHtml(item.keyword)}</div>
                    </td>
                    <td>
                        <span class="badge badge-status ${badgeClass}">${badgeText}</span>
                    </td>
                    <td class="text-muted small font-monospace">${processedDate}</td>
                    <td>${resultHtml}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-white text-danger border-0" onclick="deleteKeyword(${item.id})" title="Hapus">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {day:'2-digit', month:'short'}) + ' ' + d.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
    }

    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function renderTopicPagination(pagination) {
        const nav = $('#topicPagination');
        const info = $('#paginationInfo');
        nav.empty();
        
        const start = pagination.total_records > 0 ? (pagination.current_page - 1) * pagination.limit + 1 : 0;
        const end = Math.min(pagination.current_page * pagination.limit, pagination.total_records);
        info.text(`Menampilkan ${start} - ${end} dari ${pagination.total_records} data`);

        if (pagination.total_pages <= 1) return;

        // Prev
        const prevDisabled = pagination.current_page == 1 ? 'disabled' : '';
        nav.append(`<li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" onclick="changeTopicPage(${pagination.current_page - 1}); return false;"><i class="fa-solid fa-chevron-left"></i></a>
        </li>`);

        const total = pagination.total_pages;
        const current = pagination.current_page;
        const delta = 1; 
        
        let range = [];
        for (let i = 1; i <= total; i++) {
            if (i == 1 || i == total || (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        let l;
        for (let i of range) {
            if (l) {
                if (i - l === 2) {
                    nav.append(`<li class="page-item"><a class="page-link" href="#" onclick="changeTopicPage(${l + 1}); return false;">${l + 1}</a></li>`);
                } else if (i - l !== 1) {
                    nav.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
                }
            }
            const active = current == i ? 'active shadow-sm' : '';
            nav.append(`<li class="page-item ${active}">
                <a class="page-link" href="#" onclick="changeTopicPage(${i}); return false;">${i}</a>
            </li>`);
            l = i;
        }

        // Next
        const nextDisabled = pagination.current_page == total ? 'disabled' : '';
        nav.append(`<li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" onclick="changeTopicPage(${pagination.current_page + 1}); return false;"><i class="fa-solid fa-chevron-right"></i></a>
        </li>`);
    }

    function changeTopicPage(page) {
        if(page < 1) return;
        currentTopicPage = page;
        loadTopics();
    }

    function deleteKeyword(id) {
        if (!confirm('Hapus kata kunci ini?')) return;
        $.post('auto_content.php', { delete_id: id }, function(res) {
            loadTopics();
        }, 'json');
    }
    let sortable;
    function initSortable() {
        const tbody = document.getElementById('topicTableBody');
        
        // Destroy existing instance to prevent duplicates or wrong state
        if (sortable) {
            sortable.destroy();
            sortable = null;
        }

        // Only enable for Pending tab
        if (currentStatus === 'pending') {
            sortable = new Sortable(tbody, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: function (evt) {
                    const order = sortable.toArray(); // Returns array of data-id
                    
                    // Show saving indicator (optional)
                    const Toast = Swal.mixin({
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 1000,
                        didOpen: (toast) => { toast.onmouseenter = Swal.stopTimer; toast.onmouseleave = Swal.resumeTimer; }
                    });
                    
                    $.ajax({
                        url: 'api/update_order.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ order: order }),
                        success: function(res) {
                            if(res.status === 'success') {
                                Toast.fire({ icon: 'success', title: 'Urutan diperbarui' });
                            }
                        }
                    });
                }
            });
        }
    }
</script>

<?php 
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    echo "<script>Swal.fire('{$s['title']}', '{$s['text']}', '{$s['icon']}');</script>";
    unset($_SESSION['swal']);
}
require_once 'includes/footer.php'; 
?>
