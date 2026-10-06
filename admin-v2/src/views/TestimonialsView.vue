<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  MessageSquareQuote,
  Plus,
  Search,
  Edit2,
  Trash2,
  Star,
  MapPin,
  Loader2,
  CheckCircle2,
  AlertCircle,
  X,
  Upload,
  RefreshCw,
  Quote
} from 'lucide-vue-next'

interface Testimonial {
  id: number
  name: string
  location: string | null
  content: string
  rating: number
  image_path: string | null
  platform: string
  created_at: string
}

const testimonials = ref<Testimonial[]>([])
const isLoading = ref(true)
const searchQuery = ref('')

// Modal state
const isModalOpen = ref(false)
const isSubmitting = ref(false)
const modalError = ref<string | null>(null)
const successMessage = ref<string | null>(null)

// Form state
const formId = ref<number | null>(null)
const formName = ref('')
const formLocation = ref('')
const formContent = ref('')
const formRating = ref<number>(5.0)
const formPlatform = ref('Website')
const formCurrentImage = ref('')
const selectedFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

async function fetchTestimonials() {
  isLoading.value = true
  try {
    const res = await apiClient.get('/v2/data.php?type=testimonials')
    if (res.data?.status === 'success') {
      testimonials.value = res.data.data
    }
  } catch (err) {
    console.error('Error fetching testimonials:', err)
  } finally {
    isLoading.value = false
  }
}

const filteredTestimonials = computed(() => {
  if (!searchQuery.value) return testimonials.value
  const q = searchQuery.value.toLowerCase()
  return testimonials.value.filter(
    (t) =>
      t.name.toLowerCase().includes(q) ||
      (t.location && t.location.toLowerCase().includes(q)) ||
      t.content.toLowerCase().includes(q)
  )
})

function openCreateModal() {
  formId.value = null
  formName.value = ''
  formLocation.value = ''
  formContent.value = ''
  formRating.value = 5.0
  formPlatform.value = 'Website'
  formCurrentImage.value = ''
  selectedFile.value = null
  imagePreview.value = null
  modalError.value = null
  isModalOpen.value = true
}

