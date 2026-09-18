// tests/e2e/specs/budget.cy.ts
describe('6IS Budget & R&M Monitoring (Phase 1B)', () => {
  beforeEach(() => {
    // Authenticate as Administrator
    cy.session('admin-session', () => {
      cy.visit('/login')
      cy.get('#username').type('Admin01')
      cy.get('#password').type('adminpassword01')
      cy.get('button[type="submit"]').click()
      cy.url().should('match', /\/(home|administrator|budget)/)
    })
  })

  it('navigates to /budget and displays executive summary cards with operational invariants', () => {
    cy.visit('/budget')
    cy.contains('h2', 'Budget & R&M Monitoring').should('exist')

    // Verify 4 summary KPI cards with operational figures
    cy.contains('.metric-card', 'Approved MOOE').should('contain.text', '667,875.00')
    cy.contains('.metric-card', 'Total Disbursed').should('contain.text', '148,408.00')
    cy.contains('.metric-card', 'Remaining Balance').should('contain.text', '519,467.00')
    cy.contains('.metric-card', 'Scheduled Releases').should('exist')
  })

  it('displays the 22 schedule items in Annual Fund Schedule Matrix', () => {
    cy.visit('/budget')
    cy.contains('.tab-btn', 'Annual Fund Schedule Matrix').should('have.class', 'active')
    cy.contains('.table-item-count', '22 Items').should('exist')
    cy.get('.data-table tbody tr').should('have.length.at.least', 20)

    // Verify monthly headers exist
    cy.contains('th', 'Jan').should('exist')
    cy.contains('th', 'Dec').should('exist')
    cy.contains('th', 'Allocated Budget').should('exist')
    cy.contains('th', 'Actual Disbursed').should('exist')
    cy.contains('th', 'Remaining Balance').should('exist')
  })

  it('switches to Released Funds Ledger and displays the 14 historical disbursements', () => {
    cy.visit('/budget')
    cy.contains('.tab-btn', 'Released Funds Ledger').click()
    cy.contains('.tab-btn', 'Released Funds Ledger').should('have.class', 'active')
    cy.contains('.table-item-count', '14 Transactions').should('exist')
    cy.get('.data-table tbody tr').should('have.length.at.least', 14)

    // Verify table columns
    cy.contains('th', 'Release Date').should('exist')
    cy.contains('th', 'Quarter').should('exist')
    cy.contains('th', 'Amount Disbursed').should('exist')
    cy.contains('th', 'Received By').should('exist')
  })

  it('can open and close the Record Release modal dialog', () => {
    cy.visit('/budget')
    cy.contains('button', 'Record Release').click()
    cy.get('.modal-card').should('exist')
    cy.contains('.modal-title', 'Record Fund Disbursement').should('exist')
    cy.contains('button', 'Cancel').click()
    cy.get('.modal-card').should('not.exist')
  })

  it('can open and close the Add Schedule modal with 0-12 monthly limits', () => {
    cy.visit('/budget')
    cy.contains('button', 'Add Schedule').click()
    cy.get('.modal-card').should('exist')
    cy.contains('.modal-title', 'Add Schedule Allocation').should('exist')

    // Verify monthly input fields enforce min="0" and max="12"
    cy.get('.month-number-input').first().should('have.attr', 'min', '0')
    cy.get('.month-number-input').first().should('have.attr', 'max', '12')

    cy.contains('button', 'Cancel').click()
    cy.get('.modal-card').should('not.exist')
  })
})
