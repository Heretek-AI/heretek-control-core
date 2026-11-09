<?php
/** Installed CPT UI API qualification with disposable content. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$check = static function ( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } WP_CLI::log( 'PASS ' . $label ); };
$slug = 'emcp_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 10 );
$tax = $slug . '_tax';
$post = 0;
$term = 0;
$old_post = $_POST;
$user = get_current_user_id();
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $admins[0] );
$common = array_fill_keys( array( 'description', 'rewrite_slug', 'query_var_slug', 'rest_base', 'rest_controller_class', 'rest_namespace', 'menu_icon', 'register_meta_box_cb', 'has_archive_string', 'menu_position', 'show_in_menu_string', 'custom_supports', 'enter_title_here', 'default_term', 'meta_box_cb' ), '' );
$common += array_fill_keys( array( 'public', 'publicly_queryable', 'show_ui', 'show_in_nav_menus', 'show_in_rest', 'rewrite', 'rewrite_withfront', 'query_var', 'show_in_menu', 'can_export' ), 'true' );
$common += array_fill_keys( array( 'delete_with_user', 'has_archive', 'exclude_from_search', 'hierarchical', 'rewrite_hierarchical', 'show_admin_column', 'show_tagcloud', 'sort', 'show_in_quick_edit' ), 'false' );
try {
	$definition = array( 'name' => $slug, 'label' => 'EMCP Books', 'singular_label' => 'EMCP Book', 'capability_type' => 'post' ) + $common;
	$_POST = array( 'cpt_custom_post_type' => $definition, 'cpt_type_status' => 'new', 'cpt_original' => $slug, 'cpt_supports' => array( 'title', 'editor' ), 'cpt_addon_taxes' => array(), 'cpt_labels' => array() );
	$check( 'add_success' === cptui_update_post_type( $_POST ), 'native post type save' );
	cptui_register_single_post_type( cptui_get_post_type_data()[ $slug ] );
	$check( post_type_exists( $slug ) && get_post_type_object( $slug )->show_in_rest, 'native post type registration' );
	$_POST['cpt_type_status'] = 'edit';
	$_POST['cpt_custom_post_type']['description'] = 'Native description';
	$check( 'update_success' === cptui_update_post_type( $_POST ), 'native post type update' );
	$check( 'Native description' === cptui_get_post_type_data()[ $slug ]['description'], 'native description uses scoped POST' );
	$_POST = array( 'cpt_custom_tax' => array( 'name' => $tax, 'label' => 'EMCP Topics', 'singular_label' => 'EMCP Topic' ) + $common, 'cpt_tax_status' => 'new', 'tax_original' => $tax, 'cpt_post_types' => array( $slug ), 'cpt_tax_labels' => array() );
	$check( 'add_success' === cptui_update_taxonomy( $_POST ), 'native taxonomy save' );
	cptui_register_single_taxonomy( cptui_get_taxonomy_data()[ $tax ] );
	$check( taxonomy_exists( $tax ) && is_object_in_taxonomy( $slug, $tax ), 'native taxonomy link' );
	$post = wp_insert_post( array( 'post_type' => $slug, 'post_title' => 'Native fixture', 'post_status' => 'draft' ), true );
	$check( ! is_wp_error( $post ), 'native content creation' );
	$created = wp_insert_term( 'Native fixture', $tax );
	$check( ! is_wp_error( $created ), 'native term creation' );
	$term = $created['term_id'];
	$check( ! is_wp_error( wp_set_object_terms( $post, array( $term ), $tax ) ), 'native term assignment' );
	$check( has_term( $term, $tax, $post ), 'native linked read-back' );
} finally {
	if ( is_int( $post ) && $post ) { wp_delete_post( $post, true ); }
	if ( $term ) { wp_delete_term( $term, $tax ); }
	cptui_delete_taxonomy( array( 'name' => $tax ) );
	cptui_delete_post_type( array( 'name' => $slug ) );
	if ( taxonomy_exists( $tax ) ) { unregister_taxonomy( $tax ); }
	if ( post_type_exists( $slug ) ) { unregister_post_type( $slug ); }
	cptui_flush_rewrite_rules();
	$_POST = $old_post;
	wp_set_current_user( $user );
}
WP_CLI::success( '10 native CPT UI checks; fixtures removed.' );
