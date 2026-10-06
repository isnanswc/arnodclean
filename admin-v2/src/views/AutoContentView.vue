<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { apiClient as axios } from '@/api/client'
import {
  Sparkles,
  Rocket,
  Calendar,
  Settings,
  Wrench,
  Play,
  RotateCcw,
  Trash2,
  Plus,
  Search,
  CheckCircle2,
  AlertTriangle,
  X,
  ExternalLink,
  Clock,
  RefreshCw,
  Terminal,
  Pause,
  Sliders,
  ChevronDown
} from 'lucide-vue-next'

interface KeywordItem {
  id: number
  keyword: string
  status: 'pending' | 'processing' | 'done' | 'failed'
  error_message: string | null
  article_id: number | null
  article_title?: string | null
  article_slug?: string | null
  created_at: string
  processed_at?: string | null
}

interface Summary {
  pending: number
  processing: number
  done: number
  failed: number
  total: number
}

const loading = ref(false)
const keywords = ref<KeywordItem[]>([])
const settings = ref<Record<string, string>>({})
const summary = ref<Summary>({
  pending: 0,
  processing: 0,
  done: 0,
  failed: 0,
  total: 0
})

// Filters
const activeTab = ref<string>('all')
const searchQuery = ref<string>('')

// Turbo Mode & Delay
const turboEnabled = ref(false)
const turboDelay = ref<number>(3)
const updatingTurbo = ref(false)

// Modals
const showAddModal = ref(false)
const bulkKeywords = ref('')
const addingKeywords = ref(false)

const showScheduleModal = ref(false)
const scheduleEnabled = ref(false)
const scheduleMode = ref('smart')
const scheduleFrequency = ref(3)
const scheduleInterval = ref(1)
const scheduleTime = ref('08:00')
const scheduleDays = ref<string[]>([])
const savingSchedule = ref(false)

const showConfigModal = ref(false)
const aiActiveProvider = ref('gemini')
const geminiKeys = ref<string[]>([])
const groqKeys = ref<string[]>([])
const geminiModel = ref('gemini-1.5-flash')
const groqModel = ref('llama-3.3-70b-versatile')
const systemInstruction = ref('')
const promptTemplate = ref('')
const autoPublish = ref(true)
const generateImage = ref(true)
const savingConfig = ref(false)

// Tools dropdown
const showToolsDropdown = ref(false)

// Delete Confirm Modal
const deleteTarget = ref<KeywordItem | null>(null)
const deleting = ref(false)

// Bot Runner State
const showBotModal = ref(false)
const botRunning = ref(false)
const botInterval = ref<number>(1) // minutes between items
const botStatusTitle = ref('Siap Dijalankan')
const botStatusDesc = ref('Klik tombol mulai untuk memproses antrean AI.')
const botProgress = ref(0)
const botCountdown = ref(0)
const botLogs = ref<{ time: string; text: string; type: 'success' | 'error' | 'info' | 'warn' }[]>([])
let botTimer: any = null

// Toast
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

