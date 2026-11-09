<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/abilities/litespeed/class-litespeed-api.php';
require_once dirname( __DIR__ ) . '/includes/abilities/litespeed/class-litespeed-integration.php';
final class LitespeedIntegrationTest extends TestCase {
	protected function setUp(): void { emcp_test_reset(); }
	public function test_only_unambiguous_paths_are_accepted(): void {
		foreach ( array( '/', '/article/', '/a%20b/' ) as $path ) { $this->assertTrue( EMCP_Tools_Litespeed_Api::valid_path( $path ), $path ); }
		foreach ( array( '', 'https://example.test/', '//example.test/', '/a?b=c', '/a#b', '/a/../b', '/%2e/', '/%252e/', '/%0d/', '/a\\b', '/%zz' ) as $path ) { $this->assertFalse( EMCP_Tools_Litespeed_Api::valid_path( $path ), $path ); }
	}
	public function test_purge_schema_excludes_broad_cache_and_configuration_writes(): void {
		$this->assertSame( array( 'path', 'confirm' ), EMCP_Tools_Litespeed_Integration::operation_schema( 'purge-path' )['required'] );
		$this->assertSame( array( 'status', 'purge-path', 'purge-page-cache' ), array_keys( EMCP_Tools_Litespeed_Integration::operations() ) );
		$this->assertFalse( EMCP_Tools_Litespeed_Integration::operation_schema( 'purge-page-cache' )['additionalProperties'] );
	}
	public function test_missing_dependency_and_guest_fail_closed(): void {
		$this->assertSame( 'plugin_inactive', EMCP_Tools_Litespeed_Api::ready()->get_error_code() );
		$GLOBALS['emcp_test']['caps'] = array(); $this->assertInstanceOf( WP_Error::class, ( new EMCP_Tools_Litespeed_Integration() )->run_read( array() ) );
	}
}
