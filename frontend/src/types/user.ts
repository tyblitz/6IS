// frontend/src/types/user.ts
// TypeScript Definitions for 6IS User Management

export type UserRole = 'Administrator' | 'User'

export interface UserAccount {
  id: number
  username: string
  full_name: string
  role: UserRole
  is_active: number
  created_at: string
  updated_at: string
}

export interface CreateUserPayload {
  username: string
  full_name: string
  password: string
  role: UserRole
}

export interface UpdateUserPayload {
  id: number
  full_name: string
  role: UserRole
  password?: string
}
