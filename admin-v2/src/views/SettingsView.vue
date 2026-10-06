<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { apiClient as axios } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  Layout,
  PhoneCall,
  MessageCircle,
  Send,
  Sparkles,
  Save,
  Upload,
  Image as ImageIcon,
  CheckCircle2,
  AlertTriangle,
  X,
  RefreshCw,
  Info,
  Shield,
  Eye,
  EyeOff,
  Trash2,
  Plus,
  ArrowUp,
  ArrowDown,
  Cpu,
  Bot,
  Globe,
  Sliders,
  Check,
  Zap,
  Key,
  Layers,
  Camera,
  ChevronRight,
  Sparkle,
  Radio,
  FileCheck,
  Clock,
  ExternalLink
} from 'lucide-vue-next'

type SettingTab = 'hero' | 'identity' | 'whatsapp' | 'ai' | 'strategy' | 'telegram' | 'system'

interface TabItem {
  id: SettingTab
  title: string
  subtitle: string
  icon: any
  badge?: string
  color: string
}

const tabs: TabItem[] = [
  {
    id: 'hero',
    title: 'Tampilan Hero',
    subtitle: 'Banner utama, headline & tombol CTA',
    icon: Layout,
    color: 'text-blue-600 bg-blue-50 border-blue-200'
  },
  {
    id: 'identity',
    title: 'Identitas & Kontak',
    subtitle: 'WhatsApp, email, logo & alamat workshop',
    icon: PhoneCall,
    color: 'text-indigo-600 bg-indigo-50 border-indigo-200'
  },
  {
    id: 'whatsapp',
    title: 'Integrasi WhatsApp',
    subtitle: 'Template chat umum & order pemesanan',
    icon: MessageCircle,
    color: 'text-emerald-600 bg-emerald-50 border-emerald-200'
  },
  {
    id: 'ai',
    title: 'Otak AI (Engine)',
    subtitle: 'Live API Google Gemini & Groq LLaMA',
    icon: Sparkles,
    badge: 'Live API',
    color: 'text-violet-600 bg-violet-50 border-violet-200'
  },
  {
    id: 'strategy',
    title: 'Strategi Konten & Gambar',
    subtitle: 'Prioritas 5 provider cover & auto publish',
    icon: Layers,
    color: 'text-amber-600 bg-amber-50 border-amber-200'
  },
  {
    id: 'telegram',
    title: 'Notifikasi Telegram',
    subtitle: 'Bot alert, rekap harian & webhook 2-arah',
    icon: Send,
    color: 'text-sky-600 bg-sky-50 border-sky-200'
  },
  {
    id: 'system',
    title: 'Sistem & SEO Global',
    subtitle: 'Meta tag default & retensi log database',
    icon: Globe,
    color: 'text-slate-600 bg-slate-100 border-slate-200'
  }
]

const activeTab = ref<SettingTab>('hero')
const loading = ref(false)
const saving = ref(false)

// Action loading states
const testingAiKey = ref(false)
const fetchingLiveModels = ref(false)
const settingWebhook = ref(false)
const testingTelegram = ref(false)

// Live Model Storage from API
interface LiveAiModel {
  id: string
  name: string
  description?: string
  is_recommended?: boolean
}

const liveGeminiModels = ref<LiveAiModel[]>([])
const liveGroqModels = ref<LiveAiModel[]>([])

// Password mask state for API keys
const showGeminiKeys = ref<Record<number, boolean>>({})
const showGroqKeys = ref<Record<number, boolean>>({})

// AI Test Results modal/alert
const aiTestResult = ref<{
  show: boolean
  title: string
  models: any[]
  isSuccess: boolean
  message: string
}>({
  show: false,
  title: '',
  models: [],
  isSuccess: true,
  message: ''
})

// Settings Form Data
const heroForm = ref({
  hero_title: '',
  hero_description: '',
  hero_btn_primary: '',
  hero_btn_secondary: '',
  hero_bg_image: ''
})

const identityForm = ref({
  phone: '',
  email: '',
  address: '',
  whatsapp: '',
  site_logo: '',
  site_icon: ''
})

const whatsappForm = ref({
  wa_template_general: '',
  wa_template: ''
})

const telegramForm = ref({
  tg_bot_token: '',
  tg_chat_id: '',
  tg_report_time: '08:00',
  tg_report_config: ['leads', 'traffic'] as string[],
  tg_notify_enabled: false,
  tg_log_notify_enabled: false,
  tg_site_url: '',
  tg_webhook_token: ''
})

const aiForm = ref({
  ai_active_provider: 'gemini',
  ai_config_gemini_keys: [] as string[],
  ai_config_groq_keys: [] as string[],
  ai_config_gemini_model: 'gemini-2.5-flash',
  ai_config_groq_model: 'llama-3.3-70b-versatile',
  ai_system_instruction: '',
  ai_prompt_template: '',
  auto_publish: true,
  ai_generate_image: true,
  ai_image_priority: ['pollinations', 'huggingface', 'pexels', 'google'] as string[],
  ai_huggingface_token: '',
  ai_pexels_key: '',
  ai_image_keep_people: true
})

const systemForm = ref({
  site_meta_title: 'Arno D Clean - Jasa Cuci Kasur & Sofa Tangerang',
  site_meta_description: 'Jasa cuci sofa, kasur, dan karpet profesional di Tangerang. Bersih, wangi, dan bebas tungau.',
  log_retention_days: 30
})

// Provider metadata for image strategy
const imageProvidersMeta: Record<string, { name: string; badge: string; type: string }> = {
  pollinations: { name: 'Pollinations.ai', badge: 'Free (Default)', type: 'AI Generator' },
  huggingface: { name: 'Hugging Face', badge: 'High Quality', type: 'AI Stable Diffusion' },
  pexels: { name: 'Pexels Stock', badge: 'Real Photo', type: 'Stock Photography' },
  perchance: { name: 'Perchance.org', badge: 'Experimental', type: 'AI Generator' },
  google: { name: 'Google Imagen', badge: 'Paid', type: 'Google Cloud' }
}

// File uploads
const heroBgFile = ref<File | null>(null)
const heroBgPreview = ref<string>('')
const logoFile = ref<File | null>(null)
const logoPreview = ref<string>('')
const iconFile = ref<File | null>(null)
const iconPreview = ref<string>('')

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
  }, 4500)
}

