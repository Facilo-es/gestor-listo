<?php
/**
 * The plugin's screens.
 *
 * @package GestorListo
 */

namespace GestorListo;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce › Gestor Listo.
 */
final class Admin {

	const SLUG = 'gestor-listo';

	/**
	 * Registers everything.
	 */
	public static function hooks() {
		add_action( 'admin_menu', array( self::class, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_gestlisto_save', array( self::class, 'save' ) );
		add_action( 'admin_post_gestlisto_mark', array( self::class, 'mark' ) );
		add_action( 'admin_init', array( self::class, 'privacy' ) );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', 'Gestor Listo', 'Gestor Listo', 'manage_woocommerce', self::SLUG, array( self::class, 'page' ) );
	}

	/**
	 * Fonts, CSS and JS on our screen only.
	 *
	 * @param string $hook Screen.
	 */
	public static function assets( $hook ) {
		if ( 'woocommerce_page_' . self::SLUG === $hook ) {
			wp_enqueue_style( 'gestlisto', plugins_url( 'assets/admin.css', GESTLISTO_FILE ), array(), GESTLISTO_VERSION );
			wp_enqueue_script( 'gestlisto', plugins_url( 'assets/admin.js', GESTLISTO_FILE ), array(), GESTLISTO_VERSION, true );
			wp_localize_script(
				'gestlisto',
				'gestlistoText',
				array(
					/* translators: 1: documents done so far, 2: documents in total. */
					'preparing' => __( 'Preparing %1$s of %2$s documents…', 'gestor-listo-for-woocommerce' ),
					'ready'     => __( 'Pack ready.', 'gestor-listo-for-woocommerce' ),
					'failed'    => __( 'We couldn\'t prepare the pack. Please try again.', 'gestor-listo-for-woocommerce' ),
					'copied'    => __( 'Copied', 'gestor-listo-for-woocommerce' ),
				)
			);
		}
	}

	/**
	 * Suggested text for the privacy policy.
	 */
	public static function privacy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'Gestor Listo', '<p>' . esc_html__( 'We share with our accountants the billing data they need to keep the books: name, tax ID (NIF), country and the amounts of each invoice.', 'gestor-listo-for-woocommerce' ) . '</p>' );
		}
	}

	/**
	 * Link to a screen.
	 *
	 * @param array $args Query.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array( 'page' => self::SLUG ) + $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Money with the euro sign.
	 *
	 * @param float $x Amount.
	 * @return string
	 */
	public static function eur( $x ) {
		return Format::money( $x ) . ' €';
	}

	/**
	 * The screen.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s   = Settings::all();
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'trimestre' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $s['setup_done'] && 'ajustes' !== $tab ) {
			Setup::render();
			return;
		}
		$titles = array(
			'trimestre' => __( 'Your quarter', 'gestor-listo-for-woocommerce' ),
			'entregas'  => __( 'Deliveries', 'gestor-listo-for-woocommerce' ),
			'ajustes'   => __( 'Settings', 'gestor-listo-for-woocommerce' ),
			'pro'       => 'Pro',
		);
		if ( ! isset( $titles[ $tab ] ) ) {
			$tab = 'trimestre';
		}
		$estado = 'orden';
		$data   = array();
		$year   = 0;
		$q      = 0;
		if ( 'trimestre' === $tab ) {
			list( $year, $q ) = Quarter::last_finished();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['y'], $_GET['q'] ) ) {
				$year = absint( $_GET['y'] );
				$q    = min( 4, max( 1, absint( $_GET['q'] ) ) );
			}
			// phpcs:enable
			$data   = Quarter::load( $year, $q );
			$estado = Quarter::issues( $data ) ? 'cosas' : 'orden';
		}
		echo '<div class="gl" data-estado="' . esc_attr( $estado ) . '"><div class="alto"><div class="top"><h1>' . esc_html( $titles[ $tab ] ) . '</h1><div class="tabs">';
		foreach ( $titles as $key => $title ) {
			echo '<a class="' . ( $key === $tab ? 'on' : '' ) . '" href="' . esc_url( self::url( array( 'tab' => $key ) ) ) . '">' . esc_html( $title ) . '</a>';
		}
		echo '</div></div>';
		if ( 'trimestre' === $tab ) {
			self::years( $year, $q );
			self::tiles( $year, $q, $estado, $data );
		}
		echo '</div><div class="pagina">';
		if ( 'trimestre' === $tab ) {
			self::quarter( $year, $q, $data );
		} elseif ( 'entregas' === $tab ) {
			self::deliveries();
		} elseif ( 'ajustes' === $tab ) {
			self::settings( $s );
		} else {
			self::pro();
		}
		echo '</div></div>';
	}

	/**
	 * Earlier and later years, within the last 8 quarters.
	 *
	 * @param int $year Year.
	 * @param int $q    Quarter.
	 */
	private static function years( $year, $q ) {
		$now = Dates::quarter_of( Quarter::today() )[0];
		echo '<p class="year gl-year-gap">';
		if ( $year > $now - 2 ) {
			echo '<a aria-label="' . esc_attr__( 'Previous year', 'gestor-listo-for-woocommerce' ) . '" href="' . esc_url(
				self::url(
					array(
						'y' => $year - 1,
						'q' => $q,
					)
				)
			) . '">‹</a>';
		}
		echo esc_html( (string) $year );
		if ( $year < $now ) {
			echo '<a aria-label="' . esc_attr__( 'Next year', 'gestor-listo-for-woocommerce' ) . '" href="' . esc_url(
				self::url(
					array(
						'y' => $year + 1,
						'q' => $q,
					)
				)
			) . '">›</a>';
		}
		echo '</p>';
	}

	/**
	 * The year's four tiles.
	 *
	 * @param int    $year   Year.
	 * @param int    $q      Selected quarter.
	 * @param string $estado State.
	 * @param array  $data   Quarter data.
	 */
	private static function tiles( $year, $q, $estado, array $data ) {
		list( $now_y, $now_q ) = Dates::quarter_of( Quarter::today() );
		echo '<div class="anios c' . (int) $q . '">';
		for ( $i = 1; $i <= 4; $i++ ) {
			$future = $year > $now_y || ( $year === $now_y && $i > $now_q );
			$open   = $year === $now_y && $i === $now_q;
			$row    = $future ? null : Deliveries::delivered( $year . '-' . $i )[0];
			$class  = 't' . ( $row ? ' ok' : '' ) . ( $i === $q ? ' cur' : '' ) . ( $future || ( $open && $i !== $q ) ? ' fut' : '' );
			$sub    = '';
			if ( $open ) {
				$sub = __( 'In progress', 'gestor-listo-for-woocommerce' );
			} elseif ( $row ) {
				/* translators: %s: date, such as April 20. */
				$sub = sprintf( __( 'Delivered on %s', 'gestor-listo-for-woocommerce' ), Dates::shown( substr( $row->delivered, 0, 10 ) ) );
			} elseif ( ! $future ) {
				$sub = __( 'Not delivered', 'gestor-listo-for-woocommerce' );
			}
			if ( $i === $q && ! $row && ! $open ) {
				$n = count( Quarter::issues( $data ) );
				/* translators: %d: number of things to check. */
				$sub = sprintf( _n( '%d thing to check', '%d things to check', $n, 'gestor-listo-for-woocommerce' ), $n );
				if ( 'orden' === $estado ) {
					$sub = __( 'Ready to send', 'gestor-listo-for-woocommerce' );
				}
			}
			$label = self::quarter_name( $year, $i, $i === $q );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url(
				self::url(
					array(
						'y' => $year,
						'q' => $i,
					)
				)
			) . '"><b>' . esc_html( $label ) . '</b><span>' . esc_html( $sub ) . '</span></a>';
		}
		echo '</div>';
	}

	/**
	 * Short quarter name, such as Q3 2026.
	 *
	 * @param int  $year      Year.
	 * @param int  $q         Quarter.
	 * @param bool $with_year Add the year.
	 * @return string
	 */
	private static function quarter_name( $year, $q, $with_year = true ) {
		if ( ! $with_year ) {
			/* translators: %d: quarter number, 1 to 4. */
			return sprintf( __( 'Q%d', 'gestor-listo-for-woocommerce' ), $q );
		}
		/* translators: 1: quarter number, 1 to 4, 2: year. */
		return sprintf( __( 'Q%1$d %2$d', 'gestor-listo-for-woocommerce' ), $q, $year );
	}

	/**
	 * Name of a row in the VAT table.
	 *
	 * @param string $key Row key from Totals.
	 * @param array  $row Row.
	 * @return string
	 */
	private static function rate_name( $key, array $row ) {
		if ( 0 === strpos( $key, 'iva:' ) ) {
			/* translators: %s: VAT rate, such as 21. */
			return sprintf( __( 'VAT %s%%', 'gestor-listo-for-woocommerce' ), Format::pct( (float) substr( $key, 4 ) ) );
		}
		$names = array(
			'intra'    => __( 'Sales to EU businesses', 'gestor-listo-for-woocommerce' ),
			'export'   => __( 'Exports', 'gestor-listo-for-woocommerce' ),
			'canarias' => __( 'Canary Islands, Ceuta and Melilla', 'gestor-listo-for-woocommerce' ),
			'oss'      => __( 'OSS sales to other EU countries', 'gestor-listo-for-woocommerce' ),
		);
		return $names[ $key ] ?? $row['label'];
	}

	/**
	 * Tu trimestre.
	 *
	 * @param int   $year Year.
	 * @param int   $q    Quarter.
	 * @param array $data Quarter data.
	 */
	private static function quarter( $year, $q, array $data ) {
		$t      = $data['totals'];
		$issues = Quarter::issues( $data );
		$n      = count( $issues );
		$source = Settings::source();
		$change = Deliveries::changes( $year . '-' . $q, $data['docs'] );
		$open   = Dates::quarter_of( Quarter::today() ) === array( $year, $q );

		echo '<section class="panel"><div class="p-in"><div>';
		if ( $n ) {
			/* translators: %d: number of things to check. */
			echo '<h2>' . esc_html( sprintf( _n( '%d thing to check before you send it', '%d things to check before you send it', $n, 'gestor-listo-for-woocommerce' ), $n ) ) . '</h2>';
		} else {
			echo '<h2>' . esc_html__( 'All in order.', 'gestor-listo-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Your quarter is ready for your gestor.', 'gestor-listo-for-woocommerce' ) . '</p>';
		}
		echo '<div data-gl-pack data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'gestlisto_pack' ) ) . '" data-year="' . (int) $year . '" data-q="' . (int) $q . '">';
		echo '<button class="btn" data-gl-go>' . esc_html__( 'Prepare the pack for my gestor', 'gestor-listo-for-woocommerce' ) . '</button>';
		echo '<div data-gl-work hidden><div class="barra"><i></i></div><p class="gl-work-text" data-gl-text>' . esc_html__( 'Preparing…', 'gestor-listo-for-woocommerce' ) . '</p>';
		echo '<div data-gl-ready hidden><a class="btn s" data-gl-zip href="#">' . esc_html__( 'Download ZIP', 'gestor-listo-for-woocommerce' ) . '</a> ';
		echo '<form class="gl-mark gl-inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gestlisto_mark' );
		echo '<input type="hidden" name="action" value="gestlisto_mark"><input type="hidden" name="id" value="0"><input type="hidden" name="y" value="' . (int) $year . '"><input type="hidden" name="q" value="' . (int) $q . '">';
		echo '<input class="gl-recipient" type="text" name="recipient" placeholder="' . esc_attr__( 'Recipient (optional)', 'gestor-listo-for-woocommerce' ) . '"><button class="btn s alt gl-auto">' . esc_html__( 'Mark as delivered', 'gestor-listo-for-woocommerce' ) . '</button></form></div></div>';
		echo '<p class="opts"><label><input type="radio" name="scope" value="quarter" checked> ' . esc_html__( 'Only this quarter', 'gestor-listo-for-woocommerce' ) . '</label><label><input type="radio" name="scope" value="year"> ' . esc_html__( 'The whole year up to this quarter', 'gestor-listo-for-woocommerce' ) . '</label></p>';
		do_action( 'gestlisto_pack_options', $year, $q, $change );
		echo '</div>';
		/* translators: %s: date, such as October 20. */
		echo '<p class="plazo">' . esc_html( sprintf( __( 'The general deadline for this quarter is %s.', 'gestor-listo-for-woocommerce' ), Dates::shown( Dates::deadline( $year, $q ) ) ) ) . '</p>';
		echo '</div><div class="cards">';
		self::card( __( 'Invoices', 'gestor-listo-for-woocommerce' ), (string) $t['facturas'] );
		self::card( __( 'Tax base', 'gestor-listo-for-woocommerce' ), self::eur( $t['base'] ) );
		/* translators: %s: amount in euros. */
		$plus = $t['recargo'] ? sprintf( __( 'plus %s of equivalence surcharge', 'gestor-listo-for-woocommerce' ), self::eur( $t['recargo'] ) ) : '';
		self::card( __( 'VAT', 'gestor-listo-for-woocommerce' ), self::eur( $t['iva'] ), $plus );
		self::card( __( 'Total', 'gestor-listo-for-woocommerce' ), self::eur( $t['total'] ), '', ' total' );
		echo '</div></div></section>';

		if ( $change['row'] && ( $change['nuevas'] || $change['cambiadas'] ) ) {
			$a = count( $change['nuevas'] );
			$b = count( $change['cambiadas'] );
			/* translators: %d: number of new invoices. */
			$new = sprintf( _n( '%d new invoice', '%d new invoices', $a, 'gestor-listo-for-woocommerce' ), $a );
			/* translators: %d: number of changed invoices. */
			$changed = sprintf( _n( '%d invoice changed', '%d invoices changed', $b, 'gestor-listo-for-woocommerce' ), $b );
			/* translators: 1: date the quarter was sent, 2: new invoices, such as "2 new invoices", 3: changed invoices, such as "1 invoice changed". */
			echo '<div class="hueco"><p class="aviso gl-flush">' . esc_html( sprintf( __( 'There have been changes since you sent this quarter on %1$s: %2$s and %3$s.', 'gestor-listo-for-woocommerce' ), Dates::shown( substr( $change['row']->delivered, 0, 10 ) ), $new, $changed ) ) . '</p></div>';
		}
		if ( '' === Settings::all()['epigrafe'] ) {
			/* translators: %s: link to the Settings tab. */
			echo '<div class="hueco"><p class="aviso gl-flush">' . sprintf( esc_html__( 'Your IAE activity code (epígrafe IAE) is missing. Without it, the Tax Agency won\'t accept the invoice book. %s.', 'gestor-listo-for-woocommerce' ), '<a href="' . esc_url( self::url( array( 'tab' => 'ajustes' ) ) ) . '">' . esc_html__( 'Add it in Settings', 'gestor-listo-for-woocommerce' ) . '</a>' ) . '</p></div>';
		}
		if ( ! $source->numbered() ) {
			echo '<div class="hueco"><p class="aviso gl-flush">' . esc_html( self::no_invoice_plugin() ) . '</p></div>';
		} elseif ( ! $source->has_pdf() ) {
			/* translators: %s: name of the invoice plugin. */
			echo '<div class="hueco"><p class="aviso gl-flush">' . esc_html( sprintf( __( '%s doesn\'t let us read the PDFs without creating them, so the pack will go without them. Everything else is complete.', 'gestor-listo-for-woocommerce' ), $source->label() ) ) . '</p></div>';
		}
		if ( $open ) {
			/* translators: %s: amount in euros. */
			echo '<div class="hueco"><p class="ok-note gl-flush">' . esc_html( sprintf( __( 'You have collected %s of VAT and equivalence surcharge this quarter. It isn\'t yours: keep it aside for the Tax Agency, minus the VAT on your expenses.', 'gestor-listo-for-woocommerce' ), self::eur( $t['iva'] + $t['recargo'] ) ) ) . '</p></div>';
		}

		echo '<div class="bajo' . ( $n ? ' dos' : '' ) . '"><section class="bloque"><h3>' . esc_html__( 'VAT by rate', 'gestor-listo-for-woocommerce' ) . '</h3><table><thead><tr><th scope="col">' . esc_html__( 'Rate', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Base', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'VAT', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Surcharge', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Total', 'gestor-listo-for-woocommerce' ) . '</th></tr></thead><tbody>';
		foreach ( $t['rows'] as $key => $row ) {
			echo '<tr><th scope="row">' . esc_html( self::rate_name( (string) $key, $row ) ) . '</th><td>' . esc_html( Format::money( $row['base'] ) ) . '</td><td>' . esc_html( 0.0 === $row['iva'] ? '—' : Format::money( $row['iva'] ) ) . '</td><td>' . esc_html( 0.0 === $row['re'] ? '—' : Format::money( $row['re'] ) ) . '</td><td>' . esc_html( Format::money( $row['total'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
		if ( $n ) {
			echo '<section class="bloque"><h3>' . esc_html__( 'Before you send it', 'gestor-listo-for-woocommerce' ) . '</h3>';
			foreach ( $issues as $issue ) {
				echo '<div class="cosa' . ( $issue['error'] ? ' err' : '' ) . '"><strong>' . ( $issue['error'] ? esc_html__( 'Error · left out', 'gestor-listo-for-woocommerce' ) : esc_html__( 'Warning', 'gestor-listo-for-woocommerce' ) ) . '</strong><br>' . esc_html( $issue['text'] );
				if ( $issue['order_id'] ) {
					$order = wc_get_order( $issue['order_id'] );
					if ( $order ) {
						echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'View order', 'gestor-listo-for-woocommerce' ) . '</a>';
					}
				}
				echo '</div>';
			}
			echo '</section>';
		}
		echo '</div>';
		if ( $t['oss'] ) {
			echo '<div class="bajo"><section class="bloque"><h3>' . esc_html__( 'OSS sales by country', 'gestor-listo-for-woocommerce' ) . '</h3><table><thead><tr><th scope="col">' . esc_html__( 'Country and rate', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Base', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'VAT', 'gestor-listo-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $t['oss'] as $row ) {
				echo '<tr><th scope="row">' . esc_html( $row['pais'] . ' ' . Format::pct( $row['tipo'] ) . ' %' ) . '</th><td>' . esc_html( Format::money( $row['base'] ) ) . '</td><td>' . esc_html( Format::money( $row['iva'] ) ) . '</td></tr>';
			}
			echo '</tbody></table></section></div>';
		}
	}

	/**
	 * One card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $small Line under it.
	 * @param string $extra Extra class.
	 */
	private static function card( $label, $value, $small = '', $extra = '' ) {
		echo '<div class="card' . esc_attr( $extra ) . '"><span>' . esc_html( $label ) . '</span><b>' . esc_html( $value ) . '</b>';
		if ( $small ) {
			echo '<small>' . esc_html( $small ) . '</small>';
		}
		echo '</div>';
	}

	/**
	 * The notice shown when no invoice plugin was found.
	 *
	 * @return string
	 */
	public static function no_invoice_plugin() {
		return __( 'We haven\'t found any invoice plugin. We\'ll use the order numbers, but an order isn\'t an invoice. Check with your gestor.', 'gestor-listo-for-woocommerce' );
	}

	/**
	 * Entregas.
	 */
	private static function deliveries() {
		$rows = Deliveries::all();
		echo '<div class="bajo"><section class="bloque"><h3>' . esc_html__( 'Deliveries', 'gestor-listo-for-woocommerce' ) . '</h3>';
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Every pack you prepare will show up here.', 'gestor-listo-for-woocommerce' ) . '</p>';
		} else {
			echo '<table class="izq pilas"><thead><tr><th scope="col">' . esc_html__( 'Date', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Quarter', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Documents', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Total', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'Recipient', 'gestor-listo-for-woocommerce' ) . '</th><th scope="col">' . esc_html__( 'File', 'gestor-listo-for-woocommerce' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				list( $y, $q ) = explode( '-', $row->quarter );
				echo '<tr><td data-label="' . esc_attr__( 'Date', 'gestor-listo-for-woocommerce' ) . '">' . esc_html( Dates::dmy( substr( $row->delivered ? $row->delivered : $row->created, 0, 10 ) ) ) . '</td><td data-label="' . esc_attr__( 'Quarter', 'gestor-listo-for-woocommerce' ) . '">' . esc_html( self::quarter_name( (int) $y, (int) $q ) ) . '</td><td data-label="' . esc_attr__( 'Documents', 'gestor-listo-for-woocommerce' ) . '">' . (int) $row->doc_count . '</td><td data-label="' . esc_attr__( 'Total', 'gestor-listo-for-woocommerce' ) . '">' . esc_html( self::eur( $row->total ) ) . '</td><td data-label="' . esc_attr__( 'Recipient', 'gestor-listo-for-woocommerce' ) . '">' . esc_html( $row->recipient ? $row->recipient : ( $row->delivered ? __( 'Marked by hand', 'gestor-listo-for-woocommerce' ) : '—' ) ) . '</td><td class="gl-wrap" data-label="' . esc_attr__( 'File', 'gestor-listo-for-woocommerce' ) . '">';
				if ( $row->file_path ) {
					echo '<a href="' . esc_url( Pack::download_url( $row->id ) ) . '">' . esc_html__( 'Download ZIP', 'gestor-listo-for-woocommerce' ) . '</a>';
				} else {
					echo esc_html__( 'File deleted for security after 90 days. You can prepare it again.', 'gestor-listo-for-woocommerce' );
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section></div>';
	}

	/**
	 * Ajustes.
	 *
	 * @param array $s Settings.
	 */
	private static function settings( array $s ) {
		echo '<form class="f pasos gl-wide" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gestlisto_save' );
		echo '<input type="hidden" name="action" value="gestlisto_save"><input type="hidden" name="back" value="ajustes">';
		echo '<section class="bloque"><h3>' . esc_html__( 'Your business', 'gestor-listo-for-woocommerce' ) . '</h3>';
		Setup::company_fields( $s );
		echo '</section><section class="bloque"><h3>' . esc_html__( 'Your invoices', 'gestor-listo-for-woocommerce' ) . '</h3>';
		Setup::invoice_fields( $s );
		echo '</section><section class="bloque"><h3>' . esc_html__( 'Your taxes', 'gestor-listo-for-woocommerce' ) . '</h3>';
		Setup::tax_fields( $s );
		echo '</section><section class="bloque"><h3>' . esc_html__( 'Invoice book details', 'gestor-listo-for-woocommerce' ) . '</h3>';
		$act = $s['actividad_codigo'] . $s['actividad_tipo'];
		echo '<label>' . esc_html__( 'Type of activity', 'gestor-listo-for-woocommerce' ) . ' <select name="actividad">';
		foreach ( Settings::activities() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $act, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>' . esc_html__( 'IAE activity code (epígrafe IAE)', 'gestor-listo-for-woocommerce' ) . ' <input type="text" name="epigrafe" value="' . esc_attr( $s['epigrafe'] ) . '"></label><p class="help">' . esc_html__( 'Four digits, such as 6599. The Tax Agency asks for it on every line of the invoice book. If you don\'t know it, ask your gestor.', 'gestor-listo-for-woocommerce' ) . '</p>';
		echo '<label class="tick"><input type="checkbox" name="retail" value="1"' . checked( $s['retail'], true, false ) . '> ' . esc_html__( 'I sell retail to end consumers', 'gestor-listo-for-woocommerce' ) . '</label>';
		echo '<label>' . esc_html__( 'Credit note type', 'gestor-listo-for-woocommerce' ) . ' <select name="tipo_rectificativa"><option' . selected( $s['tipo_rectificativa'], 'R1', false ) . '>R1</option><option' . selected( $s['tipo_rectificativa'], 'R4', false ) . '>R4</option></select></label><p></p>';
		echo '</section><section class="bloque"><h3>' . esc_html__( 'Privacy', 'gestor-listo-for-woocommerce' ) . '</h3><label class="tick"><input type="checkbox" name="delete_on_uninstall" value="1"' . checked( $s['delete_on_uninstall'], true, false ) . '> ' . esc_html__( 'Delete all Gestor Listo data when uninstalling', 'gestor-listo-for-woocommerce' ) . '</label><p></p></section>';
		echo '<p><button class="btn s">' . esc_html__( 'Save', 'gestor-listo-for-woocommerce' ) . '</button></p></form>';
	}

	/**
	 * Pro tab.
	 */
	private static function pro() {
		if ( has_action( 'gestlisto_pro_tab' ) ) {
			do_action( 'gestlisto_pro_tab' );
			return;
		}
		echo '<div class="bajo pro"><section class="bloque gl-roomy"><h2>' . esc_html__( 'Your gestor imports the quarter and stops typing in invoices', 'gestor-listo-for-woocommerce' ) . '</h2>';
		echo '<p class="gl-lead">' . esc_html__( 'With Pro, your gestor gets a file their accounting software loads directly, and the pack reaches them on its own every quarter.', 'gestor-listo-for-woocommerce' ) . '</p><ul class="lista">';
		echo '<li>' . esc_html__( 'Files for a3, ContaPlus, Sage 50 and ContaSol', 'gestor-listo-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Your ledger accounts, the way your gestor uses them', 'gestor-listo-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Automatic sending every quarter', 'gestor-listo-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'Only what\'s new, if something changes later', 'gestor-listo-for-woocommerce' ) . '</li>';
		echo '<li>' . esc_html__( 'The figures for your modelos 303, 349, 369, 347 and 390, already added up', 'gestor-listo-for-woocommerce' ) . '</li></ul>';
		echo '<p><b>' . esc_html__( 'Try it for 14 days with no card. If your gestor can\'t import it, you don\'t pay.', 'gestor-listo-for-woocommerce' ) . '</b></p>';
		echo '<p class="precios">' . esc_html__( '€59 a year for one shop · €149 a year for five shops. VAT not included.', 'gestor-listo-for-woocommerce' ) . '</p>';
		echo '<p><a class="btn" href="' . esc_url( apply_filters( 'gestlisto_checkout_url', 'https://checkout.freemius.com/plugin/44830/plan/79005/?trial=free' ) ) . '">' . esc_html__( 'Try 14 days free', 'gestor-listo-for-woocommerce' ) . '</a></p>';
		echo '<p class="nota">' . esc_html__( 'a3, ContaPlus, Sage 50 and ContaSol are trademarks of their owners. Gestor Listo is not affiliated with them.', 'gestor-listo-for-woocommerce' ) . '</p></section></div>';
	}

	/**
	 * Saves settings or setup.
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'gestlisto_save' );
		$back = sanitize_key( wp_unslash( $_POST['back'] ?? 'trimestre' ) );
		$nif  = Nif::clean( sanitize_text_field( wp_unslash( $_POST['nif'] ?? '' ) ) );
		$args = array( 'tab' => $back );
		if ( ! Nif::valid( $nif ) ) {
			$args['nif'] = 'mal';
			wp_safe_redirect( self::url( $args ) );
			exit;
		}
		$map = array();
		foreach ( (array) wp_unslash( $_POST['tax'] ?? array() ) as $id => $kind ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$map[ absint( $id ) ] = in_array( $kind, array( 'iva', 'recargo', 'oss', 'exento' ), true ) ? $kind : '';
		}
		$values = array(
			'nombre'      => sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) ),
			'nif'         => $nif,
			'tipo'        => 'autonomo' === sanitize_key( wp_unslash( $_POST['tipo'] ?? '' ) ) ? 'autonomo' : 'sociedad',
			'source'      => sanitize_key( wp_unslash( $_POST['source'] ?? '' ) ),
			'nif_meta'    => sanitize_text_field( wp_unslash( $_POST['nif_meta'] ?? '' ) ),
			'number_meta' => sanitize_text_field( wp_unslash( $_POST['number_meta'] ?? '' ) ),
			'date_meta'   => sanitize_text_field( wp_unslash( $_POST['date_meta'] ?? '' ) ),
			'tax_map'     => $map,
			'setup_done'  => true,
		);
		if ( 'ajustes' === $back ) {
			$act     = sanitize_text_field( wp_unslash( $_POST['actividad'] ?? '' ) );
			$act     = isset( Settings::ACTIVIDADES[ $act ] ) ? $act : 'A03';
			$values += array(
				'actividad_codigo'    => substr( $act, 0, 1 ),
				'actividad_tipo'      => substr( $act, 1, 2 ),
				'epigrafe'            => substr( preg_replace( '/[^0-9A-Za-z]/', '', sanitize_text_field( wp_unslash( $_POST['epigrafe'] ?? '' ) ) ), 0, 4 ),
				'retail'              => ! empty( $_POST['retail'] ),
				'tipo_rectificativa'  => 'R4' === sanitize_text_field( wp_unslash( $_POST['tipo_rectificativa'] ?? '' ) ) ? 'R4' : 'R1',
				'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ),
			);
		}
		Settings::save( $values );
		wp_safe_redirect( self::url( array( 'tab' => 'ajustes' === $back ? 'ajustes' : 'trimestre' ) ) );
		exit;
	}

	/**
	 * Marks a pack as delivered.
	 */
	public static function mark() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}
		check_admin_referer( 'gestlisto_mark' );
		Deliveries::mark( absint( $_POST['id'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['recipient'] ?? '' ) ) );
		wp_safe_redirect(
			self::url(
				array(
					'y' => absint( $_POST['y'] ?? 0 ),
					'q' => absint( $_POST['q'] ?? 0 ),
				)
			)
		);
		exit;
	}
}
