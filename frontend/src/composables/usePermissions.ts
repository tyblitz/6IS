// frontend/src/composables/usePermissions.ts
// Centralized Reactive Permissions Composable for 6IS Core RBAC (Phase 2)

import { computed } from 'vue'
import { activeUser } from '../services/authService'

export function usePermissions() {
  const userPermissions = computed<string[]>(() => {
    return activeUser.value?.permissions ?? []
  })

  /**
   * Checks whether the current user possesses a permission for a module.
   * Fail closed: returns false if not authenticated or permissions are not yet loaded.
   */
  function hasPermission(moduleKey: string, permissionKey: string): boolean {
    if (!activeUser.value || !Array.isArray(activeUser.value.permissions)) {
      return false
    }

    const code = `${moduleKey.trim().toLowerCase()}.${permissionKey.trim().toLowerCase()}`
    return userPermissions.value.some(p => p.toLowerCase() === code)
  }

  /**
   * Alias for hasPermission
   */
  function can(moduleKey: string, permissionKey: string): boolean {
    return hasPermission(moduleKey, permissionKey)
  }

  /**
   * Checks permission given a dotted key, e.g. 'inventory.create'
   */
  function isPermitted(permissionCode: string): boolean {
    const parts = permissionCode.split('.')
    if (parts.length !== 2) return false
    return hasPermission(parts[0], parts[1])
  }

  const isAdmin = computed<boolean>(() => {
    return activeUser.value?.role === 'Administrator'
  })

  return {
    userPermissions,
    hasPermission,
    can,
    isPermitted,
    isAdmin
  }
}
