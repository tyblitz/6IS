// frontend/src/composables/useModules.ts
// Centralized Reactive Module Composable for 6IS

import { ref, computed } from 'vue'
import type { SystemModule, ModuleUpdateApiResponse } from '../types/module'
import { fetchModules, toggleModuleActive } from '../services/moduleService'

// Shared reactive module state (singleton across all components & router guards)
const modulesState = ref<SystemModule[]>([])
const isLoadedState = ref(false)
const isLoadingState = ref(false)

export function useModules() {
  const modules = computed(() => modulesState.value)
  const isLoaded = computed(() => isLoadedState.value)
  const isLoading = computed(() => isLoadingState.value)

  /**
   * Loads modules from the backend if not already loaded, or when forced.
   */
  async function loadModules(forceRefresh = false): Promise<SystemModule[]> {
    if (isLoadedState.value && !forceRefresh) {
      return modulesState.value
    }

    if (isLoadingState.value) {
      // Wait for existing in-flight request
      while (isLoadingState.value) {
        await new Promise(resolve => setTimeout(resolve, 50))
      }
      return modulesState.value
    }

    isLoadingState.value = true
    try {
      const data = await fetchModules()
      if (data && data.length > 0) {
        modulesState.value = data
        isLoadedState.value = true
      }
    } finally {
      isLoadingState.value = false
    }

    return modulesState.value
  }

  /**
   * Checks whether a module is active by its module_key or ModuleName identifier.
   * Core modules (dashboard, administrator) are always active.
   * 
   * @param moduleKey Module key string ('inventory', 'communications', etc.)
   */
  function isEnabled(moduleKey: string | undefined | null): boolean {
    if (!moduleKey) return true

    let normalizedKey = moduleKey.toLowerCase().trim()
    if (normalizedKey === 'equipment' || normalizedKey === 'jrrs') {
      normalizedKey = 'inventory'
    }

    // Core modules can never be disabled
    if (normalizedKey === 'dashboard' || normalizedKey === 'administrator' || normalizedKey === 'home') {
      return true
    }

    // If registry is not yet loaded, look up in current state or fallback safely
    const found = modulesState.value.find(m => m.module_key.toLowerCase() === normalizedKey)
    if (found) {
      return found.is_active
    }

    // If modules have been loaded and key was not found in registry, return false
    if (isLoadedState.value) {
      return false
    }

    // Default optimistic true while initially loading to prevent premature route rejection
    return true
  }

  /**
   * Toggles module activation and updates local reactive state immediately.
   */
  async function toggleModule(moduleId: number, isActive: boolean): Promise<ModuleUpdateApiResponse> {
    const result = await toggleModuleActive(moduleId, isActive)

    if (result.success && result.data) {
      const index = modulesState.value.findIndex(m => m.id === moduleId)
      if (index !== -1) {
        modulesState.value[index] = { ...result.data }
      }
    }

    return result
  }

  return {
    modules,
    isLoaded,
    isLoading,
    loadModules,
    isEnabled,
    toggleModule
  }
}

/**
 * Synchronous helper to check if a module is active (usable in template filters)
 */
export function isModuleActive(moduleKey: string | undefined | null): boolean {
  const { isEnabled } = useModules()
  return isEnabled(moduleKey)
}
