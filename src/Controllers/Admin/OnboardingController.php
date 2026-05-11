<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden admin page that renders the first-run guide. Page slug is
 * `zippy-crm-onboarding`; it has no sidebar entry (AdminMenu registers it
 * under a null parent so it exists at `?page=zippy-crm-onboarding` but
 * doesn't pollute the left nav).
 *
 * Hidden by design — admins only land here via:
 *   1. Auto-redirect on first plugin activation (see AdminMenu::maybe_redirect_on_activation)
 *   2. The "View setup guide" link in the Settings panel header (Phase 3)
 *
 * Stub mount point only — React (OnboardingPanel.jsx) owns the rendering.
 */
final class OnboardingController {
	public static function render(): void {
		echo '<div id="zippy-crm-admin-onboarding" class="zippy-crm-onboarding-page"></div>';
	}
}
