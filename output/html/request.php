<?php
/**
 * Request data output for HTML pages.
 *
 * @package query-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QM_Output_Html_Request extends QM_Output_Html {

	/**
	 * Collector instance.
	 *
	 * @var QM_Collector_Request Collector.
	 */
	protected $collector;

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 50 );
	}

	public function name() {
		return __( 'Request', 'query-monitor' );
	}

	public function output() {

		$data = $this->collector->get_data();

		$db_queries = QM_Collectors::get( 'db_queries' );
		$raw_request = QM_Collectors::get( 'raw_request' );

		$this->before_non_tabular_output();

		if ( $data['is_404'] ) {
			echo '<section>';
			echo '<h3>' . esc_html__( '404 Helper', 'query-monitor' ) . '</h3>';
			echo '<table>';

			if ( $data['missing_rewrite_rules'] ) {
				$message = _x( 'Need flushing', 'Rewrite rules need flushing', 'query-monitor' );
				$class   = 'qm-warn';
			} else {
				$message = __( 'OK', 'query-monitor' );
				$class   = '';
			}

			echo '<tr class="' . esc_attr( $class ) . '">';
			echo '<th>';

			if ( ! empty( $data['missing_rewrite_rules'] ) ) {
				echo '<span class="dashicons dashicons-warning" style="color:#dd3232" aria-hidden="true"></span>';
			}

			esc_html_e( 'Rewrite Rules', 'query-monitor' );
			echo '</th>';
			echo '<td>';
			echo esc_html( $message );
			echo '</td>';
			echo '</tr>';

			if ( ! empty( $data['set_404'] ) ) {
				$filtered_trace = $data['set_404']->get_display_trace();

				foreach ( $filtered_trace as $item ) {
					$stack[] = self::output_filename( $item['display'], $item['calling_file'], $item['calling_line'] );
				}

				echo '<tr>';
				echo '<th>';
				esc_html_e( '404 Triggered by', 'query-monitor' );
				echo '</th>';
				echo '<td>';

				echo '<ol>';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<li>' . implode( '</li><li>', $stack ) . '</li>';
				echo '</ol>';

				echo '</td>';
				echo '</tr>';
			}

			echo '</table>';
			echo '</section>';

			echo '</div>';

			echo '<div class="qm-boxed qm-boxed-wrap">';
		}

		if ( ! empty( $raw_request ) ) {
			$raw_data = $raw_request->get_data();
			echo '<section>';
			echo '<h3>' . esc_html__( 'Request Data', 'query-monitor' ) . '</h3>';
			echo '<table>';

			foreach ( array(
				'ip'     => __( 'Remote IP', 'query-monitor' ),
				'method' => __( 'HTTP method', 'query-monitor' ),
				'url'    => __( 'Requested URL', 'query-monitor' ),
			) as $item => $name ) {
				echo '<tr>';
				echo '<th scope="row">' . esc_html( $name ) . '</td>';
				echo '<td class="qm-ltr qm-wrap">' . esc_html( $raw_data['request'][ $item ] ) . '</td>';
				echo '</tr>';
			}

			echo '</table>';

			echo '</section>';
			echo '</div>';

			echo '<div class="qm-boxed qm-boxed-wrap">';
		}

		if ( ! empty( $data['matching_rewrites'] ) ) {
			echo '<section>';
			echo '<h3>' . esc_html__( 'Matching Rewrite Rules', 'query-monitor' ) . '</h3>';
			echo '<table>';

			foreach ( $data['matching_rewrites'] as $rule => $query ) {
				$query = str_replace( 'index.php?', '', $query );

				echo '<tr>';
				echo '<td class="qm-ltr"><code>' . esc_html( $rule ) . '</code></td>';
				echo '<td class="qm-ltr"><code>';
				echo self::format_url( $query ); // WPCS: XSS ok.
				echo '</code></td>';
				echo '</tr>';
			}

			echo '</table>';
			echo '</section>';

			echo '</div>';

			echo '<div class="qm-boxed qm-boxed-wrap">';
		}

		foreach ( array(
			'request'       => __( 'Request', 'query-monitor' ),
			'matched_query' => __( 'Matched Query', 'query-monitor' ),
			'query_string'  => __( 'Query String', 'query-monitor' ),
		) as $item => $name ) {
			if ( is_admin() && ! isset( $data['request'][ $item ] ) ) {
				continue;
			}

			if ( ! empty( $data['request'][ $item ] ) ) {
				if ( in_array( $item, array( 'request', 'matched_query', 'query_string' ), true ) ) {
					$value = self::format_url( $data['request'][ $item ] );
				} else {
					$value = esc_html( $data['request'][ $item ] );
				}
			} else {
				$value = '<em>' . esc_html__( 'none', 'query-monitor' ) . '</em>';
			}

			echo '<section>';
			echo '<h3>' . esc_html( $name ) . '</h3>';
			echo '<p class="qm-ltr"><code>' . $value . '</code></p>'; // WPCS: XSS ok.
			echo '</section>';
		}

		echo '</div>';

		echo '<div class="qm-boxed qm-boxed-wrap">';

		echo '<section>';
		echo '<h3>';
		esc_html_e( 'Query Vars', 'query-monitor' );
		echo '</h3>';

		if ( $db_queries ) {
			$db_queries_data = $db_queries->get_data();
			if ( ! empty( $db_queries_data['dbs']['$wpdb']->has_main_query ) ) {
				printf(
					'<p><button class="qm-filter-trigger" data-qm-target="db_queries-wpdb" data-qm-filter="caller" data-qm-value="qm-main-query">%s</button></p>',
					esc_html__( 'View Main Query', 'query-monitor' )
				);
			}
		}

		if ( ! empty( $data['qvars'] ) ) {

			echo '<table>';

			foreach ( $data['qvars'] as $var => $value ) {

				echo '<tr>';

				if ( isset( $data['plugin_qvars'][ $var ] ) ) {
					echo '<th scope="row" class="qm-ltr"><span class="qm-current">' . esc_html( $var ) . '</span></td>';
				} else {
					echo '<th scope="row" class="qm-ltr">' . esc_html( $var ) . '</td>';
				}

				if ( is_array( $value ) || is_object( $value ) ) {
					echo '<td class="qm-ltr"><pre>';
					echo esc_html( print_r( $value, true ) );
					echo '</pre></td>';
				} else {
					echo '<td class="qm-ltr qm-wrap">' . esc_html( $value ) . '</td>';
				}

				echo '</tr>';

			}
			echo '</table>';

		} else {

			echo '<p><em>' . esc_html__( 'none', 'query-monitor' ) . '</em></p>';

		}

		echo '</section>';

		echo '<section>';
		echo '<h3>' . esc_html__( 'Response', 'query-monitor' ) . '</h3>';
		echo '<h4>' . esc_html__( 'Queried Object', 'query-monitor' ) . '</h4>';

		if ( ! empty( $data['queried_object'] ) ) {
			printf( // WPCS: XSS ok.
				'<p>%1$s (%2$s)</p>',
				esc_html( $data['queried_object']['title'] ),
				esc_html( get_class( $data['queried_object']['data'] ) )
			);
		} else {
			echo '<p><em>' . esc_html__( 'none', 'query-monitor' ) . '</em></p>';
		}

		echo '<h4>' . esc_html__( 'Current User', 'query-monitor' ) . '</h4>';

		if ( ! empty( $data['user']['data'] ) ) {
			printf( // WPCS: XSS ok.
				'<p>%s</p>',
				esc_html( $data['user']['title'] )
			);
		} else {
			echo '<p><em>' . esc_html__( 'none', 'query-monitor' ) . '</em></p>';
		}

		if ( ! empty( $data['multisite'] ) ) {
			echo '<h4>' . esc_html__( 'Multisite', 'query-monitor' ) . '</h4>';

			foreach ( $data['multisite'] as $var => $value ) {
				printf( // WPCS: XSS ok.
					'<p>%s</p>',
					esc_html( $value['title'] )
				);
			}
		}

		echo '</section>';

		$this->after_non_tabular_output();
	}

	public function admin_menu( array $menu ) {

		$data  = $this->collector->get_data();
		$count = isset( $data['plugin_qvars'] ) ? count( $data['plugin_qvars'] ) : 0;

		$title = ( empty( $count ) )
			? __( 'Request', 'query-monitor' )
			/* translators: %s: Number of additional query variables */
			: __( 'Request (+%s)', 'query-monitor' );

		$menu[ $this->collector->id() ] = $this->menu( array(
			'title' => esc_html( sprintf(
				$title,
				number_format_i18n( $count )
			) ),
		) );
		return $menu;

	}

}

function register_qm_output_html_request( array $output, QM_Collectors $collectors ) {
	$collector = QM_Collectors::get( 'request' );
	if ( $collector ) {
		$output['request'] = new QM_Output_Html_Request( $collector );
	}
	return $output;
}

add_filter( 'qm/outputter/html', 'register_qm_output_html_request', 60, 2 );
