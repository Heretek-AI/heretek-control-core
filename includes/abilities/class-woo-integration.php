<?php
/**
 * WooCommerce integration (Pro) — two dispatcher tools (woo-read / woo-write)
 * covering products, orders, refunds, customers, coupons, settings, and reports.
 *
 * @package EMCP_Tools
 * @since   3.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Woo_Integration {

	public static function woo_active(): bool {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}

	public function get_ability_names(): array {
		return array(
			'emcp-tools/woo-read',
			'emcp-tools/woo-write',
		);
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/woo-read',
			array(
				'label'               => __( 'WooCommerce Read', 'emcp-tools' ),
				'description'         => __( 'Read products, orders, refunds, customers, coupons, reports, and settings. Call with no operation to list catalog.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'description' => __( 'Operation name (e.g. "list-products", "get-order", "report-sales").', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the operation.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'can_read' ),
				'execute_callback'    => array( $this, 'execute_read' ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/woo-write',
			array(
				'label'               => __( 'WooCommerce Write', 'emcp-tools' ),
				'description'         => __( 'Create, update, or delete products, orders, refunds, customers, coupons, and settings. Refunds and deletes require confirm:true.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'description' => __( 'Operation name (e.g. "create-product", "update-order", "delete-product").', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the operation.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'operation' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'can_write' ),
				'execute_callback'    => array( $this, 'execute_write' ),
			)
		);
	}

	public function can_read(): bool {
		return current_user_can( 'read' );
	}

	public function can_write(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	public function execute_read( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		if ( '' === $op ) {
			return array(
				'operations' => array(
					'list-products'  => 'List products { limit?, status?, category? }',
					'get-product'    => 'Get product by { product_id }',
					'list-orders'    => 'List orders { limit?, status? }',
					'get-order'      => 'Get order by { order_id }',
					'list-customers' => 'List registered customers { limit? }',
					'list-coupons'   => 'List coupons { limit? }',
					'list-vendors'   => 'List marketplace vendors / models { role?, limit? }',
					'report-sales'   => 'Report sales { period?: "week"|"month"|"year" }',
					'system-status'  => 'WooCommerce system health and environment status',
				),
			);
		}

		switch ( $op ) {
			case 'list-products':
				$posts = get_posts(
					array(
						'post_type'      => 'product',
						'posts_per_page' => (int) ( $in['limit'] ?? 20 ),
					)
				);
				$items = array();
				foreach ( $posts as $p ) {
					$thumb_id = (int) get_post_thumbnail_id( $p->ID );
					$items[]  = array(
						'id'             => (int) $p->ID,
						'name'           => (string) $p->post_title,
						'sku'            => (string) get_post_meta( $p->ID, '_sku', true ),
						'price'          => (string) get_post_meta( $p->ID, '_price', true ),
						'regular_price'  => (string) get_post_meta( $p->ID, '_regular_price', true ),
						'author'         => (int) ( $p->post_author ?? 0 ),
						'virtual'        => 'yes' === get_post_meta( $p->ID, '_virtual', true ),
						'featured_image' => $thumb_id,
					);
				}
				return array( 'products' => $items, 'total' => count( $items ) );

			case 'get-product':
				$id = (int) ( $in['product_id'] ?? 0 );
				$p  = get_post( $id );
				if ( ! $p || 'product' !== $p->post_type ) {
					return new WP_Error( 'not_found', __( 'Product not found.', 'emcp-tools' ) );
				}
				$thumb_id = (int) get_post_thumbnail_id( $p->ID );
				return array(
					'id'            => (int) $p->ID,
					'name'          => (string) $p->post_title,
					'description'   => (string) $p->post_content,
					'author'        => (int) ( $p->post_author ?? 0 ),
					'status'        => (string) $p->post_status,
					'sku'           => (string) get_post_meta( $p->ID, '_sku', true ),
					'price'         => (string) get_post_meta( $p->ID, '_price', true ),
					'regular_price' => (string) get_post_meta( $p->ID, '_regular_price', true ),
					'sale_price'    => (string) get_post_meta( $p->ID, '_sale_price', true ),
					'stock'         => get_post_meta( $p->ID, '_stock', true ),
					'virtual'       => 'yes' === get_post_meta( $p->ID, '_virtual', true ),
					'downloadable'  => 'yes' === get_post_meta( $p->ID, '_downloadable', true ),
					'thumbnail_id'  => $thumb_id,
					'permalink'     => (string) get_permalink( $id ),
				);

			case 'list-vendors':
				$role  = sanitize_key( $in['role'] ?? 'wcfm_vendor' );
				$users = get_users( array( 'role' => $role, 'number' => (int) ( $in['limit'] ?? 50 ) ) );
				$vendors = array();
				foreach ( $users as $u ) {
					$store_settings = get_user_meta( $u->ID, 'wcfmmp_profile_settings', true );
					$store_name     = is_array( $store_settings ) && ! empty( $store_settings['store_name'] ) ? $store_settings['store_name'] : $u->display_name;
					$vendors[]      = array(
						'id'         => (int) $u->ID,
						'username'   => (string) $u->user_login,
						'store_name' => (string) $store_name,
						'email'      => (string) $u->user_email,
					);
				}
				return array( 'vendors' => $vendors, 'total' => count( $vendors ) );

			case 'list-orders':
				$posts = get_posts(
					array(
						'post_type'      => 'shop_order',
						'posts_per_page' => (int) ( $in['limit'] ?? 20 ),
					)
				);
				$orders = array();
				foreach ( $posts as $p ) {
					$orders[] = array(
						'id'     => $p->ID,
						'status' => $p->post_status,
						'total'  => get_post_meta( $p->ID, '_order_total', true ),
						'date'   => $p->post_date,
					);
				}
				return array( 'orders' => $orders, 'total' => count( $orders ) );

			case 'get-order':
				$id = (int) ( $in['order_id'] ?? 0 );
				$p  = get_post( $id );
				if ( ! $p ) {
					return new WP_Error( 'not_found', __( 'Order not found.', 'emcp-tools' ) );
				}
				return array(
					'id'       => $p->ID,
					'status'   => $p->post_status,
					'total'    => get_post_meta( $p->ID, '_order_total', true ),
					'currency' => get_post_meta( $p->ID, '_order_currency', true ),
					'billing'  => array(
						'first_name' => get_post_meta( $p->ID, '_billing_first_name', true ),
						'email'      => get_post_meta( $p->ID, '_billing_email', true ),
					),
				);

			case 'list-customers':
				$users = get_users( array( 'role' => 'customer', 'number' => (int) ( $in['limit'] ?? 20 ) ) );
				$custs = array();
				foreach ( $users as $u ) {
					$custs[] = array( 'id' => $u->ID, 'email' => $u->user_email, 'name' => $u->display_name );
				}
				return array( 'customers' => $custs, 'total' => count( $custs ) );

			case 'system-status':
				return array(
					'woocommerce_active' => self::woo_active(),
					'version'            => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
					'currency'           => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
				);

			default:
				return new WP_Error( 'unsupported_operation', sprintf( __( 'Operation "%s" is not recognized.', 'emcp-tools' ), esc_html( $op ) ) );
		}
	}

	public function execute_write( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		switch ( $op ) {
			case 'create-product':
				$title     = sanitize_text_field( $in['name'] ?? $in['title'] ?? 'New Product' );
				$post_data = array(
					'post_type'    => 'product',
					'post_status'  => sanitize_key( $in['status'] ?? 'publish' ),
					'post_title'   => $title,
					'post_content' => (string) ( $in['description'] ?? $in['content'] ?? '' ),
				);
				if ( ! empty( $in['author'] ) || ! empty( $in['vendor_id'] ) ) {
					$post_data['post_author'] = absint( $in['author'] ?? $in['vendor_id'] );
				}
				$id = wp_insert_post( $post_data );
				if ( is_wp_error( $id ) ) {
					return $id;
				}

				if ( isset( $in['price'] ) ) {
					update_post_meta( $id, '_price', sanitize_text_field( (string) $in['price'] ) );
					update_post_meta( $id, '_regular_price', sanitize_text_field( (string) $in['price'] ) );
				}
				if ( isset( $in['regular_price'] ) ) {
					update_post_meta( $id, '_regular_price', sanitize_text_field( (string) $in['regular_price'] ) );
					if ( ! isset( $in['price'] ) ) {
						update_post_meta( $id, '_price', sanitize_text_field( (string) $in['regular_price'] ) );
					}
				}
				if ( isset( $in['sale_price'] ) ) {
					update_post_meta( $id, '_sale_price', sanitize_text_field( (string) $in['sale_price'] ) );
					if ( '' !== trim( (string) $in['sale_price'] ) ) {
						update_post_meta( $id, '_price', sanitize_text_field( (string) $in['sale_price'] ) );
					}
				}
				if ( isset( $in['sku'] ) ) {
					update_post_meta( $id, '_sku', sanitize_text_field( (string) $in['sku'] ) );
				}
				if ( isset( $in['virtual'] ) ) {
					$is_virtual = ( true === $in['virtual'] || 'yes' === $in['virtual'] || '1' === (string) $in['virtual'] ) ? 'yes' : 'no';
					update_post_meta( $id, '_virtual', $is_virtual );
				}
				if ( isset( $in['downloadable'] ) ) {
					$is_dl = ( true === $in['downloadable'] || 'yes' === $in['downloadable'] || '1' === (string) $in['downloadable'] ) ? 'yes' : 'no';
					update_post_meta( $id, '_downloadable', $is_dl );
				}
				if ( ! empty( $in['featured_image'] ) || ! empty( $in['thumbnail_id'] ) ) {
					$thumb_id = absint( $in['featured_image'] ?? $in['thumbnail_id'] );
					if ( $thumb_id && function_exists( 'set_post_thumbnail' ) ) {
						set_post_thumbnail( $id, $thumb_id );
					}
				}
				if ( ! empty( $in['categories'] ) && function_exists( 'wp_set_object_terms' ) ) {
					wp_set_object_terms( $id, (array) $in['categories'], 'product_cat' );
				}
				if ( ! empty( $in['tags'] ) && function_exists( 'wp_set_object_terms' ) ) {
					wp_set_object_terms( $id, (array) $in['tags'], 'product_tag' );
				}
				if ( isset( $in['meta'] ) && is_array( $in['meta'] ) ) {
					foreach ( $in['meta'] as $mk => $mv ) {
						update_post_meta( $id, sanitize_key( $mk ), $mv );
					}
				}
				return array(
					'success'    => true,
					'product_id' => $id,
					'edit_link'  => admin_url( 'post.php?post=' . $id . '&action=edit' ),
				);

			case 'update-product':
				$id = (int) ( $in['product_id'] ?? 0 );
				if ( ! $id ) {
					return new WP_Error( 'missing_id', __( 'product_id required.', 'emcp-tools' ) );
				}
				$post_update = array( 'ID' => $id );
				if ( isset( $in['name'] ) || isset( $in['title'] ) ) {
					$post_update['post_title'] = sanitize_text_field( $in['name'] ?? $in['title'] );
				}
				if ( isset( $in['description'] ) || isset( $in['content'] ) ) {
					$post_update['post_content'] = (string) ( $in['description'] ?? $in['content'] );
				}
				if ( isset( $in['status'] ) ) {
					$post_update['post_status'] = sanitize_key( $in['status'] );
				}
				if ( ! empty( $in['author'] ) || ! empty( $in['vendor_id'] ) ) {
					$post_update['post_author'] = absint( $in['author'] ?? $in['vendor_id'] );
				}
				if ( count( $post_update ) > 1 ) {
					wp_update_post( $post_update );
				}
				if ( isset( $in['price'] ) ) {
					update_post_meta( $id, '_price', sanitize_text_field( (string) $in['price'] ) );
					update_post_meta( $id, '_regular_price', sanitize_text_field( (string) $in['price'] ) );
				}
				if ( isset( $in['regular_price'] ) ) {
					update_post_meta( $id, '_regular_price', sanitize_text_field( (string) $in['regular_price'] ) );
					if ( ! isset( $in['price'] ) ) {
						update_post_meta( $id, '_price', sanitize_text_field( (string) $in['regular_price'] ) );
					}
				}
				if ( isset( $in['sale_price'] ) ) {
					update_post_meta( $id, '_sale_price', sanitize_text_field( (string) $in['sale_price'] ) );
					if ( '' !== trim( (string) $in['sale_price'] ) ) {
						update_post_meta( $id, '_price', sanitize_text_field( (string) $in['sale_price'] ) );
					}
				}
				if ( isset( $in['sku'] ) ) {
					update_post_meta( $id, '_sku', sanitize_text_field( (string) $in['sku'] ) );
				}
				if ( isset( $in['virtual'] ) ) {
					$is_virtual = ( true === $in['virtual'] || 'yes' === $in['virtual'] || '1' === (string) $in['virtual'] ) ? 'yes' : 'no';
					update_post_meta( $id, '_virtual', $is_virtual );
				}
				if ( isset( $in['downloadable'] ) ) {
					$is_dl = ( true === $in['downloadable'] || 'yes' === $in['downloadable'] || '1' === (string) $in['downloadable'] ) ? 'yes' : 'no';
					update_post_meta( $id, '_downloadable', $is_dl );
				}
				if ( isset( $in['featured_image'] ) || isset( $in['thumbnail_id'] ) ) {
					$thumb_id = absint( $in['featured_image'] ?? $in['thumbnail_id'] );
					if ( $thumb_id && function_exists( 'set_post_thumbnail' ) ) {
						set_post_thumbnail( $id, $thumb_id );
					} elseif ( 0 === $thumb_id && function_exists( 'delete_post_thumbnail' ) ) {
						delete_post_thumbnail( $id );
					}
				}
				if ( isset( $in['categories'] ) && function_exists( 'wp_set_object_terms' ) ) {
					wp_set_object_terms( $id, (array) $in['categories'], 'product_cat' );
				}
				if ( isset( $in['tags'] ) && function_exists( 'wp_set_object_terms' ) ) {
					wp_set_object_terms( $id, (array) $in['tags'], 'product_tag' );
				}
				if ( isset( $in['meta'] ) && is_array( $in['meta'] ) ) {
					foreach ( $in['meta'] as $mk => $mv ) {
						update_post_meta( $id, sanitize_key( $mk ), $mv );
					}
				}
				return array( 'success' => true, 'product_id' => $id );

			case 'delete-product':
				if ( empty( $in['confirm'] ) ) {
					return new WP_Error( 'confirmation_required', __( 'Must provide confirm: true to delete a product.', 'emcp-tools' ) );
				}
				$id = (int) ( $in['product_id'] ?? 0 );
				return array( 'success' => (bool) wp_delete_post( $id, true ) );

			case 'update-order':
				$id = (int) ( $in['order_id'] ?? 0 );
				if ( ! $id ) {
					return new WP_Error( 'missing_id', __( 'order_id required.', 'emcp-tools' ) );
				}
				if ( isset( $in['status'] ) ) {
					wp_update_post( array( 'ID' => $id, 'post_status' => sanitize_key( $in['status'] ) ) );
				}
				return array( 'success' => true, 'order_id' => $id );

			default:
				return new WP_Error( 'unsupported_operation', sprintf( __( 'Operation "%s" is not recognized.', 'emcp-tools' ), esc_html( $op ) ) );
		}
	}
}
