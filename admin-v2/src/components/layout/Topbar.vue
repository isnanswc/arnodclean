<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getImageUrl } from '@/utils/image'
import {
  Menu,
  Globe,
  Bell,
  LogOut,
  User,
  Shield,
  ChevronDown
} from 'lucide-vue-next'

const emit = defineEmits<{
  (e: 'toggleMobile'): void
}>()

const router = useRouter()
const authStore = useAuthStore()
const isProfileMenuOpen = ref(false)

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

      <!-- System Pill -->
      <div class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-100/80 text-blue-700 text-xs font-semibold">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <span>MySQL: adc (Live)</span>
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
      <div class="relative">
        <button
          @click="isProfileMenuOpen = !isProfileMenuOpen"
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
          @click="isProfileMenuOpen = false"
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
