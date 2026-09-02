describe('6IS Application Root & Auth Check', () => {
  it('Visits the app root url and redirects to login', () => {
    cy.visit('/')
    cy.url().should('include', '/login')
  })
})
