<?php

namespace DSA\Scheduler;

defined( 'ABSPATH' ) || exit;

final class CronSchedule {
	private const MODES = array( 'manual', 'interval', 'daily', 'weekly', 'monthly', 'once', 'advanced' );
	private const WEEKDAYS = array( 1, 2, 3, 4, 5, 6, 0 );

	public static function normalize( $input, $default_timezone ) {
		if ( ! is_array( $input ) ) {
			throw new \InvalidArgumentException( 'Configuration invalide.' );
		}

		$mode = isset( $input['mode'] ) && is_string( $input['mode'] ) ? sanitize_key( $input['mode'] ) : '';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			throw new \InvalidArgumentException( 'Mode de planification invalide.' );
		}

		if ( isset( $input['timezone'] ) && ! is_string( $input['timezone'] ) ) {
			throw new \InvalidArgumentException( 'Fuseau horaire invalide.' );
		}
		$timezone = isset( $input['timezone'] ) ? trim( $input['timezone'] ) : $default_timezone;
		$allowed_timezones = \DateTimeZone::listIdentifiers();
		if ( 'UTC' === $timezone ) {
			$allowed_timezones[] = 'UTC';
		}
		$is_fixed_offset = is_string( $timezone ) && (bool) preg_match( '/^[+-](?:0\d|1[0-3]):[0-5]\d$|^[+-]14:00$/', $timezone );
		if ( ! in_array( $timezone, $allowed_timezones, true ) && ! $is_fixed_offset ) {
			throw new \InvalidArgumentException( 'Fuseau horaire invalide.' );
		}

		$config = array(
			'mode'     => $mode,
			'enabled'  => self::boolean_value( $input['enabled'] ?? false ),
			'timezone' => $timezone,
			'start_at' => self::date_value( $input['start_at'] ?? '', $timezone ),
			'end_at'   => self::date_value( $input['end_at'] ?? '', $timezone ),
		);

		if ( $config['start_at'] && $config['end_at'] && $config['start_at'] > $config['end_at'] ) {
			throw new \InvalidArgumentException( 'La date de fin doit suivre la date de début.' );
		}

		if ( 'interval' === $mode ) {
			$amount = self::integer_value( $input['amount'] ?? null );
			$unit = isset( $input['unit'] ) && is_string( $input['unit'] ) ? sanitize_key( $input['unit'] ) : '';
			if ( null === $amount || $amount < 1 || $amount > 31 || ! in_array( $unit, array( 'minutes', 'hours', 'days' ), true ) || ( 'minutes' === $unit && $amount > 59 ) || ( 'hours' === $unit && $amount > 23 ) ) {
				throw new \InvalidArgumentException( 'Intervalle invalide.' );
			}
			$config['amount'] = $amount;
			$config['unit'] = $unit;
		} elseif ( in_array( $mode, array( 'daily', 'weekly', 'monthly' ), true ) ) {
			$config['time'] = self::time_value( $input['time'] ?? '' );
			if ( 'weekly' === $mode ) {
				$days = isset( $input['weekdays'] ) && is_array( $input['weekdays'] ) ? array_map( array( __CLASS__, 'integer_value' ), $input['weekdays'] ) : array();
				$days = array_values( array_unique( $days ) );
				if ( ! $days || in_array( null, $days, true ) || array_diff( $days, self::WEEKDAYS ) ) {
					throw new \InvalidArgumentException( 'Sélectionnez au moins un jour valide.' );
				}
				sort( $days );
				$config['weekdays'] = $days;
			}
			if ( 'monthly' === $mode ) {
				$type = isset( $input['monthly_type'] ) && is_string( $input['monthly_type'] ) ? sanitize_key( $input['monthly_type'] ) : 'day';
				if ( 'day' === $type ) {
					$day = self::integer_value( $input['day'] ?? null );
					$last_day = self::boolean_value( $input['last_day'] ?? false );
					if ( ! $last_day && ( null === $day || $day < 1 || $day > 31 ) ) {
						throw new \InvalidArgumentException( 'Jour du mois invalide.' );
					}
					$config['monthly_type'] = 'day';
					$config['day'] = $last_day ? 'last' : $day;
				} elseif ( 'weekday' === $type ) {
					$ordinal = self::integer_value( $input['ordinal'] ?? null );
					$weekday = self::integer_value( $input['weekday'] ?? null );
					if ( null === $ordinal || $ordinal < 1 || $ordinal > 5 || ! in_array( $weekday, self::WEEKDAYS, true ) ) {
						throw new \InvalidArgumentException( 'Rang ou jour de semaine invalide.' );
					}
					$config['monthly_type'] = 'weekday';
					$config['ordinal'] = $ordinal;
					$config['weekday'] = $weekday;
				} else {
					throw new \InvalidArgumentException( 'Règle mensuelle invalide.' );
				}
			}
		} elseif ( 'once' === $mode ) {
			$config['once_at'] = self::date_value( $input['once_at'] ?? '', $timezone );
			if ( ! $config['once_at'] ) {
				throw new \InvalidArgumentException( 'Date d’exécution requise.' );
			}
		} elseif ( 'advanced' === $mode ) {
			$cron = isset( $input['cron'] ) && is_string( $input['cron'] ) ? trim( $input['cron'] ) : '';
			if ( strlen( $cron ) > 128 ) {
				throw new \InvalidArgumentException( 'L’expression cron est trop longue.' );
			}
			self::parse_cron( $cron );
			$config['cron'] = $cron;
		}

