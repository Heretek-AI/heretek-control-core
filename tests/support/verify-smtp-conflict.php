<?php
/** Real competing-mailer regression. Requires both native plugins; must never send. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'https://msrplugins.test' !== home_url() || ! function_exists( 'wp_mail_smtp' ) || ! function_exists( 'fluentMailGetSettings' ) ) { throw new RuntimeException( 'Both native mail plugins on the local Tools site are required.' ); }
$disabled = get_option( 'emcp_tools_disabled_tools', null ); $actor = get_current_user_id(); $attempts = 0;
$trap = static function ( $result ) use ( &$attempts ) { ++$attempts; return true; };
$request = wp_generate_uuid4();
try {
	wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
	update_option( 'emcp_tools_disabled_tools', array_values( array_diff( (array) $disabled, array( 'emcp-tools/wp-mail-smtp-write' ) ) ) );
	add_filter( 'pre_wp_mail', $trap, PHP_INT_MAX );
	foreach ( array( true, false ) as $dry ) {
		$result = ( new EMCP_Tools_Wp_Mail_Smtp_Integration() )->run_write( array( 'operation' => 'send-test', 'arguments' => array( 'request_id' => $request, 'recipient' => 'conflict@example.test', 'confirm' => true, 'dry_run' => $dry ) ) );
		if ( ! is_wp_error( $result ) || 'competing_mailer' !== $result->get_error_code() ) { throw new RuntimeException( 'Competing native mailer was not refused.' ); }
	}
	if ( $attempts || false !== get_option( EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( $request ) ) ) { throw new RuntimeException( 'Conflict unexpectedly attempted or reserved a send.' ); }
} finally {
	remove_filter( 'pre_wp_mail', $trap, PHP_INT_MAX );
	null === $disabled ? delete_option( 'emcp_tools_disabled_tools' ) : update_option( 'emcp_tools_disabled_tools', $disabled ); wp_set_current_user( $actor );
}
WP_CLI::success( '3 real competing-mailer assertions; no mail attempted or receipt created.' );
