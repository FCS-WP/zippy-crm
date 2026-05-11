<?php
namespace ZippyCrm\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Tiny markdown-to-HTML renderer for in-product documentation pages.
 *
 * Why hand-rolled instead of a Composer library:
 *   - This plugin doesn't use Composer (PSR-4 autoload is hand-rolled in
 *     Plugin::register_autoloader). Pulling in Parsedown or league/commonmark
 *     would introduce a Composer dependency for one feature.
 *   - The docs guide uses a small, predictable markdown subset — fenced code,
 *     inline code, headings, paragraphs, lists, links, bold/italic, tables,
 *     blockquotes. That's ~150 lines of PHP, easy to audit, no external CVE
 *     surface.
 *
 * Output is escaped where it matters (raw HTML is NOT passed through). The
 * rendered HTML is then run through wp_kses_post() by the caller as a final
 * defensive pass.
 */
final class Markdown {

	/**
	 * Render a markdown string to HTML.
	 *
	 * Limitations (intentional for this scope):
	 *   - No raw HTML passthrough. <div> in source renders as literal text.
	 *   - No image syntax. Use HTML <img> blocks if absolutely needed (and
	 *     accept they'll be stripped by wp_kses_post defaults).
	 *   - No reference-style links — only inline [text](url).
	 *   - No autolinks (use explicit [url](url) when needed).
	 */
	public static function render( string $md ): string {
		$md   = str_replace( [ "\r\n", "\r" ], "\n", $md );
		$out  = '';
		$lines = explode( "\n", $md );
		$i = 0;
		$n = count( $lines );

		while ( $i < $n ) {
			$line = $lines[ $i ];

			// Fenced code block ```lang ... ```
			if ( preg_match( '/^```(\w+)?\s*$/', $line, $m ) ) {
				$lang = isset( $m[1] ) ? esc_attr( $m[1] ) : '';
				$buf  = [];
				$i++;
				while ( $i < $n && ! preg_match( '/^```\s*$/', $lines[ $i ] ) ) {
					$buf[] = $lines[ $i ];
					$i++;
				}
				$i++; // skip closing fence
				$code = esc_html( implode( "\n", $buf ) );
				$out .= '<pre class="zc-doc-pre"><code' . ( $lang ? ' class="language-' . $lang . '"' : '' ) . '>' . $code . '</code></pre>' . "\n";
				continue;
			}

			// ATX headings: # H1, ## H2, ### H3 ...
			if ( preg_match( '/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m ) ) {
				$level   = strlen( $m[1] );
				$text    = self::inline( $m[2] );
				$slug    = self::slugify( $m[2] );
				$out    .= '<h' . $level . ' id="' . esc_attr( $slug ) . '">' . $text . '</h' . $level . '>' . "\n";
				$i++;
				continue;
			}

			// Blockquote
			if ( preg_match( '/^>\s?(.*)$/', $line ) ) {
				$buf = [];
				while ( $i < $n && preg_match( '/^>\s?(.*)$/', $lines[ $i ], $m ) ) {
					$buf[] = $m[1];
					$i++;
				}
				$out .= '<blockquote class="zc-doc-quote">' . self::inline( implode( ' ', $buf ) ) . '</blockquote>' . "\n";
				continue;
			}

			// Table: | h1 | h2 | followed by | --- | --- | followed by data rows
			if ( preg_match( '/^\s*\|.*\|\s*$/', $line ) && isset( $lines[ $i + 1 ] ) && preg_match( '/^\s*\|[\s\-:|]+\|\s*$/', $lines[ $i + 1 ] ) ) {
				$header = self::split_table_row( $line );
				$i += 2; // skip header + separator
				$rows = [];
				while ( $i < $n && preg_match( '/^\s*\|.*\|\s*$/', $lines[ $i ] ) ) {
					$rows[] = self::split_table_row( $lines[ $i ] );
					$i++;
				}
				$out .= '<table class="zc-doc-table"><thead><tr>';
				foreach ( $header as $cell ) {
					$out .= '<th>' . self::inline( $cell ) . '</th>';
				}
				$out .= '</tr></thead><tbody>';
				foreach ( $rows as $row ) {
					$out .= '<tr>';
					foreach ( $row as $cell ) {
						$out .= '<td>' . self::inline( $cell ) . '</td>';
					}
					$out .= '</tr>';
				}
				$out .= '</tbody></table>' . "\n";
				continue;
			}

			// Unordered list: -, *, +
			if ( preg_match( '/^\s*[-*+]\s+(.*)$/', $line ) ) {
				$out .= '<ul class="zc-doc-ul">';
				while ( $i < $n && preg_match( '/^\s*[-*+]\s+(.*)$/', $lines[ $i ], $m ) ) {
					$out .= '<li>' . self::inline( $m[1] ) . '</li>';
					$i++;
				}
				$out .= '</ul>' . "\n";
				continue;
			}

			// Ordered list: 1. 2. ...
			if ( preg_match( '/^\s*\d+\.\s+(.*)$/', $line ) ) {
				$out .= '<ol class="zc-doc-ol">';
				while ( $i < $n && preg_match( '/^\s*\d+\.\s+(.*)$/', $lines[ $i ], $m ) ) {
					$out .= '<li>' . self::inline( $m[1] ) . '</li>';
					$i++;
				}
				$out .= '</ol>' . "\n";
				continue;
			}

			// Horizontal rule
			if ( preg_match( '/^\s*(?:---|\*\*\*|___)\s*$/', $line ) ) {
				$out .= '<hr class="zc-doc-hr">' . "\n";
				$i++;
				continue;
			}

			// Blank line — paragraph break
			if ( trim( $line ) === '' ) {
				$i++;
				continue;
			}

			// Paragraph: collect contiguous non-blank, non-special lines
			$buf = [ $line ];
			$i++;
			while ( $i < $n && trim( $lines[ $i ] ) !== '' && ! self::is_block_start( $lines[ $i ], $lines[ $i + 1 ] ?? '' ) ) {
				$buf[] = $lines[ $i ];
				$i++;
			}
			$out .= '<p>' . self::inline( implode( ' ', $buf ) ) . '</p>' . "\n";
		}

		return $out;
	}

	/**
	 * Heuristic: is this line the start of a non-paragraph block?
	 * Used to break out of paragraph collection without losing the next block.
	 */
	private static function is_block_start( string $line, string $next ): bool {
		if ( preg_match( '/^(#{1,6})\s/', $line ) )                       return true;
		if ( preg_match( '/^```/', $line ) )                              return true;
		if ( preg_match( '/^>\s?/', $line ) )                             return true;
		if ( preg_match( '/^\s*[-*+]\s+/', $line ) )                      return true;
		if ( preg_match( '/^\s*\d+\.\s+/', $line ) )                      return true;
		if ( preg_match( '/^\s*(?:---|\*\*\*|___)\s*$/', $line ) )        return true;
		if ( preg_match( '/^\s*\|.*\|\s*$/', $line ) && preg_match( '/^\s*\|[\s\-:|]+\|\s*$/', $next ) ) return true;
		return false;
	}

	/**
	 * Inline transformations: code spans, bold, italic, links. Order matters —
	 * code spans first so their contents don't get further processed.
	 */
	private static function inline( string $s ): string {
		// Code spans `...` — placeholder so later regexes don't touch them
		$placeholders = [];
		$s = preg_replace_callback( '/`([^`]+)`/', function ( $m ) use ( &$placeholders ) {
			$key = "\x00CODE" . count( $placeholders ) . "\x00";
			$placeholders[ $key ] = '<code class="zc-doc-code">' . esc_html( $m[1] ) . '</code>';
			return $key;
		}, $s );

		// Escape everything else as HTML — we re-inject the safe HTML below
		$s = esc_html( $s );

		// Bold **text** and __text__
		$s = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s );
		$s = preg_replace( '/__(.+?)__/s',     '<strong>$1</strong>', $s );

		// Italic *text* and _text_  (after bold so ** isn't mis-matched)
		$s = preg_replace( '/(?<![*\w])\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $s );
		$s = preg_replace( '/(?<![_\w])_([^_\n]+)_(?!_)/',     '<em>$1</em>', $s );

		// Inline links [text](url) — esc_url on the href, text already escaped
		$s = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/', function ( $m ) {
			$href = esc_url( $m[1] === '' ? $m[2] : $m[2] );
			return '<a href="' . $href . '" class="zc-doc-link">' . $m[1] . '</a>';
		}, $s );

		// Restore code-span placeholders
		foreach ( $placeholders as $key => $html ) {
			$s = str_replace( $key, $html, $s );
		}

		return $s;
	}

	private static function split_table_row( string $line ): array {
		$line  = trim( $line );
		$line  = ltrim( $line, '|' );
		$line  = rtrim( $line, '|' );
		$cells = array_map( 'trim', explode( '|', $line ) );
		return $cells;
	}

	/**
	 * URL-friendly slug used as anchor id for headings. ASCII letters/digits
	 * + hyphens; anything else becomes a hyphen, deduped, trimmed.
	 */
	public static function slugify( string $s ): string {
		$s = strtolower( trim( $s ) );
		$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
		$s = trim( (string) $s, '-' );
		return $s !== '' ? $s : 'section';
	}
}
