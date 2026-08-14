<template>
  <MainLayout title="Monthly Summary" username="Admin">
    <div class="report-page-container">

<<<<<<< HEAD
      <!-- Header & Action Buttons -->
=======
      <!-- Header -->
>>>>>>> module/login
      <div class="module-header-bar print-hide">
        <div>
          <h2>Monthly Accomplishment Summary</h2>
          <p class="subtitle">Breakdown of accomplishments and outgoing communications for the month.</p>
        </div>
<<<<<<< HEAD
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
=======
>>>>>>> module/login
      </div>

      <!-- Toolbar / Selectors (Year & Month ONLY) -->
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

        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-container">
        <ion-spinner name="crescent"></ion-spinner>
        <span>Loading monthly summary statistics...</span>
      </div>

      <!-- 2-COLUMN DASHBOARD LAYOUT -->
      <div v-else class="dashboard-grid-layout">

        <!-- LEFT MAIN COLUMN: Activities & Outgoing Comms -->
        <div class="main-column">

          <!-- GROUP 1: Activities for the Month -->
          <div class="summary-card-group">
            <div class="group-header">
              <div class="header-icon-box accomplishment-bg">
                <ion-icon :icon="checkmarkDoneCircleOutline"></ion-icon>
              </div>
              <div>
                <h3>Activities</h3>
                <p class="group-subtitle">Total activities recorded in {{ selectedMonthName }} {{ selectedYear }}</p>
              </div>
              <div class="total-badge count-accomplishment">
                <span>Total:</span>
                <strong>{{ totalAccomplishments }}</strong>
              </div>
            </div>

            <div v-if="activeAccomplishmentsByCategory.length === 0" class="empty-category-msg">
              No activities recorded for {{ selectedMonthName }} {{ selectedYear }}.
            </div>

            <div v-else class="compact-category-grid">
              <div 
                v-for="item in activeAccomplishmentsByCategory" 
                :key="item.category_id" 
                class="category-stat-card accomplishment-card"
              >
                <div class="cat-card-top">
                  <h4 class="category-title-code">{{ item.category_code || item.category_name }}</h4>
                  <span class="cat-count-badge">{{ item.count }}</span>
                </div>
                <div class="cat-card-body">
                  <div class="cat-progress-bar">
                    <div 
                      class="progress-fill fill-acc" 
                      :style="{ width: calculatePercentage(item.count, totalAccomplishments) + '%' }"
                    ></div>
                  </div>
                  <div class="cat-footer">
                    <span class="percentage-text">{{ calculatePercentage(item.count, totalAccomplishments) }}% of monthly activities</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- GROUP 2: Outgoing Communications for the Month -->
          <div class="summary-card-group">
            <div class="group-header">
              <div class="header-icon-box outgoing-bg">
                <ion-icon :icon="paperPlaneOutline"></ion-icon>
              </div>
              <div>
                <h3>Outgoing Communications</h3>
                <p class="group-subtitle">Total outgoing communications in {{ selectedMonthName }} {{ selectedYear }}</p>
              </div>
              <div class="total-badge count-outgoing">
                <span>Total Outgoing:</span>
                <strong>{{ totalOutgoingComms }}</strong>
              </div>
            </div>

            <div v-if="activeOutgoingCommsByCategory.length === 0" class="empty-category-msg">
              No outgoing communications recorded for {{ selectedMonthName }} {{ selectedYear }}.
            </div>

            <div v-else class="compact-category-grid">
              <div 
                v-for="item in activeOutgoingCommsByCategory" 
                :key="item.category_id" 
                class="category-stat-card outgoing-card"
              >
                <div class="cat-card-top">
                  <h4 class="category-title-code tag-outgoing-text">{{ item.category_code || item.category_name }}</h4>
                  <span class="cat-count-badge count-bg-outgoing">{{ item.count }}</span>
                </div>
                <div class="cat-card-body">
                  <div class="cat-progress-bar">
                    <div 
                      class="progress-fill fill-outgoing" 
                      :style="{ width: calculatePercentage(item.count, totalOutgoingComms) + '%' }"
                    ></div>
                  </div>
                  <div class="cat-footer">
                    <span class="percentage-text">{{ calculatePercentage(item.count, totalOutgoingComms) }}% of monthly outgoing comms</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT SIDEBAR COLUMN: Clearances & Executive Quick Stats -->
        <div class="sidebar-column">

          <!-- GROUP 3: Clearances (Access Pass Outgoing Comms) -->
          <div class="summary-card-group sidebar-group">
            <div class="group-header">
              <div class="header-icon-box clearance-bg">
                <ion-icon :icon="shieldCheckmarkOutline"></ion-icon>
              </div>
              <div>
                <h3>Clearances</h3>
                <p class="group-subtitle">Access Pass count in {{ selectedMonthName }} {{ selectedYear }}</p>
              </div>
            </div>

            <div v-if="activeClearancesByPurpose.length === 0" class="empty-category-msg">
              No Access Pass clearances recorded for {{ selectedMonthName }} {{ selectedYear }}.
            </div>

            <div v-else class="clearance-single-card">
              <div class="clearance-hero-box">
                <div class="clearance-number">{{ totalClearances }}</div>
                <div class="clearance-label">Released Access Pass Clearances</div>
                <div class="clearance-subtext">{{ selectedMonthName }} {{ selectedYear }}</div>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IonSpinner, IonIcon, onIonViewWillEnter } from '@ionic/vue'
