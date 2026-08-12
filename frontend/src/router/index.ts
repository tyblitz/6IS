import { createRouter, createWebHistory } from '@ionic/vue-router'
import { RouteRecordRaw } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/auth/LoginView.vue'
import CommunicationsView from '../views/communications/CommunicationsView.vue'
import InventoryView from '../views/inventory/InventoryView.vue'
import EquipmentView from '../views/inventory/EquipmentView.vue'
import JRRSView from '../views/inventory/JRRS.vue'
import AccomplishmentView from '../views/accomplishments/AccomplishmentView.vue'
import AccomplishmentDailyView from '../views/accomplishments/AccomplishmentDailyView.vue'
import AccomplishmentMonthlyView from '../views/accomplishments/AccomplishmentMonthlyView.vue'
import AccomplishmentQuarterlyView from '../views/accomplishments/AccomplishmentQuarterlyView.vue'
import AccomplishmentAnnualView from '../views/accomplishments/AccomplishmentAnnualView.vue'
import AccomplishmentCustomView from '../views/accomplishments/AccomplishmentCustomView.vue'
import { ModuleName } from '../types/module'
import { fetchCurrentUser } from '../services/authService'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    redirect: '/home'
  },
  {
    path: '/login',
    name: 'Login',
    component: LoginView,
    meta: {
      requiresAuth: false
    }
  },
  {
    path: '/home',
    name: 'Home',
    component: DashboardView,
    meta: {
      module: ModuleName.Dashboard,
      requiresAuth: true
    }
  },
  {
    path: '/communications',
    name: 'Communications',
    component: CommunicationsView,
    meta: {
      module: ModuleName.Communications,
      requiresAuth: true
    }
  },
  {
    path: '/inventory',
    name: 'Inventory',
    component: InventoryView,
    meta: {
      module: ModuleName.Inventory,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/equipment',
    name: 'Equipment',
    component: EquipmentView,
    meta: {
      module: ModuleName.Equipment,
      requiresAuth: true
    }
  },
  {
    path: '/inventory/jrrs',
    name: 'JRRS',
    component: JRRSView,
    meta: {
      module: ModuleName.JRRS,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments',
    name: 'Accomplishments',
    component: AccomplishmentView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/daily',
    name: 'Daily Report',
    component: AccomplishmentDailyView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/monthly',
    name: 'Monthly Report',
    component: AccomplishmentMonthlyView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/quarterly',
    name: 'Quarterly Report',
    component: AccomplishmentQuarterlyView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/annual',
    name: 'Annual Report',
    component: AccomplishmentAnnualView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  },
  {
    path: '/accomplishments/custom',
    name: 'Custom Period Report',
    component: AccomplishmentCustomView,
    meta: {
      module: ModuleName.Accomplishments,
      requiresAuth: true
    }
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// Centralized Authentication Navigation Guard
router.beforeEach(async (to, _from, next) => {
  const isPublicRoute = to.meta.requiresAuth === false
  const user = await fetchCurrentUser()

  if (!isPublicRoute && !user) {
    // Unauthenticated user attempting to access protected route
    next({ path: '/login', query: { redirect: to.fullPath } })
  } else if (to.path === '/login' && user) {
    // Authenticated user attempting to access login page
    next('/home')
  } else {
    next()
  }
})

export default router
