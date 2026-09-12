<?php
/**
 * Tests Database Abilities query execution with sql and query argument aliases.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/sql/class-sql-lexer.php';
require_once dirname( __DIR__ ) . '/includes/sql/class-sql-policy.php';
require_once dirname( __DIR__ ) . '/includes/class-database-guard.php';
require_once dirname( __DIR__ ) . '/includes/abilities/class-database-abilities.php';

class DatabaseAbilitiesQueryTest extends \PHPUnit\Framework\TestCase {

	private EMCP_Tools_Database_Abilities $db_abilities;

	protected function setUp(): void {
		parent::setUp();
		emcp_test_reset();
		$this->db_abilities = new EMCP_Tools_Database_Abilities();
	}

	public function test_missing_sql_and_query_fails_cleanly(): void {
		$res = $this->db_abilities->execute_query( array() );
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'missing_sql', $res->get_error_code() );

		$res2 = $this->db_abilities->execute_query( array( 'sql' => '   ', 'query' => '' ) );
		$this->assertInstanceOf( \WP_Error::class, $res2 );
		$this->assertSame( 'missing_sql', $res2->get_error_code() );
	}

	public function test_write_rejected_under_query_alias(): void {
		$res = $this->db_abilities->execute_query( array(
			'query' => "UPDATE wp_posts SET post_title = 'hacked'",
		) );
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'not_read_only', $res->get_error_code() );
	}

	public function test_user_table_protected_read_under_query_alias(): void {
		$res = $this->db_abilities->execute_query( array(
			'query' => "SELECT user_pass FROM wp_users",
		) );
		$this->assertInstanceOf( \WP_Error::class, $res );
		$this->assertSame( 'protected_read', $res->get_error_code() );
	}

	public function test_sql_and_query_aliases_behave_identically(): void {
		$res_sql = $this->db_abilities->execute_query( array(
			'sql' => "DROP TABLE wp_posts",
		) );
		$res_query = $this->db_abilities->execute_query( array(
			'query' => "DROP TABLE wp_posts",
		) );

		$this->assertInstanceOf( \WP_Error::class, $res_sql );
		$this->assertInstanceOf( \WP_Error::class, $res_query );
		$this->assertSame( $res_sql->get_error_code(), $res_query->get_error_code() );
		$this->assertSame( 'not_read_only', $res_sql->get_error_code() );
	}
}