		$config['expression'] = self::expression( $config );
		return $config;
	}

	public static function expression( $config ) {
		$time = isset( $config['time'] ) ? explode( ':', $config['time'] ) : array( '0', '0' );
		$minute = isset( $time[1] ) ? (int) $time[1] : 0;
		$hour = isset( $time[0] ) ? (int) $time[0] : 0;
		switch ( $config['mode'] ) {
			case 'manual':
				return '@manual';
			case 'interval':
				return '@every:' . (int) $config['amount'] . ':' . $config['unit'];
			case 'daily':
				return sprintf( '%d %d * * *', $minute, $hour );
			case 'weekly':
				$days = array_map( static function ( $day ) { return 0 === $day ? 7 : $day; }, $config['weekdays'] );
				return sprintf( '%d %d * * %s', $minute, $hour, implode( ',', $days ) );
			case 'monthly':
				if ( 'weekday' === $config['monthly_type'] ) {
					$day = 0 === $config['weekday'] ? 7 : $config['weekday'];
					return sprintf( '%d %d * * %d#%d', $minute, $hour, $day, $config['ordinal'] );
				}
				return sprintf( '%d %d %s * *', $minute, $hour, 'last' === $config['day'] ? 'L' : (string) $config['day'] );
			case 'once':
				return '@once:' . str_replace( 'T', ':', $config['once_at'] );
			case 'advanced':
				return $config['cron'];
		}

		return '@manual';
	}

	public static function next_occurrences( $config, $from = null, $limit = 5 ) {
		if ( 'manual' === $config['mode'] ) {
			return array();
		}

		$timezone = new \DateTimeZone( $config['timezone'] );
		$now = $from instanceof \DateTimeImmutable ? $from->setTimezone( $timezone ) : new \DateTimeImmutable( 'now', $timezone );
		$limit = max( 1, min( 5, (int) $limit ) );
		if ( 'once' === $config['mode'] ) {
			$once = new \DateTimeImmutable( $config['once_at'], $timezone );
			return $once > $now && self::in_range( $once, $config ) ? array( $once ) : array();
		}
		if ( 'interval' === $config['mode'] ) {
			$seconds = 'minutes' === $config['unit'] ? 60 : ( 'hours' === $config['unit'] ? 3600 : 0 );
			$results = array();
			$cursor = $now;
			if ( $config['start_at'] && new \DateTimeImmutable( $config['start_at'], $timezone ) > $cursor ) {
				$cursor = new \DateTimeImmutable( $config['start_at'], $timezone );
			}
			for ( $index = 0; $index < $limit; $index++ ) {
				$cursor = $seconds ? $cursor->modify( '+' . ( $config['amount'] * $seconds ) . ' seconds' ) : $cursor->modify( '+' . $config['amount'] . ' days' );
				if ( ! self::in_range( $cursor, $config ) ) {
					break;
				}
				$results[] = $cursor;
			}
			return $results;
		}

		$fields = self::fields_for( $config );
		$results = array();
		$first_day = $now->setTime( 0, 0 );
		for ( $offset = 0; $offset <= 366 * 6 && count( $results ) < $limit; $offset++ ) {
			$date = $first_day->modify( '+' . $offset . ' days' );
			if ( ! self::matches_day( $date, $fields ) ) {
				continue;
			}
			foreach ( $fields['hours'] as $hour ) {
				foreach ( $fields['minutes'] as $minute ) {
					$local = $date->format( 'Y-m-d' ) . ' ' . sprintf( '%02d:%02d', $hour, $minute );
					$occurrence = self::resolve_local_occurrence( $local, $timezone );
					if ( ! $occurrence || $occurrence->format( 'Y-m-d H:i' ) !== $local || $occurrence <= $now || ! self::in_range( $occurrence, $config ) ) {
						continue;
					}
					$results[ $occurrence->getTimestamp() ] = $occurrence;
				}
			}
			if ( $config['end_at'] && $date->format( 'Y-m-d' ) > substr( $config['end_at'], 0, 10 ) ) {
				break;
			}
		}
		ksort( $results );
		return array_slice( array_values( $results ), 0, $limit );
	}

	public static function cron_explanation( $expression ) {
		try {
			$fields = self::parse_cron( $expression );
		} catch ( \InvalidArgumentException $error ) {
			return '';
		}
		$days = array();
		foreach ( array( 1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 0 => 'dimanche' ) as $number => $label ) {
			if ( isset( $fields['weekday'][ $number ] ) ) {
				$days[] = $label;
			}
		}
		$minute = $fields['minute_raw'];
		$hour = $fields['hour_raw'];
		if ( '*' === $minute && '*' === $hour && '*' === $fields['dom_raw'] && '*' === $fields['month_raw'] && '*' === $fields['dow_raw'] ) {
			return __( 'Toutes les minutes.', 'dsa' );
		}
		if ( preg_match( '/^\*\/(\d+)$/', $minute, $step ) && '*' === $hour && '*' === $fields['dom_raw'] && '*' === $fields['month_raw'] && '*' === $fields['dow_raw'] ) {
			return sprintf( __( 'Toutes les %d minutes.', 'dsa' ), (int) $step[1] );
		}
		if ( ctype_digit( $minute ) && '*' === $hour && '*' === $fields['dom_raw'] && '*' === $fields['month_raw'] && '*' === $fields['dow_raw'] ) {
			return sprintf( __( 'À la minute %d de chaque heure.', 'dsa' ), (int) $minute );
		}
		if ( ctype_digit( $minute ) && preg_match( '/^\*\/(\d+)$/', $hour, $step ) && '*' === $fields['dom_raw'] && '*' === $fields['month_raw'] && '*' === $fields['dow_raw'] ) {
			return sprintf( __( 'Toutes les %1$d heures, à la minute %2$02d.', 'dsa' ), (int) $step[1], (int) $minute );
		}
		if ( ctype_digit( $minute ) && ctype_digit( $hour ) ) {
			$clock = sprintf( '%02d:%02d', (int) $hour, (int) $minute );
			if ( '*' !== $fields['dom_raw'] && '*' === $fields['dow_raw'] && '*' === $fields['month_raw'] && 1 === count( $fields['dom'] ) ) {
				return sprintf( __( 'Le %1$s de chaque mois à %2$s.', 'dsa' ), reset( $fields['dom'] ), $clock );
			}
			if ( $days && '*' === $fields['dom_raw'] && '*' === $fields['month_raw'] ) {
				return sprintf( __( 'Chaque %1$s à %2$s.', 'dsa' ), implode( ' et ', $days ), $clock );
			}
			if ( '*' === $fields['dom_raw'] && '*' === $fields['dow_raw'] && '*' === $fields['month_raw'] ) {
				return sprintf( __( 'Chaque jour à %s.', 'dsa' ), $clock );
			}
		}
		return sprintf( __( 'Expression cron valide : %s.', 'dsa' ), $expression );
	}

	private static function fields_for( $config ) {
		if ( 'weekly' === $config['mode'] ) {
			$cron_days = array_map( static function ( $day ) { return 0 === $day ? 7 : $day; }, $config['weekdays'] );
			$minute = (int) substr( $config['time'], 3, 2 );
			$hour = (int) substr( $config['time'], 0, 2 );
			return self::parse_cron( sprintf( '%d %d * * %s', $minute, $hour, implode( ',', $cron_days ) ) );
		}
		if ( 'monthly' === $config['mode'] ) {
			$dom = 'weekday' === $config['monthly_type'] ? '*' : ( 'last' === $config['day'] ? 'L' : (string) $config['day'] );
			$dow = 'weekday' === $config['monthly_type'] ? $config['weekday'] . '#' . $config['ordinal'] : '*';
			return self::parse_cron( sprintf( '%d %d %s * %s', (int) substr( $config['time'], 3, 2 ), (int) substr( $config['time'], 0, 2 ), $dom, $dow ), true );
		}
		if ( 'interval' === $config['mode'] ) {
			if ( 'minutes' === $config['unit'] ) {
				return self::parse_cron( '*/' . $config['amount'] . ' * * * *' );
			}
			if ( 'hours' === $config['unit'] ) {
				return self::parse_cron( '0 */' . $config['amount'] . ' * * *' );
			}
			return self::parse_cron( '0 0 */' . $config['amount'] . ' * *' );
		}
		return self::parse_cron( 'daily' === $config['mode'] ? substr( $config['time'], 3, 2 ) . ' ' . substr( $config['time'], 0, 2 ) . ' * * *' : $config['cron'] );
	}

	private static function parse_cron( $expression, $allow_extensions = false ) {
		$parts = preg_split( '/\s+/', trim( (string) $expression ) );
		if ( 5 !== count( $parts ) ) {
			throw new \InvalidArgumentException( 'L’expression cron doit contenir cinq champs.' );
		}
		$minutes = self::parse_field( $parts[0], 0, 59 );
		$hours = self::parse_field( $parts[1], 0, 23 );
		if ( ! $allow_extensions && ( 'L' === strtoupper( $parts[2] ) || false !== strpos( $parts[4], '#' ) ) ) {
			throw new \InvalidArgumentException( 'Extension cron non autorisée.' );
		}
		$dom = 'L' === strtoupper( $parts[2] ) ? array( 'last' => true ) : self::parse_field( $parts[2], 1, 31 );
		$month = self::parse_field( $parts[3], 1, 12 );
		$dow = array();
		if ( preg_match( '/^([0-7])#([1-5])$/', $parts[4], $match ) ) {
			$dow[ 7 === (int) $match[1] ? 0 : (int) $match[1] ] = true;
			$dow['ordinal'] = (int) $match[2];
		} else {
			$dow = self::parse_field( $parts[4], 0, 7 );
			if ( isset( $dow[7] ) ) {
				$dow[0] = true;
				unset( $dow[7] );
			}
		}
		return array(
			'minutes' => array_keys( $minutes ), 'hours' => array_keys( $hours ), 'minute_raw' => $parts[0], 'hour_raw' => $parts[1], 'dom' => $dom, 'month' => $month, 'weekday' => $dow,
			'dom_raw' => $parts[2], 'month_raw' => $parts[3], 'dow_raw' => $parts[4],
		);
	}

	private static function parse_field( $field, $minimum, $maximum ) {
		$values = array();
		foreach ( explode( ',', $field ) as $segment ) {
			$step = 1;
			if ( false !== strpos( $segment, '/' ) ) {
				$pieces = explode( '/', $segment );
				if ( 2 !== count( $pieces ) || ! ctype_digit( $pieces[1] ) || (int) $pieces[1] < 1 ) {
					throw new \InvalidArgumentException( 'Pas cron invalide.' );
				}
				$segment = $pieces[0];
				$step = (int) $pieces[1];
			}
			if ( '*' === $segment ) {
				$start = $minimum;
				$end = $maximum;
			} elseif ( preg_match( '/^(\d+)-(\d+)$/', $segment, $match ) ) {
				$start = (int) $match[1];
				$end = (int) $match[2];
			} elseif ( ctype_digit( $segment ) ) {
				$start = (int) $segment;
				$end = $start;
			} else {
				throw new \InvalidArgumentException( 'Champ cron invalide.' );
			}
			if ( $start < $minimum || $end > $maximum || $start > $end ) {
				throw new \InvalidArgumentException( 'Valeur cron hors limites.' );
			}
			for ( $value = $start; $value <= $end; $value += $step ) {
				$values[ $value ] = true;
			}
		}
		ksort( $values );
		return $values;
	}

	private static function matches_day( $date, $fields ) {
		$month = (int) $date->format( 'n' );
		if ( ! isset( $fields['month'][ $month ] ) ) {
			return false;
		}
		$dom_match = isset( $fields['dom']['last'] )
			? $date->format( 'j' ) === $date->format( 't' )
			: isset( $fields['dom'][ (int) $date->format( 'j' ) ] );
		$dow_match = isset( $fields['weekday']['ordinal'] )
			? (int) $date->format( 'w' ) === (int) key( $fields['weekday'] ) && (int) ceil( (int) $date->format( 'j' ) / 7 ) === $fields['weekday']['ordinal']
			: isset( $fields['weekday'][ (int) $date->format( 'w' ) ] );
		$dom_wild = '*' === $fields['dom_raw'];
		$dow_wild = '*' === $fields['dow_raw'];
		if ( $dom_wild && $dow_wild ) {
			return true;
		}
		if ( $dom_wild ) {
			return $dow_match;
		}
		if ( $dow_wild ) {
			return $dom_match;
		}
		return $dom_match || $dow_match;
	}

	private static function resolve_local_occurrence( $local, $timezone ) {
		$occurrence = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $local, $timezone );
		if ( ! $occurrence || $occurrence->format( 'Y-m-d H:i' ) !== $local ) {
			return false;
		}

		$local_timestamp = gmmktime( (int) substr( $local, 11, 2 ), (int) substr( $local, 14, 2 ), 0, (int) substr( $local, 5, 2 ), (int) substr( $local, 8, 2 ), (int) substr( $local, 0, 4 ) );
		$transitions = $timezone->getTransitions( $local_timestamp - 86400, $local_timestamp + 86400 );
		if ( ! is_array( $transitions ) ) {
			return $occurrence;
		}
		for ( $index = 1; $index < count( $transitions ); $index++ ) {
			$previous_offset = $transitions[ $index - 1 ]['offset'];
			$current = $transitions[ $index ];
			if ( $current['offset'] < $previous_offset ) {
				$repeated_start = $current['ts'] + $current['offset'];
				$repeated_end = $current['ts'] + $previous_offset;
				if ( $local_timestamp >= $repeated_start && $local_timestamp < $repeated_end ) {
					return ( new \DateTimeImmutable( '@' . ( $local_timestamp - $previous_offset ) ) )->setTimezone( $timezone );
				}
			}
		}
		return $occurrence;
	}

	private static function in_range( $date, $config ) {
		$timezone = new \DateTimeZone( $config['timezone'] );
		$stamp = $date->getTimestamp();
		if ( $config['start_at'] && $stamp < ( new \DateTimeImmutable( $config['start_at'], $timezone ) )->getTimestamp() ) {
			return false;
		}
		if ( $config['end_at'] && $stamp > ( new \DateTimeImmutable( $config['end_at'], $timezone ) )->getTimestamp() ) {
			return false;
		}
		return true;
	}

	private static function time_value( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
			throw new \InvalidArgumentException( 'Heure invalide.' );
		}
		return $value;
	}

	private static function integer_value( $value ) {
		if ( is_int( $value ) ) {
			return $value;
		}
		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : null;
	}

	private static function boolean_value( $value ) {
		if ( ! in_array( $value, array( true, false, 1, 0, '1', '0' ), true ) ) {
			throw new \InvalidArgumentException( 'Valeur booléenne invalide.' );
		}
		return true === $value || 1 === $value || '1' === $value;
	}

	private static function date_value( $value, $timezone ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			throw new \InvalidArgumentException( 'Date invalide.' );
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, new \DateTimeZone( $timezone ) );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			throw new \InvalidArgumentException( 'Date inexistante ou invalide dans ce fuseau horaire.' );
		}
		return $value;
	}
}