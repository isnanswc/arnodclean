<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import {
  Users,
  MessageCircle,
  Calendar,
  PieChart,
  Sparkles,
  RefreshCw,
  Search,
  Filter,
  Eye,
  Trash2,
  ExternalLink,
  Smartphone,
  Monitor,
  CheckCircle2,
  AlertTriangle,
  ShieldAlert,
  Send,
  X,
  Clock,
  Mail,
  Phone
} from 'lucide-vue-next'

interface Lead {
  id: number
  name: string
  email: string | null
  phone?: string | null
  whatsapp?: string | null
  service_id?: number | null
  service_name?: string | null
  message: string
  status: 'new' | 'contacted' | 'closed' | 'spam'
  ai_status: 'genuine' | 'spam' | 'uncategorized'
  ai_confidence: number | null
  ai_analysis: string | null
  created_at: string
}

interface WaSource {
  source: string
  count: number
}

interface WaPage {
  page_url: string
  count: number
}

interface WaLog {
  id: number
  source: string
  page_url: string | null
  referrer: string | null
  device: string | null
  city?: string | null
  country?: string | null
  ip_address: string | null
  clicked_at: string
}

interface Summary {
  total: number
  new: number
  contacted: number
  closed: number
  spam: number
  unsorted: number
  total_wa_clicks: number
  today_wa_clicks: number
  top_source: string
}

const activeTab = ref<'leads' | 'wa_tracker'>('leads')
const loading = ref(false)
const scanning = ref(false)
const leads = ref<Lead[]>([])
const services = ref<{ id: number; title: string }[]>([])
const summary = ref<Summary>({
  total: 0,
  new: 0,
  contacted: 0,
  closed: 0,
  spam: 0,
  unsorted: 0,
  total_wa_clicks: 0,
  today_wa_clicks: 0,
  top_source: '-'
})

// WA Tracker State
const waLoading = ref(false)
const waSources = ref<WaSource[]>([])
const waPages = ref<WaPage[]>([])
const waLogs = ref<WaLog[]>([])
const waTotalClicks = ref(0)
const waTodayClicks = ref(0)
const waLogSearch = ref('')

// Filters
const aiFilter = ref<string>('all')
const statusFilter = ref<string>('all')
const searchQuery = ref('')

// Modal Detail
const selectedLead = ref<Lead | null>(null)
const editStatus = ref<string>('new')
const updatingStatus = ref(false)

// Delete Modal
const deleteTarget = ref<Lead | null>(null)
const deleting = ref(false)

// Toast Alert
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'success'
})

function showToast(msg: string, type: 'success' | 'error' | 'info' = 'success') {
  toast.value = { show: true, message: msg, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

async function fetchLeads() {
  loading.value = true
  try {
    const params: Record<string, string> = { type: 'leads', tab: 'leads' }
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (aiFilter.value !== 'all') params.ai_filter = aiFilter.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const res = await axios.get('/api/v2/data.php', {
      params,
      withCredentials: true
    })

    if (res.data.status === 'success') {
      leads.value = res.data.data || []
      services.value = res.data.services || []
      if (res.data.summary) {
        summary.value = res.data.summary
      }
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memuat data leads', 'error')
  } finally {
    loading.value = false
  }
}

async function fetchWaAnalytics() {
  waLoading.value = true
  try {
    const params: Record<string, string> = { type: 'leads', tab: 'wa_tracker' }
    if (waLogSearch.value.trim()) params.search = waLogSearch.value.trim()

    const res = await axios.get('/api/v2/data.php', {
      params,
      withCredentials: true
    })

    if (res.data.status === 'success' && res.data.data) {
      waSources.value = res.data.data.sources || []
      waPages.value = res.data.data.pages || []
      waLogs.value = res.data.data.logs || []
      waTotalClicks.value = res.data.data.total_clicks || 0
      waTodayClicks.value = res.data.data.today_clicks || 0
    }
  } catch (err: any) {
    showToast('Gagal memuat tracker WhatsApp', 'error')
  } finally {
    waLoading.value = false
  }
}

async function runScanAI() {
  scanning.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=leads',
      { action: 'scan_ai' },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast(res.data.message || 'Klasifikasi AI berhasil!', 'success')
      fetchLeads()
    } else {
      showToast(res.data.message || 'Proses AI selesai', 'info')
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memanggil AI Scanner', 'error')
  } finally {
    scanning.value = false
  }
}

function openDetail(lead: Lead) {
  selectedLead.value = lead
  editStatus.value = lead.status
}

async function saveStatus() {
  if (!selectedLead.value) return
  updatingStatus.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=leads',
      {
        id: selectedLead.value.id,
        status: editStatus.value
      },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Status lead berhasil diperbarui', 'success')
      selectedLead.value.status = editStatus.value as any
      fetchLeads()
    }
  } catch (err: any) {
    showToast('Gagal memperbarui status', 'error')
  } finally {
    updatingStatus.value = false
  }
}

