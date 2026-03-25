<?php
/**
 * RAI_Importer — creates or updates WordPress posts from Research Agent payloads.
 */
defined( 'ABSPATH' ) || exit;

class RAI_Importer {

    /**
     * Import a single article.
     *
     * @param  array $data {
     *   markdown      string  Full markdown content (required)
     *   title         string  Override title (optional — extracted from H1 otherwise)
     *   categories    array   Category names  e.g. ["AI Research","Climate Tech"]
     *   slug          string  Explicit slug override (optional)
     *   status        string  publish|draft|pending  (default: from plugin settings)
     *   author_id     int     (default: first admin)
     *   seo           array   SEO overrides (optional — auto-generated otherwise)
     *   update_if_exists bool Re-import if slug already exists (default: false)
     * }
     * @return array{ post_id: int, url: string, action: string }
     * @throws RuntimeException on failure
     */
    public static function import( array $data ): array {

        $markdown = $data['markdown'] ?? '';
        if ( ! $markdown ) {
            throw new RuntimeException( 'markdown field is required.' );
        }

        // ── Parse Markdown ────────────────────────────────────────────────────
        $parsed  = RAI_Markdown::parse( $markdown );
        $html    = $parsed['html'];
        $excerpt = $parsed['excerpt'];

        // ── Resolve title ─────────────────────────────────────────────────────
        $title = sanitize_text_field( $data['title'] ?? RAI_Markdown::extract_title( $markdown ) );

        // ── Resolve slug / permalink ──────────────────────────────────────────
        $slug = isset( $data['slug'] ) && $data['slug']
            ? sanitize_title( $data['slug'] )
            : self::build_slug( $title );

        // ── Check for existing post ───────────────────────────────────────────
        $existing_id = self::find_post_by_slug( $slug );
        $action      = 'created';

        if ( $existing_id && empty( $data['update_if_exists'] ) ) {
            throw new RuntimeException( "A post with slug '{$slug}' already exists (ID {$existing_id}). Pass update_if_exists:true to overwrite." );
        }

        // ── Default post status from settings ────────────────────────────────
        $settings       = get_option( RAI_OPTION_KEY, [] );
        $default_status = $settings['default_status'] ?? 'publish';
        $status         = in_array( $data['status'] ?? '', [ 'publish', 'draft', 'pending' ], true )
            ? $data['status']
            : $default_status;

        // ── Resolve author ────────────────────────────────────────────────────
        $author_id = intval( $data['author_id'] ?? $settings['default_author'] ?? 0 );
        if ( ! $author_id ) {
            $admins    = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
            $author_id = $admins ? $admins[0]->ID : 1;
        }

        // ── Build post array ──────────────────────────────────────────────────
        $post_arr = [
            'post_title'   => $title,
            'post_content' => $html,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_type'    => 'post',
            'post_name'    => $slug,
            'post_author'  => $author_id,
        ];

        if ( $existing_id ) {
            $post_arr['ID'] = $existing_id;
            $post_id        = wp_update_post( $post_arr, true );
            $action         = 'updated';
        } else {
            $post_id = wp_insert_post( $post_arr, true );
        }

        if ( is_wp_error( $post_id ) ) {
            throw new RuntimeException( $post_id->get_error_message() );
        }

        // ── Categories ────────────────────────────────────────────────────────
        $categories = $data['categories'] ?? [];
        if ( ! empty( $categories ) ) {
            self::assign_categories( $post_id, (array) $categories );
        }

        // ── Tags ─────────────────────────────────────────────────────────────
        if ( ! empty( $data['tags'] ) ) {
            wp_set_post_tags( $post_id, (array) $data['tags'], false );
        }

        // ── SEO ───────────────────────────────────────────────────────────────
        $seo = self::build_seo( $title, $markdown, $excerpt, $slug, $data['seo'] ?? [] );
        RAI_SEO::save( $post_id, $seo );

        // ── Mark as AI-generated ──────────────────────────────────────────────
        update_post_meta( $post_id, '_rai_imported',    current_time( 'mysql' ) );
        update_post_meta( $post_id, '_rai_source',      sanitize_text_field( $data['source'] ?? 'research-agent' ) );

        return [
            'post_id' => $post_id,
            'url'     => get_permalink( $post_id ),
            'action'  => $action,
            'slug'    => $slug,
            'title'   => $title,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build an SEO-friendly slug: lowercase, hyphens, max 60 chars, no stop words.
     */
    public static function build_slug( string $title ): string {
        $stop  = [ 'a','an','the','and','or','but','in','on','at','to','for','of','with','by','from' ];
        $words = explode( '-', sanitize_title( $title ) );
        $kept  = array_filter( $words, fn( $w ) => $w && ! in_array( $w, $stop, true ) );
        $slug  = implode( '-', array_slice( array_values( $kept ), 0, 8 ) );
        // Ensure uniqueness by appending a suffix if needed
        return wp_unique_post_slug( $slug ?: 'article', 0, 'publish', 'post', 0 );
    }

    /**
     * Find an existing post by its slug (post_name).
     */
    private static function find_post_by_slug( string $slug ): int {
        $posts = get_posts( [
            'name'           => $slug,
            'post_type'      => 'post',
            'post_status'    => 'any',
            'posts_per_page' => 1,
        ] );
        return $posts ? $posts[0]->ID : 0;
    }

    /**
     * Resolve category names to IDs, creating terms that don't exist yet.
     */
    private static function assign_categories( int $post_id, array $names ): void {
        $ids = [];
        foreach ( $names as $name ) {
            $name = sanitize_text_field( trim( $name ) );
            if ( ! $name ) { continue; }
            $term = get_term_by( 'name', $name, 'category' );
            if ( ! $term ) {
                $result = wp_insert_term( $name, 'category', [
                    'slug' => sanitize_title( $name ),
                ] );
                if ( ! is_wp_error( $result ) ) {
                    $ids[] = $result['term_id'];
                }
            } else {
                $ids[] = $term->term_id;
            }
        }
        if ( $ids ) {
            wp_set_post_categories( $post_id, $ids );
        }
    }

    /**
     * Build the SEO array from article data + any provided overrides.
     */
    private static function build_seo( string $title, string $markdown, string $excerpt, string $slug, array $overrides ): array {
        $settings = get_option( RAI_OPTION_KEY, [] );
        $site     = get_bloginfo( 'name' );

        $meta_desc    = $overrides['meta_description']
            ?? RAI_Markdown::extract_meta_description( $markdown )
            ?: $excerpt;
        $focus_kw     = $overrides['focus_keyword']
            ?? RAI_Markdown::extract_focus_keyword( $markdown, $title );
        $seo_title    = $overrides['title']
            ?? "{$title} — {$site}";
        $og_title     = $overrides['og_title']     ?? $title;
        $og_desc      = $overrides['og_description'] ?? $meta_desc;
        $schema_type  = $overrides['schema_type']  ?? ( $settings['schema_type'] ?? 'BlogPosting' );

        return [
            'title'            => mb_substr( $seo_title, 0, 70 ),
            'meta_description' => mb_substr( $meta_desc, 0, 160 ),
            'focus_keyword'    => $focus_kw,
            'og_title'         => mb_substr( $og_title, 0, 70 ),
            'og_description'   => mb_substr( $og_desc, 0, 200 ),
            'og_image_url'     => $overrides['og_image_url'] ?? '',
            'canonical'        => $overrides['canonical'] ?? '',
            'robots'           => $overrides['robots'] ?? 'index, follow',
            'schema_type'      => $schema_type,
        ];
    }
}
