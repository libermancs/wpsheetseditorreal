<?php defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPSE_WC_Products_Sheet' ) ) {

	class WPSE_WC_Products_Sheet extends WPSE_Sheet_Factory {
		public $post_type = 'product';
		public function __construct() {
			$allowed_columns = array();

			if ( ! wpsewcp_freemius()->can_use_premium_code__premium_only() ) {
				$allowed_columns = $this->allow_columns( array() );
			}
			parent::__construct(
				array(
					'fs_object'       => wpsewcp_freemius(),
					'post_type'       => array( $this, 'get_post_types_and_labels' ),
					'allowed_columns' => $allowed_columns,
				)
			);

			add_filter( 'vg_sheet_editor/woocommerce/teasers/allowed_columns', array( $this, 'allow_columns' ) );
		}
		function get_post_types_and_labels() {
			$out = array(
				'post_types' => array( $this->post_type ),
				'labels'     => array( __( 'Products', 'woocommerce' ) ),
			);

			// If this is the free version of the products plugin and there is a premium version of the post types plugin, don't load this to give priority to the post types plugin
			if ( function_exists( 'vgse_freemius' ) && vgse_freemius()->can_use_premium_code__premium_only() && ! wpsewcp_freemius()->can_use_premium_code__premium_only() ) {
				$out = array(
					'post_types' => array(),
					'labels'     => array(),
				);
			}

			return $out;
		}
		function allow_columns( $columns ) {
			$enable = array(
				'ID',
				'post_title',
				'_sku',
				'_regular_price',
				'_sale_price',
				'_manage_stock',
				'_stock_status',
				'_stock',
				'post_content',
				'_virtual',
				'open_wp_editor',
				'view_post',
			);

			return array_unique( array_merge( $enable, $columns ) );
		}

	}
	new WPSE_WC_Products_Sheet();
}
