<?php

function customform_object() {
}

function customform_integration() {
	$fca_eoi = get_post_meta( $GLOBALS['post']->ID, 'fca_eoi', true );

	K::fieldset( __( 'HTML Form Integration' ) ,
		array(
			array( 'textarea', 'fca_eoi[customform_html_code]',
				array(
					'style' => 'width: 25em; font-family: monospace;',
					'rows' => '4'
				),
				array(
					'format' => '<p><label class="fca_eoi_customform_label">HTML Form Code<br />:textarea<br /></label></p><em>Please paste your email marketing provider\'s HTML form in here. <a tabindex="-1" href="https://fatcatapps.com/optincat/docs/email-provider-integrations/html-form/" target="_blank">Click here to learn more</a>.</em>',
					'value' => K::get_var( 'customform_html_code', $fca_eoi )
				)
			),
			array( 'input', 'fca_eoi[customform_list_id]',
				array(
					'type' => 'hidden',
					'value' => '1'
				)
			)
		),
		array(
			'id' => 'fca_eoi_fieldset_form_customform_integration',
		)
	);
}

function customform_on_save( $meta ) {
	$parser    = new EasyOptInsCustomFormParser( stripslashes( $meta['customform_html_code'] ) );
	$form_data = $parser->parse();

	if ( $form_data ) {
		$meta['customform_request'] = $form_data;
	}

	return $meta;
}

function customform_add_user( $settings, $request_data ) {
	$fca_eoi = get_post_meta( $request_data['fca_eoi_form_id'], 'fca_eoi', true );
	if ( ! empty ( $fca_eoi['customform_request'] ) ) {
		$sender = new EasyOptInsCustomFormSender( $fca_eoi['customform_request'], $request_data );
		$sender->send();
	}
}

function customform_admin_notices( $errors ) {
	return $errors;
}

class EasyOptInsCustomFormSender {
	/**
	 * @var array
	 */
	private $form_data;

	/**
	 * @var array
	 */
	private $request_data;

	/**
	 * @var array
	 */
	private $parameters;

	/**
	 * @var array
	 */
	private $encoded_parameters;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var array
	 */
	private $options;

	/**
	 * @param array $form_data
	 * @param array $request_data
	 */
	public function __construct( $form_data, $request_data ) {
		$this->form_data    = $form_data;
		$this->request_data = $request_data;
	}

	public function send() {
		$response = wp_remote_request( $this->get_url(), $this->get_options() );
		if ( wp_remote_retrieve_response_code( $response ) === 302 ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( $location ) {
				wp_remote_get( $response['headers']['location'], array_merge( array(
					'cookies' => $response['cookies']
				), $this->get_default_options() ) );
			}
		}
	}

	/**
	 * @return string
	 */
	private function get_url() {
		if ( ! empty( $this->url ) ) {
			return $this->url;
		}

		if ( $this->form_data['method'] === 'GET' ) {
			$this->url = add_query_arg( $this->get_encoded_parameters(), $this->form_data['url'] );
		} else {
			$this->url = $this->form_data['url'];
		}

		return $this->url;
	}

	/**
	 * @return array
	 */
	private function get_options() {
		if ( ! empty( $this->options ) ) {
			return $this->options;
		}

		$options = array_merge( array(
			'method'  => $this->form_data['method'],
			'headers' => $this->get_default_headers(),
		), $this->get_default_options() );

		if ( $this->form_data['method'] === 'GET' ) {
			$this->options = $this->options_for_get( $options );
		} else {
			$this->options = $this->options_for_post( $options );
		}

		return $this->options;
	}

	/**
	 * @return array
	 */
	private function get_default_options() {
		return array(
			'httpversion' => '1.1',
			'user-agent'  => $_SERVER['HTTP_USER_AGENT'],
			'sslverify'   => false,
			'redirection' => 0
		);
	}

