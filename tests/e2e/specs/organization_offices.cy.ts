// tests/e2e/specs/organization_offices.cy.ts
describe('6IS Organization & Office Management (Phase 3)', () => {
  beforeEach(() => {
    // Authenticate as Administrator
    cy.session('admin-session', () => {
      cy.visit('/login')
      cy.get('#username').type('Admin01')
      cy.get('#password').type('adminpassword01')
      cy.get('button[type="submit"]').click()
      cy.url().should('include', '/home')
    })
  })

  it('navigates from administrator overview to Organization Profile', () => {
    cy.visit('/administrator')
    cy.contains('Organization Profile').scrollIntoView().click({ force: true })
    cy.url().should('include', '/administrator/organization')
    cy.contains('h2', 'Organization Profile').should('exist')
    cy.contains('6th Infantry Division').should('exist')
    cy.contains('.org-badge', '6ID').should('exist')
  })

  it('navigates from administrator overview to Offices Management and lists offices', () => {
    cy.visit('/administrator')
    cy.contains('Offices Management').scrollIntoView().click({ force: true })
    cy.url().should('include', '/administrator/offices')
    cy.contains('h2', 'Offices Management').should('exist')
    cy.get('.office-code-badge').should('have.length.at.least', 1)
  })

  it('can open and close Register New Office modal dialog', () => {
    cy.visit('/administrator/offices')
    cy.contains('button', 'Add New Office').click({ force: true })
    cy.get('.modal-dialog').should('exist')
    cy.contains('Register New Office').should('exist')
    cy.contains('button', 'Cancel').click({ force: true })
    cy.get('.modal-dialog').should('not.exist')
  })

  it('displays office column and office filter in User Management', () => {
    cy.visit('/administrator/users')
    cy.contains('th', 'Office').should('exist')
    cy.get('#userOfficeFilter').should('exist')
  })
})
