<?php
/**
 * Tests Content Abilities e-commerce meta and featured_image handling.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/abilities/class-content-abilities.php';

class ContentAbilitiesMetaTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		parent::setUp();
		emcp_test_reset();
	}

	public function test_allowed_protected_meta_contains_woocommerce_keys(): void {
		$allowed = EMCP_Tools_Content_Abilities::get_allowed_protected_meta();
		$this->assertIsArray( $allowed );

		$expected_keys = array(
			'_price',
			'_regular_price',
			'_sale_price',
			'_virtual',
			'_downloadable',
			'_sku',
			'_stock_status',
			'_stock',
			'_thumbnail_id',
			'_pay_per_post_price',
			'_wp_page_template',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertContains( $key, $allowed, "Expected '{$key}' to be included in default allowed protected meta" );
		}

		// Ensure dangerous internal Elementor keys are NOT in allowed list
		$this->assertNotContains( '_elementor_data', $allowed );
		$this->assertNotContains( '_elementor_css', $allowed );
	}

	public function test_format_post_featured_image_without_thumbnail_returns_object_structure(): void {
		$abilities = new EMCP_Tools_Content_Abilities();

		$post = new \WP_Post( array(
			'ID'             => 42,
			'post_title'     => 'Sample Post Without Thumbnail',
			'post_name'      => 'sample-post',
			'post_status'    => 'publish',
			'post_content'   => 'Some content',
			'post_excerpt'   => '',
			'post_date'      => '2026-09-12 12:00:00',
			'post_type'      => 'post',
			'post_author'    => 1,
			'comment_status' => 'closed',
		) );

		$ref = new \ReflectionClass( $abilities );
		$method = $ref->getMethod( 'format_post' );

		$formatted = $method->invoke( $abilities, $post );
		$this->assertIsArray( $formatted );
		$this->assertArrayHasKey( 'featured_image', $formatted );
		$this->assertIsArray( $formatted['featured_image'] );
		$this->assertSame( 0, $formatted['featured_image']['id'] );
		$this->assertSame( '', $formatted['featured_image']['url'] );
		$this->assertSame( '', $formatted['featured_image']['alt'] );
	}
}
