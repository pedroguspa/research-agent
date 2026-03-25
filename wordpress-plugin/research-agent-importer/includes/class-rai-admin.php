<?php
/**
 * RAI_Admin — WordPress admin settings page.
 */
defined( 'ABSPATH' ) || exit;

class RAI_Admin {

    public static function init(): void {
        add_action( 'admin_menu',       [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init',       [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function add_menu(): void {
        add_options_page(
            'Research Agent Importer',
            'Research Agent',
            'manage_options',
            'research-agent-importer',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings(): void {
        register_setting( 'rai_settings_group', RAI_OPTION_KEY, [
            'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
        ] );
    }

    public static function sanitize_settings( $input ): array {
        $clean = [];
        $clean['default_status'] = in_array( $input['default_status'] ?? '', [ 'publish', 'draft', 'pending' ], true )
            ? $input['default_status'] : 'publish';
        $clean['schema_type']    = in_array( $input['schema_type'] ?? '', [ 'BlogPosting', 'Article', 'NewsArticle' ], true )
            ? $input['schema_type'] : 'BlogPosting';
        $clean['default_author'] = absint( $input['default_author'] ?? 0 );
        return $clean;
    }

    public static function enqueue_assets( string $hook ): void {
        if ( $hook !== 'settings_page_research-agent-importer' ) { return; }
        wp_enqueue_style(
            'rai-admin',
            RAI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            RAI_VERSION
        );
    }

    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        $token    = get_option( RAI_TOKEN_KEY, '' );
        $settings = get_option( RAI_OPTION_KEY, [] );
        $endpoint = rest_url( 'research-agent/v1/import' );
        $authors  = get_users( [ 'role__in' => [ 'administrator', 'editor', 'author' ] ] );

        // Handle token regeneration
        if ( isset( $_POST['rai_regenerate_token'] ) && check_admin_referer( 'rai_regenerate' ) ) {
            $token = wp_generate_password( 48, false );
            update_option( RAI_TOKEN_KEY, $token );
            echo '<div class="notice notice-success"><p>Token regenerated.</p></div>';
        }
        ?>
        <div class="wrap rai-wrap">

            <div class="rai-header">
                <div class="rai-header-icon">⬡</div>
                <div>
                    <h1 class="rai-title">Research Agent Importer</h1>
                    <p class="rai-subtitle">Configure the WordPress endpoint for your AI research pipeline.</p>
                </div>
            </div>

            <!-- API Endpoint Card -->
            <div class="rai-card">
                <h2 class="rai-card-title">🔌 API Endpoint</h2>
                <p class="rai-desc">Add this URL and token to your Research Agent's WordPress publish settings.</p>

                <div class="rai-field-group">
                    <label class="rai-label">Endpoint URL</label>
                    <div class="rai-copy-row">
                        <input type="text" class="rai-input" value="<?php echo esc_attr( $endpoint ); ?>" readonly id="rai-endpoint">
                        <button class="rai-btn rai-btn-ghost" onclick="raiCopy('rai-endpoint')">Copy</button>
                    </div>
                </div>

                <div class="rai-field-group">
                    <label class="rai-label">Bearer Token</label>
                    <div class="rai-copy-row">
                        <input type="text" class="rai-input rai-mono" value="<?php echo esc_attr( $token ); ?>" readonly id="rai-token">
                        <button class="rai-btn rai-btn-ghost" onclick="raiCopy('rai-token')">Copy</button>
                    </div>
                    <form method="post" style="margin-top:10px;">
                        <?php wp_nonce_field( 'rai_regenerate' ); ?>
                        <button type="submit" name="rai_regenerate_token" class="rai-btn rai-btn-danger-ghost"
                            onclick="return confirm('Regenerate token? The old one will stop working immediately.')">
                            ↻ Regenerate Token
                        </button>
                    </form>
                </div>

                <div class="rai-callout">
                    <strong>Quick test</strong> — run this in your terminal:<br>
                    <code class="rai-code">curl -X GET <?php echo esc_url( rest_url( 'research-agent/v1/status' ) ); ?> \<br>
  -H "Authorization: Bearer <?php echo esc_html( $token ); ?>"</code>
                </div>
            </div>

            <!-- Settings Card -->
            <div class="rai-card">
                <h2 class="rai-card-title">⚙️ Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields( 'rai_settings_group' ); ?>

                    <div class="rai-grid-2">
                        <div class="rai-field-group">
                            <label class="rai-label" for="default_status">Default Post Status</label>
                            <select name="<?php echo RAI_OPTION_KEY; ?>[default_status]" id="default_status" class="rai-select">
                                <?php
                                $statuses = [ 'publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending Review' ];
                                $current  = $settings['default_status'] ?? 'publish';
                                foreach ( $statuses as $val => $label ) {
                                    printf( '<option value="%s"%s>%s</option>', $val, selected( $current, $val, false ), $label );
                                }
                                ?>
                            </select>
                        </div>

                        <div class="rai-field-group">
                            <label class="rai-label" for="schema_type">Schema.org Type</label>
                            <select name="<?php echo RAI_OPTION_KEY; ?>[schema_type]" id="schema_type" class="rai-select">
                                <?php
                                $types   = [ 'BlogPosting' => 'BlogPosting', 'Article' => 'Article', 'NewsArticle' => 'NewsArticle' ];
                                $current = $settings['schema_type'] ?? 'BlogPosting';
                                foreach ( $types as $val => $label ) {
                                    printf( '<option value="%s"%s>%s</option>', $val, selected( $current, $val, false ), $label );
                                }
                                ?>
                            </select>
                        </div>

                        <div class="rai-field-group">
                            <label class="rai-label" for="default_author">Default Author</label>
                            <select name="<?php echo RAI_OPTION_KEY; ?>[default_author]" id="default_author" class="rai-select">
                                <option value="0">— Auto (first admin) —</option>
                                <?php
                                $current = intval( $settings['default_author'] ?? 0 );
                                foreach ( $authors as $user ) {
                                    printf( '<option value="%d"%s>%s</option>', $user->ID, selected( $current, $user->ID, false ), esc_html( $user->display_name ) );
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <?php submit_button( 'Save Settings', 'rai-btn rai-btn-primary', 'submit', false ); ?>
                </form>
            </div>

            <!-- SEO Info Card -->
            <div class="rai-card">
                <h2 class="rai-card-title">🔍 SEO Integration</h2>
                <div class="rai-grid-2">
                    <?php
                    $plugins = [
                        [ 'name' => 'Yoast SEO',      'active' => defined( 'WPSEO_VERSION' ),                         'icon' => '🟢' ],
                        [ 'name' => 'Rank Math',       'active' => defined( 'RANK_MATH_VERSION' ),                     'icon' => '🟢' ],
                        [ 'name' => 'All-in-One SEO',  'active' => class_exists( 'AIOSEO\Plugin\AIOSEO' ),             'icon' => '🟢' ],
                        [ 'name' => 'Standalone (built-in)', 'active' => true,                                         'icon' => '⚪' ],
                    ];
                    foreach ( $plugins as $p ) {
                        $status = $p['active'] ? 'rai-badge-active' : 'rai-badge-inactive';
                        $label  = $p['active'] ? 'Active' : 'Not detected';
                        echo "<div class='rai-plugin-row'><span>{$p['icon']} {$p['name']}</span><span class='rai-badge {$status}'>{$label}</span></div>";
                    }
                    ?>
                </div>
                <p class="rai-desc" style="margin-top:12px;">
                    The importer writes meta to whichever SEO plugin is active. If none are installed, meta tags and JSON-LD schema are injected directly into <code>&lt;head&gt;</code>.
                </p>
            </div>

            <!-- Payload Reference Card -->
            <div class="rai-card">
                <h2 class="rai-card-title">📄 Request Payload Reference</h2>
                <pre class="rai-pre"><?php echo esc_html( json_encode( [
                    'markdown'         => '# My Article\n\nContent here...',
                    'title'            => 'Optional title override',
                    'categories'       => [ 'AI Research', 'Climate Tech' ],
                    'tags'             => [ 'machine learning', '2025' ],
                    'slug'             => 'optional-slug-override',
                    'status'           => 'publish',
                    'update_if_exists' => false,
                    'seo'              => [
                        'meta_description' => 'Up to 160 chars…',
                        'focus_keyword'    => 'AI research 2025',
                        'og_image_url'     => 'https://…',
                        'schema_type'      => 'BlogPosting',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
            </div>

        </div>

        <script>
        function raiCopy(id) {
            const el = document.getElementById(id);
            navigator.clipboard.writeText(el.value).then(() => {
                const btn = el.nextElementSibling;
                btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = 'Copy', 1500);
            });
        }
        </script>
        <?php
    }
}
