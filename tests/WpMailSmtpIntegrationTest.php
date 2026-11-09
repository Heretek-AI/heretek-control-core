<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/abilities/wp-mail-smtp/class-wp-mail-smtp-api.php';
require_once dirname( __DIR__ ) . '/includes/abilities/wp-mail-smtp/class-wp-mail-smtp-integration.php';
final class WpMailSmtpIntegrationTest extends TestCase {
	protected function setUp(): void { emcp_test_reset(); }
	public function test_receipts_never_expose_private_fields(): void {
		$row = EMCP_Tools_Wp_Mail_Smtp_Api::public_receipt( array( 'request_id' => 'fixture', 'state' => 'unknown', 'actor' => 1, 'hash' => 'private', 'password' => 'secret', 'body' => 'private', 'created_at' => 1, 'change_id' => 'audit' ) );
		$this->assertSame( array( 'request_id', 'state', 'created_at', 'change_id' ), array_keys( $row ) );
	}
	public function test_schema_requires_explicit_unique_send_and_has_no_settings_surface(): void {
		$schema = EMCP_Tools_Wp_Mail_Smtp_Integration::operation_schema( 'send-test' );
		$this->assertSame( array( 'request_id', 'recipient', 'confirm' ), $schema['required'] );
		foreach ( array( 'password', 'host', 'settings', 'body', 'subject', 'resend' ) as $field ) { $this->assertArrayNotHasKey( $field, $schema['properties'] ); }
		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertSame( EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( 'ABC' ), EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( 'abc' ) );
	}
	public function test_dependency_and_guest_fail_closed(): void {
		$this->assertFalse( EMCP_Tools_Wp_Mail_Smtp_Api::active() ); $this->assertSame( 'plugin_inactive', EMCP_Tools_Wp_Mail_Smtp_Api::ready()->get_error_code() );
		$GLOBALS['emcp_test']['caps'] = array(); $this->assertInstanceOf( WP_Error::class, ( new EMCP_Tools_Wp_Mail_Smtp_Integration() )->run_read( array() ) );
	}
}