	/**
	 * @return array
	 */
	private function get_default_headers() {
		return array(
			'Host'            => $this->form_data['host'],
			'Content-Type'    => 'application/x-www-form-urlencoded',
			'Cache-Control'   => 'max-age=0',
			'Accept'          => 'text/html,application/xhtml+xml,application/xml',
			'Origin'          => 'null',
			'Accept-Encoding' => 'gzip, deflate',
			'Accept-Language' => 'en-US,en'
		);
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	private function options_for_get( $options ) {
		$options['headers']['Content-Length'] = 0;

		return $options;
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	private function options_for_post( $options ) {
		$options = $this->form_data['multipart']
			? $this->options_for_multipart_post( $options )
			: $this->options_for_simple_post( $options );

		$options['headers']['Content-Length'] = strlen( $options['body'] );

		return $options;
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	private function options_for_simple_post( $options ) {
		$options['body'] = build_query( $this->get_encoded_parameters() );

		return $options;
	}

	/**
	 * @param array $options
	 *
	 * @return array
	 */
	private function options_for_multipart_post( $options ) {
		$boundary = '----FormBoundary' . str_replace( '.', '', uniqid( microtime( true ), true ) );

		$options['body'] = $this->generate_multipart_body( $boundary );

		$options['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

		return $options;
	}

	/**
	 * @param string $boundary
	 *
	 * @return string
	 */
	private function generate_multipart_body( $boundary ) {
		$body = '';

		foreach ( $this->get_parameters() as $name => $value ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n";
			$body .= "\r\n";
			$body .= $value . "\r\n";
		}

		$body .= '--' . $boundary . "--\r\n";

		return $body;
	}

	/**
	 * @return array
	 */
	private function get_parameters() {
		if ( empty( $this->parameters ) ) {
			$form    = $this->form_data;
			$request = $this->request_data;

			if ( ! empty( $form['target_fields']['email'] ) && ! empty( $request['email'] ) ) {
				$form['fields'][ $form['target_fields']['email'] ] = $request['email'];
			}

			if ( ! empty( $form['target_fields']['name'] ) && ! empty( $request['name'] ) ) {
				$form['fields'][ $form['target_fields']['name'] ] = $request['name'];
			}

			$this->parameters = $form['fields'];
		}

		return $this->parameters;
	}

	/**
	 * @return array
	 */
	private function get_encoded_parameters() {
		if ( empty( $this->encoded_parameters ) ) {
			$encoded_parameters = array();
			foreach ( $this->get_parameters() as $key => $value ) {
				$encoded_parameters[ urlencode( $key ) ] = urlencode( $value );
			}

			$this->encoded_parameters = $encoded_parameters;
		}

		return $this->encoded_parameters;
	}
}

class EasyOptInsCustomFormParser {
	/**
	 * @var string
	 */
	private $html_code;

	/**
	 * @var DOMElement
	 */
	private $form_element;

	/**
	 * @param string $html_code
	 */
	public function __construct( $html_code ) {
		$this->html_code = $html_code;
	}

	/**
	 * @return array|null
	 */
	public function parse() {
		$form_element = $this->get_form_element();
		if ( empty( $form_element ) ) {
			return null;
		}

		$fields = $this->parse_fields();
		if ( empty( $fields['target_fields']['email'] ) ) {
			return null;
		}

		$url = $this->parse_url();

		return array_merge( array(
			'url'       => $url,
			'method'    => $this->parse_method(),
			'host'      => $this->parse_host( $url ),
			'multipart' => $this->parse_multipart()
		), $fields );
	}

	/**
	 * @return DOMElement|null
	 */
	private function get_form_element() {
		if ( empty( $this->form_element ) ) {
			$this->form_element = $this->parse_form_element();
		}

		return $this->form_element;
	}

	/**
	 * @return DOMElement|null
	 */
	private function parse_form_element() {
		$error_handler = new EasyOptInsErrorHandler();
		$error_handler->capture_start();

		$document = new DOMDocument();
		$document->loadHTML( mb_convert_encoding( $this->html_code, 'HTML-ENTITIES', 'UTF-8' ) );

		$error_handler->capture_end();

		$form_nodes = $document->getElementsByTagName( 'form' );
		if ( $form_nodes->length >= 1 ) {
			return $form_nodes->item( 0 );
		}

		return null;
	}

	/**
	 * @return string
	 */
	private function parse_url() {
		$url = preg_replace( '/#.*$/', '', $this->get_form_element()->getAttribute( 'action' ) );

		if ( empty( $url ) ) {
			return home_url();
		}

		if ( strpos( $url, '//' ) === 0 ) {
			$url = ( is_ssl() ? 'https' : 'http' ) . ':' . $url;
		}

		return $url;
	}

	/**
	 * @return string
	 */
	private function parse_method() {
		$method = $this->get_form_element()->getAttribute( 'method' );

		return empty( $method ) ? 'POST' : strtoupper( $method );
	}

	/**
	 * @return bool
	 */
	private function parse_multipart() {
		$enctype = $this->get_form_element()->getAttribute( 'enctype' );

		return ! empty( $enctype ) && strtolower( $enctype ) === 'multipart/form-data';
	}

	/**
	 * @return array
	 */
	private function parse_fields() {
		$fields        = array();
		$target_fields = array( 'name' => null, 'email' => null );

		/**
		 * @var $input_element DOMElement
		 */
		foreach ( $this->get_form_element()->getElementsByTagName( 'input' ) as $input_element ) {
			$field_name  = $input_element->getAttribute( 'name' );
			if ( empty( $field_name ) ) {
				continue;
			}

			$field_value = $input_element->getAttribute( 'value' );

			if ( empty( $target_fields['email'] ) && $this->is_input_element_email( $input_element ) ) {
				$target_fields['email'] = $field_name;
			}

			if ( empty( $target_fields['name'] ) && $this->is_input_element_name( $input_element ) ) {
				$target_fields['name'] = $field_name;
			}

			$fields[ $field_name ] = $field_value;
		}

		return array( 'fields' => $fields, 'target_fields' => $target_fields );
	}

	/**
	 * @param DOMElement $input_element
	 *
	 * @return bool
	 */
	private function is_input_element_email( $input_element ) {
		return $input_element->getAttribute( 'type' ) === 'email'
		       || $this->element_matches_content( $input_element, array( 'email', 'mail' ) );
	}

	/**
	 * @param DOMElement $input_element
	 *
	 * @return bool
	 */
	private function is_input_element_name( $input_element ) {
		return $this->element_matches_content(
			$input_element,
			array( 'fname', 'firstname', 'first_name', 'name', 'lname', 'last_name' )
		);
	}

	/**
	 * @param DOMElement $input_element
	 * @param string[] $content
	 *
	 * @return bool
	 */
	private function element_matches_content( $input_element, $content ) {
		if ( $input_element->getAttribute( 'type' ) === 'hidden' ) {
			return false;
		}

		$field_name = $input_element->getAttribute( 'name' );
		$field_id   = $input_element->getAttribute( 'id' );

		foreach ( $content as $content_part ) {
			if ( stripos( $field_name, $content_part ) !== false || stripos( $field_id, $content_part ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $url
	 *
	 * @return string
	 */
	private function parse_host( $url ) {
		$parsed_url = parse_url( $url );

		return $parsed_url['host'];
	}
}
