<?php
// admin/services.php
require_once '../db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
checkLogin();

// Handle Delete (API style check or simple GET)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
    
    if ($service) {
        if (!empty($service['image_path']) && file_exists("../" . $service['image_path'])) {
            unlink("../" . $service['image_path']);
        }
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
        logActivity("Delete Layanan", "Menghapus layanan ID: $id (" . ($service['title'] ?? 'Unknown') . ")");
    }
    // Redirect to remove query param
    header("Location: services.php?deleted=1");
    exit;
}

// Handle Add / Edit via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price_start = $_POST['price_start'];
    $image_path = $_POST['current_image'] ?? '';
    
    if (!empty($_FILES['image']['name'])) {
        try {
            $uploadedPath = uploadAndResize($_FILES['image'], "../uploads/services/", "uploads/services/");
            if ($uploadedPath) {
                if (!empty($id) && !empty($image_path) && file_exists("../" . $image_path)) {
                    unlink("../" . $image_path);
                }
                $image_path = $uploadedPath;
            }
        } catch (Exception $e) {
            $_SESSION['swal'] = ['title' => 'Gagal!', 'text' => $e->getMessage(), 'icon' => 'error'];
            header("Location: services.php");
            exit;
        }
    }
    
    if (!empty($id)) {
        $stmt = $pdo->prepare("UPDATE services SET title=?, description=?, price_start=?, image_path=? WHERE id=?");
        $stmt->execute([$title, $description, $price_start, $image_path, $id]);
        logActivity("Update Layanan", "Mengubah layanan: $title");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Layanan berhasil diperbarui.', 'icon' => 'success'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO services (title, description, price_start, image_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $description, $price_start, $image_path]);
        logActivity("Create Layanan", "Menambah layanan baru: $title");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Layanan berhasil ditambahkan.', 'icon' => 'success'];
    }
    
    header("Location: services.php");
    exit;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-primary"><i class="fa-solid fa-bell-concierge me-2"></i>Kelola Layanan</h1>
        <p class="text-muted small mb-0">Atur daftar paket layanan dan harga untuk ditampilkan di halaman utama.</p>
    </div>
    <button type="button" class="btn btn-primary shadow-sm rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="resetForm()">
        <i class="fa-solid fa-plus-circle me-2"></i>Tambah Layanan
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom border-light d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-list-ul me-2"></i>Daftar Layanan Tersedia</h6>
        <div class="input-group input-group-sm" style="width: 250px;">
             <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-search"></i></span>
             <input type="text" id="searchInput" class="form-control bg-light border-start-0" placeholder="Cari layanan...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-4" style="width: 10%;">Gambar</th>
                        <th style="width: 25%;">Nama Layanan</th>
                        <th style="width: 30%;">Keterangan</th>
                        <th style="width: 15%;">Harga</th>
                        <th class="text-center" style="width: 10%;">Statistik</th>
                        <th class="text-end pe-4" style="width: 10%;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="border-top-0">
                     <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-top border-light py-3">
        <nav aria-label="Page navigation" class="mb-0">
            <ul class="pagination justify-content-center mb-0" id="pagination"></ul>
        </nav>
    </div>
</div>

