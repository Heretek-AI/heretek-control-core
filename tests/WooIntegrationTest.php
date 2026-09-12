<?php
/**
 * Tests WooCommerce and Marketplace Creator Integration.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/abilities/class-woo-integration.php';

class WooIntegrationTest extends \PHPUnit\Framework\TestCase {

	private EMCP_Tools_Woo_Integration $woo;

	protected function setUp(): void {
		parent::setUp();
		emcp_test_reset();
		$this->woo = new EMCP_Tools_Woo_Integration();
	}

	public function test_catalog_operations_includes_list_vendors(): void {
		$catalog = $this->woo->execute_read( array( 'operation' => '' ) );
		$this->assertIsArray( $catalog );
		$this->assertArrayHasKey( 'operations', $catalog );
		$this->assertArrayHasKey( 'list-vendors', $catalog['operations'] );
		$this->assertArrayHasKey( 'list-products', $catalog['operations'] );
	}

	public function test_create_product_with_vendor_and_virtual(): void {
		// Mock wp_insert_post if needed, or check stub
		if ( ! function_exists( 'wp_insert_post' ) ) {
			// define stub
		}

		$input = array(
			'operation' => 'create-product',
			'arguments' => array(
				'name'          => 'VIP Access Pass',
				'price'         => '14.99',
				'regular_price' => '14.99',
				'sale_price'    => '9.99',
				'sku'           => 'VIP-PASS-01',
				'author'        => 2,
				'virtual'       => true,
				'downloadable'  => true,
				'meta'          => array(
					'custom_perk' => 'exclusive_vods',
				),
			),
		);

		$res = $this->woo->execute_write( $input );
		$this->assertIsArray( $res );
		$this->assertTrue( $res['success'] );
		$product_id = $res['product_id'];

		$this->assertSame( '9.99', get_post_meta( $product_id, '_price', true ) );
		$this->assertSame( '14.99', get_post_meta( $product_id, '_regular_price', true ) );
		$this->assertSame( '9.99', get_post_meta( $product_id, '_sale_price', true ) );
		$this->assertSame( 'VIP-PASS-01', get_post_meta( $product_id, '_sku', true ) );
		$this->assertSame( 'yes', get_post_meta( $product_id, '_virtual', true ) );
		$this->assertSame( 'yes', get_post_meta( $product_id, '_downloadable', true ) );
		$this->assertSame( 'exclusive_vods', get_post_meta( $product_id, 'custom_perk', true ) );

		$post = get_post( $product_id );
		$this->assertNotNull( $post );
		$this->assertSame( 2, (int) $post->post_author );
	}

	public function test_list_vendors_returns_models(): void {
		$user_id = wp_insert_user( array(
			'user_login'   => 'jackie12358',
			'user_email'   => 'jackie@example.com',
			'display_name' => 'Jackie Trans Goddess',
			'role'         => 'wcfm_vendor',
		) );

		update_user_meta( $user_id, 'wcfmmp_profile_settings', array(
			'store_name' => 'Jackie VIP Studio',
		) );

		// Stub get_users if not stubbed
		$input = array(
			'operation' => 'list-vendors',
			'arguments' => array( 'role' => 'wcfm_vendor' ),
		);

		$res = $this->woo->execute_read( $input );
		$this->assertIsArray( $res );
		$this->assertArrayHasKey( 'vendors', $res );
		$this->assertCount( 1, $res['vendors'] );
		$this->assertSame( 'jackie12358', $res['vendors'][0]['username'] );
		$this->assertSame( 'Jackie VIP Studio', $res['vendors'][0]['store_name'] );
	}
}
