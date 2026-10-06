<?php
// admin/locations.php
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

$message = '';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
    if ($stmt->execute([$id])) {
        logActivity("Delete Lokasi", "Menghapus lokasi ID: $id");
        $message = "Lokasi dihapus.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'];
    $address = $_POST['address'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $area = $_POST['area']; // Kota Tangerang, dll

    if (!empty($id)) {
        $stmt = $pdo->prepare("UPDATE locations SET name=?, address=?, latitude=?, longitude=?, name=?, iframe_link=? WHERE id=?");
        // Note: Using 'area' field? Schema didn't have 'area'. I'll add it to 'address' or just stick to 'name'.
        // Let's stick to schema: name, address, latitude, longitude.
        // I will use 'address' to store the Area name if needed for color coding, or just ignore color coding for now.
        // Let's stick to simple Lat/Long.
        $stmt = $pdo->prepare("UPDATE locations SET name=?, address=?, latitude=?, longitude=? WHERE id=?");
        $stmt->execute([$name, $address, $lat, $lng, $id]);
        logActivity("Update Lokasi", "Mengubah lokasi: $name");
        $message = "Lokasi diperbarui.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO locations (name, address, latitude, longitude) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $address, $lat, $lng]);
        logActivity("Create Lokasi", "Menambah lokasi baru: $name");
        $message = "Lokasi ditambahkan.";
    }
}


require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-primary"><i class="fa-solid fa-map-location-dot me-2"></i>Kelola Persebaran Lokasi</h1>
        <p class="text-muted small mb-0">Atur titik jangkauan layanan untuk ditampilkan di peta interaktif website.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4">
        <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
        <div>
            <strong>Berhasil!</strong> <?php echo $message; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Side -->
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 90px; z-index: 100;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold" id="formTitle"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Tambah Lokasi Baru</h6>
                <button class="btn btn-sm btn-light text-muted border-0" onclick="resetForm()" type="button" data-bs-toggle="tooltip" title="Reset Form">
                    <i class="fa-solid fa-arrows-rotate"></i>
                </button>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="id" id="loc_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Nama Lokasi / Area</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-building text-secondary"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="name" id="name" required placeholder="Contoh: BSD City, Gading Serpong...">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">Wilayah (Region)</label>
                        <div class="input-group">
                             <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-map text-secondary"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="address" id="address" list="regionList" required placeholder="Kota Tangerang">
                            <datalist id="regionList">
                                <option value="Kota Tangerang">
                                <option value="Kabupaten Tangerang">
                                <option value="Tangerang Selatan">
                                <option value="Jakarta Barat">
                                <option value="Jakarta Selatan">
                                <option value="Jakarta Pusat">
                                <option value="Jakarta Timur">
                                <option value="Jakarta Utara">
                                <option value="Depok">
                                <option value="Bogor">
                                <option value="Bekasi">
                            </datalist>
                        </div>
                    </div>

                    <!-- Smart Coords -->
                    <div class="mb-3 p-3 bg-light rounded border border-dashed text-center">
                        <label class="form-label fw-bold small text-dark mb-2">Paste Koordinat Google Maps</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="smartCoords" placeholder="-6.xxxx, 106.xxxx">
                            <button class="btn btn-dark" type="button" id="btnParse"><i class="fa-solid fa-paste me-1"></i> Parse</button>
                        </div>
                        <div class="text-xs text-muted mt-2">Salin lat,long dari URL maps lalu klik Parse.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted">Latitude</label>
                            <input type="text" class="form-control form-control-sm bg-light font-monospace" name="latitude" id="lat" required readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">Longitude</label>
                            <input type="text" class="form-control form-control-sm bg-light font-monospace" name="longitude" id="lng" required readonly>
                        </div>
                    </div>

                    <!-- Map Picker -->
                    <div class="mb-4 position-relative">
                        <div id="mapPicker" class="shadow-sm border" style="height: 250px; border-radius: 12px; overflow: hidden;"></div>
                        <div class="position-absolute bottom-0 start-0 m-2 badge bg-white text-dark shadow-sm border opacity-75">
                            <i class="fa-solid fa-hand-pointer me-1"></i> Geser Peta / Marker
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" id="btnSave">
                        <i class="fa-solid fa-save me-2"></i>Simpan Lokasi
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Table Side -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom-0">
                <div class="row align-items-center">
                    <div class="col">
                        <h6 class="mb-0 fw-bold text-secondary">Daftar Jangkauan Area</h6>
                    </div>
                    <div class="col-auto">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Cari area...">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase ls-1">
                        <tr>
                            <th class="ps-4" style="width: 35%;">Nama Area</th>
                            <th style="width: 25%;">Region</th>
                            <th style="width: 25%;">Koordinat</th>
                            <th class="text-end pe-4" style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="border-top-0">
                         <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white py-3 border-top-0">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // --- Map Logic ---
    var startLat = -6.1702;
    var startLng = 106.6403;
    var map = L.map('mapPicker', { zoomControl: false }).setView([startLat, startLng], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(map);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    var marker = L.marker([startLat, startLng], {draggable: true}).addTo(map);

    function updateInputs(lat, lng) {
        document.getElementById('lat').value = lat.toFixed(6);
        document.getElementById('lng').value = lng.toFixed(6);
    }

    // --- Reverse Geocoding (Auto-Fill) ---
    function fetchAddress(lat, lng) {
        const nameInput = document.getElementById('name');
        const regionSelect = document.getElementById('address');
        const originalPlaceholder = nameInput.placeholder;
        
        // Show loading indicator in name input
        nameInput.value = "Mengambil data...";
        nameInput.classList.add('text-muted', 'fst-italic');

        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`;

        fetch(url, {
            headers: { 'User-Agent': 'ArnodCleanAdmin/1.0' }
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.address) {
                const addr = data.address;
                
                // 1. Determine Location Name (Priority: Suburb -> Village -> Quarter -> Road)
                // We format it as "Suburb, City" or just "Suburb"
                const specificPart = addr.suburb || addr.village || addr.quarter || addr.neighbourhood || addr.residential || addr.road || '';
                // Clean up name
                if (specificPart) {
                    nameInput.value = specificPart;
                } else {
                    nameInput.value = ""; 
                }

                // 2. Determine Region
                let region = "Lainnya";
                const city = (addr.city || addr.town || addr.municipality || '').toLowerCase();
                const county = (addr.county || '').toLowerCase();
                const state = (addr.state || '').toLowerCase();
                const cityDistrict = (addr.city_district || '').toLowerCase();

                // Logic Mapping
                if (city.includes('tangerang selatan') || cityDistrict.includes('tangerang selatan')) {
                    region = "Tangerang Selatan";
                } else if ((city.includes('tangerang') && !county.includes('tangerang')) || city.includes('kota tangerang')) {
                    region = "Kota Tangerang";
                } else if (county.includes('tangerang')) {
                    region = "Kabupaten Tangerang";
                } else if (city.includes('jakarta barat') || cityDistrict.includes('jakarta barat')) {
                    region = "Jakarta Barat";
                } else if (city.includes('jakarta selatan') || cityDistrict.includes('jakarta selatan')) {
                    region = "Jakarta Selatan";
                } else {
                    // Fallback: Use City/Town/County directly formatted properly
                    // e.g. "Kota Depok", "Kabupaten Bogor"
                    region = addr.city || addr.town || addr.county || addr.municipality || state || "Lainnya";
                }
                
                regionSelect.value = region;
            } else {
                nameInput.value = "";
            }
        })
        .catch(err => {
            console.error(err);
            nameInput.value = "";
        })
        .finally(() => {
            nameInput.classList.remove('text-muted', 'fst-italic');
            if(nameInput.value === "Mengambil data...") nameInput.value = "";
        });
    }

    marker.on('dragend', function(e) {
        var pos = e.target.getLatLng();
        updateInputs(pos.lat, pos.lng);
        fetchAddress(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateInputs(e.latlng.lat, e.latlng.lng);
        fetchAddress(e.latlng.lat, e.latlng.lng);
    });

    // Smart Parse Logic
    function parseCoords() {
        var input = document.getElementById('smartCoords').value;
        var parts = input.split(',');
        if (parts.length === 2) {
            var lat = parseFloat(parts[0].trim());
            var lng = parseFloat(parts[1].trim());
            if (!isNaN(lat) && !isNaN(lng)) {
                updateInputs(lat, lng);
                fetchAddress(lat, lng);
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
            } else {
                Swal.fire('Format Salah', 'Gunakan format: lat, long (pisahkan dengan koma)', 'warning');
            }
        }
    }
    document.getElementById('smartCoords').addEventListener('change', parseCoords);
    document.getElementById('btnParse').addEventListener('click', parseCoords);

    // --- AJAX Table Logic ---
    let currentPage = 1;
    const limit = 8; 
    
    $(document).ready(function() {
        loadData();
        
        let timeout;
        $('#searchInput').on('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                currentPage = 1;
                loadData();
            }, 500);
        });
        
        // Tooltip init
        $('body').tooltip({ selector: '[data-bs-toggle="tooltip"]' });
    });

    function loadData() {
        const search = $('#searchInput').val();
        $.ajax({
            url: 'api/get_data.php',
            data: { type: 'locations', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="4" class="text-center text-danger py-4">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center py-5 text-muted"><div class="mb-2"><i class="fa-solid fa-map-location text-secondary fs-1 opacity-25"></i></div>Tidak ada lokasi ditemukan.</td></tr>');
            return;
        }

        const regionColors = {
            'Kota Tangerang': 'bg-primary-subtle text-primary border-primary',
            'Kabupaten Tangerang': 'bg-info-subtle text-info-emphasis border-info',
            'Tangerang Selatan': 'bg-success-subtle text-success border-success',
            'Jakarta Barat': 'bg-warning-subtle text-warning-emphasis border-warning',
            'Jakarta Selatan': 'bg-danger-subtle text-danger border-danger'
        };

        data.forEach(item => {
            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;");
            const badgeClass = regionColors[item.address] || 'bg-light text-secondary border';
            
            const row = `
                <tr>
                    <td class="ps-4 fw-bold text-dark">${escapeHtml(item.name)}</td>
                    <td><span class="badge border ${badgeClass} rounded-pill fw-normal px-3 py-1">${escapeHtml(item.address)}</span></td>
                    <td><small class="font-monospace text-muted bg-light px-2 py-1 rounded border">${item.latitude}, ${item.longitude}</small></td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-light text-primary border-0 me-1" onclick='editLocation(${jsonItem})' data-bs-toggle="tooltip" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <a href="locations.php?delete=${item.id}" class="btn btn-sm btn-light text-danger border-0" onclick="return confirm('Hapus lokasi ini?')" data-bs-toggle="tooltip" title="Hapus">
                            <i class="fa-solid fa-trash-can"></i>
                        </a>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function renderPagination(pagination) {
        const nav = $('#pagination');
        nav.empty();
        if (pagination.total_pages <= 1) return;

        // Prev
        const prevDisabled = pagination.current_page == 1 ? 'disabled' : '';
        nav.append(`<li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">Prev</a>
        </li>`);

        // Smart Pagination Logic
        const total = pagination.total_pages;
        const current = pagination.current_page;
        const delta = 2; 
        
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
                    nav.append(`<li class="page-item"><a class="page-link" href="#" onclick="changePage(${l + 1}); return false;">${l + 1}</a></li>`);
                } else if (i - l !== 1) {
                    nav.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
                }
            }
            const active = current == i ? 'active' : '';
            nav.append(`<li class="page-item ${active}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>`);
            l = i;
        }

        // Next
        const nextDisabled = pagination.current_page == total ? 'disabled' : '';
        nav.append(`<li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">Next</a>
        </li>`);

         // Jump to Page Input
        nav.append(`
            <li class="page-item ms-2">
                <div class="input-group" style="width: 130px;">
                    <input type="number" class="form-control" id="pageJump" min="1" max="${total}" placeholder="Page">
                    <button class="btn btn-outline-secondary" type="button" onclick="jumpToPage()">Go</button>
                </div>
            </li>
        `);
    }

    function jumpToPage() {
        const p = parseInt($('#pageJump').val());
        if(p && p > 0) changePage(p);
    }

    function changePage(page) {
        if(page < 1) return;
        currentPage = page;
        loadData();
    }

    // --- Edit Logic ---
    function editLocation(data) {
        document.getElementById('formTitle').innerText = 'Edit Lokasi';
        document.getElementById('btnSave').innerText = 'Simpan Perubahan';
        document.getElementById('loc_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('address').value = data.address; // Select value
        document.getElementById('lat').value = data.latitude;
        document.getElementById('lng').value = data.longitude;
        
        // Update Map
        var lat = parseFloat(data.latitude);
        var lng = parseFloat(data.longitude);
        if(!isNaN(lat) && !isNaN(lng)) {
            marker.setLatLng([lat, lng]);
            map.panTo([lat, lng]);
        }
        
        // Scroll to form on mobile
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('formTitle').innerText = 'Tambah Lokasi';
        document.getElementById('btnSave').innerText = 'Simpan';
        document.getElementById('loc_id').value = '';
        document.getElementById('name').value = '';
        document.getElementById('address').value = ''; // Reset to empty for input
        document.getElementById('lat').value = '';
        document.getElementById('lng').value = '';
        
        map.setView([startLat, startLng], 10);
        marker.setLatLng([startLat, startLng]);
    }
</script>

<?php require_once 'includes/footer.php'; ?>
