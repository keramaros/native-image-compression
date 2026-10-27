<?php

class GitHub_Updater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repository;

    public function __construct( $file, $username, $repository ) {
        $this->file       = $file;
        $this->username   = $username;
        $this->repository = $repository;
        $this->basename   = plugin_basename( $file );

        add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );
    }

    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Fetch release info from GitHub (cached for 12 hours)
        $remote = get_transient( 'my_gh_updater_' . $this->repository );
        if ( false === $remote ) {
            $response = wp_remote_get( "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest" );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
                return $transient;
            }
            $remote = json_decode( wp_remote_retrieve_body( $response ) );
            set_transient( 'my_gh_updater_' . $this->repository, $remote, 12 * HOUR_IN_SECONDS );
        }

        if ( $remote && isset( $remote->tag_name ) ) {
            $plugin_data = get_plugin_data( $this->file );
            $current_version = $plugin_data['Version'];

            // Compare versions (strip leading 'v' if used in tags)
            $clean_remote_version = ltrim( $remote->tag_name, 'v' );

            if ( version_compare( $clean_remote_version, $current_version, '>' ) ) {
                $transient->response[ $this->basename ] = (object) [
                    'slug'        => $this->repository,
                    'plugin'      => $this->basename,
                    'new_version' => $clean_remote_version,
                    'url'         => $remote->html_url,
                    'package'     => $remote->zipball_url, // Direct GitHub zip asset
                ];
            }
        }

        return $transient;
    }
}