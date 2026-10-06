import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import LoginView from '@/views/auth/LoginView.vue'
import AdminLayout from '@/components/layout/AdminLayout.vue'

import DashboardView from '@/views/DashboardView.vue'
import ReportingView from '@/views/ReportingView.vue'
import ServicesView from '@/views/ServicesView.vue'
import BenefitsView from '@/views/BenefitsView.vue'
import TestimonialsView from '@/views/TestimonialsView.vue'
import LocationsView from '@/views/LocationsView.vue'
import ArticlesView from '@/views/ArticlesView.vue'
import TagsView from '@/views/TagsView.vue'
import AutoContentView from '@/views/AutoContentView.vue'
import LeadsView from '@/views/LeadsView.vue'
import UsersView from '@/views/UsersView.vue'
import ActivityView from '@/views/ActivityView.vue'
import SettingsView from '@/views/SettingsView.vue'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/',
    component: AdminLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/dashboard',
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: DashboardView,
      },
      {
        path: 'reporting',
        name: 'Reporting',
        component: ReportingView,
      },
      {
        path: 'services',
        name: 'Services',
        component: ServicesView,
      },
      {
        path: 'benefits',
        name: 'Benefits',
        component: BenefitsView,
      },
      {
        path: 'testimonials',
        name: 'Testimonials',
        component: TestimonialsView,
      },
      {
        path: 'locations',
        name: 'Locations',
        component: LocationsView,
      },
      {
        path: 'articles',
        name: 'Articles',
        component: ArticlesView,
      },
      {
        path: 'tags',
        name: 'Tags',
        component: TagsView,
      },
      {
        path: 'auto-content',
        name: 'AutoContent',
        component: AutoContentView,
      },
      {
        path: 'leads',
        name: 'Leads',
        component: LeadsView,
      },
      {
        path: 'users',
        name: 'Users',
        component: UsersView,
      },
      {
        path: 'activity',
        name: 'Activity',
        component: ActivityView,
      },
      {
        path: 'settings',
        name: 'Settings',
        component: SettingsView,
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (authStore.isInitializing) {
    await authStore.checkAuth()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ path: '/login', query: { redirect: to.fullPath } })
  } else if (to.meta.guestOnly && authStore.isAuthenticated) {
    next('/dashboard')
  } else {
    next()
  }
})
