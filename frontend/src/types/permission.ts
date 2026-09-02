// frontend/src/types/permission.ts
// TypeScript Definitions for 6IS Core Roles & Permissions System (Phase 2)

export interface Permission {
  id: number;
  module_key: string;
  permission_key: string;
  name: string;
  description: string | null;
  is_active: boolean;
  code: string;
  module_name?: string;
  module_is_active?: boolean;
}

export interface GroupedModulePermissions {
  module_key: string;
  module_name: string;
  module_is_active: boolean;
  permissions: Permission[];
}

export interface Role {
  id: number;
  name: string;
  description: string | null;
  is_system: boolean;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
  user_count?: number;
  permission_count?: number;
  permission_ids?: number[];
  permissions?: string[];
}

export interface RolePayload {
  name: string;
  description?: string;
  is_active?: boolean;
}

export interface RolePermissionsUpdatePayload {
  permission_ids: number[];
}
