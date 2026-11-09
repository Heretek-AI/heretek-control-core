<?php
/** Real Wordfence + MCP fixture. No scans or licence provisioning. */
if ( 'http://localhost:18880' !== home_url() || 'local' !== wp_get_environment_type() || wfCentral::isConnected() ) { throw new RuntimeException( 'Isolated disconnected local fixture only.' ); }
require_once __DIR__ . '/local-mcp-harness.php';
global $wpdb;
$test = new EMCP_Local_Mcp_Test( array( 'emcp-tools/wordfence-read' ) );
$issues = new wfIssues(); $ids = array(); $block_id = 0; $token = wp_generate_uuid4(); $ip = '203.0.113.254';
$blocks_before = wfConfig::getInt( 'totalIPsBlocked', 0, false );
try {
	$test->check( ! wp_is_application_passwords_available(), 'native default application-password restriction retained' );
	$test->use_oauth();
	foreach ( array( 0, 1 ) as $index ) {
		$result = $issues->addIssue( 'diskSpace', 25, $token . $index, $token . $index, 'PRIVATE-' . $token, 'secret body', array( 'password' => $token, 'file' => '/private/path' ) ); $id = (int) $wpdb->insert_id;
		if ( wfIssues::ISSUE_ADDED !== $result || ! $id ) { throw new RuntimeException( 'Cannot create native benign issue.' ); } $ids[] = $id;
	}
	if ( wfBlock::findIPBlock( $ip ) ) { throw new RuntimeException( 'Reserved fixture address already blocked; untouched.' ); }
	wfBlock::createIP( $token, $ip, 300 ); $block = wfBlock::findIPBlock( $ip );
	if ( ! $block || $token !== $block->reason ) { throw new RuntimeException( 'Cannot identify owned block fixture.' ); } $block_id = (int) $block->id;
	$test->initialize(); $tool = $test->tool( 'wordfence-read' );
	$call = static function ( $op, $args = array() ) use ( $test, $tool ) { return $test->rpc( 'tools/call', array( 'name' => $tool, 'arguments' => array( 'operation' => $op, 'arguments' => (object) $args ) ) ); };
	foreach ( array( 'status', 'firewall', 'scan-status', 'list-blocks' ) as $op ) {
		$reply = $call( $op ); $data = EMCP_Local_Mcp_Test::data( $reply );
		$test->check( empty( $reply['result']['isError'] ) && ! empty( $data ), 'native ' . $op . ' through MCP with firewall active' );
		$encoded = wp_json_encode( $data ); $test->check( ! str_contains( $encoded, $token ) && ! str_contains( $encoded, $ip ), 'private data redacted from ' . $op );
	}
	$a = EMCP_Local_Mcp_Test::data( $call( 'list-findings', array( 'limit' => 1 ) ) );
	$b = EMCP_Local_Mcp_Test::data( $call( 'list-findings', array( 'limit' => 1, 'offset' => 1 ) ) );
	$test->check( 1 === count( $a['items'] ) && 1 === count( $b['items'] ) && $a['items'][0]['id'] !== $b['items'][0]['id'] && $a['total'] === $b['total'], 'bounded findings pagination and totals' );
	$r = EMCP_Local_Mcp_Test::data( $call( 'get-finding', array( 'id' => $ids[0] ) ) );
	$test->check( $ids[0] === $r['id'] && 'diskSpace' === $r['type'] && ! str_contains( wp_json_encode( $r ), $token ) && ! isset( $r['data'], $r['shortMsg'], $r['longMsg'] ), 'single finding preserves native metadata and excludes payload' );
	$bad = $call( 'start-scan' ); $test->check( ! empty( $bad['result']['isError'] ), 'scan initiation is unavailable' );
	$bad = $call( 'list-findings', array( 'limit' => 101 ) ); $test->check( ! empty( $bad['result']['isError'] ), 'unbounded read rejected' );
	update_option( 'emcp_tools_strict_schemas', '1' ); $args = array_fill_keys( array_keys( EMCP_Tools_Wordfence_Integration::fields() ), null ); $args['id'] = $ids[0];
	$r = EMCP_Local_Mcp_Test::data( $call( 'get-finding', $args ) ); $test->check( $ids[0] === $r['id'], 'strict null placeholders supported' );
	update_option( 'emcp_tools_strict_schemas', '0' );
	wp_set_current_user( 0 ); $denied = ( new EMCP_Tools_Wordfence_Integration() )->run_read( array( 'operation' => 'status' ) ); $test->check( is_wp_error( $denied ) && 'forbidden' === $denied->get_error_code(), 'direct guest denied' );
	wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
	$mutations = array(); $observer = static function ( $query ) use ( &$mutations ) { if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $query ) ) { $mutations[] = $query; } return $query; };
	add_filter( 'query', $observer ); try { foreach ( array( 'status', 'firewall', 'scan-status', 'list-findings', 'list-blocks' ) as $op ) { EMCP_Tools_Wordfence_Api::read( $op, array() ); } } finally { remove_filter( 'query', $observer ); }
	$test->check( ! $mutations, 'all read operations perform no database writes' );
	update_option( 'emcp_tools_disabled_tools', array_merge( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/wordfence-read' ) ) );
	$bad = $call( 'status' ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'disabled reader refused' );
} finally {
	foreach ( $ids as $id ) { $issues->deleteIssue( $id ); }
	if ( $block_id ) { wfBlock::removeBlockIDs( array( $block_id ), false, false ); }
	if ( $blocks_before + 1 === wfConfig::getInt( 'totalIPsBlocked', 0, false ) ) { wfConfig::set( 'totalIPsBlocked', $blocks_before ); }
	$test->close();
}
WP_CLI::success( $test->checks . ' real Wordfence MCP checks passed.' );
