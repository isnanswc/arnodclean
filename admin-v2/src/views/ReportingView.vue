<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import axios from 'axios'
import { Chart, registerables } from 'chart.js'
import {
  TrendingUp,
  Eye,
  Users,
  MessageCircle,
  Percent,
  Calendar,
  Send,
  Sparkles,
  RefreshCw,
  Smartphone,
  Monitor,
  ExternalLink,
  CheckCircle2,
  AlertTriangle,
  X,
  Clock,
  Layers,
  MapPin,
  Download,
  FileSpreadsheet,
  FileText,
  Search,
  Filter,
  Flame,
  ArrowUp,
  ArrowDown,
  Minus,
  Check,
  ChevronLeft,
  ChevronRight
} from 'lucide-vue-next'

Chart.register(...registerables)

interface TrendItem {
  date: string
  total: number
  unique_visits: number
  bots?: number
  humans?: number
}

interface TopPage {
  page_url: string
  views: number
}

interface TopCity {
  city: string
  visitors: number
}

interface DeviceItem {
  device: string
  count: number
}

interface SourceItem {
  source: string
  visits: number
}

interface WaLogItem {
  no: number
  id: number
  clicked_at: string
  button_label: string
  page_url: string
  ip_address: string
  location: string
  device: string
}

interface ReportData {
  summary: {
    total_views: number
    unique_visitors: number
    bot_count: number
    total_wa_clicks: number
    conversion_rate: number
    bounce_rate: number
  }
  growth: {
    total_views: number
    unique_visitors: number
    bot_count: number
  }
  smart_summary: string
  trends: TrendItem[]
  forecast: Array<{ date: string; val: number }>
  charts: {
    traffic: TrendItem[]
    heatmap: Array<{ day_index: number; hour_index: number; visits: number }>
    wa_heatmap: Array<{ day_index: number; hour_index: number; clicks: number }>
  }
  wa_logs: WaLogItem[]
  top_pages: TopPage[]
  top_cities: TopCity[]
  devices: DeviceItem[]
  top_sources: SourceItem[]
  telegram_config: {
    time: string
    config: string[]
    has_bot: boolean
  }
}

const loading = ref(false)
const selectedDays = ref<number>(30) // 7, 30, 90
const reportData = ref<ReportData | null>(null)

// Heatmap Mode
const heatmapType = ref<'traffic' | 'wa'>('traffic')

// WA Logs Filter & Pagination
const waSearch = ref('')
const waDeviceFilter = ref('')
const currentWaPage = ref(1)
const waPageSize = 10

// Telegram Modal
const showTgModal = ref(false)
const tgTime = ref('08:00')
const tgConfig = ref<string[]>(['leads', 'traffic'])
const savingTg = ref(false)
const sendingTg = ref(false)

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

// Chart DOM refs & instances
const trafficCanvas = ref<HTMLCanvasElement | null>(null)
const heatmapBarCanvas = ref<HTMLCanvasElement | null>(null)
const deviceCanvas = ref<HTMLCanvasElement | null>(null)
const sourceCanvas = ref<HTMLCanvasElement | null>(null)

let trafficChartInstance: Chart | null = null
let heatmapBarChartInstance: Chart | null = null
let deviceChartInstance: Chart | null = null
let sourceChartInstance: Chart | null = null

