<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/abilities/cptui/class-cptui-api.php';
require_once dirname( __DIR__ ) . '/includes/abilities/cptui/class-cptui-integration.php';
final class CptuiIntegrationTest extends TestCase {
	protected function setUp(): void { emcp_test_reset(); }
	public function test_executable_and_privileged_definitions_are_not_mutable(): void {
		$row = array( 'name' => 'books', 'capability_type' => 'post' );
		$this->assertTrue( EMCP_Tools_Cptui_Api::safe( 'post-type', $row ) );
		$this->assertFalse( EMCP_Tools_Cptui_Api::safe( 'post-type', $row + array( 'extension_setting' => 'preserve' ) ) );
		foreach ( array( 'register_meta_box_cb', 'rest_controller_class', 'custom_supports', 'meta_box_cb', 'default_term' ) as $field ) {
			$this->assertFalse( EMCP_Tools_Cptui_Api::safe( 'post-type', $row + array( $field => 'custom' ) ), $field );
		}
		$this->assertFalse( EMCP_Tools_Cptui_Api::safe( 'post-type', array_replace( $row, array( 'capability_type' => 'administrator' ) ) ) );
	}
	public function test_update_contract_cannot_change_ownership_or_registration(): void {
		$schema = EMCP_Tools_Cptui_Integration::operation_schema( 'update-post-type' );
		foreach ( array( 'object_types', 'supports', 'public', 'show_in_rest', 'capability_type', 'register_meta_box_cb' ) as $key ) { $this->assertArrayNotHasKey( $key, $schema['properties'] ); }
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertContains( 'version', $schema['required'] );
		$this->assertContains( 'confirm', $schema['required'] );
		$this->assertArrayHasKey( 'description', $schema['properties'] );
	}
	public function test_native_safe_creation_defaults(): void {
		$row = EMCP_Tools_Cptui_Api::defaults( 'post-type', 'books' );
		$this->assertSame( 'post', $row['capability_type'] );
		$this->assertSame( '', $row['register_meta_box_cb'] );
		$this->assertSame( '', $row['rest_controller_class'] );
		$this->assertSame( 'false', $row['delete_with_user'] );
		$this->assertSame( array( 'title', 'editor' ), $row['supports'] );
	}
	public function test_absent_plugin_and_guest_fail_closed(): void {
		$this->assertFalse( EMCP_Tools_Cptui_Api::active() );
		$this->assertSame( 'plugin_inactive', EMCP_Tools_Cptui_Api::ready()->get_error_code() );
		$GLOBALS['emcp_test']['caps'] = array();
		$this->assertInstanceOf( WP_Error::class, ( new EMCP_Tools_Cptui_Integration() )->run_write( array() ) );
	}
}
