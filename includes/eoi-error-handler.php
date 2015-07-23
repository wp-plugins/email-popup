<?php

class EasyOptInsErrorHandler {
	public $errors = array();
	public $output = array();

	public function capture_start() {
		ob_start();

		set_error_handler( array( $this, 'handle_error' ), E_USER_ERROR );

		add_filter( 'wp_die_handler', array( $this, 'handle_die' ) );
		add_filter( 'wp_die_ajax_handler', array( $this, 'handle_die' ) );
	}

	public function capture_end() {
		ob_end_clean();

		restore_error_handler();

		remove_filter( 'wp_die_handler', array( $this, 'handle_die' ) );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'handle_die' ) );
	}

	public function handle_error( $err_no, $err_str ) {
		$this->output[] = ob_get_clean();
		ob_start();

		$this->errors[] = $err_str;
	}

	public function handle_die() {
		$output = ob_get_clean();
		ob_start();

		$this->output[] = $output;

		return array( $this, 'handle_die_callback' );
	}

	public function handle_die_callback( $message ) {
		$this->errors[] = $message;

		return true;
	}
}
