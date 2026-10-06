<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getImageUrl } from '@/utils/image'
import {
  LayoutDashboard,
  TrendingUp,
  Sparkles,
  Star,
  MessageSquareQuote,
  MapPin,
  FileText,
  Tag,
  Bot,
  Users,
  UserCog,
  History,
  Settings,
  LogOut,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  ShieldCheck,
  Globe,
  X
} from 'lucide-vue-next'

const publicWebUrl = computed(() => {
  if (typeof window !== 'undefined') {
    const path = window.location.pathname
    const match = path.match(/^(.*?)\/admin-v2(?:\/.*)?$/)
    const prefix = match ? match[1] : ''
    return prefix ? `${prefix}/` : '/'
  }
  return '/'
})

const props = defineProps<{
  isCollapsed: boolean
  isMobileOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'toggleCollapse'): void
  (e: 'closeMobile'): void
}>()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

interface MenuItem {
  name: string
  path: string
  icon: any
  badge?: string
}

interface MenuSection {
  title?: string
  items: MenuItem[]
}

const menuSections: MenuSection[] = [
  {
    items: [
      { name: 'Dashboard', path: '/dashboard', icon: LayoutDashboard },
      { name: 'Reporting', path: '/reporting', icon: TrendingUp },
    ],
  },
  {
    title: 'Content',
    items: [
      { name: 'Layanan', path: '/services', icon: Sparkles },
      { name: 'Keunggulan', path: '/benefits', icon: Star },
      { name: 'Testimoni', path: '/testimonials', icon: MessageSquareQuote },
      { name: 'Lokasi Jangkauan', path: '/locations', icon: MapPin },
    ],
  },
  {
    title: 'Blog & SEO',
    items: [
      { name: 'Artikel', path: '/articles', icon: FileText },
      { name: 'Tags', path: '/tags', icon: Tag },
      { name: 'Auto Content AI', path: '/auto-content', icon: Bot, badge: 'AI' },
    ],
  },
  {
    title: 'Business',
    items: [
      { name: 'Leads / CRM', path: '/leads', icon: Users },
    ],
  },
  {
    title: 'System',
    items: [
      { name: 'User Manager', path: '/users', icon: UserCog },
      { name: 'Log Aktivitas', path: '/activity', icon: History },
      { name: 'Pengaturan Situs', path: '/settings', icon: Settings },
    ],
  },
]

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div>
    <!-- Mobile Backdrop Overlay -->
    <div
      v-if="isMobileOpen"
      class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 lg:hidden"
      @click="emit('closeMobile')"
    ></div>

    <!-- Sidebar Container -->
    <aside
      :class="[
        'fixed top-0 bottom-0 left-0 z-50 flex flex-col bg-white border-r border-slate-200/90 transition-all duration-300 shadow-2xl lg:shadow-sm rounded-r-3xl lg:rounded-none',
        isCollapsed ? 'w-20' : 'w-72 sm:w-64',
        isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <!-- Header / Logo -->
      <div class="h-16 flex items-center justify-between px-4 border-b border-slate-100 shrink-0">
        <router-link to="/dashboard" class="flex items-center gap-3 overflow-hidden">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-lg shrink-0 shadow-sm shadow-blue-500/20">
            A
          </div>
          <div v-if="!isCollapsed" class="flex flex-col truncate">
            <span class="font-bold text-slate-900 text-sm tracking-tight leading-none">Arno D Clean</span>
            <span class="text-[10px] text-blue-600 font-semibold tracking-wider uppercase mt-1">Admin V2 Modern</span>
          </div>
        </router-link>

        <!-- Mobile close button -->
        <button
          class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
          @click="emit('closeMobile')"
        >
          <X class="w-5 h-5" />
        </button>

        <!-- Desktop collapse button -->
        <button
          v-if="!isMobileOpen"
          class="hidden lg:flex p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          @click="emit('toggleCollapse')"
        >
          <ChevronLeft v-if="!isCollapsed" class="w-4 h-4" />
          <ChevronRight v-else class="w-4 h-4" />
        </button>
      </div>

      <!-- Navigation Menu (Scrollable) -->
      <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        <div v-for="(section, sIndex) in menuSections" :key="sIndex" class="space-y-1">
          <!-- Section Title -->
          <p
            v-if="section.title && !isCollapsed"
            class="px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2"
          >
            {{ section.title }}
          </p>

          <!-- Section Items -->
          <router-link
            v-for="item in section.items"
            :key="item.path"
            :to="item.path"
            @click="emit('closeMobile')"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150 group relative',
              route.path === item.path
                ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25'
                : 'text-slate-600 hover:text-blue-600 hover:bg-blue-50/70'
            ]"
            :title="isCollapsed ? item.name : undefined"
          >
            <component
              :is="item.icon"
              :class="[
                'w-4 h-4 shrink-0 transition-transform duration-150',
                route.path === item.path ? 'text-white' : 'text-slate-400 group-hover:text-blue-600'
              ]"
            />
            
            <span v-if="!isCollapsed" class="truncate flex-1">{{ item.name }}</span>

            <!-- Badge -->
            <span
              v-if="item.badge && !isCollapsed"
              :class="[
                'text-[10px] px-1.5 py-0.5 rounded-full font-bold uppercase tracking-wider',
                route.path === item.path ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-700'
              ]"
            >
              {{ item.badge }}
            </span>
          </router-link>
        </div>

        <!-- Public Website Link & Switch to Admin Old -->
        <div class="pt-3 border-t border-slate-100 space-y-1">
          <a
            :href="publicWebUrl"
            target="_blank"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-blue-600 hover:bg-blue-50/60 transition-colors"
            title="Lihat Website Publik"
          >
            <Globe class="w-4 h-4 shrink-0 text-blue-600" />
            <span v-if="!isCollapsed" class="truncate">Buka Web Publik</span>
          </a>

          <a
            href="../admin/dashboard.php"
            class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-medium text-slate-500 hover:text-blue-600 hover:bg-slate-50 transition-colors"
            title="Buka Admin Lama (PHP)"
          >
            <ExternalLink class="w-4 h-4 shrink-0 text-slate-400" />
            <span v-if="!isCollapsed" class="truncate">Ke Admin Lama (PHP)</span>
          </a>
        </div>
      </nav>

      <!-- User Profile & Logout (Bottom) -->
      <div class="p-3 border-t border-slate-100 bg-slate-50/60 shrink-0">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-2.5 overflow-hidden">
            <img
              :src="getImageUrl(authStore.user?.avatar) || `https://ui-avatars.com/api/?name=${encodeURIComponent(authStore.user?.username || 'Admin')}&background=2563eb&color=fff`"
              alt="Avatar"
              class="w-8 h-8 rounded-full border border-white shadow-sm object-cover shrink-0"
              @error="(e: any) => { e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(authStore.user?.username || 'Admin')}&background=2563eb&color=fff`; }"
            />
            <div v-if="!isCollapsed" class="truncate">
              <p class="text-xs font-bold text-slate-800 truncate leading-none">
                {{ authStore.user?.username || 'Admin' }}
              </p>
              <p class="text-[10px] text-slate-400 truncate mt-1">
                {{ authStore.user?.email || 'admin@arnod-clean.com' }}
              </p>
            </div>
          </div>

          <button
            v-if="!isCollapsed"
            @click="handleLogout"
            class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
            title="Keluar (Logout)"
          >
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>
  </div>
</template>
