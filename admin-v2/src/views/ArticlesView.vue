<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  FileText,
  Plus,
  Search,
  Edit2,
  Trash2,
  Eye,
  ExternalLink,
  Sparkles,
  Tag,
  Loader2,
  CheckCircle2,
  AlertCircle,
  X,
  Upload,
  RefreshCw,
  Globe,
  Award
} from 'lucide-vue-next'

interface ArticleItem {
  id: number
  title: string
  slug: string
  status: 'published' | 'draft'
  views: number
  image_path: string | null
  seo_score: number | null
  tag_names: string | null
  created_at: string
}

interface TagItem {
  id: number
  name: string
  slug: string
}

const articles = ref<ArticleItem[]>([])
const allTags = ref<TagItem[]>([])
const isLoading = ref(true)
const searchQuery = ref('')
const statusFilter = ref<'all' | 'published' | 'draft'>('all')

// Editor modal state
const isModalOpen = ref(false)
const isSubmitting = ref(false)
const modalError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const activeTab = ref<'content' | 'seo'>('content')

// Form state
const formId = ref<number | null>(null)
const formTitle = ref('')
const formSlug = ref('')
const formContent = ref('')
const formStatus = ref<'published' | 'draft'>('published')
const formSeoTitle = ref('')
const formSeoDescription = ref('')
const formFocusKeyword = ref('')
const formSelectedTags = ref<number[]>([])
const formCurrentImage = ref('')
const selectedFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)

async function fetchArticles() {
  isLoading.value = true
  try {
    const res = await apiClient.get('/v2/data.php?type=articles')
    if (res.data?.status === 'success') {
      articles.value = res.data.data
    }
  } catch (err) {
    console.error('Error fetching articles:', err)
  } finally {
    isLoading.value = false
  }
}

async function fetchTags() {
  try {
    const res = await apiClient.get('/v2/data.php?type=tags')
    if (res.data?.status === 'success') {
      allTags.value = res.data.data
    }
  } catch (err) {
    console.error('Error fetching tags:', err)
  }
}

const filteredArticles = computed(() => {
  return articles.value.filter((a) => {
    const matchesSearch =
      !searchQuery.value ||
      a.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (a.tag_names && a.tag_names.toLowerCase().includes(searchQuery.value.toLowerCase()))

    const matchesStatus =
      statusFilter.value === 'all' || a.status === statusFilter.value

    return matchesSearch && matchesStatus
  })
})

const publishedCount = computed(() => articles.value.filter((a) => a.status === 'published').length)
const draftCount = computed(() => articles.value.filter((a) => a.status === 'draft').length)

function openCreateModal() {
  formId.value = null
  formTitle.value = ''
  formSlug.value = ''
  formContent.value = ''
  formStatus.value = 'published'
  formSeoTitle.value = ''
  formSeoDescription.value = ''
  formFocusKeyword.value = ''
  formSelectedTags.value = []
  formCurrentImage.value = ''
  selectedFile.value = null
  imagePreview.value = null
  modalError.value = null
  activeTab.value = 'content'
  isModalOpen.value = true
}

