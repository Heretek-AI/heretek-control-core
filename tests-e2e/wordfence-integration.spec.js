const fs = require( 'fs' );
const { test, expect } = require( '@playwright/test' );
const { EMCP_E2E_URL, EMCP_E2E_COOKIES } = process.env;
test.skip( ! EMCP_E2E_URL || ! EMCP_E2E_COOKIES, 'Requires a real local site and temporary cookies.' );

test.beforeEach( async ( { context } ) => {
	const cookies = JSON.parse( fs.readFileSync( EMCP_E2E_COOKIES, 'utf8' ) );
	const base = { domain: cookies.host, secure: cookies.secure, httpOnly: true, sameSite: 'Lax' };
	await context.addCookies( [
		{ ...base, name: cookies.auth[ 0 ], value: cookies.auth[ 1 ], path: '/wp-admin' },
		{ ...base, name: cookies.logged[ 0 ], value: cookies.logged[ 1 ], path: '/' },
	] );
} );

test( 'Free Wordfence integration and native plugin UI render', async ( { page } ) => {
	// A new Tools Free fixture must finish its optional telemetry screen first.
	await page.goto( '/wp-admin/admin.php?page=emcp-tools' );
	const skip = page.getByText( 'Skip', { exact: true } );
	if ( await skip.isVisible() ) { await skip.click(); await page.waitForLoadState( 'domcontentloaded' ); }
	await page.goto( '/wp-admin/admin.php?page=emcp-tools-tools&tab=plugins' );
	await expect( page.getByRole( 'switch', { name: 'Wordfence Read', exact: true } ) ).toBeVisible( { timeout: 30000 } );
	await page.goto( '/wp-admin/admin.php?page=Wordfence' );
	await expect( page.getByRole( 'heading', { name: 'Wordfence Dashboard', exact: true } ) ).toBeVisible( { timeout: 30000 } );
} );
