<?php
/** Run against each separately activated package on the disposable local OLS fixture. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:18880' !== home_url() || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Disposable local package fixture only.' ); }
$mode = $args[0] ?? ''; if ( ! in_array( $mode, array( 'free', 'pro-unlicensed', 'lite' ), true ) ) { throw new RuntimeException( 'Specify package mode.' ); }
$count = 0; $check = static function ( $ok, $label ) use ( &$count ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$count; WP_CLI::log( 'PASS ' . $label ); };
require_once EMCP_TOOLS_DIR . 'includes/admin/class-admin.php';
$admin = new EMCP_Tools_Admin(); $catalog = $admin->get_all_tools();
foreach ( array( 'Redirection', 'Cptui', 'Pods', 'Wp_Mail_Smtp', 'Litespeed', 'Wordfence' ) as $name ) { $check( class_exists( 'EMCP_Tools_' . $name . '_Api' ), 'Packaged Free API: ' . $name ); }
foreach ( array( 'Fluentsmtp', 'Flamingo', 'Sitekit', 'Ultimate_Member' ) as $name ) {
	$class = 'EMCP_Tools_' . $name . '_Api';
	if ( 'pro-unlicensed' === $mode ) { $check( class_exists( $class ) && ! $class::licensed(), 'Unlicensed Pro gate: ' . $name ); }
	else { $check( ! class_exists( $class ), 'Private API absent: ' . $name ); }
}
foreach ( array( 'redirection', 'cptui', 'pods', 'wp-mail-smtp', 'litespeed' ) as $slug ) { $check( in_array( 'emcp-tools/' . $slug . '-write', $admin->default_disabled_changes( 0 )['add'], true ), 'Packaged write defaults off: ' . $slug ); }
$check( class_exists( 'EMCP_Tools_Query_Capture' ), 'Shared insert capture loads in packaged edition' );
if ( 'lite' === $mode ) { $check( ! is_dir( EMCP_TOOLS_DIR . 'vendor/wordpress/mcp-adapter' ) && class_exists( 'WP\\MCP\\Core\\McpAdapter' ), 'Lite uses active external MCP Adapter' ); }
WP_CLI::success( $count . ' actual ' . $mode . ' package assertions.' );
