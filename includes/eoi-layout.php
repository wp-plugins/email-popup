<?php

class EasyOptInsLayout {
	public $layout_number;
	public $layout_type;
	public $layout_class;
	public $layout_id;

	private $plugin_dir;
	private $plugin_url;

	public static function uses_new_css() {
		return (bool) paf( 'eoi_powerup_new_css' );
	}

	public function __construct( $layout_id ) {
		$this->plugin_dir = FCA_EOI_PLUGIN_DIR;
		$this->plugin_url = FCA_EOI_PLUGIN_URL . '/';

		list( $layout_type, $layout_number ) = explode( '_', $layout_id );

		$this->layout_number = (int) $layout_number;
		$this->layout_type   = $layout_type == 'layout' ? 'widget' : $layout_type;
		$this->layout_class  = self::generate_layout_class( $this->layout_type );
		$this->layout_id     = $layout_id;
	}

	public function new_scss_compiler() {
		$scss = new scssc();
		$scss->setFormatter( 'scss_formatter_compressed' );
		$scss->addImportPath( $this->plugin_dir );

		return $scss;
	}

	public function path_to_html_wrapper() {
		return $this->plugin_dir . $this->common_path() . $this->layout_type . '.html';
	}

	public function path_to_resource( $resource_name, $resource_type ) {
		return $this->plugin_dir . $this->subpath_to_resource( $resource_name, $resource_type );
	}

	public function url_to_resource( $resource_name, $resource_type ) {
		return $this->plugin_url . $this->subpath_to_resource( $resource_name, $resource_type );
	}

	private static function generate_layout_class( $layout_type ) {
		if ( $layout_type == 'lightbox' ) {
			return 'fca_eoi_layout_popup';
		} elseif ( $layout_type == 'postbox' ) {
			return 'fca_eoi_layout_postbox';
		} elseif ( $layout_type == 'widget' ) {
			return 'fca_eoi_layout_widget';
		}
		return '';
	}

	private function subpath_to_resource( $resource_name, $resource_type ) {
		if ( self::uses_new_css() ) {
			if ( $resource_name == 'layout' && $resource_type == 'html' ) {
				$new_path =
					$this->common_path() .
					'layout_' . $this->layout_number . '/' .
					$resource_name . '.' . $resource_type;

				if ( file_exists( $this->plugin_dir . $new_path ) ) {
					return $new_path;
				}
			}

			$new_path = $this->subpath() . $resource_name . '-new.' . $resource_type;
			if ( file_exists( $this->plugin_dir . $new_path ) ) {
				return $new_path;
			}
		}

		$path = $this->subpath();

		if ( $resource_type == 'scss' ) {
			$resource_type = 'css';
		}

		return $path . $resource_name . '.' . $resource_type;
	}

	private function subpath() {
		return 'layouts/' . $this->layout_type . '/' . $this->layout_id . '/';
	}

	private function common_path() {
		return 'layouts/common/';
	}
}
