<?php
/**
 * Sends form submissions to Klaviyo as profiles, optionally subscribing them
 * to email marketing or adding them to a list.
 *
 * One site-wide private API key covers delivery and the list picker. Klaviyo
 * authenticates with a `Klaviyo-API-Key` header and versions its API by a
 * dated `revision` header; there is no OAuth.
 *
 * The email identifies the profile. Klaviyo's built-in profile attributes are
 * offered by name; any other mapped target becomes a custom profile property
 * of that name, since Klaviyo creates properties on first write.
 *
 * @package WPIntegrations\KlaviyoConnector
 */

namespace WPIntegrations\KlaviyoConnector\Integrations\Actions\Klaviyo;

use WPIntegrations\KlaviyoConnector\Delivery\Result;
use WPIntegrations\KlaviyoConnector\Integrations\Action;
use WPIntegrations\KlaviyoConnector\Services\Http;

defined( 'ABSPATH' ) || exit;

final class KlaviyoAction extends Action {

	const BASE_URL = 'https://a.klaviyo.com/api';

	/** The API revision every request is written against. */
	const REVISION = '2026-10-15';

	/** GET /lists returns at most 10 per page; the cap allows 500 lists. */
	const LISTS_PAGE_SIZE = 10;
	const LISTS_MAX_PAGES = 50;

	/** Longest value sent for a text attribute or property. */
	const TEXT_MAX = 1024;

	/**
	 * Mapping target for a consent checkbox: a submission where it is empty
	 * is not sent at all.
	 */
	const CONSENT_TARGET = '_consent';

	/**
	 * Klaviyo has no endpoint that lists custom properties, so they are read
	 * off the most recently updated profiles: this many pages of 100.
	 */
	const PROPERTY_SAMPLE_PAGES = 3;

	/** Prefix of the targets written into the profile's `location` object. */
	const LOCATION_PREFIX = 'location.';

	/** @var Http */
	private $http;

	public function __construct( Http $http ) {
		$this->http = $http;
	}

	public function key(): string {
		return 'klaviyo';
	}

	public function label(): string {
		return __( 'Klaviyo', 'wpintegrations-connector-for-klaviyo' );
	}

	public function global_fields(): array {
		return array(
			array(
				'key'    => 'api_key',
				'label'  => __( 'Klaviyo private API key', 'wpintegrations-connector-for-klaviyo' ),
				'secret' => true,
			),
		);
	}

	/**
	 * Klaviyo creates a custom property on first write, so the mapping accepts
	 * a typed name besides the built-in attributes.
	 */
	public function custom_targets(): bool {
		return true;
	}

	/**
	 * The email, Klaviyo's built-in profile attributes, the consent gate, and
	 * the custom properties the account already uses. A new property is typed
	 * in the editor.
	 */
	public function target_fields(): array {
		$targets = array(
			array(
				'key'      => 'email',
				'label'    => __( 'Email', 'wpintegrations-connector-for-klaviyo' ),
				'required' => true,
			),
		);

		foreach ( $this->attributes() as $key => $label ) {
			$targets[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}

		foreach ( $this->account_properties() as $name ) {
			$targets[] = array(
				'key'   => $name,
				'label' => $name,
				'group' => 'custom',
			);
		}

		$targets[] = array(
			'key'   => self::CONSENT_TARGET,
			'label' => __( 'Consent — only add the person when this is ticked', 'wpintegrations-connector-for-klaviyo' ),
		);

		return $targets;
	}

	/**
	 * The email identifies the profile, so it must be mapped.
	 */
	public function validate_flow( array $field_map, array $settings ): ?string {
		foreach ( $field_map as $pair ) {
			if ( 'email' === strtolower( (string) $pair['target'] ) ) {
				return null;
			}
		}
		return __( 'Map a form field to Email — this integration identifies the Klaviyo profile by it.', 'wpintegrations-connector-for-klaviyo' );
	}

	/**
	 * Per-integration settings. Nothing is declared that execute() does not send.
	 */
	public function flow_fields(): array {
		return array(
			array(
				'key'            => 'list_ids',
				'label'          => __( 'Add to lists', 'wpintegrations-connector-for-klaviyo' ),
				'help'           => __( 'Every person from this form joins these lists. Leave empty to only create or update the profile.', 'wpintegrations-connector-for-klaviyo' ),
				'type'           => 'select',
				'multiple'       => true,
				'options_source' => 'lists',
			),
			array(
				'key'     => 'subscribe',
				'label'   => __( 'Email marketing', 'wpintegrations-connector-for-klaviyo' ),
				'help'    => __( 'Subscribing records email marketing consent. When the list uses double opt-in, Klaviyo emails a confirmation link first and adds the person once they click it.', 'wpintegrations-connector-for-klaviyo' ),
				'type'    => 'select',
				'default' => 'email',
				'options' => array(
					array(
						'value' => 'email',
						'label' => __( 'Subscribe them to email marketing', 'wpintegrations-connector-for-klaviyo' ),
					),
					array(
						'value' => '',
						'label' => __( 'Don’t subscribe — only add them to the list', 'wpintegrations-connector-for-klaviyo' ),
					),
				),
			),
			array(
				'key'     => 'update_existing',
				'label'   => __( 'Update profiles that already exist in Klaviyo', 'wpintegrations-connector-for-klaviyo' ),
				'help'    => __( 'Off: someone already in Klaviyo is left exactly as they are.', 'wpintegrations-connector-for-klaviyo' ),
				'type'    => 'boolean',
				'default' => '1',
			),
		);
	}

	/**
	 * Check the stored key by reading one list, which writes nothing.
	 */
	public function test_connection( array $credentials ): Result {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return Result::failure( __( 'Save your Klaviyo private API key first.', 'wpintegrations-connector-for-klaviyo' ) );
		}

		$response = $this->http->get( self::BASE_URL . '/lists?page%5Bsize%5D=1', $this->headers( $key ) );
		if ( ! $response->ok ) {
			return $this->failure( $response, null, null );
		}

		return Result::success( __( 'Connected to Klaviyo.', 'wpintegrations-connector-for-klaviyo' ) );
	}

