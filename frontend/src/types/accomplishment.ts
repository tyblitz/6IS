// frontend/src/types/accomplishment.ts

export interface OfficeOption {
  id: number;
  office_name: string;
  office_code: string;
}

export interface AccomplishmentOptions {
  offices: OfficeOption[];
}

export interface AccomplishmentItem {
  id: number;
  office_id: number;
  office_name?: string;
  office_code?: string;
  date: string;
  description: string;
  remarks: string | null;
  created_at?: string;
  updated_at?: string;
  deleted_at?: string | null;
}

export interface OverviewCounts {
  today: number;
  monthly: number;
  quarterly: number;
  annual: number;
  incoming_comms: number;
  outgoing_comms: number;
}

export interface OverviewSummary {
  counts: OverviewCounts;
  today_records: AccomplishmentItem[];
}

export interface ReportData {
  records: AccomplishmentItem[];
  communications_stats: {
    incoming: number;
    outgoing: number;
  };
}

export interface AccomplishmentFormPayload {
  id?: number;
  office_id: number;
  date: string;
  description: string;
  remarks?: string | null;
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T | null;
  errors: Record<string, string> | null;
}
