<?php
/**
 * Which plugin's MCP Adapter copy is loaded. EMCP runs on the official MCP Adapter plugin; when
 * another plugin's bundled copy answers instead (Elementor, WooCommerce, Amelia), the Connection
 * screen names it and the Dashboard asks for the official plugin.
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';
require_once EMCP_TOOLS_DIR . 'includes/attention/interface-attention-check.php';
require_once EMCP_TOOLS_DIR . 'includes/attention/class-attention-checks.php';

final class AdapterSourceTest extends TestCase {

	const PLUGINS = 'F:/site/wp-content/plugins';

	public function test_the_chosen_copy_is_ours(): void {
		$s = EMCP_Tools_Adapter_Bootstrap::source_from_file( self::PLUGINS . '/mcp-adapter/includes/Core/McpAdapter.php', self::PLUGINS, self::PLUGINS . '/mcp-adapter/includes/', '0.7.1' );
		$this->assertSame( array( 'file' => 'mcp-adapter/includes/Core/McpAdapter.php', 'version' => '0.7.1', 'ours' => true, 'plugin' => 'mcp-adapter' ), $s );
	}

	public function test_another_plugin_copy_names_the_plugin_and_version(): void {
		$s = EMCP_Tools_Adapter_Bootstrap::source_from_file( 'F:\\site\\wp-content\\plugins\\ameliabooking\\vendor\\wordpress\\mcp-adapter\\includes\\Core\\McpAdapter.php', self::PLUGINS, self::PLUGINS . '/mcp-adapter/includes/', '0.5.0' );
		$this->assertSame( array( false, 'ameliabooking', '0.5.0' ), array( $s['ours'], $s['plugin'], $s['version'] ) );
		$this->assertSame( '', EMCP_Tools_Adapter_Bootstrap::source_from_file( '', self::PLUGINS, '', '' )['plugin'] );
		$this->assertFalse( EMCP_Tools_Adapter_Bootstrap::source_from_file( self::PLUGINS . '/elementor/vendor/x.php', self::PLUGINS, '', '0.6.1' )['ours'], 'nothing is ours without the official plugin' );
	}

	public function test_the_attention_item_follows_the_adapter_state(): void {
		$ok = new EMCP_Tools_Adapter_Attention( array( 'state' => 'ok', 'installed' => true, 'version' => '0.7.1', 'plugin' => '' ), true );
		$this->assertFalse( $ok->applies() );

		$missing = new EMCP_Tools_Adapter_Attention( array( 'state' => 'missing', 'installed' => false, 'version' => '', 'plugin' => '' ), false );
		$this->assertTrue( $missing->applies() );
		$this->assertSame( 'mcp-adapter', $missing->id() );
		$item = $missing->item();
		$this->assertSame( 'danger', $item['severity'], 'no adapter at all: the MCP server is off' );
		$this->assertSame( 'Install and activate MCP Adapter', $item['action_label'] );

		$fallback = new EMCP_Tools_Adapter_Attention( array( 'state' => 'missing', 'installed' => false, 'version' => '0.7.1', 'plugin' => '' ), true );
		$this->assertFalse( $fallback->applies(), 'the built-in copy serves: a permanent fallback, nothing to fix' );

		$old = new EMCP_Tools_Adapter_Attention( array( 'state' => 'old', 'installed' => true, 'version' => '0.5.0', 'plugin' => '' ), true );
		$this->assertSame( 'warning', $old->item()['severity'] );

		$other = new EMCP_Tools_Adapter_Attention( array( 'state' => 'missing', 'installed' => false, 'version' => '0.5.0', 'plugin' => 'ameliabooking' ), true );
		$this->assertTrue( $other->applies() );
		$this->assertSame( 'info', $other->item()['severity'] );
		$this->assertStringContainsString( 'ameliabooking', $other->item()['body'] );
		$this->assertNotSame( $missing->state(), $other->state() );

		$inactive = new EMCP_Tools_Adapter_Attention( array( 'state' => 'inactive', 'installed' => true, 'version' => '', 'plugin' => '' ), false );
		$this->assertSame( 'Activate MCP Adapter', $inactive->item()['action_label'] );

		foreach ( array( $missing, $other, $inactive ) as $check ) {
			$i = $check->item();
			$this->assertStringNotContainsString( "\u{2014}", $i['title'] . $i['body'] . $i['action_label'] );
		}
	}
}
