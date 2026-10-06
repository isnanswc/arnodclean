<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  Star,
  Plus,
  Search,
  Edit2,
  Trash2,
  Image as ImageIcon,
  Video,
  Loader2,
  CheckCircle2,
  AlertCircle,
  X,
  Upload,
  RefreshCw
} from 'lucide-vue-next'

interface Benefit {
  id: number
  title: string
  description: string | null
  image_path: string | null
  media_type: 'image' | 'video'
  created_at: string
}

const benefits = ref<Benefit[]>([])
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
const formDescription = ref('')
const formMediaType = ref<'image' | 'video'>('image')
const formCurrentImage = ref('')
const selectedFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

async function fetchBenefits() {
  isLoading.value = true
  try {
    const res = await apiClient.get('/v2/data.php?type=benefits')
    if (res.data?.status === 'success') {
      benefits.value = res.data.data
    }
  } catch (err) {
    console.error('Error fetching benefits:', err)
  } finally {
    isLoading.value = false
  }
}

const filteredBenefits = computed(() => {
  if (!searchQuery.value) return benefits.value
  const q = searchQuery.value.toLowerCase()
  return benefits.value.filter(
    (b) =>
      b.title.toLowerCase().includes(q) ||
      (b.description && b.description.toLowerCase().includes(q))
  )
})

function openCreateModal() {
  formId.value = null
  formTitle.value = ''
  formDescription.value = ''
  formMediaType.value = 'image'
  formCurrentImage.value = ''
  selectedFile.value = null
  imagePreview.value = null
  modalError.value = null
  isModalOpen.value = true
}

function openEditModal(benefit: Benefit) {
  formId.value = benefit.id
  formTitle.value = benefit.title
  formDescription.value = benefit.description || ''
  formMediaType.value = benefit.media_type || 'image'
  formCurrentImage.value = benefit.image_path || ''
  selectedFile.value = null
  imagePreview.value = benefit.image_path ? getImageUrl(benefit.image_path) : null
  modalError.value = null
  isModalOpen.value = true
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    selectedFile.value = file
    imagePreview.value = URL.createObjectURL(file)
    if (file.type.startsWith('video/')) {
      formMediaType.value = 'video'
    } else if (file.type.startsWith('image/')) {
      formMediaType.value = 'image'
    }
  }
}

async function handleSubmit() {
  if (!formTitle.value.trim()) {
    modalError.value = 'Judul keunggulan wajib diisi.'
    return
  }

  isSubmitting.value = true
  modalError.value = null

  try {
    const formData = new FormData()
    if (formId.value) formData.append('id', formId.value.toString())
    formData.append('title', formTitle.value.trim())
    formData.append('description', formDescription.value.trim())
    formData.append('media_type', formMediaType.value)
    formData.append('current_image', formCurrentImage.value)

    if (selectedFile.value) {
      formData.append('image', selectedFile.value)
    }

    const res = await apiClient.post('/v2/data.php?type=benefits', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.status === 'success') {
      isModalOpen.value = false
      successMessage.value = res.data.message || 'Keunggulan berhasil disimpan.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchBenefits()
    } else {
      modalError.value = res.data?.message || 'Gagal menyimpan keunggulan.'
    }
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Terjadi kesalahan sistem.'
  } finally {
    isSubmitting.value = false
  }
}

async function handleDelete(benefit: Benefit) {
  if (!confirm(`Yakin ingin menghapus keunggulan "${benefit.title}"?`)) return

  try {
    const res = await apiClient.post('/v2/data.php?type=benefits&action=delete', {
      id: benefit.id,
    })
    if (res.data?.status === 'success') {
      successMessage.value = 'Keunggulan berhasil dihapus.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchBenefits()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus keunggulan.')
  }
}

