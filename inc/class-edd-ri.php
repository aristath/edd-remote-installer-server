<?php
/**
 * The main remote-installer class.
 *
 * @package EDD Remote Installer Server
 * @category Core
 * @author Aristeides Stathopoulos
 * @version 1.0
 */

/**
* The main remote installer class
*/
class EDD_RI {

	/**
	 * Constructor.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function __construct() {
		add_action( 'edd_check_download', array( $this, 'check_download' ) );
		add_action( 'edd_get_download',   array( $this, 'get_download' ) );
	}

	/**
	* Get the price for a download
	* Derived from https://github.com/easydigitaldownloads/Easy-Digital-Downloads/blob/038a293103393cc25c3f4e5592b681c8c8158559/includes/download-functions.php#L155-L206
	*
	* @since 1.0
	* @param int $download_id ID of the download price to show
	* @param bool $echo Whether to echo or return the results
	* @return string|float
	*/
	function edd_price( $download_id = 0 ) {

		if ( empty( $download_id ) ) {
			$download_id = get_the_ID();
		}

		if ( edd_has_variable_prices( $download_id ) ) {

			$prices = edd_get_variable_prices( $download_id );

			// Return the lowest price
			$i = 0;
			foreach ( $prices as $key => $value ) {

				if ( $i < 1 ) {
					$price = $value['amount'];
				}

				if ( (float) $value['amount'] < (float) $price ) {
					$price = (float) $value['amount'];
				}

				$i++;
			}

			return edd_sanitize_amount( $price );
		}
		return edd_get_download_price( $download_id );
	}

	/**
	 * Check the status of the download.
	 *
	 * @since 1.0
	 * @access public
	 * @param array $data The data.
	 * @return void
	 */
	public function check_download( $data ) {

		$download = get_page_by_title( urldecode( $data['item_name'] ), OBJECT, 'download' );

		if ( ! $download ) {
			echo json_encode(
				array(
					'download' => 'invalid'
				)
			);
			exit;
		}
		echo json_encode(
			array(
				'download' => ( $this->edd_price( $download->ID ) > 0 ) ? 'billable' : 'free',
			)
		);
		exit;
	}

	/**
	 * Get a download.
	 *
	 * @since 1.0
	 * @access public
	 * @param array $data The data.
	 * @return void
	 */
	public function get_download( $data ) {

		// Get the item-name.
		$item_name 	= urldecode( $data['item_name'] );
		$args       = array(
			'item_name' => $item_name,
		);
		$download_object = get_page_by_title( $item_name, OBJECT, 'download' );
		$download        = $download_object->ID;
		$price           = $this->edd_price( $download );
		$payment         = -1;
		$user_info       = array(
			'email' => 'Remote-Installer',
			'id'    => 'Remote-Installer',
		);

		if ( $price > 0 ) {

			$args['key'] = urldecode( $data['license'] );
			$edd_sl      = EDD_Software_Licensing();
			$status      = $edd_sl->check_license( $args );

			if ( 'valid' !== $status ) {
				return $status;
			}

			$license_id = $edd_sl->get_license_by_key( $args['key'] );
			$payment_id = get_post_meta( $license_id, '_edd_sl_payment_id', true );
			$user_info  = edd_get_payment_meta_user_info( $payment_id );

		}

		$download_files = array_values( edd_get_download_files( $download ) );

		if ( ! isset( $download_files[0] ) ) {
			wp_die();
		}

		$file = apply_filters( 'edd_requested_file', $download_files[0]['file'], $download_files, '' );

		$this->build_file( $file );

		edd_record_download_in_log( $download, $key, $user_info, edd_get_ip(), $payment );

		wp_die();
	}

	/**
	 * Builds the file to download.
	 *
	 * @since 1.0
	 * @access public
	 * @param string|null $file The file we want to get.
	 * @return void
	 */
	public function build_file( $file = null ) {

		if ( null == $file ) {
			return;
		}

		$requested_file = $file;
		$file_ext       = edd_get_file_extension( $file );
		$ctype          = edd_get_file_ctype( $file_ext );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 0 );
		}

		if ( function_exists( 'get_magic_quotes_runtime' ) && get_magic_quotes_runtime() ) {
			set_magic_quotes_runtime( 0 );
		}

		session_write_close();

		if ( function_exists( 'apache_setenv' ) ) {
			apache_setenv( 'no-gzip', 1 );
		}

		ini_set( 'zlib.output_compression', 'Off' );

		nocache_headers();

		header( 'Robots: none' );
		header( 'Content-Type: ' . $ctype );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename="' . apply_filters( 'edd_requested_file_name', basename( $file ) ) . '";' );
		header( 'Content-Transfer-Encoding: binary' );

		$path = realpath( $file );

		if ( false === filter_var( $file, FILTER_VALIDATE_URL ) && file_exists( $path ) ) {
			readfile( $path );
		} elseif ( strpos( $file, WP_CONTENT_URL ) !== false ) {
			$upload_dir = wp_upload_dir();
			$path       = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file );
			$path       = realpath( $path );

			if ( file_exists( $path ) ) {
				readfile( $path );
			} else {
				header( 'Location: ' . $file );
			}
		} else {
			header( 'Location: ' . $file );
		}
	}
}
