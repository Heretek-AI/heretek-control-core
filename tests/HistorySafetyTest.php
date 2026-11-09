<?php
/** Public regressions run in a separate process with failure-capable WP fixtures. */
final class HistorySafetyTest extends \PHPUnit\Framework\TestCase {

	/** @dataProvider scenarios */
	public function test_history_safety( string $scenario ): void {
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/fixtures/history-safety.php', $scenario ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$this->assertIsResource( $process );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertSame( "PASS\n", $output, $error );
	}

	public static function scenarios(): array {
		return array_map( static fn( $name ) => array( $name ), array(
			'legacy-db-insert', 'legacy-db-update', 'legacy-db-force',
			'post-update-failure', 'post-meta-failure', 'option-failure',
			'post-delete-failure', 'untrash-failure', 'missing-blob',
			'meta-conflict', 'untouched-field', 'term-conflict', 'field-conflict',
			'page-settings', 'page-settings-conflict', 'page-settings-failure',
			'meta-absence', 'meta-empty', 'meta-duplicates', 'meta-backslashes',
			'legacy-post-scope', 'suppression', 'legacy-db-availability',
			'user-conflict', 'acf-conflict', 'user-failure', 'acf-failure',
			'meta-handler-failure', 'attachment-missing-backup', 'attachment-id-collision', 'post-id-collision',
			'page-settings-native-noop', 'page-settings-native-drop',
			'journal-completion-failure', 'journal-record-failure',
			'journal-delete-failure', 'journal-clear-failure', 'journal-cap-failure', 'page-settings-journal-failure',
			'generic-attachment-delete', 'legacy-generic-attachment-delete', 'blob-generic-attachment-delete',
			'created-page-undo', 'created-page-conflict', 'created-upload-undo', 'created-upload-conflict',
			'created-upload-delete-failure', 'created-upload-retry', 'created-record-failure', 'created-suppression',
			'page-create-entry', 'page-create-init-failure', 'page-create-journal-failure', 'page-create-outer-suppression',
			'created-draft-clock-undo', 'created-date-conflict',
		) );
	}
}
