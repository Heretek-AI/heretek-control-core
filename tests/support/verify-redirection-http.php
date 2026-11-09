<?php
/** Authenticated local MCP HTTP integration test. Credentials never leave this process. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$user = $admins[0];
$password = WP_Application_Passwords::create_new_application_password( $user->ID, array( 'name' => 'EMCP Redirection E2E ' . wp_generate_uuid4() ) );
if ( is_wp_error( $password ) ) { throw new RuntimeException( 'Cannot create temporary local application password.' ); }
$saved = array();
foreach ( array( 'emcp_tools_disabled_tools', 'emcp_tools_dispatcher_mode', 'emcp_tools_strict_schemas', 'emcp_tools_server_enabled' ) as $key ) { $saved[ $key ] = get_option( $key, null ); }
$checks = 0;
$check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::log( 'PASS ' . $label ); };
$session = '';
$request_id = 0;
$rpc = static function ( $method, $params = array(), $authenticate = true ) use ( &$session, &$request_id, $user, $password, $check ) {
	$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18' );
	if ( $authenticate ) { $headers['Authorization'] = 'Basic ' . base64_encode( $user->user_login . ':' . $password[0] ); }
	if ( $session ) { $headers['Mcp-Session-Id'] = $session; }
	$response = wp_remote_post( rest_url( 'mcp/emcp-tools-server' ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 45, 'headers' => $headers, 'body' => wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => ++$request_id, 'method' => $method, 'params' => (object) $params ) ) ) );
	$check( ! is_wp_error( $response ), 'HTTP response for ' . $method );
	if ( ! $authenticate ) { return wp_remote_retrieve_response_code( $response ); }
	$check( 200 === wp_remote_retrieve_response_code( $response ), 'HTTP 200 for ' . $method );
	$session = wp_remote_retrieve_header( $response, 'mcp-session-id' ) ?: $session;
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) && preg_match_all( '/^data: (.+)$/m', $body, $matches ) ) {
		foreach ( $matches[1] as $line ) { $candidate = json_decode( trim( $line ), true ); if ( isset( $candidate['id'] ) ) { $data = $candidate; } }
	}
	$check( is_array( $data ), 'JSON-RPC response for ' . $method );
	return $data;
};
$group_id = 0;
$item_id = 0;
$history = array();
$decode = static function ( $response ) {
	$result = $response['result'] ?? array();
	if ( isset( $result['structuredContent'] ) ) { return $result['structuredContent']; }
	foreach ( $result['content'] ?? array() as $part ) { if ( 'text' === ( $part['type'] ?? '' ) ) { $data = json_decode( $part['text'], true ); if ( is_array( $data ) ) { return $data; } } }
	return array();
};
try {
	update_option( 'emcp_tools_server_enabled', '1' );
	update_option( 'emcp_tools_dispatcher_mode', '0' );
	update_option( 'emcp_tools_strict_schemas', '0' );
	update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) $saved['emcp_tools_disabled_tools'], array( 'emcp-tools/redirection-read', 'emcp-tools/redirection-write' ) ) ) );
	$check( in_array( $rpc( 'tools/list', array(), false ), array( 401, 403 ), true ), 'unauthenticated MCP denied' );
	$hello = $rpc( 'initialize', array( 'protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'emcp-local-integration-test', 'version' => '1' ) ) );
	$check( isset( $hello['result']['protocolVersion'] ), 'MCP initialize negotiated' );
	$list = $rpc( 'tools/list' );
	$names = array_column( $list['result']['tools'] ?? array(), 'name' );
	$read_name = null;
	$write_name = null;
	foreach ( $names as $name ) { if ( false !== strpos( $name, 'redirection-read' ) ) { $read_name = $name; } if ( false !== strpos( $name, 'redirection-write' ) ) { $write_name = $name; } }
	$check( null !== $read_name && null !== $write_name, 'both integration tools discoverable' );
	$status = $rpc( 'tools/call', array( 'name' => $read_name, 'arguments' => array( 'operation' => 'status' ) ) );
	$check( '5.10.1' === ( $decode( $status )['version'] ?? '' ), 'MCP status native version' );
	$group = $rpc( 'tools/call', array( 'name' => $write_name, 'arguments' => array( 'operation' => 'create-group', 'arguments' => array( 'name' => 'EMCP HTTP fixture', 'confirm' => true ) ) ) );
	$group_data = $decode( $group );
	$group_id = (int) ( $group_data['id'] ?? 0 );
	if ( ! empty( $group_data['change_id'] ) ) { $history[] = $group_data['change_id']; }
	$check( $group_id > 0 && Red_Group::get( $group_id, true ), 'MCP creates native group' );
	$source = '/emcp-http-' . wp_generate_uuid4();
	$created = $rpc( 'tools/call', array( 'name' => $write_name, 'arguments' => array( 'operation' => 'create-redirect', 'arguments' => array( 'source' => $source, 'target' => '/emcp-http-target/', 'group_id' => $group_id, 'confirm' => true ) ) ) );
	$created_data = $decode( $created );
	$item_id = (int) ( $created_data['id'] ?? 0 );
	if ( ! empty( $created_data['change_id'] ) ) { $history[] = $created_data['change_id']; }
	$check( $item_id > 0 && Red_Item::get_by_id( $item_id ), 'MCP creates native redirect' );
	$front = wp_remote_get( home_url( $source ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 45 ) );
	$check( 302 === wp_remote_retrieve_response_code( $front ) && false !== strpos( wp_remote_retrieve_header( $front, 'location' ), '/emcp-http-target/' ), 'MCP-created redirect works over HTTP' );
	$invalid = $rpc( 'tools/call', array( 'name' => $write_name, 'arguments' => array( 'operation' => 'delete-redirect', 'arguments' => array( 'id' => $item_id, 'version' => $created_data['version'], 'confirm' => false ) ) ) );
	$check( ! empty( $invalid['result']['isError'] ) || isset( $invalid['error'] ), 'failed mutation reported as MCP error' );
	$check( false !== Red_Item::get_by_id( $item_id ), 'rejected MCP mutation did not delete' );
	update_option( 'emcp_tools_disabled_tools', array_merge( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/redirection-write' ) ) );
	$disabled = $rpc( 'tools/call', array( 'name' => $write_name, 'arguments' => array( 'operation' => 'delete-redirect', 'arguments' => array( 'id' => $item_id, 'version' => $created_data['version'], 'confirm' => true ) ) ) );
	$check( ! empty( $disabled['result']['isError'] ) || isset( $disabled['error'] ), 'disabled tool denied over MCP' );
	update_option( 'emcp_tools_strict_schemas', '1' );
	$list = $rpc( 'tools/list' );
	$strict_args = array_fill_keys( array_keys( EMCP_Tools_Redirection_Integration::fields() ), null );
	$strict_args['id'] = $item_id;
	$strict = $rpc( 'tools/call', array( 'name' => $read_name, 'arguments' => array( 'operation' => 'get-redirect', 'arguments' => $strict_args ) ) );
	$check( $item_id === ( $decode( $strict )['id'] ?? 0 ), 'strict-schema null placeholders supported' );
} finally {
	if ( isset( $source ) ) {
		foreach ( Red_Redirect_Log::get_filtered( array( 'filterBy' => array( 'url-exact' => $source ), 'per_page' => 100 ) )['items'] as $log ) { Red_Redirect_Log::delete( $log['id'] ); }
	}
	if ( $item_id ) { $item = Red_Item::get_by_id( $item_id ); if ( $item ) { $item->delete(); } }
	if ( $group_id ) { $group = Red_Group::get( $group_id, true ); if ( $group && 0 === $group->get_total_redirects() ) { $group->delete(); } }
	foreach ( $history as $id ) { EMCP_Tools_Change_Log::delete( $id ); }
	foreach ( $saved as $key => $value ) { null === $value ? delete_option( $key ) : update_option( $key, $value ); }
	WP_Application_Passwords::delete_application_password( $user->ID, $password[1]['uuid'] );
}
WP_CLI::success( $checks . ' authenticated MCP HTTP assertions; credentials and fixtures removed.' );
