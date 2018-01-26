<?php
/*
Copyright 2009-2017 John Blackbourn

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

*/

class QM_Dispatcher_Html extends QM_Dispatcher {

	public $id = 'html';
	public $did_footer = false;

	public function __construct( QM_Plugin $qm ) {

		add_action( 'admin_bar_menu',             array( $this, 'action_admin_bar_menu' ), 999 );
		add_action( 'wp_ajax_qm_auth_on',         array( $this, 'ajax_on' ) );
		add_action( 'wp_ajax_qm_auth_off',        array( $this, 'ajax_off' ) );
		add_action( 'wp_ajax_nopriv_qm_auth_off', array( $this, 'ajax_off' ) );

		add_action( 'shutdown',                   array( $this, 'dispatch' ), 0 );

		add_action( 'wp_footer',                  array( $this, 'action_footer' ) );
		add_action( 'admin_footer',               array( $this, 'action_footer' ) );
		add_action( 'login_footer',               array( $this, 'action_footer' ) );
		add_action( 'embed_footer',               array( $this, 'action_footer' ) );
		add_action( 'amp_post_template_footer',   array( $this, 'action_footer' ) );
		add_action( 'gp_footer',                  array( $this, 'action_footer' ) );

		parent::__construct( $qm );

	}

	public function action_footer() {
		$this->did_footer = true;
	}

