<?php
require_once __DIR__ . '/local-mcp-harness.php';
$test = new EMCP_Local_Mcp_Test( array( 'emcp-tools/wp-mail-smtp-read', 'emcp-tools/wp-mail-smtp-write' ) );
$missing = new stdClass(); $before = get_option( WPMailSMTP\Options::META_KEY, $missing );
$id = wp_generate_uuid4(); $recipient = 'http-' . $id . '@example.test'; $history = array();
$mu = WPMU_PLUGIN_DIR . '/emcp-smtp-fixture-' . $id . '.php';
try {
	$options = WPMailSMTP\Options::init();
	$options->set( array( 'mail' => array( 'mailer' => 'smtp', 'from_email' => 'fixture@example.test', 'from_name' => 'EMCP local fixture', 'from_email_force' => true ), 'smtp' => array( 'host' => '127.0.0.1', 'port' => 11025, 'encryption' => 'none', 'auth' => false, 'autotls' => false, 'user' => '', 'pass' => '' ) ) );
	$test->check( '127.0.0.1' === $options->get( 'smtp', 'host' ) && 11025 === (int) $options->get( 'smtp', 'port' ) && 'smtp' === $options->get( 'mail', 'mailer' ), 'Effective mailer is local sink' );
	// Allow only this fixture recipient past Lite's existing local no-mail callback.
	$source = '<?php add_filter("pre_wp_mail", static function($result,$args){ if (($args["to"] ?? "") === ' . var_export( $recipient, true ) . ' && function_exists("wp_mail_smtp")) { $o=WPMailSMTP\\Options::init(); if($o->get("mail","mailer")==="smtp" && $o->get("smtp","host")==="127.0.0.1" && (int)$o->get("smtp","port")===11025 && !$o->get("smtp","auth")){return null;} } return $result; }, PHP_INT_MAX-1, 2);';
	if ( false === file_put_contents( $mu, $source ) ) { throw new RuntimeException( 'Cannot create scoped local fixture.' ); }
	$test->initialize(); $read = $test->tool( 'wp-mail-smtp-read' ); $write = $test->tool( 'wp-mail-smtp-write' );
	$call = static function ( $tool, $operation, $args = array() ) use ( $test, &$history ) { $reply = $test->rpc( 'tools/call', array( 'name' => $tool, 'arguments' => array( 'operation' => $operation, 'arguments' => (object) $args ) ) ); $data = EMCP_Local_Mcp_Test::data( $reply ); if ( ! empty( $data['change_id'] ) ) { $history[] = $data['change_id']; } return $reply; };
	$status = EMCP_Local_Mcp_Test::data( $call( $read, 'status' ) ); $test->check( '4.10.3' === ( $status['version'] ?? '' ), 'MCP reads installed native version' );
	$args = array( 'request_id' => $id, 'recipient' => $recipient, 'confirm' => true );
	$sent = EMCP_Local_Mcp_Test::data( $call( $write, 'send-test', $args ) ); $test->check( 'accepted' === ( $sent['state'] ?? '' ), 'Real MCP test accepted by SMTP sink' );
	$again = EMCP_Local_Mcp_Test::data( $call( $write, 'send-test', $args ) ); $test->check( $again === $sent, 'Fresh HTTP process recovers receipt without resending' );
	$messages = json_decode( wp_remote_retrieve_body( wp_remote_get( 'http://127.0.0.1:18025/api/v1/messages' ) ), true );
	$found = array_filter( $messages['messages'] ?? array(), static function ( $row ) use ( $recipient ) { return in_array( $recipient, array_column( $row['To'] ?? array(), 'Address' ), true ); } ); $test->check( 1 === count( $found ), 'Exactly one MCP-triggered email captured' );
	update_option( 'emcp_tools_strict_schemas', '1' ); $strict = array_fill_keys( array_keys( EMCP_Tools_Wp_Mail_Smtp_Integration::fields() ), null ); $strict['request_id'] = $id;
	$receipt = EMCP_Local_Mcp_Test::data( $call( $read, 'test-status', $strict ) ); $test->check( $receipt === $sent, 'Strict transport reads receipt' );
	update_option( 'emcp_tools_strict_schemas', '0' );
	update_option( 'emcp_tools_disabled_tools', array_merge( (array) get_option( 'emcp_tools_disabled_tools' ), array( 'emcp-tools/wp-mail-smtp-write' ) ) );
	$bad = $call( $write, 'send-test', $args ); $test->check( ! empty( $bad['result']['isError'] ) || isset( $bad['error'] ), 'Disabled tool denied through MCP' );
} finally {
	if ( file_exists( $mu ) ) { unlink( $mu ); }
	if ( $missing === $before ) { delete_option( WPMailSMTP\Options::META_KEY ); } else { update_option( WPMailSMTP\Options::META_KEY, $before ); }
	delete_option( EMCP_Tools_Wp_Mail_Smtp_Api::receipt_key( $id ) );
	foreach ( array_unique( $history ) as $change ) { EMCP_Tools_Change_Log::delete( $change ); } $test->close();
}
WP_CLI::success( $test->checks . ' actual SMTP MCP HTTP assertions; scoped mail fixture removed.' );
