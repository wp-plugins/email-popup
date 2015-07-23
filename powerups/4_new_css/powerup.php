<?php

function powerup_new_css() {
	paf_options( array(
		'eoi_powerup_new_css' => array(
			'type' => 'checkbox',
			'options' => array(
				'on' => __( 'Enabled (recommended)' ),
			),
			'page' => 'eoi_powerups',
			'title' => __( 'New CSS' ),
			'description' => sprintf( '<p class="description eoi_powerup_description">%s</p>', __( 'Enhances the base CSS for responsiveness and compatibility.' ) ),
		)
	) );
}

function powerup_new_css_is_active() {
	$paf = get_option( 'paf' );
	$key = 'eoi_powerup_new_css';

	return ! empty( $paf )
	       && is_array( $paf )
	       && ! empty( $paf[ $key ] )
	       && is_array( $paf[ $key ] )
	       && in_array( 'on', $paf[ $key ] );
}

function powerup_new_css_set_active( $active ) {
	$paf = get_option( 'paf' );
	$key = 'eoi_powerup_new_css';

	if ( $active ) {
		$paf[ $key ] = array( 'on' );
		powerup_new_css_on_activate();
	} else {
		$paf[ $key ] = array();
		powerup_new_css_on_deactivate();
	}

	update_option( 'paf', $paf );
}

function powerup_new_css_dismiss_notification() {
	require_once dirname( __FILE__ ) . '/../../includes/eoi-init.php';
	EasyOptInsInit::get_instance()->set_new_css_notification_dismissed( true );
}

function powerup_new_css_on_activate() {
	$new_css = new EoiNewCssMigration();
	$new_css->migrate( 'old', 'new' );

	add_action( 'admin_init', 'powerup_new_css_dismiss_notification' );
}

function powerup_new_css_on_deactivate() {
	$new_css = new EoiNewCssMigration();
	$new_css->migrate( 'new', 'old' );
}

class EoiNewCssMigration {
	private $layouts;
	private $layout_ids;

	public function migrate( $from, $to ) {
		foreach ( get_posts( array( 'post_type' => 'easy-opt-ins', 'posts_per_page' => - 1 ) ) as $optin_form ) {
			$this->migrate_form( $optin_form, $from, $to );
		}
	}

	private function migrate_form( $optin_form, $from, $to ) {
		$form_id = $optin_form->ID;
		$fca_eoi = get_post_meta( $form_id, 'fca_eoi', true );

		$fca_eoi_changed = false;
		foreach ( $this->get_layout_ids() as $layout_id ) {
			if ( $layout_id == 'layout_2' || $layout_id == 'postbox_2' || $layout_id == 'lightbox_2' ) {
				$this->prepare_for_layout_2( $layout_id, $fca_eoi, $from );
			}

			if ( $this->migrate_layout( $layout_id, $fca_eoi, $from, $to ) ) {
				$fca_eoi_changed = true;
			}
		}

		if ( $fca_eoi_changed ) {
			delete_post_meta( $form_id, 'fca_eoi' );
			add_post_meta( $form_id, 'fca_eoi', $fca_eoi );
		}
	}

	private function prepare_for_layout_2( $layout_id, &$fca_eoi, $from ) {
		if ( empty( $fca_eoi[ $layout_id ] ) ) {
			return;
		}

		foreach ( $fca_eoi[ $layout_id ] as $main_selector => $attributes ) {
			foreach ( $attributes as $sub_selector => $value ) {
				if ( $from == 'old' && $sub_selector == 'border-top-color' ) {
					$fca_eoi[ $layout_id ][ $main_selector ]['fill'] = $value;
					unset( $fca_eoi[ $layout_id ][ $main_selector ][ $sub_selector ] );
				} elseif ( $from == 'new' && $sub_selector == 'fill' ) {
					$fca_eoi[ $layout_id ][ $main_selector ]['border-top-color'] = $value;
					unset( $fca_eoi[ $layout_id ][ $main_selector ][ $sub_selector ] );
				}
			}
		}
	}

	private function migrate_layout( $layout_id, &$fca_eoi, $from, $to ) {
		$layouts = $this->get_layouts();
		$fca_eoi_changed = false;

		if ( ! empty( $fca_eoi[ $layout_id ] ) ) {
			$from_selectors = $layouts[ $layout_id ][ $from ];
			$to_selectors = $layouts[ $layout_id ][ $to ];

			for ( $i = 0, $len = count( $from_selectors ); $i < $len; $i++ ) {
				$from_selector = $from_selectors[ $i ];
				$to_selector = $to_selectors[ $i ];

				if ( ! empty( $fca_eoi[ $layout_id ][ $from_selector ] ) ) {
					$fca_eoi_changed = true;
					$this->migrate_selector( $from_selector, $to_selector, $layout_id, $fca_eoi );
				}
			}
		}

		return $fca_eoi_changed;
	}

	private function migrate_selector( $from_selector, $to_selector, $layout_id, &$fca_eoi ) {
		$fca_eoi[ $layout_id ][ $to_selector ] = $fca_eoi[ $layout_id ][ $from_selector ];
		unset( $fca_eoi[ $layout_id ][ $from_selector ] );
	}

	private function get_layouts() {
		if ( ! empty( $this->layouts ) ) {
			return $this->layouts;
		}

		$layouts = array();

		foreach ( glob( FCA_EOI_PLUGIN_DIR . 'layouts/*/*/layout-new.php' ) as $path ) {
			if ( strpos( $path, '/common/' ) !== false ) {
				continue;
			}

			$path_info  = pathinfo( $path );
			$layout_id  = basename( $path_info['dirname'] );
			$old_layout = $path_info['dirname'] . DIRECTORY_SEPARATOR . 'layout.php';
			$new_layout = $path;

			$layouts[ $layout_id ] = array(
				'old' => null,
				'new' => null
			);

			$layout = null;

			require $old_layout;
			$layouts[ $layout_id ]['old'] = $this->extract_main_selectors_from_layout( $layout );

			require $new_layout;
			$layouts[ $layout_id ]['new'] = $this->extract_main_selectors_from_layout( $layout );
		}

		$this->layouts = $layouts;
		return $layouts;
	}

	private function extract_main_selectors_from_layout( $layout ) {
		if ( empty( $layout['editables'] ) ) {
			return array();
		}

		$selectors = array();

		foreach ( $layout['editables'] as $parts ) {
			$selectors = array_merge( $selectors, array_keys( $parts ) );
		}

		return $selectors;
	}

	private function get_layout_ids() {
		if ( empty( $this->layout_ids ) ) {
			$this->layout_ids = array_keys( $this->get_layouts() );
		}
		return $this->layout_ids;
	}
}
