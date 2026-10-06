<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  Lock,
  Mail,
  Eye,
  EyeOff,
  ArrowRight,
  ShieldCheck,
  AlertCircle,
  Loader2,
  Armchair,
  BedDouble,
  Droplets,
  ShowerHead,
  Brush,
  Blinds,
  Bot,
  MessageCircle,
  Activity,
  Sparkles
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

const identity = ref('')
const password = ref('')
const showPassword = ref(false)
const rememberMe = ref(true)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Aesthetic floating cleaning icons flowing from left to right
const floatingIcons = [
  { icon: Armchair, top: '5%', size: 28, duration: 28, delay: -4, sway: -16, opacity: 0.22, color: 'text-blue-500' },
  { icon: BedDouble, top: '13%', size: 34, duration: 34, delay: -16, sway: 22, opacity: 0.24, color: 'text-indigo-500' },
  { icon: Sparkles, top: '21%', size: 20, duration: 24, delay: -9, sway: -14, opacity: 0.32, color: 'text-sky-400' },
  { icon: Droplets, top: '30%', size: 26, duration: 32, delay: -22, sway: 18, opacity: 0.28, color: 'text-cyan-500' },
  { icon: ShowerHead, top: '39%', size: 30, duration: 36, delay: -12, sway: -20, opacity: 0.20, color: 'text-blue-600' },
  { icon: Brush, top: '48%', size: 26, duration: 30, delay: -27, sway: 16, opacity: 0.24, color: 'text-indigo-600' },
  { icon: Blinds, top: '56%', size: 32, duration: 38, delay: -7, sway: -18, opacity: 0.22, color: 'text-sky-600' },
  { icon: Sparkles, top: '64%', size: 22, duration: 26, delay: -19, sway: 16, opacity: 0.30, color: 'text-blue-400' },
  { icon: Armchair, top: '72%', size: 30, duration: 33, delay: -31, sway: -15, opacity: 0.22, color: 'text-indigo-500' },
  { icon: BedDouble, top: '80%', size: 32, duration: 37, delay: -14, sway: 20, opacity: 0.20, color: 'text-blue-500' },
  { icon: Droplets, top: '88%', size: 26, duration: 29, delay: -24, sway: -16, opacity: 0.28, color: 'text-cyan-500' },
  { icon: ShowerHead, top: '94%', size: 28, duration: 35, delay: -5, sway: 14, opacity: 0.22, color: 'text-sky-500' },

  // Secondary layer for fluid ambient density
  { icon: Brush, top: '10%', size: 22, duration: 42, delay: -20, sway: 16, opacity: 0.18, color: 'text-indigo-400' },
  { icon: Blinds, top: '26%', size: 26, duration: 40, delay: -33, sway: -20, opacity: 0.18, color: 'text-blue-400' },
  { icon: Sparkles, top: '43%', size: 18, duration: 25, delay: -2, sway: 12, opacity: 0.32, color: 'text-sky-400' },
  { icon: Armchair, top: '59%', size: 24, duration: 39, delay: -17, sway: -15, opacity: 0.20, color: 'text-cyan-600' },
  { icon: BedDouble, top: '74%', size: 28, duration: 44, delay: -29, sway: 18, opacity: 0.18, color: 'text-blue-600' },
  { icon: Droplets, top: '83%', size: 20, duration: 27, delay: -11, sway: -14, opacity: 0.25, color: 'text-indigo-500' },
  { icon: ShowerHead, top: '17%', size: 24, duration: 45, delay: -38, sway: -22, opacity: 0.16, color: 'text-blue-500' }
]

