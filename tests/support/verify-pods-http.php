<?php
require_once __DIR__ . '/local-mcp-harness.php';
$test = new EMCP_Local_Mcp_Test( array( 'emcp-tools/pods-read', 'emcp-tools/pods-write' ) );
$name = 'httppod_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ); $history = array(); $pod_id = 0; $item_id = 0;
try {
	$test->initialize(); $read = $test->tool( 'pods-read' ); $write = $test->tool( 'pods-write' );
	$call = static function ( $tool, $op, $args = array() ) use ( $test, &$history ) {
		$reply = $test->rpc( 'tools/call', array( 'name' => $tool, 'arguments' => array( 'operation' => $op, 'arguments' => (object) $args ) ) );
		$data = EMCP_Local_Mcp_Test::data( $reply ); if ( ! empty( $data['change_id'] ) ) { $history[] = $data['change_id']; } return $reply;
	};
	$status = EMCP_Local_Mcp_Test::data( $call( $read, 'status' ) ); $test->check( '3.3.9.2' === ( $status['version'] ?? '' ), 'MCP reports native Pods version' );
	$pod = EMCP_Local_Mcp_Test::data( $call( $write, 'create-pod', array( 'pod' => $name, 'label' => 'HTTP fixture Pod', 'fields' => array( array( 'name' => 'fixture_note', 'label' => 'Note', 'type' => 'text' ) ), 'confirm' => true ) ) );
	$pod_id = $pod['id'] ?? 0; $test->check( $pod_id > 0, 'MCP created real native schema' );
	$record = EMCP_Local_Mcp_Test::data( $call( $write, 'create-record', array( 'pod' => $name, 'title' => 'HTTP fixture', 'values' => array( array( 'name' => 'fixture_note', 'value' => 'Before' ) ), 'confirm' => true ) ) );
	$item_id = $record['id'] ?? 0; $test->check( $item_id > 0 && 'draft' === get_post_status( $item_id ), 'MCP created native draft' );
	$first = EMCP_Local_Mcp_Test::data( $call( $read, 'get-record', array( 'pod' => $name, 'id' => $item_id ) ) );
	$test->check( 'Before' === ( $first['values']['fixture_note'] ?? '' ), 'MCP reads stored field' );
	$edited = EMCP_Local_Mcp_Test::data( $call( $write, 'update-record', array( 'pod' => $name, 'id' => $item_id, 'version' => $first['version'], 'values' => array( array( 'name' => 'fixture_note', 'value' => '' ) ), 'confirm' => true ) ) );
	$test->check( isset( $edited['change_id'] ), 'MCP update has History receipt' );
	update_option( 'emcp_tools_strict_schemas', '1' );
	$args = array_fill_keys( array_keys( EMCP_Tools_Pods_Integration::fields() ), null ); $args['pod'] = $name; $args['id'] = $item_id;
	$cleared = EMCP_Local_Mcp_Test::data( $call( $read, 'get-record', $args ) ); $test->check( '' === ( $cleared['values']['fixture_note'] ?? null ), 'strict transport preserves explicit field clearing' );
	$bad = $call( $write, 'update-record', array( 'pod' => $name, 'id' => $item_id, 'version' => $first['version'], 'values' => array( array( 'name' => 'fixture_note', 'value' => 'Stale' ) ), 'confirm' => true ) ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'stale mutation is MCP error' );
	update_option( 'emcp_tools_strict_schemas', '0' );
	update_option( 'emcp_tools_disabled_tools', array_merge( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/pods-write' ) ) );
	$bad = $call( $write, 'update-record', array( 'pod' => $name, 'id' => $item_id, 'version' => $cleared['version'], 'values' => array( array( 'name' => 'fixture_note', 'value' => 'Disabled' ) ), 'confirm' => true ) ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'disabled write denied over MCP' );
	$response = wp_remote_get( home_url( '/wp-json/wp/v2/types/' . $name ), array( 'sslverify' => false, 'timeout' => 45 ) ); $test->check( 200 === wp_remote_retrieve_response_code( $response ), 'MCP-created native schema has REST registration' );
} finally {
	if ( $item_id ) { wp_delete_post( $item_id, true ); }
	if ( $pod_id ) { pods_api()->delete_pod( array( 'id' => $pod_id ), false, false ); }
	if ( post_type_exists( $name ) ) { unregister_post_type( $name ); } flush_rewrite_rules( false );
	foreach ( array_unique( $history ) as $id ) { EMCP_Tools_Change_Log::delete( $id ); } $test->close();
}
WP_CLI::success( $test->checks . ' real Pods MCP HTTP assertions; credentials and fixtures removed.' );
