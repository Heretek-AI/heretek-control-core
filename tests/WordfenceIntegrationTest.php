<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/abilities/wordfence/class-wordfence-api.php';
require_once dirname( __DIR__ ) . '/includes/abilities/wordfence/class-wordfence-integration.php';
final class WordfenceIntegrationTest extends TestCase {
	protected function setUp(): void { emcp_test_reset(); }
	public function test_blocks_never_expose_network_or_pattern_data(): void {
		$row = (object) array( 'id' => 2, 'type' => 1, 'blockedTime' => 4, 'lastAttempt' => 5, 'blockedHits' => 6, 'expiration' => 7, 'ip' => '203.0.113.254', 'reason' => 'secret', 'parameters' => array( 'referrer' => 'token' ) );
		$this->assertSame( array( 'id', 'type', 'blockedTime', 'lastAttempt', 'blockedHits', 'expiration' ), array_keys( EMCP_Tools_Wordfence_Api::block( $row ) ) );
	}
	public function test_only_bounded_read_operations_are_exposed(): void {
		$tool = new EMCP_Tools_Wordfence_Integration(); $this->assertSame( array( 'emcp-tools/wordfence-read' ), $tool->get_ability_names() );
		$this->assertSame( 100, EMCP_Tools_Wordfence_Integration::operation_schema( 'list-findings' )['properties']['limit']['maximum'] );
		$this->assertSame( array( 'id' ), EMCP_Tools_Wordfence_Integration::operation_schema( 'get-finding' )['required'] );
		foreach ( array( 'start-scan', 'repair', 'delete-file', 'block-ip', 'settings' ) as $operation ) { $this->assertArrayNotHasKey( $operation, EMCP_Tools_Wordfence_Integration::operations() ); }
	}
	public function test_missing_dependency_and_guest_fail_closed(): void {
		$this->assertSame( 'plugin_inactive', EMCP_Tools_Wordfence_Api::ready()->get_error_code() );
		$GLOBALS['emcp_test']['caps'] = array(); $this->assertInstanceOf( WP_Error::class, ( new EMCP_Tools_Wordfence_Integration() )->run_read( array() ) );
	}
}