function openEditModal(t: Testimonial) {
  formId.value = t.id
  formName.value = t.name
  formLocation.value = t.location || ''
  formContent.value = t.content
  formRating.value = Number(t.rating) || 5.0
  formPlatform.value = t.platform || 'Website'
  formCurrentImage.value = t.image_path || ''
  selectedFile.value = null
  imagePreview.value = t.image_path ? getImageUrl(t.image_path) : null
  modalError.value = null
  isModalOpen.value = true
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    selectedFile.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

async function handleSubmit() {
  if (!formName.value.trim() || !formContent.value.trim()) {
    modalError.value = 'Nama pelanggan dan isi ulasan wajib diisi.'
    return
  }

  isSubmitting.value = true
  modalError.value = null

  try {
    const formData = new FormData()
    if (formId.value) formData.append('id', formId.value.toString())
    formData.append('name', formName.value.trim())
    formData.append('location', formLocation.value.trim())
    formData.append('content', formContent.value.trim())
    formData.append('rating', formRating.value.toString())
    formData.append('platform', formPlatform.value)
    formData.append('current_image', formCurrentImage.value)

    if (selectedFile.value) {
      formData.append('image', selectedFile.value)
    }

    const res = await apiClient.post('/v2/data.php?type=testimonials', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.status === 'success') {
      isModalOpen.value = false
      successMessage.value = res.data.message || 'Testimoni berhasil disimpan.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchTestimonials()
    } else {
      modalError.value = res.data?.message || 'Gagal menyimpan testimoni.'
    }
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Terjadi kesalahan sistem.'
  } finally {
    isSubmitting.value = false
  }
}

async function handleDelete(t: Testimonial) {
  if (!confirm(`Yakin ingin menghapus testimoni dari "${t.name}"?`)) return

  try {
    const res = await apiClient.post('/v2/data.php?type=testimonials&action=delete', {
      id: t.id,
    })
    if (res.data?.status === 'success') {
      successMessage.value = 'Testimoni berhasil dihapus.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchTestimonials()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus testimoni.')
  }
}

onMounted(() => {
  fetchTestimonials()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Testimoni Pelanggan</h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola ulasan kepuasan pelanggan dari website, Google, dan WhatsApp.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchTestimonials"
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
          <span>Tambah Testimoni</span>
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
          placeholder="Cari nama, kota, ulasan..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
        />
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Total: <span class="font-bold text-slate-800">{{ filteredTestimonials.length }}</span> Ulasan
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 text-center space-y-3">
      <Loader2 class="w-8 h-8 animate-spin text-blue-600 mx-auto" />
      <p class="text-xs text-slate-500">Memuat data testimoni...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredTestimonials.length === 0"
      class="py-16 px-4 rounded-3xl bg-white border border-slate-100 shadow-subtle text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto">
        <MessageSquareQuote class="w-6 h-6" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada ulasan</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Belum ada ulasan yang tersimpan. Klik tombol Tambah Testimoni untuk menambahkan.
      </p>
    </div>

    <!-- Testimonials Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="t in filteredTestimonials"
        :key="t.id"
        class="rounded-2xl bg-white border border-slate-100 shadow-subtle hover:shadow-card transition-all duration-200 p-6 flex flex-col justify-between group relative"
      >
        <div class="space-y-4">
          <!-- Top Row: Client Info & Platform Pill -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="relative w-10 h-10 rounded-full bg-blue-100 text-blue-700 font-bold text-sm flex items-center justify-center shrink-0 border border-blue-200 overflow-hidden shadow-sm">
                <span>{{ t.name ? t.name.charAt(0).toUpperCase() : 'U' }}</span>
                <img
                  v-if="t.image_path"
                  :src="getImageUrl(t.image_path)"
                  :alt="t.name"
                  class="w-full h-full object-cover absolute inset-0"
                  @error="(e: any) => { e.target.style.display = 'none'; }"
                />
              </div>

              <div>
                <h3 class="text-sm font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
                  {{ t.name }}
                </h3>
                <div v-if="t.location" class="flex items-center gap-1 text-[11px] text-slate-400 mt-0.5">
                  <MapPin class="w-3 h-3" />
                  <span>{{ t.location }}</span>
                </div>
              </div>
            </div>

            <!-- Platform Badge -->
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
              {{ t.platform || 'Website' }}
            </span>
          </div>

          <!-- Stars Rating -->
          <div class="flex items-center gap-1 text-amber-400">
            <Star
              v-for="s in 5"
              :key="s"
              :class="['w-4 h-4', s <= Math.round(t.rating) ? 'fill-amber-400 text-amber-400' : 'text-slate-200']"
            />
            <span class="text-xs font-bold text-slate-700 ml-1.5">{{ Number(t.rating).toFixed(1) }}</span>
          </div>

          <!-- Quote Content -->
          <p class="text-xs text-slate-600 leading-relaxed italic line-clamp-4">
            &ldquo;{{ t.content }}&rdquo;
          </p>
        </div>

        <!-- Footer Actions -->
        <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs">
          <span class="text-[10px] text-slate-400">#{{ t.id }}</span>
          <div class="flex items-center gap-1.5">
            <button
              @click="openEditModal(t)"
              class="px-2.5 py-1 rounded-lg bg-slate-50 hover:bg-blue-50 text-slate-700 hover:text-blue-600 font-semibold text-[11px] transition-colors border border-slate-200 hover:border-blue-200"
            >
              <Edit2 class="w-3 h-3 inline mr-1" />
              Edit
            </button>
            <button
              @click="handleDelete(t)"
              class="p-1 rounded-lg bg-slate-50 hover:bg-red-50 text-slate-400 hover:text-red-600 transition-colors border border-slate-200 hover:border-red-200"
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
              {{ formId ? 'Edit Testimoni' : 'Tambah Testimoni Baru' }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Ulasan kepuasan dari pelanggan Arno D Clean.</p>
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
            <label class="block text-xs font-semibold text-slate-700">Nama Pelanggan <span class="text-red-500">*</span></label>
            <input
              v-model="formName"
              type="text"
              placeholder="Contoh: Ibu Indah Permata"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Lokasi / Kota</label>
              <input
                v-model="formLocation"
                type="text"
                placeholder="BSD, Tangerang"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Platform</label>
              <select
                v-model="formPlatform"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              >
                <option value="Website">Website</option>
                <option value="Google">Google Review</option>
                <option value="WhatsApp">WhatsApp</option>
                <option value="Instagram">Instagram</option>
              </select>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Rating Bintang (1 - 5)</label>
            <div class="flex items-center gap-2">
              <button
                type="button"
                v-for="star in 5"
                :key="star"
                @click="formRating = star"
                class="p-1 focus:outline-none"
              >
                <Star
                  :class="['w-6 h-6 transition-colors', star <= formRating ? 'fill-amber-400 text-amber-400' : 'text-slate-200 hover:text-amber-300']"
                />
              </button>
              <span class="text-xs font-bold text-slate-700 ml-2">{{ formRating.toFixed(1) }} / 5.0</span>
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Isi Ulasan <span class="text-red-500">*</span></label>
            <textarea
              v-model="formContent"
              rows="3"
              placeholder="Tulis ulasan pelanggan tentang hasil cuci sofa/kasur..."
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            ></textarea>
          </div>

          <div class="space-y-2">
            <label class="block text-xs font-semibold text-slate-700">Foto Profil Pelanggan (Opsional)</label>
            <div v-if="imagePreview" class="relative w-20 h-20 rounded-full bg-slate-100 overflow-hidden border border-slate-200 mx-auto">
              <img :src="imagePreview" alt="Preview" class="w-full h-full object-cover" />
              <button
                type="button"
                @click="() => { imagePreview = null; selectedFile = null; formCurrentImage = ''; }"
                class="absolute inset-0 bg-slate-900/40 hover:bg-slate-900/60 text-white flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity"
              >
                <X class="w-4 h-4" />
              </button>
            </div>

            <label class="flex flex-col items-center justify-center p-3.5 rounded-xl border-2 border-dashed border-slate-200 hover:border-blue-400 bg-slate-50/50 hover:bg-blue-50/30 transition-all cursor-pointer">
              <Upload class="w-4 h-4 text-blue-600 mb-1" />
              <span class="text-xs font-semibold text-slate-700">Pilih foto pelanggan</span>
              <input type="file" accept="image/*" class="hidden" @change="handleFileChange" />
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
