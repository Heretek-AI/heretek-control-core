<?php
/**
 * Tests User Abilities (create-user, update-user, get-user) with meta support.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/abilities/class-user-abilities.php';

class UserAbilitiesTest extends \PHPUnit\Framework\TestCase {

	private EMCP_Tools_User_Abilities $abilities;

	protected function setUp(): void {
		parent::setUp();
		emcp_test_reset();
		$this->abilities = new EMCP_Tools_User_Abilities();
	}

	public function test_forbidden_meta_keys_blocked(): void {
		$blocked = array(
			'capabilities',
			'wp_capabilities',
			'user_level',
			'wp_user_level',
			'session_tokens',
			'default_password_nag',
			'user_pass',
			'auth_token',
			'secret_key',
			'activation_key',
			'nonce_field',
			'password_reset',
		);
		foreach ( $blocked as $key ) {
			$this->assertTrue(
				EMCP_Tools_User_Abilities::is_forbidden_user_meta_key( $key ),
				"Expected key '{$key}' to be forbidden"
			);
		}
	}

	public function test_safe_vendor_and_profile_meta_keys_allowed(): void {
		$allowed = array(
			'wcfmmp_profile_settings',
			'store_name',
			'phone',
			'twitter',
			'instagram',
			'payout_paypal',
			'creator_tier',
			'billing_address_1',
			'shipping_city',
			'model_bio',
		);
		foreach ( $allowed as $key ) {
			$this->assertFalse(
				EMCP_Tools_User_Abilities::is_forbidden_user_meta_key( $key ),
				"Expected key '{$key}' to be permitted"
			);
		}
	}

	public function test_create_user_with_safe_meta(): void {
		$input = array(
			'username' => 'testvendor',
			'email'    => 'vendor@example.com',
			'role'     => 'wcfm_vendor',
			'meta'     => array(
				'store_name'   => 'Jackie Studio',
				'creator_tier' => 'vip',
				'capabilities' => 'hacked', // Forbidden!
			),
		);
		$result = $this->abilities->execute_create_user( $input );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$id = $result['id'];

		// Safe meta saved
		$this->assertSame( 'Jackie Studio', get_user_meta( $id, 'store_name', true ) );
		$this->assertSame( 'vip', get_user_meta( $id, 'creator_tier', true ) );

		// Forbidden meta dropped
		$this->assertSame( '', get_user_meta( $id, 'capabilities', true ) );
	}

	public function test_update_user_with_safe_meta(): void {
		$user_id = wp_insert_user( array(
			'user_login' => 'model1',
			'user_email' => 'model1@example.com',
			'role'       => 'wcfm_vendor',
		) );

		$input = array(
			'id'   => $user_id,
			'meta' => array(
				'twitter'   => 'https://x.com/model1',
				'user_pass' => 'injected', // Forbidden!
			),
		);
		$res = $this->abilities->execute_update_user( $input );
		$this->assertIsArray( $res );
		$this->assertContains( 'meta', $res['updated'] );

		// Safe meta saved
		$this->assertSame( 'https://x.com/model1', get_user_meta( $user_id, 'twitter', true ) );

		// Forbidden meta dropped
		$this->assertSame( '', get_user_meta( $user_id, 'user_pass', true ) );
	}

	public function test_get_user_with_include_meta(): void {
		$user_id = wp_insert_user( array(
			'user_login' => 'model2',
			'user_email' => 'model2@example.com',
			'role'       => 'wcfm_vendor',
		) );
		update_user_meta( $user_id, 'store_name', 'Model 2 Store' );
		update_user_meta( $user_id, 'session_tokens', 'supersecret' );

		// Without include_meta
		$without = $this->abilities->execute_get_user( array( 'id' => $user_id ) );
		$this->assertIsArray( $without );
		$this->assertEquals( (object) array(), $without['meta'] );

		// With include_meta
		$with = $this->abilities->execute_get_user( array( 'id' => $user_id, 'include_meta' => true ) );
		$this->assertIsArray( $with );
		$this->assertArrayHasKey( 'meta', $with );
		$this->assertArrayHasKey( 'store_name', $with['meta'] );
		$this->assertSame( 'Model 2 Store', $with['meta']['store_name'] );

		// Sensitive session_tokens redacted
		$this->assertArrayNotHasKey( 'session_tokens', $with['meta'] );
	}
}
