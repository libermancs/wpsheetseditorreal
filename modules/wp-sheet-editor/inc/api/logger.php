<?php defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPSE_Logger' ) ) {

	class WPSE_Logger {

		private static $instance = false;
		public $directory        = null;
		public $secret_key       = null;
		public $current_job_id   = null;


		private function __construct() {

		}

		function file_expiration_hours() {
			// Expire in 7 days
			return apply_filters( 'vg_sheet_editor/logs/file_expiration_hours', 24 * 7 );
		}

		function get_site_key() {
			if ( $this->secret_key ) {
				return $this->secret_key;
			}
			if ( ! get_option( 'vgse_secret_key' ) ) {
				update_option( 'vgse_secret_key', md5( VGSE()->helpers->get_uuid() ), false );
			}
			// We use the secret key to add extra security to the file names
			$this->secret_key = get_option( 'vgse_secret_key' );
			return $this->secret_key;

		}

		function maybe_download_log_file() {
			if ( empty( $_GET['wpseelf'] ) || ! VGSE()->helpers->user_can_manage_options() ) {
				return;
			}

			if ( strpos( $_GET['wpseelf'], '.' ) !== false || strpos( $_GET['wpseelf'], '/' ) !== false || strpos( $_GET['wpseelf'], '\\' ) !== false ) {
				die();
			}
			$job_id           = sanitize_file_name( $_GET['wpseelf'] );
			$path             = $this->get_job_file( $job_id );
			$file_name        = current( explode( '.', basename( $path ) ) );
			$public_file_name = str_replace( '-' . $this->get_site_key(), '', $file_name );

			if ( ! file_exists( $path ) ) {
				die( __( 'The log file does not exist.', 'vg_sheet_editor' ) );
			}

			// output headers so that the file is downloaded rather than displayed
			header( 'Content-type: text/plain' );
			header( "Content-disposition: attachment; filename = $public_file_name.txt" );
			VGSE()->helpers->readfile_chunked( $path );
			die();
		}

		function maybe_create_directories() {
			if ( ! is_dir( $this->directory ) ) {
				wp_mkdir_p( $this->directory );
			}
			if ( ! file_exists( $this->directory . '/index.html' ) ) {
				file_put_contents( $this->directory . '/index.html', '' );
			}
		}

		function delete_old_files() {
			$files = VGSE()->helpers->get_files_list( $this->directory, '.txt' );
			foreach ( $files as $file ) {
				// Delete csv files older than 6 hours to avoid deleting exports in progress.
				$expiration_hours = (int) $this->file_expiration_hours();
				if ( file_exists( $file ) && ( time() - filemtime( $file ) > $expiration_hours * 3600 ) ) {
					unlink( $file );
				}
			}
		}

		function get_log_download_url( $job_id ) {
			$out = null;
			if ( ! empty( $job_id ) ) {
				$out = esc_url_raw( add_query_arg( 'wpseelf', sanitize_file_name( $job_id ), admin_url( 'index.php' ) ) );
			}
			return $out;
		}

		function get_job_file( $job_id ) {
			if ( strpos( $job_id, '-' . $this->get_site_key() ) === false ) {
				$job_id .= '-' . $this->get_site_key();
			}
			$file_name = str_replace( array( '.', '/', '\\', ':' ), '', wp_normalize_path( sanitize_file_name( $job_id ) ) );
			$file_path = wp_normalize_path( $this->directory . '/' . $file_name . '.txt' );
			if ( ! file_exists( $file_path ) ) {
				file_put_contents( $file_path, '' );
			}
			return $file_path;
		}

		function set_current_job_id( $job_id ) {
			$this->current_job_id = $job_id;
		}

		function entry( $message, $job_id = null ) {
			if ( ! $job_id && $this->current_job_id ) {
				$job_id = $this->current_job_id;
			}
			if ( ! $job_id ) {
				return false;
			}
			$file_path = $this->get_job_file( $job_id );
			if ( ! file_exists( $file_path ) ) {
				return $this;
			}
			$t     = microtime( true );
			$micro = sprintf( '%06d', ( $t - floor( $t ) ) * 1000000 );

			$time = current_time( 'mysql' ) . '.' . $micro;

			$fp = fopen( $file_path, 'a' ); //opens file in append mode
			fwrite( $fp, $time . ' - ' . wp_kses_post( $message ) . PHP_EOL . PHP_EOL );
			fclose( $fp );
			return $this;
		}

		function init() {
			$this->directory = apply_filters( 'vg_sheet_editor/logs/directory', WP_CONTENT_DIR . '/uploads/wp-sheet-editor/logs' );
			do_action( 'wpse_delete_old_csvs', array( $this, 'delete_old_files' ) );
			if ( is_admin() ) {
				$this->maybe_create_directories();
				add_action( 'vg_sheet_editor/initialized', array( $this, 'maybe_download_log_file' ) );
				add_action( 'admin_init', array( $this, 'delete_old_files' ) );
			}
		}

		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if ( null == self::$instance ) {
				self::$instance = new WPSE_Logger();
				self::$instance->init();
			}
			return self::$instance;
		}

		function __set( $name, $value ) {
			$this->$name = $value;
		}

		function __get( $name ) {
			return $this->$name;
		}

	}

}

if ( ! function_exists( 'WPSE_Logger_Obj' ) ) {

	function WPSE_Logger_Obj() {
		return WPSE_Logger::get_instance();
	}
}
WPSE_Logger_Obj();
