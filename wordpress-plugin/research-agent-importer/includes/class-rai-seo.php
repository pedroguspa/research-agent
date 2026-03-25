<?php
/**
 * RAI_SEO — writes SEO meta to:
 *   1. Post meta (standalone <head> injection if no SEO plugin detected)
 *   2. Yoast SEO meta keys  (if Yoast is active)
 *   3. Rank Math meta keys  (if Rank Math is active)
 *   4. All-in-One SEO keys  (if AIOSEO is active)
 */
defined( 'ABSPATH' ) || exit;

class RAI_SEO {

    // ── Save SEO data for a post ──────────────────────────────────────────────
    public static function save( int $post_id, array $seo ): void {
        /*
         * $seo shape:
         * [
         *   title            => string   (SEO <title>, defaults to post title)
         *   meta_description => string   (≤160 chars)
         *   focus_keyword    => string
         *   og_title         => string
         *   og_description   => string
         *   og_image_url     => string
         *   canonical        => string   (optional override)
         *   robots           => string   (e.g. "index, follow")
         *   schema_type      => string   (Article | BlogPosting | NewsArticle)
         * ]
         */

        // Always store our own meta (used for standalone injection + fallback)
        foreach ( $seo as $key => $value ) {
            update_post_meta( $post_id, "_rai_seo_{$key}", sanitize_text_field( $value ) );
        }

        // ── Yoast SEO ────────────────────────────────────────────────────────
        if ( defined( 'WPSEO_VERSION' ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_title',          $seo['title']            ?? '' );
            update_post_meta( $post_id, '_yoast_wpseo_metadesc',       $seo['meta_description'] ?? '' );
            update_post_meta( $post_id, '_yoast_wpseo_focuskw',        $seo['focus_keyword']    ?? '' );
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $seo['og_title']        ?? '' );
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $seo['og_description'] ?? '' );
            if ( ! empty( $seo['og_image_url'] ) ) {
                update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', $seo['og_image_url'] );
            }
            update_post_meta( $post_id, '_yoast_wpseo_canonical', $seo['canonical'] ?? '' );
            return; // Yoast handles the rest
        }

        // ── Rank Math ────────────────────────────────────────────────────────
        if ( defined( 'RANK_MATH_VERSION' ) ) {
            update_post_meta( $post_id, 'rank_math_title',              $seo['title']            ?? '' );
            update_post_meta( $post_id, 'rank_math_description',        $seo['meta_description'] ?? '' );
            update_post_meta( $post_id, 'rank_math_focus_keyword',      $seo['focus_keyword']    ?? '' );
            update_post_meta( $post_id, 'rank_math_canonical_url',      $seo['canonical']        ?? '' );
            update_post_meta( $post_id, 'rank_math_robots',             [ $seo['robots'] ?? 'index' ] );
            return;
        }

        // ── All-in-One SEO ───────────────────────────────────────────────────
        if ( class_exists( 'AIOSEO\Plugin\AIOSEO' ) ) {
            global $wpdb;
            $wpdb->replace(
                $wpdb->prefix . 'aioseo_posts',
                [
                    'post_id'          => $post_id,
                    'title'            => $seo['title']            ?? '',
                    'description'      => $seo['meta_description'] ?? '',
                    'keywords'         => $seo['focus_keyword']    ?? '',
                    'og_title'         => $seo['og_title']         ?? '',
                    'og_description'   => $seo['og_description']   ?? '',
                    'robots_default'   => 1,
                    'updated'          => current_time( 'mysql' ),
                    'created'          => current_time( 'mysql' ),
                ],
                [ '%d','%s','%s','%s','%s','%s','%d','%s','%s' ]
            );
            return;
        }

        // ── Standalone fallback: inject via wp_head ───────────────────────────
        add_filter( 'wp_head', [ __CLASS__, 'inject_head_meta' ] );
    }

    // ── Standalone <head> injection (only when no SEO plugin present) ─────────
    public static function inject_head_meta(): void {
        if ( ! is_singular() ) { return; }
        $id  = get_the_ID();
        $get = fn( $k ) => get_post_meta( $id, "_rai_seo_{$k}", true );

        $title    = $get( 'title' )            ?: get_the_title();
        $desc     = $get( 'meta_description' ) ?: '';
        $keyword  = $get( 'focus_keyword' )    ?: '';
        $og_title = $get( 'og_title' )         ?: $title;
        $og_desc  = $get( 'og_description' )   ?: $desc;
        $og_image = $get( 'og_image_url' )     ?: '';
        $canon    = $get( 'canonical' )        ?: get_permalink( $id );
        $robots   = $get( 'robots' )           ?: 'index, follow';
        $schema   = $get( 'schema_type' )      ?: 'BlogPosting';

        $url  = get_permalink( $id );
        $date = get_the_date( 'c', $id );
        $mod  = get_the_modified_date( 'c', $id );
        $site = get_bloginfo( 'name' );

        echo "\n<!-- Research Agent SEO -->\n";
        echo "<title>" . esc_html( $title ) . "</title>\n";
        if ( $desc )    { echo "<meta name=\"description\" content=\"" . esc_attr( $desc ) . "\">\n"; }
        if ( $keyword ) { echo "<meta name=\"keywords\" content=\"" . esc_attr( $keyword ) . "\">\n"; }
        echo "<meta name=\"robots\" content=\"" . esc_attr( $robots ) . "\">\n";
        echo "<link rel=\"canonical\" href=\"" . esc_url( $canon ) . "\">\n";

        // Open Graph
        echo "<meta property=\"og:type\" content=\"article\">\n";
        echo "<meta property=\"og:title\" content=\"" . esc_attr( $og_title ) . "\">\n";
        echo "<meta property=\"og:description\" content=\"" . esc_attr( $og_desc ) . "\">\n";
        echo "<meta property=\"og:url\" content=\"" . esc_url( $url ) . "\">\n";
        echo "<meta property=\"og:site_name\" content=\"" . esc_attr( $site ) . "\">\n";
        if ( $og_image ) { echo "<meta property=\"og:image\" content=\"" . esc_url( $og_image ) . "\">\n"; }

        // Twitter Card
        echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        echo "<meta name=\"twitter:title\" content=\"" . esc_attr( $og_title ) . "\">\n";
        echo "<meta name=\"twitter:description\" content=\"" . esc_attr( $og_desc ) . "\">\n";
        if ( $og_image ) { echo "<meta name=\"twitter:image\" content=\"" . esc_url( $og_image ) . "\">\n"; }

        // JSON-LD Schema
        $schema_data = [
            '@context'      => 'https://schema.org',
            '@type'         => $schema,
            'headline'      => $title,
            'description'   => $desc,
            'url'           => $url,
            'datePublished' => $date,
            'dateModified'  => $mod,
            'publisher'     => [ '@type' => 'Organization', 'name' => $site ],
        ];
        if ( $og_image ) {
            $schema_data['image'] = [ '@type' => 'ImageObject', 'url' => $og_image ];
        }
        echo "<script type=\"application/ld+json\">" . wp_json_encode( $schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
        echo "<!-- /Research Agent SEO -->\n\n";
    }

    // ── Create supplementary DB table (unused for now, reserved for analytics) ─
    public static function maybe_create_table(): void {
        // Reserved for future import log table
    }
}
