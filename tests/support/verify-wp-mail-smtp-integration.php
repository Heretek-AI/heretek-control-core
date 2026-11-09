<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local fixtures only.' ); }
$sentinel = new stdClass(); $keys = array( WPMailSMTP\Options::META_KEY, 'emcp_tools_disabled_tools' ); $before = array(); foreach ( $keys as $key ) { $before[ $key ] = get_option( $key, $sentinel ); }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]; wp_set_current_user( $admin->ID );
$ids = array(); $history = array(); $checks = 0; $suppressed = has_filter( 'pre_wp_mail', '__return_true' );
$check = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
$integration = new EMCP_Tools_Wp_Mail_Smtp_Integration();
$send = static function ( $id, $recipient, $extra = array() ) use ( $integration, &$ids, &$history ) {
	$ids[] = $id; $result = $integration->run_write( array( 'operation' => 'send-test', 'arguments' => $extra + array( 'request_id' => $id, 'recipient' => $recipient, 'confirm' => true ) ) );
	if ( is_array( $result ) && ! empty( $result['change_id'] ) ) { $history[] = $result['change_id']; } return $result;
};
try {
	$options = WPMailSMTP\Options::init();
	$options->set( array( 'mail' => array( 'mailer' => 'smtp', 'from_email' => 'fixture@example.test', 'from_name' => 'EMCP local fixture', 'from_email_force' => true ), 'smtp' => array( 'host' => '127.0.0.1', 'port' => 11025, 'encryption' => 'none', 'auth' => false, 'autotls' => false, 'user' => '', 'pass' => '' ) ) );
	$check( '127.0.0.1' === $options->get( 'smtp', 'host' ) && 11025 === (int) $options->get( 'smtp', 'port' ) && ! $options->get( 'smtp', 'auth' ) && 'smtp' === $options->get( 'mail', 'mailer' ), 'Loopback sink configured.' );
	update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/wp-mail-smtp-read', 'emcp-tools/wp-mail-smtp-write' ) ) ) );
	if ( false !== $suppressed ) { remove_filter( 'pre_wp_mail', '__return_true', $suppressed ); }
	$status = $integration->run_read( array( 'operation' => 'status' ) ); $check( 'smtp' === $status['mailer'] && ! isset( $status['password'] ), 'Redacted native status.' );
	$id = wp_generate_uuid4(); $recipient = 'adapter-' . $id . '@example.test';
	$dry = $send( $id, $recipient, array( 'dry_run' => true ) ); $check( ! empty( $dry['dry_run'] ) && false === get_option( EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( $id ) ), 'Dry run does not reserve or send.' );
	$bad = $send( $id, $recipient, array( 'confirm' => false ) ); $check( is_wp_error( $bad ) && 'confirm_required' === $bad->get_error_code(), 'Explicit confirmation required.' );
	$good = $send( $id, $recipient ); $check( 'accepted' === ( $good['state'] ?? '' ), 'Native transport accepted.' );
	$check( $good === $send( $id, $recipient ), 'Repeated UUID returns receipt without resending.' );
	$bad = $send( $id, 'different@example.test' ); $check( is_wp_error( $bad ) && 'request_conflict' === $bad->get_error_code(), 'Request cannot change recipient.' );
	$messages = json_decode( wp_remote_retrieve_body( wp_remote_get( 'http://127.0.0.1:18025/api/v1/messages' ) ), true );
	$found = array_filter( $messages['messages'] ?? array(), static function ( $row ) use ( $recipient ) { return in_array( $recipient, array_column( $row['To'] ?? array(), 'Address' ), true ); } ); $check( 1 === count( $found ), 'Exactly one real message captured.' );
	$receipt = $integration->run_read( array( 'operation' => 'test-status', 'arguments' => array( 'request_id' => $id ) ) ); $check( $receipt === $good, 'Status recovers receipt.' );
	$check( false === strpos( wp_json_encode( EMCP_Tools_Change_Log::get( $good['change_id'] ) ), $recipient ), 'History excludes recipient and body.' );
	$throw = static function () { throw new RuntimeException( 'Secret diagnostic must never leave native layer' ); }; add_action( 'wp_mail_smtp_test_email_send_after', $throw );
	try { $uncertain_id = wp_generate_uuid4(); $uncertain = $send( $uncertain_id, 'uncertain@example.test' ); $check( 'unknown' === $uncertain['state'], 'Post-send exception is uncertain.' ); $check( $uncertain === $send( $uncertain_id, 'uncertain@example.test' ), 'Uncertain attempt is never replayed.' ); } finally { remove_action( 'wp_mail_smtp_test_email_send_after', $throw ); }
	add_filter( 'pre_wp_mail', '__return_true' ); try { $intercepted = $send( wp_generate_uuid4(), 'intercepted@example.test' ); $check( 'intercepted' === $intercepted['state'], 'Suppressed mail is not reported accepted.' ); } finally { remove_filter( 'pre_wp_mail', '__return_true' ); }
	$lost_id = wp_generate_uuid4(); $lost_key = EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( $lost_id );
	$drop = static function ( $value, $old ) { return 'unknown' !== ( $value['state'] ?? '' ) ? $old : $value; }; add_filter( 'pre_update_option_' . $lost_key, $drop, 10, 2 );
	try { $lost = $send( $lost_id, 'receipt-failure@example.test' ); $check( 'unknown' === $lost['state'], 'Unpersisted delivery outcome reported unknown' ); $check( $lost === $send( $lost_id, 'receipt-failure@example.test' ), 'Lost outcome receipt prevents duplicate delivery' ); } finally { remove_filter( 'pre_update_option_' . $lost_key, $drop, 10 ); }
	$external = get_option( WPMailSMTP\Options::META_KEY ); $external['smtp']['port'] = 11026; update_option( WPMailSMTP\Options::META_KEY, $external );
	$failed = $send( wp_generate_uuid4(), 'failure@example.test' ); $check( 'failed' === $failed['state'], 'Long-lived native options refresh sees external port edit and refuses connection.' );
	wp_set_current_user( 0 ); $check( is_wp_error( $integration->run_read( array() ) ), 'Guest refused.' ); wp_set_current_user( $admin->ID );
	update_option( 'emcp_tools_disabled_tools', array( 'emcp-tools/wp-mail-smtp-write' ) ); $check( is_wp_error( $send( wp_generate_uuid4(), 'disabled@example.test' ) ), 'Disabled send refused.' );
} finally {
	if ( false !== $suppressed ) { add_filter( 'pre_wp_mail', '__return_true', $suppressed ); }
	foreach ( $before as $key => $value ) { if ( $sentinel === $value ) { delete_option( $key ); } else { update_option( $key, $value ); } }
	foreach ( array_unique( $ids ) as $id ) { delete_option( EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( $id ) ); }
	foreach ( array_unique( $history ) as $id ) { EMCP_Tools_Change_Log::delete( $id ); }
}
WP_CLI::success( $checks . ' WP Mail SMTP integration checks; options and test receipts restored.' );
