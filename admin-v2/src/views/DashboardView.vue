<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import axios from 'axios'
import { Chart, registerables } from 'chart.js'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import {
  Users,
  Eye,
  MessageCircle,
  FileText,
  Sparkles,
  Bot,
  TrendingUp,
  Clock,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  ExternalLink,
  Award,
  Trophy,
  ChevronDown,
  ChevronRight,
  Activity,
  Server,
  Smartphone,
  Monitor,
  Globe,
  Wrench,
  Cpu,
  ShoppingBag,
  Flame,
  PieChart as PieChartIcon,
  Calendar,
  Layers
} from 'lucide-vue-next'

Chart.register(...registerables)

// Range state
const currentRange = ref<'today' | '7days' | '30days' | 'month'>('7days')
const rangeLabels = {
  today: 'Hari Ini',
  '7days': '7 Hari Terakhir',
  '30days': '30 Hari Terakhir',
  month: 'Bulan Ini'
}

const isLoading = ref(true)

// Stats
const stats = ref({
  human_total: 0,
  bot_total: 0,
  range_human: 0,
  today_human: 0,
  genuine_leads: 0,
  ai_queue_count: 0,
  total_visitors: 0,
  total_leads: 0,
  total_wa_clicks: 0,
  total_articles: 0,
  total_services: 0
})

// SEO & AI State
interface SeoTier {
  tier: number
  label: string
  color: string
  bg: string
  count: number
  articles: Array<{ id: number; title: string; seo_score: number }>
}

const seo = ref({
  avg_score: 0,
  total_articles: 0,
  good_count: 0,
  ai_message: '',
  ai_status: 'success',
  tiers: [] as SeoTier[],
  problematic: [] as Array<{ id: number; title: string; seo_score: number }>
})

const expandedTier = ref<number | null>(null)
const showProblematic = ref(false)
const aiTypingText = ref('')
let typingTimer: any = null

function runTypewriter(fullText: string) {
  if (typingTimer) clearInterval(typingTimer)
  aiTypingText.value = ''
  let i = 0
  typingTimer = setInterval(() => {
    if (i < fullText.length) {
      aiTypingText.value += fullText.charAt(i)
      i++
    } else {
      clearInterval(typingTimer)
      typingTimer = null
    }
  }, 22)
}

// Server Health
const serverHealth = ref({
  php_version: '7.4',
  db_size_mb: 0,
  disk_usage_percent: 0,
  status: 'operational'
})

// Recent items
const recentVisitors = ref<any[]>([])
const recentLeads = ref<any[]>([])
const recentActivities = ref<any[]>([])

// Chart DOM refs & instances
const trafficCanvas = ref<HTMLCanvasElement | null>(null)
const articleCanvas = ref<HTMLCanvasElement | null>(null)
const readerCanvas = ref<HTMLCanvasElement | null>(null)
const deviceCanvas = ref<HTMLCanvasElement | null>(null)
const serviceCanvas = ref<HTMLCanvasElement | null>(null)

let trafficChartInstance: Chart | null = null
let articleChartInstance: Chart | null = null
let readerChartInstance: Chart | null = null
let deviceChartInstance: Chart | null = null
let serviceChartInstance: Chart | null = null

// Leaflet Map state
const mapContainer = ref<HTMLDivElement | null>(null)
const mapFilter = ref<'all' | 'human' | 'bot'>('all')
let mapInstance: L.Map | null = null
let markerLayerGroup: L.LayerGroup | null = null

async function fetchDashboardData() {
  isLoading.value = true
  try {
    const res = await axios.get('/api/v2/data.php', {
      params: {
        type: 'dashboard',
        range: currentRange.value
      },
      withCredentials: true
    })

    if (res.data?.status === 'success' && res.data?.data) {
      const d = res.data.data
      stats.value = { ...stats.value, ...d.stats }
      serverHealth.value = { ...serverHealth.value, ...d.server_health }
      recentVisitors.value = d.recent_visitors || []
      recentLeads.value = d.recent_leads || []
      recentActivities.value = d.recent_activities || []

      // SEO
      if (d.seo) {
        seo.value = {
          avg_score: d.seo.avg_score || 0,
          total_articles: d.seo.total_articles || 0,
          good_count: d.seo.good_count || 0,
          ai_message: d.seo.ai_message || '',
          ai_status: d.seo.ai_status || 'success',
          tiers: d.seo.tiers || [],
          problematic: d.seo.problematic || []
        }
        runTypewriter(d.seo.ai_message || 'Data SEO berhasil dimuat.')
      }

      await nextTick()
      renderCharts(d)
      loadMapData()
    }
  } catch (err) {
    console.error('Error fetching dashboard data:', err)
  } finally {
    isLoading.value = false
  }
}