<script>
    let currentPage = 1;
    const limit = 10;
    
    $(document).ready(function() {
        loadData();
        // ... search logic remains ...
        let timeout;
        $('#searchInput').on('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                currentPage = 1;
                loadData();
            }, 500);
        });
    });

    function loadData() {
        const search = $('#searchInput').val();
        
        $.ajax({
            url: 'api/get_data.php',
            data: { type: 'services', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-5 text-muted"><div class="mb-2"><i class="fa-solid fa-bell-concierge text-secondary fs-1 opacity-25"></i></div>Tidak ada layanan ditemukan.</td></tr>');
            return;
        }

        data.forEach(item => {
            const img = item.image_path ? `../${item.image_path}` : '';
            const imgHtml = img ? `<img src="${img}" width="50" height="50" class="rounded-3 border object-fit-cover shadow-sm">` : `<div class="bg-light rounded-3 d-flex align-items-center justify-content-center text-muted border" style="width:50px;height:50px;"><i class="fa-solid fa-image"></i></div>`;
            
            const desc = item.description.length > 60 ? item.description.substring(0, 60) + '...' : item.description;
            const orderCount = item.order_count || 0;
            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;").replace(/`/g, "\\`").replace(/\\/g, "\\\\");

            const row = `
                <tr>
                    <td class="ps-4">${imgHtml}</td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(item.title)}</div>
                    </td>
                    <td><small class="text-muted text-wrap" style="line-height: 1.4;">${escapeHtml(desc)}</small></td>
                    <td><span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Rp ${Number(item.price_start).toLocaleString('id-ID')}</span></td>
                    <td class="text-center">
                        <span class="badge bg-light text-secondary border rounded-pill px-3" data-bs-toggle="tooltip" title="Total Klik Pesan">${orderCount} <i class="fa-solid fa-mouse-pointer ms-1 text-muted" style="font-size: 10px;"></i></span>
                    </td>
                    <td class="text-end pe-4">
                         <button class="btn btn-sm btn-light text-primary border-0 me-1" title="Analitik" onclick="showAnalytics(${item.id})" data-bs-toggle="tooltip">
                            <i class="fa-solid fa-chart-pie"></i>
                        </button>
                        <button class="btn btn-sm btn-light text-secondary border-0 me-1" onclick='editService(${jsonItem})' title="Edit" data-bs-toggle="tooltip">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn-sm btn-light text-danger border-0" onclick="confirmDelete(${item.id})" title="Hapus" data-bs-toggle="tooltip">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
        
        // Re-init tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    }
    
    function escapeHtml(text) {
        if (!text) return "";
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function renderPagination(pagination) {
        const nav = $('#pagination');
        nav.empty();
        if (pagination.total_pages <= 1) return;

        // Prev
        nav.append(`<li class="page-item ${pagination.current_page == 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">Prev</a>
        </li>`);

        for (let i = 1; i <= pagination.total_pages; i++) {
             nav.append(`<li class="page-item ${pagination.current_page == i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
            </li>`);
        }

        // Next
        nav.append(`<li class="page-item ${pagination.current_page == pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">Next</a>
        </li>`);
    }

    function changePage(page) {
        if(page < 1) return;
        currentPage = page;
        loadData();
    }
</script>

<!-- Modal Form -->
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tambah Layanan Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="service_id">
                    <input type="hidden" name="current_image" id="current_image">
                    
                    <div class="mb-3">
                        <label class="form-label">Judul Layanan</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Harga Mulai (Rp)</label>
                        <input type="number" class="form-control" name="price_start" id="price_start" required placeholder="Contoh: 50000">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" id="description" rows="9" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gambar Layanan</label>
                        <input type="file" class="form-control" name="image" accept="image/*">
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengubah gambar (saat edit).</small>
                        <div id="image_preview" class="mt-2 text-center"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('modalTitle').innerText = 'Tambah Layanan Baru';
        document.getElementById('btnSave').innerText = 'Simpan';
        document.getElementById('service_id').value = '';
        document.getElementById('current_image').value = '';
        document.getElementById('title').value = '';
        document.getElementById('price_start').value = '';
        document.getElementById('description').value = '';
        document.getElementById('image_preview').innerHTML = '';
    }

    function editService(data) {
        resetForm();
        document.getElementById('modalTitle').innerText = 'Edit Layanan';
        document.getElementById('btnSave').innerText = 'Simpan Perubahan';
        
        document.getElementById('service_id').value = data.id;
        document.getElementById('current_image').value = data.image_path;
        document.getElementById('title').value = data.title;
        document.getElementById('price_start').value = data.price_start;
        document.getElementById('description').value = data.description;
        
        if (data.image_path) {
            document.getElementById('image_preview').innerHTML = '<img src="../'+data.image_path+'" height="80" class="rounded border">';
        }
        
        new bootstrap.Modal(document.getElementById('serviceModal')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Layanan?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `services.php?delete=${id}`;
            }
        })
    }
    
    // --- Analytics Functions ---
    let analyticChart = null;

    function showAnalytics(id) {
        // Show Modal & Reset
        $('#analyticModal').modal('show');
        $('#analyticContent').addClass('d-none');
        $('#analyticLoading').removeClass('d-none');

        $.ajax({
            url: 'api/get_service_analytics.php',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#analyticLoading').addClass('d-none');
                    $('#analyticContent').removeClass('d-none');
                    
                    $('#anlTitle').text(res.service.title);
                    
                    // Update Summary Cards
                    $('#anlTotal').text(Number(res.service.total_orders).toLocaleString());
                    $('#anlGenuine').text(Number(res.period_stats.genuine_leads).toLocaleString());
                    $('#anlWeek').text(Number(res.period_stats.human_week).toLocaleString());
                    $('#anlMonth').text(Number(res.period_stats.human_month).toLocaleString());
                    $('#anlBot').text(Number(res.period_stats.bot_month).toLocaleString());
                    
                    // Render Chart (Human vs Bot)
                    const ctx = document.getElementById('viewChart').getContext('2d');
                    if(analyticChart) analyticChart.destroy();
                    
                    analyticChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: res.chart.labels,
                            datasets: [
                                {
                                    label: 'Human Clicks',
                                    data: res.chart.human_data,
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                    tension: 0.3,
                                    fill: true,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#fff',
                                    order: 1
                                },
                                {
                                    label: 'Bot Activity',
                                    data: res.chart.bot_data,
                                    borderColor: '#6c757d',
                                    backgroundColor: 'transparent',
                                    borderDash: [5, 5],
                                    tension: 0.3,
                                    fill: false,
                                    pointRadius: 0,
                                    order: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                tooltip: {
                                    mode: 'index',
                                    intersect: false
                                },
                                legend: {
                                    position: 'top',
                                    align: 'end'
                                }
                            },
                            scales: {
                                y: { 
                                    beginAtZero: true, 
                                    ticks: { precision: 0 },
                                    grid: { borderDash: [2, 4] }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });

                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    }
</script>


<!-- Modal Analytics -->
<div class="modal fade" id="analyticModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-white border-bottom-0 pb-0 pt-4 px-4 align-items-center">
                <div>
                     <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Analitik Performa Layanan</h5>
                     <p class="text-muted small mb-0 mt-1">Laporan aktivitas klik tombol pesan untuk: <span id="anlTitle" class="fw-bold text-primary"></span></p>
                </div>
                <button type="button" class="btn-close mb-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="analyticLoading" class="text-center py-5">
                    <div class="spinner-border text-primary text-opacity-25" role="status" style="width: 3rem; height: 3rem;"></div>
                    <p class="mt-3 text-muted small fw-bold">Sedang mengambil data...</p>
                </div>
                
                <div id="analyticContent" class="d-none">
                    <!-- Cards Grid -->
                    <div class="row g-3 mb-4">
                        <!-- Genuine Leads -->
                        <div class="col-md-3 col-6">
                            <div class="card border-0 bg-success-subtle h-100 rounded-4">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-shape bg-white text-success rounded-circle shadow-sm mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-check"></i>
                                    </div>
                                    <h3 class="fw-bold text-dark mb-0" id="anlGenuine">0</h3>
                                    <small class="text-secondary fw-semibold" style="font-size: 0.75rem;">Leads Asli</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 7 Days -->
                         <div class="col-md-3 col-6">
                            <div class="card border-0 bg-primary-subtle h-100 rounded-4">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-shape bg-white text-primary rounded-circle shadow-sm mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-calendar-week"></i>
                                    </div>
                                    <h3 class="fw-bold text-dark mb-0" id="anlWeek">0</h3>
                                    <small class="text-secondary fw-semibold" style="font-size: 0.75rem;">Klik (7 Hari)</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 30 Days -->
                         <div class="col-md-3 col-6">
                            <div class="card border-0 bg-info-subtle h-100 rounded-4">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-shape bg-white text-info rounded-circle shadow-sm mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-calendar-days"></i>
                                    </div>
                                    <h3 class="fw-bold text-dark mb-0" id="anlMonth">0</h3>
                                    <small class="text-secondary fw-semibold" style="font-size: 0.75rem;">Klik (30 Hari)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Bots -->
                        <div class="col-md-3 col-6">
                            <div class="card border-0 bg-secondary-subtle h-100 rounded-4">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-shape bg-white text-secondary rounded-circle shadow-sm mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-robot"></i>
                                    </div>
                                    <h3 class="fw-bold text-dark mb-0" id="anlBot">0</h3>
                                    <small class="text-secondary fw-semibold" style="font-size: 0.75rem;">Aktivitas Bot</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chart Section -->
                    <div class="card border border-light shadow-sm rounded-4">
                        <div class="card-header bg-white border-bottom-0 pt-3 px-3 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-arrow-trend-up me-2"></i>Tren Aktivitas (30 Hari)</h6>
                            <span class="badge bg-light text-dark border">Total Klik: <span id="anlTotal" class="fw-bold">0</span></span>
                        </div>
                        <div class="card-body px-3 pb-3 pt-0">
                             <div style="position: relative; height: 300px;">
                                <canvas id="viewChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    echo "<script>Swal.fire('{$s['title']}', '{$s['text']}', '{$s['icon']}');</script>";
    unset($_SESSION['swal']);
}
if (isset($_GET['deleted'])) {
    echo "<script>Swal.fire('Terhapus!', 'Layanan berhasil dihapus.', 'success');</script>";
}
require_once 'includes/footer.php'; 
?>