async function fetchData() {
  loading.value = true
  try {
    const res = await axios.get('/api/v2/data.php', {
      params: { type: 'auto_content' },
      withCredentials: true
    })

    if (res.data.status === 'success' && res.data.data) {
      keywords.value = res.data.data.keywords || []
      summary.value = res.data.data.summary || summary.value
      settings.value = res.data.data.settings || {}

      // Hydrate turbo mode
      turboEnabled.value = (settings.value['worker_turbo_mode'] ?? '0') === '1'
      turboDelay.value = parseInt(settings.value['worker_delay_minutes'] ?? '3') || 3

      // Hydrate schedule
      scheduleEnabled.value = (settings.value['ai_schedule_enabled'] ?? '0') === '1'
      scheduleMode.value = settings.value['ai_schedule_mode'] || 'smart'
      scheduleFrequency.value = parseInt(settings.value['ai_schedule_frequency'] ?? '3') || 3
      scheduleInterval.value = parseInt(settings.value['ai_schedule_interval'] ?? '1') || 1
      scheduleTime.value = settings.value['ai_schedule_time'] || '08:00'
      try {
        scheduleDays.value = JSON.parse(settings.value['ai_schedule_days'] || '[]')
      } catch {
        scheduleDays.value = []
      }

      // Hydrate AI Config
      aiActiveProvider.value = settings.value['ai_active_provider'] || 'gemini'
      try {
        geminiKeys.value = JSON.parse(settings.value['ai_config_gemini_keys'] || '[]')
      } catch {
        geminiKeys.value = settings.value['ai_api_key'] ? [settings.value['ai_api_key']] : []
      }
      try {
        groqKeys.value = JSON.parse(settings.value['ai_config_groq_keys'] || '[]')
      } catch {
        groqKeys.value = []
      }
      geminiModel.value = settings.value['ai_config_gemini_model'] || settings.value['ai_model'] || 'gemini-1.5-flash'
      groqModel.value = settings.value['ai_config_groq_model'] || 'llama-3.3-70b-versatile'
      systemInstruction.value = settings.value['ai_system_instruction'] || ''
      promptTemplate.value = settings.value['ai_prompt_template'] || ''
      autoPublish.value = (settings.value['auto_publish'] ?? '1') === '1'
      generateImage.value = (settings.value['ai_generate_image'] ?? '1') === '1'
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memuat antrean AI', 'error')
  } finally {
    loading.value = false
  }
}

// Filtered Keywords
const filteredKeywords = computed(() => {
  return keywords.value.filter(item => {
    const matchesTab = activeTab.value === 'all' || item.status === activeTab.value
    const matchesSearch =
      !searchQuery.value.trim() ||
      item.keyword.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (item.article_title && item.article_title.toLowerCase().includes(searchQuery.value.toLowerCase()))
    return matchesTab && matchesSearch
  })
})

async function saveTurboMode() {
  updatingTurbo.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      {
        action: 'update_turbo',
        mode: turboEnabled.value ? '1' : '0',
        delay: turboDelay.value
      },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Mode Turbo diperbarui', 'success')
    }
  } catch {
    showToast('Gagal memperbarui Mode Turbo', 'error')
  } finally {
    updatingTurbo.value = false
  }
}

async function submitBulkKeywords() {
  if (!bulkKeywords.value.trim()) return
  addingKeywords.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      {
        action: 'add_keywords',
        keywords: bulkKeywords.value
      },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast(res.data.message || 'Kata kunci berhasil ditambahkan', 'success')
      bulkKeywords.value = ''
      showAddModal.value = false
      fetchData()
    }
  } catch (err: any) {
    showToast('Gagal menambahkan kata kunci', 'error')
  } finally {
    addingKeywords.value = false
  }
}

async function retryKeyword(id: number) {
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      { action: 'retry_keyword', id },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Kata kunci di-reset ke pending', 'success')
      fetchData()
    }
  } catch {
    showToast('Gagal me-reset kata kunci', 'error')
  }
}

async function confirmDeleteKeyword() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      { action: 'delete_keyword', id: deleteTarget.value.id },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Kata kunci berhasil dihapus', 'success')
      deleteTarget.value = null
      fetchData()
    }
  } catch {
    showToast('Gagal menghapus kata kunci', 'error')
  } finally {
    deleting.value = false
  }
}

async function runToolResetFailed() {
  showToolsDropdown.value = false
  if (!confirm('Reset semua kata kunci gagal menjadi pending?')) return
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      { action: 'reset_failed' },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast(res.data.message || 'Berhasil reset antrean gagal', 'success')
      fetchData()
    }
  } catch {
    showToast('Gagal me-reset antrean', 'error')
  }
}

async function runToolClearQueue() {
  showToolsDropdown.value = false
  if (!confirm('Hapus semua antrean yang belum selesai?')) return
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      { action: 'clear_queue' },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast(res.data.message || 'Antrean berhasil dibersihkan', 'success')
      fetchData()
    }
  } catch {
    showToast('Gagal membersihkan antrean', 'error')
  }
}

async function saveScheduleSettings() {
  savingSchedule.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      {
        action: 'save_schedule',
        ai_schedule_enabled: scheduleEnabled.value ? '1' : '0',
        ai_schedule_mode: scheduleMode.value,
        ai_schedule_frequency: scheduleFrequency.value,
        ai_schedule_interval: scheduleInterval.value,
        ai_schedule_time: scheduleTime.value,
        ai_schedule_days: scheduleDays.value
      },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Jadwal otomatis berhasil disimpan', 'success')
      showScheduleModal.value = false
      fetchData()
    }
  } catch {
    showToast('Gagal menyimpan jadwal', 'error')
  } finally {
    savingSchedule.value = false
  }
}

