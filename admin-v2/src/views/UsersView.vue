<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { getImageUrl } from '@/utils/image'
import {
  UserCheck,
  Shield,
  UserPlus,
  Edit,
  Trash2,
  Lock,
  Mail,
  User,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertTriangle,
  X,
  RefreshCw,
  Search,
  Clock
} from 'lucide-vue-next'

interface UserItem {
  id: number
  username: string
  email: string
  role: 'admin' | 'editor'
  avatar: string | null
  created_at: string
  last_login?: string | null
}

const loading = ref(false)
const users = ref<UserItem[]>([])
const currentUserId = ref<number>(0)
const currentUserRole = ref<string>('admin')
const searchQuery = ref('')

// Form Modal State
const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const showPassword = ref(false)

const form = ref({
  id: 0,
  username: '',
  email: '',
  password: '',
  role: 'editor' as 'admin' | 'editor'
})

// Delete Modal State
const deleteTarget = ref<UserItem | null>(null)
const deleting = ref(false)

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

async function fetchUsers() {
  loading.value = true
  try {
    const res = await axios.get('/api/v2/data.php?type=users', { withCredentials: true })
    if (res.data.status === 'success') {
      users.value = res.data.data || []
      currentUserId.value = res.data.current_user_id || 0
      currentUserRole.value = res.data.current_user_role || 'admin'
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal memuat daftar user', 'error')
  } finally {
    loading.value = false
  }
}

const filteredUsers = computed(() => {
  if (!searchQuery.value.trim()) return users.value
  const q = searchQuery.value.toLowerCase()
  return users.value.filter(
    u => u.username.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.role.toLowerCase().includes(q)
  )
})

const adminCount = computed(() => users.value.filter(u => u.role === 'admin').length)
const editorCount = computed(() => users.value.filter(u => u.role === 'editor').length)

function openAddModal() {
  isEditing.value = false
  form.value = {
    id: 0,
    username: '',
    email: '',
    password: '',
    role: 'editor'
  }
  showPassword.value = false
  showModal.value = true
}

function openEditModal(u: UserItem) {
  isEditing.value = true
  form.value = {
    id: u.id,
    username: u.username,
    email: u.email,
    password: '',
    role: u.role
  }
  showPassword.value = false
  showModal.value = true
}

async function handleSubmit() {
  saving.value = true
  try {
    if (isEditing.value) {
      const res = await axios.post(
        '/api/v2/data.php?type=users',
        {
          action: 'edit',
          id: form.value.id,
          username: form.value.username,
          role: form.value.role,
          password: form.value.password || undefined
        },
        { withCredentials: true }
      )
      if (res.data.status === 'success') {
        showToast('Data user berhasil diperbarui', 'success')
        showModal.value = false
        fetchUsers()
      }
    } else {
      const res = await axios.post(
        '/api/v2/data.php?type=users',
        {
          action: 'add',
          username: form.value.username,
          email: form.value.email,
          password: form.value.password,
          role: form.value.role
        },
        { withCredentials: true }
      )
      if (res.data.status === 'success') {
        showToast('User baru berhasil ditambahkan', 'success')
        showModal.value = false
        fetchUsers()
      }
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal menyimpan user', 'error')
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    const res = await axios.delete(`/api/v2/data.php?type=users&id=${deleteTarget.value.id}`, {
      withCredentials: true
    })
    if (res.data.status === 'success') {
      showToast('User berhasil dihapus', 'success')
      deleteTarget.value = null
      fetchUsers()
    }
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal menghapus user', 'error')
  } finally {
    deleting.value = false
  }
}

function formatDate(str?: string | null) {
  if (!str) return 'Belum pernah login'
  try {
    const d = new Date(str)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch {
    return str
  }
}

onMounted(() => {
  fetchUsers()
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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
          <span>Manajemen User</span>
          <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
            Akses Admin
          </span>
        </h1>
        <p class="text-slate-500 text-xs sm:text-sm mt-0.5">
          Kelola hak akses administrator dan editor untuk kontrol panel Arno D Clean.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          @click="fetchUsers"
          :disabled="loading"
          class="p-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 shadow-sm transition-all"
          title="Refresh Data"
        >
          <RefreshCw class="w-4 h-4 text-slate-500" :class="loading ? 'animate-spin' : ''" />
        </button>

        <button
          v-if="currentUserRole === 'admin'"
          @click="openAddModal"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs sm:text-sm font-semibold shadow-md shadow-blue-500/20 active:scale-95 transition-all"
        >
          <UserPlus class="w-4 h-4" />
          <span>Tambah User Baru</span>
        </button>
      </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total User</p>
          <h3 class="text-2xl font-bold text-slate-900 mt-1">{{ users.length }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Akun terdaftar di sistem</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
          <UserCheck class="w-6 h-6" />
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Administrator</p>
          <h3 class="text-2xl font-bold text-indigo-600 mt-1">{{ adminCount }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Hak akses penuh sistem</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
          <Shield class="w-6 h-6" />
        </div>
      </div>

      <div class="p-5 rounded-2xl bg-white border border-slate-100 shadow-subtle flex items-center justify-between">
        <div>
          <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Editor</p>
          <h3 class="text-2xl font-bold text-slate-700 mt-1">{{ editorCount }}</h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Pengelola konten blog & layanan</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center">
          <User class="w-6 h-6" />
        </div>
      </div>
    </div>

    <!-- User Table Card -->
    <div class="rounded-3xl bg-white border border-slate-100 shadow-subtle overflow-hidden">
      <!-- Toolbar -->
      <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="relative w-full sm:w-72">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari nama, email, role..."
            class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
          />
        </div>
        <span class="text-xs text-slate-400 font-medium">Menampilkan {{ filteredUsers.length }} user</span>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-100">
              <th class="py-3.5 px-6">User</th>
              <th class="py-3.5 px-4">Role</th>
              <th class="py-3.5 px-4">Login Terakhir</th>
              <th class="py-3.5 px-4">Terdaftar</th>
              <th class="py-3.5 px-6 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="loading">
              <td colspan="5" class="text-center py-12 text-slate-400">
                <RefreshCw class="w-6 h-6 animate-spin mx-auto text-blue-500 mb-2" />
                <span>Memuat data pengguna...</span>
              </td>
            </tr>

            <tr v-else-if="filteredUsers.length === 0">
              <td colspan="5" class="text-center py-12 text-slate-400">
                <UserCheck class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                <p class="font-medium text-slate-600">Tidak ada user ditemukan</p>
              </td>
            </tr>

            <tr
              v-else
              v-for="u in filteredUsers"
              :key="u.id"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <!-- User Profile -->
              <td class="py-3.5 px-6">
                <div class="flex items-center gap-3">
                  <img
                    :src="getImageUrl(u.avatar) || `https://ui-avatars.com/api/?name=${encodeURIComponent(u.username)}&background=2563eb&color=fff`"
                    alt="avatar"
                    class="w-10 h-10 rounded-2xl object-cover border border-slate-200/60"
                    @error="(e: any) => { e.target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(u.username)}&background=2563eb&color=fff`; }"
                  />
                  <div>
                    <div class="flex items-center gap-1.5">
                      <p class="font-bold text-slate-900">{{ u.username }}</p>
                      <span
                        v-if="u.id === currentUserId"
                        class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-100 text-blue-700"
                      >
                        Anda
                      </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5">{{ u.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Role -->
              <td class="py-3.5 px-4">
                <span
                  v-if="u.role === 'admin'"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200"
                >
                  <Shield class="w-3 h-3 text-indigo-600" />
                  <span>Administrator</span>
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
                >
                  <User class="w-3 h-3 text-slate-500" />
                  <span>Editor</span>
                </span>
              </td>

              <!-- Last Login -->
              <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap">
                <div class="flex items-center gap-1.5 text-[11px]">
                  <Clock class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ formatDate(u.last_login) }}</span>
                </div>
              </td>

              <!-- Created At -->
              <td class="py-3.5 px-4 text-slate-400 text-[11px] whitespace-nowrap">
                {{ formatDate(u.created_at) }}
              </td>

              <!-- Actions -->
              <td class="py-3.5 px-6 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button
                    v-if="currentUserRole === 'admin'"
                    @click="openEditModal(u)"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                    title="Edit User"
                  >
                    <Edit class="w-4 h-4" />
                  </button>

                  <button
                    v-if="currentUserRole === 'admin'"
                    @click="deleteTarget = u"
                    :disabled="u.id === currentUserId"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-slate-400"
                    :title="u.id === currentUserId ? 'Tidak dapat menghapus akun sendiri' : 'Hapus User'"
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

    <!-- MODAL FORM USER (ADD & EDIT) -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn overflow-y-auto"
    >
      <div class="w-full max-w-md bg-white rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-100 p-4 sm:p-7 space-y-4 sm:space-y-5 my-auto max-h-[90vh] overflow-y-auto animate-scaleUp">
        <div class="flex items-start sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <UserCheck class="w-5 h-5" />
            </div>
            <div class="min-w-0">
              <h3 class="text-base font-bold text-slate-900 truncate sm:whitespace-normal">
                {{ isEditing ? 'Edit Data User' : 'Tambah User Baru' }}
              </h3>
              <p class="text-[11px] text-slate-400 line-clamp-1">Atur kredensial dan hak akses akun</p>
            </div>
          </div>
          <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl shrink-0">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4 text-xs">
          <!-- Username -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700">Username:</label>
            <div class="relative">
              <User class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="form.username"
                type="text"
                required
                placeholder="misal: admin_tangerang"
                class="w-full pl-9 pr-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
              />
            </div>
          </div>

          <!-- Email (read-only on edit) -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700">Email:</label>
            <div class="relative">
              <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="form.email"
                type="email"
                :disabled="isEditing"
                required
                placeholder="user@example.com"
                class="w-full pl-9 pr-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 disabled:opacity-60"
              />
            </div>
          </div>

          <!-- Role -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700">Role Hak Akses:</label>
            <select
              v-model="form.role"
              class="w-full px-3 py-2.5 rounded-xl bg-slate-50 border border-slate-200 font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="editor">Editor (Kelola Layanan, Artikel, Testimoni)</option>
              <option value="admin">Administrator (Akses Penuh Semua Modul & User)</option>
            </select>
          </div>

          <!-- Password -->
          <div class="space-y-1">
            <label class="font-bold text-slate-700">
              Password
              <span v-if="isEditing" class="text-slate-400 font-normal">(Kosongkan jika tidak ingin diubah)</span>
              <span v-else class="text-rose-500">*</span>:
            </label>
            <div class="relative">
              <Lock class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                :required="!isEditing"
                placeholder="Minimal 6 karakter"
                class="w-full pl-9 pr-9 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              >
                <EyeOff v-if="showPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 sm:gap-3 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showModal = false"
              class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all text-center"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all disabled:opacity-50 text-center"
            >
              {{ saving ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Tambah User' }}
            </button>
          </div>
        </form>
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
          <h3 class="text-base font-bold text-slate-900">Hapus Akun User?</h3>
          <p class="text-xs text-slate-500 mt-1 break-words">
            Akun <strong class="text-slate-800">{{ deleteTarget.username }}</strong> ({{ deleteTarget.email }}) akan dihapus secara permanen.
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
            @click="confirmDelete"
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
