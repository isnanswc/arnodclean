<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { apiClient } from '@/api/client'
import { getImageUrl } from '@/utils/image'
import {
  Menu,
  Globe,
  Bell,
  LogOut,
  User,
  Shield,
  ChevronDown,
  Activity,
  Users,
  RefreshCw,
  Radio,
  Clock
} from 'lucide-vue-next'

const emit = defineEmits<{
  (e: 'toggleMobile'): void
}>()

const router = useRouter()
const authStore = useAuthStore()
const isProfileMenuOpen = ref(false)
const isRealtimeMenuOpen = ref(false)
const isPolling = ref(false)

interface RealtimeStatus {
  active_now: number
  active_human: number
  active_bot: number
  today_human: number
  today_bot: number
  last_visitor: {
    ip_address: string
    city: string
    country: string
    device: string
    is_bot: number
    created_at: string
  } | null
  server_time: string
}

const realtime = ref<RealtimeStatus>({
  active_now: 0,
  active_human: 0,
  active_bot: 0,
  today_human: 0,
  today_bot: 0,
  last_visitor: null,
  server_time: ''
})

let realtimeTimer: any = null

async function fetchRealtimeStatus() {
  try {
    isPolling.value = true
    const res = await apiClient.get('/v2/data.php?type=realtime_status')
    if (res.data?.status === 'success' && res.data?.data) {
      realtime.value = res.data.data
    }
  } catch (err) {
    // Fail silently in background
  } finally {
    isPolling.value = false
  }
}

onMounted(() => {
  fetchRealtimeStatus()
  // Poll every 20 seconds for actual realtime data
  realtimeTimer = setInterval(fetchRealtimeStatus, 20000)
  document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
  if (realtimeTimer) clearInterval(realtimeTimer)
  document.removeEventListener('click', handleOutsideClick)
})

function handleOutsideClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.realtime-dropdown-container')) {
    isRealtimeMenuOpen.value = false
  }
  if (!target.closest('.profile-dropdown-container')) {
    isProfileMenuOpen.value = false
  }
}