onMounted(() => {
  fetchBenefits()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Keunggulan Layanan</h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola poin-poin nilai plus Arno D Clean yang ditampilkan di website.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchBenefits"
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
          <span>Tambah Keunggulan</span>
        </button>
      </div>
    </div>

    <!-- Success Toast -->
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

    <!-- Search Bar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="relative w-full sm:w-80">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <Search class="w-4 h-4" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari keunggulan..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
        />
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Total: <span class="font-bold text-slate-800">{{ filteredBenefits.length }}</span> Poin Keunggulan
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 text-center space-y-3">
      <Loader2 class="w-8 h-8 animate-spin text-blue-600 mx-auto" />
      <p class="text-xs text-slate-500">Memuat data keunggulan...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredBenefits.length === 0"
      class="py-16 px-4 rounded-3xl bg-white border border-slate-100 shadow-subtle text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto">
        <Star class="w-6 h-6" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada data keunggulan</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Belum ada poin keunggulan yang ditambahkan. Klik tombol Tambah Keunggulan untuk menambahkan.
      </p>
    </div>

    <!-- Benefits Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
      <div
        v-for="b in filteredBenefits"
        :key="b.id"
        class="rounded-2xl bg-white border border-slate-100 shadow-subtle hover:shadow-card transition-all duration-200 overflow-hidden flex flex-col justify-between group"
      >
        <div class="p-5 space-y-3">
          <!-- Thumbnail / Media -->
          <div class="relative h-32 rounded-xl bg-slate-100 overflow-hidden flex items-center justify-center">
            <div v-if="b.media_type === 'video'" class="flex flex-col items-center justify-center text-blue-600 gap-1">
              <Video class="w-8 h-8" />
              <span class="text-[10px] font-semibold">Video Media</span>
            </div>
            <div v-else class="flex flex-col items-center justify-center text-slate-400 gap-1">
              <ImageIcon class="w-8 h-8" />
              <span class="text-[10px]">Tanpa Foto</span>
            </div>

            <video
              v-if="b.image_path && b.media_type === 'video'"
              :src="getImageUrl(b.image_path)"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 absolute inset-0"
              muted
              loop
              playsinline
              autoplay
              @error="(e: any) => { e.target.style.display = 'none'; }"
            ></video>
            <img
              v-else-if="b.image_path"
              :src="getImageUrl(b.image_path)"
              :alt="b.title"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 absolute inset-0"
              @error="(e: any) => { e.target.style.display = 'none'; }"
            />

            <!-- Media Type Pill -->
            <span class="absolute top-2 right-2 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-white/90 backdrop-blur-md text-blue-700 shadow-sm border border-white">
              {{ b.media_type }}
            </span>
          </div>

          <div>
            <h3 class="text-sm font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
              {{ b.title }}
            </h3>
            <p class="text-xs text-slate-500 mt-1 line-clamp-3 leading-relaxed">
              {{ b.description || 'Tidak ada keterangan tambahan.' }}
            </p>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="px-5 py-3 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="text-[10px] text-slate-400">#{{ b.id }}</span>
          <div class="flex items-center gap-1.5">
            <button
              @click="openEditModal(b)"
              class="px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:border-blue-300 hover:text-blue-600 text-slate-700 font-semibold text-[11px] transition-colors shadow-sm"
            >
              Edit
            </button>
            <button
              @click="handleDelete(b)"
              class="p-1 rounded-lg bg-white border border-slate-200 hover:border-red-300 hover:text-red-600 text-slate-400 transition-colors shadow-sm"
              title="Hapus"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL (CREATE / EDIT) -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 truncate sm:whitespace-normal">
              {{ formId ? 'Edit Keunggulan' : 'Tambah Keunggulan' }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Poin nilai keunggulan jasa Arno D Clean.</p>
          </div>
          <button @click="isModalOpen = false" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div v-if="modalError" class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-center gap-2">
          <AlertCircle class="w-4 h-4 shrink-0 text-red-500" />
          <span>{{ modalError }}</span>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Judul Keunggulan <span class="text-red-500">*</span></label>
            <input
              v-model="formTitle"
              type="text"
              placeholder="Contoh: Teknisi Terlatih & Berpengalaman"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Jenis Media</label>
            <div class="flex items-center gap-4 text-xs">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="image" v-model="formMediaType" class="text-blue-600" />
                <span>Gambar / Foto</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="video" v-model="formMediaType" class="text-blue-600" />
                <span>Video Animasi</span>
              </label>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Keterangan / Deskripsi</label>
            <textarea
              v-model="formDescription"
              rows="3"
              placeholder="Jelaskan detail keunggulan..."
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            ></textarea>
          </div>

          <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700">Berkas Media (Opsional)</label>
            <div v-if="imagePreview" class="relative w-full h-28 rounded-xl bg-slate-100 overflow-hidden border border-slate-200">
              <video
                v-if="formMediaType === 'video' || (selectedFile && selectedFile.type.startsWith('video/'))"
                :src="imagePreview"
                class="w-full h-full object-cover"
                controls
              ></video>
              <img v-else :src="imagePreview" alt="Preview" class="w-full h-full object-cover" />
              <button
                type="button"
                @click="() => { imagePreview = null; selectedFile = null; formCurrentImage = ''; }"
                class="absolute top-2 right-2 p-1 rounded-full bg-slate-900/60 hover:bg-slate-900 text-white z-10"
              >
                <X class="w-3.5 h-3.5" />
              </button>
            </div>

            <label class="flex flex-col items-center justify-center p-3.5 rounded-xl border-2 border-dashed border-slate-200 hover:border-blue-400 bg-slate-50/50 hover:bg-blue-50/30 transition-all cursor-pointer">
              <Upload class="w-4 h-4 text-blue-600 mb-1" />
              <span class="text-xs font-semibold text-slate-700">Pilih berkas</span>
              <input type="file" accept="image/*,video/*" class="hidden" @change="handleFileChange" />
            </label>
          </div>

          <div class="pt-3 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 border-t border-slate-100">
            <button type="button" @click="isModalOpen = false" class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold text-center">
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all disabled:opacity-70 cursor-pointer text-center"
            >
              <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
              <span>Simpan</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
