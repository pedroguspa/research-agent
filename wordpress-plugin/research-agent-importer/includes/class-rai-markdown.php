<?php
/**
 * RAI_Markdown — lightweight Markdown → HTML converter.
 * Handles the subset of Markdown the Research Agent actually produces.
 */
defined( 'ABSPATH' ) || exit;

class RAI_Markdown {

    /**
     * Convert a Markdown string to HTML and return both the HTML
     * and a plain-text excerpt (first ~160 chars of body text).
     *
     * @param  string $markdown
     * @return array{ html: string, excerpt: string, headings: string[] }
     */
    public static function parse( string $markdown ): array {
        $lines    = explode( "\n", $markdown );
        $html     = '';
        $headings = [];
        $in_list  = false;
        $in_code  = false;
        $plain    = '';

        foreach ( $lines as $line ) {

            // Fenced code blocks
            if ( str_starts_with( trim( $line ), '```' ) ) {
                if ( $in_code ) {
                    $html    .= "</code></pre>\n";
                    $in_code  = false;
                } else {
                    $lang     = trim( substr( trim( $line ), 3 ) );
                    $html    .= '<pre><code' . ( $lang ? " class=\"language-{$lang}\"" : '' ) . '>';
                    $in_code  = true;
                }
                continue;
            }
            if ( $in_code ) {
                $html .= esc_html( $line ) . "\n";
                continue;
            }

            // Close open list
            if ( $in_list && ! preg_match( '/^[\*\-\+] /', $line ) && ! preg_match( '/^\d+\. /', $line ) ) {
                $html   .= "</ul>\n";
                $in_list = false;
            }

            // Headings
            if ( preg_match( '/^(#{1,6}) (.+)/', $line, $m ) ) {
                $level      = strlen( $m[1] );
                $text       = self::inline( $m[2] );
                $id         = sanitize_title( wp_strip_all_tags( $text ) );
                $html      .= "<h{$level} id=\"{$id}\">{$text}</h{$level}>\n";
                $headings[] = [ 'level' => $level, 'text' => wp_strip_all_tags( $text ), 'id' => $id ];
                continue;
            }

            // Horizontal rule
            if ( preg_match( '/^-{3,}$|^\*{3,}$/', trim( $line ) ) ) {
                $html .= "<hr>\n";
                continue;
            }

            // Unordered list
            if ( preg_match( '/^[\*\-\+] (.+)/', $line, $m ) ) {
                if ( ! $in_list ) { $html .= "<ul>\n"; $in_list = true; }
                $html .= '<li>' . self::inline( $m[1] ) . "</li>\n";
                continue;
            }

            // Ordered list
            if ( preg_match( '/^\d+\. (.+)/', $line, $m ) ) {
                if ( ! $in_list ) { $html .= "<ol>\n"; $in_list = true; }
                $html .= '<li>' . self::inline( $m[1] ) . "</li>\n";
                continue;
            }

            // Blockquote
            if ( str_starts_with( $line, '> ' ) ) {
                $html .= '<blockquote><p>' . self::inline( substr( $line, 2 ) ) . "</p></blockquote>\n";
                continue;
            }

            // Blank line → paragraph break
            if ( trim( $line ) === '' ) {
                $html .= "\n";
                continue;
            }

            // Paragraph
            $text  = self::inline( $line );
            $html .= "<p>{$text}</p>\n";
            if ( strlen( $plain ) < 300 ) {
                $plain .= wp_strip_all_tags( $text ) . ' ';
            }
        }

        if ( $in_list )  { $html .= "</ul>\n"; }
        if ( $in_code )  { $html .= "</code></pre>\n"; }

        $excerpt = mb_substr( trim( $plain ), 0, 160 );
        if ( strlen( trim( $plain ) ) > 160 ) { $excerpt .= '…'; }

        return [
            'html'     => $html,
            'excerpt'  => $excerpt,
            'headings' => $headings,
        ];
    }

    // ── Inline formatting ─────────────────────────────────────────────────────
    private static function inline( string $text ): string {
        // Bold + italic
        $text = preg_replace( '/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $text );
        $text = preg_replace( '/\*\*(.+?)\*\*/',     '<strong>$1</strong>',          $text );
        $text = preg_replace( '/\*(.+?)\*/',          '<em>$1</em>',                 $text );
        $text = preg_replace( '/\_\_(.+?)\_\_/',      '<strong>$1</strong>',          $text );
        $text = preg_replace( '/\_(.+?)\_/',           '<em>$1</em>',                 $text );
        // Inline code
        $text = preg_replace( '/`(.+?)`/', '<code>$1</code>', $text );
        // Links
        $text = preg_replace( '/\[(.+?)\]\((.+?)\)/', '<a href="$2" rel="noopener">$1</a>', $text );
        // Strikethrough
        $text = preg_replace( '/~~(.+?)~~/', '<del>$1</del>', $text );
        return $text;
    }

    /**
     * Extract the first H1 title from Markdown (used as post title).
     */
    public static function extract_title( string $markdown ): string {
        if ( preg_match( '/^# (.+)/m', $markdown, $m ) ) {
            return trim( $m[1] );
        }
        // Fallback: first non-empty line
        foreach ( explode( "\n", $markdown ) as $line ) {
            $line = trim( $line );
            if ( $line ) { return $line; }
        }
        return 'Imported Article';
    }

    /**
     * Extract the meta description from a "<!-- meta: ... -->" comment OR
     * the first paragraph that looks like an intro.
     */
    public static function extract_meta_description( string $markdown ): string {
        if ( preg_match( '/<!--\s*meta:\s*(.+?)\s*-->/i', $markdown, $m ) ) {
            return trim( $m[1] );
        }
        // First paragraph after the H1
        if ( preg_match( '/^# .+\n+([^#\n].{40,})/m', $markdown, $m ) ) {
            return mb_substr( wp_strip_all_tags( $m[1] ), 0, 160 );
        }
        return '';
    }

    /**
     * Extract focus keyword from "<!-- focus: ... -->" or guess from title.
     */
    public static function extract_focus_keyword( string $markdown, string $title ): string {
        if ( preg_match( '/<!--\s*focus:\s*(.+?)\s*-->/i', $markdown, $m ) ) {
            return trim( $m[1] );
        }
        // Use lowercase title without stop words as a rough keyword
        $stop  = [ 'a','an','the','and','or','but','in','on','at','to','for','of','with','by','from','is','are','was','were','be','been','being','have','has','had','do','does','did','will','would','could','should','may','might','shall' ];
        $words = array_filter(
            explode( ' ', strtolower( preg_replace( '/[^a-z0-9 ]/i', '', $title ) ) ),
            fn( $w ) => $w && ! in_array( $w, $stop, true )
        );
        return implode( ' ', array_slice( array_values( $words ), 0, 3 ) );
    }
}