async function fetchReportData() {
  loading.value = true
  try {
    const end = new Date()
    const start = new Date()
    start.setDate(end.getDate() - selectedDays.value)

    const startDate = start.toISOString().split('T')[0]
    const endDate = end.toISOString().split('T')[0]

    const res = await axios.get('/api/v2/data.php', {
      params: {
        type: 'reporting',
        start_date: startDate,
        end_date: endDate
      },
      withCredentials: true
    })

    if (res.data.status === 'success' && res.data.data) {
      reportData.value = res.data.data
      tgTime.value = res.data.data.telegram_config?.time || '08:00'
      tgConfig.value = res.data.data.telegram_config?.config || ['leads', 'traffic']

      await nextTick()
      renderAllCharts()
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memuat data laporan analitik', 'error')
  } finally {
    loading.value = false
  }
}

// Filtered WA Logs
const filteredWaLogs = computed(() => {
  if (!reportData.value?.wa_logs) return []
  const q = waSearch.value.toLowerCase().trim()
  const dev = waDeviceFilter.value

  return reportData.value.wa_logs.filter((log) => {
    const matchSearch =
      !q ||
      log.button_label.toLowerCase().includes(q) ||
      log.page_url.toLowerCase().includes(q) ||
      log.ip_address.toLowerCase().includes(q) ||
      log.location.toLowerCase().includes(q)

    const matchDevice = !dev || log.device === dev
    return matchSearch && matchDevice
  })
})

const paginatedWaLogs = computed(() => {
  const start = (currentWaPage.value - 1) * waPageSize
  return filteredWaLogs.value.slice(start, start + waPageSize)
})

const totalWaPages = computed(() => {
  return Math.ceil(filteredWaLogs.value.length / waPageSize) || 1
})

// Heatmap Matrix Computed (7 Days x 24 Hours)
const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']

const heatmapMatrix = computed(() => {
  const matrix = Array.from({ length: 7 }, () => new Array(24).fill(0))
  if (!reportData.value?.charts) return { matrix, maxVal: 1, hourlyTotals: new Array(24).fill(0) }

  const list =
    heatmapType.value === 'wa'
      ? reportData.value.charts.wa_heatmap || []
      : reportData.value.charts.heatmap || []

  let maxVal = 1
  const hourlyTotals = new Array(24).fill(0)

  list.forEach((item: any) => {
    const d = parseInt(item.day_index)
    const h = parseInt(item.hour_index)
    const val = parseInt(item.clicks || item.visits || 0)
    if (d >= 0 && d < 7 && h >= 0 && h < 24) {
      matrix[d][h] = val
      hourlyTotals[h] += val
      if (val > maxVal) maxVal = val
    }
  })

  return { matrix, maxVal, hourlyTotals }
})

function getHeatmapCellStyle(val: number, max: number) {
  if (val === 0) return { background: '#f8fafc', color: '#94a3b8' }
  const ratio = Math.min(1, val / Math.max(1, max))
  if (heatmapType.value === 'wa') {
    const alpha = Math.max(0.18, ratio).toFixed(2)
    return {
      background: `rgba(16, 185, 129, ${alpha})`,
      color: ratio > 0.55 ? '#ffffff' : '#065f46'
    }
  } else {
    const alpha = Math.max(0.18, ratio).toFixed(2)
    return {
      background: `rgba(37, 99, 235, ${alpha})`,
      color: ratio > 0.55 ? '#ffffff' : '#1e40af'
    }
  }
}

// Chart Rendering
function renderAllCharts() {
  if (!reportData.value) return
  const fontFamily = "'Plus Jakarta Sans', 'Inter', system-ui, sans-serif"

  // 1. Traffic Trends & AI Forecast Chart
  if (trafficCanvas.value) {
    if (trafficChartInstance) trafficChartInstance.destroy()

    const rawTrends = reportData.value.trends || []
    const dates = rawTrends.map((t) => {
      const dt = new Date(t.date)
      return dt.toLocaleDateString('id-ID', { month: 'short', day: 'numeric' })
    })
    const totals = rawTrends.map((t) => t.total)
    const uniques = rawTrends.map((t) => t.unique_visits)

    const forecastData = new Array(totals.length).fill(null)
    const rawForecast = reportData.value.forecast || []
    if (rawForecast.length > 0 && totals.length > 0) {
      forecastData[totals.length - 1] = totals[totals.length - 1]
      rawForecast.forEach((f) => {
        const dt = new Date(f.date)
        dates.push(dt.toLocaleDateString('id-ID', { month: 'short', day: 'numeric' }))
        totals.push(null as any)
        uniques.push(null as any)
        forecastData.push(f.val)
      })
    }

    trafficChartInstance = new Chart(trafficCanvas.value, {
      type: 'line',
      data: {
        labels: dates,
        datasets: [
          {
            label: 'Total Views',
            data: totals,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            fill: true,
            tension: 0.35,
            borderWidth: 2.5,
            pointRadius: dates.length > 20 ? 0 : 3
          },
          {
            label: 'AI Forecast 7 Hari',
            data: forecastData,
            borderColor: '#06b6d4',
            backgroundColor: 'transparent',
            borderDash: [5, 5],
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 0
          },
          {
            label: 'Unique Visitors',
            data: uniques,
            borderColor: '#10b981',
            backgroundColor: 'transparent',
            borderDash: [2, 2],
            borderWidth: 2,
            tension: 0.35,
            pointRadius: dates.length > 20 ? 0 : 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: {
              boxWidth: 8,
              usePointStyle: true,
              font: { family: fontFamily, size: 11, weight: 600 }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { family: fontFamily, size: 10 }, color: '#64748b' }
          },
          y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: { font: { family: fontFamily, size: 10 }, precision: 0 }
          }
        }
      }
    })
  }

  // 2. Hourly Bar Chart below heatmap
  renderHourlyChart()

  // 3. Devices Doughnut Chart
  if (deviceCanvas.value) {
    if (deviceChartInstance) deviceChartInstance.destroy()
    const devices = reportData.value.devices || []
    const labels = devices.map((d) => d.device)
    const data = devices.map((d) => d.count)

    deviceChartInstance = new Chart(deviceCanvas.value, {
      type: 'doughnut',
      data: {
        labels: labels.length ? labels : ['Desktop', 'Mobile'],
        datasets: [
          {
            data: data.length ? data : [1, 1],
            backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#64748b'],
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 8, usePointStyle: true, font: { family: fontFamily, size: 10 } }
          }
        }
      }
    })
  }

  // 4. Sources Pie Chart
  if (sourceCanvas.value) {
    if (sourceChartInstance) sourceChartInstance.destroy()
    const sources = reportData.value.top_sources || []
    const labels = sources.map((s) => s.source)
    const data = sources.map((s) => s.visits)

    sourceChartInstance = new Chart(sourceCanvas.value, {
      type: 'pie',
      data: {
        labels: labels.length ? labels : ['Direct'],
        datasets: [
          {
            data: data.length ? data : [1],
            backgroundColor: ['#3b82f6', '#8b5cf6', '#ec4899', '#f97316', '#10b981'],
            borderWidth: 0
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 8, usePointStyle: true, font: { family: fontFamily, size: 10 } }
          }
        }
      }
    })
  }
}