async function confirmDeleteLead() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    const res = await axios.delete(`/api/v2/data.php?type=leads&id=${deleteTarget.value.id}`, {
      withCredentials: true
    })
    if (res.data.status === 'success') {
      showToast('Data lead berhasil dihapus', 'success')
      deleteTarget.value = null
      fetchLeads()
    }
  } catch (err: any) {
    showToast('Gagal menghapus lead', 'error')
  } finally {
    deleting.value = false
  }
}

function formatWaUrl(lead: Lead) {
  const rawNum = lead.whatsapp || lead.phone || ''
  const clean = rawNum.replace(/[^0-9]/g, '')
  if (!clean) return '#'
  const msg = encodeURIComponent(
    `Halo ${lead.name}, terima kasih telah menghubungi Arno D Clean. Ada yang bisa kami bantu?`
  )
  return `https://wa.me/${clean}?text=${msg}`
}

function formatSourceLabel(src: string) {
  switch (src) {
    case 'form_crm':
      return 'Form CRM Direct'
    case 'floating_wa':
      return 'Floating Button'
    case 'cta_banner':
      return 'Banner CTA'
    case 'header':
      return 'Header Nav'
    case 'contact_page':
      return 'Halaman Kontak'
    default:
      return src || 'Direct / Unknown'
  }
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
      minute: '2-digit'
    })
  } catch {
    return dateStr
  }
}