<<<<<<< HEAD
import { printOutline, clipboardOutline, documentTextOutline } from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { AccomplishmentItem, AccomplishmentOptions } from '../../types/accomplishment'
import { fetchMonthlyAccomplishments, fetchAccomplishmentOptions, exportMonthlyReportDocx } from '../../services/accomplishmentService'
=======
import { 
  checkmarkDoneCircleOutline, 
  paperPlaneOutline,
  shieldCheckmarkOutline 
} from 'ionicons/icons'

import MainLayout from '../../layouts/MainLayout.vue'
import type { 
  AccomplishmentCategorySummary, 
  OutgoingCommCategorySummary,
  ClearancePurposeSummary 
} from '../../types/accomplishment'
import { fetchMonthlyAccomplishments } from '../../services/accomplishmentService'
>>>>>>> module/login

const route = useRoute()

const loading = ref(true)
<<<<<<< HEAD
const isGeneratingDocx = ref(false)
const records = ref<AccomplishmentItem[]>([])
const commsStats = reactive({ incoming: 10, outgoing: 10 })
=======
const accomplishmentsByCategory = ref<AccomplishmentCategorySummary[]>([])
const outgoingCommsByCategory = ref<OutgoingCommCategorySummary[]>([])
const clearancesByPurpose = ref<ClearancePurposeSummary[]>([])
>>>>>>> module/login

const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)

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
<<<<<<< HEAD
=======
})

const totalAccomplishments = computed(() => {
  return accomplishmentsByCategory.value.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
})

const totalOutgoingComms = computed(() => {
  return outgoingCommsByCategory.value.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
})

const totalClearances = computed(() => {
  return clearancesByPurpose.value.reduce((sum, item) => sum + (Number(item.count) || 0), 0)
})

const activeAccomplishmentsByCategory = computed(() => {
  return accomplishmentsByCategory.value.filter(item => (Number(item.count) || 0) > 0)
})

const activeOutgoingCommsByCategory = computed(() => {
  return outgoingCommsByCategory.value.filter(item => (Number(item.count) || 0) > 0)
})

const activeClearancesByPurpose = computed(() => {
  return clearancesByPurpose.value.filter(item => (Number(item.count) || 0) > 0)
>>>>>>> module/login
})

onMounted(() => {
  loadData()
})

onIonViewWillEnter(() => {
  loadData()
})

watch(() => route.fullPath, () => {
  loadData()
})

async function loadData() {
  loading.value = true
  const res = await fetchMonthlyAccomplishments(
    selectedYear.value,
    selectedMonth.value
  )
  loading.value = false

  if (res.success && res.data) {
<<<<<<< HEAD
    records.value = res.data.records || []
    commsStats.incoming = res.data.communications_stats?.incoming || 10
    commsStats.outgoing = res.data.communications_stats?.outgoing || 10
=======
    accomplishmentsByCategory.value = res.data.accomplishments_by_category || []
    outgoingCommsByCategory.value = res.data.outgoing_comms_by_category || []
    clearancesByPurpose.value = res.data.clearances_by_purpose || []
>>>>>>> module/login
  }
}

