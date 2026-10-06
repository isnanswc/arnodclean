<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import {
  Tag,
  Plus,
  Search,
  Edit2,
  Trash2,
  FileText,
  Loader2,
  CheckCircle2,
  AlertCircle,
  X,
  RefreshCw,
  Hash
} from 'lucide-vue-next'

interface TagItem {
  id: number
  name: string
  slug: string
  article_count?: number
  created_at: string
}

const tags = ref<TagItem[]>([])
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
const formSlug = ref('')

// Auto-generate slug when name changes (if creating or slug hasn't been manually edited)
watch(formName, (newVal) => {
  if (!formId.value) {
    formSlug.value = newVal
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
  }
})

async function fetchTags() {
  isLoading.value = true
  try {
    const res = await apiClient.get('/v2/data.php?type=tags')
    if (res.data?.status === 'success') {
      tags.value = res.data.data
    }
  } catch (err) {
    console.error('Error fetching tags:', err)
  } finally {
    isLoading.value = false
  }
}

const filteredTags = computed(() => {
  if (!searchQuery.value) return tags.value
  const q = searchQuery.value.toLowerCase()
  return tags.value.filter(
    (t) =>
      t.name.toLowerCase().includes(q) ||
      t.slug.toLowerCase().includes(q)
  )
})

function openCreateModal() {
  formId.value = null
  formName.value = ''
  formSlug.value = ''
  modalError.value = null
  isModalOpen.value = true
}

function openEditModal(tag: TagItem) {
  formId.value = tag.id
  formName.value = tag.name
  formSlug.value = tag.slug
  modalError.value = null
  isModalOpen.value = true
}

async function handleSubmit() {
  if (!formName.value.trim()) {
    modalError.value = 'Nama tag wajib diisi.'
    return
  }

  isSubmitting.value = true
  modalError.value = null

  try {
    const payload = {
      id: formId.value,
      name: formName.value.trim(),
      slug: formSlug.value.trim(),
    }

    const res = await apiClient.post('/v2/data.php?type=tags', payload)

    if (res.data?.status === 'success') {
      isModalOpen.value = false
      successMessage.value = res.data.message || 'Tag berhasil disimpan.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchTags()
    } else {
      modalError.value = res.data?.message || 'Gagal menyimpan tag.'
    }
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Terjadi kesalahan sistem.'
  } finally {
    isSubmitting.value = false
  }
}

async function handleDelete(tag: TagItem) {
  if (!confirm(`Yakin ingin menghapus tag "${tag.name}"? Hubungan dengan artikel terkait akan dilepas.`)) return

  try {
    const res = await apiClient.post('/v2/data.php?type=tags&action=delete', {
      id: tag.id,
    })
    if (res.data?.status === 'success') {
      successMessage.value = 'Tag berhasil dihapus.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchTags()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus tag.')
  }
}

onMounted(() => {
  fetchTags()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Kategori & Tags Artikel</h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola label tag artikel untuk kategorisasi, pencarian, dan tautan internal SEO.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchTags"
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
          <span>Tambah Tag</span>
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
          placeholder="Cari tag atau slug..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
        />
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Total: <span class="font-bold text-slate-800">{{ filteredTags.length }}</span> Tag Terdaftar
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 text-center space-y-3">
      <Loader2 class="w-8 h-8 animate-spin text-blue-600 mx-auto" />
      <p class="text-xs text-slate-500">Memuat data tags...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredTags.length === 0"
      class="py-16 px-4 rounded-3xl bg-white border border-slate-100 shadow-subtle text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto">
        <Tag class="w-6 h-6" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada tags ditemukan</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Belum ada tag yang terdaftar. Klik tombol Tambah Tag untuk membuat tag baru.
      </p>
    </div>

    <!-- Tags Grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div
        v-for="t in filteredTags"
        :key="t.id"
        class="rounded-2xl bg-white border border-slate-100 shadow-subtle hover:shadow-card transition-all duration-200 p-4 flex flex-col justify-between group"
      >
        <div class="space-y-2.5">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <Hash class="w-4 h-4" />
              </div>
              <h3 class="text-sm font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate">
                {{ t.name }}
              </h3>
            </div>
            <span class="text-[10px] text-slate-400 font-mono">#{{ t.id }}</span>
          </div>

          <!-- Slug Pill & Article Count -->
          <div class="flex items-center justify-between text-xs pt-1">
            <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono text-[11px] truncate max-w-[140px]">
              {{ t.slug }}
            </span>
            <div class="flex items-center gap-1 text-[11px] text-blue-600 font-semibold" title="Jumlah artikel yang memakai tag ini">
              <FileText class="w-3 h-3" />
              <span>{{ t.article_count || 0 }} artikel</span>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-end gap-1.5 text-xs">
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
            title="Hapus Tag"
          >
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL (CREATE / EDIT) -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 max-w-sm w-full p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 truncate sm:whitespace-normal">
              {{ formId ? 'Edit Tag' : 'Tambah Tag Baru' }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Label kategori artikel SEO.</p>
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
            <label class="block text-xs font-semibold text-slate-700">Nama Tag <span class="text-red-500">*</span></label>
            <input
              v-model="formName"
              type="text"
              placeholder="Contoh: Cuci Sofa Tangerang"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Slug URL</label>
            <input
              v-model="formSlug"
              type="text"
              placeholder="cuci-sofa-tangerang"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
            <p class="text-[11px] text-slate-400">Otomatis diformat menjadi huruf kecil dan tanda strip.</p>
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
              <span>Simpan Tag</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