	public function discover_options( string $key, array $settings ): array {
		$options = 'lists' === $key ? $this->lists() : null;
		return null === $options ? array() : $options;
	}

	public function execute( object $flow, array $payload ): Result {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return Result::failure( __( 'Klaviyo is not connected — add your private API key under Settings.', 'wpintegrations-connector-for-klaviyo' ) );
		}

		$attributes = $this->attributes();
		$email      = '';
		$profile    = array();
		$location   = array();
		$properties = array();
		$consent    = null;
		$notes      = array();
		foreach ( $this->map_fields( $flow, $payload ) as $target => $value ) {
			$target = trim( (string) $target );
			$value  = trim( (string) $value );
			if ( 'email' === strtolower( $target ) ) {
				$email = $value;
			} elseif ( self::CONSENT_TARGET === $target ) {
				$consent = $value;
			} elseif ( '' === $target || '' === $value ) {
				continue;
			} elseif ( 'phone_number' === $target ) {
				$phone = $this->e164( $value );
				if ( null === $phone ) {
					/* translators: %s: the submitted phone number. */
					$notes[] = sprintf( __( 'Phone “%s” left out: Klaviyo only takes international numbers that start with + and the country code.', 'wpintegrations-connector-for-klaviyo' ), sanitize_text_field( $value ) );
				} else {
					$profile['phone_number'] = $phone;
				}
			} elseif ( isset( $attributes[ $target ] ) && 0 === strpos( $target, self::LOCATION_PREFIX ) ) {
				$location[ substr( $target, strlen( self::LOCATION_PREFIX ) ) ] = $this->text( $value );
			} elseif ( isset( $attributes[ $target ] ) ) {
				$profile[ $target ] = $this->text( $value );
			} else {
				$name = $this->property_name( $target );
				if ( '' !== $name ) {
					$properties[ $name ] = $this->text( $value );
				}
			}
		}

		// A mapped consent box left empty means no sign-up. An unmapped one
		// is no gate at all, so map_fields() never offering it is fine.
		if ( $this->maps_consent( $flow ) && ( null === $consent || '' === $consent ) ) {
			return Result::success( __( 'Not sent: the visitor did not tick the consent box.', 'wpintegrations-connector-for-klaviyo' ) );
		}

		// The mapping has the email, but the visitor may have left it empty.
		if ( '' === $email ) {
			return Result::failure( __( 'This submission has no email, so the Klaviyo profile cannot be identified. Nothing was sent.', 'wpintegrations-connector-for-klaviyo' ) );
		}
		if ( ! is_email( $email ) ) {
			/* translators: %s: the submitted email address. */
			return Result::failure( sprintf( __( '“%s” is not a valid email address, so the profile was not sent.', 'wpintegrations-connector-for-klaviyo' ), sanitize_text_field( $email ) ) );
		}
		$email = sanitize_email( $email );

