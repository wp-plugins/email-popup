<?php

class EasyOptInsActivity {
	private static $instance;

	public $settings;

	private $text;
	private $table_name;
	private $form_stats;
	private $daily_stats;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . "fca_eoi_activity";

		$this->text = array(
			'impressions' => array(
				'total' => __( 'Total Impressions' ),
				'form'  => __( 'Form Impressions' )
			),
			'conversions' => array(
				'total' => __( 'Total Conversions' ),
				'form'  => __( 'Form Conversions' )
			),
			'conversion_rate' => array(
				'total' => __( 'Conversion Rate' ),
				'form'  => __( 'Conversion Rate' ),
			),
			'period' => __( 'Last %d days' ),
			'all_time' => __( 'All time' )
		);

		add_filter( 'request', array( $this, 'track_activity' ) );
	}

	public function get_text( $name, $category = null, $parameters = array() ) {
		$plain_text = $this->text[ $name ];
		$text = $category ? $plain_text[ $category ] : $plain_text;

		if ( ! empty( $parameters ) ) {
			$text = call_user_func_array( 'sprintf', array_merge( array( $text ), $parameters ) );
		}

		if ( $name == 'period' && empty( $parameters[0] ) ) {
			$text = $this->text['all_time'];
		}

		return $text;
	}

	public function setup() {
		$sql = "CREATE TABLE $this->table_name (
			form_id INT(11) NOT NULL,
			type ENUM('impression', 'conversion') NOT NULL,
			timestamp TIMESTAMP NOT NULL,
			day DATE NOT NULL,
			KEY form_id (form_id),
			KEY timestamp (timestamp),
			KEY day (day)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function get_tracking_code( $form_id, $escape = true ) {
		ob_start();
		?>
			jQuery.post( <?php echo json_encode( trailingslashit( get_home_url() ) ) ?>, {
				'fca_eoi_track_form_id': <?php echo $escape ? json_encode( $form_id ) : $form_id ?>
			} );
		<?php
		return ob_get_clean();
	}

	public function track_activity( $request ) {
		if ( empty( $_REQUEST['fca_eoi_track_form_id'] ) ) {
			return $request;
		}

		require_once $this->settings['plugin_dir'] . 'includes/classes/RobotDetector/RobotDetector.php';

		$form_id = (int) $_REQUEST['fca_eoi_track_form_id'];
		if ( get_post($form_id) ) {
			$robot_detector = new RobotDetector();
			if ( ! $robot_detector->is_robot() ) {
				$this->add_impression( $form_id );
			}
		}

		exit;
	}

	public function format_column_text( $column_name, $value ) {
		if ( $column_name == 'conversion_rate' ) {
			return number_format( $value * 100, 2 ) . '%';
		} else {
			return $value;
		}
	}

	public function get_daily_stats( $day_interval ) {
		if ( empty( $this->daily_stats ) ) {
			global $wpdb;

			$days  = array();
			$stats = array(
				'impressions' => array(),
				'conversions' => array(),
				'totals' => array(
					'impressions' => 0,
					'conversions' => 0,
					'conversion_rate' => 0
				)
			);

			$now = time();
			for ( $i = $day_interval - 1; $i >= 0; $i -- ) {
				$time   = $now - ( 86400 * $i );
				$day    = date( 'Y-m-d', $time );
				$days[] = $day;

				$stats['impressions'][ $day ] = 0;
				$stats['conversions'][ $day ] = 0;
			}

			foreach ( array( 'impression', 'conversion' ) as $activity_type ) {
				$query = $this->get_daily_stats_query( $activity_type, $day_interval );
				foreach ( $wpdb->get_results( $query ) as $result ) {
					$activity_type_plural = $activity_type . 's';

					if ( ! array_key_exists( $result->day, $stats[ $activity_type_plural ] ) ) {
						continue;
					}

					$total = (int) $result->total;

					$stats[ $activity_type_plural ][ $result->day ] = $total;
					$stats['totals'][ $activity_type_plural ] += $total;
				}
			}

			$stats['totals']['conversion_rate'] = $this->calculate_conversion_rate(
				$stats['totals']['impressions'],
				$stats['totals']['conversions']
			);

			$this->daily_stats = $stats;
		}

		return $this->daily_stats;
	}

	public function get_form_stats( $day_interval ) {
		if ( empty( $this->form_stats ) ) {
			global $wpdb;

			$stats = array();
			$ids = array();

			foreach ( array( 'impression', 'conversion' ) as $activity_type ) {
				$stat = array();
				$query = $this->get_form_stats_query( $activity_type, $day_interval );
				foreach ( $wpdb->get_results( $query ) as $result ) {
					$ids[ $result->form_id ] = true;
					$stat[ $result->form_id ] = (int) $result->total;
				}
				$stats[ $activity_type . 's' ] = $stat;
			}

			foreach ( array_keys( $ids ) as $form_id ) {
				$stats['conversion_rate'][ $form_id ] = $this->calculate_conversion_rate(
					floatval( empty( $stats['impressions'][ $form_id ] ) ? 0 : $stats['impressions'][ $form_id ] ),
					floatval( empty( $stats['conversions'][ $form_id ] ) ? 0 : $stats['conversions'][ $form_id ] )
				);
			}

			$this->form_stats = $stats;
		}

		return $this->form_stats;
	}

	private function calculate_conversion_rate( $impressions, $conversions ) {
		if ( $impressions < 1 ) {
			return 0.0;
		} else {
			return $conversions / $impressions;
		}
	}

	public function add_impression( $form_id ) {
		$this->add_activity( $form_id, 'impression' );
	}

	public function add_conversion( $form_id ) {
		$this->add_activity( $form_id, 'conversion' );
	}

	public function reset_stats( $form_id ) {
		global $wpdb;

		$wpdb->delete( $this->table_name, array( 'form_id' => $form_id ), '%d' );
	}

	private function add_activity( $form_id, $activity_type ) {
		global $wpdb;

		$time = current_time( 'mysql', 1 );
		$wpdb->insert( $this->table_name, array(
			'form_id'   => $form_id,
			'type'      => $activity_type,
			'timestamp' => $time,
			'day'       => $time
		), array( '%d', '%s', '%s', '%s' ) );
	}

	private function get_form_stats_query( $activity_type, $day_interval ) {
		return $this->get_stats_query( 'form_id', $activity_type, $day_interval );
	}

	private function get_daily_stats_query( $activity_type, $day_interval ) {
		return $this->get_stats_query( 'day', $activity_type, $day_interval );
	}

	private function get_stats_query( $field, $activity_type, $day_interval ) {
		global $wpdb;

		return $wpdb->prepare(
			"SELECT `$field`, COUNT(*) AS `total` " .
			"FROM `$this->table_name` " .
			"WHERE " .
				"`type` = %s " .
				( $day_interval
					? "AND `timestamp` >= DATE_SUB(NOW(), INTERVAL $day_interval DAY) "
					: "") .
			"GROUP BY `$field`", $activity_type );
	}
}
