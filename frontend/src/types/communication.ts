// frontend/src/types/communication.ts

export interface CommunicationCategory {
  id: number;
  name: string;
  code: string | null;
  is_active: number | boolean;
  created_at?: string;
  updated_at?: string;
  created_by?: number;
  modified_by?: number;
  deleted_at?: string | null;
}

export interface CommunicationPurpose {
  id: number;
  name: string;
  is_active: number | boolean;
  created_at?: string;
  updated_at?: string;
  created_by?: number;
  modified_by?: number;
  deleted_at?: string | null;
}

export interface CommunicationOffice {
  id: number;
  office_name: string;
  office_code: string;
  office_abbv?: string | null;
  office_category?: 'Staff' | 'Special Staff' | 'Group' | 'Others';
  is_active: number | boolean;
}

export interface CommunicationOptions {
  categories: CommunicationCategory[];
  purposes: CommunicationPurpose[];
  offices: CommunicationOffice[];
}

export interface CommunicationActivity {
  id: number;
  communication_id: number;
  activity_type: string;
  activity_date: string;
  remarks?: string | null;
  created_at?: string;
  created_by?: number;
}

export interface Communication {
  id: number;
  communication_type: 'Incoming' | 'Outgoing';
  office_id: number;
  category_id: number;
  purpose_id: number;
  subject: string;
  communication_date: string;
  status: string;
  image_url?: string | null;
  image_urls?: string[];
  attachments?: { id: number; image_url: string }[];
  created_at?: string;
  updated_at?: string;
  created_by?: number;
  modified_by?: number;
  deleted_at?: string | null;
  office_name?: string;
  office_code?: string;
  office_abbv?: string | null;
  category_name?: string;
  category_code?: string | null;
  purpose_name?: string;
  latest_activity_date?: string | null;
  age_days?: number;
  activities?: CommunicationActivity[];
}

export interface CommunicationFormPayload {
  id?: number;
  communication_type: 'Incoming' | 'Outgoing';
  office_id: number;
  category_id: number;
  purpose_id: number;
  subject: string;
  communication_date: string;
  status: string;
  image_url?: string | null;
  image_data?: string | null;
  images_data?: string[];
}

export interface CommunicationActivityPayload {
  communication_id: number;
  activity_type: string;
  activity_date?: string;
  remarks?: string | null;
}

export interface CommunicationFilterParams {
  type?: 'Incoming' | 'Outgoing';
  office_id?: number;
  category_id?: number;
  purpose_id?: number;
  status?: string;
  search?: string;
}

export interface CommunicationReportsData {
  by_type: Array<{ communication_type: string; total: number }>;
  by_category: Array<{ category_name: string; total: number }>;
  by_purpose: Array<{ purpose_name: string; total: number }>;
  by_status: Array<{ status: string; total: number }>;
}

export interface CommunicationOverviewSummary {
  monthly_summary: {
    incoming: number;
    outgoing: number;
    total: number;
  };
  todays_incoming: Communication[];
  todays_outgoing: Communication[];
}
