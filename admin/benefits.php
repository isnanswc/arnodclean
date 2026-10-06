<?php
// admin/benefits.php
require_once '../db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
checkLogin();

// Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM benefits WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if ($row) {
        if (!empty($row['image_path']) && file_exists("../" . $row['image_path'])) {
            unlink("../" . $row['image_path']);
        }
        $pdo->prepare("DELETE FROM benefits WHERE id = ?")->execute([$id]);
        logActivity("Delete Keunggulan", "Menghapus keunggulan ID: $id (" . ($row['title'] ?? 'Unknown') . ")");
    }
    header("Location: benefits.php?deleted=1");
    exit;
}

// POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'];
    $description = $_POST['description'];
    $media_type = $_POST['media_type'] ?? 'image';
    $image_path = $_POST['current_image'] ?? '';
    
    if (!empty($_FILES['image']['name'])) {
        try {
            $uploadedPath = uploadAndResize($_FILES['image'], "../uploads/benefits/", "uploads/benefits/");
            if ($uploadedPath) {
                if (!empty($id) && !empty($image_path) && file_exists("../" . $image_path)) {
                    unlink("../" . $image_path);
                }
                $image_path = $uploadedPath;
            }
        } catch (Exception $e) {
            $_SESSION['swal'] = ['title' => 'Gagal!', 'text' => $e->getMessage(), 'icon' => 'error'];
            header("Location: benefits.php");
            exit;
        }
    }
    
    if (!empty($id)) {
        $pdo->prepare("UPDATE benefits SET title=?, description=?, image_path=?, media_type=? WHERE id=?")
            ->execute([$title, $description, $image_path, $media_type, $id]);
        logActivity("Update Keunggulan", "Mengubah keunggulan: $title");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Keunggulan diperbarui.', 'icon' => 'success'];
    } else {
        $pdo->prepare("INSERT INTO benefits (title, description, image_path, media_type) VALUES (?, ?, ?, ?)")
            ->execute([$title, $description, $image_path, $media_type]);
        logActivity("Create Keunggulan", "Menambah keunggulan: $title");
        $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Keunggulan ditambahkan.', 'icon' => 'success'];
    }
    header("Location: benefits.php");
    exit;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kelola Keunggulan</h1>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#benefitModal" onclick="resetForm()">
        <i class="fa-solid fa-plus me-2"></i> Tambah Keunggulan
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari keunggulan...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Icon/Gambar</th>
                        <th>Judul</th>
                        <th>Deskripsi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                     <tr><td colspan="4" class="text-center">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation" class="mt-3">
            <ul class="pagination justify-content-center" id="pagination"></ul>
        </nav>
    </div>
</div>

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
            data: { type: 'benefits', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="4" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center">Tidak ada data ditemukan.</td></tr>');
            return;
        }

        data.forEach(item => {
            const media = item.image_path ? `../${item.image_path}` : '';
            let mediaHtml = '<span class="badge bg-secondary">No Media</span>';
            
            if (media) {
                if (item.media_type === 'video') {
                    mediaHtml = `<div class="position-relative" style="width: 50px; height: 50px; overflow: hidden; border-radius: 4px;">
                                    <video src="${media}" width="50" muted style="object-fit: cover; height: 100%;"></video>
                                    <span class="position-absolute top-50 start-50 translate-middle text-white" style="font-size: 0.8rem;">
                                        <i class="fa-solid fa-play"></i>
                                    </span>
                                 </div>`;
                } else {
                    mediaHtml = `<img src="${media}" width="50" class="rounded">`;
                }
            }
            const desc = item.description;
            
            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;").replace(/`/g, "\\`").replace(/\\/g, "\\\\");

            const row = `
                <tr>
                    <td>${mediaHtml}</td>
                    <td class="fw-bold">${escapeHtml(item.title)}</td>
                    <td><small>${escapeHtml(desc)}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick='editBenefit(${jsonItem})'>
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

<!-- Modal -->
<div class="modal fade" id="benefitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tambah Keunggulan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="data_id">
                    <input type="hidden" name="current_image" id="current_image">
                    
                    <div class="mb-3">
                        <label class="form-label">Judul Keunggulan</label>
                        <input type="text" class="form-control" name="title" id="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Singkat</label>
                        <textarea class="form-control" name="description" id="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipe Media</label>
                        <select class="form-select" name="media_type" id="media_type" onchange="updateAccept()">
                            <option value="image">Gambar (JPG, PNG, WebP)</option>
                            <option value="video">Video (MP4, WebM)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="label_media">Icon / Gambar</label>
                        <input type="file" class="form-control" name="image" id="file_input" accept="image/*">
                        <div id="media_preview" class="mt-2 text-center"></div>
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
        document.getElementById('modalTitle').innerText = 'Tambah Keunggulan';
        document.getElementById('btnSave').innerText = 'Simpan';
        document.getElementById('data_id').value = '';
        document.getElementById('current_image').value = '';
        document.getElementById('title').value = '';
        document.getElementById('description').value = '';
        document.getElementById('media_type').value = 'image';
        document.getElementById('media_preview').innerHTML = '';
        updateAccept();
    }

    function updateAccept() {
        const type = document.getElementById('media_type').value;
        const input = document.getElementById('file_input');
        const label = document.getElementById('label_media');
        
        if (type === 'video') {
            input.accept = 'video/mp4,video/webm';
            label.innerText = 'File Video';
        } else {
            input.accept = 'image/*';
            label.innerText = 'Icon / Gambar';
        }
    }

    function editBenefit(data) {
        resetForm();
        document.getElementById('modalTitle').innerText = 'Edit Keunggulan';
        document.getElementById('btnSave').innerText = 'Simpan Perubahan';
        
        document.getElementById('data_id').value = data.id;
        document.getElementById('current_image').value = data.image_path;
        document.getElementById('title').value = data.title;
        document.getElementById('description').value = data.description;
        document.getElementById('media_type').value = data.media_type || 'image';
        
        if (data.image_path) {
            if (data.media_type === 'video') {
                document.getElementById('media_preview').innerHTML = '<video src="../'+data.image_path+'" width="100" class="rounded" controls></video>';
            } else {
                document.getElementById('media_preview').innerHTML = '<img src="../'+data.image_path+'" width="60" class="rounded">';
            }
        }
        
        updateAccept();
        
        new bootstrap.Modal(document.getElementById('benefitModal')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `benefits.php?delete=${id}`;
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
    echo "<script>Swal.fire('Terhapus!', 'Data berhasil dihapus.', 'success');</script>";
}
require_once 'includes/footer.php'; 
?>
