const fs = require( 'fs' );
const { test, expect } = require( '@playwright/test' );
const { EMCP_E2E_URL, EMCP_E2E_COOKIES } = process.env;
test.skip( ! EMCP_E2E_URL || ! EMCP_E2E_COOKIES, 'Requires a real local site and temporary cookies.' );

test.beforeEach( async ( { context } ) => {
	const cookies = JSON.parse( fs.readFileSync( EMCP_E2E_COOKIES, 'utf8' ) );
	const base = { domain: cookies.host, secure: true, httpOnly: true, sameSite: 'Lax' };
	await context.addCookies( [
		{ ...base, name: cookies.auth[ 0 ], value: cookies.auth[ 1 ], path: '/wp-admin' },
		{ ...base, name: cookies.logged[ 0 ], value: cookies.logged[ 1 ], path: '/' },
	] );
} );

test( 'Free Redirection integration and native plugin UI render', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-tools&tab=plugins' );
	await expect( page.getByText( 'Redirection Read', { exact: true } ) ).toBeVisible( { timeout: 30000 } );
	await expect( page.getByText( 'Redirection Write', { exact: true } ) ).toBeVisible();
	await page.goto( '/wp-admin/tools.php?page=redirection.php' );
	await expect( page.getByRole( 'heading', { name: 'Add redirect', exact: true } ) ).toBeVisible( { timeout: 30000 } );
	await expect( page.getByRole( 'textbox', { name: 'The relative URL you want to redirect from', exact: true } ) ).toBeVisible();
} );
