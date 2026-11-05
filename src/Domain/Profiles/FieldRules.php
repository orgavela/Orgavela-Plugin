<?php
/**
 * Frozen profile field rules and typed scalar validation.
 *
 * @package UOP
 */

namespace UOP\Domain\Profiles;

use InvalidArgumentException;

/** Profile field configuration and value validation share one allowlist. */
final class FieldRules {
	/**
	 * Return the V1 field palette.
	 *
	 * @return list<string>
	 */
	public static function types(): array {
		return array( 'text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'radio', 'checkbox', 'multiselect', 'consent' );
	}

	/**
	 * Validate all author-controlled field properties.
	 *
	 * @param array<string, mixed> $definition Strict definition object.
	 * @throws InvalidArgumentException When an unknown or incompatible rule appears.
	 */
	public static function validate( array $definition ): void {
		$allowed = array( 'key', 'type', 'label', 'sensitivity', 'subject_view', 'subject_edit', 'delegate_view', 'delegate_edit', 'privacy_purpose', 'lawful_basis_note', 'retention_class', 'options' );
		if ( array_diff( array_keys( $definition ), $allowed ) ) {
			throw new InvalidArgumentException( 'Unknown field definition attribute.' );
		}
		$key   = $definition['key'] ?? null;
		$type  = $definition['type'] ?? null;
		$label = $definition['label'] ?? null;
		if ( ! is_string( $key ) || ! preg_match( '/^[a-z][a-z0-9_]{0,99}$/D', $key )
			|| ! is_string( $type ) || ! in_array( $type, self::types(), true )
			|| ! is_string( $label ) || '' === trim( $label ) || mb_strlen( $label ) > 191 ) {
			throw new InvalidArgumentException( 'Invalid profile field identity.' );
		}
		$sensitivity = $definition['sensitivity'] ?? 'personal';
		if ( ! is_string( $sensitivity ) || ! in_array( $sensitivity, array( 'public', 'internal', 'personal', 'sensitive', 'medical' ), true ) ) {
			throw new InvalidArgumentException( 'Unknown sensitivity classification.' );
		}
		foreach ( array( 'subject_view', 'subject_edit', 'delegate_view', 'delegate_edit' ) as $flag ) {
			if ( isset( $definition[ $flag ] ) && ! is_bool( $definition[ $flag ] ) ) {
				throw new InvalidArgumentException( 'Field policy flags must be boolean.' );
			}
		}
		foreach ( array(
			'privacy_purpose'   => 255,
			'lawful_basis_note' => 2000,
			'retention_class'   => 64,
		) as $property => $limit ) {
			if ( isset( $definition[ $property ] ) && ( ! is_string( $definition[ $property ] ) || mb_strlen( $definition[ $property ] ) > $limit ) ) {
				throw new InvalidArgumentException( 'Invalid privacy metadata.' );
			}
		}
		$choices = $definition['options'] ?? array();
		if ( ! is_array( $choices ) || count( $choices ) > 100 || ( ! in_array( $type, array( 'select', 'radio', 'multiselect' ), true ) && $choices ) ) {
			throw new InvalidArgumentException( 'Invalid options for the field type.' );
		}
		if ( in_array( $type, array( 'select', 'radio', 'multiselect' ), true ) && ! $choices ) {
			throw new InvalidArgumentException( 'Choice fields require options.' );
		}
		$seen = array();
		foreach ( $choices as $choice ) {
			if ( ! is_string( $choice ) || '' === $choice || strlen( $choice ) > 100 || isset( $seen[ $choice ] ) ) {
				throw new InvalidArgumentException( 'Options must be unique short strings.' );
			}
			$seen[ $choice ] = true;
		}
	}

	/**
	 * Normalize user input to exact database slots, never a lossy string cast.
	 *
	 * @param string $type    Frozen V1 type.
	 * @param mixed  $value   Raw typed input; null clears the field.
	 * @param array  $choices Validated choice values.
	 * @phpstan-param list<string> $choices
	 * @return list<array{slot:string,value:string|int,ordinal:int}>
	 * @throws InvalidArgumentException For invalid value type or representation.
	 */
	public static function normalize( string $type, mixed $value, array $choices = array() ): array {
		if ( ! in_array( $type, self::types(), true ) || 'consent' === $type ) {
			throw new InvalidArgumentException( 'Consent is recorded separately, not as a profile value.' );
		}
		if ( null === $value ) {
			return array();
		}
		if ( 'multiselect' === $type ) {
			if ( ! is_array( $value ) || count( $value ) > 100 || count( $value ) !== count( array_unique( $value, SORT_REGULAR ) ) ) {
				throw new InvalidArgumentException( 'Invalid multiselect values.' );
			}
			$result = array();
			foreach ( array_values( $value ) as $ordinal => $item ) {
				$normalized = self::normalize( 'select', $item, $choices );
				$result[]   = array(
					'slot'    => 'value_string',
					'value'   => $normalized[0]['value'],
					'ordinal' => $ordinal,
				);
			}
			return $result;
		}
		if ( 'checkbox' === $type ) {
			if ( ! is_bool( $value ) ) {
				throw new InvalidArgumentException( 'Checkbox must be boolean.' );
			}
			return array(
				array(
					'slot'    => 'value_boolean',
					'value'   => $value ? 1 : 0,
					'ordinal' => 0,
				),
			);
		}
		if ( 'number' === $type ) {
			if ( ! is_int( $value ) && ! is_float( $value ) && ! is_string( $value ) ) {
				throw new InvalidArgumentException( 'Number must be numeric.' );
			}
			$number = (string) $value;
			if ( ! preg_match( '/^-?(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,6})?$/D', $number ) ) {
				throw new InvalidArgumentException( 'Number exceeds fixed decimal precision.' );
			}
			return array(
				array(
					'slot'    => 'value_decimal',
					'value'   => $number,
					'ordinal' => 0,
				),
			);
		}
		if ( ! is_string( $value ) ) {
			throw new InvalidArgumentException( 'Text-like input must be a string.' );
		}
		if ( 'date' === $type ) {
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
				throw new InvalidArgumentException( 'Invalid calendar date.' );
			}
			return array(
				array(
					'slot'    => 'value_date',
					'value'   => $value,
					'ordinal' => 0,
				),
			);
		}
		$limit = 'textarea' === $type ? 10000 : 191;
		if ( mb_strlen( $value ) > $limit || ( 'email' === $type && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) )
			|| ( 'phone' === $type && ! preg_match( '/^[+0-9 ()\/-]{3,40}$/D', $value ) )
			|| ( in_array( $type, array( 'select', 'radio' ), true ) && ! in_array( $value, $choices, true ) ) ) {
			throw new InvalidArgumentException( 'Invalid profile field value.' );
		}
		return array(
			array(
				'slot'    => 'textarea' === $type ? 'value_text' : 'value_string',
				'value'   => $value,
				'ordinal' => 0,
			),
		);
	}
}