async function saveAiConfiguration() {
  savingConfig.value = true
  try {
    const settingsPayload: Record<string, any> = {
      ai_active_provider: aiActiveProvider.value,
      ai_config_gemini_keys: geminiKeys.value.filter(k => k.trim()),
      ai_config_groq_keys: groqKeys.value.filter(k => k.trim()),
      ai_config_gemini_model: geminiModel.value,
      ai_config_groq_model: groqModel.value,
      ai_system_instruction: systemInstruction.value,
      ai_prompt_template: promptTemplate.value,
      auto_publish: autoPublish.value ? '1' : '0',
      ai_generate_image: generateImage.value ? '1' : '0'
    }

    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      {
        action: 'save_settings',
        settings: settingsPayload
      },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Konfigurasi AI berhasil disimpan', 'success')
      showConfigModal.value = false
      fetchData()
    }
  } catch {
    showToast('Gagal menyimpan konfigurasi AI', 'error')
  } finally {
    savingConfig.value = false
  }
}

// Bot Runner Logic
function appendBotLog(text: string, type: 'success' | 'error' | 'info' | 'warn' = 'info') {
  const d = new Date()
  const time = d.toTimeString().split(' ')[0]
  botLogs.value.unshift({ time, text, type })
  if (botLogs.value.length > 50) botLogs.value.pop()
}

function startBot() {
  botRunning.value = true
  botLogs.value = []
  appendBotLog('🚀 Bot dijalankan. Memeriksa antrean kata kunci...', 'info')
  processNextBotItem()
}

function stopBot() {
  botRunning.value = false
  if (botTimer) clearTimeout(botTimer)
  botStatusTitle.value = 'Dihentikan'
  botStatusDesc.value = 'Proses bot dihentikan secara manual.'
  botProgress.value = 0
  appendBotLog('⏹️ Bot dihentikan oleh pengguna.', 'warn')
}

async function processNextBotItem() {
  if (!botRunning.value) return

  botStatusTitle.value = 'Memproses Konten AI...'
  botStatusDesc.value = 'Sedang generate konten dengan model AI...'
  botProgress.value = 100

  try {
    const res = await axios.post(
      '/api/v2/data.php?type=auto_content',
      { action: 'run_single' },
      { withCredentials: true }
    )

    if (!botRunning.value) return

    if (res.data.status === 'success') {
      appendBotLog(`✅ ${res.data.message || 'Artikel berhasil dibuat.'}`, 'success')
      fetchData()
      scheduleNextBotItem()
    } else {
      if (res.data.message && res.data.message.includes('Antrian kosong')) {
        appendBotLog('🏁 Antrean selesai! Semua kata kunci sudah terproses.', 'info')
        finishBot()
      } else {
        appendBotLog(`❌ Gagal: ${res.data.message}`, 'error')
        finishBot()
      }
    }
  } catch (err: any) {
    appendBotLog(`❌ Koneksi error: ${err.message}`, 'error')
    finishBot()
  }
}

function scheduleNextBotItem() {
  const delaySec = Math.max(1, botInterval.value * 60)
  botCountdown.value = delaySec
  botStatusTitle.value = 'Menunggu Jeda...'

  const total = delaySec
  const tick = () => {
    if (!botRunning.value) return
    if (botCountdown.value <= 0) {
      processNextBotItem()
      return
    }
    botStatusDesc.value = `Lanjut item berikutnya dalam ${botCountdown.value} detik...`
    botProgress.value = Math.round((botCountdown.value / total) * 100)
    botCountdown.value--
    botTimer = setTimeout(tick, 1000)
  }
  tick()
}

function finishBot() {
  botRunning.value = false
  botStatusTitle.value = 'Selesai'
  botStatusDesc.value = 'Seluruh tugas telah diselesaikan.'
  botProgress.value = 0
  fetchData()
}

