<?php
/**
 * Heretek Analytics MCP Integration.
 *
 * Exposes two dispatcher tools (`analytics-read` and `analytics-write`) for
 * GA4 telemetry, realtime active users, author/character/chapter rankings,
 * custom dimension provisioning, and tag conflict resolution.
 *
 * @package EMCP_Tools
 * @since   3.21.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EMCP_Tools_Analytics_Integration
 */
class EMCP_Tools_Analytics_Integration {

	/**
	 * Check if Heretek Analytics is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'Heretek_Rest_Reporting_Gateway' ) && function_exists( 'HeretekAnalytics' );
	}

	/**
	 * Return registered ability names.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/analytics-read',
			'emcp-tools/analytics-write',
		);
	}

	/**
	 * Register the abilities with the WordPress Abilities API.
	 */
	public function register(): void {
		$read_names  = array_keys( EMCP_Tools_Analytics_Read_Operations::op_schema() );
		$write_names = array_keys( EMCP_Tools_Analytics_Write_Operations::op_schema() );

		emcp_tools_register_ability(
			'emcp-tools/analytics-read',
			array(
				'label'               => __( 'Heretek Analytics Read', 'emcp-tools' ),
				'description'         => sprintf(
					/* translators: %1$s: comma-separated operation names */
					__( 'Read GA4 telemetry, realtime active users, dashboard KPIs, comic character/chapter/author rankings, and custom dimension status from Heretek Analytics. Pass `operation` and `arguments`, or omit `operation` for the full schema catalog. Operations: %1$s.', 'emcp-tools' ),
					implode( ', ', $read_names )
				),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'enum'        => $read_names,
							'description' => __( 'The read operation to run.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'can_read' ),
				'execute_callback'    => array( $this, 'execute_read' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/analytics-write',
			array(
				'label'               => __( 'Heretek Analytics Write', 'emcp-tools' ),
				'description'         => sprintf(
					/* translators: %1$s: comma-separated operation names */
					__( 'Provision GA4 custom dimensions, flush cached Heretek Analytics telemetry, and resolve duplicate gtag.js snippet conflicts. Pass `operation` and `arguments`, or omit `operation` for the full schema catalog. Operations: %1$s.', 'emcp-tools' ),
					implode( ', ', $write_names )
				),
				'category'            => 'emcp-tools',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operation' => array(
							'type'        => 'string',
							'enum'        => $write_names,
							'description' => __( 'The write operation to run.', 'emcp-tools' ),
						),
						'arguments' => array(
							'type'        => 'object',
							'description' => __( 'Arguments passed to the chosen operation.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => array( $this, 'can_write' ),
				'execute_callback'    => array( $this, 'execute_write' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Build discovery-catalog response.
	 *
	 * @param string $tool
	 * @param string $description
	 * @param array  $spec
	 * @return array
	 */
	private function catalog( string $tool, string $description, array $spec ): array {
		$operations = array();

		foreach ( $spec as $name => $entry ) {
			$schema   = (array) ( $entry['schema'] ?? array() );
			$required = (array) ( $schema['required'] ?? array() );
			$props    = (array) ( $schema['properties'] ?? array() );
			$args     = array();

			foreach ( $props as $arg_name => $prop ) {
				$type = isset( $prop['type'] ) ? ( is_array( $prop['type'] ) ? implode( '|', $prop['type'] ) : (string) $prop['type'] ) : 'mixed';
				$hint = str_replace( 'integer', 'int', $type );
				if ( ! empty( $prop['enum'] ) && is_array( $prop['enum'] ) ) {
					$hint .= ' (enum: ' . implode( '|', $prop['enum'] ) . ')';
				}
				if ( in_array( $arg_name, $required, true ) ) {
					$hint .= ' (required)';
				}
				$args[ $arg_name ] = $hint;
			}

			$operations[ $name ] = array(
				'description' => (string) ( $entry['description'] ?? $name ),
				'arguments'   => $args,
				'required'    => $required,
				'schema'      => $schema,
				'example'     => (array) ( $entry['example'] ?? array() ),
			);
		}

		return array(
			'tool'        => $tool,
			'description' => $description,
			'operations'  => $operations,
		);
	}

	/**
	 * Permission check for read operations.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for write operations.
	 *
	 * @return bool
	 */
	public function can_write(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute read operation.
	 *
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public function execute_read( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		if ( '' === $op ) {
			return $this->catalog(
				'emcp-tools/analytics-read',
				__( 'Read GA4 telemetry, realtime active users, dashboard KPIs, and taxonomy rankings from Heretek Analytics.', 'emcp-tools' ),
				EMCP_Tools_Analytics_Read_Operations::op_schema()
			);
		}

		return EMCP_Tools_Analytics_Read_Operations::execute( $op, $in );
	}

	/**
	 * Execute write operation.
	 *
	 * @param array $args
	 * @return array|\WP_Error
	 */
	public function execute_write( array $args ) {
		$op = trim( (string) ( $args['operation'] ?? '' ) );
		$in = (array) ( $args['arguments'] ?? array() );

		if ( '' === $op ) {
			return $this->catalog(
				'emcp-tools/analytics-write',
				__( 'Provision GA4 custom dimensions, flush analytics cache, and resolve duplicate tag conflicts.', 'emcp-tools' ),
				EMCP_Tools_Analytics_Write_Operations::op_schema()
			);
		}

		return EMCP_Tools_Analytics_Write_Operations::execute( $op, $in );
	}
}
