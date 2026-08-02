// frontend/src/services/accomplishmentService.ts

import type {
  Accomplishment,
  AccomplishmentFormPayload,
  AccomplishmentOptions,
  AccomplishmentTodayItem,
  ApiResponse
} from '../types/accomplishment';

const API_BASE_URL = 'http://localhost/6IS/backend/api/accomplishments/index.php';

export async function fetchTodayAccomplishments(): Promise<ApiResponse<AccomplishmentTodayItem[]>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=overview_today`);
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to connect to backend server.',
      data: [],
      errors: { network: err.message }
    };
  }
}

export async function fetchAccomplishmentsList(params?: {
  view?: string;
  search?: string;
  status?: string;
  priority?: string;
  office_id?: number;
  category_id?: number;
}): Promise<ApiResponse<Accomplishment[]>> {
  try {
    const query = new URLSearchParams();
    if (params?.view) query.append('view', params.view);
    if (params?.search) query.append('search', params.search);
    if (params?.status) query.append('status', params.status);
    if (params?.priority) query.append('priority', params.priority);
    if (params?.office_id) query.append('office_id', params.office_id.toString());
    if (params?.category_id) query.append('category_id', params.category_id.toString());

    const res = await fetch(`${API_BASE_URL}?${query.toString()}`);
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to connect to backend server.',
      data: [],
      errors: { network: err.message }
    };
  }
}

export async function fetchAccomplishmentById(id: number): Promise<ApiResponse<Accomplishment>> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`);
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch accomplishment details.',
      data: null,
      errors: { network: err.message }
    };
  }
}

export async function fetchAccomplishmentOptions(): Promise<ApiResponse<AccomplishmentOptions>> {
  try {
    const res = await fetch(`${API_BASE_URL}?view=options`);
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to fetch options.',
      data: null,
      errors: { network: err.message }
    };
  }
}

export async function createAccomplishment(payload: AccomplishmentFormPayload): Promise<ApiResponse<{ id: number }>> {
  try {
    const res = await fetch(API_BASE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to save accomplishment record.',
      data: null,
      errors: { network: err.message }
    };
  }
}

export async function updateAccomplishment(id: number, payload: AccomplishmentFormPayload): Promise<ApiResponse<{ id: number }>> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to update accomplishment record.',
      data: null,
      errors: { network: err.message }
    };
  }
}

export async function deleteAccomplishment(id: number): Promise<ApiResponse<{ id: number }>> {
  try {
    const res = await fetch(`${API_BASE_URL}?id=${id}`, {
      method: 'DELETE'
    });
    return await res.json();
  } catch (err: any) {
    return {
      success: false,
      message: 'Failed to delete accomplishment record.',
      data: null,
      errors: { network: err.message }
    };
  }
}