		$settings = (array) ( $flow->settings ?? array() );
		$list_ids = $this->list_ids( (array) ( $settings['list_ids'] ?? array() ) );
		$opt_in   = 'email' === (string) ( $settings['subscribe'] ?? '' );

		// Klaviyo always updates a known email, so leaving one untouched means
		// asking first.
		if ( empty( $settings['update_existing'] ) ) {
			$known = $this->profile_exists( $email, $key );
			if ( $known instanceof Result ) {
				return $known;
			}
			if ( $known ) {
				return Result::success( __( 'Profile already in Klaviyo — left unchanged, since this integration does not update existing profiles.', 'wpintegrations-connector-for-klaviyo' ) );
			}
		}

		// 1. Create or update the profile.
		$profile = array( 'email' => $email ) + $profile;
		if ( $location ) {
			$profile['location'] = $location;
		}
		if ( $properties ) {
			$profile['properties'] = $properties;
		}
		$body     = array(
			'data' => array(
				'type'       => 'profile',
				'attributes' => $profile,
			),
		);
		$endpoint = self::BASE_URL . '/profile-import';
		$request  = array(
			'endpoint' => $endpoint,
			'payload'  => $body,
		);
		$response = $this->http->post_json( $endpoint, $body, $this->headers( $key ) );
		$logged   = array(
			'code' => $response->code,
			'body' => $response->body,
		);
		if ( ! $response->ok ) {
			return $this->failure( $response, $request, $logged );
		}

		$profile_id = is_array( $response->body ) ? sanitize_text_field( (string) ( $response->body['data']['id'] ?? '' ) ) : '';
		$messages   = array(
			201 === $response->code
				? __( 'Profile created in Klaviyo.', 'wpintegrations-connector-for-klaviyo' )
				: __( 'Profile updated in Klaviyo.', 'wpintegrations-connector-for-klaviyo' ),
		);

		// 2. Subscribe to email marketing, or only add to the lists. Klaviyo
		// takes one list per subscription, so each list is its own call.
		$steps = array();
		if ( $opt_in ) {
			foreach ( $list_ids ? $list_ids : array( '' ) as $list_id ) {
				$steps[] = $this->subscribe( $email, $list_id, $flow, $key );
			}
		} elseif ( $list_ids ) {
			if ( '' === $profile_id ) {
				return Result::failure( __( 'Klaviyo saved the profile but did not return its id, so it could not be added to the lists.', 'wpintegrations-connector-for-klaviyo' ), $request, $logged );
			}
			foreach ( $list_ids as $list_id ) {
				$steps[] = $this->add_to_list( $profile_id, $list_id, $key );
			}
		}

		if ( $steps ) {
			$request = array(
				'profile' => $request,
				'lists'   => array_column( $steps, 'request' ),
			);
			$logged  = array(
				'profile' => $logged,
				'lists'   => array_map(
					static function ( $step ) {
						return array(
							'code' => $step['response']->code,
							'body' => $step['response']->body,
						);
					},
					$steps
				),
			);
			foreach ( $steps as $step ) {
				if ( ! $step['ok'] ) {
					$failure = $this->failure( $step['response'], $request, $logged );
					/* translators: %s: Klaviyo's error message. */
					return Result::failure( sprintf( __( 'The profile was saved, but the list step failed: %s', 'wpintegrations-connector-for-klaviyo' ), $failure->message ), $request, $logged );
				}
			}

			if ( ! $opt_in ) {
				/* translators: %d: number of lists. */
				$messages[] = sprintf( _n( 'Added to %d list.', 'Added to %d lists.', count( $list_ids ), 'wpintegrations-connector-for-klaviyo' ), count( $list_ids ) );
			} elseif ( $list_ids ) {
				/* translators: %d: number of lists. */
				$messages[] = sprintf( _n( 'Subscribed to email marketing on %d list — a double opt-in list emails the confirmation link first.', 'Subscribed to email marketing on %d lists — a double opt-in list emails the confirmation link first.', count( $list_ids ), 'wpintegrations-connector-for-klaviyo' ), count( $list_ids ) );
			} else {
				$messages[] = __( 'Subscribed to email marketing.', 'wpintegrations-connector-for-klaviyo' );
			}
		}

