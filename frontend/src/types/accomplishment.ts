// frontend/src/types/accomplishment.ts

export interface OfficeOption {
  id: number;
  office_name: string;
  office_code: string;
  office_abbv?: string;
}

export interface CategoryOption {
  id: number;
  category_name: string;
  category_code: string;
}

export interface AccomplishmentOptions {
  offices: OfficeOption[];
  categories?: CategoryOption[];
}

export interface AccomplishmentItem {
  id: number;
  office_id: number;
  category_id?: number;
  office_name?: string;
  office_code?: string;
  office_abbv?: string;
  category_name?: string;
  category_code?: string;
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

export interface AccomplishmentCategorySummary {
  category_id: number;
  category_name: string;
  category_code?: string | null;
  count: number;
}

export interface OutgoingCommCategorySummary {
  category_id: number;
  category_name: string;
  category_code?: string | null;
  count: number;
}

export interface ClearancePurposeSummary {
  purpose_id: number;
  purpose_name: string;
  count: number;
}

export interface ReportData {
  records: AccomplishmentItem[];
  accomplishments_by_category?: AccomplishmentCategorySummary[];
  outgoing_comms_by_category?: OutgoingCommCategorySummary[];
  clearances_by_purpose?: ClearancePurposeSummary[];
  communications_stats: {
    incoming: number;
    outgoing: number;
  };
}

export interface AccomplishmentFormPayload {
  id?: number;
  office_id: number;
  category_id?: number;
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
