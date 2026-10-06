<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { apiClient as axios } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  Activity,
  History,
  LogIn,
  Search,
  RefreshCw,
  Clock,
  Shield,
  User,
  ChevronLeft,
  ChevronRight,
  Monitor,
  CheckCircle2,
  AlertTriangle,
  X
} from 'lucide-vue-next'

interface ActivityLogItem {
  id: number
  log_type: 'activity' | 'login'
  user_id: number | null
  action: string
  details: string | null
  ip_address: string | null
  created_at: string
  username: string | null
  avatar: string | null
}

interface Summary {
  total_activities: number
  today_activities: number
  total_logins: number
  today_logins: number
}

interface Pagination {
  current_page: number
  limit: number
  total_records: number
  total_pages: number
}

const loading = ref(false)
const logs = ref<ActivityLogItem[]>([])
const summary = ref<Summary>({
  total_activities: 0,
  today_activities: 0,
  total_logins: 0,
  today_logins: 0
})
const pagination = ref<Pagination>({
  current_page: 1,
  limit: 15,
  total_records: 0,
  total_pages: 1
})

const searchQuery = ref('')
let searchTimeout: any = null

// Toast Alert
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' }>({
  show: false,
  message: '',
  type: 'success'
})

function showToast(msg: string, type: 'success' | 'error' = 'success') {
  toast.value = { show: true, message: msg, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

async function fetchLogs(page = 1) {
  loading.value = true
  try {
    const res = await axios.get('/api/v2/data.php', {
      params: {
        type: 'activity_logs',
        page,
        limit: pagination.value.limit,
        search: searchQuery.value.trim() || undefined
      },
      withCredentials: true
    })

    if (res.data.status === 'success') {
      logs.value = res.data.data || []
      if (res.data.pagination) {
        pagination.value = res.data.pagination
      }
      if (res.data.summary) {
        summary.value = res.data.summary
      }
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memuat log aktivitas', 'error')
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchLogs(1)
  }, 400)
}

function changePage(page: number) {
  if (page < 1 || page > pagination.value.total_pages) return
  fetchLogs(page)
}

function formatDate(dateStr: string) {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    })
  } catch {
    return dateStr
  }
}

function getActionBadgeClass(action: string, logType: string) {
  if (logType === 'login') {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  }
  const a = action.toLowerCase()
  if (a.includes('delete') || a.includes('hapus')) {
    return 'bg-rose-50 text-rose-700 border-rose-200'
  }
  if (a.includes('create') || a.includes('tambah') || a.includes('add')) {
    return 'bg-blue-50 text-blue-700 border-blue-200'
  }
  if (a.includes('update') || a.includes('ubah') || a.includes('edit')) {
    return 'bg-amber-50 text-amber-700 border-amber-200'
  }
  return 'bg-slate-50 text-slate-700 border-slate-200'
}

