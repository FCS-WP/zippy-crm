<?php
/**
 * Admin Docs page template.
 *
 * Variables in scope (set by DocsController::render):
 *   $manifest array of doc entries
 *   $active   array — the currently-selected doc entry
 *   $html     string — rendered HTML for the active doc
 *
 * Styling is hand-rolled in the inline <style> block below — pure CSS using
 * the same color tokens (sky, emerald, amber, rose, fuchsia, zinc) the React
 * panels use, plus the Inter @font-face that's already loaded via admin.css.
 *
 * The Inter font itself loads via the admin Tailwind bundle (Assets::enqueue_admin
 * fires on every zippy-crm* page). We just have to *apply* it to the docs shell
 * — admin.css declares the font but doesn't set body { font-family } on a static
 * page (that's done by Tailwind's font utility on React-rendered chrome).
 */

defined( 'ABSPATH' ) || exit;

use ZippyCrm\Controllers\Admin\DocsController;

$grouped  = DocsController::grouped();
$base_url = admin_url( 'admin.php?page=' . DocsController::MENU_SLUG );

// Active group → accent color hue. Drives the hero strip, sidebar accent,
// and content-card top border so each section feels visually distinct.
$accent_by_group = [
	'Getting started' => 'emerald',
	'Features'        => 'sky',
	'Reference'       => 'fuchsia',
	'Dev notes'       => 'amber',
];
$accent = $accent_by_group[ $active['group'] ] ?? 'sky';

