<?php
/**
 * Builds the ZIP in steps of 200 documents, F6 of the spec.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

/**
 * Each browser poll runs one step. Automatic delivery runs them in a row.
 */
final class Pack {

	const BATCH = 200;

	/**
	 * Registers the actions.
	 */
	public static function hooks() {
		add_action( 'wp_ajax_gestlisto_pack_start', array( self::class, 'ajax_start' ) );
		add_action( 'wp_ajax_gestlisto_pack_step', array( self::class, 'ajax_step' ) );
		add_action( 'admin_post_gestlisto_download', array( self::class, 'download' ) );
	}

	/**
	 * Private folder in uploads, with a random name.
	 *
	 * @return string
	 */
	public static function folder() {
		$name = get_option( 'gestlisto_folder' );
		if ( ! $name ) {
			$name = 'gestor-listo-' . wp_generate_password( 20, false );
			update_option( 'gestlisto_folder', $name, false );
		}
		$dir = wp_upload_dir()['basedir'] . '/' . $name;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// phpcs:disable WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" );
			file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
			// phpcs:enable
		}
		return $dir;
	}

	/**
	 * Documents for a pack.
	 *
	 * @param int   $year  Year.
	 * @param int   $q     Quarter.
	 * @param array $opt   scope: quarter or year, and whatever a filter reads.
	 * @return array Documents and quarter-level checks.
	 */
	public static function documents( $year, $q, array $opt ) {
		$docs  = array();
		$notes = array();
		$from  = 'year' === ( $opt['scope'] ?? '' ) ? 1 : $q;
		for ( $i = $from; $i <= $q; $i++ ) {
			$data  = Quarter::load( $year, $i );
			$docs  = array_merge( $docs, $data['docs'] );
			$notes = array_merge( $notes, $data['notes'] );
		}
		return array( apply_filters( 'gestlisto_pack_documents', $docs, $year, $q, $opt ), $notes );
	}

	/**
	 * Starts a job.
	 *
	 * @param int   $year Year.
	 * @param int   $q    Quarter.
	 * @param array $opt  Options.
	 * @return string Job id.
	 */
	public static function start( $year, $q, array $opt = array() ) {
		$job           = wp_generate_password( 16, false );
		list( $docs, ) = self::documents( $year, $q, $opt );
		update_option(
			'gestlisto_job_' . $job,
			array(
				'year'  => $year,
				'q'     => $q,
				'opt'   => $opt,
				'done'  => 0,
				'total' => count( $docs ),
				'file'  => wp_generate_password( 32, false ) . '.zip',
			),
			false
		);
		return $job;
	}

	/**
	 * Runs one step.
	 *
	 * @param string $job Job id.
	 * @return array done, total, finished, id.
	 */
	public static function step( $job ) {
		$state = get_option( 'gestlisto_job_' . $job );
		if ( ! $state ) {
			return array(
				'finished' => true,
				'id'       => 0,
				'done'     => 0,
				'total'    => 0,
			);
		}
		list( $docs, $notes ) = self::documents( $state['year'], $state['q'], $state['opt'] );
		$path                 = self::folder() . '/' . $state['file'];
		$zip                  = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE );
		$source = Settings::source();
		$part   = array_slice( $docs, $state['done'], self::BATCH );
		foreach ( $part as $i => $doc ) {
			// Each invoice PDF leaves its order in WooCommerce's in-memory cache. Drop it now and then.
			if ( $i && 0 === $i % 10 && wp_cache_supports( 'flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
			if ( ! Document::excluded( $doc ) && 'factura' === $doc['tipo'] && $source->has_pdf() ) {
				$order = wc_get_order( $doc['order_id'] );
				try {
					$pdf = $order ? $source->pdf( $order ) : null;
				} catch ( \Throwable $e ) {
					$pdf = null; // One bad PDF never stops the pack.
				}
				if ( $pdf ) {
					$zip->addFromString( 'facturas/' . sanitize_file_name( $doc['numero_completo'] ) . '.pdf', $pdf );
				}
				// The PDF library leaves loops of objects that PHP only frees when told to.
				unset( $pdf, $order );
				gc_collect_cycles();
				gc_mem_caches();
			}
		}
		$state['done'] += count( $part );
		$finished       = $state['done'] >= count( $docs );
		$id             = 0;
		if ( $finished ) {
			self::add_files( $zip, $docs, $notes, $state );
		}
		$zip->close();
		if ( $finished ) {
			$id = Deliveries::add( $state['year'] . '-' . $state['q'], $docs, $state['file'] );
			delete_option( 'gestlisto_job_' . $job );
		} else {
			update_option( 'gestlisto_job_' . $job, $state, false );
		}
		return array(
			'done'     => $state['done'],
			'total'    => count( $docs ),
			'finished' => $finished,
			'id'       => $id,
		);
	}

	/**
	 * Runs a whole job.
	 *
	 * @param int   $year Year.
	 * @param int   $q    Quarter.
	 * @param array $opt  Options.
	 * @return int History row.
	 */
	public static function build_now( $year, $q, array $opt = array() ) {
		$job = self::start( $year, $q, $opt );
		do {
			$step = self::step( $job );
		} while ( ! $step['finished'] );
		return $step['id'];
	}

	/**
	 * Adds everything that isn't a PDF.
	 *
	 * @param \ZipArchive $zip   The ZIP.
	 * @param array       $docs  Documents.
	 * @param array       $notes Quarter-level checks.
	 * @param array       $state Job.
	 */
	private static function add_files( $zip, array $docs, array $notes, array $state ) {
		$shop  = Settings::shop();
		$tmp   = wp_tempnam( 'gestlisto' );
		$files = array();
		Writers\Book::write( $tmp, $docs, $shop );
		$zip->addFile( $tmp, 'libro/' . Writers\Book::file_name( $state['year'], $shop ) );
		$zip->addFromString( 'ventas.csv', Writers\Csv::ventas( $docs ) );
		$oss = Writers\Csv::oss( $docs );
		if ( $oss ) {
			$zip->addFromString( 'oss-por-pais.csv', $oss );
		}
		$extra   = apply_filters( 'gestlisto_pack_files', $files, $docs, $state );
		$imports = apply_filters( 'gestlisto_pack_imports', array(), $docs, $state );
		foreach ( $extra as $path => $bytes ) {
			$zip->addFromString( $path, $bytes );
		}
		$zip->addFromString(
			'resumen.html',
			Writers\Summary::html(
				$docs,
				$notes,
				$shop,
				array(
					'label'     => Dates::label( $state['year'], $state['q'] ),
					'generated' => Dates::long( Quarter::today(), true ),
				),
				$imports
			)
		);
		// ZipArchive reads addFile sources on close, so the temporary book goes at shutdown.
		add_action(
			'shutdown',
			function () use ( $tmp ) {
				wp_delete_file( $tmp );
			}
		);
	}

	/**
	 * Name for downloads.
	 *
	 * @param string $quarter For example 2026-3.
	 * @return string
	 */
	public static function zip_name( $quarter ) {
		list( $y, $q ) = explode( '-', $quarter );
		return 'gestor-listo-' . $y . '-' . $q . 'T.zip';
	}

	/**
	 * Sends a pack file through PHP.
	 *
	 * @param object $row History row.
	 */
	public static function send( $row ) {
		$path = self::folder() . '/' . basename( $row->file_path );
		if ( '' === $row->file_path || ! is_file( $path ) ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . self::zip_name( $row->quarter ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Permission and nonce for every action.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( $action, '_wpnonce', false ) ) {
			wp_die( -1, 403 );
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- self::guard() checks the nonce first.

	/**
	 * AJAX: start.
	 */
	public static function ajax_start() {
		self::guard( 'gestlisto_pack' );
		$year = absint( $_POST['year'] ?? 0 );
		$q    = absint( $_POST['q'] ?? 0 );
		$opt  = array(
			'scope' => 'year' === sanitize_key( wp_unslash( $_POST['scope'] ?? '' ) ) ? 'year' : 'quarter',
			'delta' => ! empty( $_POST['delta'] ),
		);
		$job  = self::start( $year, min( max( $q, 1 ), 4 ), $opt );
		wp_send_json_success( array( 'job' => $job ) );
	}

	/**
	 * AJAX: one step.
	 */
	public static function ajax_step() {
		self::guard( 'gestlisto_pack' );
		$step = self::step( preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['job'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $step['id'] ) {
			$step['url'] = self::download_url( $step['id'] );
		}
		wp_send_json_success( $step );
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/**
	 * Download link.
	 *
	 * @param int $id History row.
	 * @return string
	 */
	public static function download_url( $id ) {
		return add_query_arg(
			array(
				'action'   => 'gestlisto_download',
				'id'       => (int) $id,
				'_wpnonce' => wp_create_nonce( 'gestlisto_download_' . (int) $id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Logged-in download.
	 */
	public static function download() {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'gestlisto_download_' . $id ) ) {
			wp_die( -1, 403 );
		}
		$row = Deliveries::get( $id );
		if ( ! $row ) {
			wp_die( -1, 404 );
		}
		self::send( $row );
	}
}
