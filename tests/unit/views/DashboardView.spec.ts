// tests/unit/views/DashboardView.spec.ts
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import DashboardView from '@/views/DashboardView.vue'
import * as authService from '@/services/authService'
import * as useModulesComposable from '@/composables/useModules'
import type { AuthUser } from '@/types/auth'

describe('DashboardView.vue — User Dashboard Operational Launcher', () => {
  const mockUser: AuthUser = {
    id: 1,
    username: 'TestUser',
    role: 'User',
    role_id: 2,
    permissions: ['inventory.view', 'communications.view', 'calendar.view', 'accomplishments.view']
  }

  beforeEach(() => {
    vi.restoreAllMocks()
    vi.spyOn(authService, 'activeUser', 'get').mockReturnValue(ref<AuthUser | null>(mockUser))
  })

  function mountComponent(enabledModules: Record<string, boolean> = {}) {
    const isEnabledMock = vi.fn((key: string | undefined | null) => {
      if (!key) return true
      return enabledModules[key] ?? true
    })
    const loadModulesMock = vi.fn().mockResolvedValue([])

    vi.spyOn(useModulesComposable, 'useModules').mockReturnValue({
      modules: ref([]),
      isLoaded: ref(true),
      isLoading: ref(false),
      loadModules: loadModulesMock,
      isEnabled: isEnabledMock,
      toggleModule: vi.fn()
    } as any)

    return mount(DashboardView, {
      global: {
        stubs: {
          MainLayout: {
            template: '<div class="main-layout-stub"><slot /></div>'
          },
          'ion-icon': true,
          'router-link': {
            props: ['to'],
            template: '<a :href="to" :to="to" v-bind="$attrs"><slot /></a>'
          }
        }
      }
    })
  }

  it('renders the welcome hero banner with active user username', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('.welcome-title').text()).toContain('Welcome back, TestUser! 👋')
    expect(wrapper.find('.welcome-subtitle').text()).toContain('Select an operational module')
    expect(wrapper.find('.today-date-badge').exists()).toBe(true)
  })

  it('renders all 4 operational module cards when all modules are enabled', () => {
    const wrapper = mountComponent({
      inventory: true,
      communications: true,
      accomplishments: true,
      calendar: true
    })

    expect(wrapper.find('.inventory-card').exists()).toBe(true)
    expect(wrapper.find('.communications-card').exists()).toBe(true)
    expect(wrapper.find('.accomplishments-card').exists()).toBe(true)
    expect(wrapper.find('.calendar-card').exists()).toBe(true)
  })

  it('provides semantic router-link navigation to the respective module routes', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('.inventory-card').attributes('to')).toBe('/inventory')
    expect(wrapper.find('.communications-card').attributes('to')).toBe('/communications')
    expect(wrapper.find('.accomplishments-card').attributes('to')).toBe('/accomplishments')
    expect(wrapper.find('.calendar-card').attributes('to')).toBe('/calendar')
  })

  it('has accessible labels on module cards', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('.inventory-card').attributes('aria-label')).toContain('Open Inventory module')
    expect(wrapper.find('.communications-card').attributes('aria-label')).toContain('Open Communications module')
    expect(wrapper.find('.accomplishments-card').attributes('aria-label')).toContain('Open Accomplishments module')
    expect(wrapper.find('.calendar-card').attributes('aria-label')).toContain('Open Calendar module')
  })

  it('hides disabled modules dynamically from the launcher', () => {
    const wrapper = mountComponent({
      inventory: true,
      communications: false,
      accomplishments: true,
      calendar: false
    })

    expect(wrapper.find('.inventory-card').exists()).toBe(true)
    expect(wrapper.find('.communications-card').exists()).toBe(false)
    expect(wrapper.find('.accomplishments-card').exists()).toBe(true)
    expect(wrapper.find('.calendar-card').exists()).toBe(false)
  })

  it('contains NO Weekly Operational Schedule or Calendar mini-widgets', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('.dash-schedule-widget').exists()).toBe(false)
    expect(wrapper.find('.dash-schedule-header').exists()).toBe(false)
    expect(wrapper.find('.dash-schedule-grid').exists()).toBe(false)
    expect(wrapper.find('.stepper-controls').exists()).toBe(false)
    expect(wrapper.find('.link-view-calendar').exists()).toBe(false)
  })

  it('contains NO duplicate Administrator-specific card grid', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('.admin-cards-grid').exists()).toBe(false)
    expect(wrapper.find('.admin-icon').exists()).toBe(false)
  })
})
