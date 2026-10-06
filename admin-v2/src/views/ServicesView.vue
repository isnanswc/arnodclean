<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  Sparkles,
  Plus,
  Search,
  Edit2,
  Trash2,
  Image as ImageIcon,
  DollarSign,
  Loader2,
  CheckCircle2,
  AlertCircle,
  X,
  Upload,
  RefreshCw
} from 'lucide-vue-next'

interface Service {
  id: number
  title: string
  description: string | null
  price_start: string | null
  image_path: string | null
  created_at: string
  order_count?: number
}

const services = ref<Service[]>([])
const isLoading = ref(true)
const searchQuery = ref('')

// Modal state
const isModalOpen = ref(false)
const isSubmitting = ref(false)
const modalError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Form state
const formId = ref<number | null>(null)
const formTitle = ref('')
const formPriceStart = ref('')
const formDescription = ref('')
const formCurrentImage = ref('')
const selectedFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

// Fetch services list
async function fetchServices() {
  isLoading.value = true
  try {
    const res = await apiClient.get('/v2/data.php?type=services')
    if (res.data?.status === 'success') {
      services.value = res.data.data
    }
  } catch (err: any) {
    console.error('Error fetching services:', err)
  } finally {
    isLoading.value = false
  }
}

// Filtered list
const filteredServices = computed(() => {
  if (!searchQuery.value) return services.value
  const q = searchQuery.value.toLowerCase()
  return services.value.filter(
    (s) =>
      s.title.toLowerCase().includes(q) ||
      (s.description && s.description.toLowerCase().includes(q))
  )
})

// Format currency
function formatCurrency(val: string | number | null) {
  if (!val) return 'Rp 0'
  const num = typeof val === 'number' ? val : parseInt(val.toString().replace(/\D/g, ''), 10)
  if (isNaN(num)) return val
  return 'Rp ' + num.toLocaleString('id-ID')
}

// Open modal for Create
function openCreateModal() {
  formId.value = null
  formTitle.value = ''
  formPriceStart.value = ''
  formDescription.value = ''
  formCurrentImage.value = ''
  selectedFile.value = null
  imagePreview.value = null
  modalError.value = null
  isModalOpen.value = true
}

// Open modal for Edit
function openEditModal(service: Service) {
  formId.value = service.id
  formTitle.value = service.title
  formPriceStart.value = service.price_start || ''
  formDescription.value = service.description || ''
  formCurrentImage.value = service.image_path || ''
  selectedFile.value = null
  imagePreview.value = service.image_path ? getImageUrl(service.image_path) : null
  modalError.value = null
  isModalOpen.value = true
}

// Handle file selection
function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    selectedFile.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

// Submit Form (Create / Edit)
async function handleSubmit() {
  if (!formTitle.value.trim()) {
    modalError.value = 'Nama layanan wajib diisi.'
    return
  }

  isSubmitting.value = true
  modalError.value = null

  try {
    const formData = new FormData()
    if (formId.value) {
      formData.append('id', formId.value.toString())
    }
    formData.append('title', formTitle.value.trim())
    formData.append('price_start', formPriceStart.value.trim())
    formData.append('description', formDescription.value.trim())
    formData.append('current_image', formCurrentImage.value)

    if (selectedFile.value) {
      formData.append('image', selectedFile.value)
    }

    const res = await apiClient.post('/v2/data.php?type=services', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.status === 'success') {
      isModalOpen.value = false
      successMessage.value = res.data.message || 'Layanan berhasil disimpan.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchServices()
    } else {
      modalError.value = res.data?.message || 'Gagal menyimpan layanan.'
    }
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Terjadi kesalahan sistem saat menyimpan.'
  } finally {
    isSubmitting.value = false
  }
}