onMounted(() => {
  fetchLeads()
  fetchWaAnalytics()
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
          : toast.type === 'error'
          ? 'bg-rose-50 text-rose-800 border-rose-200'
          : 'bg-blue-50 text-blue-800 border-blue-200'
      "
    >
      <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600" />
      <AlertTriangle v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-600" />
      <Sparkles v-else class="w-5 h-5 text-blue-600" />
      <span>{{ toast.message }}</span>
      <button @click="toast.show = false" class="text-slate-400 hover:text-slate-600 ml-2">
        <X class="w-4 h-4" />
      </button>
    </div>

    <!-- Header & Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>Customer Leads & WhatsApp CRM</span>
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            Realtime
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola prospek formulir pelanggan dan pantau konversi tombol WhatsApp secara akurat.
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <button
          @click="runScanAI"
          :disabled="scanning"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs sm:text-sm font-semibold shadow-md shadow-blue-500/20 active:scale-95 transition-all disabled:opacity-50"
        >
          <Sparkles class="w-4 h-4" :class="scanning ? 'animate-spin' : ''" />
          <span>{{ scanning ? 'Scanning AI...' : 'Scan AI Otomatis' }}</span>
        </button>

        <button
          @click="() => { fetchLeads(); fetchWaAnalytics(); }"
          :disabled="loading || waLoading"
          class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-medium shadow-sm transition-all"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="loading || waLoading ? 'animate-spin' : ''" />
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Total Leads -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Form Leads</p>
          <h3 class="text-2xl font-bold text-slate-900 mt-1">{{ summary.total }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Database prospek CRM</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
          <Users class="w-6 h-6" />
        </div>
      </div>

      <!-- Total WA Clicks -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Klik WhatsApp</p>
          <h3 class="text-2xl font-bold text-emerald-600 mt-1">{{ summary.total_wa_clicks }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Seluruh tombol WA situs</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
          <MessageCircle class="w-6 h-6" />
        </div>
      </div>

      <!-- WA Today -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Klik WA Hari Ini</p>
          <h3 class="text-2xl font-bold text-indigo-600 mt-1">{{ summary.today_wa_clicks }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Respon visitor hari ini</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
          <Calendar class="w-6 h-6" />
        </div>
      </div>

      <!-- Top Source -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Sumber Teratas</p>
          <h3 class="text-lg font-bold text-slate-800 mt-1 truncate max-w-[140px]" :title="summary.top_source">
            {{ formatSourceLabel(summary.top_source) }}
          </h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Saluran konversi utama</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center">
          <PieChart class="w-6 h-6" />
        </div>
      </div>
    </div>

    <!-- Alert for Unsorted Leads -->
    <div
      v-if="summary.unsorted > 0"
      class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-amber-900"
    >
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
          <AlertTriangle class="w-5 h-5" />
        </div>
        <div>
          <p class="text-xs sm:text-sm font-semibold">
            Terdapat {{ summary.unsorted }} pesan masuk yang belum dianalisis oleh AI.
          </p>
          <p class="text-[11px] sm:text-xs text-amber-700/80">
            AI dapat otomatis memisahkan mana prospek asli (Genuine) dan spam/iklan.
          </p>
        </div>
      </div>
      <button
        @click="runScanAI"
        :disabled="scanning"
        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-sm transition-all self-start sm:self-auto flex items-center gap-1.5"
      >
        <Sparkles class="w-3.5 h-3.5" />
        <span>Sortir Sekarang</span>
      </button>
    </div>

    <!-- Main Navigation Card with 2 Tabs -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle overflow-hidden">
      <!-- Tabs Bar -->
      <div class="border-b border-slate-100 px-6 pt-4 flex gap-8">
        <button
          @click="activeTab = 'leads'"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all"
          :class="
            activeTab === 'leads'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          "
        >
          <Users class="w-4 h-4" />
          <span>Data Lead CRM</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
            {{ summary.total }}
          </span>
        </button>

        <button
          @click="() => { activeTab = 'wa_tracker'; fetchWaAnalytics(); }"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all"
          :class="
            activeTab === 'wa_tracker'
              ? 'border-emerald-600 text-emerald-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          "
        >
          <MessageCircle class="w-4 h-4" />
          <span>Tracker WhatsApp & Analisa</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">
            {{ summary.total_wa_clicks }} Klik
          </span>
        </button>
      </div>

      <!-- TAB 1 CONTENT: LEADS CRM TABLE -->
      <div v-if="activeTab === 'leads'" class="p-6 space-y-5">
        <!-- Sub-filters & Search Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <!-- AI Sub-filters -->
          <div class="flex items-center gap-1.5 bg-slate-50 p-1 rounded-xl border border-slate-100 flex-wrap">
            <button
              @click="() => { aiFilter = 'all'; fetchLeads(); }"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
              :class="
                aiFilter === 'all'
                  ? 'bg-white text-slate-800 shadow-sm'
                  : 'text-slate-500 hover:text-slate-800'
              "
            >
              Semua Pesan
            </button>
            <button
              @click="() => { aiFilter = 'genuine'; fetchLeads(); }"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1"
              :class="
                aiFilter === 'genuine'
                  ? 'bg-emerald-600 text-white shadow-sm'
                  : 'text-emerald-700 hover:bg-emerald-50'
              "
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              <span>Genuine</span>
            </button>
            <button
              @click="() => { aiFilter = 'spam'; fetchLeads(); }"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1"
              :class="
                aiFilter === 'spam'
                  ? 'bg-rose-600 text-white shadow-sm'
                  : 'text-rose-700 hover:bg-rose-50'
              "
            >
              <ShieldAlert class="w-3.5 h-3.5" />
              <span>Spam / Bot</span>
            </button>
            <button
              @click="() => { aiFilter = 'uncategorized'; fetchLeads(); }"
              class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
              :class="
                aiFilter === 'uncategorized'
                  ? 'bg-slate-700 text-white shadow-sm'
                  : 'text-slate-500 hover:text-slate-700'
              "
            >
              Unsorted ({{ summary.unsorted }})
            </button>
          </div>

          <!-- Search & Status Dropdown -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <div class="relative w-full sm:w-64">
              <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="searchQuery"
                @input="fetchLeads"
                type="text"
                placeholder="Cari nama, WA, email..."
                class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
              />
            </div>

            <select
              v-model="statusFilter"
              @change="fetchLeads"
              class="px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
            >
              <option value="all">Semua Status</option>
              <option value="new">Baru (New)</option>
              <option value="contacted">Sudah Dihubungi</option>
              <option value="closed">Selesai (Closed)</option>
              <option value="spam">Spam</option>
            </select>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                <th class="py-3.5 px-4 w-32">Waktu</th>
                <th class="py-3.5 px-4">Pelanggan</th>
                <th class="py-3.5 px-4">Kontak</th>
                <th class="py-3.5 px-4 min-w-[240px]">Pesan & Analisa AI</th>
                <th class="py-3.5 px-4 w-32">Status</th>
                <th class="py-3.5 px-4 w-24 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs">
              <tr v-if="loading">
                <td colspan="6" class="text-center py-12 text-slate-400">
                  <RefreshCw class="w-6 h-6 animate-spin mx-auto text-blue-500 mb-2" />
                  <span>Memuat data prospek...</span>
                </td>
              </tr>

              <tr v-else-if="leads.length === 0">
                <td colspan="6" class="text-center py-12 text-slate-400">
                  <Users class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                  <p class="font-medium text-slate-600">Tidak ada lead ditemukan</p>
                  <p class="text-[11px] text-slate-400 mt-0.5">Coba ubah kata kunci pencarian atau filter status.</p>
                </td>
              </tr>

              <tr
                v-else
                v-for="lead in leads"
                :key="lead.id"
                class="hover:bg-slate-50/60 transition-colors"
              >
                <!-- Waktu -->
                <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                  <div class="flex items-center gap-1.5 text-[11px]">
                    <Clock class="w-3.5 h-3.5 text-slate-400" />
                    <span>{{ formatDate(lead.created_at) }}</span>
                  </div>
                </td>

                <!-- Pelanggan & Layanan -->
                <td class="py-3.5 px-4">
                  <p class="font-bold text-slate-900">{{ lead.name }}</p>
                  <p class="text-[11px] text-blue-600 font-medium mt-0.5">
                    {{ lead.service_name || 'Layanan Umum' }}
                  </p>
                </td>

                <!-- Kontak -->
                <td class="py-3.5 px-4 space-y-1">
                  <div v-if="lead.whatsapp || lead.phone" class="flex items-center gap-2">
                    <a
                      :href="formatWaUrl(lead)"
                      target="_blank"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[10px] font-bold border border-emerald-200 transition-all"
                    >
                      <MessageCircle class="w-3 h-3 text-emerald-600" />
                      <span>Chat WA</span>
                    </a>
                    <span class="text-slate-700 font-mono text-[11px]">
                      {{ lead.whatsapp || lead.phone }}
                    </span>
                  </div>
                  <div v-if="lead.email" class="flex items-center gap-1.5 text-slate-500 text-[11px]">
                    <Mail class="w-3 h-3 text-slate-400" />
                    <span>{{ lead.email }}</span>
                  </div>
                </td>

                <!-- Pesan & AI Analysis -->
                <td class="py-3.5 px-4 space-y-1.5">
                  <p class="text-slate-800 line-clamp-2">{{ lead.message || '-' }}</p>
                  
                  <!-- AI Badge -->
                  <div class="flex items-center gap-2 flex-wrap">
                    <span
                      v-if="lead.ai_status === 'genuine'"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold"
                    >
                      <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                      <span>Genuine {{ lead.ai_confidence ? `(${lead.ai_confidence}%)` : '' }}</span>
                    </span>

                    <span
                      v-else-if="lead.ai_status === 'spam'"
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold"
                    >
                      <ShieldAlert class="w-3 h-3 text-rose-600" />
                      <span>Spam {{ lead.ai_confidence ? `(${lead.ai_confidence}%)` : '' }}</span>
                    </span>

                    <span
                      v-else
                      class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 text-[10px] font-semibold"
                    >
                      Unsorted
                    </span>

                    <!-- Reason preview -->
                    <span
                      v-if="lead.ai_analysis"
                      class="text-[10px] text-slate-400 italic truncate max-w-[220px]"
                      :title="lead.ai_analysis"
                    >
                      "{{ lead.ai_analysis }}"
                    </span>
                  </div>
                </td>

                <!-- Status Badge -->
                <td class="py-3.5 px-4">
                  <span
                    v-if="lead.status === 'new'"
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                  >
                    Baru
                  </span>
                  <span
                    v-else-if="lead.status === 'contacted'"
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                  >
                    Dihubungi
                  </span>
                  <span
                    v-else-if="lead.status === 'closed'"
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                  >
                    Selesai
                  </span>
                  <span
                    v-else
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                  >
                    Spam
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      @click="openDetail(lead)"
                      class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                      title="Lihat Detail"
                    >
                      <Eye class="w-4 h-4" />
                    </button>
                    <button
                      @click="deleteTarget = lead"
                      class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                      title="Hapus Lead"
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

      <!-- TAB 2 CONTENT: WA TRACKER & ANALYTICS -->
      <div v-if="activeTab === 'wa_tracker'" class="p-6 space-y-6">
        <!-- Top Analytics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Source Breakdown -->
          <div class="rounded-2xl border border-slate-100 p-5 bg-slate-50/50 space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <PieChart class="w-4 h-4 text-blue-600" />
                <span>Analisa Saluran Klik WA (Source)</span>
              </h4>
              <span class="text-xs text-slate-400 font-medium">{{ waTotalClicks }} total klik</span>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead>
                  <tr class="text-slate-400 text-[10px] uppercase font-bold border-b border-slate-200/60 pb-2">
                    <th class="text-left pb-2">Saluran</th>
                    <th class="text-right pb-2">Jumlah</th>
                    <th class="text-right pb-2 w-32">Persentase</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-if="waSources.length === 0">
                    <td colspan="3" class="text-center py-4 text-slate-400">Belum ada data sumber klik.</td>
                  </tr>
                  <tr v-for="s in waSources" :key="s.source" class="py-2.5">
                    <td class="py-2 font-medium text-slate-800">{{ formatSourceLabel(s.source) }}</td>
                    <td class="py-2 text-right font-bold text-slate-900">{{ s.count }}</td>
                    <td class="py-2 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <div class="w-20 bg-slate-200 rounded-full h-1.5 overflow-hidden">
                          <div
                            class="bg-emerald-500 h-1.5 rounded-full"
                            :style="{ width: `${Math.round((s.count / (waTotalClicks || 1)) * 100)}%` }"
                          ></div>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-600 w-8 text-right">
                          {{ Math.round((s.count / (waTotalClicks || 1)) * 100) }}%
                        </span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Top Pages Breakdown -->
          <div class="rounded-2xl border border-slate-100 p-5 bg-slate-50/50 space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                <ExternalLink class="w-4 h-4 text-emerald-600" />
                <span>Halaman Asal Pemicu WA (Top Pages)</span>
              </h4>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead>
                  <tr class="text-slate-400 text-[10px] uppercase font-bold border-b border-slate-200/60 pb-2">
                    <th class="text-left pb-2">URL Halaman / Artikel</th>
                    <th class="text-right pb-2">Klik WA</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-if="waPages.length === 0">
                    <td colspan="2" class="text-center py-4 text-slate-400">Belum ada data halaman.</td>
                  </tr>
                  <tr v-for="p in waPages" :key="p.page_url" class="py-2.5">
                    <td class="py-2 text-slate-700 truncate max-w-[280px]" :title="p.page_url">
                      <a :href="p.page_url" target="_blank" class="hover:text-blue-600 hover:underline">
                        {{ p.page_url }}
                      </a>
                    </td>
                    <td class="py-2 text-right font-bold text-emerald-600">{{ p.count }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Live Log Activity Table -->
        <div class="space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
              <Clock class="w-4 h-4 text-blue-600" />
              <span>Log Aktivitas Klik WhatsApp Terbaru</span>
            </h4>
            <div class="relative w-full sm:w-60">
              <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="waLogSearch"
                @input="fetchWaAnalytics"
                type="text"
                placeholder="Cari IP, source, URL..."
                class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              />
            </div>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                  <th class="py-3 px-4">Waktu</th>
                  <th class="py-3 px-4">Saluran (Source)</th>
                  <th class="py-3 px-4">Halaman Asal (Page URL)</th>
                  <th class="py-3 px-4">Referrer</th>
                  <th class="py-3 px-4">Perangkat</th>
                  <th class="py-3 px-4">IP Address</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="waLoading">
                  <td colspan="6" class="text-center py-8 text-slate-400">
                    <RefreshCw class="w-5 h-5 animate-spin mx-auto text-blue-500 mb-1" />
                    <span>Memuat log aktivitas...</span>
                  </td>
                </tr>
                <tr v-else-if="waLogs.length === 0">
                  <td colspan="6" class="text-center py-8 text-slate-400">Belum ada aktivitas klik WhatsApp.</td>
                </tr>
                <tr v-else v-for="log in waLogs" :key="log.id" class="hover:bg-slate-50/50">
                  <td class="py-3 px-4 text-slate-500 whitespace-nowrap">{{ formatDate(log.clicked_at) }}</td>
                  <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      {{ formatSourceLabel(log.source) }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-slate-700 truncate max-w-[220px]" :title="log.page_url || '/'">
                    {{ log.page_url || '/' }}
                  </td>
                  <td class="py-3 px-4 text-slate-500">{{ log.referrer || 'Direct' }}</td>
                  <td class="py-3 px-4">
                    <span
                      class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-medium"
                      :class="log.device === 'Mobile' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600'"
                    >
                      <Smartphone v-if="log.device === 'Mobile'" class="w-3 h-3" />
                      <Monitor v-else class="w-3 h-3" />
                      <span>{{ log.device || 'Desktop' }}</span>
                    </span>
                  </td>
                  <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ log.ip_address || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL DETAIL LEAD -->
    <div
      v-if="selectedLead"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-lg bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto animate-scaleUp">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Users class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">Detail Data Prospek</h3>
              <p class="text-[11px] text-slate-400 line-clamp-1">ID #{{ selectedLead.id }} • {{ formatDate(selectedLead.created_at) }}</p>
            </div>
          </div>
          <button @click="selectedLead = null" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Info Grid -->
        <div class="space-y-4 text-xs">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 p-3.5 rounded-2xl bg-slate-50 border border-slate-100">
            <div>
              <p class="text-[10px] uppercase font-bold text-slate-400">Nama Pelanggan</p>
              <p class="text-sm font-bold text-slate-900 mt-0.5 break-words">{{ selectedLead.name }}</p>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-slate-400">Layanan Tertarik</p>
              <p class="text-sm font-semibold text-blue-600 mt-0.5 break-words">{{ selectedLead.service_name || 'Layanan Umum' }}</p>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-slate-400">WhatsApp / HP</p>
              <p class="font-mono text-slate-800 mt-0.5 break-all">{{ selectedLead.whatsapp || selectedLead.phone || '-' }}</p>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-slate-400">Email</p>
              <p class="text-slate-800 mt-0.5 break-all">{{ selectedLead.email || '-' }}</p>
            </div>
          </div>

          <!-- Message -->
          <div class="space-y-1">
            <p class="text-[11px] font-bold text-slate-700">Isi Pesan Formulir:</p>
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 text-slate-700 leading-relaxed whitespace-pre-wrap break-words">
              {{ selectedLead.message || '(Tidak ada pesan teks)' }}
            </div>
          </div>

          <!-- AI Analysis Box -->
          <div class="p-3.5 rounded-2xl border space-y-1.5" :class="selectedLead.ai_status === 'genuine' ? 'bg-emerald-50/50 border-emerald-200 text-emerald-900' : selectedLead.ai_status === 'spam' ? 'bg-rose-50/50 border-rose-200 text-rose-900' : 'bg-slate-50 border-slate-200 text-slate-700'">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[11px] font-bold flex items-center gap-1.5 min-w-0">
                <Sparkles class="w-3.5 h-3.5 shrink-0" />
                <span class="truncate">Analisa Otomatis AI</span>
              </span>
              <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0" :class="selectedLead.ai_status === 'genuine' ? 'bg-emerald-100 text-emerald-800' : selectedLead.ai_status === 'spam' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700'">
                {{ selectedLead.ai_status }} ({{ selectedLead.ai_confidence || 0 }}%)
              </span>
            </div>
            <p class="text-xs italic break-words">{{ selectedLead.ai_analysis || 'Belum ada penjelasan AI untuk pesan ini.' }}</p>
          </div>

          <!-- Update Status Section -->
          <div class="space-y-2 pt-2 border-t border-slate-100">
            <label class="text-[11px] font-bold text-slate-700">Perbarui Status Prospek:</label>
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
              <select
                v-model="editStatus"
                class="flex-1 px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="new">Baru (New)</option>
                <option value="contacted">Sudah Dihubungi</option>
                <option value="closed">Selesai (Closed)</option>
                <option value="spam">Spam</option>
              </select>
              <button
                @click="saveStatus"
                :disabled="updatingStatus"
                class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs transition-all disabled:opacity-50 text-center"
              >
                {{ updatingStatus ? 'Menyimpan...' : 'Simpan Status' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 pt-3 border-t border-slate-100">
          <button
            @click="selectedLead = null"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all text-center"
          >
            Tutup
          </button>
          <a
            :href="formatWaUrl(selectedLead)"
            target="_blank"
            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-all text-center"
          >
            <MessageCircle class="w-4 h-4" />
            <span>Chat via WhatsApp</span>
          </a>
        </div>
      </div>
    </div>

    <!-- MODAL CONFIRM DELETE -->
    <div
      v-if="deleteTarget"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-sm bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-5 sm:p-6 space-y-4 animate-scaleUp text-center my-auto">
        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
          <Trash2 class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-base font-bold text-slate-900">Hapus Data Lead?</h3>
          <p class="text-xs text-slate-500 mt-1 break-words">
            Data lead dari <strong class="text-slate-800">{{ deleteTarget.name }}</strong> akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div class="flex flex-col-reverse sm:flex-row items-center gap-2 pt-2">
          <button
            @click="deleteTarget = null"
            class="w-full sm:w-1/2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all"
          >
            Batal
          </button>
          <button
            @click="confirmDeleteLead"
            :disabled="deleting"
            class="w-full sm:w-1/2 px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs shadow-sm transition-all disabled:opacity-50"
          >
            {{ deleting ? 'Menghapus...' : 'Ya, Hapus' }}
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

@keyframes scaleUp {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

.animate-fadeIn {
  animation: fadeIn 0.25s ease-out forwards;
}

.animate-scaleUp {
  animation: scaleUp 0.2s ease-out forwards;
}
</style>