function renderHourlyChart() {
  if (!heatmapBarCanvas.value) return
  if (heatmapBarChartInstance) heatmapBarChartInstance.destroy()

  const fontFamily = "'Plus Jakarta Sans', 'Inter', system-ui, sans-serif"
  const totals = heatmapMatrix.value.hourlyTotals
  const hourLabels = totals.map((_, i) => `${i < 10 ? '0' + i : i}:00`)

  heatmapBarChartInstance = new Chart(heatmapBarCanvas.value, {
    type: 'bar',
    data: {
      labels: hourLabels,
      datasets: [
        {
          label: heatmapType.value === 'wa' ? 'Total Klik WhatsApp / Jam' : 'Total Kunjungan Visitor / Jam',
          data: totals,
          backgroundColor: heatmapType.value === 'wa' ? '#10b981' : '#2563eb',
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { family: fontFamily, size: 9 }, color: '#64748b' }
        },
        y: {
          beginAtZero: true,
          grid: { color: '#f1f5f9' },
          ticks: { font: { family: fontFamily, size: 9 }, precision: 0 }
        }
      }
    }
  })
}

watch(heatmapType, () => {
  renderHourlyChart()
})

// Telegram Settings & Instant Trigger
async function saveTelegramSettings() {
  savingTg.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=reporting',
      {
        action: 'save_telegram_config',
        time: tgTime.value,
        config: tgConfig.value
      },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Konfigurasi laporan Telegram disimpan', 'success')
      showTgModal.value = false
      fetchReportData()
    }
  } catch {
    showToast('Gagal menyimpan konfigurasi Telegram', 'error')
  } finally {
    savingTg.value = false
  }
}