async function fetchSettings() {
  loading.value = true
  try {
    const res = await axios.get('/api/v2/data.php?type=settings', { withCredentials: true })
    if (res.data.status === 'success' && res.data.data) {
      const d = res.data.data

      // Hero
      if (d.hero) {
        heroForm.value = { ...d.hero }
        heroBgPreview.value = d.hero.hero_bg_image ? getImageUrl(d.hero.hero_bg_image) : ''
      }

      // Identity
      if (d.identity) {
        identityForm.value = { ...d.identity }
        logoPreview.value = d.identity.site_logo ? getImageUrl(d.identity.site_logo) : ''
        iconPreview.value = d.identity.site_icon ? getImageUrl(d.identity.site_icon) : ''
      }

      // WhatsApp
      if (d.whatsapp) {
        whatsappForm.value = { ...d.whatsapp }
      }

      // Telegram
      if (d.telegram) {
        telegramForm.value = {
          tg_bot_token: d.telegram.tg_bot_token || '',
          tg_chat_id: d.telegram.tg_chat_id || '',
          tg_report_time: d.telegram.tg_report_time || '08:00',
          tg_report_config: Array.isArray(d.telegram.tg_report_config) ? d.telegram.tg_report_config : ['leads', 'traffic'],
          tg_notify_enabled: !!d.telegram.tg_notify_enabled,
          tg_log_notify_enabled: !!d.telegram.tg_log_notify_enabled,
          tg_site_url: d.telegram.tg_site_url || window.location.origin,
          tg_webhook_token: d.telegram.tg_webhook_token || ''
        }
      }

      // AI
      if (d.ai) {
        let gModel = d.ai.ai_config_gemini_model || 'gemini-2.5-flash'
        // Upgrade legacy obsolete model references automatically
        if (gModel.includes('1.5') || gModel.includes('1.0') || gModel.includes('2.0-flash-exp')) {
          gModel = 'gemini-2.5-flash'
        }

        aiForm.value = {
          ai_active_provider: d.ai.ai_active_provider || 'gemini',
          ai_config_gemini_keys: Array.isArray(d.ai.ai_config_gemini_keys) ? [...d.ai.ai_config_gemini_keys] : [],
          ai_config_groq_keys: Array.isArray(d.ai.ai_config_groq_keys) ? [...d.ai.ai_config_groq_keys] : [],
          ai_config_gemini_model: gModel,
          ai_config_groq_model: d.ai.ai_config_groq_model || 'llama-3.3-70b-versatile',
          ai_system_instruction: d.ai.ai_system_instruction || '',
          ai_prompt_template: d.ai.ai_prompt_template || '',
          auto_publish: d.ai.auto_publish ?? true,
          ai_generate_image: d.ai.ai_generate_image ?? true,
          ai_image_priority: Array.isArray(d.ai.ai_image_priority) && d.ai.ai_image_priority.length > 0
            ? [...d.ai.ai_image_priority]
            : ['pollinations', 'huggingface', 'pexels', 'google'],
          ai_huggingface_token: d.ai.ai_huggingface_token || '',
          ai_pexels_key: d.ai.ai_pexels_key || '',
          ai_image_keep_people: d.ai.ai_image_keep_people ?? true
        }

        // Ensure all available image providers exist in the priority list
        Object.keys(imageProvidersMeta).forEach(pKey => {
          if (!aiForm.value.ai_image_priority.includes(pKey)) {
            aiForm.value.ai_image_priority.push(pKey)
          }
        })
      }

      // System
      if (d.system) {
        systemForm.value = {
          site_meta_title: d.system.site_meta_title || '',
          site_meta_description: d.system.site_meta_description || '',
          log_retention_days: Number(d.system.log_retention_days) || 30
        }
      }

      // Automatically fetch live models if API keys are present
      fetchLiveAiModels(false)
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memuat pengaturan', 'error')
  } finally {
    loading.value = false
  }
}

// Fetch live models directly from provider's API
async function fetchLiveAiModels(notify = true) {
  const provider = aiForm.value.ai_active_provider as 'gemini' | 'groq'
  const keys = provider === 'gemini' ? aiForm.value.ai_config_gemini_keys : aiForm.value.ai_config_groq_keys
  const activeKey = keys.find(k => k.trim().length > 0) || ''

  if (!activeKey && notify) {
    showToast(`Masukkan API Key ${provider === 'gemini' ? 'Google Gemini' : 'Groq'} terlebih dahulu`, 'error')
    return
  }

  fetchingLiveModels.value = true
  try {
    const res = await axios.post('/api/v2/data.php?type=settings', {
      action: 'get_live_models',
      provider,
      api_key: activeKey
    }, { withCredentials: true })

    if (res.data.status === 'success' && Array.isArray(res.data.models)) {
      if (provider === 'gemini') {
        liveGeminiModels.value = res.data.models
      } else {
        liveGroqModels.value = res.data.models
      }
      if (notify) {
        showToast(res.data.message || `Berhasil memuat model ${provider === 'gemini' ? 'Gemini' : 'Groq'} terbaru!`, 'success')
      }
    } else if (notify) {
      showToast(res.data.message || 'Gagal mengambil model dari API', 'error')
    }
  } catch (err: any) {
    if (notify) {
      showToast(err.response?.data?.message || 'Gagal menghubungi server untuk memuat model', 'error')
    }
  } finally {
    fetchingLiveModels.value = false
  }
}

function handleFileSelect(e: Event, type: 'hero' | 'logo' | 'icon') {
  const target = e.target as HTMLInputElement
  if (!target.files?.length) return
  const file = target.files[0]
  const reader = new FileReader()
  reader.onload = () => {
    const res = reader.result as string
    if (type === 'hero') {
      heroBgFile.value = file
      heroBgPreview.value = res
    } else if (type === 'logo') {
      logoFile.value = file
      logoPreview.value = res
    } else if (type === 'icon') {
      iconFile.value = file
      iconPreview.value = res
    }
  }
  reader.readAsDataURL(file)
}

// Multi-Key helper methods
function addKey(provider: 'gemini' | 'groq') {
  if (provider === 'gemini') {
    aiForm.value.ai_config_gemini_keys.push('')
  } else {
    aiForm.value.ai_config_groq_keys.push('')
  }
}

function removeKey(provider: 'gemini' | 'groq', index: number) {
  if (provider === 'gemini') {
    aiForm.value.ai_config_gemini_keys.splice(index, 1)
  } else {
    aiForm.value.ai_config_groq_keys.splice(index, 1)
  }
}

function moveKey(provider: 'gemini' | 'groq', index: number, direction: 'up' | 'down') {
  const list = provider === 'gemini' ? aiForm.value.ai_config_gemini_keys : aiForm.value.ai_config_groq_keys
  const targetIndex = direction === 'up' ? index - 1 : index + 1
  if (targetIndex < 0 || targetIndex >= list.length) return
  const temp = list[index]
  list[index] = list[targetIndex]
  list[targetIndex] = temp
}

function moveImageProvider(index: number, direction: 'up' | 'down') {
  const list = aiForm.value.ai_image_priority
  const targetIndex = direction === 'up' ? index - 1 : index + 1
  if (targetIndex < 0 || targetIndex >= list.length) return
  const temp = list[index]
  list[index] = list[targetIndex]
  list[targetIndex] = temp
}

// Test AI Key Connection
async function testAiKey(provider: 'gemini' | 'groq') {
  const keys = provider === 'gemini' ? aiForm.value.ai_config_gemini_keys : aiForm.value.ai_config_groq_keys
  const validKey = keys.find(k => k.trim().length > 0)
  if (!validKey) {
    showToast(`Silakan masukkan minimal 1 API Key ${provider === 'gemini' ? 'Google Gemini' : 'Groq'} terlebih dahulu`, 'error')
    return
  }

  testingAiKey.value = true
  try {
    const res = await axios.post('/api/v2/data.php?type=settings', {
      action: 'test_ai_key',
      provider,
      api_key: validKey
    }, { withCredentials: true })

    if (res.data.status === 'success') {
      const returnedModels = res.data.models || []
      if (provider === 'gemini') liveGeminiModels.value = returnedModels
      else liveGroqModels.value = returnedModels

      aiTestResult.value = {
        show: true,
        title: `Koneksi API ${provider === 'gemini' ? 'Gemini' : 'Groq'} Berhasil!`,
        models: returnedModels,
        isSuccess: true,
        message: res.data.message || 'Kunci API valid dan siap digunakan.'
      }
    } else {
      aiTestResult.value = {
        show: true,
        title: `Uji Coba ${provider === 'gemini' ? 'Gemini' : 'Groq'} Gagal`,
        models: [],
        isSuccess: false,
        message: res.data.message || 'Koneksi ditolak oleh server provider.'
      }
    }
  } catch (err: any) {
    aiTestResult.value = {
      show: true,
      title: 'Kesalahan Sistem',
      models: [],
      isSuccess: false,
      message: err.response?.data?.message || 'Gagal menghubungi endpoint uji coba API.'
    }
  } finally {
    testingAiKey.value = false
  }
}

// Telegram Webhook Setup
async function registerTelegramWebhook() {
  if (!telegramForm.value.tg_bot_token || !telegramForm.value.tg_site_url || !telegramForm.value.tg_webhook_token) {
    showToast('Mohon lengkapi Bot Token, URL Website, dan Webhook Secret Token.', 'error')
    return
  }

  settingWebhook.value = true
  try {
    const res = await axios.post('/api/v2/data.php?type=settings', {
      action: 'set_telegram_webhook',
      tg_bot_token: telegramForm.value.tg_bot_token,
      tg_site_url: telegramForm.value.tg_site_url,
      tg_webhook_token: telegramForm.value.tg_webhook_token
    }, { withCredentials: true })

    if (res.data.status === 'success') {
      showToast(res.data.message || 'Webhook Telegram berhasil didaftarkan!', 'success')
    } else {
      showToast(res.data.message || 'Gagal mendaftarkan webhook', 'error')
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Error saat mendaftarkan webhook', 'error')
  } finally {
    settingWebhook.value = false
  }
}

// Test Telegram Message
async function sendTelegramTest() {
  if (!telegramForm.value.tg_bot_token || !telegramForm.value.tg_chat_id) {
    showToast('Bot Token dan Chat ID wajib diisi untuk tes kirim pesan.', 'error')
    return
  }

  testingTelegram.value = true
  try {
    const res = await axios.post('/api/v2/data.php?type=settings', {
      action: 'test_telegram_notification',
      tg_bot_token: telegramForm.value.tg_bot_token,
      tg_chat_id: telegramForm.value.tg_chat_id
    }, { withCredentials: true })

    if (res.data.status === 'success') {
      showToast(res.data.message || 'Pesan tes berhasil dikirim ke Telegram!', 'success')
    } else {
      showToast(res.data.message || 'Gagal mengirim pesan ke Telegram', 'error')
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Error saat mengirim pesan test ke Telegram', 'error')
  } finally {
    testingTelegram.value = false
  }
}

async function saveCurrentTab() {
  saving.value = true
  try {
    const fd = new FormData()

    let backendTab = activeTab.value as string
    if (activeTab.value === 'strategy') {
      backendTab = 'ai'
    }
    fd.append('tab', backendTab)

    if (activeTab.value === 'hero') {
      fd.append('hero_title', heroForm.value.hero_title)
      fd.append('hero_description', heroForm.value.hero_description)
      fd.append('hero_btn_primary', heroForm.value.hero_btn_primary)
      fd.append('hero_btn_secondary', heroForm.value.hero_btn_secondary)
      if (heroBgFile.value) {
        fd.append('hero_bg_image', heroBgFile.value)
      }
    } else if (activeTab.value === 'identity') {
      fd.append('phone', identityForm.value.phone)
      fd.append('email', identityForm.value.email)
      fd.append('address', identityForm.value.address)
      fd.append('whatsapp', identityForm.value.whatsapp)
      if (logoFile.value) {
        fd.append('site_logo', logoFile.value)
      }
      if (iconFile.value) {
        fd.append('site_icon', iconFile.value)
      }
    } else if (activeTab.value === 'whatsapp') {
      fd.append('wa_template_general', whatsappForm.value.wa_template_general)
      fd.append('wa_template', whatsappForm.value.wa_template)
    } else if (activeTab.value === 'telegram') {
      fd.append('tg_bot_token', telegramForm.value.tg_bot_token)
      fd.append('tg_chat_id', telegramForm.value.tg_chat_id)
      fd.append('tg_report_time', telegramForm.value.tg_report_time)
      fd.append('tg_report_config', JSON.stringify(telegramForm.value.tg_report_config))
      fd.append('tg_notify_enabled', telegramForm.value.tg_notify_enabled ? '1' : '0')
      fd.append('tg_log_notify_enabled', telegramForm.value.tg_log_notify_enabled ? '1' : '0')
      fd.append('tg_site_url', telegramForm.value.tg_site_url)
      fd.append('tg_webhook_token', telegramForm.value.tg_webhook_token)
    } else if (activeTab.value === 'ai' || activeTab.value === 'strategy') {
      fd.append('ai_active_provider', aiForm.value.ai_active_provider)
      fd.append('ai_config_gemini_keys', JSON.stringify(aiForm.value.ai_config_gemini_keys.filter(k => k.trim())))
      fd.append('ai_config_groq_keys', JSON.stringify(aiForm.value.ai_config_groq_keys.filter(k => k.trim())))
      fd.append('ai_config_gemini_model', aiForm.value.ai_config_gemini_model)
      fd.append('ai_config_groq_model', aiForm.value.ai_config_groq_model)
      fd.append('ai_system_instruction', aiForm.value.ai_system_instruction)
      fd.append('ai_prompt_template', aiForm.value.ai_prompt_template)
      fd.append('auto_publish', aiForm.value.auto_publish ? '1' : '0')
      fd.append('ai_generate_image', aiForm.value.ai_generate_image ? '1' : '0')
      fd.append('ai_image_priority', JSON.stringify(aiForm.value.ai_image_priority))
      fd.append('ai_huggingface_token', aiForm.value.ai_huggingface_token)
      fd.append('ai_pexels_key', aiForm.value.ai_pexels_key)
      fd.append('ai_image_keep_people', aiForm.value.ai_image_keep_people ? '1' : '0')
    } else if (activeTab.value === 'system') {
      fd.append('site_meta_title', systemForm.value.site_meta_title)
      fd.append('site_meta_description', systemForm.value.site_meta_description)
      fd.append('log_retention_days', String(systemForm.value.log_retention_days))
    }

    const res = await axios.post('/api/v2/data.php?type=settings', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      withCredentials: true
    })

    if (res.data.status === 'success') {
      showToast(res.data.message || 'Pengaturan berhasil disimpan!', 'success')
      fetchSettings()
    } else {
      showToast(res.data.message || 'Gagal menyimpan pengaturan', 'error')
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal menyimpan pengaturan', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="space-y-6 animate-fadeIn pb-16">
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

    <!-- AI Connection Modal Result -->
    <div
      v-if="aiTestResult.show"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 max-w-lg w-full border border-slate-100 shadow-2xl space-y-4 my-auto max-h-[90vh] overflow-y-auto">
        <div class="flex items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5 min-w-0 flex-1">
            <div
              class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0"
              :class="aiTestResult.isSuccess ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
            >
              <CheckCircle2 v-if="aiTestResult.isSuccess" class="w-5 h-5" />
              <AlertTriangle v-else class="w-5 h-5" />
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="font-bold text-slate-900 text-sm truncate sm:whitespace-normal">{{ aiTestResult.title }}</h3>
              <p class="text-[11px] text-slate-500 line-clamp-1">{{ aiTestResult.isSuccess ? 'Koneksi API Valid & Model Aktif Ditemukan' : 'Uji Koneksi Gagal' }}</p>
            </div>
          </div>
          <button @click="aiTestResult.show = false" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <p class="text-xs text-slate-600 leading-relaxed">{{ aiTestResult.message }}</p>

        <div v-if="aiTestResult.models.length > 0" class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Model Aktif dari API ({{ aiTestResult.models.length }}):</span>
            <span class="text-[10px] text-slate-400">Klik model untuk menerapkan</span>
          </div>
          <div class="max-h-56 overflow-y-auto space-y-1.5 p-2 bg-slate-50 rounded-2xl border border-slate-200/80">
            <div
              v-for="m in aiTestResult.models"
              :key="typeof m === 'string' ? m : m.id"
              @click="() => {
                const targetId = typeof m === 'string' ? m : m.id
                if (aiForm.ai_active_provider === 'gemini') aiForm.ai_config_gemini_model = targetId
                else aiForm.ai_config_groq_model = targetId
                aiTestResult.show = false
                showToast(`Model diubah ke: ${targetId}`, 'success')
              }"
              class="flex items-center justify-between text-xs font-mono text-slate-700 bg-white hover:bg-blue-50/70 hover:border-blue-300 px-3 py-2 rounded-xl border border-slate-100 cursor-pointer transition-all shadow-xs"
            >
              <div class="flex items-center gap-2 truncate">
                <Check class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" />
                <span class="font-semibold">{{ typeof m === 'string' ? m : m.id }}</span>
              </div>
              <span v-if="typeof m !== 'string' && m.is_recommended" class="text-[10px] px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-sans font-bold flex-shrink-0">
                Rekomendasi
              </span>
            </div>
          </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row justify-end">
          <button
            @click="aiTestResult.show = false"
            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-sm transition-all text-center"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Top Executive Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>Pengaturan Situs & Sistem</span>
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            Arno D-Clean Control Center
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Pusat kendali komprehensif tampilan, integrasi pesan, model AI live, dan otomasi web.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchSettings"
          :disabled="loading"
          class="p-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm transition-all"
          title="Segarkan Konfigurasi"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="loading ? 'animate-spin' : ''" />
        </button>

        <button
          @click="saveCurrentTab"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs sm:text-sm font-semibold shadow-md shadow-blue-500/20 active:scale-95 transition-all disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
        </button>
      </div>
    </div>

    <!-- MOBILE & TABLET COMPACT NAVIGATION (Zero-Slide Responsive Grid) -->
    <div class="lg:hidden">
      <div class="bg-white rounded-3xl p-3 border border-slate-100 shadow-subtle space-y-2">
        <div class="flex items-center justify-between px-2 pt-1">
          <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Pilih Bagian Pengaturan:</span>
          <span class="text-[11px] text-blue-600 font-semibold">{{ tabs.find(t => t.id === activeTab)?.title }}</span>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
          <button
            v-for="t in tabs"
            :key="t.id"
            @click="activeTab = t.id"
            class="flex items-center gap-2 px-3 py-2.5 rounded-2xl text-xs font-semibold transition-all text-left"
            :class="
              activeTab === t.id
                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/60'
            "
          >
            <component :is="t.icon" class="w-4 h-4 flex-shrink-0" :class="activeTab === t.id ? 'text-white' : 'text-slate-500'" />
            <span class="truncate">{{ t.title.split(' ')[0] }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- MAIN MASTER-DETAIL WORKSPACE (Vertical Sidebar + Precision Sheet, No Slider!) -->
    <div class="flex flex-col lg:flex-row gap-6 items-start">
      <!-- LEFT SIDEBAR: 7 Clean Vertical Sheets (Desktop) -->
      <aside class="hidden lg:block w-80 flex-shrink-0">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-subtle p-3 space-y-2 sticky top-6">
          <div class="px-3 py-2 flex items-center justify-between border-b border-slate-100 pb-3">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kategori Pengaturan</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-bold">7 Menu Lengkap</span>
          </div>

          <nav class="space-y-1">
            <button
              v-for="t in tabs"
              :key="t.id"
              @click="activeTab = t.id"
              class="w-full flex items-center justify-between p-3 rounded-2xl text-left transition-all group relative"
              :class="
                activeTab === t.id
                  ? 'bg-blue-50/80 text-blue-900 border border-blue-200/80 shadow-xs'
                  : 'text-slate-600 hover:bg-slate-50 border border-transparent hover:border-slate-100'
              "
            >
              <div class="flex items-center gap-3 min-w-0">
                <div
                  class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0 transition-transform group-hover:scale-105"
                  :class="activeTab === t.id ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : t.color"
                >
                  <component :is="t.icon" class="w-5 h-5" />
                </div>
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5">
                    <span class="font-bold text-xs truncate" :class="activeTab === t.id ? 'text-blue-950 font-bold' : 'text-slate-800'">
                      {{ t.title }}
                    </span>
                    <span v-if="t.badge" class="px-1.5 py-0.2 rounded-md bg-violet-100 text-violet-700 text-[9px] font-bold">
                      {{ t.badge }}
                    </span>
                  </div>
                  <p class="text-[11px] truncate mt-0.5" :class="activeTab === t.id ? 'text-blue-700/80' : 'text-slate-400'">
                    {{ t.subtitle }}
                  </p>
                </div>
              </div>

              <ChevronRight
                class="w-4 h-4 flex-shrink-0 transition-transform"
                :class="activeTab === t.id ? 'text-blue-600 translate-x-0.5' : 'text-slate-300 opacity-0 group-hover:opacity-100'"
              />
            </button>
          </nav>

          <!-- System Status Footer Widget -->
          <div class="pt-3 border-t border-slate-100 px-2 space-y-2 text-[11px]">
            <div class="flex items-center justify-between text-slate-500">
              <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Otak AI Aktif:</span>
              </span>
              <span class="font-bold font-mono text-slate-700">
                {{ aiForm.ai_active_provider === 'gemini' ? 'Google Gemini' : 'Groq LLaMA' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-slate-500">
              <span>Model Default:</span>
              <span class="font-semibold font-mono text-slate-700 truncate max-w-[130px]">
                {{ aiForm.ai_active_provider === 'gemini' ? aiForm.ai_config_gemini_model : aiForm.ai_config_groq_model }}
              </span>
            </div>
          </div>
        </div>
      </aside>

      <!-- RIGHT CONTENT PANEL: Active Sheet Configuration -->
      <main class="flex-1 min-w-0">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-subtle p-6 lg:p-8 space-y-6">

          <!-- SHEET 1: HERO DISPLAY -->
          <div v-if="activeTab === 'hero'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                  <Layout class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Tampilan Hero & Banner Utama</h2>
                  <p class="text-xs text-slate-500">Sesuaikan headline, sub-deskripsi, gambar latar belakang dan tombol ajakan bertindak (CTA).</p>
                </div>
              </div>
            </div>

            <div class="space-y-5 text-xs">
              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Judul Utama Hero (Headline):</label>
                <input
                  v-model="heroForm.hero_title"
                  type="text"
                  class="w-full px-4 py-3 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-sm"
                  placeholder="Jasa Cuci Kasur & Sofa Profesional Tangerang"
                />
              </div>

              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Sub-Deskripsi Hero:</label>
                <textarea
                  v-model="heroForm.hero_description"
                  rows="3"
                  class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500/20 text-xs"
                  placeholder="Bersih higienis, bebas tungau, wangi tahan lama dengan teknologi deep cleaning modern."
                ></textarea>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Teks Tombol Utama (CTA 1):</label>
                  <input
                    v-model="heroForm.hero_btn_primary"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-semibold"
                    placeholder="Lihat Layanan"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Teks Tombol Sekunder (CTA 2):</label>
                  <input
                    v-model="heroForm.hero_btn_secondary"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-semibold"
                    placeholder="Hubungi Kami"
                  />
                </div>
              </div>

              <!-- Hero Background Image -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200 space-y-3">
                <label class="font-bold text-slate-700 block">Gambar Latar Belakang Hero (Background Banner):</label>
                <div class="flex flex-col sm:flex-row items-center gap-4">
                  <div class="w-44 h-24 rounded-2xl bg-white border border-slate-200 overflow-hidden flex items-center justify-center flex-shrink-0 shadow-sm">
                    <img v-if="heroBgPreview" :src="heroBgPreview" alt="hero preview" class="w-full h-full object-cover" @error="heroBgPreview = ''" />
                    <ImageIcon v-else class="w-8 h-8 text-slate-300" />
                  </div>
                  <div class="space-y-2">
                    <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold cursor-pointer shadow-xs transition-all">
                      <Upload class="w-3.5 h-3.5" />
                      <span>Pilih Gambar Latar Baru</span>
                      <input type="file" accept="image/*" class="sr-only" @change="(e) => handleFileSelect(e, 'hero')" />
                    </label>
                    <p class="text-[11px] text-slate-400">Rekomendasi resolusi: 1920x1080px (Format JPG, PNG, atau WebP).</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SHEET 2: IDENTITY & CONTACT -->
          <div v-if="activeTab === 'identity'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                  <PhoneCall class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Identitas Website & Kontak Resmi</h2>
                  <p class="text-xs text-slate-500">Informasi resmi nomor WhatsApp penerima order, alamat, logo navbar, dan favicon browser.</p>
                </div>
              </div>
            </div>

            <div class="space-y-5 text-xs">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Nomor WhatsApp Admin (Penerima Pesanan):</label>
                  <input
                    v-model="identityForm.whatsapp"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-mono font-bold"
                    placeholder="6281xxxxxxxx"
                  />
                  <p class="text-[11px] text-slate-400">Format angka diawali kode negara tanpa spasi (62812...)</p>
                </div>

                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Nomor Telepon Kantor (Display):</label>
                  <input
                    v-model="identityForm.phone"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-mono"
                    placeholder="0812-xxxx-xxxx"
                  />
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Email Bisnis Resmi:</label>
                <input
                  v-model="identityForm.email"
                  type="email"
                  class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800"
                  placeholder="kontak@arnodclean.com"
                />
              </div>

              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Alamat Lengkap Kantor / Workshop:</label>
                <textarea
                  v-model="identityForm.address"
                  rows="3"
                  class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed"
                  placeholder="Jl. Raya Serpong No. ..., Tangerang Selatan"
                ></textarea>
              </div>

              <!-- Logo & Favicon in Clean Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div class="p-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200 space-y-3">
                  <label class="font-bold text-slate-700 block">Logo Website (Navbar):</label>
                  <div class="flex items-center gap-3">
                    <div class="w-24 h-14 rounded-xl bg-white border border-slate-200 flex items-center justify-center p-2 shadow-xs">
                      <img v-if="logoPreview" :src="logoPreview" alt="logo" class="max-w-full max-h-full object-contain" @error="logoPreview = ''" />
                      <ImageIcon v-else class="w-6 h-6 text-slate-300" />
                    </div>
                    <div>
                      <label class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold cursor-pointer shadow-xs inline-block">
                        <span>Ganti Logo</span>
                        <input type="file" accept="image/*" class="sr-only" @change="(e) => handleFileSelect(e, 'logo')" />
                      </label>
                      <p class="text-[10px] text-slate-400 mt-1">Rasio 3:1 (PNG Transparan)</p>
                    </div>
                  </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 border border-dashed border-slate-200 space-y-3">
                  <label class="font-bold text-slate-700 block">Favicon Browser (App Icon):</label>
                  <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-xl bg-white border border-slate-200 flex items-center justify-center p-2 shadow-xs">
                      <img v-if="iconPreview" :src="iconPreview" alt="icon" class="max-w-full max-h-full object-contain" @error="iconPreview = ''" />
                      <ImageIcon v-else class="w-6 h-6 text-slate-300" />
                    </div>
                    <div>
                      <label class="px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-semibold cursor-pointer shadow-xs inline-block">
                        <span>Ganti Favicon</span>
                        <input type="file" accept="image/*" class="sr-only" @change="(e) => handleFileSelect(e, 'icon')" />
                      </label>
                      <p class="text-[10px] text-slate-400 mt-1">Rasio 1:1 Persegi (PNG/ICO)</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SHEET 3: WHATSAPP INTEGRATION -->
          <div v-if="activeTab === 'whatsapp'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                  <MessageCircle class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Integrasi WhatsApp & Pesan Otomatis</h2>
                  <p class="text-xs text-slate-500">Sesuaikan template teks obrolan ketika pengunjung mengklik tombol konsultasi atau kartu layanan.</p>
                </div>
              </div>
            </div>

            <div class="space-y-5 text-xs">
              <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200 flex items-start gap-3 text-emerald-950">
                <MessageCircle class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                <div>
                  <p class="font-bold text-xs">Variabel Dinamis Nama Layanan</p>
                  <p class="text-[11px] text-emerald-800/90 mt-0.5 leading-relaxed">
                    Sistem mendukung variabel <code>[nama layanan]</code> atau <code>{service}</code>. Nilai tersebut otomatis diganti dengan layanan spesifik yang diklik pengunjung.
                  </p>
                </div>
              </div>

              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Pesan Floating Button WhatsApp (Umum):</label>
                <textarea
                  v-model="whatsappForm.wa_template_general"
                  rows="3"
                  class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed font-sans"
                  placeholder="Halo Admin Arno D Clean, saya ingin menanyakan informasi layanan Anda."
                ></textarea>
              </div>

              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Pesan Pemesanan Layanan Spesifik (Tombol Pesan/Kartu):</label>
                <textarea
                  v-model="whatsappForm.wa_template"
                  rows="4"
                  class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed font-sans"
                  placeholder="Halo Arno D Clean, saya tertarik memesan layanan [nama layanan]. Boleh info harga dan jadwal terdekat?"
                ></textarea>
              </div>
            </div>
          </div>

          <!-- SHEET 4: AI BRAIN & ENGINE (LIVE API INTEGRATION) -->
          <div v-if="activeTab === 'ai'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center font-bold">
                    <Sparkles class="w-4 h-4" />
                  </div>
                  <div>
                    <h2 class="text-lg font-bold text-slate-900">Otak AI (Engine) & Live API Integration</h2>
                    <p class="text-xs text-slate-500">Koneksi langsung ke Google Generative Language API (v1beta) dan Groq LLaMA dengan model terbaru.</p>
                  </div>
                </div>

                <button
                  type="button"
                  @click="fetchLiveAiModels(true)"
                  :disabled="fetchingLiveModels"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-violet-50 hover:bg-violet-100 text-violet-700 font-semibold text-xs transition-all disabled:opacity-50"
                  title="Ambil daftar model terbaru langsung dari server Google/Groq"
                >
                  <RefreshCw class="w-3.5 h-3.5" :class="fetchingLiveModels ? 'animate-spin' : ''" />
                  <span>{{ fetchingLiveModels ? 'Memuat API...' : 'Perbarui Model via API' }}</span>
                </button>
              </div>
            </div>

            <div class="space-y-6 text-xs">
              <!-- Active AI Provider Switcher -->
              <div class="space-y-2">
                <label class="font-bold text-slate-700">Penyedia AI Utama (Active Provider):</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label
                    class="p-4 rounded-2xl border cursor-pointer flex items-center justify-between transition-all"
                    :class="aiForm.ai_active_provider === 'gemini' ? 'bg-blue-50/80 border-blue-500 text-blue-900 shadow-xs' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
                  >
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">G</div>
                      <div>
                        <div class="font-bold text-sm text-slate-900">Google Gemini API</div>
                        <div class="text-[10px] text-slate-500">Generasi 2.5 & 3 (Flash, Pro, Latest)</div>
                      </div>
                    </div>
                    <input type="radio" value="gemini" v-model="aiForm.ai_active_provider" class="sr-only" />
                    <div class="w-4 h-4 rounded-full border border-blue-500 flex items-center justify-center">
                      <div v-if="aiForm.ai_active_provider === 'gemini'" class="w-2.5 h-2.5 rounded-full bg-blue-600"></div>
                    </div>
                  </label>

                  <label
                    class="p-4 rounded-2xl border cursor-pointer flex items-center justify-between transition-all"
                    :class="aiForm.ai_active_provider === 'groq' ? 'bg-rose-50/80 border-rose-500 text-rose-900 shadow-xs' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
                  >
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-2xl bg-rose-600 text-white flex items-center justify-center shadow-xs">
                        <Zap class="w-5 h-5" />
                      </div>
                      <div>
                        <div class="font-bold text-sm text-slate-900">Groq Cloud (LLaMA 3)</div>
                        <div class="text-[10px] text-slate-500">Inference Ultra-Cepat LPU Engine</div>
                      </div>
                    </div>
                    <input type="radio" value="groq" v-model="aiForm.ai_active_provider" class="sr-only" />
                    <div class="w-4 h-4 rounded-full border border-rose-500 flex items-center justify-center">
                      <div v-if="aiForm.ai_active_provider === 'groq'" class="w-2.5 h-2.5 rounded-full bg-rose-600"></div>
                    </div>
                  </label>
                </div>
              </div>

              <!-- GOOGLE GEMINI CONFIG PANEL -->
              <div class="p-5 rounded-3xl border border-blue-100 bg-blue-50/20 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-blue-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <span class="font-bold text-slate-800 text-sm">Konfigurasi Google Gemini</span>
                  </div>
                  <button
                    type="button"
                    @click="testAiKey('gemini')"
                    :disabled="testingAiKey"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-blue-200 hover:bg-blue-50 text-blue-700 font-semibold shadow-xs transition-all disabled:opacity-50"
                  >
                    <RefreshCw v-if="testingAiKey" class="w-3.5 h-3.5 animate-spin" />
                    <Key v-else class="w-3.5 h-3.5 text-blue-600" />
                    <span>{{ testingAiKey ? 'Menguji...' : 'Test & Verifikasi Gemini' }}</span>
                  </button>
                </div>

                <!-- Gemini Live Model Picker -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <label class="font-bold text-slate-700">Model Google Gemini Aktif:</label>
                    <span class="text-[11px] text-blue-700 font-semibold font-mono">{{ aiForm.ai_config_gemini_model }}</span>
                  </div>

                  <div class="flex gap-2">
                    <input
                      v-model="aiForm.ai_config_gemini_model"
                      type="text"
                      class="flex-1 px-4 py-2.5 rounded-2xl bg-white border border-slate-200 font-mono text-slate-800 font-semibold text-xs shadow-xs"
                      placeholder="e.g. gemini-2.5-flash"
                    />
                    <select
                      @change="(e: any) => { if (e.target.value) aiForm.ai_config_gemini_model = e.target.value }"
                      class="px-3 py-2.5 rounded-2xl bg-white border border-slate-200 font-mono text-xs text-slate-700 shadow-xs cursor-pointer focus:outline-none"
                    >
                      <option value="">Pilih dari API Google...</option>
                      <option
                        v-for="m in liveGeminiModels"
                        :key="m.id"
                        :value="m.id"
                        :selected="aiForm.ai_config_gemini_model === m.id"
                      >
                        {{ m.id }} {{ m.is_recommended ? '★ (Rekomendasi)' : '' }}
                      </option>
                    </select>
                  </div>

                  <!-- Quick Model Badges (Live Version) -->
                  <div class="space-y-1.5 pt-1">
                    <span class="text-[10px] text-slate-400 block font-semibold uppercase tracking-wider">Model Generasi Terbaru (Aktif & Valid):</span>
                    <div class="flex flex-wrap gap-1.5">
                      <button
                        type="button"
                        v-for="m in ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-flash-latest', 'gemini-2.5-flash-lite', 'gemini-3-flash-preview', 'gemini-pro-latest']"
                        :key="m"
                        @click="aiForm.ai_config_gemini_model = m"
                        class="px-3 py-1 rounded-xl border text-[11px] font-mono transition-all flex items-center gap-1 shadow-2xs"
                        :class="
                          aiForm.ai_config_gemini_model === m
                            ? 'bg-blue-600 text-white border-blue-600 font-bold shadow-xs'
                            : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                        "
                      >
                        <span>{{ m }}</span>
                        <span v-if="m.includes('2.5-flash') || m.includes('flash-latest')" class="text-[9px] px-1 rounded bg-blue-500/20 text-white font-sans">Top</span>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Gemini API Keys Rotation -->
                <div class="space-y-2 pt-2">
                  <div class="flex items-center justify-between">
                    <div>
                      <label class="font-bold text-slate-700">Gemini API Keys (Rotasi Failover):</label>
                      <p class="text-[10px] text-slate-400">Jika limit habis, sistem otomatis beralih ke kunci berikutnya.</p>
                    </div>
                    <button
                      type="button"
                      @click="addKey('gemini')"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold text-xs transition-all"
                    >
                      <Plus class="w-3.5 h-3.5" />
                      <span>Tambah Kunci</span>
                    </button>
                  </div>

                  <div v-if="aiForm.ai_config_gemini_keys.length === 0" class="text-slate-400 italic py-3 text-center bg-white rounded-2xl border border-dashed border-slate-200">
                    Belum ada API Key Gemini ditambahkan. Klik "+ Tambah Kunci".
                  </div>

                  <div class="space-y-2">
                    <div
                      v-for="(_k, idx) in aiForm.ai_config_gemini_keys"
                      :key="idx"
                      class="flex items-center gap-2 p-2 bg-white rounded-2xl border border-slate-200 shadow-xs"
                    >
                      <span class="w-6 text-center font-bold text-slate-400 text-xs">{{ idx + 1 }}</span>
                      <input
                        v-model="aiForm.ai_config_gemini_keys[idx]"
                        :type="showGeminiKeys[idx] ? 'text' : 'password'"
                        class="flex-1 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-xs text-slate-800 focus:bg-white focus:outline-none"
                        placeholder="AIzaSy..."
                      />
                      <button
                        type="button"
                        @click="showGeminiKeys[idx] = !showGeminiKeys[idx]"
                        class="p-1.5 text-slate-400 hover:text-slate-600"
                        title="Tampilkan / Sembunyikan Kunci"
                      >
                        <EyeOff v-if="showGeminiKeys[idx]" class="w-4 h-4" />
                        <Eye v-else class="w-4 h-4" />
                      </button>
                      <button
                        type="button"
                        :disabled="idx === 0"
                        @click="moveKey('gemini', idx, 'up')"
                        class="p-1.5 text-slate-400 hover:text-slate-600 disabled:opacity-20"
                        title="Naikkan Urutan"
                      >
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        :disabled="idx === aiForm.ai_config_gemini_keys.length - 1"
                        @click="moveKey('gemini', idx, 'down')"
                        class="p-1.5 text-slate-400 hover:text-slate-600 disabled:opacity-20"
                        title="Turunkan Urutan"
                      >
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        @click="removeKey('gemini', idx)"
                        class="p-1.5 text-rose-500 hover:text-rose-700"
                        title="Hapus Kunci"
                      >
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- GROQ LLaMA CONFIG PANEL -->
              <div class="p-5 rounded-3xl border border-rose-100 bg-rose-50/20 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-rose-100">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
                    <span class="font-bold text-slate-800 text-sm">Konfigurasi Groq (LLaMA 3)</span>
                  </div>
                  <button
                    type="button"
                    @click="testAiKey('groq')"
                    :disabled="testingAiKey"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-rose-200 hover:bg-rose-50 text-rose-700 font-semibold shadow-xs transition-all disabled:opacity-50"
                  >
                    <RefreshCw v-if="testingAiKey" class="w-3.5 h-3.5 animate-spin" />
                    <Key v-else class="w-3.5 h-3.5 text-rose-600" />
                    <span>{{ testingAiKey ? 'Menguji...' : 'Test & Verifikasi Groq' }}</span>
                  </button>
                </div>

                <!-- Groq Live Model Picker -->
                <div class="space-y-2">
                  <div class="flex items-center justify-between">
                    <label class="font-bold text-slate-700">Model Groq Aktif:</label>
                    <span class="text-[11px] text-rose-700 font-semibold font-mono">{{ aiForm.ai_config_groq_model }}</span>
                  </div>

                  <div class="flex gap-2">
                    <input
                      v-model="aiForm.ai_config_groq_model"
                      type="text"
                      class="flex-1 px-4 py-2.5 rounded-2xl bg-white border border-slate-200 font-mono text-slate-800 font-semibold text-xs shadow-xs"
                      placeholder="llama-3.3-70b-versatile"
                    />
                    <select
                      @change="(e: any) => { if (e.target.value) aiForm.ai_config_groq_model = e.target.value }"
                      class="px-3 py-2.5 rounded-2xl bg-white border border-slate-200 font-mono text-xs text-slate-700 shadow-xs cursor-pointer focus:outline-none"
                    >
                      <option value="">Pilih dari API Groq...</option>
                      <option
                        v-for="m in liveGroqModels"
                        :key="m.id"
                        :value="m.id"
                        :selected="aiForm.ai_config_groq_model === m.id"
                      >
                        {{ m.id }}
                      </option>
                    </select>
                  </div>

                  <!-- Quick Model Badges Groq -->
                  <div class="space-y-1.5 pt-1">
                    <span class="text-[10px] text-slate-400 block font-semibold uppercase tracking-wider">Preset Model Groq Unggulan:</span>
                    <div class="flex flex-wrap gap-1.5">
                      <button
                        type="button"
                        v-for="m in ['llama-3.3-70b-versatile', 'llama-3.1-70b-versatile', 'llama-3.1-8b-instant', 'qwen/qwen3.8-27b']"
                        :key="m"
                        @click="aiForm.ai_config_groq_model = m"
                        class="px-3 py-1 rounded-xl border text-[11px] font-mono transition-all"
                        :class="
                          aiForm.ai_config_groq_model === m
                            ? 'bg-rose-600 text-white border-rose-600 font-bold shadow-xs'
                            : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
                        "
                      >
                        {{ m }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Groq Keys Rotation -->
                <div class="space-y-2 pt-2">
                  <div class="flex items-center justify-between">
                    <div>
                      <label class="font-bold text-slate-700">Groq API Keys (Rotasi Failover):</label>
                      <p class="text-[10px] text-slate-400">Rotasi otomatis antar kunci Groq saat batas kuota tercapai.</p>
                    </div>
                    <button
                      type="button"
                      @click="addKey('groq')"
                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 font-semibold text-xs transition-all"
                    >
                      <Plus class="w-3.5 h-3.5" />
                      <span>Tambah Kunci</span>
                    </button>
                  </div>

                  <div v-if="aiForm.ai_config_groq_keys.length === 0" class="text-slate-400 italic py-3 text-center bg-white rounded-2xl border border-dashed border-slate-200">
                    Belum ada API Key Groq ditambahkan. Klik "+ Tambah Kunci".
                  </div>

                  <div class="space-y-2">
                    <div
                      v-for="(_k, idx) in aiForm.ai_config_groq_keys"
                      :key="idx"
                      class="flex items-center gap-2 p-2 bg-white rounded-2xl border border-slate-200 shadow-xs"
                    >
                      <span class="w-6 text-center font-bold text-slate-400 text-xs">{{ idx + 1 }}</span>
                      <input
                        v-model="aiForm.ai_config_groq_keys[idx]"
                        :type="showGroqKeys[idx] ? 'text' : 'password'"
                        class="flex-1 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 font-mono text-xs text-slate-800 focus:bg-white focus:outline-none"
                        placeholder="gsk_..."
                      />
                      <button
                        type="button"
                        @click="showGroqKeys[idx] = !showGroqKeys[idx]"
                        class="p-1.5 text-slate-400 hover:text-slate-600"
                        title="Tampilkan Kunci"
                      >
                        <EyeOff v-if="showGroqKeys[idx]" class="w-4 h-4" />
                        <Eye v-else class="w-4 h-4" />
                      </button>
                      <button
                        type="button"
                        :disabled="idx === 0"
                        @click="moveKey('groq', idx, 'up')"
                        class="p-1.5 text-slate-400 hover:text-slate-600 disabled:opacity-20"
                        title="Naikkan Urutan"
                      >
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        :disabled="idx === aiForm.ai_config_groq_keys.length - 1"
                        @click="moveKey('groq', idx, 'down')"
                        class="p-1.5 text-slate-400 hover:text-slate-600 disabled:opacity-20"
                        title="Turunkan Urutan"
                      >
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        @click="removeKey('groq', idx)"
                        class="p-1.5 text-rose-500 hover:text-rose-700"
                        title="Hapus Kunci"
                      >
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- System Instruction & Persona -->
              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">System Instruction (Persona AI):</label>
                <textarea
                  v-model="aiForm.ai_system_instruction"
                  rows="3"
                  class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed"
                  placeholder="Anda adalah asisten SEO profesional spesialis jasa cuci kasur, sofa, dan karpet..."
                ></textarea>
                <p class="text-[11px] text-slate-400">Instruksi sistem yang membentuk persona, nada bicara, dan standar penulisan konten.</p>
              </div>

              <!-- Prompt Template -->
              <div class="space-y-1.5">
                <label class="font-bold text-slate-700">Template Prompt Penulisan Artikel Otomatis:</label>
                <textarea
                  v-model="aiForm.ai_prompt_template"
                  rows="5"
                  class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 font-mono text-xs text-slate-800 leading-relaxed"
                ></textarea>
                <p class="text-[11px] text-amber-600 flex items-center gap-1 font-semibold">
                  <AlertTriangle class="w-3.5 h-3.5" />
                  <span>Wajib mencantumkan variabel <code>{keyword}</code> di dalam isi prompt template.</span>
                </p>
              </div>
            </div>
          </div>

          <!-- SHEET 5: AI CONTENT & IMAGE STRATEGY -->
          <div v-if="activeTab === 'strategy'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                  <Layers class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Strategi Konten & Fallback Sumber Gambar</h2>
                  <p class="text-xs text-slate-500">Tentukan otomatisasi publikasi dan urutan prioritas failover penyedia gambar cover artikel.</p>
                </div>
              </div>
            </div>

            <div class="space-y-6 text-xs">
              <!-- Switch Controls -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex items-center justify-between cursor-pointer hover:bg-slate-50 transition-all">
                  <div>
                    <span class="font-bold text-slate-800 block text-xs">Generate Gambar AI</span>
                    <span class="text-[10px] text-slate-400">Otomatis buat cover visual</span>
                  </div>
                  <input type="checkbox" v-model="aiForm.ai_generate_image" class="rounded text-blue-600 w-4 h-4" />
                </label>

                <label class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex items-center justify-between cursor-pointer hover:bg-slate-50 transition-all">
                  <div>
                    <span class="font-bold text-slate-800 block text-xs">Langsung Terbit (Publish)</span>
                    <span class="text-[10px] text-slate-400">Publish langsung vs Draft</span>
                  </div>
                  <input type="checkbox" v-model="aiForm.auto_publish" class="rounded text-blue-600 w-4 h-4" />
                </label>

                <label class="p-4 rounded-2xl border border-slate-200 bg-slate-50/50 flex items-center justify-between cursor-pointer hover:bg-slate-50 transition-all">
                  <div>
                    <span class="font-bold text-slate-800 block text-xs">Izin Orang / Hewan</span>
                    <span class="text-[10px] text-slate-400">Tampilkan manusia pada foto</span>
                  </div>
                  <input type="checkbox" v-model="aiForm.ai_image_keep_people" class="rounded text-blue-600 w-4 h-4" />
                </label>
              </div>

              <!-- Image Priority Fallback List -->
              <div class="p-5 rounded-3xl border border-amber-200/80 bg-amber-50/30 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-amber-100">
                  <div class="flex items-center gap-2">
                    <Camera class="w-4 h-4 text-amber-600" />
                    <span class="font-bold text-slate-800 text-sm">Prioritas Sumber Gambar (Failover Fallback):</span>
                  </div>
                  <span class="text-[10px] text-slate-400">Urutan teratas dieksekusi terlebih dahulu</span>
                </div>

                <div class="space-y-2.5">
                  <div
                    v-for="(provKey, pIdx) in aiForm.ai_image_priority"
                    :key="provKey"
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 bg-white rounded-2xl border border-slate-200 shadow-xs"
                  >
                    <div class="flex items-center gap-3">
                      <div class="w-7 h-7 rounded-xl bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center flex-shrink-0">
                        {{ pIdx + 1 }}
                      </div>
                      <div>
                        <div class="font-bold text-slate-800 flex items-center gap-2">
                          <span>{{ imageProvidersMeta[provKey]?.name || provKey }}</span>
                          <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-normal">
                            {{ imageProvidersMeta[provKey]?.badge || '' }}
                          </span>
                        </div>
                        <div class="text-[10px] text-slate-400">{{ imageProvidersMeta[provKey]?.type || '' }}</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 self-end sm:self-auto">
                      <!-- Token Hugging Face -->
                      <div v-if="provKey === 'huggingface'" class="w-52">
                        <input
                          v-model="aiForm.ai_huggingface_token"
                          type="password"
                          class="w-full px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono"
                          placeholder="Token Hugging Face..."
                        />
                      </div>

                      <!-- Token Pexels -->
                      <div v-if="provKey === 'pexels'" class="w-52">
                        <input
                          v-model="aiForm.ai_pexels_key"
                          type="password"
                          class="w-full px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono"
                          placeholder="API Key Pexels..."
                        />
                      </div>

                      <!-- Move buttons -->
                      <button
                        type="button"
                        :disabled="pIdx === 0"
                        @click="moveImageProvider(pIdx, 'up')"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 disabled:opacity-20 transition-all"
                        title="Naikkan Urutan"
                      >
                        <ArrowUp class="w-3.5 h-3.5" />
                      </button>
                      <button
                        type="button"
                        :disabled="pIdx === aiForm.ai_image_priority.length - 1"
                        @click="moveImageProvider(pIdx, 'down')"
                        class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 disabled:opacity-20 transition-all"
                        title="Turunkan Urutan"
                      >
                        <ArrowDown class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SHEET 6: TELEGRAM NOTIFICATIONS -->
          <div v-if="activeTab === 'telegram'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center font-bold">
                  <Send class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Notifikasi Bot Telegram & Webhook</h2>
                  <p class="text-xs text-slate-500">Kirim rekapan harian otomatis ke Telegram, alert artikel baru, dan setup webhook respons 2-arah.</p>
                </div>
              </div>
            </div>

            <div class="space-y-5 text-xs">
              <!-- Switch Notification Toggles -->
              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <label class="font-bold text-slate-800 block text-xs">Aktivasi Alert Telegram Otomatis:</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <label class="flex items-center justify-between p-3.5 rounded-xl bg-white border border-slate-200 cursor-pointer">
                    <div>
                      <span class="font-bold text-slate-800 block">Notifikasi Konten Baru</span>
                      <p class="text-[10px] text-slate-400">Kirim alert saat artikel AI selesai digenerate</p>
                    </div>
                    <input type="checkbox" v-model="telegramForm.tg_notify_enabled" class="rounded text-blue-600 w-4 h-4" />
                  </label>

                  <label class="flex items-center justify-between p-3.5 rounded-xl bg-white border border-slate-200 cursor-pointer">
                    <div>
                      <span class="font-bold text-slate-800 block">Notifikasi System Log</span>
                      <p class="text-[10px] text-slate-400">Kirim alert saat terjadi error/warning sistem</p>
                    </div>
                    <input type="checkbox" v-model="telegramForm.tg_log_notify_enabled" class="rounded text-blue-600 w-4 h-4" />
                  </label>
                </div>
              </div>

              <!-- Bot Token & Chat ID -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Telegram Bot Token:</label>
                  <input
                    v-model="telegramForm.tg_bot_token"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-mono text-xs"
                    placeholder="123456789:ABCdefGhI..."
                  />
                </div>

                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Chat ID Penerima:</label>
                  <input
                    v-model="telegramForm.tg_chat_id"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-mono text-xs"
                    placeholder="-100123456789 (bisa pisah koma)"
                  />
                </div>
              </div>

              <!-- Report Schedule & Checkboxes -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Jam Pengiriman Rekap Harian:</label>
                  <input
                    v-model="telegramForm.tg_report_time"
                    type="time"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-bold"
                  />
                </div>

                <div class="space-y-2">
                  <label class="font-bold text-slate-700">Data yang Dilaporkan:</label>
                  <div class="flex items-center gap-4 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" value="leads" v-model="telegramForm.tg_report_config" class="rounded text-blue-600" />
                      <span>Data Leads & WA Click</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" value="traffic" v-model="telegramForm.tg_report_config" class="rounded text-blue-600" />
                      <span>Analitik Traffic Pengunjung</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Test Send Telegram -->
              <div class="pt-1">
                <button
                  type="button"
                  @click="sendTelegramTest"
                  :disabled="testingTelegram"
                  class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-all disabled:opacity-50"
                >
                  <RefreshCw v-if="testingTelegram" class="w-4 h-4 animate-spin" />
                  <Send v-else class="w-4 h-4 text-blue-600" />
                  <span>{{ testingTelegram ? 'Mengirim Pesan...' : 'Kirim Pesan Tes ke Telegram' }}</span>
                </button>
              </div>

              <hr class="border-slate-100" />

              <!-- Telegram Webhook Registration -->
              <div class="p-5 rounded-3xl border border-sky-100 bg-sky-50/40 space-y-4">
                <div>
                  <h4 class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                    <Zap class="w-4 h-4 text-sky-600" />
                    <span>Setup Webhook Interaktif Telegram (Respons 2-Arah):</span>
                  </h4>
                  <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                    Daftarkan webhook agar bot Telegram dapat menerima perintah pengguna seperti <code>/report</code> atau <code>/start</code>.
                  </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="space-y-1.5">
                    <label class="font-bold text-slate-700">URL Website Publik (Site URL):</label>
                    <input
                      v-model="telegramForm.tg_site_url"
                      type="text"
                      class="w-full px-4 py-2.5 rounded-2xl bg-white border border-slate-200 text-slate-800 font-mono text-xs"
                      placeholder="https://arnodclean.com"
                    />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-slate-700">Secret Token Webhook:</label>
                    <input
                      v-model="telegramForm.tg_webhook_token"
                      type="text"
                      class="w-full px-4 py-2.5 rounded-2xl bg-white border border-slate-200 text-slate-800 font-mono text-xs"
                      placeholder="Token rahasia..."
                    />
                  </div>
                </div>

                <div class="pt-1">
                  <button
                    type="button"
                    @click="registerTelegramWebhook"
                    :disabled="settingWebhook"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow-xs transition-all disabled:opacity-50"
                  >
                    <RefreshCw v-if="settingWebhook" class="w-3.5 h-3.5 animate-spin" />
                    <Globe v-else class="w-3.5 h-3.5" />
                    <span>{{ settingWebhook ? 'Mendaftarkan Webhook...' : 'Daftarkan Webhook Telegram Sekarang' }}</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- SHEET 7: SYSTEM & GLOBAL SEO -->
          <div v-if="activeTab === 'system'" class="space-y-6 animate-fadeIn">
            <div>
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center font-bold">
                  <Globe class="w-4 h-4" />
                </div>
                <div>
                  <h2 class="text-lg font-bold text-slate-900">Sistem & Global SEO Metadata</h2>
                  <p class="text-xs text-slate-500">Konfigurasi meta title dan description default Google serta jadwal pembersihan retensi log.</p>
                </div>
              </div>
            </div>

            <div class="space-y-5 text-xs">
              <!-- SEO Fields -->
              <div class="space-y-4">
                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Site Title Default (Meta Title):</label>
                  <input
                    v-model="systemForm.site_meta_title"
                    type="text"
                    class="w-full px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-semibold"
                    placeholder="Arno D Clean - Jasa Cuci Kasur & Sofa Tangerang"
                  />
                  <p class="text-[11px] text-slate-400">Judul website default saat halaman tidak memiliki meta title khusus.</p>
                </div>

                <div class="space-y-1.5">
                  <label class="font-bold text-slate-700">Meta Description Default:</label>
                  <textarea
                    v-model="systemForm.site_meta_description"
                    rows="3"
                    class="w-full p-4 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 leading-relaxed"
                    placeholder="Jasa cuci sofa, kasur, dan karpet profesional di Tangerang..."
                  ></textarea>
                  <p class="text-[11px] text-slate-400">Deskripsi yang muncul di hasil pencarian Google SERP (panjang ideal 150-160 karakter).</p>
                </div>
              </div>

              <hr class="border-slate-100" />

              <!-- Database Maintenance -->
              <div class="space-y-3">
                <div>
                  <h3 class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                    <Sliders class="w-4 h-4 text-slate-600" />
                    <span>Pemeliharaan Basis Data (Database Retention):</span>
                  </h3>
                  <p class="text-[11px] text-slate-500 mt-0.5">
                    Hapus log kunjungan dan aktivitas yang lebih tua dari batas ini agar ukuran database tetap optimal.
                  </p>
                </div>

                <div class="space-y-1.5 max-w-xs">
                  <label class="font-bold text-slate-700">Masa Retensi Log:</label>
                  <div class="flex items-center gap-2">
                    <input
                      v-model.number="systemForm.log_retention_days"
                      type="number"
                      min="7"
                      class="w-32 px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-800 font-bold"
                    />
                    <span class="text-slate-500 font-semibold">Hari</span>
                  </div>
                  <p class="text-[11px] text-slate-400">Minimal 7 hari masa retensi.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Save Action Bar inside Sheet -->
          <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[11px] text-slate-400">
              Perubahan pada lembar ini akan tersimpan ke database ADC secara terpusat.
            </span>
            <button
              @click="saveCurrentTab"
              :disabled="saving"
              class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs font-semibold shadow-md shadow-blue-500/20 active:scale-95 transition-all disabled:opacity-50"
            >
              <Save class="w-4 h-4" />
              <span>{{ saving ? 'Menyimpan...' : 'Simpan Lembar Ini' }}</span>
            </button>
          </div>

        </div>
      </main>
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

.shadow-subtle {
  box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04), 0 2px 6px -1px rgba(0, 0, 0, 0.02);
}

.shadow-xs {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
</style>
