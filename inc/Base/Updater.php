<?php
namespace QQCPC\Base;

class Updater {
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $github_username;
    private $github_repo;
    private $github_token;

    public function __construct() {
        $this->plugin_file = QQ_CPC_PLUGIN_PATH . 'qq-cpc-woocommerce.php';
        $this->plugin_slug = plugin_basename($this->plugin_file);
        $this->version = $this->get_plugin_version();
        $this->github_username = 'quinto-quarto';
        $this->github_repo = 'qq-cpc-woocommerce';
        $this->github_token = get_option('qq_cpc_github_token', '');
    }

    public function register() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
        add_action('admin_init', array($this, 'maybe_show_update_notice'));
    }

    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data($this->plugin_file);
        return $plugin_data['Version'];
    }

    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => $this->get_github_repo_url(),
                'package' => $this->get_download_url($remote_version),
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => get_bloginfo('version'),
                'requires_php' => '7.4',
                'compatibility' => new \stdClass(),
            );
        }

        return $transient;
    }

    private function get_remote_version() {
        $cached_version = get_transient('qq_cpc_remote_version');
        if ($cached_version !== false) {
            return $cached_version;
        }

        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        );

        if (!empty($this->github_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }

        $response = wp_remote_get($request_uri, $args);

        if (is_wp_error($response)) {
            error_log('QQ CPC Update Check Error: ' . $response->get_error_message());
            return $this->version;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['tag_name'])) {
            $remote_version = ltrim($data['tag_name'], 'v');
            set_transient('qq_cpc_remote_version', $remote_version, HOUR_IN_SECONDS * 6);
            return $remote_version;
        }

        return $this->version;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $remote_version = $this->get_remote_version();
        $plugin_data = get_plugin_data($this->plugin_file);

        return (object) array(
            'name' => $plugin_data['Name'],
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_version,
            'author' => $plugin_data['Author'],
            'author_profile' => $this->get_github_repo_url(),
            'last_updated' => date('Y-m-d'),
            'homepage' => $this->get_github_repo_url(),
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'Description' => $plugin_data['Description'],
                'Updates' => $this->get_changelog(),
            ),
            'download_link' => $this->get_download_url($remote_version),
            'tested' => get_bloginfo('version'),
            'requires' => '5.0',
            'requires_php' => '7.4',
        );
    }

    private function get_changelog() {
        $request_uri = sprintf(
            'https://api.github.com/repos/%s/%s/releases',
            $this->github_username,
            $this->github_repo
        );

        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        );

        if (!empty($this->github_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }

        $response = wp_remote_get($request_uri, $args);

        if (is_wp_error($response)) {
            return 'Unable to fetch changelog.';
        }

        $body = wp_remote_retrieve_body($response);
        $releases = json_decode($body, true);

        if (!is_array($releases)) {
            return 'Unable to fetch changelog.';
        }

        $changelog = '<h4>Recent Updates</h4>';
        foreach (array_slice($releases, 0, 5) as $release) {
            $changelog .= '<h5>' . esc_html($release['tag_name']) . ' - ' . date('F j, Y', strtotime($release['published_at'])) . '</h5>';
            $changelog .= '<div>' . wp_kses_post($release['body']) . '</div>';
        }

        return $changelog;
    }

    public function download_package($result, $package, $wp_upgrader) {
        if (strpos($package, 'github.com/' . $this->github_username . '/' . $this->github_repo) === false) {
            return $result;
        }

        return $result;
    }

    private function get_download_url($version) {
        return sprintf(
            'https://github.com/%s/%s/archive/refs/tags/v%s.zip',
            $this->github_username,
            $this->github_repo,
            $version
        );
    }

    private function get_github_repo_url() {
        return sprintf(
            'https://github.com/%s/%s',
            $this->github_username,
            $this->github_repo
        );
    }

    public function maybe_show_update_notice() {
        if (!current_user_can('update_plugins')) {
            return;
        }

        $screen = get_current_screen();
        if ($screen->id !== 'plugins') {
            return;
        }

        $remote_version = $this->get_remote_version();
        if (version_compare($this->version, $remote_version, '<')) {
            add_action('admin_notices', array($this, 'show_update_notice'));
        }
    }

    public function show_update_notice() {
        $remote_version = $this->get_remote_version();
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>QQ CPC for WooCommerce:</strong> 
                A new version (<?php echo esc_html($remote_version); ?>) is available. 
                Current version: <?php echo esc_html($this->version); ?>
                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">Update now</a>
            </p>
        </div>
        <?php
    }
}