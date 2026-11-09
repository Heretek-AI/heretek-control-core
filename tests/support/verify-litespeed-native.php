<?php
/** Real LSCache spike. Run only inside the disposable loopback OpenLiteSpeed fixture. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:18880' !== home_url() || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Disposable local OLS fixture only.' ); }
global $wpdb;
$checks = 0;
$check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::log( 'PASS ' . $label ); };
$check( '7.9.2' === LSCWP_V && class_exists( 'LiteSpeed\\Purge' ), 'actual qualified LiteSpeed plugin loaded' );
$conf = LiteSpeed\Conf::cls(); $conf->load_options();
$check( 1 === (int) $conf->conf( LiteSpeed\Base::O_CACHE ), 'native cache configuration enabled' );
$saved = array(); foreach ( array( 'show_on_front', 'page_on_front' ) as $key ) { $saved[ $key ] = get_option( $key ); }
$token = wp_generate_uuid4(); $mu = WPMU_PLUGIN_DIR . '/emcp-native-cache-fixture.php'; $post = 0;
if ( file_exists( $mu ) ) { throw new RuntimeException( 'Fixture already exists.' ); }
wp_mkdir_p( WPMU_PLUGIN_DIR );
$code = '<?php add_action("rest_api_init", static function(){ register_rest_route("emcp-cache-fixture/v1", "/purge", array("methods"=>"POST", "permission_callback"=>static function($r){return hash_equals(' . var_export( $token, true ) . ', (string)$r->get_header("X-EMCP-Fixture"));}, "callback"=>static function(){ $ok=LiteSpeed\\Purge::cls()->purge_url("/",false,true); return array("ok"=>$ok,"server"=>LITESPEED_SERVER_TYPE,"allowed"=>defined("LITESPEED_ALLOWED") && LITESPEED_ALLOWED); })); });';
file_put_contents( $mu, $code );
$purge = static function () use ( $token ) { return wp_remote_post( home_url( '/?rest_route=/emcp-cache-fixture/v1/purge' ), array( 'headers' => array( 'X-EMCP-Fixture' => $token ), 'timeout' => 30 ) ); };
$get = static function () { $r = wp_remote_get( home_url( '/' ), array( 'timeout' => 30 ) ); if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_code() ); } return $r; };
try {
	$post = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'EMCP cache fixture', 'post_content' => 'EMCP_OLD_' . $token ), true );
	if ( is_wp_error( $post ) ) { throw new RuntimeException( 'Fixture creation failed.' ); }
	update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $post );
	$r = $purge(); $data = json_decode( wp_remote_retrieve_body( $r ), true );
	$check( 200 === wp_remote_retrieve_response_code( $r ) && ! empty( $data['ok'] ) && ! empty( $data['allowed'] ), 'native HTTP purge accepted by supported server' );
	$r = $get(); $check( 'miss' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ), 'real cache MISS' );
	$r = $get(); $check( 'hit' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ) && str_contains( wp_remote_retrieve_body( $r ), 'EMCP_OLD_' . $token ), 'real cache HIT with old content' );
	// Deliberately bypass native automatic invalidation for this owned fixture only.
	$wpdb->update( $wpdb->posts, array( 'post_content' => 'EMCP_NEW_' . $token ), array( 'ID' => $post ) ); clean_post_cache( $post );
	$r = $get(); $check( str_contains( wp_remote_retrieve_body( $r ), 'EMCP_OLD_' . $token ), 'cached content stays stale before explicit purge' );
	$purge(); $r = $get();
	$check( 'miss' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ) && str_contains( wp_remote_retrieve_body( $r ), 'EMCP_NEW_' . $token ), 'native purge produces fresh MISS' );
	$r = $get(); $check( 'hit' === wp_remote_retrieve_header( $r, 'x-litespeed-cache' ) && str_contains( wp_remote_retrieve_body( $r ), 'EMCP_NEW_' . $token ), 'fresh content cached as HIT' );
} finally {
	foreach ( $saved as $key => $value ) { update_option( $key, $value ); }
	if ( $post && ! is_wp_error( $post ) ) { wp_delete_post( $post, true ); }
	$purge(); unlink( $mu );
}
WP_CLI::success( $checks . ' native cache checks passed.' );
