<?php
namespace ZippyCrm\Models;

use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for crm_memberships. Reads/writes only — business logic
 * (tier evaluation, multipliers) lives in MembershipService.
 *
 * Hot path: find_by_user is called on most authenticated WC requests.
 * Cache it at the service layer, not here.
 */
final class Membership {

	public const TABLE = 'crm_memberships';

	public const LEVELS = [ 'free', 'silver', 'gold', 'vip' ];
	public const STATUSES = [ 'active', 'suspended', 'expired' ];

	public const MULTIPLIERS = [
		'free'   => 1.0,
		'silver' => 1.2,
		'gold'   => 1.5,
		'vip'    => 2.0,
	];

	public const LABELS = [
		'free'   => 'Free',
		'silver' => 'Silver',
		'gold'   => 'Gold',
		'vip'    => 'VIP',
	];

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_user( int $user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, user_id, membership_level, status, joined_at, expires_at
				 FROM ' . self::table() . ' WHERE user_id = %d',
				$user_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function create( int $user_id, string $level = 'free' ): bool {
		global $wpdb;
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'free';
		}
		$inserted = $wpdb->insert(
			self::table(),
			[
				'user_id'          => $user_id,
				'membership_level' => $level,
				'status'           => 'active',
				'joined_at'        => DateTimeHelper::now_mysql(),
			],
			[ '%d', '%s', '%s', '%s' ]
		);
		return $inserted !== false;
	}

	public static function update_level( int $user_id, string $level ): bool {
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[ 'membership_level' => $level ],
			[ 'user_id' => $user_id ],
			[ '%s' ],
			[ '%d' ]
		);
		return $updated !== false;
	}

	public static function update_status( int $user_id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[ 'status' => $status ],
			[ 'user_id' => $user_id ],
			[ '%s' ],
			[ '%d' ]
		);
		return $updated !== false;
	}

	public static function delete_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}
}
