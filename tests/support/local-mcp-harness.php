<?php
/** Reusable real local MCP transport fixture. Never prints credentials or changes remote sites. */
final class EMCP_Local_Mcp_Test {
	private $user;
	private $password;
	private $saved = array();
	private $session = '';
	private $request = 0;
	private $oauth_client = '';
	private $bearer = '';
	public $checks = 0;
	public function __construct( array $enable ) {
		$local_ols = 'http://localhost:18880' === home_url() && 'local' === wp_get_environment_type();
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ( ! $local_ols && ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) ) { throw new RuntimeException( 'Local test sites only.' ); }
		$this->user = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
		$this->password = WP_Application_Passwords::create_new_application_password( $this->user->ID, array( 'name' => 'EMCP local integration ' . wp_generate_uuid4() ) );
		if ( is_wp_error( $this->password ) ) { throw new RuntimeException( 'Cannot create temporary application password.' ); }
		foreach ( array( 'emcp_tools_disabled_tools', 'emcp_tools_dispatcher_mode', 'emcp_tools_strict_schemas', 'emcp_tools_server_enabled' ) as $key ) { $this->saved[ $key ] = get_option( $key, null ); }
		update_option( 'emcp_tools_server_enabled', '1' ); update_option( 'emcp_tools_dispatcher_mode', '0' ); update_option( 'emcp_tools_strict_schemas', '0' );
		update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) $this->saved['emcp_tools_disabled_tools'], $enable ) ) );
	}
	public function check( $ok, string $label ): void { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$this->checks; WP_CLI::log( 'PASS ' . $label ); }
	/** Authorize an owned local test client, then perform the real PKCE token exchange over HTTP. */
	public function use_oauth(): void {
		$redirect = 'http://127.0.0.1/emcp-local-fixture';
		$client = EMCP_Tools_OAuth_Store::create_client( 'EMCP local fixture ' . wp_generate_uuid4(), array( $redirect ), $this->user->ID );
		$this->oauth_client = $client['client_id']; $verifier = bin2hex( random_bytes( 32 ) );
		$code = EMCP_Tools_OAuth_Store::issue_code( array( 'client_id' => $this->oauth_client, 'user_id' => $this->user->ID, 'redirect_uri' => $redirect, 'code_challenge' => rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ), 'scopes' => EMCP_Tools_OAuth_Server::SCOPE, 'resource' => EMCP_Tools_OAuth_Metadata::resource() ) );
		try {
			$r = wp_remote_post( rest_url( EMCP_Tools_OAuth_Server::REST_NAMESPACE . '/token' ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 45, 'body' => array( 'grant_type' => 'authorization_code', 'client_id' => $this->oauth_client, 'redirect_uri' => $redirect, 'code' => $code, 'code_verifier' => $verifier, 'resource' => EMCP_Tools_OAuth_Metadata::resource() ) ) );
			$data = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
			$this->check( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && ! empty( $data['access_token'] ), 'real local OAuth PKCE exchange' );
			$this->bearer = $data['access_token'];
		} finally { EMCP_Tools_OAuth_Store::consume_code( $code ); }
	}
	public function rpc( string $method, array $params = array(), bool $auth = true ) {
		$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2025-06-18' );
		if ( $auth ) { $headers['Authorization'] = $this->bearer ? 'Bearer ' . $this->bearer : 'Basic ' . base64_encode( $this->user->user_login . ':' . $this->password[0] ); }
		if ( $this->session ) { $headers['Mcp-Session-Id'] = $this->session; }
		$response = wp_remote_post( rest_url( 'mcp/emcp-tools-server' ), array( 'sslverify' => false, 'redirection' => 0, 'timeout' => 45, 'headers' => $headers, 'body' => wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => ++$this->request, 'method' => $method, 'params' => (object) $params ) ) ) );
		$this->check( ! is_wp_error( $response ), 'HTTP response: ' . $method );
		if ( ! $auth ) { return wp_remote_retrieve_response_code( $response ); }
		$http_code = wp_remote_retrieve_response_code( $response );
		$error_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->check( 200 === $http_code, 'HTTP 200: ' . $method . ( 200 !== $http_code ? ' (received ' . $http_code . ', ' . sanitize_key( $error_body['code'] ?? 'unknown' ) . ')' : '' ) );
		$this->session = wp_remote_retrieve_header( $response, 'mcp-session-id' ) ?: $this->session;
		$body = wp_remote_retrieve_body( $response ); $data = json_decode( $body, true );
		if ( ! is_array( $data ) && preg_match_all( '/^data: (.+)$/m', $body, $matches ) ) {
			foreach ( $matches[1] as $line ) { $candidate = json_decode( trim( $line ), true ); if ( isset( $candidate['id'] ) ) { $data = $candidate; } }
		}
		$this->check( is_array( $data ), 'JSON-RPC response: ' . $method ); return $data;
	}
	public function initialize(): void {
		$this->check( in_array( $this->rpc( 'tools/list', array(), false ), array( 401, 403 ), true ), 'unauthenticated MCP refused' );
		$reply = $this->rpc( 'initialize', array( 'protocolVersion' => '2025-06-18', 'capabilities' => new stdClass(), 'clientInfo' => array( 'name' => 'emcp-local-test', 'version' => '1' ) ) );
		$this->check( isset( $reply['result']['protocolVersion'] ), 'MCP initialized' );
	}
	public function tool( string $suffix ): string {
		$reply = $this->rpc( 'tools/list' );
		foreach ( $reply['result']['tools'] ?? array() as $tool ) { if ( str_ends_with( $tool['name'], $suffix ) ) { return $tool['name']; } }
		throw new RuntimeException( 'Tool not registered: ' . $suffix );
	}
	public static function data( array $reply ): array {
		$result = $reply['result'] ?? array(); if ( isset( $result['structuredContent'] ) ) { return $result['structuredContent']; }
		foreach ( $result['content'] ?? array() as $part ) { if ( 'text' === ( $part['type'] ?? '' ) ) { $data = json_decode( $part['text'], true ); if ( is_array( $data ) ) { return $data; } } } return array();
	}
	public function close(): void {
		if ( $this->oauth_client ) { EMCP_Tools_OAuth_Store::delete_client( $this->oauth_client ); $this->oauth_client = ''; $this->bearer = ''; }
		foreach ( $this->saved as $key => $value ) { null === $value ? delete_option( $key ) : update_option( $key, $value ); }
		if ( is_array( $this->password ) ) { WP_Application_Passwords::delete_application_password( $this->user->ID, $this->password[1]['uuid'] ); $this->password = null; }
	}
}
