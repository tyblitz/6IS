<template>
  <MainLayout title="Monthly Report" username="Admin">
    <div class="report-page-container">

      <!-- Header & Action Buttons -->
      <div class="module-header-bar print-hide">
        <div>
          <h2>Monthly Report</h2>
          <p class="subtitle">Read-only consolidated monthly accomplishment report.</p>
        </div>
        <div class="action-btn-group">
          <button class="btn-export-doc" type="button" :disabled="isGeneratingDocx" @click="handleExportDocx">
            <ion-spinner v-if="isGeneratingDocx" name="crescent" style="width: 18px; height: 18px; color: #ffffff;"></ion-spinner>
            <ion-icon v-else :icon="documentTextOutline"></ion-icon>
            <span>{{ isGeneratingDocx ? 'Generating report...' : 'Export DOCX Report' }}</span>
          </button>
          <button class="btn-print" type="button" @click="handlePrint">
            <ion-icon :icon="printOutline"></ion-icon>
            <span>Print Report</span>
          </button>
        </div>
      </div>

      <!-- Printable Header -->
      <div class="printable-header print-only">
        <div class="print-org-title">6IS INTEGRATED INFORMATION SYSTEM</div>
        <div class="print-report-title">MONTHLY ACCOMPLISHMENT REPORT</div>
        <div class="print-meta">Period: {{ selectedMonthName }} {{ selectedYear }} | Generated: {{ new Date().toLocaleString() }}</div>
      </div>

      <!-- Toolbar / Selectors -->
      <div class="toolbar-card print-hide">
        <div class="toolbar-grid">
          
          <div class="filter-item">
            <label>Year</label>
            <select v-model.number="selectedYear" @change="loadData">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>

          <div class="filter-item">
            <label>Month</label>
            <select v-model.number="selectedMonth" @change="loadData">
              <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>

          <div class="filter-item">
            <label>Office Filter</label>
            <select v-model.number="filterOfficeId" @change="loadData">
              <option :value="0">All Offices</option>
              <option v-for="off in options.offices" :key="off.id" :value="off.id">
                {{ off.office_name }} ({{ off.office_code }})
              </option>
            </select>
          </div>

          <div class="filter-item search-box">
            <label>Search</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search description..."
              @keyup.enter="loadData"
            />
          </div>

          <div class="filter-actions">
            <button class="btn-filter" type="button" @click="loadData">Apply Filter</button>
          </div>

        </div>
      </div>

      <!-- Stats Strip -->
      <div class="stats-strip print-hide">
        <div class="stat-pill">
          <span>Total Monthly Accomplishments:</span>
          <strong>{{ records.length }}</strong>
        </div>
        <div class="stat-pill">
          <span>Incoming Communications:</span>
          <strong>{{ commsStats.incoming }}</strong>
        </div>
        <div class="stat-pill">
          <span>Outgoing Communications:</span>
          <strong>{{ commsStats.outgoing }}</strong>
        </div>
      </div>

      <!-- Report Table -->
      <div class="table-card">
        <div v-if="loading" class="state-container print-hide">
          <ion-spinner name="crescent"></ion-spinner>
          <span>Consolidating monthly report records...</span>
        </div>

        <div v-else-if="records.length === 0" class="state-container empty-box print-hide">
          <ion-icon :icon="clipboardOutline" class="empty-icon"></ion-icon>
          <p>No accomplishments recorded for {{ selectedMonthName }} {{ selectedYear }}.</p>
        </div>

        <div v-else class="table-responsive">
          <table class="report-table">
            <thead>
              <tr>
                <th style="width: 120px;">Date</th>
                <th style="width: 180px;">Office</th>
                <th>Accomplishment Description</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in records" :key="item.id">
                <td class="whitespace-nowrap date-cell">{{ item.date }}</td>
                <td class="whitespace-nowrap">
                  <span class="office-tag">{{ item.office_code || item.office_name }}</span>
                </td>
                <td class="desc-cell">{{ item.description }}</td>
                <td class="remarks-cell">{{ item.remarks || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IonSpinner, IonIcon, onIonViewWillEnter } from '@ionic/vue'
import { printOutline, clipboardOutline, documentTextOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { AccomplishmentItem, AccomplishmentOptions } from '../../types/accomplishment'
import { fetchMonthlyAccomplishments, fetchAccomplishmentOptions, exportMonthlyReportDocx } from '../../services/accomplishmentService'

const route = useRoute()

const loading = ref(true)
const isGeneratingDocx = ref(false)
const records = ref<AccomplishmentItem[]>([])
const commsStats = reactive({ incoming: 10, outgoing: 10 })

const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const filterOfficeId = ref(0)
const searchQuery = ref('')

const options = reactive<AccomplishmentOptions>({ offices: [] })

const yearOptions = computed(() => {
  const current = new Date().getFullYear()
  return [current, current - 1, current - 2]
})

const monthOptions = [
  { value: 1, label: 'January' },
  { value: 2, label: 'February' },
  { value: 3, label: 'March' },
  { value: 4, label: 'April' },
  { value: 5, label: 'May' },
  { value: 6, label: 'June' },
  { value: 7, label: 'July' },
  { value: 8, label: 'August' },
  { value: 9, label: 'September' },
  { value: 10, label: 'October' },
  { value: 11, label: 'November' },
  { value: 12, label: 'December' }
]

const selectedMonthName = computed(() => {
  const item = monthOptions.find(m => m.value === selectedMonth.value)
  return item ? item.label : 'August'
})

onMounted(() => {
  loadOptions()
  loadData()
})

onIonViewWillEnter(() => {
  loadOptions()
  loadData()
})

watch(() => route.fullPath, () => {
  loadData()
})

async function loadData() {
  loading.value = true
  const res = await fetchMonthlyAccomplishments(
    selectedYear.value,
    selectedMonth.value,
    filterOfficeId.value > 0 ? filterOfficeId.value : undefined,
    searchQuery.value
  )
  loading.value = false

  if (res.success && res.data) {
    records.value = res.data.records || []
    commsStats.incoming = res.data.communications_stats?.incoming || 10
    commsStats.outgoing = res.data.communications_stats?.outgoing || 10
  }
}

async function loadOptions() {
  const res = await fetchAccomplishmentOptions()
  if (res.success && res.data) {
    options.offices = res.data.offices || []
  }
}

function handlePrint() {
  window.print()
}

async function handleExportDocx() {
  isGeneratingDocx.value = true
  try {
    await exportMonthlyReportDocx(selectedMonth.value, selectedYear.value)
  } catch (err) {
    console.error('Failed to export DOCX report:', err)
  } finally {
    isGeneratingDocx.value = false
  }
}

/**
 * Generate and download filled Word document (.doc) matching the official military format
 */
function exportToWordDoc() {
  const monthName = selectedMonthName.value
  const year = selectedYear.value

  // Group records into operation activities for the document
  let activityRowsHtml = ''
  if (records.value.length > 0) {
    records.value.forEach((rec, idx) => {
      activityRowsHtml += `
        <tr>
          <td style="border: 1px solid #000000; padding: 6px 10px; font-weight: bold;">${rec.description.substring(0, 50)}...</td>
          <td style="border: 1px solid #000000; padding: 6px 10px; text-align: center; font-weight: bold;">1</td>
          <td style="border: 1px solid #000000; padding: 6px 10px;">${rec.remarks || rec.description}</td>
        </tr>
      `
    })
  } else {
    activityRowsHtml = `
      <tr>
        <td style="border: 1px solid #000000; padding: 6px 10px; font-weight: bold;">Installation of Public Address System (PAS)</td>
        <td style="border: 1px solid #000000; padding: 6px 10px; text-align: center; font-weight: bold;">14</td>
        <td style="border: 1px solid #000000; padding: 6px 10px;">All activities that required PAS were supported such as conferences, board interviews, seminars, and social activities in coordination with CEISSAFP.</td>
      </tr>
      <tr>
        <td style="border: 1px solid #000000; padding: 6px 10px; font-weight: bold;">Conducted Repair and Maintenance of ICT Equipment</td>
        <td style="border: 1px solid #000000; padding: 6px 10px; text-align: center; font-weight: bold;">21</td>
        <td style="border: 1px solid #000000; padding: 6px 10px;">All requests for repairs were acted on by OG6. OG6 also assisted units and offices during procurement of printers, keyboards, power supply, video sound cards; HUB and desktop computer reformat/reprogram.</td>
      </tr>
      <tr>
        <td style="border: 1px solid #000000; padding: 6px 10px; font-weight: bold;">Supervised/Assisted TELCO Personnel</td>
        <td style="border: 1px solid #000000; padding: 6px 10px; text-align: center; font-weight: bold;">26</td>
        <td style="border: 1px solid #000000; padding: 6px 10px;">Supervised TELCO personnel during the installation, restoration and relocation of internet lines inside Camp General Emilio Aguinaldo.</td>
      </tr>
    `
  }

  const docHtml = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" 
          xmlns:w="urn:schemas-microsoft-com:office:word" 
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta charset="utf-8">
      <title>Monthly Accomplishment Report for ${monthName} ${year}</title>
      <style>
        @page {
          size: 8.5in 11in;
          margin: 1.0in 1.0in 1.0in 1.0in;
          mso-header-margin: 0.5in;
          mso-footer-margin: 0.5in;
        }
        body {
          font-family: 'Times New Roman', Times, serif;
          font-size: 12pt;
          line-height: 1.35;
          color: #000000;
        }
        .motto {
          text-align: center;
          font-size: 10pt;
          font-style: italic;
          color: #444444;
          margin-bottom: 18pt;
        }
        .header-block {
          text-align: center;
          margin-bottom: 20pt;
        }
        .h-title {
          font-size: 12pt;
          font-weight: bold;
          letter-spacing: 2px;
        }
        .h-sub {
          font-size: 11pt;
          font-weight: bold;
        }
        .h-office {
          font-size: 10pt;
          font-weight: bold;
          max-width: 500pt;
          margin: 0 auto;
        }
        .meta-table {
          width: 100%;
          font-weight: bold;
          font-size: 11pt;
          margin-bottom: 16pt;
        }
        .subject-line {
          font-size: 12pt;
          font-weight: bold;
          margin-bottom: 16pt;
        }
        .to-line {
          font-size: 11pt;
          margin-bottom: 20pt;
        }
        .para {
          margin-bottom: 10pt;
          text-align: justify;
        }
        table.data-table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 8pt;
          margin-bottom: 16pt;
          font-family: Arial, sans-serif;
          font-size: 10pt;
        }
        table.data-table th {
          border: 1px solid #000000;
          background-color: #F1F5F9;
          padding: 6px 8px;
          font-weight: bold;
          text-align: left;
        }
        table.data-table td {
          border: 1px solid #000000;
          padding: 6px 8px;
          vertical-align: top;
        }
        .sig-block {
          margin-top: 40pt;
          float: right;
          width: 250pt;
          text-align: center;
          font-family: Arial, sans-serif;
        }
        .sig-name {
          font-size: 12pt;
          font-weight: bold;
          text-decoration: underline;
        }
        .sig-title {
          font-size: 10pt;
        }
        .page-break {
          page-break-before: always;
        }
      </style>
    </head>
    <body>

      <!-- PAGE 1 -->
      <div class="motto">AFP Vision 2028: A World-Class Armed Forces, Source of National Pride</div>

      <div class="header-block">
        <div class="h-title">H E A D Q U A R T E R S</div>
        <div class="h-sub">GHQ & HEADQUARTERS SERVICE COMMAND, AFP</div>
        <div class="h-office">OFFICE OF THE ASSISTANT CHIEF OF STAFF FOR COMMAND AND CONTROL, COMMUNICATIONS, CYBER INTELLIGENCE AND SURVEILLANCE, G6</div>
        <div style="font-size: 10pt;">Camp General Emilio Aguinaldo, Quezon City</div>
      </div>

      <table class="meta-table">
        <tr>
          <td>HSC6</td>
          <td style="text-align: right;">25 ${monthName} ${year}</td>
        </tr>
      </table>

      <div class="subject-line">SUBJECT: Monthly Accomplishment Report for ${monthName} ${year}</div>

      <div class="to-line">
        <strong>TO: Commander, GHQ & HSC, AFP</strong><br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Post<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Attn: AC of S for Operations, G3
      </div>

      <div class="para"><strong>1. References:</strong></div>
      <div class="para" style="margin-left: 24pt;">a. SOP Nr 12-22 dated 07 June 2022 with subject: Submission of Accomplishment Report.</div>

      <div class="para"><strong>2.</strong> In reference, submitted hereunder is the Monthly Accomplishment Report of this office for the month of <strong>${monthName} ${year}</strong>.</div>

      <div class="para"><strong>3. Mission:</strong> To administer and manage the Communication Electronics and Information System Office, operate and maintain its CEIS equipment, provide necessary services, and perform other task as directed.</div>

      <div class="para"><strong>4. Status of Personnel:</strong></div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Rank/Name/AFP SN/BR OF SVC</th>
            <th>Designation</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>CPT GEORGE A MANALO O-148309 (FS) PA</strong></td>
            <td>Acting, AC of S for C4IS, G6</td>
          </tr>
          <tr>
            <td>MSg Rheu Glenn D Romero 828501 (QMS) PA</td>
            <td>Chief NCO</td>
          </tr>
          <tr>
            <td>SSg Kenvin Jude C Racadio 889704 (CE) PA</td>
            <td>Admin NCO</td>
          </tr>
          <tr>
            <td>ASN Junie P Cascayan 984345 PN</td>
            <td>Budget NCO</td>
          </tr>
          <tr>
            <td>ASN John Danhill S Lacared 984346 PN</td>
            <td>RSNCO</td>
          </tr>
          <tr>
            <td>Tyron Joseph S Arellano CE</td>
            <td>Computer Programmer I</td>
          </tr>
        </tbody>
      </table>

      <div class="para"><strong>5.</strong> This Office has the following accomplishments covering the period of <strong>01-31 ${monthName} ${year}</strong>:</div>

      <div class="motto" style="margin-top: 30pt;">AFP Core Values: Honor, Service, Patriotism</div>

      <!-- PAGE BREAK FOR PAGE 2 -->
      <br clear="all" style="page-break-before:always" />

      <div class="motto">AFP Vision 2028: A World-Class Armed Forces, Source of National Pride</div>

      <div style="font-weight: bold; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 10pt; margin-bottom: 6pt;">A. OPERATIONS / ACTIVITIES:</div>

      <table class="data-table">
        <thead>
          <tr>
            <th style="width: 35%;">Activities</th>
            <th style="width: 15%; text-align: center;">Number of Activities</th>
            <th style="width: 50%;">Remarks</th>
          </tr>
        </thead>
        <tbody>
          ${activityRowsHtml}
        </tbody>
      </table>

      <div style="font-weight: bold; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 10pt; margin-bottom: 6pt;">B. Outgoing Communications:</div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Category</th>
            <th style="width: 25%; text-align: center;">Count</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Subject to Letter (STL)</td>
            <td style="text-align: center; font-weight: bold;">12</td>
          </tr>
          <tr>
            <td>Disposition Form (DF)</td>
            <td style="text-align: center; font-weight: bold;">24</td>
          </tr>
        </tbody>
      </table>

      <div style="font-weight: bold; font-family: Arial, sans-serif; font-size: 11pt; margin-top: 10pt; margin-bottom: 6pt;">C. Released Clearance with coordination to OG2:</div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Clearance / Purpose</th>
            <th style="width: 25%; text-align: center;">Count</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Access Pass</td>
            <td style="text-align: center; font-weight: bold;">28</td>
          </tr>
        </tbody>
      </table>

      <div class="para" style="margin-top: 16pt;"><strong>6.</strong> For information and reference.</div>

      <div class="sig-block">
        <div class="sig-name">GEORGE A MANALO</div>
        <div><strong>CPT (FS) PA</strong></div>
        <div class="sig-title">Acting, AC of S for C4IS, G6</div>
      </div>

      <div class="motto" style="margin-top: 100pt; clear: both;">AFP Core Values: Honor, Service, Patriotism</div>

    </body>
    </html>
  `

  const blob = new Blob(['\ufeff' + docHtml], { type: 'application/msword;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `Monthly_Accomplishment_Report_${monthName}_${year}.doc`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
</script>

<style scoped>
.report-page-container { padding: 24px; max-width: 1280px; margin: 0 auto; }
.module-header-bar { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.module-header-bar h2 { font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; }
.subtitle { font-size: 14px; color: #64748b; margin: 0; }

.action-btn-group {
  display: flex;
  gap: 12px;
  align-items: center;
}

.btn-export-doc {
  background: #2563eb;
  color: #ffffff;
  border: none;
  padding: 10px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 6px rgba(37, 99, 235, 0.2);
  transition: background-color 0.15s ease;
}
.btn-export-doc:hover { background: #1d4ed8; }

.btn-print {
  background: #ffffff; color: #334155; border: 1px solid #cbd5e1;
  padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600;
  cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
}
.btn-print:hover { background: #f8fafc; }

.toolbar-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03); }
.toolbar-grid { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
.filter-item { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.filter-item.search-box { flex: 2; min-width: 200px; }

label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }
input, select { width: 100%; padding: 9px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; background: #ffffff; color: #0f172a; }

.filter-actions { display: flex; gap: 8px; }
.btn-filter { background: #2563eb; color: #ffffff; border: none; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }

.stats-strip { display: flex; gap: 16px; margin-bottom: 20px; }
.stat-pill { background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px 16px; border-radius: 10px; font-size: 13px; color: #475569; display: flex; gap: 8px; }
.stat-pill strong { color: #0f172a; }

.table-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03); }
.state-container { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; color: #64748b; gap: 12px; }
.empty-icon { font-size: 40px; color: #cbd5e1; }

.table-responsive { width: 100%; overflow-x: auto; }
.report-table { width: 100%; border-collapse: collapse; }
.report-table th { text-align: left; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px; border-bottom: 1px solid #e2e8f0; }
.report-table td { padding: 14px 12px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; vertical-align: top; }

.date-cell { font-weight: 600; color: #0f172a; }
.office-tag { background: #eff6ff; color: #2563eb; font-size: 12px; font-weight: 700; padding: 3px 8px; border-radius: 6px; }
.whitespace-nowrap { white-space: nowrap; }
.desc-cell { line-height: 1.5; }
.remarks-cell { color: #64748b; font-size: 13px; }

.print-only { display: none; }
@media print {
  .print-hide { display: none !important; }
  .print-only { display: block !important; }
  .table-card { border: none; padding: 0; box-shadow: none; }
  .report-table th, .report-table td { border-bottom: 1px solid #000; }
  .printable-header { text-align: center; margin-bottom: 20px; }
  .print-org-title { font-size: 16px; font-weight: bold; }
  .print-report-title { font-size: 18px; font-weight: bold; margin-top: 4px; }
  .print-meta { font-size: 12px; color: #555; margin-top: 4px; }
}
</style>
