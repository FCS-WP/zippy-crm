<?php
/**
 * Documentation manifest. Source of truth for what shows up in the in-product
 * Documentation page sidebar, and in what order.
 *
 * Each entry:
 *   - slug:    URL fragment (?doc=...) and anchor id. Lowercase, hyphenated.
 *   - title:   Sidebar label + page heading.
 *   - file:    Filename inside docs/guide/ (rendered as markdown).
 *   - group:   Section label for the sidebar grouping.
 *   - hidden:  (optional bool) When true, the entry is reachable via direct
 *              URL (?doc=slug) but doesn't appear in the sidebar. Use for
 *              audience-restricted pages (e.g. dev-only references) you want
 *              to share via link rather than expose to all admins.
 *
 * Adding a new page = drop a {slug}.md in docs/guide/ and add an entry below.
 * Order is preserved.
 */

defined( 'ABSPATH' ) || exit;

return [
	// --- Getting started ---
	[
		'slug'  => 'overview',
		'title' => 'What Zippy CRM does',
		'file'  => '00-overview.md',
		'group' => 'Getting started',
	],
	[
		'slug'  => 'first-steps',
		'title' => 'First steps after install',
		'file'  => '05-first-steps.md',
		'group' => 'Getting started',
	],

	// --- Features ---
	[
		'slug'  => 'membership-tiers',
		'title' => 'Membership tiers',
		'file'  => '10-membership-tiers.md',
		'group' => 'Features',
	],
	[
		'slug'  => 'points',
		'title' => 'Points (earn & redeem)',
		'file'  => '20-points.md',
		'group' => 'Features',
	],
	[
		'slug'  => 'vouchers',
		'title' => 'Vouchers',
		'file'  => '30-vouchers.md',
		'group' => 'Features',
	],
	[
		'slug'  => 'notifications',
		'title' => 'Notifications',
		'file'  => '40-notifications.md',
		'group' => 'Features',
	],
	[
		'slug'  => 'audit-log',
		'title' => 'Audit log',
		'file'  => '50-audit-log.md',
		'group' => 'Features',
	],

	// --- Reference ---
	[
		'slug'  => 'settings',
		'title' => 'Settings reference',
		'file'  => '60-settings.md',
		'group' => 'Reference',
	],
	[
		'slug'  => 'troubleshooting',
		'title' => 'Troubleshooting & FAQ',
		'file'  => '70-troubleshooting.md',
		'group' => 'Reference',
	],

	// --- Dev notes ---
	// Hidden from the sidebar by default — share via direct link
	// (?page=zippy-crm-docs&doc=dev-notes) when needed.
	[
		'slug'   => 'dev-notes',
		'title'  => 'Architecture & integrations',
		'file'   => '80-dev-notes.md',
		'group'  => 'Dev notes',
		'hidden' => true,
	],
];