		return Result::success( implode( ' ', array_merge( $messages, $notes ) ), $request, $logged );
	}

	/**
	 * Clean, unique list ids.
	 *
	 * @param array<int, mixed> $ids Stored ids.
	 * @return string[]
	 */
	private function list_ids( array $ids ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', array_filter( $ids, 'is_scalar' ) ) ), 'strlen' ) ) );
	}

	/**
	 * Klaviyo's built-in profile attributes this integration writes, target
	 * key => label. `location.*` keys go into the profile's location object.
	 *
	 * @return array<string, string>
	 */
	private function attributes(): array {
		return array(
			'first_name'        => __( 'First name', 'wpintegrations-connector-for-klaviyo' ),
			'last_name'         => __( 'Last name', 'wpintegrations-connector-for-klaviyo' ),
			'phone_number'      => __( 'Phone number', 'wpintegrations-connector-for-klaviyo' ),
			'organization'      => __( 'Organization', 'wpintegrations-connector-for-klaviyo' ),
			'title'             => __( 'Job title', 'wpintegrations-connector-for-klaviyo' ),
			'external_id'       => __( 'External ID (your own customer ID)', 'wpintegrations-connector-for-klaviyo' ),
			'locale'            => __( 'Locale (e.g. en-US)', 'wpintegrations-connector-for-klaviyo' ),
			'location.address1' => __( 'Address', 'wpintegrations-connector-for-klaviyo' ),
			'location.address2' => __( 'Address line 2', 'wpintegrations-connector-for-klaviyo' ),
			'location.city'     => __( 'City', 'wpintegrations-connector-for-klaviyo' ),
			'location.region'   => __( 'State / region', 'wpintegrations-connector-for-klaviyo' ),
			'location.zip'      => __( 'Zip / postal code', 'wpintegrations-connector-for-klaviyo' ),
			'location.country'  => __( 'Country', 'wpintegrations-connector-for-klaviyo' ),
		);
	}

	/**
	 * POST /profile-subscription-bulk-create-jobs for one profile. With a list,
	 * that list's single or double opt-in setting applies; without one, the
	 * account's default does.
	 *
	 * @param object $flow Flow row, whose title labels the consent record.
	 * @return array{ok:bool, request:array, response:object}
	 */
	private function subscribe( string $email, string $list_id, object $flow, string $key ): array {
		$source = sanitize_text_field( (string) ( $flow->title ?? '' ) );
		$data   = array(
			'type'       => 'profile-subscription-bulk-create-job',
			'attributes' => array(
				'custom_source' => mb_substr( '' !== $source ? $source : 'WordPress form', 0, 200 ),
				'profiles'      => array(
					'data' => array(
						array(
							'type'       => 'profile',
							'attributes' => array(
								'email'         => $email,
								'subscriptions' => array(
									'email' => array(
										'marketing' => array( 'consent' => 'SUBSCRIBED' ),
									),
								),
							),
						),
					),
				),
			),
		);
		if ( '' !== $list_id ) {
			$data['relationships'] = array(
				'list' => array(
					'data' => array(
						'type' => 'list',
						'id'   => $list_id,
					),
				),
			);
		}

		$body     = array( 'data' => $data );
		$endpoint = self::BASE_URL . '/profile-subscription-bulk-create-jobs';
		$response = $this->http->post_json( $endpoint, $body, $this->headers( $key ) );

		return array(
			'ok'       => $response->ok,
			'request'  => array(
				'endpoint' => $endpoint,
				'payload'  => $body,
			),
			'response' => $response,
		);
	}

	/**
	 * POST /lists/{id}/relationships/profiles: list membership with no
	 * marketing consent.
	 *
	 * @return array{ok:bool, request:array, response:object}
	 */
	private function add_to_list( string $profile_id, string $list_id, string $key ): array {
		$body     = array(
			'data' => array(
				array(
					'type' => 'profile',
					'id'   => $profile_id,
				),
			),
		);
		$endpoint = self::BASE_URL . '/lists/' . rawurlencode( $list_id ) . '/relationships/profiles';
		$response = $this->http->post_json( $endpoint, $body, $this->headers( $key ) );

		return array(
			'ok'       => $response->ok,
			'request'  => array(
				'endpoint' => $endpoint,
				'payload'  => $body,
			),
			'response' => $response,
		);
	}

	/**
	 * Whether the integration maps a form field to the consent gate.
	 *
	 * @param object $flow Flow row.
	 */
	private function maps_consent( object $flow ): bool {
		foreach ( (array) $flow->field_map as $pair ) {
			if ( self::CONSENT_TARGET === (string) ( $pair['target'] ?? '' ) && '' !== (string) ( $pair['source'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * A phone number in E.164, which is all Klaviyo accepts; null when the
	 * submitted value cannot be one. Separators are dropped and a leading 00
	 * becomes +, but no country code is guessed.
	 */
	private function e164( string $value ): ?string {
		$phone = (string) preg_replace( '/[\s().\-]/', '', $value );
		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}
		return preg_match( '/^\+[1-9]\d{6,14}$/', $phone ) ? $phone : null;
	}

	/**
	 * A custom property name as typed in the mapping, cleaned of markup.
	 */
	private function property_name( string $target ): string {
		return mb_substr( trim( sanitize_text_field( $target ) ), 0, 100 );
	}

	private function text( string $value ): string {
		return mb_substr( sanitize_text_field( $value ), 0, self::TEXT_MAX );
	}

	/**
	 * Whether Klaviyo already has a profile with this email.
	 *
	 * @return bool|Result A Result when Klaviyo could not answer, so delivery
	 *                     stops rather than guessing.
	 */
	private function profile_exists( string $email, string $key ) {
		$url      = self::BASE_URL . '/profiles?page%5Bsize%5D=1&fields%5Bprofile%5D=email&filter='
			. rawurlencode( 'equals(email,"' . $email . '")' );
		$response = $this->http->get( $url, $this->headers( $key ) );
		if ( $response->ok && is_array( $response->body ) ) {
			return ! empty( $response->body['data'] );
		}
		return $this->failure(
			$response,
			array( 'endpoint' => self::BASE_URL . '/profiles?filter=equals(email,…)' ),
			array(
				'code' => $response->code,
				'body' => $response->body,
			)
		);
	}

	/**
	 * A failed call as a Result, worded from Klaviyo's own reason.
	 *
	 * @param object     $response        Normalized response.
	 * @param array|null $request_excerpt Logged request.
	 * @param array|null $logged          Logged response.
	 */
	private function failure( object $response, $request_excerpt, $logged ): Result {
		if ( 0 === $response->code ) {
			/* translators: %s: transport error message. */
			$message = sprintf( __( 'Could not reach Klaviyo: %s', 'wpintegrations-connector-for-klaviyo' ), $response->error );
		} elseif ( 401 === $response->code ) {
			$message = __( 'Klaviyo rejected the API key. Use a private API key (it starts with pk_), copied whole, that has not been revoked under Settings → API keys.', 'wpintegrations-connector-for-klaviyo' );
		} elseif ( 403 === $response->code ) {
			/* translators: %s: Klaviyo's error detail. */
			$message = sprintf( __( 'The API key is missing a permission this needs — give it Full access to Lists, Profiles and Subscriptions in Klaviyo. (%s)', 'wpintegrations-connector-for-klaviyo' ), $this->detail( $response ) );
		} elseif ( 429 === $response->code ) {
			$message = __( 'Klaviyo’s rate limit was reached, so this submission was not sent.', 'wpintegrations-connector-for-klaviyo' );
		} else {
			/* translators: 1: HTTP status code, 2: error detail from Klaviyo. */
			$message = sprintf( __( 'Klaviyo error (HTTP %1$d): %2$s', 'wpintegrations-connector-for-klaviyo' ), $response->code, $this->detail( $response ) );
		}

		return Result::failure( $message, $request_excerpt, $logged );
	}

	/**
	 * Klaviyo's own reason: the first entry of its `errors` array, with the
	 * attribute it points at.
	 *
	 * @param object $response Normalized response.
	 */
	private function detail( object $response ): string {
		if ( is_array( $response->body ) ) {
			$error   = (array) ( $response->body['errors'][0] ?? array() );
			$detail  = (string) ( $error['detail'] ?? $error['title'] ?? '' );
			$pointer = (string) ( $error['source']['pointer'] ?? '' );
			if ( '' !== $detail && '' !== $pointer ) {
				$detail .= ' (' . preg_replace( '#^/data/attributes/#', '', $pointer ) . ')';
			}
			if ( '' === $detail ) {
				$detail = (string) wp_json_encode( $response->body );
			}
		} else {
			$detail = (string) $response->body;
		}
		return mb_substr( sanitize_text_field( $detail ), 0, 300 );
	}

	/**
	 * The account's lists as select options, all pages.
	 *
	 * @return array<int, array{value:string, label:string}>|null
	 */
	private function lists(): ?array {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return null;
		}

		$cache_key = $this->cache_key( 'lists', $key );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}

		$options = array();
		$url     = self::BASE_URL . '/lists?sort=name&fields%5Blist%5D=name,opt_in_process&page%5Bsize%5D=' . self::LISTS_PAGE_SIZE;
		for ( $page = 1; $page <= self::LISTS_MAX_PAGES && '' !== $url; $page++ ) {
			$response = $this->http->get( $url, $this->headers( $key ) );
			if ( ! $response->ok || ! is_array( $response->body ) ) {
				return $options ? $options : null;
			}

			foreach ( (array) ( $response->body['data'] ?? array() ) as $list ) {
				$label = sanitize_text_field( (string) ( $list['attributes']['name'] ?? '' ) );
				if ( 'double_opt_in' === ( $list['attributes']['opt_in_process'] ?? '' ) ) {
					/* translators: %s: list name. */
					$label = sprintf( __( '%s (double opt-in)', 'wpintegrations-connector-for-klaviyo' ), $label );
				}
				$options[] = array(
					'value' => sanitize_text_field( (string) ( $list['id'] ?? '' ) ),
					'label' => $label,
				);
			}

			// Follow Klaviyo's own cursor link, and only to its own API.
			$next = (string) ( $response->body['links']['next'] ?? '' );
			$url  = 0 === strpos( $next, self::BASE_URL . '/' ) ? $next : '';
		}

		// Short-lived: a list made in Klaviyo a minute ago should show up soon.
		set_transient( $cache_key, $options, 5 * MINUTE_IN_SECONDS );
		return $options;
	}

	/**
	 * Custom property names in use on the account, read off the most recently
	 * updated profiles, sorted by name. Klaviyo's own `$`-prefixed properties
	 * are left out. Empty when there is no key or Klaviyo cannot be read.
	 *
	 * @param bool $refresh Bypass the cache.
	 * @return string[]
	 */
	private function account_properties( bool $refresh = false ): array {
		$key = $this->credential( 'api_key' );
		if ( '' === $key ) {
			return array();
		}

		$cache_key = $this->cache_key( 'props', $key );
		$cached    = $refresh ? false : get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$names = array();
		$url   = self::BASE_URL . '/profiles?page%5Bsize%5D=100&sort=-updated&fields%5Bprofile%5D=properties';
		for ( $page = 1; $page <= self::PROPERTY_SAMPLE_PAGES && '' !== $url; $page++ ) {
			$response = $this->http->get( $url, $this->headers( $key ) );
			if ( ! $response->ok || ! is_array( $response->body ) ) {
				break;
			}
			foreach ( (array) ( $response->body['data'] ?? array() ) as $profile ) {
				foreach ( array_keys( (array) ( $profile['attributes']['properties'] ?? array() ) ) as $name ) {
					$name = $this->property_name( (string) $name );
					if ( '' !== $name && '$' !== $name[0] && ! isset( $this->attributes()[ $name ] ) && 'email' !== $name ) {
						$names[ $name ] = true;
					}
				}
			}
			$next = (string) ( $response->body['links']['next'] ?? '' );
			$url  = 0 === strpos( $next, self::BASE_URL . '/' ) ? $next : '';
		}

		$names = array_keys( $names );
		natcasesort( $names );
		$names = array_values( $names );

		// Short-lived: a property first written a minute ago should show up soon.
		set_transient( $cache_key, $names, 10 * MINUTE_IN_SECONDS );
		return $names;
	}

	/**
	 * Cache keys carry a hash of the key, so switching accounts never serves
	 * the old account's lists.
	 */
	private function cache_key( string $kind, string $api_key ): string {
		return 'wpi_klaviyo_api_' . $kind . '_' . substr( md5( $api_key ), 0, 12 );
	}

	/**
	 * @return array<string, string>
	 */
	private function headers( string $api_key ): array {
		return array(
			'Authorization' => 'Klaviyo-API-Key ' . $api_key,
			'Accept'        => 'application/vnd.api+json',
			'Content-Type'  => 'application/vnd.api+json',
			'revision'      => self::REVISION,
		);
	}
}