async function handleLogin() {
  if (!identity.value.trim() || !password.value) {
    errorMessage.value = 'Silakan masukkan username/email dan kata sandi.'
    return
  }

  isLoading.value = true
  errorMessage.value = null

  const success = await authStore.login(identity.value.trim(), password.value)
  isLoading.value = false

  if (success) {
    router.push('/dashboard')
  } else {
    errorMessage.value = authStore.error || 'Autentikasi gagal. Silakan periksa kembali kredensial akun Anda.'
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-900 flex items-center justify-center p-3.5 sm:p-6 lg:p-8 relative overflow-x-hidden selection:bg-blue-600 selection:text-white">
    
    <!-- Deep Gradient Background Canvas -->
    <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 pointer-events-none"></div>

    <!-- Soft Ambient Radial Glows -->
    <div class="absolute -top-32 -left-32 w-72 sm:w-[500px] h-72 sm:h-[500px] bg-blue-600/15 rounded-full blur-[90px] sm:blur-[130px] pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-72 sm:w-[600px] h-72 sm:h-[600px] bg-indigo-600/15 rounded-full blur-[100px] sm:blur-[150px] pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/3 w-64 sm:w-[450px] h-64 sm:h-[450px] bg-sky-500/10 rounded-full blur-[80px] sm:blur-[120px] pointer-events-none"></div>

    <!-- FLOATING CLEANING ICONS (Sofa, Kasur, Busa, Sabun, Shower, Sikat, Gorden) Flowing Left -> Right -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
      <div
        v-for="(item, idx) in floatingIcons"
        :key="idx"
        class="floating-icon-item absolute flex items-center justify-center will-change-transform"
        :style="{
          top: item.top,
          left: '0px',
          animationDuration: `${item.duration}s`,
          animationDelay: `${item.delay}s`,
          '--sway-y': `${item.sway}px`,
          '--item-opacity': item.opacity
        }"
      >
        <component
          :is="item.icon"
          :class="item.color"
          :style="{
            width: `${item.size}px`,
            height: `${item.size}px`,
            filter: 'drop-shadow(0 2px 6px rgba(56, 189, 248, 0.15))'
          }"
        />
      </div>
    </div>

    <!-- Main Container -->
    <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 items-center relative z-10 py-4 sm:py-0">
      
      <!-- Left Column: Branding (Responsive: Compact on Mobile, Full on Desktop) -->
      <div class="lg:col-span-6 space-y-4 sm:space-y-6 text-center lg:text-left">
        <!-- Live System Badge -->
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[11px] sm:text-xs font-semibold tracking-wide backdrop-blur-md shadow-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>Sistem Manajemen Operasional & CRM</span>
        </div>

        <div class="space-y-2 sm:space-y-3">
          <h1 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Pusat Kendali Bisnis <br class="hidden sm:inline" />
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-sky-300 to-indigo-300">
              Arno D Clean
            </span>
          </h1>
          <p class="text-slate-400 text-xs sm:text-sm lg:text-base max-w-md mx-auto lg:mx-0 leading-relaxed font-normal">
            Platform komprehensif layanan cuci profesional kasur, sofa, karpet, dan jok mobil dengan analitik real-time dan otomasi pesan.
          </p>
        </div>

        <!-- Value Highlights (Hidden on small mobile to give immediate view of login card) -->
        <div class="hidden sm:grid grid-cols-3 gap-2.5 max-w-lg mx-auto lg:mx-0 text-left pt-1">
          <div class="p-3 rounded-2xl bg-slate-800/50 backdrop-blur-md border border-slate-700/50 space-y-1">
            <div class="w-7 h-7 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
              <Activity class="w-3.5 h-3.5" />
            </div>
            <p class="text-[11px] font-bold text-slate-200">Analitik Live</p>
            <p class="text-[10px] text-slate-400 truncate">Monitoring traffic</p>
          </div>

          <div class="p-3 rounded-2xl bg-slate-800/50 backdrop-blur-md border border-slate-700/50 space-y-1">
            <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
              <MessageCircle class="w-3.5 h-3.5" />
            </div>
            <p class="text-[11px] font-bold text-slate-200">WhatsApp CRM</p>
            <p class="text-[10px] text-slate-400 truncate">Pencatatan lead</p>
          </div>

          <div class="p-3 rounded-2xl bg-slate-800/50 backdrop-blur-md border border-slate-700/50 space-y-1">
            <div class="w-7 h-7 rounded-lg bg-violet-500/10 text-violet-400 flex items-center justify-center">
              <Bot class="w-3.5 h-3.5" />
            </div>
            <p class="text-[11px] font-bold text-slate-200">AI Engine</p>
            <p class="text-[10px] text-slate-400 truncate">Generasi konten</p>
          </div>
        </div>
      </div>

      <!-- Right Column: Production-Ready Login Card -->
      <div class="lg:col-span-6 w-full">
        <div class="bg-white rounded-3xl border border-slate-100 shadow-2xl p-6 sm:p-8 lg:p-10 relative">
          
          <!-- Card Header -->
          <div class="mb-6 sm:mb-8">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Masuk ke Sistem</h2>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">
              Masukkan kredensial akun administrator Anda untuk melanjutkan.
            </p>
          </div>

          <!-- Error Alert -->
          <div
            v-if="errorMessage"
            class="mb-5 p-3.5 sm:p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm flex items-start gap-2.5 animate-fadeIn"
          >
            <AlertCircle class="w-4 h-4 sm:w-5 sm:h-5 shrink-0 text-rose-600 mt-0.5" />
            <span class="flex-1 leading-snug">{{ errorMessage }}</span>
          </div>

          <!-- Login Form -->
          <form @submit.prevent="handleLogin" class="space-y-4 sm:space-y-5">
            <!-- Identity Input -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 tracking-wide">
                Email atau Username
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Mail class="w-4 h-4" />
                </div>
                <input
                  v-model="identity"
                  type="text"
                  placeholder="admin@arnodclean.com atau username"
                  autocomplete="username"
                  required
                  class="w-full pl-10 pr-4 py-3 sm:py-3 rounded-2xl bg-slate-50 border border-slate-200 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition-all font-medium min-h-[46px]"
                />
              </div>
            </div>

            <!-- Password Input -->
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 tracking-wide">
                Kata Sandi
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Lock class="w-4 h-4" />
                </div>
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Masukkan kata sandi Anda"
                  autocomplete="current-password"
                  required
                  class="w-full pl-10 pr-11 py-3 sm:py-3 rounded-2xl bg-slate-50 border border-slate-200 text-base sm:text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition-all font-medium min-h-[46px]"
                />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition-colors p-2"
                  title="Tampilkan / Sembunyikan Kata Sandi"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Remember Me & Portal Switcher -->
            <div class="flex items-center justify-between pt-1">
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input
                  v-model="rememberMe"
                  type="checkbox"
                  class="w-4 h-4 rounded text-blue-600 border-slate-300 focus:ring-blue-500"
                />
                <span class="text-xs text-slate-600 font-medium">Ingat sesi saya</span>
              </label>
              <a
                href="../admin/login.php"
                class="text-xs text-blue-600 hover:text-blue-700 font-semibold hover:underline"
              >
                Portal Klasik &rarr;
              </a>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="isLoading"
              class="w-full py-3.5 px-5 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold text-sm shadow-md shadow-blue-500/25 active:scale-98 transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer min-h-[48px]"
            >
              <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin" />
              <template v-else>
                <span>Masuk ke Dashboard</span>
                <ArrowRight class="w-4 h-4" />
              </template>
            </button>
          </form>

          <!-- Production Clean Footer -->
          <div class="mt-6 sm:mt-8 pt-5 sm:pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-slate-400 text-center sm:text-left">
            <span>&copy; 2026 Arno D Clean. All rights reserved.</span>
            <span class="flex items-center gap-1 text-slate-500 font-medium">
              <ShieldCheck class="w-3.5 h-3.5 text-blue-600" />
              <span>Sesi Terenkripsi SSL</span>
            </span>
          </div>

        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
@keyframes flowAcross {
  0% {
    transform: translateX(-15vw) translateY(0px) rotate(0deg);
    opacity: 0;
  }
  8% {
    opacity: var(--item-opacity, 0.25);
  }
  50% {
    transform: translateX(50vw) translateY(var(--sway-y, -18px)) rotate(180deg);
    opacity: var(--item-opacity, 0.25);
  }
  92% {
    opacity: var(--item-opacity, 0.25);
  }
  100% {
    transform: translateX(115vw) translateY(0px) rotate(360deg);
    opacity: 0;
  }
}

.floating-icon-item {
  animation-name: flowAcross;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.2s ease-out;
}
</style>
