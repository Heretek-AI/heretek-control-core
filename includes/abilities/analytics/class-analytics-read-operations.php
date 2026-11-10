<?php
/**
 * Heretek Analytics Read Operations for MCP.
 *
 * Exposes GA4 status, custom dimension readiness, realtime active users,
 * dashboard KPIs, and taxonomy rankings (authors, characters, chapters, tags).
 *
 * @package EMCP_Tools
 * @since   3.21.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Analytics_Read_Operations
 */
class EMCP_Tools_Analytics_Read_Operations {

	/**
	 * Operation schema catalog.
	 *
	 * @return array<string, array>
	 */
	public static function op_schema(): array {
		return array(
			'get-status' => array(
				'description' => 'Inspect Heretek Analytics GA4 configuration, credential verification, custom dimension provisioning status, and duplicate tag conflicts.',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'verify_api' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Whether to perform a live GA4 metadata API ping to verify service account credentials.',
						),
					),
				),
				'example'     => array( 'verify_api' => true ),
			),
			'get-realtime' => array(
				'description' => 'Fetch GA4 realtime telemetry: active users (last 30m and last 5m), top active pages, countries, and device categories.',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'force_refresh' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Bypass the 60-second realtime transient cache.',
						),
					),
				),
				'example'     => array( 'force_refresh' => true ),
			),
			'get-dashboard' => array(
				'description' => 'Fetch GA4 dashboard KPIs (sessions, users, pageviews, avg duration, engagement rate, bounce rate with period-over-period deltas) plus top pages, sources, countries, authors, and comic characters.',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'start_date'    => array(
							'type'        => 'string',
							'default'     => '-7days',
							'description' => 'Start date in YYYY-MM-DD or relative format such as -7days or -30days.',
						),
						'end_date'      => array(
							'type'        => 'string',
							'default'     => 'today',
							'description' => 'End date in YYYY-MM-DD or relative format such as today or yesterday.',
						),
						'force_refresh' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Bypass cached dashboard telemetry.',
						),
					),
				),
				'example'     => array(
					'start_date'    => '-7days',
					'end_date'      => 'today',
					'force_refresh' => false,
				),
			),
			'get-taxonomy-ranking' => array(
				'description' => 'Fetch GA4 and hybrid-attributed performance rankings for authors, comic characters, comic chapters, or post tags.',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy'      => array(
							'type'        => 'string',
							'enum'        => array( 'author', 'character', 'chapter', 'tag' ),
							'default'     => 'author',
							'description' => 'Taxonomy or entity dimension to rank.',
						),
						'start_date'    => array(
							'type'        => 'string',
							'default'     => '-30days',
							'description' => 'Start date in YYYY-MM-DD or relative format.',
						),
						'end_date'      => array(
							'type'        => 'string',
							'default'     => 'today',
							'description' => 'End date in YYYY-MM-DD or relative format.',
						),
						'limit'         => array(
							'type'        => 'integer',
							'default'     => 25,
							'description' => 'Maximum number of ranked rows to return (1 to 100).',
						),
						'force_refresh' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Bypass cached taxonomy ranking.',
						),
					),
				),
				'example'     => array(
					'taxonomy'   => 'character',
					'start_date' => '-30days',
					'end_date'   => 'today',
					'limit'      => 20,
				),
			),
		);
	}

	/**
	 * Dispatch read operation.
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
			case 'get-status':
				return self::get_status( $args );
			case 'get-realtime':
				return self::get_realtime( $args );
			case 'get-dashboard':
				return self::get_dashboard( $args );
			case 'get-taxonomy-ranking':
				return self::get_taxonomy_ranking( $args );
			default:
				return new \WP_Error(
					'invalid_operation',
					sprintf(
						/* translators: %s: operation name */
						__( 'Unknown analytics-read operation "%s". Call without operation to inspect the catalog.', 'emcp-tools' ),
						$operation
					)
				);
		}
	}

	/**
	 * Get Heretek Analytics status and conflict diagnostics.
	 *
	 * @param array $args
	 * @return array
	 */
	private static function get_status( array $args ): array {
		$gw   = new \Heretek_Rest_Reporting_Gateway();
		$auth = \HeretekAnalytics()->auth;
		$pid  = $auth->get_property_id();
		$v4   = $auth->get_manual_v4_id();
		$sa   = $auth->get_service_account_json();

		$sitekit_ga4 = get_option( 'googlesitekit_analytics-4_settings', array() );
		$sitekit_conflict = is_array( $sitekit_ga4 ) && ! empty( $sitekit_ga4['useSnippet'] );

		$api_verified = null;
		$api_error    = '';
		if ( ! empty( $args['verify_api'] ) ) {
			$check = $gw->verify_credentials();
			if ( is_wp_error( $check ) ) {
				$api_verified = false;
				$api_error    = $check->get_error_message();
			} else {
				$api_verified = true;
			}
		}

		$dimensions = $gw->get_dimensions_status();

		return array(
			'plugin_version'         => defined( 'HERETEK_ANALYTICS_VERSION' ) ? HERETEK_ANALYTICS_VERSION : 'unknown',
			'configured'             => ! empty( $pid ) && ! empty( $sa ),
			'property_id'            => (string) $pid,
			'measurement_id'         => (string) $v4,
			'has_service_account'    => ! empty( $sa ),
			'api_verified'           => $api_verified,
			'api_error'              => $api_error,
			'sitekit_tag_conflict'   => $sitekit_conflict,
			'sitekit_conflict_note'  => $sitekit_conflict
				? 'Google Site Kit has useSnippet enabled, which injects a duplicate gtag.js tag and overwrites window.gtag. Run analytics-write with resolve-sitekit-conflict to disable duplicate Site Kit tagging.'
				: 'No duplicate Site Kit gtag.js snippet detected.',
			'dimensions_status'      => $dimensions,
		);
	}

	/**
	 * Fetch realtime summary.
	 *
	 * @param array $args
	 * @return array
	 */
	private static function get_realtime( array $args ): array {
		$gw = new \Heretek_Rest_Reporting_Gateway();
		return $gw->run_realtime_summary( ! empty( $args['force_refresh'] ) );
	}

	/**
	 * Fetch dashboard KPIs and panels.
	 *
	 * @param array $args
	 * @return array
	 */
	private static function get_dashboard( array $args ): array {
		$gw    = new \Heretek_Rest_Reporting_Gateway();
		$start = isset( $args['start_date'] ) ? sanitize_text_field( (string) $args['start_date'] ) : '-7days';
		$end   = isset( $args['end_date'] ) ? sanitize_text_field( (string) $args['end_date'] ) : 'today';
		$force = ! empty( $args['force_refresh'] );

		return $gw->get_dashboard_telemetry( $start, $end, $force );
	}

	/**
	 * Fetch taxonomy rankings.
	 *
	 * @param array $args
	 * @return array
	 */
	private static function get_taxonomy_ranking( array $args ): array {
		$gw       = new \Heretek_Rest_Reporting_Gateway();
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'author';
		if ( ! in_array( $taxonomy, array( 'author', 'character', 'chapter', 'tag' ), true ) ) {
			$taxonomy = 'author';
		}
		$start = isset( $args['start_date'] ) ? sanitize_text_field( (string) $args['start_date'] ) : '-30days';
		$end   = isset( $args['end_date'] ) ? sanitize_text_field( (string) $args['end_date'] ) : 'today';
		$limit = isset( $args['limit'] ) ? min( max( 1, (int) $args['limit'] ), 100 ) : 25;
		$force = ! empty( $args['force_refresh'] );

		return $gw->get_taxonomy_telemetry( $taxonomy, $start, $end, $limit, $force );
	}
}