onMounted(() => {
  fetchLogs(1)
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn pb-12">
    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      class="fixed top-20 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl transition-all border text-sm font-medium"
      :class="
        toast.type === 'success'
          ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
          : 'bg-rose-50 text-rose-800 border-rose-200'
      "
    >
      <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600" />
      <AlertTriangle v-else class="w-5 h-5 text-rose-600" />
      <span>{{ toast.message }}</span>
      <button @click="toast.show = false" class="text-slate-400 hover:text-slate-600 ml-2">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Header & Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>Log Aktivitas & Audit Trail</span>
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            System Security
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Pantau riwayat operasi, mutasi data, dan event login pengguna secara real-time.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchLogs(pagination.current_page)"
          :disabled="loading"
          class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-medium shadow-sm transition-all"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="loading ? 'animate-spin' : ''" />
          <span>Refresh Data</span>
        </button>
      </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Aktivitas</p>
          <h3 class="text-2xl font-bold text-slate-900 mt-1">{{ summary.total_activities }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Seluruh log mutasi data</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
          <Activity class="w-6 h-6" />
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Aktivitas Hari Ini</p>
          <h3 class="text-2xl font-bold text-indigo-600 mt-1">{{ summary.today_activities }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Operasi sistem hari ini</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
          <History class="w-6 h-6" />
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Riwayat Login</p>
          <h3 class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.total_logins }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Sesi login tercatat</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
          <LogIn class="w-6 h-6" />
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Login Hari Ini</p>
          <h3 class="text-2xl font-bold text-amber-600 mt-1">{{ summary.today_logins }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Sesi masuk hari ini</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
          <Shield class="w-6 h-6" />
        </div>
      </div>
    </div>

    <!-- Activity Log Table Card -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative w-full sm:w-80">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            @input="handleSearch"
            type="text"
            placeholder="Cari user, aksi, detail, IP..."
            class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
          />
        </div>

        <span class="text-xs text-slate-400 font-medium">
          Total {{ pagination.total_records }} catatan log
        </span>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
              <th class="py-3.5 px-6 w-52">User / Pelaksana</th>
              <th class="py-3.5 px-4 w-40">Aksi</th>
              <th class="py-3.5 px-4 min-w-[280px]">Detail Aktivitas</th>
              <th class="py-3.5 px-4 w-36">IP Address</th>
              <th class="py-3.5 px-6 w-44 text-right">Waktu</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="5" class="text-center py-12 text-slate-400">
                <RefreshCw class="w-6 h-6 animate-spin mx-auto text-blue-500 mb-2" />
                <span>Memuat log aktivitas...</span>
              </td>
            </tr>

            <tr v-else-if="logs.length === 0">
              <td colspan="5" class="text-center py-12 text-slate-400">
                <History class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                <p class="font-medium text-slate-600">Tidak ada riwayat aktivitas ditemukan</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Coba ubah kata kunci pencarian Anda.</p>
              </td>
            </tr>

            <tr
              v-else
              v-for="item in logs"
              :key="`${item.log_type}-${item.id}`"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <!-- User -->
              <td class="py-3.5 px-6">
                <div class="flex items-center gap-2.5">
                  <div class="relative w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-xs shrink-0 border border-slate-200 overflow-hidden">
                    <span>{{ (item.username || 'System').charAt(0).toUpperCase() }}</span>
                    <img
                      v-if="item.avatar"
                      :src="getImageUrl(item.avatar)"
                      alt="avatar"
                      class="w-full h-full object-cover absolute inset-0"
                      @error="(e: any) => { e.target.style.display = 'none'; }"
                    />
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">{{ item.username || 'System' }}</p>
                    <p class="text-[10px] text-slate-400">
                      {{ item.user_id ? `ID #${item.user_id}` : 'System Auto' }}
                    </p>
                  </div>
                </div>
              </td>

              <!-- Action Badge -->
              <td class="py-3.5 px-4">
                <span
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold border"
                  :class="getActionBadgeClass(item.action, item.log_type)"
                >
                  <LogIn v-if="item.log_type === 'login'" class="w-3 h-3" />
                  <Activity v-else class="w-3 h-3" />
                  <span>{{ item.action }}</span>
                </span>
              </td>

              <!-- Details -->
              <td class="py-3.5 px-4">
                <p class="text-slate-700 leading-relaxed font-mono text-[11px]">
                  {{ item.details || '-' }}
                </p>
              </td>

              <!-- IP Address -->
              <td class="py-3.5 px-4 font-mono text-slate-500 text-[11px] whitespace-nowrap">
                <div class="flex items-center gap-1.5">
                  <Monitor class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ item.ip_address || '127.0.0.1' }}</span>
                </div>
              </td>

              <!-- Waktu -->
              <td class="py-3.5 px-6 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5 text-slate-500 text-[11px]">
                  <Clock class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ formatDate(item.created_at) }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="p-4 sm:p-5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
        <div>
          Halaman <strong class="text-slate-900">{{ pagination.current_page }}</strong> dari
          <strong class="text-slate-900">{{ pagination.total_pages }}</strong>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="changePage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1"
            class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white flex items-center gap-1 transition-all"
          >
            <ChevronLeft class="w-4 h-4" />
            <span>Sebelumnya</span>
          </button>

          <button
            @click="changePage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.total_pages"
            class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:hover:bg-white flex items-center gap-1 transition-all"
          >
            <span>Selanjutnya</span>
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
  animation: fadeIn 0.25s ease-out forwards;
}
</style>
