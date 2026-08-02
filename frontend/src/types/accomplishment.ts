// frontend/src/types/accomplishment.ts

export type AccomplishmentStatus = 'Pending' | 'Ongoing' | 'Completed' | 'Cancelled';
export type AccomplishmentPriority = 'Low' | 'Medium' | 'High' | 'Critical';

export interface OfficeOption {
  id: number;
  office_name: string;
  office_code: string;
}

export interface CategoryOption {
  id: number;
  category_name: string;
}

export interface UserOption {
  id: number;
  full_name: string;
  username: string;
  role: string;
}

export interface AccomplishmentOptions {
  offices: OfficeOption[];
  categories: CategoryOption[];
  users: UserOption[];
}

export interface AccomplishmentTodayItem {
  id: number;
  title: string;
  assigned_employee_name: string;
  office_name: string;
  office_code: string;
  category_name: string;
}

export interface Accomplishment {
  id: number;
  title: string;
  description: string | null;
  office_id: number;
  office_name?: string;
  office_code?: string;
  category_id: number;
  category_name?: string;
  assigned_employee_id: number;
  assigned_employee_name?: string;
  date_started: string;
  date_completed: string | null;
  status: AccomplishmentStatus;
  priority: AccomplishmentPriority;
  remarks: string | null;
  created_at: string;
  updated_at: string;
  created_by: number;
  modified_by: number;
  deleted_at: string | null;
}

export interface AccomplishmentFormPayload {
  id?: number;
  title: string;
  description?: string | null;
  office_id: number;
  category_id: number;
  assigned_employee_id: number;
  date_started: string;
  date_completed?: string | null;
  status: AccomplishmentStatus;
  priority: AccomplishmentPriority;
  remarks?: string | null;
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T | null;
  errors: Record<string, string> | null;
}