// Delete service
async function handleDelete(service: Service) {
  if (!confirm(`Yakin ingin menghapus layanan "${service.title}"?`)) {
    return
  }

  try {
    const res = await apiClient.post('/v2/data.php?type=services&action=delete', {
      id: service.id,
    })
    if (res.data?.status === 'success') {
      successMessage.value = 'Layanan berhasil dihapus.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchServices()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus layanan.')
  }
}

onMounted(() => {
  fetchServices()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Layanan</h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola katalog layanan, harga mulai, foto dokumentasi, dan deskripsi kebersihan.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchServices"
          :disabled="isLoading"
          class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 hover:text-blue-600 hover:border-blue-200 text-xs font-semibold shadow-sm transition-all"
        >
          <RefreshCw :class="['w-3.5 h-3.5', isLoading ? 'animate-spin text-blue-600' : '']" />
          <span>Muat Ulang</span>
        </button>

        <button
          @click="openCreateModal"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Tambah Layanan</span>
        </button>
      </div>
    </div>

    <!-- Success Toast Alert -->
    <div
      v-if="successMessage"
      class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-center justify-between gap-3 animate-fadeIn shadow-sm"
    >
      <div class="flex items-center gap-2.5">
        <CheckCircle2 class="w-5 h-5 text-emerald-600 shrink-0" />
        <span>{{ successMessage }}</span>
      </div>
      <button @click="successMessage = null" class="text-emerald-600 hover:text-emerald-800">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="relative w-full sm:w-80">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <Search class="w-4 h-4" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama atau deskripsi..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
        />
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Total: <span class="font-bold text-slate-800">{{ filteredServices.length }}</span> Layanan
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 text-center space-y-3">
      <Loader2 class="w-8 h-8 animate-spin text-blue-600 mx-auto" />
      <p class="text-xs text-slate-500">Memuat data layanan...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredServices.length === 0"
      class="py-16 px-4 rounded-3xl bg-white border border-slate-100 shadow-subtle text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto">
        <Sparkles class="w-6 h-6" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada data layanan</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        {{ searchQuery ? 'Tidak ada layanan yang sesuai dengan kata kunci pencarian.' : 'Belum ada layanan yang ditambahkan. Silakan klik tombol Tambah Layanan.' }}
      </p>
    </div>

    <!-- Services Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="service in filteredServices"
        :key="service.id"
        class="rounded-2xl bg-white border border-slate-100 shadow-subtle hover:shadow-card transition-all duration-200 overflow-hidden flex flex-col justify-between group"
      >
        <!-- Service Card Top (Image & Info) -->
        <div>
          <!-- Image Container -->
          <div class="relative h-44 bg-slate-100 overflow-hidden">
            <img
              v-if="service.image_path"
              :src="getImageUrl(service.image_path)"
              :alt="service.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
              @error="(e: any) => { e.target.style.display = 'none'; if (e.target.nextElementSibling) e.target.nextElementSibling.style.display = 'flex'; }"
            />
            <div
              :style="{ display: service.image_path ? 'none' : 'flex' }"
              class="w-full h-full flex-col items-center justify-center text-slate-400 gap-2 bg-slate-100"
            >
              <ImageIcon class="w-8 h-8" />
              <span class="text-[11px]">Foto Belum Diunggah</span>
            </div>

            <!-- Price Badge -->
            <div class="absolute bottom-3 left-3 px-3 py-1 rounded-xl bg-white/90 backdrop-blur-md text-blue-700 font-bold text-xs shadow-sm flex items-center gap-1 border border-white">
              <span>Mulai</span>
              <span class="text-blue-900">{{ formatCurrency(service.price_start) }}</span>
            </div>
          </div>

          <!-- Content Details -->
          <div class="p-5 space-y-2">
            <h3 class="text-base font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
              {{ service.title }}
            </h3>
            <p class="text-xs text-slate-600 line-clamp-3 leading-relaxed">
              {{ service.description || 'Tidak ada deskripsi detail layanan.' }}
            </p>
          </div>
        </div>

        <!-- Service Card Footer (Actions) -->
        <div class="px-5 py-3.5 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="text-[11px] text-slate-400">
            ID: #{{ service.id }}
          </span>

          <div class="flex items-center gap-1.5">
            <button
              @click="openEditModal(service)"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-blue-300 hover:text-blue-600 text-slate-700 font-semibold transition-colors shadow-sm"
            >
              <Edit2 class="w-3.5 h-3.5" />
              <span>Edit</span>
            </button>

            <button
              @click="handleDelete(service)"
              class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-red-300 hover:text-red-600 text-slate-400 font-semibold transition-colors shadow-sm"
              title="Hapus Layanan"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL FORM (CREATE / EDIT) -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full p-4 sm:p-7 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <div class="min-w-0 flex-1">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 truncate sm:whitespace-normal">
              {{ formId ? 'Edit Layanan' : 'Tambah Layanan Baru' }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">
              {{ formId ? 'Perbarui informasi dan gambar layanan.' : 'Isi form di bawah untuk menambahkan layanan baru.' }}
            </p>
          </div>
          <button
            @click="isModalOpen = false"
            class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors shrink-0"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Error Alert -->
        <div
          v-if="modalError"
          class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-center gap-2.5"
        >
          <AlertCircle class="w-4 h-4 shrink-0 text-red-500" />
          <span>{{ modalError }}</span>
        </div>

        <!-- Form Fields -->
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Title -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">
              Nama / Judul Layanan <span class="text-red-500">*</span>
            </label>
            <input
              v-model="formTitle"
              type="text"
              placeholder="Contoh: Jasa Cuci Kasur Springbed"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
          </div>

          <!-- Price Start -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">
              Harga Mulai (Angka/Teks)
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <DollarSign class="w-4 h-4" />
              </div>
              <input
                v-model="formPriceStart"
                type="text"
                placeholder="Contoh: 150000 atau Rp 150.000"
                class="w-full pl-9 pr-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>
            <p class="text-[11px] text-slate-400">Tampil sebagai harga awal di landing page website.</p>
          </div>

          <!-- Description -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">
              Deskripsi Layanan
            </label>
            <textarea
              v-model="formDescription"
              rows="3"
              placeholder="Jelaskan proses cuci, alat yang digunakan, garansi kebersihan, dll..."
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            ></textarea>
          </div>

          <!-- Image Upload & Preview -->
          <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700">
              Foto / Thumbnail Layanan
            </label>

            <!-- Image preview box -->
            <div
              v-if="imagePreview"
              class="relative w-full h-36 rounded-2xl bg-slate-100 overflow-hidden border border-slate-200"
            >
              <img :src="imagePreview" alt="Preview" class="w-full h-full object-cover" />
              <button
                type="button"
                @click="() => { imagePreview = null; selectedFile = null; formCurrentImage = ''; }"
                class="absolute top-2 right-2 p-1.5 rounded-full bg-slate-900/60 hover:bg-slate-900 text-white transition-colors"
                title="Hapus Foto"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Upload trigger -->
            <label class="flex flex-col items-center justify-center p-4 rounded-2xl border-2 border-dashed border-slate-200 hover:border-blue-400 bg-slate-50/50 hover:bg-blue-50/30 transition-all cursor-pointer">
              <Upload class="w-5 h-5 text-blue-600 mb-1" />
              <span class="text-xs font-semibold text-slate-700">Pilih berkas foto baru</span>
              <span class="text-[10px] text-slate-400">JPG, PNG, atau WebP (Maks 5MB)</span>
              <input
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleFileChange"
              />
            </label>
          </div>

          <!-- Actions -->
          <div class="pt-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 border-t border-slate-100">
            <button
              type="button"
              @click="isModalOpen = false"
              class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition-colors text-center"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all disabled:opacity-70 cursor-pointer text-center"
            >
              <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
              <span>{{ formId ? 'Simpan Perubahan' : 'Tambah Layanan' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>
