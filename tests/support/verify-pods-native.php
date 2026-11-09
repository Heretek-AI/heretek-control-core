<?php
/** Native Pods API exploration, limited to task-owned post/meta content. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$check = static function ( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } WP_CLI::log( 'PASS ' . $label ); };
$name = 'epods_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 ); $pod_id = 0; $item_id = 0;
$user = get_current_user_id(); $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) ); wp_set_current_user( $admins[0] );
try {
	$pod_id = pods_api()->save_pod( array( 'name' => $name, 'label' => 'EMCP Pods fixtures', 'type' => 'post_type', 'storage' => 'meta', 'public' => 1, 'show_ui' => 1, 'rest_enable' => 1, 'fields' => array( array( 'name' => 'fixture_note', 'label' => 'Fixture note', 'type' => 'text' ), array( 'name' => 'fixture_score', 'label' => 'Fixture score', 'type' => 'number' ), array( 'name' => 'fixture_flag', 'label' => 'Fixture flag', 'type' => 'boolean' ) ) ) );
	$check( is_int( $pod_id ) && $pod_id > 0, 'native pod and fields created' );
	$schema = pods_api()->load_pod( array( 'name' => $name, 'bypass_cache' => true ) );
	$check( $schema instanceof \Pods\Whatsit\Pod && 'meta' === $schema['storage'], 'native schema object and storage' );
	$check( 3 === count( $schema->get_fields() ), 'native field definitions' );
	pods_meta()->cache_pods( true );
	pods_init()->setup_content_types( true );
	$check( post_type_exists( $name ), 'native post type registered' );
	$check( $pod_id === pods_api()->save_pod( array( 'id' => $pod_id, 'label' => 'Renamed fixture' ) ), 'native schema label update' );
	$check( 3 === count( pods_api()->load_pod( array( 'name' => $name, 'bypass_cache' => true ) )->get_fields() ), 'native label update preserves fields' );
	$pods = new Pods( $name );
	$item_id = $pods->add( array( 'post_title' => 'EMCP native fixture', 'post_status' => 'draft', 'fixture_note' => 'Before', 'fixture_score' => 7, 'fixture_flag' => true ) );
	$check( is_numeric( $item_id ) && $item_id > 0 && $name === get_post_type( $item_id ), 'native record creation' );
	$pods = new Pods( $name, $item_id );
	$check( 'Before' === $pods->field( 'fixture_note', true, true ), 'native raw field read' );
	$check( $item_id == $pods->save( array( 'fixture_note' => '', 'fixture_score' => 9, 'fixture_flag' => false ) ), 'native typed update' );
	$read = new Pods( $name, $item_id );
	$check( '' === $read->field( 'fixture_note', true, true ), 'native explicit empty field' );
	$check( 9 === (int) $read->field( 'fixture_score', true, true ), 'native number read-back' );
	$check( ! $read->field( 'fixture_flag', true, true ), 'native false read-back' );
	$check( pods_permission( $schema->get_fields()['fixture_note'] ), 'native field access check' );
	$response = wp_remote_get( home_url( '/wp-json/wp/v2/types/' . $name ), array( 'sslverify' => false, 'timeout' => 45 ) );
	$check( 200 === wp_remote_retrieve_response_code( $response ), 'native schema registered in fresh HTTP' );
} finally {
	if ( is_numeric( $item_id ) && $item_id > 0 ) { wp_delete_post( (int) $item_id, true ); }
	if ( is_int( $pod_id ) && $pod_id > 0 ) { pods_api()->delete_pod( array( 'id' => $pod_id ), false, false ); }
	if ( post_type_exists( $name ) ) { unregister_post_type( $name ); }
	flush_rewrite_rules( false ); wp_set_current_user( $user );
}
WP_CLI::success( '14 native Pods checks; fixtures removed.' );
