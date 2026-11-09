<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/abilities/pods/class-pods-api.php';
require_once dirname( __DIR__ ) . '/includes/abilities/pods/class-pods-integration.php';
final class PodsFieldFixture extends ArrayObject {
	public function is_repeatable(): bool { return ! empty( $this['repeatable'] ); }
}
final class PodsIntegrationTest extends TestCase {
	protected function setUp(): void { emcp_test_reset(); }
	public function test_protected_names_and_scalar_boundary(): void {
		foreach ( array( '_secret', 'wp_custom', 'emcp_template', 'elementor_library', 'users', 'settings', 'attachment' ) as $name ) { $this->assertTrue( EMCP_Tools_Pods_Api::protected_name( $name ) ); }
		$this->assertFalse( EMCP_Tools_Pods_Api::protected_name( 'books' ) );
		$field = new PodsFieldFixture( array( 'name' => 'rating', 'type' => 'number', 'number_decimals' => 0 ) );
		$this->assertTrue( EMCP_Tools_Pods_Api::field_supported( $field ) );
		$field['number_decimals'] = 2; $this->assertFalse( EMCP_Tools_Pods_Api::field_supported( $field ) );
		$field['type'] = 'text'; $field['repeatable'] = true; $this->assertFalse( EMCP_Tools_Pods_Api::field_supported( $field ) );
		foreach ( array( 'pick', 'file', 'password', 'code', 'html' ) as $type ) { $field['type'] = $type; $field['repeatable'] = false; $this->assertFalse( EMCP_Tools_Pods_Api::field_supported( $field ) ); }
	}
	public function test_record_contract_cannot_supply_core_fields_or_query_code(): void {
		$schema = EMCP_Tools_Pods_Integration::operation_schema( 'update-record' );
		foreach ( array( 'post_status', 'post_author', 'sql', 'where', 'code', 'fields' ) as $key ) { $this->assertArrayNotHasKey( $key, $schema['properties'] ); }
		$this->assertContains( 'version', $schema['required'] ); $this->assertContains( 'confirm', $schema['required'] ); $this->assertFalse( $schema['additionalProperties'] );
		$this->assertFalse( $schema['properties']['values']['items']['additionalProperties'] );
	}
	public function test_absent_dependency_fails_closed(): void {
		$this->assertFalse( EMCP_Tools_Pods_Api::active() );
		$this->assertSame( 'plugin_inactive', EMCP_Tools_Pods_Api::ready()->get_error_code() );
		$GLOBALS['emcp_test']['caps'] = array();
		$this->assertInstanceOf( WP_Error::class, ( new EMCP_Tools_Pods_Integration() )->run_read( array() ) );
	}
}
