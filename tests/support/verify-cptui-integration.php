<?php
/** Real CPT UI schema, content, HTTP and History qualification. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$checks = 0;
$check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::log( 'PASS ' . $label ); };
$good = static function ( $result, $label ) use ( $check ) { $check( ! is_wp_error( $result ), $label . ( is_wp_error( $result ) ? ': ' . $result->get_error_code() : '' ) ); return $result; };
$tool = new EMCP_Tools_Cptui_Integration(); $history = array();
$disabled = get_option( 'emcp_tools_disabled_tools', null ); $user = get_current_user_id();
$slug = 'ecpt_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 ); $tax = $slug . '_t'; $failed_slug = $slug . '_f';
$post = 0; $term = 0; $thrower = null;
$call = static function ( $operation, array $args ) use ( $tool, &$history ) {
	$result = $tool->run_write( array( 'operation' => $operation, 'arguments' => $args + array( 'confirm' => true ) ) );
	$data = is_wp_error( $result ) ? $result->get_error_data() : $result;
	if ( ! empty( $data['change_id'] ) ) { $history[] = $data['change_id']; }
	return $result;
};
try {
	wp_set_current_user( 0 ); $check( is_wp_error( $tool->run_read( array() ) ), 'guest denied' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) ); wp_set_current_user( $admins[0] );
	update_option( 'emcp_tools_disabled_tools', array( 'emcp-tools/cptui-write' ) );
	$check( 'tool_disabled' === $call( 'create-post-type', array() )->get_error_code(), 'disabled callback denied' );
	update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) $disabled, array( 'emcp-tools/cptui-read', 'emcp-tools/cptui-write' ) ) ) );
	$good( $tool->run_read( array( 'operation' => 'status' ) ), 'status' );
	$check( null !== wp_get_ability( 'emcp-tools/cptui-write' ), 'ability registered' );
	$base = array( 'slug' => $slug, 'label' => "Reader's Books & Notes", 'singular_label' => "Reader's Book", 'description' => 'First description C:\\books\\notes' );
	$check( is_wp_error( $call( 'create-post-type', $base + array( 'confirm' => false ) ) ), 'confirmation required' );
	$good( $call( 'create-post-type', $base + array( 'dry_run' => true ) ), 'dry run' );
	$check( ! EMCP_Tools_Cptui_Api::row( 'post-type', $slug ), 'dry run stored nothing' );
	$check( 'protected_slug' === $call( 'create-post-type', array_replace( $base, array( 'slug' => 'post' ) ) )->get_error_code(), 'core type protected' );
	$check( is_wp_error( $call( 'create-post-type', $base + array( 'register_meta_box_cb' => 'phpinfo' ) ) ), 'callback input rejected' );
	$check( is_wp_error( $call( 'create-post-type', $base + array( 'public' => 'true' ) ) ), 'string boolean rejected' );
	$created = $good( $call( 'create-post-type', $base ), 'create native post type' );
	$check( htmlspecialchars( $base['label'], ENT_QUOTES ) === $created['definition']['label'] && $base['description'] === $created['definition']['description'], 'native encoded labels and literal backslashes preserved' );
	$check( post_type_exists( $slug ) && get_post_type_object( $slug )->show_in_rest, 'current process registration' );
	$response = wp_remote_get( home_url( '/wp-json/wp/v2/types/' . $slug ), array( 'sslverify' => false, 'timeout' => 45 ) );
	$check( 200 === wp_remote_retrieve_response_code( $response ), 'fresh HTTP REST registration' );
	$edited = $good( $call( 'update-post-type', array( 'slug' => $slug, 'version' => $created['version'], 'description' => '' ) ), 'clear description' );
	$check( '' === cptui_get_post_type_data()[ $slug ]['description'], 'empty description persisted' );
	$check( 'conflict' === $call( 'update-post-type', array( 'slug' => $slug, 'version' => $created['version'], 'label' => 'Stale' ) )->get_error_code(), 'stale version refused' );
	$good( EMCP_Tools_Change_Log::rollback( $edited['change_id'] ), 'native update undo' );
	$check( $base['description'] === cptui_get_post_type_data()[ $slug ]['description'], 'description restored exactly, including backslashes' );
	$stored = get_option( 'cptui_post_types' );
	$external = static function ( $rows ) use ( $slug ) { $rows[ $slug ]['description'] = 'External storage view'; return $rows; };
	add_filter( 'cptui_get_post_type_data', $external );
	try { $blocked = EMCP_Tools_Change_Log::rollback( $created['change_id'], true ); $check( is_wp_error( $blocked ) && 'filtered_storage' === $blocked->get_error_code() && $stored === get_option( 'cptui_post_types' ), 'Force undo refuses changed native storage without deleting definitions' ); }
	finally { remove_filter( 'cptui_get_post_type_data', $external ); }
	$rows = cptui_get_post_type_data(); $rows[ $slug ]['fixture_extension'] = 'preserve'; update_option( 'cptui_post_types', $rows );
	$blocked = $call( 'update-post-type', array( 'slug' => $slug, 'version' => EMCP_Tools_Cptui_Api::hash(), 'label' => 'Blocked extension' ) );
	$check( is_wp_error( $blocked ) && 'unsupported_definition' === $blocked->get_error_code() && 'preserve' === cptui_get_post_type_data()[ $slug ]['fixture_extension'], 'extension-owned fields refused without loss' );
	unset( $rows[ $slug ]['fixture_extension'] ); update_option( 'cptui_post_types', $rows );
	$taxonomy = $good( $call( 'create-taxonomy', array( 'slug' => $tax, 'label' => "Reader's Topics & Tags", 'singular_label' => "Reader's Topic", 'description' => 'C:\\topics\\notes', 'object_types' => array( $slug ) ) ), 'create native taxonomy' );
	$check( 'C:\\topics\\notes' === $taxonomy['definition']['description'] && htmlspecialchars( "Reader's Topic", ENT_QUOTES ) === $taxonomy['definition']['singular_label'], 'taxonomy labels and backslashes preserved' );
	$check( is_object_in_taxonomy( $slug, $tax ), 'native type relationship' );
	$post = wp_insert_post( array( 'post_type' => $slug, 'post_title' => 'Fixture', 'post_status' => 'draft' ), true ); $good( $post, 'native content creation' );
	$term_row = $good( wp_insert_term( 'Fixture', $tax ), 'native term creation' ); $term = $term_row['term_id'];
	$good( wp_set_object_terms( $post, array( $term ), $tax ), 'term assignment' );
	$check( has_term( $term, $tax, $post ), 'native relationship read-back' );
	$check( 'definition_in_use' === EMCP_Tools_Change_Log::rollback( $taxonomy['change_id'], true )->get_error_code(), 'populated taxonomy blocks forced undo' );
	$check( 'definition_in_use' === EMCP_Tools_Change_Log::rollback( $created['change_id'], true )->get_error_code(), 'content and dependent taxonomy block forced type undo' );
	update_option( 'default_term_' . $tax, $term );
	$check( 'unsupported_definition' === $call( 'update-taxonomy', array( 'slug' => $tax, 'version' => EMCP_Tools_Cptui_Api::hash(), 'label' => 'Blocked' ) )->get_error_code(), 'default term side effect blocked' );
	$check( (int) $term === (int) get_option( 'default_term_' . $tax ), 'default term preserved' ); delete_option( 'default_term_' . $tax );
	$thrower = static function () { throw new RuntimeException( 'fixture native after-save failure' ); };
	add_action( 'cptui_after_update_post_type', $thrower );
	$failed = $call( 'create-post-type', array_replace( $base, array( 'slug' => $failed_slug ) ) );
	remove_action( 'cptui_after_update_post_type', $thrower ); $thrower = null;
	$check( 'native_error_recovered' === $failed->get_error_code(), 'attributed create hook failure recovered' );
	$check( ! EMCP_Tools_Cptui_Api::row( 'post-type', $failed_slug ), 'failed create removed only owned definition' );
	$deny = static function () { return 'do_not_allow'; }; add_filter( 'cptui_required_capabilities', $deny );
	try { $check( is_wp_error( EMCP_Tools_Change_Log::rollback( $created['change_id'], true ) ), 'force cannot bypass native capability' ); }
	finally { remove_filter( 'cptui_required_capabilities', $deny ); }
	wp_delete_post( $post, true ); $post = 0; wp_delete_term( $term, $tax ); $term = 0;
	$good( EMCP_Tools_Change_Log::rollback( $taxonomy['change_id'], true ), 'undo empty taxonomy creation' );
	$check( ! taxonomy_exists( $tax ), 'taxonomy unregistered in current process' );
	$good( EMCP_Tools_Change_Log::rollback( $created['change_id'], true ), 'undo empty post type creation' );
	$check( ! post_type_exists( $slug ), 'post type unregistered in current process' );
} finally {
	if ( $thrower ) { remove_action( 'cptui_after_update_post_type', $thrower ); }
	if ( is_int( $post ) && $post ) { wp_delete_post( $post, true ); }
	if ( $term ) { wp_delete_term( $term, $tax ); }
	delete_option( 'default_term_' . $tax );
	if ( EMCP_Tools_Cptui_Api::row( 'taxonomy', $tax ) ) { cptui_delete_taxonomy( array( 'name' => $tax ) ); }
	foreach ( array( $slug, $failed_slug ) as $name ) { if ( EMCP_Tools_Cptui_Api::row( 'post-type', $name ) ) { cptui_delete_post_type( array( 'name' => $name ) ); } EMCP_Tools_Cptui_Api::refresh( 'post-type', $name ); }
	EMCP_Tools_Cptui_Api::refresh( 'taxonomy', $tax );
	foreach ( $history as $id ) { EMCP_Tools_Change_Log::delete( $id ); }
	null === $disabled ? delete_option( 'emcp_tools_disabled_tools' ) : update_option( 'emcp_tools_disabled_tools', $disabled ); wp_set_current_user( $user );
}
WP_CLI::success( $checks . ' native CPT UI integration assertions; fixtures removed.' );
