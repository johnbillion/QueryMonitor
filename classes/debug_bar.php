<?php
/**
 * Mock 'Debug Bar' plugin class.
 *
 * @package query-monitor
 */

class Debug_Bar {
	public $panels = array();

	public function __construct() {
		add_action( 'wp_head', array( $this, 'ensure_ajaxurl' ), 1 );

		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'enqueue_embed_scripts', array( $this, 'enqueue' ) );
		$this->init_panels();
	}

	public function enqueue() {
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return;
		}

		wp_register_style( 'debug-bar', false, array(
			'query-monitor',
		) );
		wp_register_script( 'debug-bar', false, array(
			'query-monitor',
		) );

		do_action( 'debug_bar_enqueue_scripts' );
	}

	public function init_panels() {
		require_once 'debug_bar_panel.php';

		$this->panels = apply_filters( 'debug_bar_panels', array() );
	}

	public function ensure_ajaxurl() {
		if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
			return;
		}

		?>
		<script type="text/javascript">
		var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		</script>
		<?php
	}

	public function Debug_Bar() {
		Debug_Bar::__construct();
	}

}
