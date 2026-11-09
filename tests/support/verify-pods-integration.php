<?php
/** Real Pods integration and native History smoke with bounded disposable fixtures. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local test sites only.' ); }
$checks = 0; $history = array(); $records = array(); $pod_id = 0; $thrower = null;
$name = 'epods_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 9 );
$check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::log( 'PASS ' . $label ); };
$good = static function ( $result, $label ) use ( $check, &$history ) { if ( is_wp_error( $result ) && ! empty( $result->get_error_data()['change_id'] ) ) { $history[] = $result->get_error_data()['change_id']; } $check( ! is_wp_error( $result ), $label . ( is_wp_error( $result ) ? ': ' . $result->get_error_code() : '' ) ); return $result; };
$tool = new EMCP_Tools_Pods_Integration(); $disabled = get_option( 'emcp_tools_disabled_tools', null ); $user = get_current_user_id();
$call = static function ( $op, array $args ) use ( $tool, &$history, &$records, &$pod_id ) {
	$result = $tool->run_write( array( 'operation' => $op, 'arguments' => $args + array( 'confirm' => true ) ) );
	$data = is_wp_error( $result ) ? $result->get_error_data() : $result;
	if ( ! empty( $data['change_id'] ) ) { $history[] = $data['change_id']; }
	if ( ! is_wp_error( $result ) && empty( $result['dry_run'] ) ) { if ( 'create-pod' === $op ) { $pod_id = $result['id']; } elseif ( 'create-record' === $op ) { $records[] = $result['id']; } }
	return $result;
};
$read = static function ( $op, array $args = array() ) use ( $tool ) { return $tool->run_read( array( 'operation' => $op, 'arguments' => $args ) ); };
try {
	wp_set_current_user( 0 ); $check( is_wp_error( $read( 'status' ) ), 'guest denied' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) ); wp_set_current_user( $admins[0] );
	update_option( 'emcp_tools_disabled_tools', array( 'emcp-tools/pods-write' ) ); $check( 'tool_disabled' === $call( 'create-pod', array() )->get_error_code(), 'disabled callback denied' );
	update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) $disabled, array( 'emcp-tools/pods-read', 'emcp-tools/pods-write' ) ) ) );
	$good( $read( 'status' ), 'native status' ); $check( null !== wp_get_ability( 'emcp-tools/pods-write' ), 'ability registered' );
	$base = array( 'pod' => $name, 'label' => 'Fixture Pod', 'fields' => array( array( 'name' => 'fixture_note', 'label' => 'Note', 'type' => 'text' ), array( 'name' => 'fixture_score', 'label' => 'Score', 'type' => 'number' ), array( 'name' => 'fixture_flag', 'label' => 'Flag', 'type' => 'boolean' ) ) );
	$check( is_wp_error( $call( 'create-pod', $base + array( 'confirm' => false ) ) ), 'confirmation enforced' );
	$good( $call( 'create-pod', $base + array( 'dry_run' => true ) ), 'dry run' ); $check( ! pods_api()->load_pod( $name ), 'dry run writes nothing' );
	$check( is_wp_error( $call( 'create-pod', array_replace( $base, array( 'pod' => 'post' ) ) ) ), 'existing core type protected' );
	$created = $good( $call( 'create-pod', $base ), 'native schema created' );
	$check( post_type_exists( $name ), 'new schema registered in current process' );
	$schema = $good( $read( 'get-pod', array( 'pod' => $name ) ), 'get schema' ); $check( 3 === count( $schema['fields'] ), 'all native fields retained' );
	$renamed = $good( $call( 'update-pod-label', array( 'pod' => $name, 'version' => $schema['version'], 'label' => 'Renamed fixture' ) ), 'native label update' );
	$good( EMCP_Tools_Change_Log::rollback( $renamed['change_id'] ), 'native label undo' );
	$check( 'Fixture Pod' === pods_api()->load_pod( array( 'name' => $name, 'bypass_cache' => true ) )['label'], 'label restored' );
	$values = array( array( 'name' => 'fixture_note', 'value' => 'Before' ), array( 'name' => 'fixture_score', 'value' => 7 ), array( 'name' => 'fixture_flag', 'value' => true ) );
	$record = $good( $call( 'create-record', array( 'pod' => $name, 'title' => 'Fixture one', 'values' => $values ) ), 'create native draft' ); $id = $record['id'];
	$check( 'draft' === get_post_status( $id ), 'creation stays draft' );
	$second = $good( $call( 'create-record', array( 'pod' => $name, 'title' => 'Fixture two', 'values' => array( array( 'name' => 'fixture_note', 'value' => 'Second' ) ) ) ), 'second native draft' );
	$good( $read( 'get-record', array( 'pod' => $name, 'id' => $second['id'] ) ), 'read second record' );
	$first = $good( $read( 'get-record', array( 'pod' => $name, 'id' => $id ) ), 'read first record again' );
	$check( 'Before' === $first['values']['fixture_note'], 'cross-record cache isolation' );
	$edited = $good( $call( 'update-record', array( 'pod' => $name, 'id' => $id, 'version' => $first['version'], 'values' => array( array( 'name' => 'fixture_note', 'value' => '' ), array( 'name' => 'fixture_flag', 'value' => false ) ) ) ), 'clear text and boolean' );
	$live = $good( $read( 'get-record', array( 'pod' => $name, 'id' => $id ) ), 'read changed record' );
	$check( '' === $live['values']['fixture_note'] && ! $live['values']['fixture_flag'], 'cleared values persisted' );
	$check( 7 === (int) $live['values']['fixture_score'], 'unrelated scalar preserved' );
	$check( 'conflict' === $call( 'update-record', array( 'pod' => $name, 'id' => $id, 'version' => $first['version'], 'values' => $values ) )->get_error_code(), 'stale record refused' );
	$good( EMCP_Tools_Change_Log::rollback( $edited['change_id'] ), 'native field undo' );
	$live = $good( $read( 'get-record', array( 'pod' => $name, 'id' => $id ) ), 'read restored record' ); $check( 'Before' === $live['values']['fixture_note'], 'field restored' );
	$check( is_wp_error( $call( 'update-record', array( 'pod' => $name, 'id' => $id, 'version' => $live['version'], 'values' => array( array( 'name' => 'post_status', 'value' => 'publish' ) ) ) ) ), 'core field mutation refused' );
	$check( is_wp_error( $call( 'update-record', array( 'pod' => $name, 'id' => $id, 'version' => $live['version'], 'values' => array( array( 'name' => 'fixture_flag', 'value' => 'false' ) ) ) ) ), 'wrong native value type refused' );
	$once = true; $thrower = static function ( $pieces ) use ( &$once ) { if ( $once ) { $once = false; throw new RuntimeException( 'fixture after-save failure' ); } return $pieces; };
	add_filter( 'pods_api_post_save_pod_item_' . $name, $thrower );
	$failed = $call( 'update-record', array( 'pod' => $name, 'id' => $id, 'version' => $live['version'], 'values' => array( array( 'name' => 'fixture_note', 'value' => 'Failure' ) ) ) );
	remove_filter( 'pods_api_post_save_pod_item_' . $name, $thrower ); $thrower = null;
	$check( is_wp_error( $failed ) && 'native_error_recovered' === $failed->get_error_code(), 'native post-save error recovered' );
	$check( 'Before' === ( new Pods( $name, $id ) )->field( 'fixture_note', true, true ), 'recovery native read-back' );
	$list = $good( $read( 'list-records', array( 'pod' => $name, 'per_page' => 1 ) ), 'bounded record listing' ); $check( 2 === $list['total'] && 1 === count( $list['items'] ), 'correct pagination and totals' );
	$check( 'irreversible' === EMCP_Tools_Change_Log::rollback( $created['change_id'] )->get_error_code(), 'schema creation honestly irreversible' );
	$check( 'irreversible' === EMCP_Tools_Change_Log::rollback( $record['change_id'] )->get_error_code(), 'draft creation honestly irreversible' );
	$alter = static function ( $pieces, $is_new, $native_id ) use ( &$records ) { if ( $is_new ) { $records[] = (int) $native_id; update_post_meta( $native_id, 'fixture_note', 'Native hook changed the requested value' ); } return $pieces; };
	add_filter( 'pods_api_post_save_pod_item_' . $name, $alter, 20, 3 );
	try {
		$partial = $call( 'create-record', array( 'pod' => $name, 'title' => 'Incomplete fixture', 'values' => array( array( 'name' => 'fixture_note', 'value' => 'Requested value' ) ) ) );
		$check( is_wp_error( $partial ) && 'partial_write' === $partial->get_error_code() && ! empty( $partial->get_error_data()['created_ids'] ), 'native create mismatch is an attributed partial write, never a false success' );
	} finally { remove_filter( 'pods_api_post_save_pod_item_' . $name, $alter, 20 ); }
	$http = wp_remote_get( home_url( '/wp-json/wp/v2/types/' . $name ), array( 'sslverify' => false, 'timeout' => 45 ) ); $check( 200 === wp_remote_retrieve_response_code( $http ), 'fresh HTTP schema registration' );
} finally {
	if ( $thrower ) { remove_filter( 'pods_api_post_save_pod_item_' . $name, $thrower ); }
	foreach ( $records as $id ) { wp_delete_post( $id, true ); }
	if ( $pod_id ) { pods_api()->delete_pod( array( 'id' => $pod_id ), false, false ); }
	if ( post_type_exists( $name ) ) { unregister_post_type( $name ); } flush_rewrite_rules( false );
	foreach ( array_unique( $history ) as $id ) { EMCP_Tools_Change_Log::delete( $id ); }
	null === $disabled ? delete_option( 'emcp_tools_disabled_tools' ) : update_option( 'emcp_tools_disabled_tools', $disabled ); wp_set_current_user( $user );
}
WP_CLI::success( $checks . ' real Pods integration assertions; fixtures removed.' );