function calculatePercentage(count: number, total: number): number {
  if (!total || total === 0) return 0
  return Math.round(((Number(count) || 0) / total) * 100)
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
<<<<<<< HEAD
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
=======
.report-page-container {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
>>>>>>> module/login
}

.module-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

<<<<<<< HEAD
label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }
input, select { width: 100%; padding: 9px 12px; font-size: 14px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; background: #ffffff; color: #0f172a; }
=======
.module-header-bar h2 {
  font-size: 24px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px 0;
}
>>>>>>> module/login

.subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.action-btn-group {
  display: flex;
  gap: 10px;
}

.btn-disabled {
  background: #f1f5f9;
  color: #94a3b8;
  border: 1px solid #cbd5e1;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: not-allowed;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  opacity: 0.7;
}

.toolbar-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 24px;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
}

.toolbar-grid {
  display: flex;
  gap: 20px;
  align-items: flex-end;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
}

label {
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

select {
  width: 100%;
  padding: 9px 12px;
  font-size: 14px;
  font-weight: 600;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px;
  color: #64748b;
  gap: 12px;
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
}

/* 2-COLUMN DASHBOARD GRID */
.dashboard-grid-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
  align-items: start;
}

@media (max-width: 1024px) {
  .dashboard-grid-layout {
    grid-template-columns: 1fr;
  }
}

.main-column, .sidebar-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.summary-card-group {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
}

.group-header {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 14px;
}

.header-icon-box {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.accomplishment-bg {
  background: #eff6ff;
  color: #2563eb;
}

.outgoing-bg {
  background: #f0fdf4;
  color: #16a34a;
}

.clearance-bg {
  background: #faf5ff;
  color: #9333ea;
}

.group-header h3 {
  font-size: 17px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 2px 0;
}

.group-subtitle {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

.total-badge {
  margin-left: auto;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.count-accomplishment {
  background: #eff6ff;
  color: #1e40af;
  border: 1px solid #bfdbfe;
}

.count-outgoing {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.empty-category-msg {
  padding: 20px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
}

.compact-category-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}

.category-stat-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 12px 14px;
  transition: all 0.2s ease;
  background: #ffffff;
}

.category-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}

.accomplishment-card {
  border-left: 4px solid #2563eb;
}

.outgoing-card {
  border-left: 4px solid #16a34a;
}

.cat-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.category-title-code {
  font-size: 15px;
  font-weight: 800;
  color: #1e293b;
  margin: 0;
  letter-spacing: 0.02em;
}

.tag-outgoing-text {
  color: #166534;
}

.cat-count-badge {
  background: #2563eb;
  color: #ffffff;
  font-size: 15px;
  font-weight: 800;
  padding: 1px 9px;
  border-radius: 14px;
}

.count-bg-outgoing {
  background: #16a34a;
}

.cat-progress-bar {
  width: 100%;
  height: 5px;
  background: #f1f5f9;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 6px;
}

.progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s ease;
}

.fill-acc {
  background: #2563eb;
}

.fill-outgoing {
  background: #16a34a;
}

.cat-footer {
  display: flex;
  justify-content: flex-end;
}

.percentage-text {
  font-size: 11px;
  color: #64748b;
  font-weight: 600;
}

/* SIDEBAR HERO BOX & QUICK STATS */
.clearance-hero-box {
  background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
  border: 1px solid #e9d5ff;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.clearance-number {
  font-size: 42px;
  font-weight: 900;
  color: #7e22ce;
  line-height: 1;
  margin-bottom: 6px;
}

.clearance-label {
  font-size: 14px;
  font-weight: 800;
  color: #581c87;
  margin-bottom: 4px;
}

.clearance-subtext {
  font-size: 12px;
  color: #7e22ce;
  font-weight: 600;
}

.stats-widget-group {
  background: #ffffff;
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
}

.widget-header h4 {
  font-size: 15px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.widget-period-tag {
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.widget-metrics-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.widget-metric-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #f1f5f9;
}

.metric-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.metric-name {
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
}

.metric-sub {
  font-size: 11px;
  color: #64748b;
}

.metric-val {
  font-size: 18px;
  font-weight: 800;
}

.text-blue { color: #2563eb; }
.text-indigo { color: #4f46e5; }
.text-emerald { color: #059669; }

.print-hide {
  display: block;
}
</style>
