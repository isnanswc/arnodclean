<?php
// admin/testimonials.php
require_once '../db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
checkLogin();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM testimonials WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if ($row) {
        if (!empty($row['image_path']) && file_exists("../" . $row['image_path'])) {
            unlink("../" . $row['image_path']);
        }
        $pdo->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
        logActivity("Delete Testimoni", "Menghapus testimoni ID: $id (" . ($row['name'] ?? 'Unknown') . ")");
    }
    header("Location: testimonials.php?deleted=1");
    exit;
}

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'];
    $location = $_POST['location'];
    $content = $_POST['content'];
    $rating = $_POST['rating'];
    $image_path = $_POST['current_image'] ?? '';
    
    if (!empty($_FILES['image']['name'])) {
        try {
            $uploadedPath = uploadAndResize($_FILES['image'], "../uploads/testimonials/", "uploads/testimonials/");
            if ($uploadedPath) {
                if (!empty($id) && !empty($image_path) && file_exists("../" . $image_path)) {
                    unlink("../" . $image_path);
                }
                $image_path = $uploadedPath;
            }
        } catch (Exception $e) {
            $_SESSION['swal'] = ['title' => 'Gagal!', 'text' => $e->getMessage(), 'icon' => 'error'];
            header("Location: testimonials.php");
            exit;
        }
    }
    
    $platform = $_POST['platform'] ?? 'Website';

    if (!empty($id)) {
        $sql = "UPDATE testimonials SET name=?, location=?, content=?, rating=?, image_path=?, platform=? WHERE id=?";
        $pdo->prepare($sql)->execute([$name, $location, $content, $rating, $image_path, $platform, $id]);
        logActivity("Update Testimoni", "Mengubah testimoni: $name");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Testimoni diperbarui.', 'icon' => 'success'];
    } else {
        $sql = "INSERT INTO testimonials (name, location, content, rating, image_path, platform) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$name, $location, $content, $rating, $image_path, $platform]);
        logActivity("Create Testimoni", "Menambah testimoni: $name");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Testimoni ditambahkan.', 'icon' => 'success'];
    }
    header("Location: testimonials.php");
    exit;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kelola Testimoni</h1>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#testiModal" onclick="resetForm()">
        <i class="fa-solid fa-plus me-2"></i> Tambah Testimoni
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari testimoni...">
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Foto</th>
                        <th>Nama & Lokasi</th>
                        <th>Ulasan</th>
                        <th>Rating</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                     <tr><td colspan="5" class="text-center">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation" class="mt-3">
            <ul class="pagination justify-content-center" id="pagination"></ul>
        </nav>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentPage = 1;
    const limit = 10;
    
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
    });

    function loadData() {
        const search = $('#searchInput').val();
        $.ajax({
            url: 'api/get_data.php',
            data: { type: 'testimonials', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center">Tidak ada data ditemukan.</td></tr>');
            return;
        }

        data.forEach(item => {
            const img = item.image_path ? `../${item.image_path}` : null;
            const imgHtml = img ? `<img src="${img}" width="50" height="50" class="rounded-circle object-fit-cover border">` : '<span class="badge bg-secondary rounded-circle p-3">NA</span>';
            const desc = item.content.length > 60 ? item.content.substring(0, 60) + '...' : item.content;
            
            let stars = '';
            for(let i=0; i < item.rating; i++) stars += '<i class="fa-solid fa-star text-warning small"></i>';
            
            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;");

            const row = `
                <tr>
                    <td>${imgHtml}</td>
                    <td>
                        <div class="fw-bold">${escapeHtml(item.name)}</div>
                        <small class="text-muted"><i class="fa-solid fa-location-dot me-1"></i>${escapeHtml(item.location)}</small>
                    </td>
                    <td><small>${escapeHtml(desc)}</small></td>
                    <td>${stars}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editTesti(${jsonItem})'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${item.id})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
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

        nav.append(`<li class="page-item ${pagination.current_page == 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">Prev</a>
        </li>`);

        for (let i = 1; i <= pagination.total_pages; i++) {
             nav.append(`<li class="page-item ${pagination.current_page == i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
            </li>`);
        }

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
<div class="modal fade" id="testiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tambah Testimoni</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="data_id">
                    <input type="hidden" name="current_image" id="current_image">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Pelanggan</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lokasi (Kecamatan/Kota)</label>
                        <input type="text" class="form-control" name="location" id="location" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rating</label>
                            <select class="form-select" name="rating" id="rating">
                                <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                                <option value="4">⭐⭐⭐⭐ (4)</option>
                                <option value="3">⭐⭐⭐ (3)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sumber Ulasan</label>
                            <select class="form-select" name="platform" id="platform">
                                <option value="Website">Website (Default)</option>
                                <option value="Google">Google Maps</option>
                                <option value="WhatsApp">WhatsApp Chat</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Isi Ulasan</label>
                        <textarea class="form-control" name="content" id="content" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Foto Pelanggan</label>
                        <input type="file" class="form-control" name="image" accept="image/*">
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
        document.getElementById('modalTitle').innerText = 'Tambah Testimoni';
        document.getElementById('btnSave').innerText = 'Simpan';
        document.getElementById('data_id').value = '';
        document.getElementById('current_image').value = '';
        document.getElementById('name').value = '';
        document.getElementById('location').value = '';
        document.getElementById('content').value = '';
        document.getElementById('rating').value = 5;
        document.getElementById('platform').value = 'Website';
        document.getElementById('image_preview').innerHTML = '';
    }

    function editTesti(data) {
        resetForm();
        document.getElementById('modalTitle').innerText = 'Edit Testimoni';
        document.getElementById('btnSave').innerText = 'Simpan Perubahan';
        
        document.getElementById('data_id').value = data.id;
        document.getElementById('current_image').value = data.image_path;
        document.getElementById('name').value = data.name;
        document.getElementById('location').value = data.location;
        document.getElementById('content').value = data.content;
        document.getElementById('rating').value = data.rating;
        document.getElementById('platform').value = data.platform || 'Website';
        
        if (data.image_path) {
            document.getElementById('image_preview').innerHTML = '<img src="../'+data.image_path+'" width="80" class="rounded-circle border">';
        }
        
        new bootstrap.Modal(document.getElementById('testiModal')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Testimoni?',
            text: "Data tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `testimonials.php?delete=${id}`;
            }
        })
    }
</script>

<?php 
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    echo "<script>Swal.fire('{$s['title']}', '{$s['text']}', '{$s['icon']}');</script>";
    unset($_SESSION['swal']);
}
if (isset($_GET['deleted'])) {
    echo "<script>Swal.fire('Terhapus!', 'Testimoni berhasil dihapus.', 'success');</script>";
}
require_once 'includes/footer.php'; 
?>
