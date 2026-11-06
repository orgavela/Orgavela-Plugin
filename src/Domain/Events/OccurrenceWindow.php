<?php
/**
 * Explicit local time plus IANA timezone, stored as UTC.
 *
 * @package UOP
 */

namespace UOP\Domain\Events;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** The explicit offset disambiguates autumn DST folds. */
final readonly class OccurrenceWindow {
	/**
	 * Normalize two offset-qualified local instants to UTC.
	 *
	 * @param string $start Local ISO-8601 including its explicit numeric offset.
	 * @param string $end   Local ISO-8601 including its explicit numeric offset.
	 * @param string $zone  IANA timezone identity.
	 * @throws InvalidArgumentException When invalid, nonexistent or reversed.
	 */
	public function __construct( public string $start, public string $end, public string $zone ) {
		$this->parse( $start, $zone );
		$this->parse( $end, $zone );
		if ( $this->as_utc( $end ) <= $this->as_utc( $start ) ) {
			throw new InvalidArgumentException( 'Occurrence must end after it begins.' );
		}
	}

	/**
	 * Return the explicit UTC start for persistence.
	 *
	 * @return string
	 */
	public function start_utc(): string {
		return $this->as_utc( $this->start )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Return the explicit UTC end for persistence.
	 *
	 * @return string
	 */
	public function end_utc(): string {
		return $this->as_utc( $this->end )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Interpret an offset-qualified ISO8601 timestamp at UTC.
	 *
	 * @param string $input Explicit local offset-qualified timestamp.
	 * @return DateTimeImmutable
	 */
	private function as_utc( string $input ): DateTimeImmutable {
		return ( new DateTimeImmutable( $input ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Validate the offset against the IANA timezone for the same instant.
	 *
	 * @param string $input Offset-qualified local instant.
	 * @param string $zone  IANA timezone name.
	 * @throws InvalidArgumentException If the civil time is nonexistent.
	 */
	private function parse( string $input, string $zone ): void {
		if ( ! in_array( $zone, DateTimeZone::listIdentifiers( DateTimeZone::ALL_WITH_BC ), true )
			|| ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[+-][0-9]{2}:[0-9]{2}$/D', $input ) ) {
			throw new InvalidArgumentException( 'An IANA zone and explicit local offset are required.' );
		}
		try {
			$utc   = $this->as_utc( $input );
			$local = $utc->setTimezone( new DateTimeZone( $zone ) );
		} catch ( \Exception ) {
			throw new InvalidArgumentException( 'Invalid event instant.' );
		}
		if ( $local->format( 'Y-m-d\TH:i:sP' ) !== $input ) {
			throw new InvalidArgumentException( 'Local event time does not exist in that zone or offset.' );
		}
	}
}
