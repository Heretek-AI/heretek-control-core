<?php
/** Actual native send through the task-owned local sink. Never uses saved credentials. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( home_url(), array( 'https://msrplugins.test', 'https://emcp-lite.test' ), true ) ) { throw new RuntimeException( 'Local fixtures only.' ); }
$key = WPMailSMTP\Options::META_KEY;
$sentinel = new stdClass(); $before = get_option( $key, $sentinel );
$checks = 0;
$suppressed = has_filter( 'pre_wp_mail', '__return_true' );
$check = static function ( $ok, $message ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; };
try {
	$options = WPMailSMTP\Options::init();
	$options->set( array( 'mail' => array( 'mailer' => 'smtp', 'from_email' => 'fixture@example.test', 'from_name' => 'EMCP local fixture', 'from_email_force' => true ), 'smtp' => array( 'host' => '127.0.0.1', 'port' => 11025, 'encryption' => 'none', 'auth' => false, 'autotls' => false, 'user' => '', 'pass' => '' ) ) );
	$check( '127.0.0.1' === $options->get( 'smtp', 'host' ) && 11025 === (int) $options->get( 'smtp', 'port' ) && ! $options->get( 'smtp', 'auth' ) && 'smtp' === $options->get( 'mail', 'mailer' ), 'Effective settings must point only to local sink.' );
	if ( false !== $suppressed ) { remove_filter( 'pre_wp_mail', '__return_true', $suppressed ); }
	$recipient = 'native-' . wp_generate_uuid4() . '@example.test';
	$test = ( new WPMailSMTP\TestEmail\TestEmail() )->as_html( false )->with_domain_check( false );
	$check( 1 === $test->send( $recipient ), 'Native test send succeeds.' );
	$response = wp_remote_get( 'http://127.0.0.1:18025/api/v1/messages' );
	$messages = json_decode( wp_remote_retrieve_body( $response ), true );
	$found = array_filter( $messages['messages'] ?? array(), static function ( $row ) use ( $recipient ) { return in_array( $recipient, array_column( $row['To'] ?? array(), 'Address' ), true ); } );
	$check( 1 === count( $found ), 'Exactly one native message reached local sink.' );
	$options->set( array( 'smtp' => array( 'port' => 11026 ) ), false, false );
	$check( 0 === ( new WPMailSMTP\TestEmail\TestEmail() )->as_html( false )->with_domain_check( false )->send( 'failure@example.test' ), 'Native connection refusal reports failure.' );
} finally {
	if ( false !== $suppressed ) { add_filter( 'pre_wp_mail', '__return_true', $suppressed ); }
	if ( $sentinel === $before ) { delete_option( $key ); } else { update_option( $key, $before ); }
}
WP_CLI::success( $checks . ' native WP Mail SMTP checks; original options restored.' );