// Hex stops per accent (50, 100, 500, 600, 700, 900) — matches Tailwind defaults.
$palette = [
	'sky'     => [ '#f0f9ff', '#e0f2fe', '#0ea5e9', '#0284c7', '#0369a1', '#0c4a6e' ],
	'emerald' => [ '#ecfdf5', '#d1fae5', '#10b981', '#059669', '#047857', '#064e3b' ],
	'fuchsia' => [ '#fdf4ff', '#fae8ff', '#d946ef', '#c026d3', '#a21caf', '#701a75' ],
	'amber'   => [ '#fffbeb', '#fef3c7', '#f59e0b', '#d97706', '#b45309', '#78350f' ],
];
[ $a50, $a100, $a500, $a600, $a700, $a900 ] = $palette[ $accent ] ?? $palette['sky'];
?>
<style>
	/* Inter font for the docs shell. The @font-face is already loaded by
	   admin.css; we just apply it here. Falls back gracefully on systems
	   that haven't fetched the woff2 yet. */
	.zc-docs-shell, .zc-docs-shell * {
		font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		box-sizing: border-box;
	}
	.zc-docs-shell h1, .zc-docs-shell h2, .zc-docs-shell h3, .zc-docs-shell h4, .zc-docs-shell p { margin: 0; }

	/* === Page chrome === */
	.zc-docs-shell {
		margin: 16px 20px 32px 0;
		color: #3f3f46;
		font-feature-settings: "cv11", "ss01"; /* Inter alternate forms — friendlier numerals + letters */
	}

	/* === Hero card === */
	.zc-docs-hero {
		overflow: hidden;
		border-radius: 12px;
		border: 1px solid #e4e4e7;
		background: linear-gradient(135deg, #ffffff 0%, <?php echo esc_attr( $a50 ); ?> 100%);
		box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 1px 2px rgba(0,0,0,.06);
		margin-bottom: 24px;
		position: relative;
	}
	.zc-docs-hero::before {
		content: "";
		position: absolute;
		top: 0; left: 0; right: 0;
		height: 4px;
		background: linear-gradient(to right, <?php echo esc_attr( $a700 ); ?>, <?php echo esc_attr( $a500 ); ?>, <?php echo esc_attr( $a700 ); ?>);
	}
	.zc-docs-hero-body { padding: 28px 32px; display: flex; align-items: center; gap: 20px; }
	.zc-docs-hero-icon {
		flex-shrink: 0;
		width: 56px;
		height: 56px;
		border-radius: 12px;
		background: <?php echo esc_attr( $a100 ); ?>;
		color: <?php echo esc_attr( $a700 ); ?>;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.zc-docs-hero-eyebrow {
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: .14em;
		color: <?php echo esc_attr( $a700 ); ?>;
		font-weight: 600;
		margin-bottom: 4px;
	}
	.zc-docs-hero-title {
		font-size: 26px;
		font-weight: 700;
		color: #18181b;
		line-height: 1.15;
		letter-spacing: -.01em;
	}
	.zc-docs-hero-tagline {
		margin-top: 6px;
		font-size: 14px;
		color: #52525b;
		max-width: 720px;
		line-height: 1.5;
	}

	/* === Layout === */
	.zc-docs-grid { display: grid; grid-template-columns: 260px 1fr; gap: 24px; align-items: start; }
	@media (max-width: 900px) { .zc-docs-grid { grid-template-columns: 1fr; } }

	/* === Sidebar === */
	.zc-docs-side {
		background: #ffffff;
		border: 1px solid #e4e4e7;
		border-radius: 12px;
		padding: 12px 0;
		box-shadow: 0 1px 2px rgba(0,0,0,.04);
		position: sticky;
		top: 42px;
	}
	.zc-docs-side-group + .zc-docs-side-group {
		margin-top: 18px;
		padding-top: 18px;
		border-top: 1px solid #e4e4e7;
		position: relative;
	}
	/* A short accent-colored tick on the divider matching the next group's
	   color — gives each section a visual handle without a heavy hr. */
	.zc-docs-side-group + .zc-docs-side-group::before {
		content: "";
		position: absolute;
		top: -1px;
		left: 16px;
		width: 24px;
		height: 2px;
		background: currentColor;
		border-radius: 2px;
		opacity: .6;
	}
	.zc-docs-side-group.accent-emerald { color: #10b981; }
	.zc-docs-side-group.accent-sky     { color: #0ea5e9; }
	.zc-docs-side-group.accent-fuchsia { color: #d946ef; }
	.zc-docs-side-group.accent-amber   { color: #f59e0b; }

	.zc-docs-side-label {
		font-size: 10.5px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: .12em;
		color: #3f3f46;
		margin: 6px 16px 8px;
		display: flex;
		align-items: center;
		gap: 8px;
	}
	.zc-docs-side-dot {
		display: inline-block;
		width: 6px;
		height: 6px;
		border-radius: 999px;
	}
	.zc-docs-side-dot.dot-emerald { background: #10b981; }
	.zc-docs-side-dot.dot-sky     { background: #0ea5e9; }
	.zc-docs-side-dot.dot-fuchsia { background: #d946ef; }
	.zc-docs-side-dot.dot-amber   { background: #f59e0b; }
	.zc-docs-side ul { margin: 0; padding: 0; list-style: none; }
	.zc-docs-side li { margin: 0; }
	.zc-docs-nav-link {
		display: block;
		padding: 9px 16px;
		border-left: 3px solid transparent;
		color: #52525b;
		text-decoration: none;
		font-size: 13.5px;
		line-height: 1.4;
		transition: background-color .12s, color .12s, border-color .12s;
	}
	.zc-docs-nav-link:hover { background-color: #fafafa; color: #18181b; }
	.zc-docs-nav-link.is-active {
		background-color: <?php echo esc_attr( $a50 ); ?>;
		color: <?php echo esc_attr( $a900 ); ?>;
		border-left-color: <?php echo esc_attr( $a500 ); ?>;
		font-weight: 600;
	}
	/* Hide click-focus ring; keep keyboard focus visible for a11y. */
	.zc-docs-nav-link:focus { outline: none; box-shadow: none; }
	.zc-docs-nav-link:focus:not(:focus-visible) { outline: none; box-shadow: none; }
	.zc-docs-nav-link:focus-visible { outline: 2px solid <?php echo esc_attr( $a500 ); ?>; outline-offset: -2px; }

	/* === Content card === */
	.zc-docs-main {
		background: #ffffff;
		border: 1px solid #e4e4e7;
		border-radius: 12px;
		padding: 36px 44px;
		min-width: 0;
		box-shadow: 0 1px 2px rgba(0,0,0,.04);
		position: relative;
		overflow: hidden;
	}
	.zc-docs-main::before {
		content: "";
		position: absolute;
		top: 0; left: 0; right: 0;
		height: 3px;
		background: linear-gradient(to right, <?php echo esc_attr( $a500 ); ?>, <?php echo esc_attr( $a600 ); ?>);
		opacity: 0.6;
	}
	.zc-docs-breadcrumb {
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: .14em;
		color: <?php echo esc_attr( $a700 ); ?>;
		margin-bottom: 12px;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 4px 10px;
		background: <?php echo esc_attr( $a50 ); ?>;
		border-radius: 999px;
	}

	/* === Markdown prose === */
	.zc-docs-prose { color: #3f3f46; font-size: 14.5px; line-height: 1.7; }
	/* Sibling spacing. !important because wp-admin resets margins on
	   table/pre/ul/p with selectors that beat ours on specificity. */
	.zc-docs-prose > * + * { margin-top: 18px !important; }
	.zc-docs-prose > p,
	.zc-docs-prose > ul,
	.zc-docs-prose > ol,
	.zc-docs-prose > pre,
	.zc-docs-prose > table,
	.zc-docs-prose > blockquote { margin-bottom: 0 !important; }
	/* Block elements need a touch more breathing room than text-to-text. */
	.zc-docs-prose > pre + *,
	.zc-docs-prose > table + *,
	.zc-docs-prose > ul + *,
	.zc-docs-prose > ol + *,
	.zc-docs-prose > blockquote + * { margin-top: 20px !important; }
	.zc-docs-prose h1 {
		font-size: 28px;
		font-weight: 700;
		color: #18181b;
		line-height: 1.15;
		letter-spacing: -.015em;
		margin-top: 0;
	}
	.zc-docs-prose h1 + p { margin-top: 12px; font-size: 16px; color: #52525b; line-height: 1.55; }
	.zc-docs-prose h2 {
		font-size: 19px;
		font-weight: 600;
		color: #18181b;
		line-height: 1.3;
		margin-top: 36px;
		padding-bottom: 8px;
		border-bottom: 1px solid #e4e4e7;
		letter-spacing: -.005em;
	}
	/* Headings need extra breathing room — visual weight + h2's bottom
	   border eat perceived space. */
	.zc-docs-prose h2 + * { margin-top: 24px; }
	.zc-docs-prose h3 {
		font-size: 15.5px;
		font-weight: 600;
		color: #27272a;
		line-height: 1.3;
		margin-top: 24px;
	}
	.zc-docs-prose h3 + * { margin-top: 10px; }
	.zc-docs-prose h4 {
		font-size: 12.5px;
		font-weight: 700;
		color: #52525b;
		line-height: 1.3;
		margin-top: 20px;
		text-transform: uppercase;
		letter-spacing: .06em;
	}
	.zc-docs-prose h4 + * { margin-top: 8px; }
	.zc-docs-prose p { color: #3f3f46; }
	.zc-docs-prose strong { color: #18181b; font-weight: 600; }
	.zc-docs-prose em { font-style: italic; color: #3f3f46; }

	.zc-docs-prose .zc-doc-link {
		color: <?php echo esc_attr( $a700 ); ?>;
		text-decoration: underline;
		text-decoration-color: <?php echo esc_attr( $a500 ); ?>;
		text-decoration-thickness: 1px;
		text-underline-offset: 3px;
		transition: text-decoration-color .15s, color .15s;
		font-weight: 500;
	}
	.zc-docs-prose .zc-doc-link:hover { color: <?php echo esc_attr( $a900 ); ?>; text-decoration-color: <?php echo esc_attr( $a700 ); ?>; }

	.zc-docs-prose .zc-doc-ul, .zc-docs-prose .zc-doc-ol { padding-left: 24px; }
	.zc-docs-prose .zc-doc-ul { list-style: disc; }
	.zc-docs-prose .zc-doc-ol { list-style: decimal; }
	.zc-docs-prose .zc-doc-ul li, .zc-docs-prose .zc-doc-ol li { margin-top: 6px; padding-left: 4px; }
	.zc-docs-prose .zc-doc-ul li::marker { color: <?php echo esc_attr( $a500 ); ?>; }
	.zc-docs-prose .zc-doc-ol li::marker { color: <?php echo esc_attr( $a700 ); ?>; font-weight: 700; }

	.zc-docs-prose .zc-doc-code {
		font-family: ui-monospace, "SF Mono", "JetBrains Mono", Consolas, Monaco, monospace;
		font-size: 13px;
		padding: 2px 7px;
		background: <?php echo esc_attr( $a50 ); ?>;
		border: 1px solid <?php echo esc_attr( $a100 ); ?>;
		border-radius: 5px;
		color: <?php echo esc_attr( $a900 ); ?>;
		font-weight: 500;
	}
	.zc-docs-prose .zc-doc-pre {
		background: #1e1e2e;
		color: #cdd6f4;
		padding: 18px 20px;
		border-radius: 10px;
		overflow-x: auto;
		font-size: 13px;
		line-height: 1.6;
		border: 1px solid #313244;
		box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
	}
	.zc-docs-prose .zc-doc-pre code {
		font-family: ui-monospace, "SF Mono", "JetBrains Mono", Consolas, Monaco, monospace;
		background: none;
		border: 0;
		padding: 0;
		color: inherit;
		font-size: inherit;
	}

	.zc-docs-prose .zc-doc-quote {
		border-left: 4px solid <?php echo esc_attr( $a500 ); ?>;
		background: <?php echo esc_attr( $a50 ); ?>;
		margin: 0;
		padding: 14px 18px;
		border-radius: 0 8px 8px 0;
		color: <?php echo esc_attr( $a900 ); ?>;
		font-style: italic;
	}
	.zc-docs-prose .zc-doc-quote p { margin: 0; }

	.zc-docs-prose .zc-doc-table {
		border-collapse: separate;
		border-spacing: 0;
		width: 100%;
		border: 1px solid #e4e4e7;
		border-radius: 10px;
		overflow: hidden;
		box-shadow: 0 1px 2px rgba(0,0,0,.03);
	}
	.zc-docs-prose .zc-doc-table th {
		background: linear-gradient(to bottom, #fafafa, #f4f4f5);
		font-weight: 600;
		color: #18181b;
		text-align: left;
		padding: 11px 16px;
		font-size: 12.5px;
		text-transform: uppercase;
		letter-spacing: .04em;
		border-bottom: 1px solid #e4e4e7;
	}
	.zc-docs-prose .zc-doc-table td {
		padding: 12px 16px;
		font-size: 13.5px;
		vertical-align: top;
		border-bottom: 1px solid #f4f4f5;
		color: #3f3f46;
	}
	.zc-docs-prose .zc-doc-table tr:last-child td { border-bottom: 0; }
	.zc-docs-prose .zc-doc-table tr:hover td { background-color: <?php echo esc_attr( $a50 ); ?>; transition: background-color .12s; }

	.zc-docs-prose .zc-doc-hr { border: 0; border-top: 1px solid #e4e4e7; margin: 36px 0; }
</style>

<div class="zc-docs-shell">

	<!-- Hero header — accent color shifts per section group -->
	<div class="zc-docs-hero">
		<div class="zc-docs-hero-body">
			<div class="zc-docs-hero-icon">
				<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>
				</svg>
			</div>
			<div style="min-width: 0;">
				<p class="zc-docs-hero-eyebrow"><?php esc_html_e( 'Zippy CRM Documentation', 'zippy-crm' ); ?></p>
				<h1 class="zc-docs-hero-title"><?php echo esc_html( $active['title'] ); ?></h1>
				<p class="zc-docs-hero-tagline">
					<?php esc_html_e( 'Reference for everyone managing the CRM. Browse the sections in the sidebar, or use Cmd/Ctrl+F to search this page.', 'zippy-crm' ); ?>
				</p>
			</div>
		</div>
	</div>

	<div class="zc-docs-grid">

		<!-- Sidebar nav -->
		<aside class="zc-docs-side" aria-label="<?php esc_attr_e( 'Documentation navigation', 'zippy-crm' ); ?>">
			<?php foreach ( $grouped as $group => $entries ) :
				$group_accent = $accent_by_group[ $group ] ?? 'sky';
			?>
				<div class="zc-docs-side-group accent-<?php echo esc_attr( $group_accent ); ?>">
					<p class="zc-docs-side-label">
						<span class="zc-docs-side-dot dot-<?php echo esc_attr( $group_accent ); ?>"></span>
						<?php echo esc_html( $group ); ?>
					</p>
					<ul>
						<?php foreach ( $entries as $entry ) : ?>
							<li>
								<a
									href="<?php echo esc_url( $base_url . '&doc=' . $entry['slug'] ); ?>"
									class="zc-docs-nav-link <?php echo $entry['slug'] === $active['slug'] ? 'is-active' : ''; ?>"
									<?php if ( $entry['slug'] === $active['slug'] ) echo 'aria-current="page"'; ?>
								>
									<?php echo esc_html( $entry['title'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</aside>

		<!-- Content card -->
		<main class="zc-docs-main">
			<span class="zc-docs-breadcrumb">
				<?php echo esc_html( $active['group'] ); ?>
			</span>
			<div class="zc-docs-prose">
				<?php
				// $html is already wp_kses_post()-filtered in DocsController::render_doc.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $html;
				?>
			</div>
		</main>

	</div>
</div>
