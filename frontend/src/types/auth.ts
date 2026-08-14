// frontend/src/types/auth.ts
// TypeScript Definitions for 6IS Authentication Foundation

export type UserRole = 'Administrator' | 'User'

export interface AuthUser {
  id: number;
  username: string;
  role: UserRole;
}

export interface LoginPayload {
  username: string;
  password: string;
}

export interface LoginResponse {
  success: boolean;
  message: string;
  user?: AuthUser | null;
  errors?: Record<string, string> | null;
}

export interface CurrentUserResponse {
  authenticated: boolean;
  user?: AuthUser | null;
}
