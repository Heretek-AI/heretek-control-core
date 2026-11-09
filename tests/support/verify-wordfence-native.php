<?php
/** Native read spike and benign finding fixture, never a real scan or malware sample. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:18880' !== home_url() || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated local fixture only.' ); }
global $wpdb;
$checks = 0; $check = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; WP_CLI::log( 'PASS ' . $label ); };
$check( '9.0.2' === WORDFENCE_VERSION, 'real Wordfence version' );
$check( ! wfCentral::isConnected() && ! wfConfig::get( 'apiKey', false, false ), 'fixture has no remote licence or Central connection' );
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
$check( wfUtils::isAdmin(), 'native administrator permission' );
$firewall = new wfFirewall();
$check( in_array( $firewall->firewallMode(), array( 'disabled', 'learning-mode', 'enabled' ), true ) && 'basic' === $firewall->protectionMode(), 'native basic firewall status without optimization' );
$scanner = new wfScanner(); $check( ! $scanner->isRunning(), 'native scanner idle without starting a scan' );
$issues = new wfIssues(); $table = $issues->getIssuesTable(); $id = 0; $token = wp_generate_uuid4();
try {
	$result = $issues->addIssue( 'test', wfIssues::SEVERITY_LOW, $token, $token, 'EMCP benign local fixture', 'No malware or remote scan.', array( 'secret' => 'must-not-be-exposed', 'file' => '/private/path' ) );
	$id = (int) $wpdb->insert_id;
	$check( wfIssues::ISSUE_ADDED === $result && $id > 0, 'native benign issue created with its own insert ID' );
	$row = $issues->getIssueByID( $id ); $check( 'test' === $row['type'] && 'new' === $row['status'], 'native issue read-back' );
	$counts = $issues->getIssueCounts(); $check( ( $counts['new'] ?? 0 ) >= 1, 'native finding counts' );
	$rows = $issues->getIssues( 0, 1, 0, 0 ); $check( count( $rows['new'] ) === 1 && ! $rows['ignored'], 'native bounded finding pagination' );
	$check( is_array( wfBlock::allBlocks( true, array(), 0, 1, 'ruleAdded', 'descending' ) ), 'native bounded block read' );
	wfConfig::get( 'wf_scanRunning', false, false ); $check( $scanner->isRunning() === (bool) ( wfConfig::get( 'wf_scanRunning' ) && time() - wfConfig::get( 'wf_scanRunning' ) < WORDFENCE_MAX_SCAN_LOCK_TIME ), 'fresh native scan lock semantics' );
} finally {
	if ( $id && ( $wpdb->get_var( $wpdb->prepare( 'SELECT ignoreP FROM %i WHERE id=%d', $table, $id ) ) === md5( $token ) ) ) { $issues->deleteIssue( $id ); }
}
WP_CLI::success( $checks . ' native Wordfence checks passed.' );
