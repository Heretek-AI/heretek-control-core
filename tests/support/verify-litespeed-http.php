<?php
require_once __DIR__ . '/local-mcp-harness.php';
global $wpdb;
$test = new EMCP_Local_Mcp_Test( array( 'emcp-tools/litespeed-read', 'emcp-tools/litespeed-write' ) );
$ols = 'http://localhost:18880' === home_url(); $history = array(); $post = 0; $saved = array();
$token = wp_generate_uuid4();
try {
	if ( ! wp_is_application_passwords_available() ) { $test->use_oauth(); }
	$test->initialize(); $read = $test->tool( 'litespeed-read' ); $write = $test->tool( 'litespeed-write' );
	$call = static function ( $tool, $operation, $args = array() ) use ( $test, &$history ) { $reply = $test->rpc( 'tools/call', array( 'name' => $tool, 'arguments' => array( 'operation' => $operation, 'arguments' => (object) $args ) ) ); $data = EMCP_Local_Mcp_Test::data( $reply ); if ( ! empty( $data['change_id'] ) ) { $history[] = $data['change_id']; } return $reply; };
	$status = EMCP_Local_Mcp_Test::data( $call( $read, 'status' ) ); $test->check( '7.9.2' === ( $status['version'] ?? '' ), 'real plugin version read over MCP' );
	$test->check( $ols === $status['purge_supported'], 'actual server capability reported truthfully' );
	$cli = EMCP_Tools_Litespeed_Api::read( 'status', array() ); $test->check( ! $cli['purge_supported'] && 'unknown_cli' === $cli['server'], 'CLI cannot claim real server invalidation' );
	wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
	$cli_write = ( new EMCP_Tools_Litespeed_Integration() )->run_write( array( 'operation' => 'purge-page-cache', 'arguments' => array( 'confirm' => true ) ) );
	$test->check( is_wp_error( $cli_write ) && 'unsupported_environment' === $cli_write->get_error_code(), 'CLI purge refused rather than silently queued' );
	$bad = $call( $write, 'purge-path', array( 'path' => 'https://elsewhere.test/', 'confirm' => true ) ); $test->check( ! empty( $bad['result']['isError'] ), 'foreign URL rejected' );
	$bad = $call( $write, 'purge-page-cache', array( 'confirm' => false ) ); $test->check( ! empty( $bad['result']['isError'] ), 'confirmation required' );
	if ( $ols ) {
		foreach ( array( 'show_on_front', 'page_on_front' ) as $key ) { $saved[ $key ] = get_option( $key ); }
		$post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'EMCP MCP cache fixture', 'post_content' => 'CACHE_OLD_' . $token ), true );
		if ( is_wp_error( $post ) ) { throw new RuntimeException( 'Fixture creation failed.' ); }
		update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $post );
		$get = static function () { usleep( 250000 ); $r = wp_remote_get( home_url( '/' ), array( 'timeout' => 30 ) ); if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_code() ); } return $r; };
		$call( $write, 'purge-path', array( 'path' => '/', 'confirm' => true ) ); $get();
		$r = $get(); $test->check( 'hit' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ) && str_contains( wp_remote_retrieve_body( $r ), 'CACHE_OLD_' . $token ), 'real initial cache HIT' );
		foreach ( array( 'purge-path', 'purge-page-cache' ) as $index => $op ) {
			// Native LiteSpeed may carry a previous purge in its next uncached response.
			// Drain that response and warm the cache before introducing a new stale value.
			$call( $read, 'status' ); $get();
			$test->check( 'hit' === wp_remote_retrieve_header( $get(), 'x-litespeed-cache' ), 'cache settled before fixture edit' );
			$content = 'CACHE_NEW_' . $index . '_' . $token;
			// Owned test page only: bypass automatic invalidation to prove the explicit MCP purge.
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post ) ); clean_post_cache( $post );
			$r = $get(); $test->check( ! str_contains( wp_remote_retrieve_body( $r ), $content ), 'cache remains stale before ' . $op );
			$args = array( 'confirm' => true ); if ( 'purge-path' === $op ) { $args['path'] = '/'; }
			$dry = EMCP_Local_Mcp_Test::data( $call( $write, $op, array_merge( $args, array( 'dry_run' => true ) ) ) );
			$test->check( ! empty( $dry['dry_run'] ), 'dry-run validates without native call' );
			$dry_response = $get();
			$test->check( ! str_contains( wp_remote_retrieve_body( $dry_response ), $content ), 'dry-run leaves stale cache: ' . wp_remote_retrieve_header( $dry_response, 'x-litespeed-cache' ) );
			$result = EMCP_Local_Mcp_Test::data( $call( $write, $op, $args ) ); $test->check( 'purge_requested' === ( $result['state'] ?? '' ), 'MCP native ' . $op . ' requested' );
			$r = $get(); $test->check( 'miss' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ) && str_contains( wp_remote_retrieve_body( $r ), $content ), 'fresh MISS after ' . $op );
			$r = $get(); $test->check( 'hit' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ) && str_contains( wp_remote_retrieve_body( $r ), $content ), 'fresh HIT after ' . $op );
		}
	} else {
		$bad = $call( $write, 'purge-page-cache', array( 'confirm' => true ) ); $test->check( ! empty( $bad['result']['isError'] ), 'unsupported Laragon server refuses purge' );
	}
	update_option( 'emcp_tools_strict_schemas', '1' );
	$strict = array_fill_keys( array_keys( EMCP_Tools_Litespeed_Integration::fields() ), null );
	$r = EMCP_Local_Mcp_Test::data( $call( $read, 'status', $strict ) ); $test->check( '7.9.2' === ( $r['version'] ?? '' ), 'strict null placeholders supported' );
	update_option( 'emcp_tools_strict_schemas', '0' );
	update_option( 'emcp_tools_disabled_tools', array_merge( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/litespeed-write' ) ) );
	$bad = $call( $write, 'purge-page-cache', array( 'confirm' => true ) ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'disabled write refused over MCP' );
} finally {
	foreach ( $saved as $key => $value ) { update_option( $key, $value ); }
	if ( $post && ! is_wp_error( $post ) ) { wp_delete_post( $post, true ); }
	foreach ( array_unique( $history ) as $id ) { EMCP_Tools_Change_Log::delete( $id ); }
	$test->close();
}
WP_CLI::success( $test->checks . ' real LiteSpeed MCP/cache checks passed.' );
