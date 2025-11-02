<?php
/**
 * Custom Options Management
 *
 * Isolates plugin options from WordPress wp_options table.
 * Only loads data when plugin is actively being used.
 *
 * @package NoBloat_User_Foundry
 * @since 1.0.0
 */

/* Prevent direct access */
if (!defined('ABSPATH')) {
    exit;
}

class NBUF_Options {

    /**
     * In-memory cache for loaded options
     *
     * @var array
     */
    private static $cache = array();

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = null;

    /**
     * Initialize table name
     * Called automatically on first use
     */
    private static function init() {
        if (self::$table_name === null) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'nbuf_options';
        }
    }

    /**
     * Get option value
     *
     * @param string $key Option name
     * @param mixed $default Default value if not found
     * @return mixed Option value or default
     */
    public static function get($key, $default = false) {
        /* Check cache first */
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        global $wpdb;
        self::init();

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM " . self::$table_name . " WHERE option_name = %s",
            $key
        ));

        if ($value === null) {
            return $default;
        }

        /* Unserialize if needed and cache */
        $value = maybe_unserialize($value);
        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * Update or insert option value
     *
     * @param string $key Option name
     * @param mixed $value Option value
     * @param bool $autoload Whether to autoload (default: false)
     * @param string $group Option group: 'settings', 'templates', 'css', 'system' (default: 'settings')
     * @return bool Success
     */
    public static function update($key, $value, $autoload = false, $group = 'settings') {
        global $wpdb;
        self::init();

        $serialized_value = maybe_serialize($value);

        $result = $wpdb->replace(
            self::$table_name,
            array(
                'option_name' => $key,
                'option_value' => $serialized_value,
                'autoload' => $autoload ? 1 : 0,
                'option_group' => $group,
            ),
            array('%s', '%s', '%d', '%s')
        );

        /* Update cache */
        if ($result !== false) {
            self::$cache[$key] = $value;
        }

        return $result !== false;
    }

    /**
     * Delete option
     *
     * @param string $key Option name
     * @return bool Success
     */
    public static function delete($key) {
        global $wpdb;
        self::init();

        $result = $wpdb->delete(
            self::$table_name,
            array('option_name' => $key),
            array('%s')
        );

        /* Clear from cache */
        unset(self::$cache[$key]);

        return $result !== false;
    }

    /**
     * Get multiple options at once (batch query)
     * More efficient than calling get() multiple times
     *
     * @param array $keys Array of option names
     * @return array Associative array of option values (key => value)
     */
    public static function get_multiple($keys) {
        if (empty($keys)) {
            return array();
        }

        global $wpdb;
        self::init();

        /* Build placeholders for IN clause */
        $placeholders = implode(',', array_fill(0, count($keys), '%s'));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM " . self::$table_name . " WHERE option_name IN ($placeholders)",
            $keys
        ));

        $options = array();
        foreach ($results as $row) {
            $value = maybe_unserialize($row->option_value);
            $options[$row->option_name] = $value;
            self::$cache[$row->option_name] = $value;
        }

        return $options;
    }

    /**
     * Get all options in a specific group
     * Useful for loading all settings or all templates at once
     *
     * @param string $group Option group: 'settings', 'templates', 'css', 'system'
     * @return array Associative array of option values (key => value)
     */
    public static function get_group($group) {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM " . self::$table_name . " WHERE option_group = %s",
            $group
        ));

        $options = array();
        foreach ($results as $row) {
            $value = maybe_unserialize($row->option_value);
            $options[$row->option_name] = $value;
            self::$cache[$row->option_name] = $value;
        }

        return $options;
    }

    /**
     * Preload autoload options into cache
     * Call this when plugin initializes (only if plugin is being used)
     *
     * This mimics WordPress autoload behavior but only for our plugin
     * and only when the plugin is actually being used on the page.
     */
    public static function preload_autoload() {
        global $wpdb;
        self::init();

        $results = $wpdb->get_results(
            "SELECT option_name, option_value FROM " . self::$table_name . " WHERE autoload = 1"
        );

        foreach ($results as $row) {
            self::$cache[$row->option_name] = maybe_unserialize($row->option_value);
        }
    }

    /**
     * Clear the in-memory cache
     * Useful for testing or after bulk operations
     */
    public static function clear_cache() {
        self::$cache = array();
    }

    /**
     * Get cache statistics (for debugging)
     *
     * @return array Cache stats
     */
    public static function get_cache_stats() {
        return array(
            'cached_keys' => array_keys(self::$cache),
            'cache_count' => count(self::$cache),
            'cache_size_bytes' => strlen(serialize(self::$cache)),
        );
    }
}
