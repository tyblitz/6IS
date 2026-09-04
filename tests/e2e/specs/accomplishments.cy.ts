// tests/e2e/specs/accomplishments.cy.ts
describe('Accomplishments & Operational Reporting (Phase 5)', () => {
  beforeEach(() => {
    cy.session('admin-session', () => {
      cy.visit('/login')
      cy.get('#username').type('Admin01')
      cy.get('#password').type('adminpassword01')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', '/home')
    })
  })

  it('navigates to Daily Accomplishments view and displays page header with Print button', () => {
    cy.visit('/accomplishments/daily?date=2026-08-10')
    cy.contains('h2', 'Daily Report').should('exist')
    cy.get('.header-action-group .btn-print').should('exist').and('contain', 'Print Report')
    cy.get('.header-action-group .add-btn').should('exist').and('contain', 'Add Activity')
    cy.get('.toolbar-card').should('exist')
  })

  it('renders daily records table with view and edit action buttons', () => {
    cy.visit('/accomplishments/daily?date=2026-08-10')
    cy.get('.loading-container').should('not.exist')
    cy.get('.report-table').should('exist')
    cy.get('.report-table tbody tr').should('have.length.at.least', 1)

    cy.get('.report-table tbody tr').first().within(() => {
      cy.get('.view-btn').should('exist')
      cy.get('.edit-btn').should('exist')
      cy.get('.delete-btn').should('exist')
    })
  })

  it('opens inline AccomplishmentFormModal when clicking Add Activity button', () => {
    cy.visit('/accomplishments/daily?date=2026-08-10')
    cy.get('.header-action-group .add-btn').click()
    cy.get('.modal-card').should('be.visible')
    cy.get('.modal-header h3').should('contain', 'Add Daily Accomplishment')
    cy.get('#office_id').should('exist')
    cy.get('#category_id').should('exist')
    cy.get('#date').should('exist')
    cy.get('#description').should('exist')
    cy.get('.btn-cancel').click()
    cy.get('.modal-backdrop').should('not.exist')
  })

  it('opens inline AccomplishmentFormModal when clicking Edit button on a table row', () => {
    cy.visit('/accomplishments/daily?date=2026-08-10')
    cy.get('.loading-container').should('not.exist')
    cy.get('.report-table tbody tr .edit-btn').first().click({ force: true })
    cy.get('.modal-card').should('be.visible')
    cy.get('.modal-header h3').should('contain', 'Edit Daily Accomplishment')
    cy.get('#description').invoke('val').should('not.be.empty')
    cy.get('.btn-cancel').click()
  })

  it('navigates to Monthly Summary view and displays Export DOCX and Print buttons', () => {
    cy.visit('/accomplishments/monthly')
    cy.contains('h2', 'Monthly Accomplishment Summary').should('exist')
    cy.get('.action-btn-group .btn-export-doc').should('exist').and('contain', 'Export DOCX Report')
    cy.get('.action-btn-group .btn-print').should('exist').and('contain', 'Print Report')
    cy.get('.toolbar-card').should('exist')
    cy.get('.summary-card-group').should('have.length.at.least', 1)
  })

  it('navigates to Quarterly Summary view and renders print button', () => {
    cy.visit('/accomplishments/quarterly')
    cy.contains('h2', 'Quarterly Accomplishment Summary').should('exist')
    cy.get('.action-btn-group .btn-print').should('exist').and('contain', 'Print Report')
  })

  it('navigates to Annual Summary view and renders print button', () => {
    cy.visit('/accomplishments/annual')
    cy.contains('h2', 'Annual Accomplishment Summary').should('exist')
    cy.get('.action-btn-group .btn-print').should('exist').and('contain', 'Print Report')
  })

  it('navigates to Custom Period Summary view and renders print button', () => {
    cy.visit('/accomplishments/custom')
    cy.contains('h2', 'Custom Period Accomplishment Summary').should('exist')
    cy.get('.action-btn-group .btn-print').should('exist').and('contain', 'Print Report')
  })

  it('executes complete Accomplishment CRUD flow with category selection and persistence verification', () => {
    const uniqueId = Date.now()
    const testDesc = `E2E Verified Activity ${uniqueId}`
    const updatedDesc = `E2E Verified Activity ${uniqueId} [UPDATED]`
    const testDate = '2026-08-10'

    // 1. Visit Daily Report
    cy.visit(`/accomplishments/daily?date=${testDate}`)
    cy.get('.loading-container').should('not.exist')

    // 2. Open Add Activity Modal
    cy.get('.header-action-group .add-btn').click()
    cy.get('.modal-card').should('be.visible')

    // 3. Verify category & office options are actually loaded (> 1 option)
    cy.get('#category_id option').should('have.length.at.least', 2)
    cy.get('#office_id option').should('have.length.at.least', 2)

    // 4. Fill form with specific category (Category ID 2: ICT Repair)
    cy.get('#office_id').select(1)
    cy.get('#category_id').select(2)
    cy.get('#date').clear().type(testDate)
    cy.get('#description').type(testDesc)
    cy.get('#remarks').type('E2E Audit Remarks')

    // 5. Submit Form
    cy.get('.modal-footer .btn-save').click()
    cy.get('.modal-backdrop').should('not.exist')

    // 6. Verify created record appears in table with category
    cy.contains('.desc-cell', testDesc).should('exist')
    cy.contains('tr', testDesc).within(() => {
      cy.get('.category-tag').should('not.be.empty')
    })

    // 7. Open Edit modal for the newly created record
    cy.contains('tr', testDesc).within(() => {
      cy.get('.edit-btn').click({ force: true })
    })
    cy.get('.modal-card').should('be.visible')
    cy.get('#description').should('have.value', testDesc)

    // 8. Update description and change category (to Category ID 1: PAS)
    cy.get('#category_id').select(1)
    cy.get('#description').clear().type(updatedDesc)
    cy.get('.modal-footer .btn-save').click()
    cy.get('.modal-backdrop').should('not.exist')

    // 9. Verify updated description and category persist in the table
    cy.contains('.desc-cell', updatedDesc).should('exist')
    cy.contains('tr', updatedDesc).within(() => {
      cy.get('.category-tag').should('contain', 'PAS')
    })

    // 10. Clean up: Delete the test record
    cy.contains('tr', updatedDesc).within(() => {
      cy.get('.delete-btn').click({ force: true })
    })

    // 11. Confirm deletion from table
    cy.contains('.desc-cell', updatedDesc).should('not.exist')
  })
})
