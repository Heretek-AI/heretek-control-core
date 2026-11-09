<?php
/** Real installed-plugin smoke, including native HTTP outcome and History. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$checks = 0;
$check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::log( 'PASS ' . $label ); };
$tool = new EMCP_Tools_Redirection_Integration();
$original_user = get_current_user_id();
$disabled = get_option( 'emcp_tools_disabled_tools', null );
$groups = array();
$items = array();
$history = array();
$thrower = null;
$call = static function ( $operation, $args = array() ) use ( $tool, &$groups, &$items, &$history ) {
	$result = $tool->run_write( array( 'operation' => $operation, 'arguments' => $args + array( 'confirm' => true ) ) );
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( is_array( $data ) && ! empty( $data['change_id'] ) ) { $history[] = $data['change_id']; }
		return $result;
	}
	if ( ! empty( $result['change_id'] ) ) { $history[] = $result['change_id']; }
	if ( 'create-group' === $operation && empty( $result['dry_run'] ) ) { $groups[] = $result['id']; }
	if ( 'create-redirect' === $operation && empty( $result['dry_run'] ) ) { $items[] = $result['id']; }
	return $result;
};
$good = static function ( $result, $label ) use ( $check ) { $check( ! is_wp_error( $result ), $label . ( is_wp_error( $result ) ? ': ' . $result->get_error_code() : '' ) ); return $result; };
try {
	wp_set_current_user( 0 );
	$check( 'forbidden' === $tool->run_read( array() )->get_error_code(), 'guest denied' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( $admins[0] );
	update_option( 'emcp_tools_disabled_tools', array( 'emcp-tools/redirection-write' ) );
	$check( 'tool_disabled' === $call( 'create-group', array( 'name' => 'not created' ) )->get_error_code(), 'disabled callback denied' );
	update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) $disabled, array( 'emcp-tools/redirection-read', 'emcp-tools/redirection-write' ) ) ) );
	$good( $tool->run_read( array( 'operation' => 'status' ) ), 'status' );
	$check( null !== wp_get_ability( 'emcp-tools/redirection-read' ), 'read ability registered' );
	$check( null !== wp_get_ability( 'emcp-tools/redirection-write' ), 'write ability registered' );
	$check( is_wp_error( $call( 'get-redirect', array( 'id' => 1 ) ) ), 'wrong mode denied' );
	$check( is_wp_error( $tool->run_read( array( 'operation' => 'get-redirect', 'arguments' => array( 'id' => '1' ) ) ) ), 'numeric string denied' );
	$check( is_wp_error( $call( 'create-group', array( 'name' => 'not created', 'confirm' => false ) ) ), 'confirmation enforced' );
	$dry = $good( $call( 'create-group', array( 'name' => 'not created', 'dry_run' => true ) ), 'dry run' );
	$check( true === $dry['dry_run'], 'dry run did not write' );
	$group = $good( $call( 'create-group', array( 'name' => 'EMCP smoke ' . substr( wp_generate_uuid4(), 0, 12 ) ) ), 'create group' );
	$source = '/emcp-redirection-smoke-' . wp_generate_uuid4();
	$redirect = $good( $call( 'create-redirect', array( 'source' => $source, 'target' => '/emcp-redirection-target/', 'group_id' => $group['id'], 'title' => 'Initial', 'code' => 302 ) ), 'create redirect' );
	$id = $redirect['id'];
	$response = wp_remote_get( home_url( $source ), array( 'redirection' => 0, 'sslverify' => false, 'timeout' => 45 ) );
	$good( $response, 'local HTTP request' );
	$check( 302 === wp_remote_retrieve_response_code( $response ), 'front-end redirect status' );
	$check( false !== strpos( wp_remote_retrieve_header( $response, 'location' ), '/emcp-redirection-target/' ), 'front-end redirect target' );
	$edited = $good( $call( 'update-redirect', array( 'id' => $id, 'version' => $redirect['version'], 'target' => '/emcp-redirection-new/', 'title' => '' ) ), 'update and clear title' );
	$check( '' === (string) $edited['object']['title'], 'empty title persisted' );
	$check( 'conflict' === $call( 'disable-redirect', array( 'id' => $id, 'version' => $redirect['version'] ) )->get_error_code(), 'stale write rejected' );
	$good( EMCP_Tools_Change_Log::rollback( $edited['change_id'] ), 'History undo update' );
	$restored = EMCP_Tools_Redirection_Api::view( 'redirect', $id );
	$check( 'Initial' === $restored['title'] && '/emcp-redirection-target/' === $restored['action_data']['url'], 'native restored configuration' );
	$disabled_item = $good( $call( 'disable-redirect', array( 'id' => $id, 'version' => $restored['version'] ) ), 'disable redirect' );
	$check( ! Red_Item::get_by_id( $id )->is_enabled(), 'native disabled read-back' );
	$good( EMCP_Tools_Change_Log::rollback( $disabled_item['change_id'] ), 'History undo disabled state' );
	$live = EMCP_Tools_Redirection_Api::view( 'redirect', $id );
	$check( 'redirect_cycle' === $call( 'update-redirect', array( 'id' => $id, 'version' => $live['version'], 'target' => $source ) )->get_error_code(), 'self redirect rejected' );
	$check( is_wp_error( $call( 'update-redirect', array( 'id' => $id, 'version' => $live['version'], 'target' => 'https://user:secret@example.com/' ) ) ), 'credential URL rejected' );
	$throw_once = true;
	$thrower = static function ( $changed ) use ( $id, &$throw_once ) { if ( $changed === $id && $throw_once ) { $throw_once = false; throw new RuntimeException( 'fixture hook failure' ); } };
	add_action( 'redirection_redirect_updated', $thrower );
	$failed = $call( 'update-redirect', array( 'id' => $id, 'version' => $live['version'], 'target' => '/emcp-redirection-failed/' ) );
	remove_action( 'redirection_redirect_updated', $thrower );
	$thrower = null;
	$check( is_wp_error( $failed ) && 'native_error_recovered' === $failed->get_error_code(), 'post-write hook failure recovered' );
	$check( '/emcp-redirection-target/' === EMCP_Tools_Redirection_Api::item( $id )['action_data']['url'], 'failed update restored' );
	// Change the native row after History's precheck but before our leased handler.
	$live = EMCP_Tools_Redirection_Api::view( 'redirect', $id );
	$race_change = $good( $call( 'update-redirect', array( 'id' => $id, 'version' => $live['version'], 'title' => 'Before race' ) ), 'prepare undo interleaving' );
	$race = static function ( $result, $rollback ) use ( $id ) {
		if ( 'redirection-native' === ( $rollback['type'] ?? '' ) && $id === $rollback['object_id'] ) {
			$row = EMCP_Tools_Redirection_Api::config( EMCP_Tools_Redirection_Api::item( $id ) );
			$row['title'] = 'Native concurrent change';
			Red_Item::get_by_id( $id )->update( $row );
		}
		return $result;
	};
	add_filter( 'emcp_tools_change_apply_rollback', $race, 5, 2 );
	try { $check( 'conflict' === EMCP_Tools_Change_Log::rollback( $race_change['change_id'] )->get_error_code(), 'undo rechecks after precheck interleaving' ); }
	finally { remove_filter( 'emcp_tools_change_apply_rollback', $race, 5 ); }
	$check( 'Native concurrent change' === EMCP_Tools_Redirection_Api::item( $id )['title'], 'undo preserves intervening native edit' );
	$deny = static function () { return 'do_not_allow'; };
	add_filter( 'redirection_capability_check', $deny );
	try {
		$check( is_wp_error( $tool->run_read( array( 'operation' => 'list-logs' ) ) ), 'native log capability enforced' );
		$check( 'forbidden' === EMCP_Tools_Change_Log::rollback( $race_change['change_id'], true )->get_error_code(), 'force undo cannot bypass native capability' );
	} finally { remove_filter( 'redirection_capability_check', $deny ); }
	$owner = wp_generate_uuid4();
	$lease = new EMCP_Tools_Lease();
	$check( $lease->acquire( EMCP_Tools_Redirection_Writer::LOCK, $owner, 30 ), 'test lease acquired' );
	try { $check( 'redirection_busy' === $call( 'create-group', array( 'name' => 'not created' ) )->get_error_code(), 'concurrent writer refused' ); }
	finally { $lease->release( EMCP_Tools_Redirection_Writer::LOCK, $owner ); }
	$group_live = EMCP_Tools_Redirection_Api::view( 'group', $group['id'] );
	$renamed = $good( $call( 'rename-group', array( 'id' => $group['id'], 'version' => $group_live['version'], 'name' => 'EMCP renamed' ) ), 'rename group' );
	$good( EMCP_Tools_Change_Log::rollback( $renamed['change_id'] ), 'undo group rename' );
	$good( $tool->run_read( array( 'operation' => 'list-404s' ) ), '404 log read' );
	$good( $tool->run_read( array( 'operation' => 'list-logs' ) ), 'redirect log read' );
	$live = EMCP_Tools_Redirection_Api::view( 'redirect', $id );
	$deleted = $good( $call( 'delete-redirect', array( 'id' => $id, 'version' => $live['version'] ) ), 'delete redirect' );
	$check( ! Red_Item::get_by_id( $id ), 'native deletion read-back' );
	$check( 'irreversible' === EMCP_Tools_Change_Log::rollback( $deleted['change_id'] )->get_error_code(), 'delete honestly irreversible' );
} finally {
	if ( isset( $source ) ) {
		foreach ( Red_Redirect_Log::get_filtered( array( 'filterBy' => array( 'url-exact' => $source ), 'per_page' => 100 ) )['items'] as $log ) { Red_Redirect_Log::delete( $log['id'] ); }
	}
	if ( $thrower ) { remove_action( 'redirection_redirect_updated', $thrower ); }
	foreach ( $items as $id ) { $item = Red_Item::get_by_id( $id ); if ( $item ) { $item->delete(); } }
	foreach ( $groups as $id ) { $group = Red_Group::get( $id, true ); if ( $group && 0 === $group->get_total_redirects() ) { $group->delete(); } }
	foreach ( $history as $id ) { EMCP_Tools_Change_Log::delete( $id ); }
	if ( null === $disabled ) { delete_option( 'emcp_tools_disabled_tools' ); } else { update_option( 'emcp_tools_disabled_tools', $disabled ); }
	wp_set_current_user( $original_user );
}
WP_CLI::success( $checks . ' real Redirection integration checks; fixtures removed.' );
