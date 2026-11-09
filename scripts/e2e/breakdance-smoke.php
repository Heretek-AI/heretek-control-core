<?php
/** Local integration test. --keep retains the draft for browser verification. */
if ( PHP_SAPI !== 'cli' ) { exit; }
$_SERVER['REQUEST_SCHEME'] = 'https';
$_SERVER['HTTP_HOST'] = 'msrplugins.test';
require dirname( __DIR__, 5 ) . '/wp-load.php';
if ( wp_parse_url( home_url(), PHP_URL_HOST ) !== 'msrplugins.test' ) { exit( "Local site required.\n" ); }
EMCP_Tools_Bootstrap::load_mcp_surface();
if ( ! EMCP_Tools_Breakdance_Integration::supported() ) { exit( "Supported Breakdance required.\n" ); }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
$keep = in_array( '--keep', $argv, true );
$saved = get_option( 'emcp_tools_page_builder', null );
// This is a request-scoped opt-in; persistent write-tool choices stay untouched.
add_filter( 'pre_option_emcp_tools_disabled_tools', static function() { return array(); } );
$id = 0;
$editor_id = 0;
$template_id = 0;
$completed = false;
function bd_check( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; }
function bd_run( $slug, $args = array() ) {
	$ability = wp_get_ability( 'emcp-tools/breakdance-' . $slug );
	if ( ! $ability ) { throw new RuntimeException( 'Missing ability: ' . $slug ); }
	$result = $ability->execute( $args );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $slug . ': ' . $result->get_error_code() . ': ' . $result->get_error_message() ); }
	return $result;
}
try {
	update_option( 'emcp_tools_page_builder', 'breakdance' );
	bd_check( bd_run( 'get-context' )['version'] === '2.8.3', 'installed version' );
	bd_check( ! wp_get_ability( 'emcp-tools/bricks-get-context' ) && ! wp_get_ability( 'emcp-tools/get-page-structure' ), 'other standalone builders excluded' );
	bd_check( (bool) wp_get_ability( 'emcp-tools/list-blocks' ), 'Gutenberg coexists' );
	bd_check( count( bd_run( 'list-elements' )['elements'] ) > 50, 'native element catalog' );
	$schema = bd_run( 'get-element-schema', array( 'type' => 'EssentialElements\\Heading' ) );
	bd_check( ! empty( $schema['controls']['designSections'] ), 'native heading controls' );
	bd_check( isset( bd_run( 'get-design-system' )['breakpoints'] ), 'design discovery' );
	$state = bd_run( 'create-page', array( 'title' => 'EMCP Breakdance Integration Test' ) );
	$id = $state['post_id'];
	bd_check( get_post_status( $id ) === 'draft', 'draft creation' );
	wp_update_post( array( 'ID' => $id, 'post_content' => 'Preserved WordPress content.' ) );
	\Breakdance\Data\set_meta( $id, '_breakdance_futurelayer_meta', '{"test":"keep"}' );
	\Breakdance\Data\set_meta( $id, '_breakdance_template_settings', array( 'test' => 'keep' ) );
	$state = bd_run( 'get-page', array( 'post_id' => $id ) );
	$tree = EMCP_Tools_Breakdance_Tree::blank();
	$tree['root']['children'][] = array( 'id' => 100, 'data' => array( 'type' => 'EssentialElements\\Section', 'properties' => array( 'design' => array( 'layout_v2' => array( 'layout' => 'vertical' ) ) ) ), 'children' => array(
		array( 'id' => 101, 'data' => array( 'type' => 'EssentialElements\\Heading', 'properties' => array( 'content' => array( 'content' => array( 'text' => 'Build something remarkable.', 'tags' => 'h1' ) ), 'design' => array( 'typography' => array( 'color' => array( 'breakpoint_base' => '#2447a8', 'breakpoint_phone_portrait' => '#ae304a' ) ) ) ) ), 'children' => array() ),
	) );
	$tree['_nextNodeId'] = 102;
	$state = bd_run( 'set-page-tree', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'tree' => $tree ) );
	bd_check( $state['tree'] === $tree, 'nested responsive tree round trip' );
	bd_check( ! empty( $state['revision_id'] ), 'prior revision created' );
	$stale = $state['content_hash'];
	$state = bd_run( 'add-element', array( 'post_id' => $id, 'expected_hash' => $stale, 'parent_id' => 100, 'type' => 'EssentialElements\\Text', 'properties' => array( 'content' => array( 'content' => array( 'text' => 'Native Breakdance elements, powered by EMCP. Path C:\\demo and "quotes".' ) ) ) ) );
	bd_check( $state['tree']['_nextNodeId'] === 103, 'allocated ID and slash preservation' );
	$denied = wp_get_ability( 'emcp-tools/breakdance-remove-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $stale, 'element_id' => 102 ) );
	bd_check( is_wp_error( $denied ) && 'stale_page' === $denied->get_error_code(), 'stale edit rejected' );
	$state = bd_run( 'update-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 101, 'properties' => array( 'content' => array( 'content' => array( 'text' => 'Breakdance meets EMCP.' ) ) ) ) );
	bd_check( \Breakdance\Data\get_tree( $state['revision_id'] )['root']['children'][0]['children'][0]['data']['properties']['content']['content']['text'] === 'Build something remarkable.', 'revision contains previous tree' );
	bd_check( $state['tree']['root']['children'][0]['children'][0]['data']['properties']['content']['content']['tags'] === 'h1', 'nested update preserves heading tag' );
	$state = bd_run( 'move-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 102, 'parent_id' => 100, 'position' => 0 ) );
	bd_check( $state['tree']['root']['children'][0]['children'][0]['id'] === 102, 'sibling reorder' );
	$state = bd_run( 'remove-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 102 ) );
	bd_check( count( $state['tree']['root']['children'][0]['children'] ) === 1, 'subtree removal' );
	foreach ( array( array( 'invented' => 'invalid' ), array( 'design' => array( 'typography' => array( 'color' => array( 'breakpoint_wrong' => '20px' ) ) ) ) ) as $properties ) {
		$denied = wp_get_ability( 'emcp-tools/breakdance-update-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 101, 'properties' => $properties ) );
		bd_check( is_wp_error( $denied ), 'invalid property rejected without mutation' );
		bd_check( bd_run( 'get-page', array( 'post_id' => $id ) )['content_hash'] === $state['content_hash'], 'rejected save leaves content intact' );
	}
	bd_check( get_post_field( 'post_content', $id ) === 'Preserved WordPress content.', 'WordPress content preserved' );
	bd_check( \Breakdance\Data\get_meta( $id, '_breakdance_futurelayer_meta' ) === '{"test":"keep"}', 'builder metadata preserved' );
	bd_check( \Breakdance\Data\get_meta( $id, '_breakdance_template_settings' ) === array( 'test' => 'keep' ), 'template settings preserved' );
	bd_check( strpos( \Breakdance\Data\get_tree_as_html( $id ), 'Breakdance meets EMCP.' ) !== false, 'native frontend render' );
	bd_check( ! empty( $state['css_cache']['postCssFilePath'] ), 'native CSS cache generated' );
	$old_css = $state['css_cache']['postCssFilePath'];
	$state = bd_run( 'update-element', array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 101, 'properties' => array( 'design' => array( 'typography' => array( 'color' => array( 'breakpoint_base' => '#183b91' ) ) ), 'settings' => array( 'advanced' => array( 'id' => 'emcp-test-heading' ) ) ) ) );
	bd_check( $state['css_cache']['postCssFilePath'] !== $old_css, 'CSS cache regenerated after style edit' );
	bd_check( strpos( \Breakdance\Data\get_tree_as_html( $id ), 'id="emcp-test-heading"' ) !== false, 'universal HTML controls' );
	$denied_post = static function( $caps, $cap, $user_id, $arguments ) use ( $id ) { return 'edit_post' === $cap && (int) ( $arguments[0] ?? 0 ) === $id ? array( 'do_not_allow' ) : $caps; };
	add_filter( 'map_meta_cap', $denied_post, 10, 4 );
	bd_check( is_wp_error( wp_get_ability( 'emcp-tools/breakdance-get-page' )->execute( array( 'post_id' => $id ) ) ), 'per-post access denied' );
	remove_filter( 'map_meta_cap', $denied_post, 10 );
	$disabled = static function() { return array( 'emcp-tools/breakdance-update-element' ); };
	add_filter( 'pre_option_emcp_tools_disabled_tools', $disabled, 11 );
	bd_check( is_wp_error( wp_get_ability( 'emcp-tools/breakdance-update-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 101, 'properties' => array() ) ) ), 'per-tool disable honored on retained ability' );
	remove_filter( 'pre_option_emcp_tools_disabled_tools', $disabled, 11 );
	$editor_id = wp_insert_user( array( 'user_login' => 'emcp_bd_test_' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'editor' ) );
	if ( is_wp_error( $editor_id ) ) { throw new RuntimeException( 'Could not create test editor.' ); }
	\Breakdance\Permissions\givePermission( 'edit', $editor_id );
	update_post_meta( $id, '_edit_lock', time() . ':' . $editor_id );
	bd_check( is_wp_error( wp_get_ability( 'emcp-tools/breakdance-remove-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 101 ) ) ), 'another editor lock honored' );
	delete_post_meta( $id, '_edit_lock' );
	wp_set_current_user( $editor_id );
	bd_check( ! is_wp_error( wp_get_ability( 'emcp-tools/breakdance-get-page' )->execute( array( 'post_id' => $id ) ) ), 'content-only builder user can read page' );
	bd_check( is_wp_error( wp_get_ability( 'emcp-tools/breakdance-remove-element' )->execute( array( 'post_id' => $id, 'expected_hash' => $state['content_hash'], 'element_id' => 101 ) ) ), 'content-only builder user cannot change structure' );
	wp_set_current_user( $admins[0]->ID );
	$template_id = wp_insert_post( array( 'post_title' => 'EMCP temporary header fixture', 'post_type' => BREAKDANCE_HEADER_POST_TYPE, 'post_status' => 'draft' ), true );
	if ( is_wp_error( $template_id ) ) { throw new RuntimeException( 'Could not create header fixture.' ); }
	$template = bd_run( 'get-page', array( 'post_id' => $template_id ) );
	$template = bd_run( 'set-page-tree', array( 'post_id' => $template_id, 'expected_hash' => $template['content_hash'], 'tree' => $tree ) );
	bd_check( $template['tree'] === $tree, 'existing header template editing' );
	bd_check( in_array( $template_id, array_column( bd_run( 'list-templates' )['items'], 'post_id' ), true ), 'template discovery' );
	$callback = wp_get_ability( 'emcp-tools/breakdance-get-page' );
	update_option( 'emcp_tools_page_builder', '' );
	bd_check( is_wp_error( $callback->execute( array( 'post_id' => $id ) ) ), 'retained callback disabled after builder switch' );
	update_option( 'emcp_tools_page_builder', 'breakdance' );
	wp_set_current_user( 0 );
	bd_check( is_wp_error( $callback->execute( array( 'post_id' => $id ) ) ), 'anonymous access denied' );
	wp_set_current_user( $admins[0]->ID );
	$completed = true;
	if ( $keep ) { file_put_contents( __DIR__ . '/.breakdance-test-state.json', wp_json_encode( $state, JSON_PRETTY_PRINT ) ); echo 'Browser test draft: ' . $id . "\n"; }
} finally {
	wp_set_current_user( $admins[0]->ID );
	if ( $editor_id && ! is_wp_error( $editor_id ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $editor_id ); }
	if ( $template_id && ! is_wp_error( $template_id ) ) { wp_delete_post( $template_id, true ); }
	if ( $id && ( ! $keep || ! $completed ) ) { wp_delete_post( $id, true ); }
	if ( null === $saved ) { delete_option( 'emcp_tools_page_builder' ); } else { update_option( 'emcp_tools_page_builder', $saved ); }
}
