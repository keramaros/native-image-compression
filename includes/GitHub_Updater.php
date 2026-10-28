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
        add_filter('upgrader_source_selection', [$this, 'upgrader_source_selection'], 1, 4);
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
                $package = isset($remote->zipball_url) ? $remote->zipball_url : '';
                if (!empty($remote->assets) && is_array($remote->assets)) {
                    foreach ($remote->assets as $asset) {
                        if (!empty($asset->browser_download_url) && preg_match('/\.zip$/i', $asset->name)) {
                            $package = $asset->browser_download_url;
                            break;
                        }
                    }
                }

                $transient->response[$this->basename] = (object)[
                    'slug' => $this->repository,
                    'plugin' => $this->basename,
                    'new_version' => $clean_remote_version,
                    'url' => $remote->html_url,
                    'package' => $package,
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

        if (is_wp_error($source)) {
            return $source;
        }

        // Determine if this source belongs to our plugin
        $is_our_plugin = false;

        if (isset($hook_extra['plugin']) && ($hook_extra['plugin'] === $this->basename || dirname($hook_extra['plugin']) === $this->repository)) {
            $is_our_plugin = true;
        } elseif ($wp_filesystem && $wp_filesystem->exists(trailingslashit($source) . basename($this->file))) {
            $is_our_plugin = true;
        }

        if (!$is_our_plugin) {
            return $source;
        }

        $proper_folder_name = !empty($this->repository) ? $this->repository : 'native-image-compression';

        $source_clean = untrailingslashit($source);
        $remote_source_clean = untrailingslashit($remote_source);
        $new_source = $remote_source_clean . '/' . $proper_folder_name;

        if ($source_clean === $new_source) {
            return trailingslashit($source);
        }

        // Delete destination in the working folder if it already exists
        if ($wp_filesystem->exists($new_source)) {
            $wp_filesystem->delete($new_source, true);
        }

        // Move/rename the unzipped folder
        $moved = $wp_filesystem->move($source_clean, $new_source, true);

        if (!$moved) {
            // Fallback: copy directory and delete source if move failed
            if (function_exists('copy_dir') && copy_dir($source_clean, $new_source)) {
                $wp_filesystem->delete($source_clean, true);
                $moved = true;
            } elseif (@rename($source_clean, $new_source)) {
                $moved = true;
            }
        }

        if ($moved) {
            return trailingslashit($new_source);
        }

        return new WP_Error(
            'source_rename_failed',
            sprintf(__('Unable to rename update folder from %1$s to %2$s.'), basename($source_clean), $proper_folder_name)
        );
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

        $readme_data = [
            'requires_at_least' => '',
            'tested_up_to'      => '',
            'requires_php'      => '',
            'sections'          => [],
            'upgrade_notice'    => [],
        ];

        // Fetch the readme.txt file directly from GitHub repository raw URL
        $readme_url = "https://raw.githubusercontent.com/{$this->username}/{$this->repository}/{$tag}/readme.txt";
        $response = wp_remote_get($readme_url);

        if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
            $readme_content = wp_remote_retrieve_body($response);
            $readme_data = $this->parse_readme($readme_content);
        }

        // Build the response object expected by the WordPress modal
        $plugin_data = get_plugin_data($this->file);

        $obj = new stdClass();
        $obj->name = !empty($plugin_data['Name']) ? $plugin_data['Name'] : $this->repository;
        $obj->slug = $this->repository;
        $obj->version = ltrim($remote->tag_name, 'v');
        $obj->author = !empty($plugin_data['AuthorName']) ? $plugin_data['AuthorName'] : $this->username;
        $obj->author_profile = !empty($plugin_data['AuthorURI']) ? $plugin_data['AuthorURI'] : '';
        $obj->homepage = !empty($plugin_data['PluginURI']) ? $plugin_data['PluginURI'] : '';
        $obj->requires = !empty($readme_data['requires_at_least']) ? $readme_data['requires_at_least'] : (isset($plugin_data['RequiresWP']) ? $plugin_data['RequiresWP'] : '5.8');
        $obj->tested = !empty($readme_data['tested_up_to']) ? $readme_data['tested_up_to'] : '';
        $obj->requires_php = !empty($readme_data['requires_php']) ? $readme_data['requires_php'] : (isset($plugin_data['RequiresPHP']) ? $plugin_data['RequiresPHP'] : '7.4');
        $package = isset($remote->zipball_url) ? $remote->zipball_url : '';
        if (!empty($remote->assets) && is_array($remote->assets)) {
            foreach ($remote->assets as $asset) {
                if (!empty($asset->browser_download_url) && preg_match('/\.zip$/i', $asset->name)) {
                    $package = $asset->browser_download_url;
                    break;
                }
            }
        }
        $obj->download_link = $package;

        // Map sections from readme.txt (Description, Installation, FAQ, Changelog, etc.)
        $obj->sections = [];
        if (!empty($readme_data['sections'])) {
            $obj->sections = $readme_data['sections'];
        } else {
            // Fallback default sections if parsing fails
            $obj->sections['description'] = !empty($plugin_data['Description']) ? $plugin_data['Description'] : '';
        }

        // Map upgrade notices if available
        if (!empty($readme_data['upgrade_notice'])) {
            $obj->upgrade_notice = $readme_data['upgrade_notice'];
        }

        return $obj;
    }

    /**
     * Parse WordPress readme.txt content into structured data.
     *
     * @param string $content
     * @return array
     */
    private function parse_readme($content)
    {
        $data = [
            'requires_at_least' => '',
            'tested_up_to'      => '',
            'requires_php'      => '',
            'sections'          => [],
            'upgrade_notice'    => [],
        ];

        if (empty($content)) {
            return $data;
        }

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Parse headers
        if (preg_match('/Requires at least:\s*(.+)$/mi', $content, $matches)) {
            $data['requires_at_least'] = trim($matches[1]);
        }
        if (preg_match('/Tested up to:\s*(.+)$/mi', $content, $matches)) {
            $data['tested_up_to'] = trim($matches[1]);
        }
        if (preg_match('/Requires PHP:\s*(.+)$/mi', $content, $matches)) {
            $data['requires_php'] = trim($matches[1]);
        }

        // Split content into sections by == Section Title ==
        $parts = preg_split('/^==\s*([^=]+?)\s*==\s*$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!empty($parts) && count($parts) > 1) {
            $count = count($parts);
            for ($i = 1; $i < $count; $i += 2) {
                $title = trim($parts[$i]);
                $body = isset($parts[$i + 1]) ? trim($parts[$i + 1]) : '';

                $slug = strtolower($title);
                $slug = str_replace(' ', '_', $slug);
                if ('frequently_asked_questions' === $slug) {
                    $slug = 'faq';
                }

                $html = $this->format_readme_section($body);

                if ('upgrade_notice' === $slug) {
                    $data['upgrade_notice'] = $body;
                } else {
                    $data['sections'][$slug] = $html;
                }
            }
        }

        return $data;
    }

    /**
     * Format readme section text to basic HTML.
     *
     * @param string $text
     * @return string
     */
    private function format_readme_section($text)
    {
        if (empty($text)) {
            return '';
        }

        // Convert subheaders: = Header = -> <h4>Header</h4>
        $text = preg_replace('/^=\s*(.+?)\s*=\s*$/m', '<h4>$1</h4>', $text);

        // Convert unordered lists
        $lines = explode("\n", $text);
        $in_list = false;
        $formatted_lines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^[\*\-]\s+(.+)$/', $trimmed, $matches)) {
                if (!$in_list) {
                    $formatted_lines[] = '<ul>';
                    $in_list = true;
                }
                $formatted_lines[] = '<li>' . $matches[1] . '</li>';
            } else {
                if ($in_list) {
                    $formatted_lines[] = '</ul>';
                    $in_list = false;
                }
                $formatted_lines[] = $line;
            }
        }
        if ($in_list) {
            $formatted_lines[] = '</ul>';
        }

        $text = implode("\n", $formatted_lines);

        // Convert bold **bold** and `code`
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);

        // Convert paragraphs
        if (function_exists('wpautop')) {
            $text = wpautop($text);
        } else {
            $paragraphs = preg_split('/\n{2,}/', trim($text));
            $formatted_paras = [];
            foreach ($paragraphs as $para) {
                $para = trim($para);
                if (empty($para)) {
                    continue;
                }
                if (strpos($para, '<h') === 0 || strpos($para, '<ul') === 0) {
                    $formatted_paras[] = $para;
                } else {
                    $formatted_paras[] = '<p>' . nl2br($para) . '</p>';
                }
            }
            $text = implode("\n", $formatted_paras);
        }

        return $text;
    }
}