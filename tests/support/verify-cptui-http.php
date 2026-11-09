<?php
require_once __DIR__ . '/local-mcp-harness.php';
$test = new EMCP_Local_Mcp_Test( array( 'emcp-tools/cptui-read', 'emcp-tools/cptui-write' ) );
$slug = 'httpcpt_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 ); $history = array();
try {
	$test->initialize(); $read = $test->tool( 'cptui-read' ); $write = $test->tool( 'cptui-write' );
	$call = static function ( $name, $op, $args = array() ) use ( $test ) { return $test->rpc( 'tools/call', array( 'name' => $name, 'arguments' => array( 'operation' => $op, 'arguments' => (object) $args ) ) ); };
	$status = EMCP_Local_Mcp_Test::data( $call( $read, 'status' ) ); $test->check( '1.19.3' === ( $status['version'] ?? '' ), 'MCP native version' );
	$description = 'HTTP fixture C:\\books\\notes';
	$create = EMCP_Local_Mcp_Test::data( $call( $write, 'create-post-type', array( 'slug' => $slug, 'label' => "Reader's HTTP & notes", 'singular_label' => "Reader's HTTP note", 'description' => $description, 'confirm' => true ) ) );
	if ( ! empty( $create['change_id'] ) ) { $history[] = $create['change_id']; }
	$test->check( null !== EMCP_Tools_Cptui_Api::row( 'post-type', $slug ), 'MCP created native schema' );
	$test->check( ! empty( $create['change_id'] ) && $description === ( $create['definition']['description'] ?? '' ) && htmlspecialchars( "Reader's HTTP & notes", ENT_QUOTES ) === ( $create['definition']['label'] ?? '' ), 'Native encoded label and literal backslashes round-trip over MCP' );
	$front = wp_remote_get( home_url( '/wp-json/wp/v2/types/' . $slug ), array( 'sslverify' => false, 'timeout' => 45 ) ); $test->check( 200 === wp_remote_retrieve_response_code( $front ), 'MCP schema registered over fresh HTTP' );
	$edit = EMCP_Local_Mcp_Test::data( $call( $write, 'update-post-type', array( 'slug' => $slug, 'version' => $create['version'], 'description' => '', 'confirm' => true ) ) );
	if ( ! empty( $edit['change_id'] ) ) { $history[] = $edit['change_id']; }
	$test->check( '' === ( $edit['definition']['description'] ?? null ), 'MCP explicit empty description' );
	$bad = $call( $write, 'update-post-type', array( 'slug' => $slug, 'version' => $edit['version'], 'label' => 'No', 'confirm' => false ) ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'MCP rejected mutation is an error' );
	update_option( 'emcp_tools_strict_schemas', '1' );
	$args = array_fill_keys( array_keys( EMCP_Tools_Cptui_Integration::fields() ), null ); $args['slug'] = $slug;
	$result = EMCP_Local_Mcp_Test::data( $call( $read, 'get-post-type', $args ) ); $test->check( $slug === ( $result['definition']['name'] ?? '' ), 'strict null placeholders supported' );
	update_option( 'emcp_tools_strict_schemas', '0' );
	update_option( 'emcp_tools_disabled_tools', array_merge( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/cptui-write' ) ) );
	$bad = $call( $write, 'update-post-type', array( 'slug' => $slug, 'version' => $edit['version'], 'label' => 'Disabled', 'confirm' => true ) ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'disabled tool denied over HTTP' );
} finally {
	if ( EMCP_Tools_Cptui_Api::row( 'post-type', $slug ) ) { cptui_delete_post_type( array( 'name' => $slug ) ); EMCP_Tools_Cptui_Api::refresh( 'post-type', $slug ); }
	foreach ( $history as $id ) { EMCP_Tools_Change_Log::delete( $id ); } $test->close();
}
WP_CLI::success( $test->checks . ' real CPT UI MCP HTTP assertions; credentials and fixtures removed.' );