const publicWebUrl = computed(() => {
  if (typeof window !== 'undefined') {
    const path = window.location.pathname
    const match = path.match(/^(.*?)\/admin-v2(?:\/.*)?$/)
    const prefix = match ? match[1] : ''
    return prefix ? `${prefix}/` : '/'
  }
  return '/'
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between">
    <!-- Left Section -->
    <div class="flex items-center gap-3">
      <button
        @click="emit('toggleMobile')"
        class="lg:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors"
      >
        <Menu class="w-5 h-5" />
      </button>

      <!-- Mobile Brand Display -->
      <span class="lg:hidden font-bold text-slate-800 text-sm tracking-tight">Arno D Clean</span>

      <!-- REALTIME ACTIVE VISITORS STATUS BAR PILL -->
      <div class="relative realtime-dropdown-container">
        <button
          @click.stop="isRealtimeMenuOpen = !isRealtimeMenuOpen"
          type="button"
          class="flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold transition-all select-none shadow-xs"
          :class="realtime.active_now > 0 
            ? 'bg-emerald-50/90 border-emerald-200 text-emerald-800 hover:bg-emerald-100/90' 
            : 'bg-slate-50 border-slate-200/90 text-slate-600 hover:bg-slate-100'"
          title="Klik untuk detail monitoring pengunjung realtime"
        >
          <!-- Pulsing dot indicator -->
          <span class="relative flex h-2.5 w-2.5">
            <span
              v-if="realtime.active_now > 0"
              class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"
            ></span>
            <span
              class="relative inline-flex rounded-full h-2.5 w-2.5"
              :class="realtime.active_now > 0 ? 'bg-emerald-500' : 'bg-slate-400'"
            ></span>
          </span>

          <span class="hidden sm:inline">Pengunjung Aktif:</span>
          <span class="font-bold text-slate-900">{{ realtime.active_now }}</span>
          
          <span
            class="text-[10px] font-bold px-1.5 py-0.2 rounded-md hidden md:inline"
            :class="realtime.active_now > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
          >
            Realtime
          </span>

          <ChevronDown class="w-3.5 h-3.5 text-slate-400 ml-0.5" />
        </button>

        <!-- Realtime Dropdown Popover Card -->
        <div
          v-if="isRealtimeMenuOpen"
          class="absolute left-0 mt-2 w-80 bg-white rounded-2xl shadow-elevated border border-slate-200/80 p-4 z-50 animate-fadeIn"
          @click.stop
        >
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-900">
              <Radio class="w-4 h-4 text-emerald-500 animate-pulse" />
              <span>Monitoring Pengunjung Realtime</span>
            </div>
            <button
              @click="fetchRealtimeStatus"
              :disabled="isPolling"
              class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
              title="Perbarui Data"
            >
              <RefreshCw class="w-3.5 h-3.5" :class="isPolling ? 'animate-spin text-blue-600' : ''" />
            </button>
          </div>

          <div class="py-3 space-y-2.5 text-xs">
            <!-- Active Now -->
            <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-between">
              <div>
                <div class="text-[11px] font-medium text-emerald-800">Aktif Sekarang (5 Menit Terakhir)</div>
                <div class="text-xs text-emerald-600">Terbuka di halaman web</div>
              </div>
              <div class="text-lg font-black text-emerald-700">
                {{ realtime.active_now }} <span class="text-xs font-normal">Orang</span>
              </div>
            </div>

            <!-- Breakdown -->
            <div class="grid grid-cols-2 gap-2 text-[11px]">
              <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-slate-400 block">Manusia Aktif</span>
                <span class="font-bold text-slate-800 text-sm">{{ realtime.active_human }}</span>
              </div>
              <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                <span class="text-slate-400 block">Crawler / Bot</span>
                <span class="font-bold text-slate-800 text-sm">{{ realtime.active_bot }}</span>
              </div>
            </div>

            <!-- Today Human Visits -->
            <div class="flex items-center justify-between px-1 text-slate-600">
              <span>Total Kunjungan Hari Ini:</span>
              <span class="font-bold text-blue-600">{{ realtime.today_human }} Manusia</span>
            </div>

            <!-- Last Visitor Info -->
            <div v-if="realtime.last_visitor" class="pt-2.5 border-t border-slate-100 text-[11px] space-y-1">
              <div class="font-semibold text-slate-700 flex items-center gap-1.5">
                <Clock class="w-3.5 h-3.5 text-slate-400" />
                <span>Kunjungan Terakhir:</span>
              </div>
              <div class="text-slate-600 pl-5">
                <strong class="text-slate-800">{{ realtime.last_visitor.city || 'Unknown' }}, {{ realtime.last_visitor.country || 'ID' }}</strong>
                <span class="text-slate-400"> &bull; {{ realtime.last_visitor.device || 'Perangkat' }}</span>
              </div>
              <div class="text-[10px] text-slate-400 pl-5">
                {{ realtime.last_visitor.created_at }}
              </div>
            </div>
          </div>

          <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400">
            <span>Server: {{ realtime.server_time || 'Aktual' }}</span>
            <button
              @click="isRealtimeMenuOpen = false"
              class="text-blue-600 hover:underline font-semibold"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Section -->
    <div class="flex items-center gap-2 sm:gap-4">
      <!-- Public Website Link -->
      <a
        :href="publicWebUrl"
        target="_blank"
        class="p-2 sm:px-3 sm:py-1.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-blue-600 hover:bg-blue-50/70 border border-slate-200/80 transition-all flex items-center gap-1.5"
        title="Lihat Website Publik"
      >
        <Globe class="w-4 h-4 text-blue-600" />
        <span class="hidden sm:inline">Buka Web Publik</span>
      </a>

      <!-- Profile Dropdown -->
      <div class="relative profile-dropdown-container">
        <button
          @click.stop="isProfileMenuOpen = !isProfileMenuOpen"
          class="flex items-center gap-2.5 p-1 sm:px-2.5 sm:py-1.5 rounded-xl hover:bg-slate-50 transition-colors text-left"
        >
          <img
            :src="getImageUrl(authStore.user?.avatar) || `https://ui-avatars.com/api/?name=${encodeURIComponent(authStore.user?.username || 'Admin')}&background=2563eb&color=fff`"
            alt="Avatar"
            class="w-8 h-8 rounded-full border border-blue-100 shadow-sm object-cover"
            @error="(e: any) => { e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(authStore.user?.username || 'Admin')}&background=2563eb&color=fff`; }"
          />
          <div class="hidden sm:block">
            <p class="text-xs font-bold text-slate-800 leading-none">
              {{ authStore.user?.username || 'Admin' }}
            </p>
            <p class="text-[10px] text-blue-600 font-medium capitalize mt-0.5">
              {{ authStore.user?.role || 'Administrator' }}
            </p>
          </div>
          <ChevronDown class="w-3.5 h-3.5 text-slate-400 hidden sm:block" />
        </button>

        <!-- Dropdown Menu -->
        <div
          v-if="isProfileMenuOpen"
          class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-elevated border border-slate-100 py-1.5 z-50 animate-fadeIn"
          @click.stop
        >
          <div class="px-4 py-2 border-b border-slate-100 sm:hidden">
            <p class="text-xs font-bold text-slate-800">{{ authStore.user?.username }}</p>
            <p class="text-[10px] text-slate-500">{{ authStore.user?.email }}</p>
          </div>
          <router-link
            to="/users"
            class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-600 transition-colors"
          >
            <User class="w-3.5 h-3.5" />
            <span>Profil Pengguna</span>
          </router-link>
          <router-link
            to="/settings"
            class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-blue-50/70 hover:text-blue-600 transition-colors"
          >
            <Shield class="w-3.5 h-3.5" />
            <span>Pengaturan Akun</span>
          </router-link>
          <div class="border-t border-slate-100 my-1"></div>
          <button
            @click="handleLogout"
            class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-red-600 hover:bg-red-50 transition-colors text-left"
          >
            <LogOut class="w-3.5 h-3.5" />
            <span>Keluar (Sign out)</span>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
