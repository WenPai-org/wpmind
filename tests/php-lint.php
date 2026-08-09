<?php
/**
 * Dependency-free PHP syntax gate.
 *
 * @package WPMind
 */

declare(strict_types=1);

$root     = dirname( __DIR__ );
$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ): bool {
			if ( $file->isDir() && in_array( $file->getFilename(), [ '.git', 'node_modules', 'vendor' ], true ) ) {
				return false;
			}
			return true;
		}
	)
);

$count = 0;
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}

	$output = [];
	$status = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ), $output, $status );
	if ( 0 !== $status ) {
		fwrite( STDERR, implode( PHP_EOL, $output ) . PHP_EOL );
		exit( $status );
	}
	++$count;
}

echo "PHP syntax OK ({$count} files)" . PHP_EOL;
