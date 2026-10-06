<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiClient } from '@/api/client'
import {
  MapPin,
  Plus,
  Search,
  Edit2,
  Trash2,
  Navigation,
  ExternalLink,
  Loader2,
  CheckCircle2,
  AlertCircle,
  X,
  RefreshCw,
  Globe
} from 'lucide-vue-next'

interface Location {
  id: number
  name: string
  address: string | null
  latitude: number | null
  longitude: number | null
  iframe_link: string | null
  created_at: string
}

const locations = ref<Location[]>([])
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
const formAddress = ref('')
const formLatitude = ref<string>('')
const formLongitude = ref<string>('')
const formIframeLink = ref('')

async function fetchLocations() {
  isLoading.value = true
  try {
    const res = await apiClient.get('/v2/data.php?type=locations')
    if (res.data?.status === 'success') {
      locations.value = res.data.data
    }
  } catch (err) {
    console.error('Error fetching locations:', err)
  } finally {
    isLoading.value = false
  }
}

const filteredLocations = computed(() => {
  if (!searchQuery.value) return locations.value
  const q = searchQuery.value.toLowerCase()
  return locations.value.filter(
    (l) =>
      l.name.toLowerCase().includes(q) ||
      (l.address && l.address.toLowerCase().includes(q))
  )
})

function openCreateModal() {
  formId.value = null
  formName.value = ''
  formAddress.value = ''
  formLatitude.value = ''
  formLongitude.value = ''
  formIframeLink.value = ''
  modalError.value = null
  isModalOpen.value = true
}

function openEditModal(loc: Location) {
  formId.value = loc.id
  formName.value = loc.name
  formAddress.value = loc.address || ''
  formLatitude.value = loc.latitude ? loc.latitude.toString() : ''
  formLongitude.value = loc.longitude ? loc.longitude.toString() : ''
  formIframeLink.value = loc.iframe_link || ''
  modalError.value = null
  isModalOpen.value = true
}

async function handleSubmit() {
  if (!formName.value.trim()) {
    modalError.value = 'Nama lokasi wilayah wajib diisi.'
    return
  }

  isSubmitting.value = true
  modalError.value = null

  try {
    const payload = {
      id: formId.value,
      name: formName.value.trim(),
      address: formAddress.value.trim(),
      latitude: formLatitude.value ? parseFloat(formLatitude.value) : null,
      longitude: formLongitude.value ? parseFloat(formLongitude.value) : null,
      iframe_link: formIframeLink.value.trim(),
    }

    const res = await apiClient.post('/v2/data.php?type=locations', payload)

    if (res.data?.status === 'success') {
      isModalOpen.value = false
      successMessage.value = res.data.message || 'Lokasi berhasil disimpan.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchLocations()
    } else {
      modalError.value = res.data?.message || 'Gagal menyimpan lokasi.'
    }
  } catch (err: any) {
    modalError.value = err.response?.data?.message || 'Terjadi kesalahan sistem.'
  } finally {
    isSubmitting.value = false
  }
}

async function handleDelete(loc: Location) {
  if (!confirm(`Yakin ingin menghapus titik lokasi "${loc.name}"?`)) return

  try {
    const res = await apiClient.post('/v2/data.php?type=locations&action=delete', {
      id: loc.id,
    })
    if (res.data?.status === 'success') {
      successMessage.value = 'Lokasi berhasil dihapus.'
      setTimeout(() => (successMessage.value = null), 4000)
      await fetchLocations()
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Gagal menghapus lokasi.')
  }
}

onMounted(() => {
  fetchLocations()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Lokasi Jangkauan Layanan</h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola titik jangkauan cuci kasur, sofa, dan karpet panggilan di Tangerang & Jabodetabek.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchLocations"
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
          <span>Tambah Lokasi</span>
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
          placeholder="Cari nama area atau kota..."
          class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
        />
      </div>

      <div class="text-xs text-slate-500 font-medium">
        Total: <span class="font-bold text-slate-800">{{ filteredLocations.length }}</span> Titik Jangkauan
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="py-16 text-center space-y-3">
      <Loader2 class="w-8 h-8 animate-spin text-blue-600 mx-auto" />
      <p class="text-xs text-slate-500">Memuat data lokasi...</p>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredLocations.length === 0"
      class="py-16 px-4 rounded-3xl bg-white border border-slate-100 shadow-subtle text-center space-y-3"
    >
      <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto">
        <MapPin class="w-6 h-6" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">Tidak ada data lokasi</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto">
        Belum ada titik jangkauan yang ditambahkan. Klik tombol Tambah Lokasi untuk menambahkan.
      </p>
    </div>

    <!-- Locations Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="loc in filteredLocations"
        :key="loc.id"
        class="rounded-2xl bg-white border border-slate-100 shadow-subtle hover:shadow-card transition-all duration-200 p-5 flex flex-col justify-between group"
      >
        <div class="space-y-3">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <MapPin class="w-5 h-5" />
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
                  {{ loc.name }}
                </h3>
                <p class="text-[11px] text-slate-500">{{ loc.address || 'Area Sekitar' }}</p>
              </div>
            </div>
            <span class="text-[10px] text-slate-400 font-mono">#{{ loc.id }}</span>
          </div>

          <!-- Coordinates Pill -->
          <div v-if="loc.latitude && loc.longitude" class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between text-xs font-mono text-slate-600">
            <div class="flex items-center gap-1.5 truncate">
              <Navigation class="w-3.5 h-3.5 text-blue-600 shrink-0" />
              <span class="truncate">{{ Number(loc.latitude).toFixed(4) }}, {{ Number(loc.longitude).toFixed(4) }}</span>
            </div>
            <a
              :href="`https://www.google.com/maps?q=${loc.latitude},${loc.longitude}`"
              target="_blank"
              class="text-blue-600 hover:text-blue-700 hover:underline text-[11px] font-sans font-semibold shrink-0 ml-2"
            >
              Buka Maps
            </a>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-end gap-1.5 text-xs">
          <button
            @click="openEditModal(loc)"
            class="px-2.5 py-1.5 rounded-lg bg-slate-50 hover:bg-blue-50 text-slate-700 hover:text-blue-600 font-semibold text-[11px] transition-colors border border-slate-200 hover:border-blue-200"
          >
            <Edit2 class="w-3 h-3 inline mr-1" />
            Edit
          </button>
          <button
            @click="handleDelete(loc)"
            class="p-1.5 rounded-lg bg-slate-50 hover:bg-red-50 text-slate-400 hover:text-red-600 transition-colors border border-slate-200 hover:border-red-200"
            title="Hapus"
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
      <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="min-w-0">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 truncate sm:whitespace-normal">
              {{ formId ? 'Edit Lokasi Jangkauan' : 'Tambah Titik Lokasi' }}
            </h2>
            <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">Area operasional layanan panggilan Arno D Clean.</p>
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
            <label class="block text-xs font-semibold text-slate-700">Nama Wilayah / Titik <span class="text-red-500">*</span></label>
            <input
              v-model="formName"
              type="text"
              placeholder="Contoh: BSD City / Gading Serpong"
              required
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Keterangan / Kota</label>
            <input
              v-model="formAddress"
              type="text"
              placeholder="Contoh: Kota Tangerang Selatan"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
            />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Latitude</label>
              <input
                v-model="formLatitude"
                type="text"
                placeholder="-6.212259"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Longitude</label>
              <input
                v-model="formLongitude"
                type="text"
                placeholder="106.612943"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs sm:text-sm font-mono text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all"
              />
            </div>
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
              <span>Simpan Lokasi</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