function renderCharts(d: any) {
  // Common chart styling
  const fontFamily = "'Plus Jakarta Sans', 'Inter', system-ui, sans-serif"

  // 1. Traffic Chart (Line Area - Human vs Bot)
  if (trafficCanvas.value) {
    if (trafficChartInstance) trafficChartInstance.destroy()
    const labels = d.trend?.labels || []
    const humanData = d.trend?.human || []
    const botData = d.trend?.bot || []

    trafficChartInstance = new Chart(trafficCanvas.value, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Pengunjung Manusia (Human)',
            data: humanData,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.08)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.35,
            pointRadius: labels.length > 15 ? 0 : 3,
            pointHoverRadius: 6,
            pointBackgroundColor: '#2563eb'
          },
          {
            label: 'Trafik Bot / Crawler',
            data: botData,
            borderColor: '#94a3b8',
            backgroundColor: 'transparent',
            borderWidth: 1.8,
            borderDash: [5, 5],
            fill: false,
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: '#94a3b8'
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
          },
          tooltip: {
            padding: 10,
            cornerRadius: 10,
            titleFont: { family: fontFamily, weight: 'bold' }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { family: fontFamily, size: 11 }, color: '#64748b' }
          },
          y: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: { font: { family: fontFamily, size: 11 }, color: '#64748b', precision: 0 }
          }
        }
      }
    })
  }

  // 2. Popular Articles (Horizontal Bar)
  if (articleCanvas.value) {
    if (articleChartInstance) articleChartInstance.destroy()
    const pop = d.popular_articles || []
    const popLabels = pop.map((p: any) => p.title.length > 22 ? p.title.substring(0, 22) + '...' : p.title)
    const popData = pop.map((p: any) => p.views)

    articleChartInstance = new Chart(articleCanvas.value, {
      type: 'bar',
      data: {
        labels: popLabels,
        datasets: [
          {
            label: 'Tayangan (Views)',
            data: popData,
            backgroundColor: '#10b981',
            borderRadius: 6,
            barThickness: 12
          }
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: '#f1f5f9' },
            ticks: { font: { family: fontFamily, size: 10 }, precision: 0 }
          },
          y: {
            grid: { display: false },
            ticks: { font: { family: fontFamily, size: 10 }, color: '#334155' }
          }
        }
      }
    })
  }

  // 3. Reader Trend (Line Sparkline)
  if (readerCanvas.value) {
    if (readerChartInstance) readerChartInstance.destroy()
    const rtLabels = d.reader_trend?.labels || []
    const rtData = d.reader_trend?.data || []

    readerChartInstance = new Chart(readerCanvas.value, {
      type: 'line',
      data: {
        labels: rtLabels,
        datasets: [
          {
            label: 'Pembaca Harian',
            data: rtData,
            borderColor: '#06b6d4',
            backgroundColor: 'rgba(6, 182, 212, 0.12)',
            borderWidth: 2,
            fill: true,
            tension: 0.35,
            pointRadius: 2,
            pointBackgroundColor: '#06b6d4'
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
            grid: { color: '#f8fafc' },
            ticks: { font: { family: fontFamily, size: 9 }, precision: 0 }
          }
        }
      }
    })
  }

  // 4. Device Usage (Doughnut)
  if (deviceCanvas.value) {
    if (deviceChartInstance) deviceChartInstance.destroy()
    const devices = d.devices || []
    const devLabels = devices.map((dv: any) => dv.device)
    const devData = devices.map((dv: any) => dv.count)

    deviceChartInstance = new Chart(deviceCanvas.value, {
      type: 'doughnut',
      data: {
        labels: devLabels.length ? devLabels : ['Desktop', 'Mobile'],
        datasets: [
          {
            data: devData.length ? devData : [1, 1],
            backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#64748b'],
            borderWidth: 0,
            hoverOffset: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              boxWidth: 8,
              usePointStyle: true,
              font: { family: fontFamily, size: 10, weight: 600 },
              padding: 12
            }
          }
        }
      }
    })
  }

  // 5. Favorite Services (Bar)
  if (serviceCanvas.value) {
    if (serviceChartInstance) serviceChartInstance.destroy()
    const fav = d.favorite_services || []
    const favLabels = fav.map((s: any) => s.title.length > 18 ? s.title.substring(0, 18) + '...' : s.title)
    const favData = fav.map((s: any) => s.orders)

    serviceChartInstance = new Chart(serviceCanvas.value, {
      type: 'bar',
      data: {
        labels: favLabels,
        datasets: [
          {
            label: 'Pesanan Genuine',
            data: favData,
            backgroundColor: '#3b82f6',
            borderRadius: 6,
            barThickness: 16
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
            ticks: { font: { family: fontFamily, size: 10 }, color: '#334155' }
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
}

// Leaflet Map Initialization
function initMap() {
  if (!mapContainer.value) return
  if (mapInstance) return

  // Center on Indonesia archipelago
  mapInstance = L.map(mapContainer.value, {
    center: [-1.8, 118.0],
    zoom: 5,
    scrollWheelZoom: false
  })

  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
    subdomains: 'abcd',
    maxZoom: 19
  }).addTo(mapInstance)

  markerLayerGroup = L.layerGroup().addTo(mapInstance)
}

async function loadMapData() {
  if (!mapInstance || !markerLayerGroup) {
    initMap()
  }
  if (!markerLayerGroup) return

  markerLayerGroup.clearLayers()

  try {
    const res = await axios.get('/api/v2/data.php', {
      params: {
        type: 'dashboard_map',
        range: currentRange.value,
        filter: mapFilter.value
      },
      withCredentials: true
    })

    if (res.data?.status === 'success' && Array.isArray(res.data?.data)) {
      const points = res.data.data
      points.forEach((pt: any) => {
        const isBot = Number(pt.is_bot) === 1
        const color = isBot ? '#64748b' : '#2563eb'
        const fillColor = isBot ? '#cbd5e1' : '#93c5fd'

        const marker = L.circleMarker([pt.lat, pt.lng], {
          radius: 6,
          fillColor,
          color,
          weight: 1.5,
          opacity: 0.9,
          fillOpacity: 0.75
        })

        const badgeHtml = isBot
          ? `<span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;">BOT / CRAWLER</span>`
          : `<span style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;">HUMAN VISITOR</span>`

        const popupHtml = `
          <div style="font-family:sans-serif;text-align:center;padding:4px 6px;">
            <div style="margin-bottom:6px;">${badgeHtml}</div>
            <div style="font-weight:700;color:#0f172a;font-size:12px;">${pt.city || 'Unknown'}, ${pt.country || ''}</div>
            <div style="font-size:11px;color:#64748b;margin-top:2px;">${pt.device || 'Perangkat'} &bull; ${pt.visited_at || ''}</div>
          </div>
        `
        marker.bindPopup(popupHtml)
        markerLayerGroup?.addLayer(marker)
      })
    }
  } catch (err) {
    console.error('Failed to load map data:', err)
  }
}

watch(mapFilter, () => {
  loadMapData()
})

onMounted(() => {
  initMap()
  fetchDashboardData()
})

onBeforeUnmount(() => {
  if (typingTimer) clearInterval(typingTimer)
  if (trafficChartInstance) trafficChartInstance.destroy()
  if (articleChartInstance) articleChartInstance.destroy()
  if (readerChartInstance) readerChartInstance.destroy()
  if (deviceChartInstance) deviceChartInstance.destroy()
  if (serviceChartInstance) serviceChartInstance.destroy()
  if (mapInstance) {
    mapInstance.remove()
    mapInstance = null
  }
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn pb-12">
    <!-- Top Action Toolbar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <span>Dashboard Overview</span>
          <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
            Realtime
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Pantau seluruh metrik trafik, kecerdasan SEO, prospek leads, serta kesehatan sistem.
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <!-- Range Filter Buttons -->
        <div class="inline-flex items-center p-1 rounded-xl bg-white border border-slate-200 shadow-sm text-xs font-semibold">
          <button
            v-for="(label, key) in rangeLabels"
            :key="key"
            @click="() => { currentRange = key as any; fetchDashboardData(); }"
            class="px-3 py-1.5 rounded-lg transition-all"
            :class="currentRange === key ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          >
            {{ label }}
          </button>
        </div>

        <button
          @click="fetchDashboardData"
          :disabled="isLoading"
          class="p-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm transition-all"
          title="Segarkan Data"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="isLoading ? 'animate-spin' : ''" />
        </button>

        <router-link
          to="/auto-content"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-sm transition-all"
        >
          <Bot class="w-3.5 h-3.5" />
          <span>Auto Content AI</span>
        </router-link>
      </div>
    </div>

    <!-- SECTION 1: GLOBAL SEO CERTIFICATE & RANKING + AI INSIGHT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- Left: SEO Score Gauge & Certificate (4 Cols) -->
      <div
        class="lg:col-span-4 rounded-3xl p-6 border transition-all duration-300 relative overflow-hidden flex flex-col justify-between"
        :class="
          seo.avg_score >= 80
            ? 'bg-gradient-to-br from-amber-50/70 via-white to-amber-50/40 border-amber-300/80 shadow-md shadow-amber-500/5'
            : 'bg-white border-slate-100 shadow-subtle'
        "
      >
        <!-- Gold Ribbon background if Score >= 80 -->
        <div v-if="seo.avg_score >= 80" class="absolute -top-3 -right-3 opacity-15 pointer-events-none">
          <Award class="w-32 h-32 text-amber-500" />
        </div>

        <div>
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider" :class="seo.avg_score >= 80 ? 'text-amber-800' : 'text-slate-500'">
              {{ seo.avg_score >= 80 ? 'Certificate of Excellence' : 'Rata-rata Skor SEO Global' }}
            </span>
            <span
              v-if="seo.avg_score >= 80"
              class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1"
            >
              <Trophy class="w-3 h-3 text-amber-600" />
              <span>Gold Tier</span>
            </span>
          </div>

          <!-- Circular Gauge SVG -->
          <div class="flex flex-col items-center justify-center my-6">
            <div class="relative w-36 h-36 flex items-center justify-center">
              <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                <path
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  fill="none"
                  stroke="#f1f5f9"
                  stroke-width="3"
                />
                <path
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  fill="none"
                  :stroke="seo.avg_score >= 80 ? '#10b981' : seo.avg_score >= 60 ? '#f59e0b' : '#ef4444'"
                  stroke-width="3"
                  stroke-linecap="round"
                  :stroke-dasharray="`${seo.avg_score}, 100`"
                  class="transition-all duration-1000 ease-out"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-4xl font-black text-slate-900 tracking-tight">{{ seo.avg_score }}</span>
                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mt-0.5">Skor / 100</span>
              </div>
            </div>
          </div>
        </div>

        <div class="text-center pt-3 border-t border-slate-100 text-xs text-slate-500">
          Dievaluasi dari <strong class="text-slate-800 font-bold">{{ seo.total_articles }}</strong> artikel publikasi aktif.
        </div>
      </div>

      <!-- Middle & Right: Global SEO Ranking (5 Cols) & AI Assistant Insight (3 Cols) -->
      <div class="lg:col-span-5 rounded-3xl bg-white border border-slate-100 shadow-subtle p-5 flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <Trophy class="w-4 h-4 text-amber-500" />
              <h2 class="text-sm font-bold text-slate-800">Global SEO Ranking (10 Tier)</h2>
            </div>
            <span class="text-[11px] font-semibold text-slate-500 bg-slate-50 px-2.5 py-0.5 rounded-full border border-slate-200">
              Total {{ seo.total_articles }} Artikel
            </span>
          </div>

          <!-- Accordion of Tiers -->
          <div class="divide-y divide-slate-100 mt-2 max-h-[340px] overflow-y-auto pr-1">
            <div
              v-for="t in seo.tiers"
              :key="t.tier"
              class="py-2"
            >
              <button
                @click="expandedTier = expandedTier === t.tier ? null : t.tier"
                class="w-full flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 transition-colors text-left"
              >
                <div class="flex items-center gap-2.5">
                  <span
                    class="w-8 h-6 rounded-lg text-white font-bold text-xs flex items-center justify-center shadow-xs"
                    :style="{ backgroundColor: t.color }"
                  >
                    {{ t.tier }}
                  </span>
                  <span class="text-xs font-bold" :style="{ color: t.color }">{{ t.label }}</span>
                </div>

                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700">
                    {{ t.count }} artikel
                  </span>
                  <ChevronDown
                    class="w-3.5 h-3.5 text-slate-400 transition-transform"
                    :class="expandedTier === t.tier ? 'rotate-180' : ''"
                  />
                </div>
              </button>

              <!-- Tier Articles List (Dropdown) -->
              <div v-if="expandedTier === t.tier" class="pl-10 pr-2 pt-2 space-y-1.5 animate-fadeIn">
                <div v-if="!t.articles.length" class="text-[11px] text-slate-400 py-1">
                  Tidak ada artikel di tingkat peringkat ini.
                </div>
                <div
                  v-for="art in t.articles"
                  :key="art.id"
                  class="flex items-center justify-between text-xs py-1 px-2 rounded-lg bg-slate-50 hover:bg-blue-50/60"
                >
                  <router-link :to="`/articles?edit=${art.id}`" class="truncate text-slate-700 hover:text-blue-600 font-medium max-w-[240px]">
                    {{ art.title }}
                  </router-link>
                  <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-white border border-slate-200">
                    {{ art.seo_score }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: AI Assistant Typewriter Insight (3 Cols) -->
      <div class="lg:col-span-3 rounded-3xl bg-white border border-slate-100 shadow-subtle p-5 flex flex-col justify-between">
        <div class="space-y-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
              <Bot class="w-4 h-4" />
            </div>
            <div>
              <h3 class="text-xs font-bold text-slate-800">AI Assistant SEO</h3>
              <p class="text-[10px] text-slate-400">Insight untuk Arno</p>
            </div>
          </div>

          <!-- Typewriter message box -->
          <div class="p-3.5 rounded-2xl bg-gradient-to-br from-blue-50/70 to-indigo-50/40 border border-blue-100/70 text-xs text-slate-700 leading-relaxed min-h-[110px] relative">
            <div v-if="isLoading" class="flex items-center gap-2 text-slate-400 text-xs py-4 justify-center">
              <RefreshCw class="w-3.5 h-3.5 animate-spin text-blue-600" />
              <span>Menganalisis performa SEO...</span>
            </div>
            <div v-else>
              <span>{{ aiTypingText }}</span>
              <span class="inline-block w-1.5 h-3.5 bg-blue-600 ml-0.5 animate-pulse"></span>
            </div>
          </div>

          <!-- Problematic Articles Accordion if any (< 80) -->
          <div v-if="seo.problematic.length > 0" class="pt-2 border-t border-slate-100">
            <button
              @click="showProblematic = !showProblematic"
              class="w-full flex items-center justify-between text-xs font-bold text-rose-600 py-1.5 px-2 rounded-xl bg-rose-50/60 hover:bg-rose-50 transition-colors"
            >
              <div class="flex items-center gap-1.5">
                <Wrench class="w-3.5 h-3.5 text-rose-500" />
                <span>Perlu Perbaikan ({{ seo.problematic.length }})</span>
              </div>
              <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="showProblematic ? 'rotate-180' : ''" />
            </button>

            <div v-if="showProblematic" class="mt-2 space-y-1.5 max-h-[150px] overflow-y-auto pr-1 animate-fadeIn">
              <div
                v-for="prob in seo.problematic"
                :key="prob.id"
                class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-xs space-y-1"
              >
                <div class="flex items-center justify-between gap-2">
                  <router-link :to="`/articles?edit=${prob.id}`" class="truncate font-semibold text-slate-800 hover:text-blue-600 text-[11px]">
                    {{ prob.title }}
                  </router-link>
                  <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-100 text-rose-700 shrink-0">
                    {{ prob.seo_score }}
                  </span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                  <div class="bg-rose-500 h-1.5 rounded-full" :style="{ width: `${prob.seo_score}%` }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-3 text-[11px] text-slate-400 border-t border-slate-100 flex items-center justify-between">
          <span>{{ seo.good_count }} artikel $\ge$ 80</span>
          <span class="text-emerald-600 font-semibold">{{ Math.round((seo.good_count / Math.max(1, seo.total_articles)) * 100) }}% Optimal</span>
        </div>
      </div>

    </div>

    <!-- SECTION 2: 6 STAT METRIC CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
      
      <!-- Card 1: Human Visitors -->
      <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold text-slate-500">Human Visitors</span>
          <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <Users class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xl font-bold text-slate-900">{{ stats.human_total.toLocaleString() }}</div>
          <span class="text-[10px] text-slate-400 font-medium">Pengunjung Asli</span>
        </div>
      </div>

      <!-- Card 2: Bot Traffic -->
      <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold text-slate-500">Bot Traffic</span>
          <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center">
            <Bot class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xl font-bold text-slate-600">{{ stats.bot_total.toLocaleString() }}</div>
          <span class="text-[10px] text-slate-400 font-medium">Crawler & Robot</span>
        </div>
      </div>

      <!-- Card 3: Active Range Human -->
      <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold text-slate-500">{{ rangeLabels[currentRange] }}</span>
          <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
            <PieChartIcon class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xl font-bold text-slate-900">{{ stats.range_human.toLocaleString() }}</div>
          <span class="text-[10px] text-amber-600 font-medium">Trafik Manusia Rentang</span>
        </div>
      </div>

      <!-- Card 4: Hari Ini Asli -->
      <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold text-slate-500">Hari Ini (Asli)</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <Calendar class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xl font-bold text-emerald-600">{{ stats.today_human.toLocaleString() }}</div>
          <span class="text-[10px] text-emerald-700 font-semibold">Trafik Hari Ini</span>
        </div>
      </div>

      <!-- Card 5: Total Genuine Orders -->
      <div class="p-4 rounded-2xl bg-white border border-slate-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold text-slate-500">Total Pesanan</span>
          <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
            <ShoppingBag class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xl font-bold text-slate-900">{{ stats.genuine_leads.toLocaleString() }}</div>
          <span class="text-[10px] text-blue-600 font-medium">Lolos Verifikasi AI</span>
        </div>
      </div>

      <!-- Card 6: AI Queue -->
      <div class="p-4 rounded-2xl bg-purple-50/60 border border-purple-100 shadow-subtle flex flex-col justify-between">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold text-purple-700">Antrean AI</span>
          <div class="w-8 h-8 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
            <Cpu class="w-4 h-4" />
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xl font-bold text-purple-900">{{ stats.ai_queue_count.toLocaleString() }}</div>
          <span class="text-[10px] text-purple-600 font-medium">{{ stats.total_articles }} tayang</span>
        </div>
      </div>

    </div>

    <!-- SECTION 3: CHARTS & HEALTH (TRAFFIC OVERVIEW, POPULAR ARTICLES, DEVICES, SERVER) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- Left (8 Cols): Traffic Chart + Nested (Popular Articles & Reader Trend) -->
      <div class="lg:col-span-8 rounded-3xl bg-white border border-slate-100 shadow-subtle p-6 space-y-6">
        <div>
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-base font-bold text-slate-900">Traffic Overview</h2>
              <p class="text-xs text-slate-400 mt-0.5">
                {{ currentRange === 'today' ? 'Aktivitas 24 Jam Terakhir' : 'Trend Kunjungan Pengunjung' }} (Human vs Bot)
              </p>
            </div>
            <span class="px-2.5 py-1 rounded-xl bg-blue-50 text-blue-700 font-bold text-xs border border-blue-100">
              {{ rangeLabels[currentRange] }}
            </span>
          </div>

          <div class="h-64 mt-4 relative">
            <canvas ref="trafficCanvas"></canvas>
          </div>
        </div>

        <!-- Nested Row: Popular Articles & Reader Trend -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t border-slate-100">
          <div>
            <div class="flex items-center gap-2 mb-3">
              <Flame class="w-4 h-4 text-amber-500" />
              <h3 class="text-xs font-bold text-slate-800">Artikel Terpopuler (Top 5)</h3>
            </div>
            <div class="h-44 relative">
              <canvas ref="articleCanvas"></canvas>
            </div>
          </div>

          <div>
            <div class="flex items-center gap-2 mb-3">
              <TrendingUp class="w-4 h-4 text-cyan-500" />
              <h3 class="text-xs font-bold text-slate-800">Tren Pembaca Blog</h3>
            </div>
            <div class="h-44 relative">
              <canvas ref="readerCanvas"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Right (4 Cols): Device Usage, Favorite Services, Server Health -->
      <div class="lg:col-span-4 space-y-6">
        
        <!-- Device Usage Card -->
        <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-slate-800">Device Usage</h3>
            <span class="text-[10px] text-slate-400">Distribusi Perangkat</span>
          </div>
          <div class="h-44 relative">
            <canvas ref="deviceCanvas"></canvas>
          </div>
        </div>

        <!-- Popular Services Card -->
        <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold text-slate-800">Layanan Favorit</h3>
            <span class="text-[10px] text-blue-600 font-semibold">Genuine Leads</span>
          </div>
          <div class="h-36 relative">
            <canvas ref="serviceCanvas"></canvas>
          </div>
        </div>

        <!-- Server Health Card -->
        <div class="rounded-3xl bg-slate-900 text-white p-5 space-y-4 shadow-elevated">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></div>
              <div>
                <p class="text-xs font-bold leading-none">Server Status</p>
                <p class="text-[10px] text-emerald-400 mt-0.5">Systems Operational</p>
              </div>
            </div>
            <Server class="w-4 h-4 text-slate-400" />
          </div>

          <div>
            <div class="flex justify-between text-xs mb-1.5">
              <span class="text-slate-400">Disk Usage</span>
              <span class="font-bold font-mono">{{ serverHealth.disk_usage_percent }}%</span>
            </div>
            <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
              <div
                class="bg-emerald-400 h-1.5 rounded-full"
                :style="{ width: `${serverHealth.disk_usage_percent}%` }"
              ></div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-center pt-1 text-xs">
            <div class="p-2 rounded-xl bg-white/5 border border-white/5">
              <p class="text-[10px] text-slate-400 mb-0.5">PHP Ver</p>
              <p class="font-bold font-mono">{{ serverHealth.php_version }}</p>
            </div>
            <div class="p-2 rounded-xl bg-white/5 border border-white/5">
              <p class="text-[10px] text-slate-400 mb-0.5">DB Size</p>
              <p class="font-bold font-mono">{{ serverHealth.db_size_mb }} MB</p>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- SECTION 4: RECENT VISITORS TABLE -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle p-6 space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-bold text-slate-900">Pengunjung Terakhir</h2>
          <p class="text-xs text-slate-400 mt-0.5">5 log aktivitas kunjungan terbaru dari tabel analitik</p>
        </div>
        <router-link to="/reporting" class="text-xs font-semibold text-blue-600 hover:underline">
          Lihat Analitik Selengkapnya &rarr;
        </router-link>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="text-slate-400 text-[10px] uppercase font-bold border-b border-slate-100 pb-2">
              <th class="pb-2 pl-3">Waktu</th>
              <th class="pb-2">IP Address</th>
              <th class="pb-2">Lokasi</th>
              <th class="pb-2">Perangkat</th>
              <th class="pb-2 pr-3 text-right">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!recentVisitors.length">
              <td colspan="5" class="text-center py-6 text-slate-400">Belum ada data pengunjung tercatat.</td>
            </tr>
            <tr
              v-for="v in recentVisitors"
              :key="v.id"
              class="hover:bg-slate-50/70 transition-colors"
            >
              <td class="py-3 pl-3 font-medium text-slate-800">
                <div>{{ new Date(v.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) }}</div>
                <div class="text-[10px] text-slate-400">{{ new Date(v.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</div>
              </td>
              <td class="py-3">
                <span class="font-mono text-[11px] font-bold text-slate-600 px-2 py-0.5 rounded-lg bg-slate-100">
                  {{ v.ip_address }}
                </span>
              </td>
              <td class="py-3 text-slate-700">
                <span class="mr-1">{{ v.country === 'Indonesia' ? '🇮🇩' : '🌍' }}</span>
                <span class="font-medium">{{ v.city || 'Unknown' }}</span>
                <span class="text-slate-400 text-[10px] ml-1">{{ v.country }}</span>
              </td>
              <td class="py-3">
                <div class="flex items-center gap-1.5 text-slate-600 font-medium">
                  <Smartphone v-if="v.device === 'Mobile'" class="w-3.5 h-3.5 text-blue-500" />
                  <Monitor v-else class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ v.device || 'Desktop' }}</span>
                </div>
              </td>
              <td class="py-3 pr-3 text-right">
                <span
                  class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                  :class="
                    Number(v.is_bot) === 1
                      ? 'bg-slate-100 text-slate-600'
                      : 'bg-blue-50 text-blue-700'
                  "
                >
                  {{ Number(v.is_bot) === 1 ? 'Bot' : 'Human' }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SECTION 5: INTERACTIVE LEAFLET VISITOR MAP -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle overflow-hidden">
      <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <Globe class="w-4 h-4 text-blue-600" />
            <span>Peta Persebaran Pengunjung</span>
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">
            Visualisasi titik lokasi pengunjung berdasarkan IP Address
          </p>
        </div>

        <!-- Filter Pill Group -->
        <div class="inline-flex items-center p-1 rounded-xl bg-slate-100 text-xs font-semibold">
          <button
            @click="mapFilter = 'all'"
            class="px-3 py-1 rounded-lg transition-all"
            :class="mapFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            Semua
          </button>
          <button
            @click="mapFilter = 'human'"
            class="px-3 py-1 rounded-lg transition-all"
            :class="mapFilter === 'human' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            Human
          </button>
          <button
            @click="mapFilter = 'bot'"
            class="px-3 py-1 rounded-lg transition-all"
            :class="mapFilter === 'bot' ? 'bg-white text-slate-700 shadow-xs' : 'text-slate-500 hover:text-slate-800'"
          >
            Bot
          </button>
        </div>
      </div>

      <!-- Map Container -->
      <div class="relative w-full h-96 bg-slate-50">
        <div ref="mapContainer" class="w-full h-full z-10"></div>
        <div class="absolute top-3 right-3 z-20 px-3 py-1.5 rounded-xl bg-white/95 backdrop-blur-md border border-slate-200 shadow-sm text-[11px] font-medium text-slate-600">
          Jangkauan: <strong class="text-slate-900 font-bold">{{ rangeLabels[currentRange] }}</strong>
        </div>
      </div>
    </div>

    <!-- SECTION 6: RECENT LEADS & AUDIT LOGS (CRM & Audit) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Recent Leads Box -->
      <div class="p-6 rounded-3xl bg-white border border-slate-100 shadow-subtle space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <Users class="w-4 h-4 text-blue-600" />
            <h2 class="text-sm font-bold text-slate-800">Prospek / Leads Terbaru</h2>
          </div>
          <router-link to="/leads" class="text-xs font-semibold text-blue-600 hover:underline">
            Lihat Semua &rarr;
          </router-link>
        </div>

        <div v-if="!recentLeads.length" class="py-8 text-center text-xs text-slate-400">
          Belum ada data leads baru.
        </div>

        <div v-else class="divide-y divide-slate-100">
          <div
            v-for="lead in recentLeads"
            :key="lead.id"
            class="py-3 flex items-center justify-between gap-4"
          >
            <div class="min-w-0">
              <p class="text-xs font-bold text-slate-800 truncate">{{ lead.name || 'Pengunjung WA' }}</p>
              <p class="text-[11px] text-slate-500 truncate">
                {{ lead.phone || 'Nomor tidak tersedia' }} &bull; {{ lead.service_interested || 'Layanan Umum' }}
              </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-50 text-blue-700">
                {{ lead.status || 'Baru' }}
              </span>
              <a
                v-if="lead.phone"
                :href="`https://wa.me/${lead.phone.replace(/[^0-9]/g, '')}`"
                target="_blank"
                class="p-1 rounded-lg text-emerald-600 hover:bg-emerald-50 transition-colors"
                title="Chat WhatsApp"
              >
                <ExternalLink class="w-3.5 h-3.5" />
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Audit Activity Log -->
      <div class="p-6 rounded-3xl bg-white border border-slate-100 shadow-subtle space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <Clock class="w-4 h-4 text-blue-600" />
            <h2 class="text-sm font-bold text-slate-800">Aktivitas Terkini (Audit Log)</h2>
          </div>
          <router-link to="/activity" class="text-xs font-semibold text-blue-600 hover:underline">
            Lihat Semua &rarr;
          </router-link>
        </div>

        <div v-if="!recentActivities.length" class="py-8 text-center text-xs text-slate-400">
          Belum ada riwayat audit log.
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="act in recentActivities"
            :key="act.id"
            class="p-3 rounded-2xl bg-slate-50/70 border border-slate-100 flex items-start justify-between gap-3 text-xs"
          >
            <div>
              <p class="font-bold text-slate-800">{{ act.action }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{{ act.details }}</p>
            </div>
            <span class="text-[10px] text-slate-400 shrink-0 font-mono">
              {{ act.created_at ? new Date(act.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '' }}
            </span>
          </div>
        </div>
      </div>

    </div>

  </div>
</template>
