<?php
/*
Plugin Name: Greenhouse Octagon Job Board
Description: Import and display job listings from Greenhouse API with search, filters, and application forms
Version: 1.0.0
Author: Shajibur Rahman
Author URI: https://shojibur.com
License: GPLv2 or later
Text Domain: greenhouse-octagon
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GH_OCTAGON_VERSION', '1.1.0');
define('GH_OCTAGON_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GH_OCTAGON_PLUGIN_URL', plugin_dir_url(__FILE__));

// Activation hook
register_activation_hook(__FILE__, 'gh_octagon_activate');
function gh_octagon_activate() {
    gh_octagon_create_table();
    gh_octagon_schedule_cron();
    // Import jobs on activation
    gh_octagon_import_jobs();
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'gh_octagon_deactivate');
function gh_octagon_deactivate() {
    $timestamp = wp_next_scheduled('gh_octagon_import_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'gh_octagon_import_hook');
    }
    flush_rewrite_rules();
}

// Create database table
function gh_octagon_create_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        board_name varchar(255) NOT NULL,
        gh_id bigint(20) NOT NULL,
        internal_job_id bigint(20),
        requisition_id text,
        absolute_url text,
        title text NOT NULL,
        location text,
        location_city text,
        location_state text,
        location_country text,
        employment_type text,
        content longtext,
        metadata longtext,
        departments longtext,
        offices longtext,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY board_gh_id (board_name, gh_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue styles - ALWAYS enqueue since CSS is scoped to .greenhouse
add_action('wp_enqueue_scripts', 'gh_octagon_enqueue_assets');
function gh_octagon_enqueue_assets() {
    // Enqueue Font Awesome
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');

    // Always enqueue our scoped CSS
    wp_enqueue_style('gh-octagon-style', GH_OCTAGON_PLUGIN_URL . 'css/style.css', array(), GH_OCTAGON_VERSION);
    wp_enqueue_script('gh-octagon-script', GH_OCTAGON_PLUGIN_URL . 'js/script.js', array('jquery'), GH_OCTAGON_VERSION, true);

    wp_localize_script('gh-octagon-script', 'ghOctagon', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gh_octagon_nonce')
    ));
}

// Import jobs from Greenhouse API
function gh_octagon_import_jobs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    // Get configured boards
    $boards = get_option('gh_octagon_boards', array());

    // Backward compatibility: check for old single API URL setting
    if (empty($boards)) {
        $old_api_url = get_option('gh_octagon_api_url');
        if (!empty($old_api_url)) {
            // Extract board name from URL
            preg_match('/\/boards\/([^\/]+)\//', $old_api_url, $matches);
            $board_name = isset($matches[1]) ? $matches[1] : 'octagon';
            $boards = array($board_name => $old_api_url);
        } else {
            error_log('Greenhouse: No boards configured');
            return false;
        }
    }

    // Clear transients
    gh_octagon_clear_transients();

    $success_count = 0;

    // Import jobs from each board
    foreach ($boards as $board_name => $api_url) {
        $response = wp_remote_get($api_url, array('timeout' => 30));

        if (is_wp_error($response)) {
            error_log('Greenhouse API Error for board ' . $board_name . ': ' . $response->get_error_message());
            continue;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['jobs'])) {
            error_log('Greenhouse: No jobs found for board ' . $board_name);
            continue;
        }

        // Delete existing jobs for this board only
        $wpdb->delete($table_name, array('board_name' => $board_name), array('%s'));

        foreach ($data['jobs'] as $job) {
        // Parse location with better logic
        $location_full = $job['location']['name'];
        $location_city = '';
        $location_state = '';
        $location_country = '';

        // Known country mapping
        $known_countries = array(
            'United States', 'United Kingdom', 'Australia', 'Singapore',
            'Germany', 'Canada', 'France', 'Spain', 'Italy', 'Netherlands',
            'United States of America'
        );

        // Check for semicolon format: "Country; City"
        if (strpos($location_full, ';') !== false) {
            $parts = explode(';', $location_full);
            $location_country = trim($parts[0]);
            $location_city = isset($parts[1]) ? trim($parts[1]) : '';
        }
        // Check for comma format: "City, State, Country" or "City, Country"
        elseif (strpos($location_full, ',') !== false) {
            $location_parts = array_map('trim', explode(',', $location_full));

            if (count($location_parts) >= 3) {
                // Format: City, State, Country
                $location_city = $location_parts[0];
                $location_state = $location_parts[1];
                $location_country = $location_parts[2];
            } elseif (count($location_parts) == 2) {
                // Format: City, Country
                $location_city = $location_parts[0];
                $location_country = $location_parts[1];
            } else {
                $location_city = $location_parts[0];
                $location_country = $location_parts[0];
            }
        }
        // Single value - check if it's a known country
        else {
            $is_country = false;
            foreach ($known_countries as $country) {
                if (stripos($location_full, $country) !== false) {
                    $location_country = $location_full;
                    $is_country = true;
                    break;
                }
            }
            if (!$is_country) {
                $location_city = $location_full;
                $location_country = $location_full; // Fallback
            }
        }

        // Normalize country names
        if (stripos($location_country, 'United States') !== false) {
            $location_country = 'United States';
        } elseif (stripos($location_country, 'United Kingdom') !== false) {
            $location_country = 'United Kingdom';
        }

        // Handle cities without country - map to known countries
        $city_country_map = array(
            'Frankfurt' => 'Germany',
            'München' => 'Germany',
            'Munich' => 'Germany',
            'Berlin' => 'Germany',
            'London' => 'United Kingdom',
            'Singapore' => 'Singapore',
            'Sydney' => 'Australia',
            'Melbourne' => 'Australia'
        );

        if (empty($location_country) || $location_country === $location_city) {
            if (isset($city_country_map[$location_city])) {
                $location_country = $city_country_map[$location_city];
            }
        }

        // Extract employment type from metadata
        $employment_type = '';
        if (!empty($job['metadata'])) {
            foreach ($job['metadata'] as $meta) {
                if (isset($meta['name']) && $meta['name'] === 'Employment Type' && !empty($meta['value'])) {
                    $employment_type = $meta['value'];
                    break;
                }
            }
        }

            $wpdb->replace(
                $table_name,
                array(
                    'board_name' => $board_name,
                    'gh_id' => $job['id'],
                    'internal_job_id' => $job['internal_job_id'],
                    'requisition_id' => $job['requisition_id'],
                    'absolute_url' => $job['absolute_url'],
                    'title' => $job['title'],
                    'location' => $job['location']['name'],
                    'location_city' => $location_city,
                    'location_state' => $location_state,
                    'location_country' => $location_country,
                    'employment_type' => $employment_type,
                    'content' => html_entity_decode($job['content']),
                    'metadata' => json_encode($job['metadata']),
                    'departments' => json_encode($job['departments']),
                    'offices' => json_encode($job['offices']),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }

        $success_count++;
    }

    update_option('gh_octagon_last_sync', current_time('mysql'));
    return $success_count > 0;
}

// Clear all transients
function gh_octagon_clear_transients() {
    global $wpdb;
    $transient_like = $wpdb->esc_like('_transient_gh_octagon_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like));
}

// Schedule cron job
function gh_octagon_schedule_cron() {
    if (!wp_next_scheduled('gh_octagon_import_hook')) {
        $interval = get_option('gh_octagon_sync_interval', 'daily');
        wp_schedule_event(time(), $interval, 'gh_octagon_import_hook');
    }
}
add_action('gh_octagon_import_hook', 'gh_octagon_import_jobs');

// Get unique departments with counts based on current filters
function gh_octagon_get_departments($filters = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    // Build WHERE clause based on filters (excluding department itself)
    $where = array('1=1');
    $where_values = array();

    if (!empty($filters['search'])) {
        $where[] = '(title LIKE %s OR content LIKE %s OR requisition_id LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }

    if (!empty($filters['country'])) {
        $where[] = 'location_country = %s';
        $where_values[] = $filters['country'];
    }

    if (!empty($filters['location'])) {
        $where[] = 'location_city = %s';
        $where_values[] = $filters['location'];
    }

    if (!empty($filters['employment_type'])) {
        $where[] = 'employment_type = %s';
        $where_values[] = $filters['employment_type'];
    }

    if (!empty($filters['board'])) {
        $where[] = 'board_name = %s';
        $where_values[] = $filters['board'];
    }

    $where_sql = implode(' AND ', $where);

    // Get jobs matching filters
    if (!empty($where_values)) {
        $jobs = $wpdb->get_results($wpdb->prepare("SELECT departments FROM $table_name WHERE $where_sql", $where_values));
    } else {
        $jobs = $wpdb->get_results("SELECT departments FROM $table_name WHERE $where_sql");
    }

    $departments = array();

    foreach ($jobs as $job) {
        $job_departments = json_decode($job->departments, true);
        if ($job_departments) {
            foreach ($job_departments as $dept) {
                $dept_name = $dept['name'];
                if (!isset($departments[$dept_name])) {
                    $departments[$dept_name] = 0;
                }
                $departments[$dept_name]++;
            }
        }
    }

    return $departments;
}

// Get unique locations (full location string) with counts based on current filters
function gh_octagon_get_locations($filters = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    // Build WHERE clause based on filters (excluding location itself)
    $where = array('location != ""');
    $where_values = array();

    if (!empty($filters['search'])) {
        $where[] = '(title LIKE %s OR content LIKE %s OR requisition_id LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }

    if (!empty($filters['department'])) {
        $where[] = 'departments LIKE %s';
        $where_values[] = '%' . $wpdb->esc_like($filters['department']) . '%';
    }

    if (!empty($filters['country'])) {
        $where[] = 'location_country = %s';
        $where_values[] = $filters['country'];
    }

    if (!empty($filters['employment_type'])) {
        $where[] = 'employment_type = %s';
        $where_values[] = $filters['employment_type'];
    }

    if (!empty($filters['board'])) {
        $where[] = 'board_name = %s';
        $where_values[] = $filters['board'];
    }

    $where_sql = implode(' AND ', $where);

    // Get locations with job counts (using full location string)
    $query = "SELECT location, COUNT(*) as job_count FROM $table_name WHERE $where_sql GROUP BY location ORDER BY location";

    if (!empty($where_values)) {
        $locations = $wpdb->get_results($wpdb->prepare($query, $where_values));
    } else {
        $locations = $wpdb->get_results($query);
    }

    return $locations;
}

// Get unique countries
function gh_octagon_get_countries() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    $countries = $wpdb->get_results("SELECT DISTINCT location_country FROM $table_name WHERE location_country != '' ORDER BY location_country");
    return $countries;
}

// Get unique employment types
function gh_octagon_get_employment_types() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    $types = $wpdb->get_results("SELECT DISTINCT employment_type FROM $table_name WHERE employment_type != '' ORDER BY employment_type");
    return $types;
}

// Get unique boards
function gh_octagon_get_boards() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    $boards = $wpdb->get_results("SELECT DISTINCT board_name FROM $table_name WHERE board_name != '' ORDER BY board_name");
    return $boards;
}

// Get job URL slug based on board
function gh_octagon_get_job_slug($board_name) {
    $board_slugs = get_option('gh_octagon_board_slugs', array());

    // Ensure $board_slugs is an array
    if (!is_array($board_slugs)) {
        $board_slugs = array();
    }

    return isset($board_slugs[$board_name]) ? $board_slugs[$board_name] : 'job';
}

// Job listing shortcode
add_shortcode('gh_octagon_jobs', 'gh_octagon_jobs_shortcode');
function gh_octagon_jobs_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';

    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'board' => '', // Optional: filter by specific board
    ), $atts);

    // Get filters
    $search = isset($_GET['gh_search']) ? sanitize_text_field($_GET['gh_search']) : '';
    $department = isset($_GET['gh_department']) ? sanitize_text_field($_GET['gh_department']) : '';
    $country = isset($_GET['gh_country']) ? sanitize_text_field($_GET['gh_country']) : '';
    $location = isset($_GET['gh_location']) ? sanitize_text_field($_GET['gh_location']) : '';
    $employment_type = isset($_GET['gh_employment_type']) ? sanitize_text_field($_GET['gh_employment_type']) : '';
    $board = isset($_GET['gh_board']) ? sanitize_text_field($_GET['gh_board']) : $atts['board'];

    // Pagination
    $page = isset($_GET['jobpage']) ? max(1, intval($_GET['jobpage'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Build query
    $where = array('1=1');
    $where_values = array();

    if ($search) {
        $where[] = '(title LIKE %s OR content LIKE %s OR requisition_id LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }

    if ($department) {
        $where[] = 'departments LIKE %s';
        $where_values[] = '%' . $wpdb->esc_like($department) . '%';
    }

    if ($country) {
        $where[] = 'location_country = %s';
        $where_values[] = $country;
    }

    if ($location) {
        $where[] = 'location = %s';
        $where_values[] = $location;
    }

    if ($employment_type) {
        $where[] = 'employment_type = %s';
        $where_values[] = $employment_type;
    }

    if ($board) {
        $where[] = 'board_name = %s';
        $where_values[] = $board;
    }

    $where_sql = implode(' AND ', $where);

    // Get total count
    if (!empty($where_values)) {
        $total_jobs = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE $where_sql", $where_values));
    } else {
        $total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where_sql");
    }

    // Get jobs
    $query = "SELECT * FROM $table_name WHERE $where_sql ORDER BY title ASC LIMIT %d OFFSET %d";
    $query_values = array_merge($where_values, array($per_page, $offset));

    if (!empty($where_values)) {
        $jobs = $wpdb->get_results($wpdb->prepare($query, $query_values));
    } else {
        $jobs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE $where_sql ORDER BY title ASC LIMIT %d OFFSET %d", $per_page, $offset));
    }

    // Get filter options with current filters applied
    $current_filters = array(
        'search' => $search,
        'country' => $country,
        'location' => $location,
        'employment_type' => $employment_type,
        'board' => $board
    );

    // Get departments and locations with counts based on other filters
    $departments = gh_octagon_get_departments(array_diff_key($current_filters, array('department' => '')));
    $locations = gh_octagon_get_locations(array_diff_key($current_filters, array('location' => '')));
    $countries = gh_octagon_get_countries();
    $employment_types = gh_octagon_get_employment_types();
    $boards_list = gh_octagon_get_boards();

    // Start output
    ob_start();
    include GH_OCTAGON_PLUGIN_DIR . 'templates/job-listing.php';
    return ob_get_clean();
}

// Add rewrite rules for single job
add_action('init', 'gh_octagon_rewrite_rules');
function gh_octagon_rewrite_rules() {
    // Get all configured board slugs
    $board_slugs = get_option('gh_octagon_board_slugs', array());

    // Ensure $board_slugs is an array
    if (!is_array($board_slugs)) {
        $board_slugs = array();
    }

    // Get unique slugs (default to 'job')
    $slugs = array_unique(array_merge(array('job'), array_values($board_slugs)));

    // Add rewrite rule for each slug
    foreach ($slugs as $slug) {
        if (!empty($slug)) {
            add_rewrite_rule('^' . $slug . '/([0-9]+)/?$', 'index.php?gh_job_id=$matches[1]', 'top');
        }
    }
}

// Add query var
add_filter('query_vars', 'gh_octagon_query_vars');
function gh_octagon_query_vars($vars) {
    $vars[] = 'gh_job_id';
    return $vars;
}

// Template include for single job
add_filter('template_include', 'gh_octagon_template_include');
function gh_octagon_template_include($template) {
    if (get_query_var('gh_job_id')) {
        $custom_template = GH_OCTAGON_PLUGIN_DIR . 'templates/single-job.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}

// Add custom body classes for single job pages
add_filter('body_class', 'gh_octagon_body_classes');
function gh_octagon_body_classes($classes) {
    if (get_query_var('gh_job_id')) {
        // Remove unwanted classes
        $classes = array_diff($classes, array('blog', 'archive', 'category', 'tag'));

        // Add job-specific classes
        $classes[] = 'wp-singular';
        $classes[] = 'page-template-default';
        $classes[] = 'page';
        $classes[] = 'page-id-job-' . get_query_var('gh_job_id');
        $classes[] = 'job';
        $classes[] = 'gh-single-job-page';
    }
    return $classes;
}

// Admin menu
add_action('admin_menu', 'gh_octagon_admin_menu');
function gh_octagon_admin_menu() {
    add_options_page(
        'Greenhouse Octagon Settings',
        'Greenhouse Jobs',
        'manage_options',
        'gh-octagon-settings',
        'gh_octagon_settings_page'
    );
}

// Register settings
add_action('admin_init', 'gh_octagon_register_settings');
function gh_octagon_register_settings() {
    register_setting('gh_octagon_settings', 'gh_octagon_api_url'); // Keep for backward compatibility
    register_setting('gh_octagon_settings', 'gh_octagon_boards');
    register_setting('gh_octagon_settings', 'gh_octagon_board_slugs');
    register_setting('gh_octagon_settings', 'gh_octagon_sync_interval');
    register_setting('gh_octagon_settings', 'gh_octagon_custom_css');
}

// Settings page
function gh_octagon_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle add board
    if (isset($_POST['gh_octagon_add_board']) && check_admin_referer('gh_octagon_add_board')) {
        $board_name = sanitize_text_field($_POST['board_name']);
        $board_url = esc_url_raw($_POST['board_api_url']);
        $board_slug = sanitize_title($_POST['board_slug']);

        if (!empty($board_name) && !empty($board_url)) {
            $boards = get_option('gh_octagon_boards', array());
            if (!is_array($boards)) {
                $boards = array();
            }
            $boards[$board_name] = $board_url;
            update_option('gh_octagon_boards', $boards);

            // Save board slug if provided
            if (!empty($board_slug)) {
                $board_slugs = get_option('gh_octagon_board_slugs', array());
                if (!is_array($board_slugs)) {
                    $board_slugs = array();
                }
                $board_slugs[$board_name] = $board_slug;
                update_option('gh_octagon_board_slugs', $board_slugs);
            }

            // Flush rewrite rules to apply new slug
            flush_rewrite_rules();

            add_settings_error('gh_octagon_messages', 'gh_octagon_message', 'Board added successfully!', 'updated');
        }
    }

    // Handle remove board
    if (isset($_POST['gh_octagon_remove_board']) && check_admin_referer('gh_octagon_remove_board')) {
        $board_name = sanitize_text_field($_POST['remove_board_name']);
        $boards = get_option('gh_octagon_boards', array());
        if (!is_array($boards)) {
            $boards = array();
        }
        if (isset($boards[$board_name])) {
            // Remove board from configuration
            unset($boards[$board_name]);
            update_option('gh_octagon_boards', $boards);

            // Remove board slug
            $board_slugs = get_option('gh_octagon_board_slugs', array());
            if (!is_array($board_slugs)) {
                $board_slugs = array();
            }
            if (isset($board_slugs[$board_name])) {
                unset($board_slugs[$board_name]);
                update_option('gh_octagon_board_slugs', $board_slugs);
            }

            // Delete all jobs associated with this board
            global $wpdb;
            $table_name = $wpdb->prefix . 'gh_octagon_jobs';
            $deleted = $wpdb->delete($table_name, array('board_name' => $board_name), array('%s'));

            // Clear transients
            gh_octagon_clear_transients();

            // Flush rewrite rules
            flush_rewrite_rules();

            add_settings_error('gh_octagon_messages', 'gh_octagon_message',
                sprintf('Board removed successfully! %d jobs deleted.', $deleted), 'updated');
        }
    }

    // Handle manual sync
    if (isset($_POST['gh_octagon_manual_sync']) && check_admin_referer('gh_octagon_manual_sync')) {
        $result = gh_octagon_import_jobs();
        if ($result) {
            add_settings_error('gh_octagon_messages', 'gh_octagon_message', 'Jobs imported successfully!', 'updated');
        } else {
            add_settings_error('gh_octagon_messages', 'gh_octagon_message', 'Failed to import jobs. Check error log.', 'error');
        }
    }

    settings_errors('gh_octagon_messages');

    $boards = get_option('gh_octagon_boards', array());
    if (!is_array($boards)) {
        $boards = array();
    }

    $board_slugs = get_option('gh_octagon_board_slugs', array());
    if (!is_array($board_slugs)) {
        $board_slugs = array();
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <h2>Job Boards Configuration</h2>
        <p>Add multiple Greenhouse job boards to import jobs from different companies or departments.</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Board Name</th>
                    <th>API URL</th>
                    <th>Page Slug</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($boards)): ?>
                    <tr>
                        <td colspan="4">No boards configured yet. Add your first board below.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($boards as $name => $url): ?>
                        <?php
                        $slug = isset($board_slugs[$name]) ? $board_slugs[$name] : 'job';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($name); ?></strong></td>
                            <td><?php echo esc_html($url); ?></td>
                            <td><code><?php echo esc_html($slug); ?></code></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('gh_octagon_remove_board'); ?>
                                    <input type="hidden" name="remove_board_name" value="<?php echo esc_attr($name); ?>" />
                                    <input type="submit" name="gh_octagon_remove_board" class="button button-small" value="Remove"
                                           onclick="return confirm('Are you sure you want to remove this board? All jobs from this board will be deleted.');" />
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h3>Add New Board</h3>
        <form method="post">
            <?php wp_nonce_field('gh_octagon_add_board'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="board_name">Board Name</label>
                    </th>
                    <td>
                        <input type="text" id="board_name" name="board_name" class="regular-text" required
                               placeholder="e.g., octagon, company-name" />
                        <p class="description">A unique identifier for this board (e.g., "octagon", "sales-team")</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="board_api_url">Board API URL</label>
                    </th>
                    <td>
                        <input type="url" id="board_api_url" name="board_api_url" class="regular-text" required
                               placeholder="https://boards-api.greenhouse.io/v1/boards/BOARD_NAME/jobs?content=true" />
                        <p class="description">The full Greenhouse API URL for this board</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="board_slug">Page Slug (Optional)</label>
                    </th>
                    <td>
                        <input type="text" id="board_slug" name="board_slug" class="regular-text"
                               placeholder="e.g., internal-job, career" />
                        <p class="description">Custom URL slug for this board's jobs (e.g., "internal-job"). If not specified, defaults to "job". Jobs will be accessible at: yoursite.com/<strong>slug</strong>/123</p>
                    </td>
                </tr>
            </table>
            <p>
                <input type="submit" name="gh_octagon_add_board" class="button button-primary" value="Add Board" />
            </p>
        </form>

        <hr>

        <form action="options.php" method="post">
            <?php
            settings_fields('gh_octagon_settings');
            do_settings_sections('gh_octagon_settings');
            ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gh_octagon_sync_interval">Sync Interval</label>
                    </th>
                    <td>
                        <select id="gh_octagon_sync_interval" name="gh_octagon_sync_interval">
                            <option value="hourly" <?php selected(get_option('gh_octagon_sync_interval', 'daily'), 'hourly'); ?>>Hourly</option>
                            <option value="twicedaily" <?php selected(get_option('gh_octagon_sync_interval', 'daily'), 'twicedaily'); ?>>Twice Daily</option>
                            <option value="daily" <?php selected(get_option('gh_octagon_sync_interval', 'daily'), 'daily'); ?>>Daily</option>
                        </select>
                        <p class="description">How often to automatically sync jobs from all configured boards</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="gh_octagon_custom_css">Custom CSS</label>
                    </th>
                    <td>
                        <textarea id="gh_octagon_custom_css" name="gh_octagon_custom_css" rows="10" cols="50" class="large-text code"><?php echo esc_textarea(get_option('gh_octagon_custom_css', '')); ?></textarea>
                        <p class="description">Add custom CSS to override default styles</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>

        <hr>

        <h2>Manual Sync</h2>
        <form method="post">
            <?php wp_nonce_field('gh_octagon_manual_sync'); ?>
            <p>Last sync: <?php echo esc_html(get_option('gh_octagon_last_sync', 'Never')); ?></p>
            <p>
                <input type="submit" name="gh_octagon_manual_sync" class="button button-primary" value="Sync All Boards Now" />
            </p>
        </form>
    </div>
    <?php
}

// Add custom CSS to head
add_action('wp_head', 'gh_octagon_custom_css');
function gh_octagon_custom_css() {
    $css = get_option('gh_octagon_custom_css', '');
    if (!empty($css)) {
        echo '<style type="text/css">' . wp_strip_all_tags($css) . '</style>';
    }
}

// Handle application submission
add_action('wp_ajax_gh_octagon_submit_application', 'gh_octagon_submit_application');
add_action('wp_ajax_nopriv_gh_octagon_submit_application', 'gh_octagon_submit_application');

function gh_octagon_submit_application() {
    // Verify nonce
    if (!isset($_POST['gh_octagon_apply_nonce']) || !wp_verify_nonce($_POST['gh_octagon_apply_nonce'], 'gh_octagon_apply')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }

    // Validate required fields
    if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
        wp_send_json_error(array('message' => 'Please fill in all required fields.'));
    }

    // Validate email
    if (!is_email($_POST['email'])) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    }

    // Handle file upload
    if (empty($_FILES['resume'])) {
        wp_send_json_error(array('message' => 'Please upload your resume.'));
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $file = $_FILES['resume'];
    $allowed_types = array('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    if (!in_array($file['type'], $allowed_types)) {
        wp_send_json_error(array('message' => 'Invalid file type. Please upload PDF, DOC, or DOCX.'));
    }

    if ($file['size'] > 5242880) { // 5MB
        wp_send_json_error(array('message' => 'File size too large. Maximum 5MB.'));
    }

    $upload = wp_handle_upload($file, array('test_form' => false));

    if (isset($upload['error'])) {
        wp_send_json_error(array('message' => 'File upload failed: ' . $upload['error']));
    }

    // Prepare application data
    $application_data = array(
        'job_id' => intval($_POST['job_id']),
        'first_name' => sanitize_text_field($_POST['first_name']),
        'last_name' => sanitize_text_field($_POST['last_name']),
        'email' => sanitize_email($_POST['email']),
        'phone' => sanitize_text_field($_POST['phone']),
        'resume_url' => $upload['url'],
        'cover_letter' => sanitize_textarea_field($_POST['cover_letter']),
        'linkedin' => esc_url_raw($_POST['linkedin']),
        'applied_at' => current_time('mysql')
    );

    // Get job details
    global $wpdb;
    $table_name = $wpdb->prefix . 'gh_octagon_jobs';
    $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE gh_id = %d", $application_data['job_id']));

    // Send email notification to admin
    $admin_email = get_option('admin_email');
    $subject = 'New Job Application: ' . $job->title;
    $message = "New application received:\n\n";
    $message .= "Job: " . $job->title . "\n";
    $message .= "Name: " . $application_data['first_name'] . ' ' . $application_data['last_name'] . "\n";
    $message .= "Email: " . $application_data['email'] . "\n";
    $message .= "Phone: " . $application_data['phone'] . "\n";
    $message .= "Resume: " . $application_data['resume_url'] . "\n";
    $message .= "LinkedIn: " . $application_data['linkedin'] . "\n\n";
    $message .= "Cover Letter:\n" . $application_data['cover_letter'];

    wp_mail($admin_email, $subject, $message);

    // Send confirmation email to applicant
    $applicant_subject = 'Application Received - ' . $job->title;
    $applicant_message = "Dear " . $application_data['first_name'] . ",\n\n";
    $applicant_message .= "Thank you for your application for the position of " . $job->title . ".\n\n";
    $applicant_message .= "We have received your application and will review it shortly. If your qualifications match our needs, we will contact you to discuss the next steps.\n\n";
    $applicant_message .= "Best regards,\n";
    $applicant_message .= get_bloginfo('name');

    wp_mail($application_data['email'], $applicant_subject, $applicant_message);

    wp_send_json_success(array('message' => 'Thank you! Your application has been submitted successfully.'));
}
