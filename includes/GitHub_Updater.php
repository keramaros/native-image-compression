<?php

class GitHub_Updater
{
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repository;

    public function __construct($file, $username, $repository)
    {
        $this->file = $file;
        $this->username = $username;
        $this->repository = $repository;
        $this->basename = plugin_basename($file);

        add_filter('site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_popup_handler'], 20, 3);
        add_filter('upgrader_source_selection', [$this, 'upgrader_source_selection'], 10, 4);
    }

    public function check_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Fetch release info from GitHub (cached for 12 hours)
        $remote = get_transient('my_gh_updater_' . $this->repository);
        if (false === $remote) {
            $response = wp_remote_get("https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest");
            if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
                return $transient;
            }
            $remote = json_decode(wp_remote_retrieve_body($response));
            set_transient('my_gh_updater_' . $this->repository, $remote, 12 * HOUR_IN_SECONDS);
        }

        if ($remote && isset($remote->tag_name)) {
            $plugin_data = get_plugin_data($this->file);
            $current_version = $plugin_data['Version'];

            // Compare versions (strip leading 'v' if used in tags)
            $clean_remote_version = ltrim($remote->tag_name, 'v');

            if (version_compare($clean_remote_version, $current_version, '>')) {
                $transient->response[$this->basename] = (object)[
                    'slug' => $this->repository,
                    'plugin' => $this->basename,
                    'new_version' => $clean_remote_version,
                    'url' => $remote->html_url,
                    'package' => $remote->zipball_url, // Direct GitHub zip asset
                ];
            }
        }

        return $transient;
    }

    /**
     * Rename the uncompressed GitHub directory to match the actual plugin slug.
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra = null)
    {
        global $wp_filesystem;

        // Ensure this hook only alters upgrades meant for this plugin
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->basename) {
            $proper_folder_name = dirname($this->basename);
            if ('.' === $proper_folder_name || empty($proper_folder_name)) {
                $proper_folder_name = $this->repository;
            }

            $new_source = trailingslashit($remote_source) . $proper_folder_name;

            if (untrailingslashit($source) !== untrailingslashit($new_source)) {
                $wp_filesystem->move($source, $new_source, true);
                return trailingslashit($new_source);
            }
        }

        return $source;
    }

    public function plugin_popup_handler($res, $action, $args)
    {
        // Only run if this is a plugin information request for our plugin
        if ('plugin_information' !== $action) {
            return $res;
        }

        if (!isset($args->slug) || $args->slug !== $this->repository) {
            return $res;
        }

        // Fetch release info to get the proper tag/version context
        $remote = get_transient('my_gh_updater_' . $this->repository);
        if (!$remote) {
            return $res;
        }

        $tag = isset($remote->tag_name) ? $remote->tag_name : 'main';

        // Fetch the readme.txt file directly from GitHub repository raw URL
        $readme_url = "https://raw.githubusercontent.com/{$this->username}/{$this->repository}/{$tag}/readme.txt";
        $response = wp_remote_get($readme_url);

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return $res;
        }

        $readme_content = wp_remote_retrieve_body($response);

        // Parse the readme.txt content using WordPress's built-in parser
        if (!class_exists('WP_Readme_Parser')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        // Fallback parser if WP_Readme_Parser isn't directly exposed
        $parser = new WP_Readme_Parser();
        $readme_data = $parser->parse_readme_contents($readme_content);

        // Build the response object expected by the WordPress modal
        $plugin_data = get_plugin_data($this->file);

        $obj = new stdClass();
        $obj->nameppo = $plugin_data['Name'];
        $obj->slug = $this->repository;
        $obj->version = ltrim($remote->tag_name, 'v');
        $obj->author = $plugin_data['AuthorName'];
        $obj->homepage = $plugin_data['PluginURI'];
        $obj->requires = isset($readme_data['requires_at_least']) ? $readme_data['requires_at_least'] : '5.0';
        $obj->tested = isset($readme_data['tested_up_to']) ? $readme_data['tested_up_to'] : '';
        $obj->download_link = $remote->zipball_url;

        // Map sections from readme.txt (Description, Changelog, FAQ, etc.)
        $obj->sections = [];
        if (!empty($readme_data['sections'])) {
            $obj->sections = $readme_data['sections'];
        } else {
            // Fallback default sections if parsing fails
            $obj->sections['description'] = $plugin_data['Description'];
        }

        // Map upgrade notices if available
        if (!empty($readme_data['upgrade_notice'])) {
            $obj->upgrade_notice = $readme_data['upgrade_notice'];
        }

        return $obj;
    }
}