<?php
/** Local landing-page build/inspection through registered EMCP abilities. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$_SERVER['REQUEST_SCHEME'] = 'https'; $_SERVER['HTTP_HOST'] = 'msrplugins.test';
require dirname( __DIR__, 5 ) . '/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
if ( get_template() !== 'bricks' || wp_parse_url( home_url(), PHP_URL_HOST ) !== 'msrplugins.test' ) { exit( "Local Bricks site required.\n" ); }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );
\Bricks\Capabilities::$capabilities_set = false; \Bricks\Capabilities::set_user_capabilities();
if ( ( $argv[1] ?? '' ) === 'inspect' ) {
	echo json_encode( array( 'selected' => EMCP_Tools_Page_Builders::selected(), 'css_mode' => \Bricks\Database::get_setting( 'cssLoading' ), 'bricks' => BRICKS_VERSION ) ) . "\n";
	foreach ( array( 'section', 'container', 'block', 'heading', 'text-basic', 'button' ) as $name ) {
		$schema = \Bricks\Elements::get_element( array( 'name' => $name ) );
		echo $name . ': ' . implode( ',', array_keys( $schema['controls'] ) ) . "\n";
	}
	exit;
}
$disabled = get_option( 'emcp_tools_disabled_tools', array() );
$allow = array( 'emcp-tools/bricks-create-page', 'emcp-tools/bricks-set-page-elements', 'emcp-tools/bricks-get-page' );
// User-authorized test build: scope write opt-in to this process only.
add_filter( 'pre_option_emcp_tools_disabled_tools', static function() use ( $disabled, $allow ) { return array_values( array_diff( $disabled, $allow ) ); } );
function landing_call( $name, $args ) {
	$ability = wp_get_ability( 'emcp-tools/bricks-' . $name );
	if ( ! $ability ) { throw new RuntimeException( 'Ability unavailable: ' . $name ); }
	$result = $ability->execute( $args );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_code() . ': ' . $result->get_error_message() ); }
	return $result;
}
$artifact = __DIR__ . '/../../bricks-json/forma-landing.json';
$elements = json_decode( file_get_contents( $artifact ), true )['content'];
$state_file = __DIR__ . '/../../bricks-json/forma-state.json';
if ( is_file( $state_file ) ) {
	$prior = json_decode( file_get_contents( $state_file ), true );
	$state = landing_call( 'get-page', array( 'post_id' => $prior['post_id'] ) );
} else {
	$state = landing_call( 'create-page', array( 'title' => 'Forma — Bricks Test Landing Page' ) );
	file_put_contents( $state_file, json_encode( $state, JSON_PRETTY_PRINT ) );
}
// Hide the surrounding template's title only on this standalone test landing.
$elements[0]['settings']['_cssCustom'] = 'body.page-id-' . $state['post_id'] . ' .wp-block-emcp-post-title { display: none; }';
$state = landing_call( 'set-page-elements', array( 'post_id' => $state['post_id'], 'expected_hash' => $state['content_hash'], 'elements' => $elements ) );
$state['preview_url'] = get_preview_post_link( $state['post_id'] );
file_put_contents( $state_file, json_encode( array_diff_key( $state, array( 'elements' => true ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
echo json_encode( array( 'post_id' => $state['post_id'], 'elements' => count( $state['elements'] ), 'preview_url' => $state['preview_url'], 'editor_url' => $state['editor_url'], 'warnings' => $state['warnings'] ?? array() ), JSON_UNESCAPED_SLASHES );
