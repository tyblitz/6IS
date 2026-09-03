// frontend/src/types/user.ts
// TypeScript Definitions for 6IS User Management

export type UserRole = 'Administrator' | 'User' | string

export interface UserAccount {
  id: number
  username: string
  full_name: string
  role: UserRole
  role_id?: number | null
  role_name?: string
  office_id?: number | null
  office_name?: string | null
  office_code?: string | null
  is_active: number
  created_at: string
  updated_at: string
}

export interface CreateUserPayload {
  username: string
  full_name: string
  password: string
  role?: UserRole
  role_id?: number | null
  office_id?: number | null
}

export interface UpdateUserPayload {
  id: number
  full_name: string
  role?: UserRole
  role_id?: number | null
  office_id?: number | null
  password?: string
}

