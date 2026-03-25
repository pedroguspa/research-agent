<?php
/**
 * RAI_Rest_API — registers the /wp-json/research-agent/v1/import endpoint.
 *
 * Authentication: Bearer token stored in wp_options (generated on activation).
 * The research-agent frontend sends:
 *   Authorization: Bearer <token>
 */
defined( 'ABSPATH' ) || exit;

class RAI_Rest_API {

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes(): void {
        // POST /wp-json/research-agent/v1/import
        register_rest_route( 'research-agent/v1', '/import', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_import' ],
            'permission_callback' => [ __CLASS__, 'check_token' ],
            'args'                => self::import_args(),
        ] );

        // POST /wp-json/research-agent/v1/import/batch  (multiple articles at once)
        register_rest_route( 'research-agent/v1', '/import/batch', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_batch' ],
            'permission_callback' => [ __CLASS__, 'check_token' ],
        ] );

        // GET /wp-json/research-agent/v1/status  (ping / health check)
        register_rest_route( 'research-agent/v1', '/status', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'handle_status' ],
            'permission_callback' => [ __CLASS__, 'check_token' ],
        ] );
    }

    // ── Auth ──────────────────────────────────────────────────────────────────
    public static function check_token( WP_REST_Request $request ): bool|WP_Error {
        $auth  = $request->get_header( 'Authorization' );
        $token = get_option( RAI_TOKEN_KEY, '' );

        if ( ! $auth || ! $token ) {
            return new WP_Error( 'rai_unauthorized', 'Missing token.', [ 'status' => 401 ] );
        }

        // Support both "Bearer <token>" and raw token
        $provided = preg_replace( '/^Bearer\s+/i', '', $auth );

        if ( ! hash_equals( $token, $provided ) ) {
            return new WP_Error( 'rai_forbidden', 'Invalid token.', [ 'status' => 403 ] );
        }

        return true;
    }

    // ── Single import ─────────────────────────────────────────────────────────
    public static function handle_import( WP_REST_Request $request ): WP_REST_Response {
        try {
            $result = RAI_Importer::import( $request->get_json_params() );
            return new WP_REST_Response( [
                'success' => true,
                'data'    => $result,
            ], 201 );
        } catch ( RuntimeException $e ) {
            return new WP_REST_Response( [
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422 );
        }
    }

    // ── Batch import ─────────────────────────────────────────────────────────
    public static function handle_batch( WP_REST_Request $request ): WP_REST_Response {
        $body     = $request->get_json_params();
        $articles = $body['articles'] ?? [];
        $results  = [];
        $errors   = [];

        foreach ( $articles as $i => $article ) {
            try {
                $results[] = RAI_Importer::import( $article );
            } catch ( RuntimeException $e ) {
                $errors[] = [ 'index' => $i, 'error' => $e->getMessage() ];
            }
        }

        return new WP_REST_Response( [
            'success'  => empty( $errors ),
            'imported' => count( $results ),
            'failed'   => count( $errors ),
            'results'  => $results,
            'errors'   => $errors,
        ], 200 );
    }

    // ── Status / health ───────────────────────────────────────────────────────
    public static function handle_status( WP_REST_Request $request ): WP_REST_Response {
        $settings = get_option( RAI_OPTION_KEY, [] );
        return new WP_REST_Response( [
            'status'         => 'ok',
            'plugin_version' => RAI_VERSION,
            'wp_version'     => get_bloginfo( 'version' ),
            'yoast_active'   => defined( 'WPSEO_VERSION' ),
            'rankmath_active'=> defined( 'RANK_MATH_VERSION' ),
            'aioseo_active'  => class_exists( 'AIOSEO\Plugin\AIOSEO' ),
            'default_status' => $settings['default_status'] ?? 'publish',
            'schema_type'    => $settings['schema_type']    ?? 'BlogPosting',
        ], 200 );
    }

    // ── Argument schema (for WP REST validation) ──────────────────────────────
    private static function import_args(): array {
        return [
            'markdown' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ],
            'title' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'categories' => [
                'required' => false,
                'type'     => 'array',
                'items'    => [ 'type' => 'string' ],
            ],
            'tags' => [
                'required' => false,
                'type'     => 'array',
                'items'    => [ 'type' => 'string' ],
            ],
            'slug' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_title',
            ],
            'status' => [
                'required' => false,
                'type'     => 'string',
                'enum'     => [ 'publish', 'draft', 'pending' ],
            ],
            'update_if_exists' => [
                'required' => false,
                'type'     => 'boolean',
            ],
            'seo' => [
                'required' => false,
                'type'     => 'object',
            ],
        ];
    }
}