	/**
	 * Helper function. Should the authentication cookie be secure?
	 *
	 * @return bool Should the authentication cookie be secure?
	 */
	public static function secure_cookie() {
		return ( is_ssl() and ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) ) );
	}

	public function ajax_on() {

		if ( ! current_user_can( 'view_query_monitor' ) or ! check_ajax_referer( 'qm-auth-on', 'nonce', false ) ) {
			wp_send_json_error( __( 'Could not set authentication cookie.', 'query-monitor' ) );
		}

		$expiration = time() + ( 2 * DAY_IN_SECONDS );
		$secure     = self::secure_cookie();
		$cookie     = wp_generate_auth_cookie( get_current_user_id(), $expiration, 'logged_in' );

		setcookie( QM_COOKIE, $cookie, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure, false );

		$text = __( 'Authentication cookie set. You can now view Query Monitor output while logged out or while logged in as a different user.', 'query-monitor' );

		wp_send_json_success( $text );

	}

	public function ajax_off() {

		if ( ! self::user_verified() or ! check_ajax_referer( 'qm-auth-off', 'nonce', false ) ) {
			wp_send_json_error( __( 'Could not clear authentication cookie.', 'query-monitor' ) );
		}

		$expiration = time() - 31536000;

		setcookie( QM_COOKIE, ' ', $expiration, COOKIEPATH, COOKIE_DOMAIN );

		$text = __( 'Authentication cookie cleared.', 'query-monitor' );

		wp_send_json_success( $text );

	}

	public function action_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {

		if ( ! $this->user_can_view() ) {
			return;
		}

		$title = __( 'Query Monitor', 'query-monitor' );

		$wp_admin_bar->add_menu( array(
			'id'    => 'query-monitor',
			'title' => esc_html( $title ),
			'href'  => '#qm',
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'query-monitor',
			'id'     => 'query-monitor-placeholder',
			'title'  => esc_html( $title ),
			'href'   => '#qm',
		) );

	}

	public function init() {

		if ( ! $this->user_can_view() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', 1 );
		}

		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'enqueue_embed_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'send_headers',          'nocache_headers' );

		add_action( 'amp_post_template_head', array( $this, 'enqueue_assets' ) );
		add_action( 'amp_post_template_head', array( $this, 'manually_print_assets' ), 11 );

		add_action( 'gp_head',                array( $this, 'manually_print_assets' ), 11 );

	}

	public function manually_print_assets() {
		wp_print_scripts( array(
			'query-monitor',
		) );
		wp_print_styles( array(
			'query-monitor',
		) );
	}

	public function enqueue_assets() {

		global $wp_locale, $wp_version;

		wp_enqueue_style(
			'query-monitor',
			$this->qm->plugin_url( 'assets/query-monitor.css' ),
			null,
			$this->qm->plugin_ver( 'assets/query-monitor.css' )
		);
		wp_enqueue_script(
			'query-monitor',
			$this->qm->plugin_url( 'assets/query-monitor.js' ),
			array(
				'jquery',
				'wp-util',
			),
			$this->qm->plugin_ver( 'assets/query-monitor.js' ),
			true
		);
		wp_localize_script(
			'query-monitor',
			'qm_number_format',
			$wp_locale->number_format
		);
		wp_localize_script(
			'query-monitor',
			'qm_l10n',
			array(
				'ajax_error'            => __( 'PHP Error in AJAX Response', 'query-monitor' ),
				'infinitescroll_paused' => __( 'Infinite Scroll has been paused by Query Monitor', 'query-monitor' ),
				'ajaxurl'               => admin_url( 'admin-ajax.php' ),
				'auth_nonce' => array(
					'on'         => wp_create_nonce( 'qm-auth-on' ),
					'off'        => wp_create_nonce( 'qm-auth-off' ),
				),
			)
		);

		if ( floatval( $wp_version ) <= 3.7 ) {
			wp_enqueue_style(
				'query-monitor-compat',
				$this->qm->plugin_url( 'assets/compat.css' ),
				null,
				$this->qm->plugin_ver( 'assets/compat.css' )
			);
		}

	}

	public function dispatch() {

		if ( ! $this->should_dispatch() ) {
			return;
		}

		$json = array();

		$this->before_output();

		/* @var QM_Output_Html[] */
		foreach ( $this->get_outputters( 'html' ) as $id => $output ) {
			$timer = new QM_Timer;
			$timer->start();

			$collector   = $output->get_collector();
			$json[ $id ] = $collector->get_data();
			$json[ $id ]['_collector'] = array(
				'id'   => $collector->id(),
				'name' => $collector->name(),
			);

			if ( method_exists( $output, 'template' ) ) {
				?>
				<script type="text/html" id="tmpl-qm-<?php echo esc_attr( $id ); ?>">
					<?php $output->template(); ?>
				</script>
				<div id="qm-out-<?php echo esc_attr( $id ); ?>"></div>
				<?php
			} else {
				$output->output();
			}

			$output->set_timer( $timer->stop() );
		}

		echo '<script>var qm_json = ' . wp_json_encode( $json ) . ';</script>';

		$this->after_output();

	}

	protected function before_output() {

		require_once $this->qm->plugin_path( 'output/Html.php' );

		foreach ( glob( $this->qm->plugin_path( 'output/html/*.php' ) ) as $file ) {
			require_once $file;
		}

		$class = array(
			'qm-no-js',
		);

		if ( did_action( 'wp_head' ) ) {
			$class[] = sprintf( 'qm-theme-%s', get_template() );
			$class[] = sprintf( 'qm-theme-%s', get_stylesheet() );
		}

		if ( ! is_admin_bar_showing() ) {
			$class[] = 'qm-peek';
		}

		echo '<div id="qm" class="' . implode( ' ', array_map( 'esc_attr', $class ) ) . '">';
		echo '<div id="qm-wrapper">';
		echo '<div id="qm-title">';
		echo '<p>' . esc_html__( 'Query Monitor', 'query-monitor' ) . '</p>';
		echo '</div>';

	}

	protected function after_output() {

		echo '<div class="qm qm-half qm-clear" id="qm-authentication">';
		echo '<table cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Authentication', 'query-monitor' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		if ( ! self::user_verified() ) {

			echo '<tr>';
			echo '<td>' . esc_html__( 'You can set an authentication cookie which allows you to view Query Monitor output when you&rsquo;re not logged in.', 'query-monitor' ) . '</td>';
			echo '</tr>';
			echo '<tr>';
			echo '<td><a href="#" class="qm-auth" data-action="on">' . esc_html__( 'Set authentication cookie', 'query-monitor' ) . '</a></td>';
			echo '</tr>';

		} else {

			echo '<tr>';
			echo '<td>' . esc_html__( 'You currently have an authentication cookie which allows you to view Query Monitor output.', 'query-monitor' ) . '</td>';
			echo '</tr>';
			echo '<tr>';
			echo '<td><a href="#" class="qm-auth" data-action="off">' . esc_html__( 'Clear authentication cookie', 'query-monitor' ) . '</a></td>';
			echo '</tr>';

		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		echo '<div class="qm qm-half" id="qm-self">';
		echo '<table cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Self Awareness', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num">' . esc_html__( 'Data', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num" colspan="2">' . esc_html__( 'Processing', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num" colspan="2">' . esc_html__( 'Output', 'query-monitor' ) . '</th>';
		echo '</tr>';
		echo '<tr>';
		echo '<th>&nbsp;</th>';
		echo '<th class="qm-num">' . esc_html_x( 'kB', 'kilobytes', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num">' . esc_html_x( 'ms', 'milliseconds', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num">' . esc_html_x( 'kB', 'kilobytes', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num">' . esc_html_x( 'ms', 'milliseconds', 'query-monitor' ) . '</th>';
		echo '<th class="qm-num">' . esc_html_x( 'kB', 'kilobytes', 'query-monitor' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		$total_time = $total_memory = array(
			'data'       => 0,
			'processing' => 0,
			'output'     => 0,
		);

		foreach ( $this->outputters as $outputter ) {
			$collector       = $outputter->get_collector();
			$collector_timer = $collector->get_timer();
			$output_timer    = $outputter->get_timer();

			$processing_time = $collector_timer->get_time();
			$total_time['processing'] += $processing_time;

			$processing_memory = $collector_timer->get_memory();
			$total_memory['processing'] += $processing_memory;

			$output_time = $output_timer->get_time();
			$total_time['output'] += $output_time;

			$output_memory = $output_timer->get_memory();
			$total_memory['output'] += $output_memory;

			if ( $collector instanceof QM_Collector_Debug_Bar ) {
				$data_kb = '-';
			} else {
				$data_size = self::size( $collector->get_data() );

				if ( $data_size instanceof Exception ) {
					$data_kb = $data_size->getMessage();
				} elseif ( ! is_numeric( $data_size ) ) {
					$data_kb = $data_size;
				} else {
					$total_memory['data'] += $data_size;
					$data_kb = number_format_i18n( $data_size / 1024, 1 );
				}
			}

			echo '<tr>';
			echo '<td>' . esc_html( $collector->name() ) . '</td>';
			echo '<td class="qm-num">' . esc_html( $data_kb ) . '</td>';
			echo '<td class="qm-num">' . esc_html( number_format_i18n( $processing_time * 1000, 1 ) ) . '</td>';
			echo '<td class="qm-num">' . esc_html( number_format_i18n( $processing_memory / 1024, 1 ) ) . '</td>';
			echo '<td class="qm-num">' . esc_html( number_format_i18n( $output_time * 1000, 1 ) ) . '</td>';
			echo '<td class="qm-num">' . esc_html( number_format_i18n( $output_memory / 1024, 1 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';

		echo '<tfoot>';
		echo '<tr>';
		echo '<td style="text-align:right !important">' . esc_html__( 'Total', 'query-monitor' ) . '</td>';
		echo '<td class="qm-num">' . esc_html( number_format_i18n( $total_memory['data'] / 1024, 1 ) ) . '</td>';
		echo '<td class="qm-num">' . esc_html( number_format_i18n( $total_time['processing'] * 1000, 1 ) ) . '</td>';
		echo '<td class="qm-num">' . esc_html( number_format_i18n( $total_memory['processing'] / 1024, 1 ) ) . '</td>';
		echo '<td class="qm-num">' . esc_html( number_format_i18n( $total_time['output'] * 1000, 1 ) ) . '</td>';
		echo '<td class="qm-num">' . esc_html( number_format_i18n( $total_memory['output'] / 1024, 1 ) ) . '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td colspan="6" style="text-align:right !important" class="qm-info"><em>' . esc_html__( 'Note: A negative value for "Processing kB" means data was filtered out during processing', 'query-monitor' ) . '</em></td>';
		echo '</tr>';
		echo '</tfoot>';

		echo '</table>';
		echo '</div>';

		echo '</div>';
		echo '</div>';

		$json = array(
			'menu'        => $this->js_admin_bar_menu(),
			'ajax_errors' => array(), # @TODO move this into the php_errors collector
		);

		echo '<script type="text/javascript">' . "\n\n";
		echo 'var qm = ' . json_encode( $json ) . ';' . "\n\n";
		?>
		if ( ( 'undefined' === typeof QM_i18n ) || ( 'undefined' === typeof jQuery ) || ! jQuery ) {
			document.getElementById( 'qm' ).style.display = 'block';
		} else if ( ! document.getElementById( 'wpadminbar' ) ) {
			document.getElementById( 'qm' ).className += ' qm-peek';
		}
		<?php
		echo '</script>' . "\n\n";

	}

	protected static function size( $var ) {
		$start_memory = memory_get_usage();

		try {
			$var = unserialize( serialize( $var ) ); // @codingStandardsIgnoreLine
		} catch ( Exception $e ) {
			return $e;
		}

		return memory_get_usage() - $start_memory - ( PHP_INT_SIZE * 8 );
	}

	public function js_admin_bar_menu() {

		$class = implode( ' ', apply_filters( 'qm/output/menu_class', array() ) );

		if ( false === strpos( $class, 'qm-' ) ) {
			$class .= ' qm-all-clear';
		}

		$title = implode( '&nbsp;&nbsp;&nbsp;', apply_filters( 'qm/output/title', array() ) );

		if ( empty( $title ) ) {
			$title = esc_html__( 'Query Monitor', 'query-monitor' );
		}

		$admin_bar_menu = array(
			'top' => array(
				'title'     => sprintf( '<span class="ab-icon">QM</span><span class="ab-label">%s</span>', $title ),
				'classname' => $class,
			),
			'sub' => array(),
		);

		foreach ( apply_filters( 'qm/output/menus', array() ) as $menu ) {
			$admin_bar_menu['sub'][ $menu['id'] ] = $menu;
		}

		return $admin_bar_menu;

	}

	public function is_active() {

		if ( ! $this->user_can_view() ) {
			return false;
		}

		if ( ! $this->did_footer ) {
			return false;
		}

		// If this is an async request and not a customizer preview:
		if ( QM_Util::is_async() && ( ! function_exists( 'is_customize_preview' ) || ! is_customize_preview() ) ) {
			return false;
		}

		# Don't process if the minimum required actions haven't fired:
		if ( is_admin() ) {
			if ( ! did_action( 'admin_init' ) ) {
				return false;
			}
		} else {
			if ( ! ( did_action( 'wp' ) || did_action( 'login_init' ) || did_action( 'gp_head' ) ) ) {
				return false;
			}
		}

		# Back-compat filter. Please use `qm/dispatch/html` instead
		if ( ! apply_filters( 'qm/process', true, is_admin_bar_showing() ) ) {
			return false;
		}

		return true;

	}

}

function register_qm_dispatcher_html( array $dispatchers, QM_Plugin $qm ) {
	$dispatchers['html'] = new QM_Dispatcher_Html( $qm );
	return $dispatchers;
}

add_filter( 'qm/dispatchers', 'register_qm_dispatcher_html', 10, 2 );