async function triggerTelegramNow() {
  sendingTg.value = true
  try {
    const res = await axios.post(
      '/api/v2/data.php?type=reporting',
      { action: 'send_report_now' },
      { withCredentials: true }
    )
    if (res.data.status === 'success') {
      showToast('Laporan Telegram berhasil dikirim ke channel!', 'success')
      showTgModal.value = false
    }
  } catch {
    showToast('Gagal mengirim laporan Telegram.', 'error')
  } finally {
    sendingTg.value = false
  }
}

// Exports
function exportExcel() {
  if (!reportData.value?.wa_logs?.length) {
    showToast('Tidak ada data log untuk diekspor', 'error')
    return
  }
  const headers = ['No', 'Tanggal Jam', 'Tombol Halaman', 'IP Address', 'Wilayah', 'Perangkat']
  const rows = reportData.value.wa_logs.map((l) => [
    l.no,
    l.clicked_at,
    `"${l.button_label.replace(/"/g, '""')}"`,
    l.ip_address,
    `"${l.location.replace(/"/g, '""')}"`,
    l.device
  ])

  const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map((r) => r.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  link.setAttribute('download', `laporan-analitik-${new Date().toISOString().slice(0, 10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  showToast('Laporan Excel (CSV) berhasil diunduh', 'success')
}

function exportPDF() {
  window.print()
}

onMounted(() => {
  fetchReportData()
})

onBeforeUnmount(() => {
  if (trafficChartInstance) trafficChartInstance.destroy()
  if (heatmapBarChartInstance) heatmapBarChartInstance.destroy()
  if (deviceChartInstance) deviceChartInstance.destroy()
  if (sourceChartInstance) sourceChartInstance.destroy()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn pb-12 print:p-0 print:space-y-4">
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
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 print:hidden">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>Laporan Lanjutan & Analytics</span>
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            Insights
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Analisa mendalam performa kunjungan situs, rasio konversi WhatsApp, dan tren harian.
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <!-- Date Presets Buttons -->
        <div class="flex items-center bg-white border border-slate-200 rounded-xl p-1 shadow-sm text-xs font-semibold">
          <button
            @click="() => { selectedDays = 7; fetchReportData(); }"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="selectedDays === 7 ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          >
            7 Hari
          </button>
          <button
            @click="() => { selectedDays = 30; fetchReportData(); }"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="selectedDays === 30 ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          >
            30 Hari
          </button>
          <button
            @click="() => { selectedDays = 90; fetchReportData(); }"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="selectedDays === 90 ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          >
            90 Hari
          </button>
        </div>

        <!-- Export Buttons (PDF & Excel) -->
        <div class="inline-flex items-center bg-white border border-slate-200 rounded-xl p-1 shadow-sm text-xs font-semibold">
          <button
            @click="exportPDF"
            class="px-2.5 py-1.5 rounded-lg text-slate-700 hover:text-rose-600 hover:bg-slate-50 flex items-center gap-1.5 transition-colors"
            title="Cetak Laporan PDF"
          >
            <FileText class="w-3.5 h-3.5 text-rose-500" />
            <span>PDF</span>
          </button>
          <button
            @click="exportExcel"
            class="px-2.5 py-1.5 rounded-lg text-slate-700 hover:text-emerald-600 hover:bg-slate-50 flex items-center gap-1.5 transition-colors"
            title="Unduh File Excel / CSV"
          >
            <FileSpreadsheet class="w-3.5 h-3.5 text-emerald-600" />
            <span>Excel</span>
          </button>
        </div>

        <!-- Telegram Config Modal Button -->
        <button
          @click="showTgModal = true"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm transition-all"
        >
          <Send class="w-3.5 h-3.5 text-blue-500" />
          <span>Konfigurasi Telegram</span>
        </button>

        <button
          @click="fetchReportData"
          :disabled="loading"
          class="p-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm transition-all"
          title="Refresh Data"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="loading ? 'animate-spin' : ''" />
        </button>
      </div>
    </div>

    <!-- AI Smart Insight Banner -->
    <div
      v-if="reportData?.smart_summary"
      class="p-4 rounded-3xl bg-gradient-to-r from-blue-50/80 via-indigo-50/50 to-sky-50/80 border border-blue-100/80 flex items-start gap-3.5 text-blue-950 shadow-sm"
    >
      <div class="w-10 h-10 rounded-2xl bg-white shadow-sm text-blue-600 flex items-center justify-center flex-shrink-0">
        <Sparkles class="w-5 h-5" />
      </div>
      <div>
        <p class="text-xs font-bold uppercase tracking-wider text-blue-700">AI Executive Summary</p>
        <p class="text-xs sm:text-sm mt-0.5 leading-relaxed font-medium" v-html="reportData.smart_summary"></p>
      </div>
    </div>

    <!-- KPI Metric Cards with % Growth Indicators -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Total Views -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Views</p>
          <span
            v-if="reportData?.growth"
            class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold"
            :class="
              reportData.growth.total_views > 0
                ? 'bg-emerald-50 text-emerald-700'
                : reportData.growth.total_views < 0
                ? 'bg-rose-50 text-rose-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <ArrowUp v-if="reportData.growth.total_views > 0" class="w-3 h-3" />
            <ArrowDown v-else-if="reportData.growth.total_views < 0" class="w-3 h-3" />
            <Minus v-else class="w-3 h-3" />
            <span>{{ Math.abs(reportData.growth.total_views) }}%</span>
          </span>
        </div>
        <div class="mt-3">
          <h3 class="text-2xl font-bold text-slate-900">
            {{ reportData?.summary.total_views.toLocaleString() || 0 }}
          </h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Total impresi halaman dilihat</p>
        </div>
      </div>

      <!-- Unique Visitors -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Unique Visitor</p>
          <span
            v-if="reportData?.growth"
            class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold"
            :class="
              reportData.growth.unique_visitors > 0
                ? 'bg-emerald-50 text-emerald-700'
                : reportData.growth.unique_visitors < 0
                ? 'bg-rose-50 text-rose-700'
                : 'bg-slate-100 text-slate-600'
            "
          >
            <ArrowUp v-if="reportData.growth.unique_visitors > 0" class="w-3 h-3" />
            <ArrowDown v-else-if="reportData.growth.unique_visitors < 0" class="w-3 h-3" />
            <Minus v-else class="w-3 h-3" />
            <span>{{ Math.abs(reportData.growth.unique_visitors) }}%</span>
          </span>
        </div>
        <div class="mt-3">
          <h3 class="text-2xl font-bold text-indigo-600">
            {{ reportData?.summary.unique_visitors.toLocaleString() || 0 }}
          </h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Pengunjung unik (Unique IP)</p>
        </div>
      </div>

      <!-- WhatsApp Clicks & Conversion -->
      <div class="p-5 rounded-2xl bg-emerald-50/50 border border-emerald-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-emerald-800 uppercase tracking-wider">Klik WhatsApp</p>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
            {{ reportData?.summary.conversion_rate || 0 }}% Konversi
          </span>
        </div>
        <div class="mt-3">
          <h3 class="text-2xl font-bold text-emerald-700">
            {{ reportData?.summary.total_wa_clicks || 0 }}
          </h3>
          <p class="text-[11px] text-emerald-600 mt-0.5">Konversi klik ke tombol kontak WA</p>
        </div>
      </div>

      <!-- Bounce Rate -->
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bounce Rate</p>
          <Percent class="w-4 h-4 text-amber-500" />
        </div>
        <div class="mt-3">
          <h3 class="text-2xl font-bold text-amber-600">
            {{ reportData?.summary.bounce_rate || 0 }}%
          </h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Kunjungan langsung keluar (1 halaman)</p>
        </div>
      </div>

    </div>

    <!-- SECTION 2: HEATMAP AKTIVITAS HARI & JAM (PEAK HOURS MATRIX 7x24) -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-6 space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <Flame class="w-5 h-5 text-rose-500" />
            <span>Heatmap Aktivitas Hari & Jam (Peak Hours Matrix)</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Visualisasi jam & hari tersibuk dari kunjungan pengunjung dan konversi klik WhatsApp.
          </p>
        </div>

        <!-- Mode Toggle -->
        <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 text-xs font-semibold">
          <button
            @click="heatmapType = 'traffic'"
            class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
            :class="heatmapType === 'traffic' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            <Users class="w-3.5 h-3.5" />
            <span>Traffic Visitor</span>
          </button>
          <button
            @click="heatmapType = 'wa'"
            class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5"
            :class="heatmapType === 'wa' ? 'bg-white text-emerald-600 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            <MessageCircle class="w-3.5 h-3.5" />
            <span>Klik WhatsApp</span>
          </button>
        </div>
      </div>

      <!-- Matrix Grid 7x24 -->
      <div class="overflow-x-auto pb-2">
        <div class="min-w-[800px] text-xs">
          <!-- Hours Header -->
          <div class="grid grid-cols-[80px_repeat(24,1fr)] gap-1 text-center font-bold text-slate-400 text-[10px] mb-1">
            <div class="text-left pl-1">Hari / Jam</div>
            <div v-for="h in 24" :key="h">{{ (h - 1) < 10 ? '0' + (h - 1) : h - 1 }}</div>
          </div>

          <!-- Days Rows -->
          <div
            v-for="(day, dIdx) in dayNames"
            :key="day"
            class="grid grid-cols-[80px_repeat(24,1fr)] gap-1 items-center mb-1"
          >
            <div class="text-xs font-semibold text-slate-700 pl-1">{{ day }}</div>
            <div
              v-for="(_, hIdx) in 24"
              :key="hIdx"
              class="aspect-square rounded-md flex items-center justify-center font-bold text-[10px] transition-transform hover:scale-125 cursor-pointer shadow-2xs"
              :style="getHeatmapCellStyle(heatmapMatrix.matrix[dIdx][hIdx], heatmapMatrix.maxVal)"
              :title="`${day} jam ${hIdx}:00 - ${heatmapMatrix.matrix[dIdx][hIdx]} ${heatmapType === 'wa' ? 'Klik WA' : 'Visits'}`"
            >
              {{ heatmapMatrix.matrix[dIdx][hIdx] > 0 ? heatmapMatrix.matrix[dIdx][hIdx] : '' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Hourly Bar Chart below Matrix -->
      <div class="pt-4 border-t border-slate-100">
        <div class="flex items-center justify-between text-xs text-slate-500 mb-2">
          <span class="font-bold">Distribusi Akumulasi 24 Jam:</span>
          <span>00:00 s/d 23:00 WIB</span>
        </div>
        <div class="h-32 relative">
          <canvas ref="heatmapBarCanvas"></canvas>
        </div>
      </div>
    </div>

    <!-- SECTION 3: TRAFFIC TRENDS & AI FORECAST 7 HARI -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-6 space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <TrendingUp class="w-5 h-5 text-blue-600" />
            <span>Traffic Trends & AI Forecast 7 Hari</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Analisis tren historis serta proyeksi kecerdasan buatan untuk 7 hari ke depan
          </p>
        </div>
        <span class="px-2.5 py-1 rounded-xl bg-cyan-50 text-cyan-800 font-bold text-xs border border-cyan-200 flex items-center gap-1">
          <Sparkles class="w-3.5 h-3.5 text-cyan-600" />
          <span>AI Forecast</span>
        </span>
      </div>

      <div class="h-72 relative">
        <canvas ref="trafficCanvas"></canvas>
      </div>
    </div>

    <!-- SECTION 4: DETAIL LAPORAN KLIK WHATSAPP (REAL-TIME LOGS TABLE) -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-6 space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <MessageCircle class="w-5 h-5 text-emerald-600" />
            <span>Detail Laporan Klik WhatsApp</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Rincian interaksi setiap tombol WA yang ditekan pengunjung di seluruh artikel & halaman situs.
          </p>
        </div>

        <!-- Search & Filter Controls -->
        <div class="flex items-center gap-2 flex-wrap">
          <div class="relative w-48 sm:w-56">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              v-model="waSearch"
              type="text"
              placeholder="Cari tombol, IP, halaman..."
              class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
          </div>

          <select
            v-model="waDeviceFilter"
            class="px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none font-medium"
          >
            <option value="">Semua Perangkat</option>
            <option value="Mobile">Mobile</option>
            <option value="Desktop">Desktop</option>
          </select>
        </div>
      </div>

      <!-- Logs Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="text-slate-400 text-[10px] uppercase font-bold border-b border-slate-100 pb-2">
              <th class="pb-2 pl-3">No</th>
              <th class="pb-2">Waktu</th>
              <th class="pb-2">Tombol / Artikel / Halaman</th>
              <th class="pb-2">IP Address</th>
              <th class="pb-2">Wilayah</th>
              <th class="pb-2 pr-3 text-right">Perangkat</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!paginatedWaLogs.length">
              <td colspan="6" class="text-center py-8 text-slate-400">
                Tidak ada data log klik WhatsApp ditemukan.
              </td>
            </tr>
            <tr
              v-for="(log, idx) in paginatedWaLogs"
              :key="log.id"
              class="hover:bg-slate-50/70 transition-colors"
            >
              <td class="py-3 pl-3 font-mono font-bold text-slate-400">
                {{ (currentWaPage - 1) * waPageSize + idx + 1 }}
              </td>
              <td class="py-3 whitespace-nowrap text-slate-700">
                <div>{{ new Date(log.clicked_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) }}</div>
                <div class="text-[10px] text-slate-400">{{ new Date(log.clicked_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</div>
              </td>
              <td class="py-3 max-w-xs">
                <div class="font-bold text-emerald-700">{{ log.button_label }}</div>
                <div class="text-[11px] text-slate-400 truncate mt-0.5" :title="log.page_url">
                  {{ log.page_url }}
                </div>
              </td>
              <td class="py-3">
                <span class="font-mono text-[11px] font-bold text-slate-600 px-2 py-0.5 rounded-lg bg-slate-100">
                  {{ log.ip_address }}
                </span>
              </td>
              <td class="py-3 text-slate-600">
                <div class="flex items-center gap-1">
                  <MapPin class="w-3 h-3 text-rose-500 shrink-0" />
                  <span class="truncate">{{ log.location }}</span>
                </div>
              </td>
              <td class="py-3 pr-3 text-right">
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                  :class="log.device === 'Mobile' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-700'"
                >
                  {{ log.device }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
        <span class="text-slate-400">
          Menampilkan {{ paginatedWaLogs.length }} dari {{ filteredWaLogs.length }} log klik
        </span>

        <div v-if="totalWaPages > 1" class="flex items-center gap-1">
          <button
            @click="currentWaPage = Math.max(1, currentWaPage - 1)"
            :disabled="currentWaPage === 1"
            class="p-1.5 rounded-lg border border-slate-200 text-slate-600 disabled:opacity-40"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="px-3 py-1 font-semibold text-slate-700">
            Hal {{ currentWaPage }} / {{ totalWaPages }}
          </span>
          <button
            @click="currentWaPage = Math.min(totalWaPages, currentWaPage + 1)"
            :disabled="currentWaPage === totalWaPages"
            class="p-1.5 rounded-lg border border-slate-200 text-slate-600 disabled:opacity-40"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- SECTION 5: BREAKDOWN GRID (TOP PAGES, TOP CITIES, DEVICES & REFERRER) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      
      <!-- Top Pages -->
      <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-5 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
          <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2">
            <Layers class="w-4 h-4 text-indigo-600" />
            <span>Halaman Terpopuler (Top Pages)</span>
          </h3>
          <span class="text-[10px] text-slate-400 font-semibold">Views</span>
        </div>

        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
          <div v-if="!reportData?.top_pages?.length" class="text-xs text-slate-400 py-4 text-center">
            Belum ada data halaman.
          </div>
          <div
            v-for="p in reportData?.top_pages"
            :key="p.page_url"
            class="flex items-center justify-between text-xs py-1.5 px-2 rounded-xl bg-slate-50 hover:bg-blue-50/50 transition-colors"
          >
            <a
              :href="p.page_url"
              target="_blank"
              class="truncate text-slate-700 hover:text-blue-600 font-medium max-w-[210px]"
              :title="p.page_url"
            >
              {{ p.page_url }}
            </a>
            <span class="font-bold text-slate-900 font-mono">{{ p.views }}</span>
          </div>
        </div>
      </div>

      <!-- Top Cities -->
      <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-5 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
          <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2">
            <MapPin class="w-4 h-4 text-rose-500" />
            <span>Kota Pengunjung (Top Cities)</span>
          </h3>
          <span class="text-[10px] text-slate-400 font-semibold">Visitors</span>
        </div>

        <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
          <div v-if="!reportData?.top_cities?.length" class="text-xs text-slate-400 py-4 text-center">
            Belum ada data kota tercatat.
          </div>
          <div
            v-for="c in reportData?.top_cities"
            :key="c.city"
            class="flex items-center justify-between text-xs py-1.5 px-2 rounded-xl bg-slate-50 hover:bg-rose-50/50 transition-colors"
          >
            <span class="font-medium text-slate-800 truncate max-w-[210px]">{{ c.city }}</span>
            <span class="font-bold text-slate-900 font-mono">{{ c.visitors }}</span>
          </div>
        </div>
      </div>

      <!-- Device & Source Chart -->
      <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-5 space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
          <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2">
            <Monitor class="w-4 h-4 text-emerald-600" />
            <span>Perangkat & Sumber Trafik</span>
          </h3>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase text-center mb-1">Perangkat</p>
            <div class="h-28 relative">
              <canvas ref="deviceCanvas"></canvas>
            </div>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase text-center mb-1">Sumber Referrer</p>
            <div class="h-28 relative">
              <canvas ref="sourceCanvas"></canvas>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- MODAL TELEGRAM REPORT CONFIG & INSTANT TRIGGER -->
    <div
      v-if="showTgModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-md bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto animate-scaleUp">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Send class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">Laporan Otomatis Telegram</h3>
              <p class="text-[11px] text-slate-400 line-clamp-1">Jadwal pengiriman rekap harian ke bot Telegram</p>
            </div>
          </div>
          <button @click="showTgModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Schedule Time -->
        <div class="p-3.5 sm:p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">
          <label class="block text-xs font-bold text-slate-700 flex items-center gap-1.5">
            <Clock class="w-4 h-4 text-blue-600" />
            <span>Waktu Pengiriman Harian (WIB)</span>
          </label>
          <input
            v-model="tgTime"
            type="time"
            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
          />
        </div>

        <!-- Report Content Checkboxes -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-slate-700">Konten Laporan Harian</label>
          <div class="space-y-2">
            <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition-colors">
              <input
                type="checkbox"
                value="leads"
                v-model="tgConfig"
                class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 shrink-0"
              />
              <span class="text-xs font-semibold text-slate-800">Leads & Pesan (Pesan masuk & genuine leads)</span>
            </label>

            <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition-colors">
              <input
                type="checkbox"
                value="traffic"
                v-model="tgConfig"
                class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 shrink-0"
              />
              <span class="text-xs font-semibold text-slate-800">Traffic Stats (Total visitor & tren harian)</span>
            </label>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3">
          <!-- Instant Trigger Button -->
          <button
            type="button"
            @click="triggerTelegramNow"
            :disabled="sendingTg"
            class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-bold transition-all flex items-center justify-center gap-1.5 order-last sm:order-first"
          >
            <Send class="w-3.5 h-3.5" :class="sendingTg ? 'animate-spin' : ''" />
            <span>Kirim Sekarang</span>
          </button>

          <div class="flex flex-col-reverse sm:flex-row items-center gap-2 w-full sm:w-auto">
            <button
              type="button"
              @click="showTgModal = false"
              class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-slate-500 hover:bg-slate-100 border border-slate-200 sm:border-transparent text-xs font-semibold text-center"
            >
              Batal
            </button>
            <button
              type="button"
              @click="saveTelegramSettings"
              :disabled="savingTg"
              class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition-all text-center"
            >
              Simpan
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