async function openEditModal(art: ArticleItem) {
  modalError.value = null
  activeTab.value = 'content'
  try {
    const res = await apiClient.get(`/v2/data.php?type=articles&id=${art.id}`)
    if (res.data?.status === 'success' && res.data?.data) {
      const data = res.data.data
      formId.value = data.id
      formTitle.value = data.title
      formSlug.value = data.slug
      formContent.value = data.content || ''
      formStatus.value = data.status || 'published'
      formSeoTitle.value = data.seo_title || ''
      formSeoDescription.value = data.seo_description || ''
      formFocusKeyword.value = data.focus_keyword || ''
      formSelectedTags.value = data.tag_ids || []
      formCurrentImage.value = data.image_path || ''
      selectedFile.value = null
      imagePreview.value = data.image_path ? getImageUrl(data.image_path) : null
      isModalOpen.value = true
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal memuat detail artikel.')
  }
}

function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    const file = target.files[0]
    selectedFile.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

function toggleTag(tagId: number) {
  const idx = formSelectedTags.value.indexOf(tagId)
  if (idx > -1) {
    formSelectedTags.value.splice(idx, 1)
  } else {
    formSelectedTags.value.push(tagId)
  }
}

async function handleSubmit() {
  if (!formTitle.value.trim()) {
    modalError.value = 'Judul artikel wajib diisi.'
    return
  }

  isSubmitting.value = true
  modalError.value = null

  try {
    const formData = new FormData()
    if (formId.value) formData.append('id', formId.value.toString())
    formData.append('title', formTitle.value.trim())
    formData.append('slug', formSlug.value.trim())
    formData.append('content', formContent.value)
    formData.append('status', formStatus.value)
    formData.append('seo_title', formSeoTitle.value.trim())
    formData.append('seo_description', formSeoDescription.value.trim())
    formData.append('focus_keyword', formFocusKeyword.value.trim())
    formData.append('current_image', formCurrentImage.value)
    formData.append('tags', JSON.stringify(formSelectedTags.value))

    if (selectedFile.value) {
      formData.append('image', selectedFile.value)
    }

    const res = await apiClient.post('/v2/data.php?type=articles', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (res.data?.status === 'success') {
      isModalOpen.value = false
      successMessage.value = res.data.message || 'Artikel berhasil disimpan.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchArticles()
    } else {
      modalError.value = res.data?.message || 'Gagal menyimpan artikel.'
    }
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Terjadi kesalahan sistem saat menyimpan.'
  } finally {
    isSubmitting.value = false
  }
}

async function handleDelete(art: ArticleItem) {
  if (!confirm(`Yakin ingin menghapus artikel "${art.title}"?`)) return

  try {
    const res = await apiClient.post('/v2/data.php?type=articles&action=delete', {
      id: art.id,
    })
    if (res.data?.status === 'success') {
      successMessage.value = 'Artikel berhasil dihapus.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchArticles()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus artikel.')
  }
}

onMounted(() => {
  fetchArticles()
  fetchTags()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Artikel Blog</h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola konten artikel SEO, panduan kebersihan, dan publikasi edukasi pelanggan.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchArticles"
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
          <span>Tulis Artikel Baru</span>
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

    <!-- Filter & Search Toolbar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col md:flex-row items-center justify-between gap-3">
      <!-- Search Input -->
      <div class="relative w-full md:w-80">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <Search class="w-4 h-4" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari judul atau tag..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
        />
      </div>

      <!-- Status Tabs & Counter -->
      <div class="flex items-center gap-2 w-full md:w-auto justify-between md:justify-end">
        <div class="inline-flex p-1 rounded-xl bg-slate-100 text-xs font-semibold text-slate-600">
          <button
            @click="statusFilter = 'all'"
            :class="['px-3 py-1 rounded-lg transition-all', statusFilter === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'hover:text-slate-900']"
          >
            Semua ({{ articles.length }})
          </button>
          <button
            @click="statusFilter = 'published'"
            :class="['px-3 py-1 rounded-lg transition-all', statusFilter === 'published' ? 'bg-white text-emerald-600 shadow-sm' : 'hover:text-slate-900']"
          >
            Tayang ({{ publishedCount }})
          </button>
          <button
            @click="statusFilter = 'draft'"
            :class="['px-3 py-1 rounded-lg transition-all', statusFilter === 'draft' ? 'bg-white text-amber-600 shadow-sm' : 'hover:text-slate-900']"
          >
            Draft ({{ draftCount }})
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 text-center space-y-3">
      <Loader2 class="w-8 h-8 animate-spin text-blue-600 mx-auto" />
      <p class="text-xs text-slate-500">Memuat artikel blog...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredArticles.length === 0"
      class="py-16 px-4 rounded-3xl bg-white border border-slate-100 shadow-subtle text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto">
        <FileText class="w-6 h-6" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada artikel ditemukan</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        {{ searchQuery ? 'Tidak ada artikel yang cocok dengan filter pencarian.' : 'Belum ada artikel yang dibuat. Klik tombol Tulis Artikel Baru untuk memulai.' }}
      </p>
    </div>

    <!-- Articles Table -->
    <div v-else class="bg-white rounded-2xl border border-slate-100 shadow-subtle overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
              <th class="py-3.5 px-4">Artikel</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4">Tags</th>
              <th class="py-3.5 px-4 text-center">Tayangan</th>
              <th class="py-3.5 px-4 text-center">Skor SEO</th>
              <th class="py-3.5 px-4">Tanggal</th>
              <th class="py-3.5 px-4 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <tr
              v-for="art in filteredArticles"
              :key="art.id"
              class="hover:bg-blue-50/40 transition-colors group"
            >
              <!-- Image & Title -->
              <td class="py-4 px-4 max-w-xs">
                <div class="flex items-center gap-3">
                  <div class="relative w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 shrink-0 border border-slate-200 overflow-hidden">
                    <FileText class="w-5 h-5 text-slate-400" />
                    <img
                      v-if="art.image_path"
                      :src="getImageUrl(art.image_path)"
                      :alt="art.title"
                      class="w-full h-full object-cover absolute inset-0"
                      @error="(e: any) => { e.target.style.display = 'none'; }"
                    />
                  </div>

                  <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate">
                      {{ art.title }}
                    </h3>
                    <p class="text-[11px] font-mono text-slate-400 truncate mt-0.5">
                      /blog/{{ art.slug }}
                    </p>
                  </div>
                </div>
              </td>

              <!-- Status -->
              <td class="py-4 px-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider',
                    art.status === 'published' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'
                  ]"
                >
                  {{ art.status === 'published' ? 'Tayang' : 'Draft' }}
                </span>
              </td>

              <!-- Tags -->
              <td class="py-4 px-4 max-w-xs">
                <span v-if="art.tag_names" class="text-[11px] text-slate-600 line-clamp-1">
                  {{ art.tag_names }}
                </span>
                <span v-else class="text-[11px] text-slate-400 italic">Tanpa tag</span>
              </td>

              <!-- Views -->
              <td class="py-4 px-4 text-center whitespace-nowrap">
                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-50 text-slate-600 text-[11px] font-semibold">
                  <Eye class="w-3 h-3 text-slate-400" />
                  <span>{{ art.views || 0 }}</span>
                </div>
              </td>

              <!-- SEO Score -->
              <td class="py-4 px-4 text-center whitespace-nowrap">
                <span
                  v-if="art.seo_score"
                  :class="[
                    'px-2 py-0.5 rounded-md text-[11px] font-bold',
                    art.seo_score >= 80 ? 'bg-emerald-50 text-emerald-700' : art.seo_score >= 50 ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'
                  ]"
                >
                  {{ art.seo_score }}/100
                </span>
                <span v-else class="text-[11px] text-slate-400">-</span>
              </td>

              <!-- Date -->
              <td class="py-4 px-4 whitespace-nowrap text-[11px] text-slate-500">
                {{ art.created_at ? new Date(art.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}
              </td>

              <!-- Actions -->
              <td class="py-4 px-4 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <a
                    :href="`/blog/${art.slug}`"
                    target="_blank"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                    title="Buka Artikel di Web Publik"
                  >
                    <ExternalLink class="w-4 h-4" />
                  </a>

                  <button
                    @click="openEditModal(art)"
                    class="p-1.5 rounded-lg text-slate-600 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                    title="Edit Artikel"
                  >
                    <Edit2 class="w-4 h-4" />
                  </button>

                  <button
                    @click="handleDelete(art)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                    title="Hapus Artikel"
                  >
                    <Trash2 class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- FULL ARTICLE EDITOR MODAL -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 max-w-3xl w-full p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <div class="min-w-0 flex-1">
            <h2 class="text-base sm:text-xl font-bold text-slate-900 truncate sm:whitespace-normal">
              {{ formId ? 'Edit Artikel Blog' : 'Tulis Artikel Baru' }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Kelola konten narasi edukasi dan optimasi SEO halaman blog.</p>
          </div>
          <button @click="isModalOpen = false" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Tab Nav -->
        <div class="flex items-center gap-2 border-b border-slate-100 pb-2 overflow-x-auto no-scrollbar">
          <button
            type="button"
            @click="activeTab = 'content'"
            :class="['flex-1 sm:flex-initial px-3 sm:px-4 py-2 rounded-xl text-xs font-bold transition-all text-center whitespace-nowrap shrink-0', activeTab === 'content' ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:text-slate-800']"
          >
            1. Konten Utama
          </button>
          <button
            type="button"
            @click="activeTab = 'seo'"
            :class="['flex-1 sm:flex-initial px-3 sm:px-4 py-2 rounded-xl text-xs font-bold transition-all text-center whitespace-nowrap shrink-0', activeTab === 'seo' ? 'bg-blue-50 text-blue-700' : 'text-slate-500 hover:text-slate-800']"
          >
            2. Pengaturan SEO & Meta
          </button>
        </div>

        <!-- Error Alert -->
        <div v-if="modalError" class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs flex items-center gap-2">
          <AlertCircle class="w-4 h-4 shrink-0 text-red-500" />
          <span>{{ modalError }}</span>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- TAB 1: KONTEN UTAMA -->
          <div v-show="activeTab === 'content'" class="space-y-4">
            <!-- Judul -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Judul Artikel <span class="text-red-500">*</span></label>
              <input
                v-model="formTitle"
                type="text"
                placeholder="Contoh: 5 Cara Ampuh Menghilangkan Noda dan Bau Apek pada Kasur Springbed"
                required
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>

            <!-- Slug & Status Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div class="sm:col-span-2 space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700">Slug URL</label>
                <input
                  v-model="formSlug"
                  type="text"
                  placeholder="otomatis-dibuat-dari-judul"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
                />
              </div>

              <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700">Status Publikasi</label>
                <select
                  v-model="formStatus"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-semibold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
                >
                  <option value="published">Tayang (Published)</option>
                  <option value="draft">Draf (Draft)</option>
                </select>
              </div>
            </div>

            <!-- Image Upload & Preview -->
            <div class="space-y-2">
              <label class="block text-xs font-semibold text-slate-700">Gambar Sampul (Thumbnail Utama)</label>
              <div v-if="imagePreview" class="relative w-full h-40 rounded-2xl bg-slate-100 overflow-hidden border border-slate-200">
                <img :src="getImageUrl(imagePreview)" alt="Preview" class="w-full h-full object-cover" />
                <button
                  type="button"
                  @click="() => { imagePreview = null; selectedFile = null; formCurrentImage = ''; }"
                  class="absolute top-2 right-2 p-1.5 rounded-full bg-slate-900/60 hover:bg-slate-900 text-white"
                >
                  <X class="w-4 h-4" />
                </button>
              </div>

              <label class="flex flex-col items-center justify-center p-4 rounded-2xl border-2 border-dashed border-slate-200 hover:border-blue-400 bg-slate-50/50 hover:bg-blue-50/30 transition-all cursor-pointer">
                <Upload class="w-5 h-5 text-blue-600 mb-1" />
                <span class="text-xs font-semibold text-slate-700">Pilih gambar sampul baru</span>
                <span class="text-[10px] text-slate-400">JPG, PNG, WebP (Rekomendasi rasio 16:9)</span>
                <input type="file" accept="image/*" class="hidden" @change="handleFileChange" />
              </label>
            </div>

            <!-- Tags Selector -->
            <div class="space-y-2">
              <label class="block text-xs font-semibold text-slate-700">Pilih Tags Kategori Terkait</label>
              <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto p-2 rounded-xl bg-slate-50 border border-slate-200">
                <button
                  type="button"
                  v-for="t in allTags"
                  :key="t.id"
                  @click="toggleTag(t.id)"
                  :class="[
                    'px-2.5 py-1 rounded-lg text-xs font-medium transition-all',
                    formSelectedTags.includes(t.id) ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:border-blue-300'
                  ]"
                >
                  #{{ t.name }}
                </button>
              </div>
            </div>

            <!-- Content Area -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Isi Konten Artikel</label>
              <textarea
                v-model="formContent"
                rows="10"
                placeholder="Tulis artikel lengkap di sini (mendukung format paragraf, poin-poin, dan heading)..."
                class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all font-sans leading-relaxed"
              ></textarea>
            </div>
          </div>

          <!-- TAB 2: PENGATURAN SEO -->
          <div v-show="activeTab === 'seo'" class="space-y-4">
            <div class="p-4 rounded-2xl bg-blue-50/70 border border-blue-100 text-xs text-blue-800 flex items-start gap-2.5">
              <Sparkles class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
              <span>Optimasi SEO membantu artikel ini menempati peringkat tertinggi di pencarian Google untuk kata kunci jasa cuci kasur/sofa.</span>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Focus Keyword (Kata Kunci Utama)</label>
              <input
                v-model="formFocusKeyword"
                type="text"
                placeholder="Contoh: jasa cuci springbed tangerang"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Meta SEO Title</label>
              <input
                v-model="formSeoTitle"
                type="text"
                placeholder="Judul khusus yang tampil di halaman hasil pencarian Google"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Meta SEO Description</label>
              <textarea
                v-model="formSeoDescription"
                rows="3"
                placeholder="Ringkasan 120-160 karakter yang tampil di bawah judul pada pencarian Google..."
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              ></textarea>
            </div>

            <!-- Google Snippet Live Preview -->
            <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-sm space-y-1">
              <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Simulasi Tampilan Google (Preview)</p>
              <p class="text-xs text-emerald-700 font-mono">https://arnod-clean.com &rsaquo; blog &rsaquo; {{ formSlug || 'judul-artikel' }}</p>
              <h4 class="text-sm font-semibold text-blue-800 line-clamp-1 hover:underline cursor-pointer">
                {{ formSeoTitle || formTitle || 'Judul Artikel Anda - Arno D Clean' }}
              </h4>
              <p class="text-xs text-slate-600 line-clamp-2">
                {{ formSeoDescription || (formContent ? formContent.substring(0, 150) + '...' : 'Deskripsi ringkasan artikel...') }}
              </p>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="pt-4 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 border-t border-slate-100">
            <button
              type="button"
              @click="isModalOpen = false"
              class="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold text-center"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm shadow-blue-500/25 transition-all disabled:opacity-70 cursor-pointer text-center"
            >
              <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
              <span>{{ formId ? 'Simpan Perubahan' : 'Terbitkan Artikel' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
