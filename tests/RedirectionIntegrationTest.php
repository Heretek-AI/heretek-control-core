<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/abilities/redirection/class-redirection-api.php';
require_once dirname( __DIR__ ) . '/includes/abilities/redirection/class-redirection-integration.php';

final class RedirectionIntegrationTest extends TestCase {
	protected function setUp(): void { emcp_test_reset(); }

	public function test_target_urls_refuse_credentials_protocol_relative_and_header_injection(): void {
		foreach ( array( '//evil.example/', 'javascript:alert(1)', 'https://user:secret@example.com/', "https://example.com/\r\nX-Test:yes", '/%0d%0aLocation:test', '/\\evil.example/', 'file:///tmp/file', '' ) as $url ) {
			$this->assertFalse( EMCP_Tools_Redirection_Api::url( $url ), $url );
		}
		$this->assertTrue( EMCP_Tools_Redirection_Api::url( '/destination/?page=2' ) );
		$this->assertTrue( EMCP_Tools_Redirection_Api::url( 'https://example.com/path#section' ) );
	}

	public function test_source_cannot_be_an_external_url_or_fragment(): void {
		$this->assertFalse( EMCP_Tools_Redirection_Api::url( 'https://example.com/source', true ) );
		$this->assertFalse( EMCP_Tools_Redirection_Api::url( '/source#fragment', true ) );
		$this->assertTrue( EMCP_Tools_Redirection_Api::url( '/source', true ) );
	}

	public function test_advanced_redirects_are_outside_the_mutation_scope(): void {
		$base = array( 'match_type' => 'url', 'action_type' => 'url', 'regex' => false, 'action_data' => array( 'url' => '/target' ), 'action_code' => 302 );
		$this->assertTrue( EMCP_Tools_Redirection_Api::supported( $base ) );
		foreach ( array( array( 'regex' => true ), array( 'match_type' => 'login' ), array( 'action_type' => 'pass' ), array( 'action_code' => 410 ) ) as $change ) {
			$this->assertFalse( EMCP_Tools_Redirection_Api::supported( array_replace( $base, $change ) ) );
		}
	}

	public function test_traffic_counters_do_not_change_configuration_versions(): void {
		$before = array( 'id' => 8, 'url' => '/source', 'hits' => 2, 'last_access' => 'yesterday' );
		$after = array_replace( $before, array( 'hits' => 3, 'last_access' => 'today' ) );
		$this->assertSame( EMCP_Tools_Redirection_Api::config( $before ), EMCP_Tools_Redirection_Api::config( $after ) );
		$this->assertNotSame( EMCP_Tools_Redirection_Api::config( $before ), EMCP_Tools_Redirection_Api::config( array_replace( $after, array( 'url' => '/changed' ) ) ) );
	}

	public function test_direct_callbacks_enforce_guest_and_disabled_tool_gates(): void {
		$tool = new EMCP_Tools_Redirection_Integration();
		$GLOBALS['emcp_test']['caps'] = array();
		$this->assertSame( 'forbidden', $tool->run_write( array() )->get_error_code() );
		$GLOBALS['emcp_test']['caps'] = array( 'manage_options' );
		update_option( 'emcp_tools_disabled_tools', array( 'emcp-tools/redirection-write' ) );
		$this->assertSame( 'tool_disabled', $tool->run_write( array() )->get_error_code() );
	}
}
