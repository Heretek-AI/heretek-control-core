<?php
/**
 * MCP Adapter 0.7 answers 400 to a request after initialize that carries no
 * MCP-Protocol-Version header. The EMCP Cloud Gateway sent only Mcp-Session-Id,
 * so every site on 3.21.0 showed "Gateway unavailable". The shim fills the
 * header from the version the session negotiated.
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'vendor/wordpress/php-mcp-schema/src/Schemas.php';
require_once EMCP_TOOLS_DIR . 'vendor/wordpress/mcp-adapter/includes/Core/McpVersionNegotiator.php';
require_once EMCP_TOOLS_DIR . 'includes/class-mcp-protocol-header.php';

if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- minimal request double.
	class WP_REST_Request {
		/** @var array<string,string> */
		private $headers = array();
		/** @var string */
		private $method;
		/** @var string */
		private $route;
		public function __construct( string $method = 'GET', string $route = '' ) {
			$this->method = $method;
			$this->route  = $route;
		}
		public function get_method() {
			return $this->method;
		}
		public function get_route() {
			return $this->route;
		}
		public function get_header( $key ) {
			$key = strtolower( str_replace( '-', '_', $key ) );
			return $this->headers[ $key ] ?? null;
		}
		public function set_header( $key, $value ) {
			$this->headers[ strtolower( str_replace( '-', '_', $key ) ) ] = $value;
		}
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return (int) ( $GLOBALS['emcp_test_user_id'] ?? 0 );
	}
}

final class McpProtocolHeaderTest extends TestCase {

	/** @var array<int,array{0:int,1:string}> */
	private $reads = array();

	protected function setUp(): void {
		$GLOBALS['emcp_test_user_id'] = 7;
		$this->reads                  = array();
		EMCP_Tools_MCP_Protocol_Header::use_session_reader(
			function ( int $user_id, string $session_id ) {
				$this->reads[] = array( $user_id, $session_id );
				$sessions      = array(
					's-0618' => array( 'client_params' => array( 'protocolVersion' => '2025-06-18' ) ),
					's-0326' => array( 'client_params' => array( 'protocolVersion' => '2025-03-26' ) ),
					's-1105' => array( 'client_params' => array( 'protocolVersion' => '2024-11-05' ) ),
				);
				return $sessions[ $session_id ] ?? false;
			}
		);
	}

	protected function tearDown(): void {
		EMCP_Tools_MCP_Protocol_Header::use_session_reader( null );
		unset( $GLOBALS['emcp_test_user_id'] );
	}

	private function request( string $session = '', string $header = '', string $method = 'POST', string $route = '/mcp/emcp-tools-server' ): WP_REST_Request {
		$r = new WP_REST_Request( $method, $route );
		if ( '' !== $session ) {
			$r->set_header( 'Mcp-Session-Id', $session );
		}
		if ( '' !== $header ) {
			$r->set_header( 'Mcp-Protocol-Version', $header );
		}
		return $r;
	}

	public function test_missing_header_gets_the_negotiated_version(): void {
		$r = $this->request( 's-0618' );
		$this->assertNull( EMCP_Tools_MCP_Protocol_Header::fill( null, null, $r ) );
		$this->assertSame( '2025-06-18', $r->get_header( 'Mcp-Protocol-Version' ) );
		$this->assertSame( array( array( 7, 's-0618' ) ), $this->reads );
	}

	public function test_an_unknown_proposal_uses_what_the_adapter_negotiated(): void {
		$r = $this->request( 's-0326' );
		EMCP_Tools_MCP_Protocol_Header::fill( null, null, $r );
		$this->assertSame( '2025-11-25', $r->get_header( 'Mcp-Protocol-Version' ), '0.7 answers 2025-03-26 with 2025-11-25' );

		$old = $this->request( 's-1105' );
		EMCP_Tools_MCP_Protocol_Header::fill( null, null, $old );
		$this->assertSame( '2024-11-05', $old->get_header( 'Mcp-Protocol-Version' ) );
	}

	public function test_a_header_the_client_sent_is_never_changed(): void {
		$r = $this->request( 's-0618', '2025-03-26' );
		EMCP_Tools_MCP_Protocol_Header::fill( null, null, $r );
		$this->assertSame( '2025-03-26', $r->get_header( 'Mcp-Protocol-Version' ) );
		$this->assertSame( array(), $this->reads );
	}

	public function test_nothing_happens_without_a_live_session_or_user(): void {
		$cases = array(
			'no session header' => $this->request(),
			'unknown session'   => $this->request( 'gone' ),
			'other route'       => $this->request( 's-0618', '', 'POST', '/wp/v2/posts' ),
			'other server'      => $this->request( 's-0618', '', 'POST', '/mcp/elementor-mcp-server' ),
			'not a POST'        => $this->request( 's-0618', '', 'DELETE' ),
		);
		foreach ( $cases as $label => $r ) {
			EMCP_Tools_MCP_Protocol_Header::fill( null, null, $r );
			$this->assertNull( $r->get_header( 'Mcp-Protocol-Version' ), $label );
		}

		$GLOBALS['emcp_test_user_id'] = 0;
		$r                            = $this->request( 's-0618' );
		EMCP_Tools_MCP_Protocol_Header::fill( null, null, $r );
		$this->assertNull( $r->get_header( 'Mcp-Protocol-Version' ), 'logged out' );
	}

	public function test_an_earlier_dispatch_result_and_reader_errors_pass_through(): void {
		$done = array( 'already' => 'answered' );
		$r    = $this->request( 's-0618' );
		$this->assertSame( $done, EMCP_Tools_MCP_Protocol_Header::fill( $done, null, $r ) );
		$this->assertNull( $r->get_header( 'Mcp-Protocol-Version' ) );

		EMCP_Tools_MCP_Protocol_Header::use_session_reader(
			static function () {
				throw new RuntimeException( 'storage down' );
			}
		);
		$r = $this->request( 's-0618' );
		$this->assertNull( EMCP_Tools_MCP_Protocol_Header::fill( null, null, $r ) );
		$this->assertNull( $r->get_header( 'Mcp-Protocol-Version' ) );
	}
}
