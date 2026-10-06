<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'
import {
  LayoutDashboard,
  TrendingUp,
  Sparkles,
  Users,
  Menu,
  Settings
} from 'lucide-vue-next'

const route = useRoute()

const isCollapsed = ref(false)
const isMobileOpen = ref(false)

function toggleCollapse() {
  isCollapsed.value = !isCollapsed.value
}

function toggleMobile() {
  isMobileOpen.value = !isMobileOpen.value
}

function closeMobile() {
  isMobileOpen.value = false
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 flex">
    <!-- Sidebar (Drawer on mobile, collapsible on desktop) -->
    <Sidebar
      :is-collapsed="isCollapsed"
      :is-mobile-open="isMobileOpen"
      @toggle-collapse="toggleCollapse"
      @close-mobile="closeMobile"
    />

    <!-- Main Container -->
    <div
      :class="[
        'flex-1 flex flex-col min-w-0 transition-all duration-300',
        isCollapsed ? 'lg:pl-20' : 'lg:pl-64'
      ]"
    >
      <Topbar @toggle-mobile="toggleMobile" />

      <!-- Page Content (pb-24 on mobile ensures bottom nav never covers content) -->
      <main class="flex-1 p-3.5 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto pb-24 lg:pb-8">
        <router-view />
      </main>

      <!-- MOBILE MINIMALIST BOTTOM NAVIGATION BAR (lg:hidden) -->
      <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-xl border-t border-slate-200/80 px-2 pt-1.5 safe-bottom flex items-center justify-around shadow-lg shadow-slate-900/5">
        <router-link
          to="/dashboard"
          class="flex flex-col items-center justify-center py-1 px-3 rounded-2xl transition-all"
          :class="route.path === '/dashboard' ? 'text-blue-600 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'"
        >
          <div class="relative">
            <LayoutDashboard class="w-5 h-5" />
            <span v-if="route.path === '/dashboard'" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
          </div>
          <span class="text-[10px] mt-1">Dashboard</span>
        </router-link>

        <router-link
          to="/reporting"
          class="flex flex-col items-center justify-center py-1 px-3 rounded-2xl transition-all"
          :class="route.path === '/reporting' ? 'text-blue-600 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'"
        >
          <div class="relative">
            <TrendingUp class="w-5 h-5" />
            <span v-if="route.path === '/reporting'" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
          </div>
          <span class="text-[10px] mt-1">Analitik</span>
        </router-link>

        <router-link
          to="/services"
          class="flex flex-col items-center justify-center py-1 px-3 rounded-2xl transition-all"
          :class="route.path === '/services' ? 'text-blue-600 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'"
        >
          <div class="relative">
            <Sparkles class="w-5 h-5" />
            <span v-if="route.path === '/services'" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
          </div>
          <span class="text-[10px] mt-1">Layanan</span>
        </router-link>

        <router-link
          to="/leads"
          class="flex flex-col items-center justify-center py-1 px-3 rounded-2xl transition-all"
          :class="route.path === '/leads' ? 'text-blue-600 font-bold' : 'text-slate-400 hover:text-slate-600 font-medium'"
        >
          <div class="relative">
            <Users class="w-5 h-5" />
            <span v-if="route.path === '/leads'" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-blue-600"></span>
          </div>
          <span class="text-[10px] mt-1">Leads</span>
        </router-link>

        <button
          type="button"
          @click="toggleMobile"
          class="flex flex-col items-center justify-center py-1 px-3 rounded-2xl text-slate-400 hover:text-blue-600 transition-all font-medium"
        >
          <Menu class="w-5 h-5" />
          <span class="text-[10px] mt-1">Menu</span>
        </button>
      </nav>
    </div>
  </div>
</template>
