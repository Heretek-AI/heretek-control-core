<?php
/** Native Redirection API qualification. Run with wp eval-file on a local test site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) {
	throw new RuntimeException( 'Local test sites only.' );
}
$check = static function ( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } WP_CLI::log( 'PASS ' . $label ); };
$group = null;
$item = null;
try {
	$group = Red_Group::create( 'EMCP native ' . wp_generate_uuid4(), 1 );
	$check( $group instanceof Red_Group, 'native group creation' );
	$item = Red_Item::create( array( 'url' => '/emcp-native-' . wp_generate_uuid4(), 'group_id' => $group->get_id(), 'match_type' => 'url', 'action_type' => 'url', 'action_code' => 302, 'action_data' => array( 'url' => '/emcp-native-target/' ), 'title' => 'EMCP fixture', 'regex' => false ) );
	$check( $item instanceof Red_Item, 'native redirect creation' );
	$before = $item->to_json();
	$check( '/emcp-native-target/' === $before['action_data']['url'], 'native target shape' );
	$before['action_data']['url'] = '/emcp-native-updated/';
	$check( true === $item->update( $before ), 'native update accepts full JSON state' );
	$check( '/emcp-native-updated/' === Red_Item::get_by_id( $item->get_id() )->to_json()['action_data']['url'], 'fresh native read-back' );
	$item->disable();
	$check( ! Red_Item::get_by_id( $item->get_id() )->is_enabled(), 'native disable' );
	$item->enable();
	$check( Red_Item::get_by_id( $item->get_id() )->is_enabled(), 'native enable' );
	$check( 1 === Red_Group::get( $group->get_id(), true )->get_total_redirects(), 'uncached group child count' );
	$listed = Red_Item::get_filtered( array( 'filterBy' => array( 'group' => $group->get_id() ), 'per_page' => 5, 'page' => 0 ) );
	$check( 1 === $listed['total'], 'filtered native pagination' );
	$group->update( array( 'name' => 'EMCP native renamed', 'moduleId' => 1 ) );
	$check( 'EMCP native renamed' === Red_Group::get( $group->get_id(), true )->get_name(), 'group update and cache bypass' );
	$check( is_array( Red_404_Log::get_filtered( array( 'per_page' => 5 ) ) ), 'native 404 log reader' );
	$check( is_array( Red_Redirect_Log::get_filtered( array( 'per_page' => 5 ) ) ), 'native redirect log reader' );
} finally {
	if ( $item instanceof Red_Item ) { $item->delete(); }
	if ( $group instanceof Red_Group ) { $group->delete(); }
}
WP_CLI::success( 'Native Redirection API qualified; fixtures removed.' );
