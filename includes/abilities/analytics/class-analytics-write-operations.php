<?php
/**
 * Heretek Analytics Write Operations for MCP.
 *
 * Provides GA4 custom dimension provisioning, telemetry cache flushing,
 * and duplicate tag conflict resolution.
 *
 * @package EMCP_Tools
 * @since   3.21.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Analytics_Write_Operations
 */
class EMCP_Tools_Analytics_Write_Operations {

	/**
	 * Operation schema catalog.
	 *
	 * @return array<string, array>
	 */
	public static function op_schema(): array {
		return array(
			'provision-dimensions' => array(
				'description' => 'Provision all standard Heretek Analytics custom dimensions (author_id, author, character, chapter, location, post_tag, category, post_type) in the connected GA4 property.',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'example'     => array(),
			),
			'flush-cache' => array(
				'description' => 'Clear cached Heretek Analytics GA4 dashboard, realtime, and taxonomy transients.',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'example'     => array(),
			),
			'resolve-sitekit-conflict' => array(
				'description' => 'Disable duplicate frontend gtag.js snippet output in Google Site Kit (useSnippet = false) so Heretek Analytics remains the single authoritative GA4 tracker.',
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'confirm' ),
					'properties' => array(
						'confirm' => array(
							'type'        => 'boolean',
							'description' => 'Must be true to update Google Site Kit snippet settings.',
						),
					),
				),
				'example'     => array( 'confirm' => true ),
			),
		);
	}

	/**
	 * Dispatch write operation.
	 *
	 * @param string $operation
	 * @param array  $args
	 * @return array|\WP_Error
	 */
	public static function execute( string $operation, array $args ) {
		if ( ! class_exists( 'Heretek_Rest_Reporting_Gateway' ) || ! function_exists( 'HeretekAnalytics' ) ) {
			return new \WP_Error( 'plugin_inactive', __( 'Heretek Analytics plugin is not active.', 'emcp-tools' ) );
		}

		switch ( $operation ) {
			case 'provision-dimensions':
				$gw = new \Heretek_Rest_Reporting_Gateway();
				return $gw->provision_standard_dimensions();

			case 'flush-cache':
				return self::flush_cache();

			case 'resolve-sitekit-conflict':
				if ( empty( $args['confirm'] ) ) {
					return new \WP_Error( 'confirm_required', __( 'Pass confirm:true to update Google Site Kit snippet settings.', 'emcp-tools' ) );
				}
				return self::resolve_sitekit_conflict();

			default:
				return new \WP_Error(
					'invalid_operation',
					sprintf(
						/* translators: %s: operation name */
						__( 'Unknown analytics-write operation "%s". Call without operation to inspect the catalog.', 'emcp-tools' ),
						$operation
					)
				);
		}
	}

	/**
	 * Flush Heretek Analytics GA4 transients.
	 *
	 * @return array
	 */
	private static function flush_cache(): array {
		global $wpdb;
		$deleted = 0;
		if ( isset( $wpdb->options ) ) {
			$rows = $wpdb->get_col(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_heretek\_ga4\_%' OR option_name LIKE '\_transient\_timeout\_heretek\_ga4\_%'"
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $opt_name ) {
					if ( 0 === strpos( $opt_name, '_transient_heretek_ga4_' ) ) {
						$transient = substr( $opt_name, strlen( '_transient_' ) );
						if ( delete_transient( $transient ) ) {
							++$deleted;
						}
					}
				}
			}
		}
		return array(
			'flushed'            => true,
			'transients_deleted' => $deleted,
		);
	}

	/**
	 * Disable duplicate Google Site Kit gtag.js snippet output.
	 *
	 * @return array
	 */
	private static function resolve_sitekit_conflict(): array {
		$ga4_settings = get_option( 'googlesitekit_analytics-4_settings', array() );
		$changed_ga4  = false;
		if ( is_array( $ga4_settings ) && ! empty( $ga4_settings['useSnippet'] ) ) {
			$ga4_settings['useSnippet'] = false;
			update_option( 'googlesitekit_analytics-4_settings', $ga4_settings );
			$changed_ga4 = true;
		}

		$gtm_settings = get_option( 'googlesitekit_tagmanager_settings', array() );
		$changed_gtm  = false;
		if ( is_array( $gtm_settings ) && ! empty( $gtm_settings['useSnippet'] ) ) {
			$gtm_settings['useSnippet'] = false;
			update_option( 'googlesitekit_tagmanager_settings', $gtm_settings );
			$changed_gtm = true;
		}

		return array(
			'resolved'               => true,
			'ga4_snippet_disabled'   => $changed_ga4,
			'gtm_snippet_disabled'   => $changed_gtm,
			'effective_use_snippet'  => false,
		);
	}
}