function formatDate(str: string | null | undefined) {
  if (!str) return '-'
  try {
    const d = new Date(str)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch {
    return str
  }
}

onMounted(() => {
  fetchData()
})

onUnmounted(() => {
  if (botTimer) clearTimeout(botTimer)
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn pb-12">
    <!-- Toast Alert -->
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
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>Auto Content AI</span>
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            Automated CMS
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola antrean dan otomatisasi penulisan artikel blog menggunakan model kecerdasan buatan.
        </p>
      </div>

      <!-- Actions Bar -->
      <div class="flex items-center gap-2.5 flex-wrap">
        <!-- AI Config Button -->
        <button
          @click="showConfigModal = true"
          class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-medium shadow-sm transition-all"
        >
          <Settings class="w-4 h-4 text-slate-500" />
          <span>Konfigurasi AI</span>
        </button>

        <!-- Schedule Button -->
        <button
          @click="showScheduleModal = true"
          class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-medium shadow-sm transition-all"
        >
          <Calendar class="w-4 h-4 text-blue-600" />
          <span>Penjadwalan</span>
          <span
            v-if="scheduleEnabled"
            class="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-emerald-100 text-emerald-800 ml-0.5"
          >
            ON
          </span>
        </button>

        <!-- Turbo Mode Toggle -->
        <div class="flex items-center bg-white border border-slate-200 rounded-xl px-2.5 py-1.5 shadow-sm gap-2">
          <label class="flex items-center gap-1.5 cursor-pointer select-none text-xs font-bold" :class="turboEnabled ? 'text-rose-600' : 'text-slate-600'">
            <input
              type="checkbox"
              v-model="turboEnabled"
              @change="saveTurboMode"
              class="sr-only"
            />
            <div class="w-7 h-4 bg-slate-200 rounded-full transition-colors relative" :class="turboEnabled ? 'bg-rose-500' : ''">
              <div class="w-3 h-3 bg-white rounded-full absolute top-0.5 left-0.5 transition-transform" :class="turboEnabled ? 'translate-x-3' : ''"></div>
            </div>
            <Rocket class="w-3.5 h-3.5" :class="turboEnabled ? 'animate-bounce text-rose-600' : 'text-slate-400'" />
            <span>TURBO</span>
          </label>

          <div class="border-l border-slate-200 pl-2 flex items-center gap-1 text-xs">
            <Clock class="w-3 h-3 text-slate-400" />
            <input
              v-model.number="turboDelay"
              @change="saveTurboMode"
              type="number"
              min="3"
              max="360"
              class="w-10 text-center font-bold text-slate-800 bg-transparent border-none p-0 focus:outline-none"
            />
            <span class="text-slate-400 text-[10px]">m</span>
          </div>
        </div>

        <!-- Tools Dropdown -->
        <div class="relative">
          <button
            @click="showToolsDropdown = !showToolsDropdown"
            class="inline-flex items-center gap-1 px-3 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs sm:text-sm font-medium shadow-sm transition-all"
          >
            <Wrench class="w-4 h-4 text-slate-500" />
            <span>Tools</span>
            <ChevronDown class="w-3.5 h-3.5 text-slate-400 ml-0.5" />
          </button>

          <div
            v-if="showToolsDropdown"
            class="absolute right-0 mt-1.5 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-40 animate-fadeIn"
          >
            <button
              @click="runToolResetFailed"
              class="w-full px-4 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 flex items-center gap-2"
            >
              <RotateCcw class="w-3.5 h-3.5 text-amber-500" />
              <span>Reset Antrean Gagal</span>
            </button>
            <button
              @click="runToolClearQueue"
              class="w-full px-4 py-2 text-left text-xs text-rose-600 hover:bg-rose-50 flex items-center gap-2"
            >
              <Trash2 class="w-3.5 h-3.5 text-rose-500" />
              <span>Bersihkan Antrian</span>
            </button>
          </div>
        </div>

        <!-- Run Bot Modal Trigger -->
        <button
          @click="showBotModal = true"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-blue-500/20 active:scale-95 transition-all"
        >
          <Play class="w-4 h-4 fill-white" />
          <span>Jalankan Bot</span>
        </button>
      </div>
    </div>

    <!-- Main Content Container with Tabs -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle overflow-hidden">
      <!-- Tabs Bar -->
      <div class="border-b border-slate-100 px-6 pt-4 flex gap-6 overflow-x-auto">
        <button
          @click="activeTab = 'all'"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap"
          :class="activeTab === 'all' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          <span>Semua</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
            {{ summary.total }}
          </span>
        </button>

        <button
          @click="activeTab = 'pending'"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap"
          :class="activeTab === 'pending' ? 'border-amber-500 text-amber-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          <span>Pending</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700">
            {{ summary.pending }}
          </span>
        </button>

        <button
          @click="activeTab = 'processing'"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap"
          :class="activeTab === 'processing' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          <span>Processing</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700">
            {{ summary.processing }}
          </span>
        </button>

        <button
          @click="activeTab = 'done'"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap"
          :class="activeTab === 'done' ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          <span>Selesai</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700">
            {{ summary.done }}
          </span>
        </button>

        <button
          @click="activeTab = 'failed'"
          class="pb-3.5 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap"
          :class="activeTab === 'failed' ? 'border-rose-600 text-rose-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
        >
          <span>Gagal</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700">
            {{ summary.failed }}
          </span>
        </button>
      </div>

      <!-- Queue Toolbar -->
      <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative w-full sm:w-72">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari topik atau kata kunci..."
            class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
          />
        </div>

        <button
          @click="showAddModal = true"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm active:scale-95 transition-all self-start sm:self-auto"
        >
          <Plus class="w-4 h-4" />
          <span>Tambah Topik Baru</span>
        </button>
      </div>

      <!-- Keywords Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
              <th class="py-3.5 px-4 w-12 text-center">#</th>
              <th class="py-3.5 px-4">Topic / Keyword</th>
              <th class="py-3.5 px-4 w-32">Status</th>
              <th class="py-3.5 px-4 w-40">Waktu</th>
              <th class="py-3.5 px-4 min-w-[200px]">Hasil Artikel / Log</th>
              <th class="py-3.5 px-4 w-24 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="6" class="text-center py-12 text-slate-400">
                <RefreshCw class="w-6 h-6 animate-spin mx-auto text-blue-500 mb-2" />
                <span>Memuat antrean kata kunci...</span>
              </td>
            </tr>

            <tr v-else-if="filteredKeywords.length === 0">
              <td colspan="6" class="text-center py-12 text-slate-400">
                <Sparkles class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                <p class="font-medium text-slate-600">Tidak ada kata kunci ditemukan</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Tambahkan kata kunci baru untuk memulai antrean.</p>
              </td>
            </tr>

            <tr
              v-else
              v-for="(item, idx) in filteredKeywords"
              :key="item.id"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <!-- Index -->
              <td class="py-3.5 px-4 text-center font-mono text-slate-400">{{ idx + 1 }}</td>

              <!-- Topic -->
              <td class="py-3.5 px-4">
                <p class="font-bold text-slate-900">{{ item.keyword }}</p>
              </td>

              <!-- Status Badge -->
              <td class="py-3.5 px-4">
                <span
                  v-if="item.status === 'pending'"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                >
                  Pending
                </span>
                <span
                  v-else-if="item.status === 'processing'"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200 inline-flex items-center gap-1"
                >
                  <RefreshCw class="w-3 h-3 animate-spin" />
                  <span>Processing</span>
                </span>
                <span
                  v-else-if="item.status === 'done'"
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1"
                >
                  <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                  <span>Selesai</span>
                </span>
                <span
                  v-else
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 inline-flex items-center gap-1"
                >
                  <AlertTriangle class="w-3 h-3 text-rose-600" />
                  <span>Gagal</span>
                </span>
              </td>

              <!-- Date -->
              <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                <p class="text-[11px]">{{ formatDate(item.processed_at || item.created_at) }}</p>
              </td>

              <!-- Result or Error -->
              <td class="py-3.5 px-4">
                <div v-if="item.status === 'done' && item.article_slug">
                  <a
                    :href="`/arno-dc/artikel/${item.article_slug}`"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-semibold text-xs transition-colors"
                  >
                    <span>{{ item.article_title || 'Lihat Artikel' }}</span>
                    <ExternalLink class="w-3 h-3" />
                  </a>
                </div>
                <div v-else-if="item.status === 'failed' && item.error_message" class="text-rose-600 text-[11px] italic max-w-xs truncate" :title="item.error_message">
                  {{ item.error_message }}
                </div>
                <div v-else class="text-slate-400 text-[11px]">
                  Menunggu antrean bot
                </div>
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-4 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    v-if="item.status === 'failed' || item.status === 'done'"
                    @click="retryKeyword(item.id)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                    title="Reset ke Pending"
                  >
                    <RotateCcw class="w-4 h-4" />
                  </button>
                  <button
                    @click="deleteTarget = item"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                    title="Hapus Kata Kunci"
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

    <!-- MODAL TAMBAH TOPIK (BULK) -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-lg bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 my-auto max-h-[90vh] overflow-y-auto animate-scaleUp">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Plus class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">Tambah Topik Kata Kunci</h3>
              <p class="text-[11px] text-slate-400 line-clamp-1 sm:line-clamp-none">Bisa memasukkan banyak kata kunci sekaligus (1 baris per topik)</p>
            </div>
          </div>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-2">
          <label class="text-xs font-bold text-slate-700">Daftar Kata Kunci:</label>
          <textarea
            v-model="bulkKeywords"
            rows="6"
            placeholder="Contoh:&#10;Cara membersihkan sofa kain basah&#10;Tips merawat kasur springbed anti tungau&#10;Jasa cuci karpet kantor Tangerang"
            class="w-full p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono"
          ></textarea>
          <p class="text-[11px] text-slate-400 italic">
            Tips: Pisahkan setiap topik dengan baris baru (Enter) atau tanda koma.
          </p>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 pt-3 border-t border-slate-100">
          <button
            @click="showAddModal = false"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all text-center"
          >
            Batal
          </button>
          <button
            @click="submitBulkKeywords"
            :disabled="addingKeywords || !bulkKeywords.trim()"
            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all disabled:opacity-50 text-center"
          >
            {{ addingKeywords ? 'Menyimpan...' : 'Tambahkan ke Antrean' }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL PENJADWALAN -->
    <div
      v-if="showScheduleModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-lg bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto animate-scaleUp">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Calendar class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">Pengaturan Penjadwalan AI</h3>
              <p class="text-[11px] text-slate-400 line-clamp-1 sm:line-clamp-none">Atur otomatisasi posting harian/berkala.</p>
            </div>
          </div>
          <button @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4 text-xs">
          <!-- Toggle Schedule Enabled -->
          <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-3">
            <div>
              <p class="font-bold text-slate-900">Aktifkan Penjadwalan Otomatis</p>
              <p class="text-[11px] text-slate-500">Cron Job akan memproses antrian sesuai jadwal</p>
            </div>
            <label class="cursor-pointer shrink-0">
              <input type="checkbox" v-model="scheduleEnabled" class="sr-only" />
              <div class="w-10 h-6 bg-slate-200 rounded-full transition-colors relative" :class="scheduleEnabled ? 'bg-blue-600' : ''">
                <div class="w-4 h-4 bg-white rounded-full absolute top-1 left-1 transition-transform" :class="scheduleEnabled ? 'translate-x-4' : ''"></div>
              </div>
            </label>
          </div>

          <!-- Schedule Mode -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700">Mode Jadwal:</label>
            <select
              v-model="scheduleMode"
              class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="smart">Smart Interval (Berdasarkan Jam Kerja)</option>
              <option value="daily">Harian pada Jam Tertentu</option>
            </select>
          </div>

          <!-- Frequency / Time -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="font-bold text-slate-700">Frekuensi (Artikel / Hari):</label>
              <input
                v-model.number="scheduleFrequency"
                type="number"
                min="1"
                max="24"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800"
              />
            </div>
            <div class="space-y-1">
              <label class="font-bold text-slate-700">Waktu Eksekusi Awal:</label>
              <input
                v-model="scheduleTime"
                type="time"
                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800"
              />
            </div>
          </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 pt-3 border-t border-slate-100">
          <button
            @click="showScheduleModal = false"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all text-center"
          >
            Batal
          </button>
          <button
            @click="saveScheduleSettings"
            :disabled="savingSchedule"
            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all disabled:opacity-50 text-center"
          >
            {{ savingSchedule ? 'Menyimpan...' : 'Simpan Jadwal' }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL KONFIGURASI AI -->
    <div
      v-if="showConfigModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-2xl bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 sm:space-y-5 animate-scaleUp my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 sticky top-0 bg-white z-10 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Settings class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">Konfigurasi Otak AI (AI Brain)</h3>
              <p class="text-[11px] text-slate-400 line-clamp-1 sm:line-clamp-none">Model, Prompt, API Key, dan Otomatisasi Konten</p>
            </div>
          </div>
          <button @click="showConfigModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4 text-xs">
          <!-- Active Provider Selector -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700">Provider Utama (Primary Provider):</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3">
              <label
                class="p-3 rounded-2xl border cursor-pointer flex items-center gap-2.5 transition-all"
                :class="aiActiveProvider === 'gemini' ? 'bg-blue-50/60 border-blue-500 text-blue-900 font-bold' : 'border-slate-200 text-slate-600'"
              >
                <input type="radio" value="gemini" v-model="aiActiveProvider" class="sr-only" />
                <div class="w-3.5 h-3.5 rounded-full border border-blue-500 flex items-center justify-center shrink-0">
                  <div v-if="aiActiveProvider === 'gemini'" class="w-2 h-2 rounded-full bg-blue-600"></div>
                </div>
                <span class="truncate">Google Gemini (Rekomendasi)</span>
              </label>

              <label
                class="p-3 rounded-2xl border cursor-pointer flex items-center gap-2.5 transition-all"
                :class="aiActiveProvider === 'groq' ? 'bg-indigo-50/60 border-indigo-500 text-indigo-900 font-bold' : 'border-slate-200 text-slate-600'"
              >
                <input type="radio" value="groq" v-model="aiActiveProvider" class="sr-only" />
                <div class="w-3.5 h-3.5 rounded-full border border-indigo-500 flex items-center justify-center shrink-0">
                  <div v-if="aiActiveProvider === 'groq'" class="w-2 h-2 rounded-full bg-indigo-600"></div>
                </div>
                <span class="truncate">Groq LLaMA (Super Cepat)</span>
              </label>
            </div>
          </div>

          <!-- Gemini Settings -->
          <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-3">
            <p class="font-bold text-slate-800 flex items-center gap-1.5">
              <Sparkles class="w-3.5 h-3.5 text-blue-600" />
              <span>Google Gemini Settings</span>
            </p>
            <div class="space-y-1">
              <label class="font-semibold text-slate-600">Gemini Model:</label>
              <input
                v-model="geminiModel"
                type="text"
                class="w-full px-3 py-2 rounded-xl bg-white border border-slate-200 font-mono text-slate-800"
                placeholder="gemini-1.5-flash"
              />
            </div>
            <div class="space-y-1">
              <label class="font-semibold text-slate-600">Gemini API Keys (Multi-key failover, 1 baris per key):</label>
              <textarea
                :value="geminiKeys.join('\n')"
                @input="(e: any) => geminiKeys = e.target.value.split('\n')"
                rows="3"
                class="w-full p-2.5 rounded-xl bg-white border border-slate-200 font-mono text-slate-800"
                placeholder="AIzaSy..."
              ></textarea>
            </div>
          </div>

          <!-- Prompt Template -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700">Prompt Template Konten:</label>
            <textarea
              v-model="promptTemplate"
              rows="4"
              class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-mono"
            ></textarea>
            <p class="text-[10px] text-slate-400">Gunakan tag <code>{keyword}</code> di dalam template.</p>
          </div>

          <!-- System Instruction -->
          <div class="space-y-1.5">
            <label class="font-bold text-slate-700">System Instruction (Instruksi Peran Ahli):</label>
            <textarea
              v-model="systemInstruction"
              rows="3"
              class="w-full p-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800"
            ></textarea>
          </div>

          <!-- Switches -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-3 pt-1">
            <label class="p-3 rounded-2xl border border-slate-200 flex items-center justify-between cursor-pointer">
              <span class="font-semibold text-slate-700">Publikasikan Otomatis</span>
              <input type="checkbox" v-model="autoPublish" class="rounded text-blue-600" />
            </label>
            <label class="p-3 rounded-2xl border border-slate-200 flex items-center justify-between cursor-pointer">
              <span class="font-semibold text-slate-700">Generate Gambar AI</span>
              <input type="checkbox" v-model="generateImage" class="rounded text-blue-600" />
            </label>
          </div>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 pt-3 border-t border-slate-100 sticky bottom-0 bg-white">
          <button
            @click="showConfigModal = false"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all text-center"
          >
            Batal
          </button>
          <button
            @click="saveAiConfiguration"
            :disabled="savingConfig"
            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all disabled:opacity-50 text-center"
          >
            {{ savingConfig ? 'Menyimpan...' : 'Simpan Konfigurasi AI' }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL LIVE BOT RUNNER -->
    <div
      v-if="showBotModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-xl bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 sm:space-y-5 animate-scaleUp my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Play class="w-5 h-5 fill-blue-600" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">Live AI Bot Runner</h3>
              <p class="text-[11px] text-slate-400 line-clamp-1 sm:line-clamp-none">Eksekusi antrean kata kunci secara interaktif di browser</p>
            </div>
          </div>
          <button @click="showBotModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Bot Live Status Display -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-900 text-white space-y-3 shadow-inner">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 min-w-0">
              <div
                class="w-3 h-3 rounded-full shrink-0"
                :class="botRunning ? 'bg-emerald-400 animate-ping' : 'bg-slate-500'"
              ></div>
              <span class="font-bold text-sm tracking-wide truncate">{{ botStatusTitle }}</span>
            </div>
            <span class="text-xs text-slate-400 shrink-0">{{ botRunning ? 'Running' : 'Idle' }}</span>
          </div>

          <p class="text-xs text-slate-300">{{ botStatusDesc }}</p>

          <!-- Progress Bar -->
          <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
            <div
              class="bg-blue-500 h-2 rounded-full transition-all duration-300"
              :style="{ width: `${botProgress}%` }"
            ></div>
          </div>
        </div>

        <!-- Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
          <div class="flex items-center justify-between sm:justify-start gap-2">
            <label class="font-semibold text-slate-600">Jeda Antar Artikel:</label>
            <select
              v-model.number="botInterval"
              :disabled="botRunning"
              class="px-2.5 py-1.5 rounded-xl bg-slate-50 border border-slate-200 font-bold text-slate-800"
            >
              <option :value="0">Tanpa Jeda (0 m)</option>
              <option :value="1">1 Menit</option>
              <option :value="2">2 Menit</option>
              <option :value="3">3 Menit</option>
            </select>
          </div>

          <div class="flex items-center gap-2 w-full sm:w-auto">
            <button
              v-if="!botRunning"
              @click="startBot"
              class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all"
            >
              <Play class="w-3.5 h-3.5 fill-white" />
              <span>Mulai Bot</span>
            </button>
            <button
              v-else
              @click="stopBot"
              class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all"
            >
              <Pause class="w-3.5 h-3.5" />
              <span>Hentikan</span>
            </button>
          </div>
        </div>

        <!-- Terminal Logs -->
        <div class="space-y-1.5">
          <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700">
            <Terminal class="w-4 h-4 text-blue-600" />
            <span>Terminal Output:</span>
          </div>
          <div class="bg-slate-950 text-slate-200 font-mono text-[11px] p-3.5 rounded-2xl h-44 overflow-y-auto space-y-1 border border-slate-800">
            <div v-if="botLogs.length === 0" class="text-slate-500 italic">
              Log aktivitas eksekusi AI akan tampil di sini saat bot mulai bekerja...
            </div>
            <div
              v-for="(log, i) in botLogs"
              :key="i"
              class="flex items-start gap-2"
              :class="
                log.type === 'success'
                  ? 'text-emerald-400'
                  : log.type === 'error'
                  ? 'text-rose-400'
                  : log.type === 'warn'
                  ? 'text-amber-400'
                  : 'text-slate-300'
              "
            >
              <span class="text-slate-600 select-none">[{{ log.time }}]</span>
              <span>{{ log.text }}</span>
            </div>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row sm:justify-end">
          <button
            @click="showBotModal = false"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all text-center"
          >
            Tutup Jendela
          </button>
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
          <h3 class="text-base font-bold text-slate-900">Hapus Kata Kunci?</h3>
          <p class="text-xs text-slate-500 mt-1 break-words">
            Topik <strong class="text-slate-800">{{ deleteTarget.keyword }}</strong> akan dihapus dari antrean AI.
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
            @click="confirmDeleteKeyword"
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
