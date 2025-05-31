<?php
/**
 * Register all actions and filters for the plugin
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    WooCommerce_Price_History_Compliance
 * @subpackage WooCommerce_Price_History_Compliance/includes
 * @author     Your Name <email@example.com>
 */
class WPHC_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
     */
    protected $filters;

    /**
     * The array of shortcodes registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $shortcodes    The shortcodes registered with WordPress to fire when the plugin loads.
     */
    protected $shortcodes;

    /**
     * Initialize the collections used to maintain the actions, filters, and shortcodes.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
        $this->shortcodes = array();
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string $hook             The name of the WordPress action that is being registered.
     * @param    object $component        A reference to the instance of the object on which the action is defined.
     * @param    string $callback         The name of the function definition on the $component.
     * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string $hook             The name of the WordPress filter that is being registered.
     * @param    object $component        A reference to the instance of the object on which the filter is defined.
     * @param    string $callback         The name of the function definition on the $component.
     * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new shortcode to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string $tag              The name of the new shortcode.
     * @param    object $component        A reference to the instance of the object on which the shortcode is defined.
     * @param    string $callback         The name of the function that defines the shortcode.
     * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
     */
    public function add_shortcode($tag, $component, $callback, $priority = 10) {
        $this->shortcodes = $this->add($this->shortcodes, $tag, $component, $callback, $priority, 2);
    }

    /**
     * A utility function that is used to register the actions, filters, and shortcodes into a single
     * collection.
     *
     * @since    1.0.0
     * @access   private
     * @param    array  $hooks            The collection of hooks that is being registered (that is, actions, filters, or shortcodes).
     * @param    string $hook             The name of the WordPress filter that is being registered.
     * @param    object $component        A reference to the instance of the object on which the filter is defined.
     * @param    string $callback         The name of the function definition on the $component.
     * @param    int    $priority         The priority at which the function should be fired.
     * @param    int    $accepted_args    The number of arguments that should be passed to the $callback.
     * @return   array                    The collection of actions and filters registered with WordPress.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters, actions, and shortcodes with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        // Register all actions
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all filters
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                array($hook['component'], $hook['callback']),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // Register all shortcodes
        foreach ($this->shortcodes as $hook) {
            add_shortcode(
                $hook['hook'],
                array($hook['component'], $hook['callback'])
            );
        }
    }

    /**
     * Remove a specific action from the collection.
     *
     * @since    1.0.0
     * @param    string $hook       The name of the WordPress action to remove.
     * @param    object $component  The component object.
     * @param    string $callback   The callback function name.
     * @param    int    $priority   The priority of the action.
     */
    public function remove_action($hook, $component, $callback, $priority = 10) {
        $this->actions = $this->remove($this->actions, $hook, $component, $callback, $priority);
    }

    /**
     * Remove a specific filter from the collection.
     *
     * @since    1.0.0
     * @param    string $hook       The name of the WordPress filter to remove.
     * @param    object $component  The component object.
     * @param    string $callback   The callback function name.
     * @param    int    $priority   The priority of the filter.
     */
    public function remove_filter($hook, $component, $callback, $priority = 10) {
        $this->filters = $this->remove($this->filters, $hook, $component, $callback, $priority);
    }

    /**
     * Remove a specific shortcode from the collection.
     *
     * @since    1.0.0
     * @param    string $tag        The shortcode tag to remove.
     * @param    object $component  The component object.
     * @param    string $callback   The callback function name.
     */
    public function remove_shortcode($tag, $component, $callback) {
        $this->shortcodes = $this->remove($this->shortcodes, $tag, $component, $callback, 10);
    }

    /**
     * A utility function that is used to remove actions, filters, and shortcodes from collections.
     *
     * @since    1.0.0
     * @access   private
     * @param    array  $hooks      The collection of hooks.
     * @param    string $hook       The name of the WordPress hook.
     * @param    object $component  A reference to the instance of the object.
     * @param    string $callback   The name of the function definition on the $component.
     * @param    int    $priority   The priority at which the function should be fired.
     * @return   array              The collection with the specified hook removed.
     */
    private function remove($hooks, $hook, $component, $callback, $priority) {
        foreach ($hooks as $key => $registered_hook) {
            if ($registered_hook['hook'] === $hook &&
                $registered_hook['component'] === $component &&
                $registered_hook['callback'] === $callback &&
                $registered_hook['priority'] === $priority) {
                unset($hooks[$key]);
            }
        }

        return array_values($hooks); // Re-index array
    }

    /**
     * Get all registered actions.
     *
     * @since    1.0.0
     * @return   array    The registered actions.
     */
    public function get_actions() {
        return $this->actions;
    }

    /**
     * Get all registered filters.
     *
     * @since    1.0.0
     * @return   array    The registered filters.
     */
    public function get_filters() {
        return $this->filters;
    }

    /**
     * Get all registered shortcodes.
     *
     * @since    1.0.0
     * @return   array    The registered shortcodes.
     */
    public function get_shortcodes() {
        return $this->shortcodes;
    }

    /**
     * Check if a specific hook is registered.
     *
     * @since    1.0.0
     * @param    string $hook_name   The hook name to check.
     * @param    string $type        The type of hook (action, filter, shortcode).
     * @return   bool                True if the hook is registered, false otherwise.
     */
    public function is_hook_registered($hook_name, $type = 'action') {
        $hooks = array();
        
        switch ($type) {
            case 'action':
                $hooks = $this->actions;
                break;
            case 'filter':
                $hooks = $this->filters;
                break;
            case 'shortcode':
                $hooks = $this->shortcodes;
                break;
            default:
                return false;
        }

        foreach ($hooks as $hook) {
            if ($hook['hook'] === $hook_name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get hooks by component.
     *
     * @since    1.0.0
     * @param    object $component   The component object to search for.
     * @param    string $type        The type of hook (action, filter, shortcode, or 'all').
     * @return   array               Array of hooks registered by the component.
     */
    public function get_hooks_by_component($component, $type = 'all') {
        $result = array();
        $hook_types = array();

        if ($type === 'all') {
            $hook_types = array('actions', 'filters', 'shortcodes');
        } else {
            $hook_types = array($type . 's'); // Convert 'action' to 'actions', etc.
        }

        foreach ($hook_types as $hook_type) {
            if (property_exists($this, $hook_type)) {
                foreach ($this->$hook_type as $hook) {
                    if ($hook['component'] === $component) {
                        $result[$hook_type][] = $hook;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Clear all registered hooks.
     *
     * @since    1.0.0
     * @param    string $type    Optional. The type of hooks to clear (action, filter, shortcode, or 'all').
     */
    public function clear_hooks($type = 'all') {
        switch ($type) {
            case 'action':
                $this->actions = array();
                break;
            case 'filter':
                $this->filters = array();
                break;
            case 'shortcode':
                $this->shortcodes = array();
                break;
            case 'all':
            default:
                $this->actions = array();
                $this->filters = array();
                $this->shortcodes = array();
                break;
        }
    }

    /**
     * Get total count of registered hooks.
     *
     * @since    1.0.0
     * @return   array    Array with counts for each hook type.
     */
    public function get_hook_counts() {
        return array(
            'actions'    => count($this->actions),
            'filters'    => count($this->filters),
            'shortcodes' => count($this->shortcodes),
            'total'      => count($this->actions) + count($this->filters) + count($this->shortcodes)
        );
    }

    /**
     * Register WordPress hooks in bulk.
     *
     * @since    1.0.0
     * @param    array $hooks_config    Array of hook configurations.
     */
    public function register_bulk_hooks($hooks_config) {
        if (!is_array($hooks_config)) {
            return;
        }

        foreach ($hooks_config as $hook_config) {
            if (!isset($hook_config['type']) || !isset($hook_config['hook']) || 
                !isset($hook_config['component']) || !isset($hook_config['callback'])) {
                continue;
            }

            $priority = isset($hook_config['priority']) ? $hook_config['priority'] : 10;
            $accepted_args = isset($hook_config['accepted_args']) ? $hook_config['accepted_args'] : 1;

            switch ($hook_config['type']) {
                case 'action':
                    $this->add_action(
                        $hook_config['hook'],
                        $hook_config['component'],
                        $hook_config['callback'],
                        $priority,
                        $accepted_args
                    );
                    break;
                case 'filter':
                    $this->add_filter(
                        $hook_config['hook'],
                        $hook_config['component'],
                        $hook_config['callback'],
                        $priority,
                        $accepted_args
                    );
                    break;
                case 'shortcode':
                    $this->add_shortcode(
                        $hook_config['hook'],
                        $hook_config['component'],
                        $hook_config['callback'],
                        $priority
                    );
                    break;
            }
        }
    }

    /**
     * Debug function to output all registered hooks.
     *
     * @since    1.0.0
     * @param    bool $return    Whether to return the output instead of echoing it.
     * @return   string|void     Debug output if $return is true.
     */
    public function debug_hooks($return = false) {
        if (!wphc_is_development_mode()) {
            return;
        }

        $output = "=== WPHC Loader Debug ===\n";
        $output .= "Actions: " . count($this->actions) . "\n";
        $output .= "Filters: " . count($this->filters) . "\n";
        $output .= "Shortcodes: " . count($this->shortcodes) . "\n\n";

        $output .= "=== ACTIONS ===\n";
        foreach ($this->actions as $action) {
            $component_name = is_object($action['component']) ? get_class($action['component']) : 'Unknown';
            $output .= sprintf(
                "Hook: %s | Component: %s | Callback: %s | Priority: %d | Args: %d\n",
                $action['hook'],
                $component_name,
                $action['callback'],
                $action['priority'],
                $action['accepted_args']
            );
        }

        $output .= "\n=== FILTERS ===\n";
        foreach ($this->filters as $filter) {
            $component_name = is_object($filter['component']) ? get_class($filter['component']) : 'Unknown';
            $output .= sprintf(
                "Hook: %s | Component: %s | Callback: %s | Priority: %d | Args: %d\n",
                $filter['hook'],
                $component_name,
                $filter['callback'],
                $filter['priority'],
                $filter['accepted_args']
            );
        }

        $output .= "\n=== SHORTCODES ===\n";
        foreach ($this->shortcodes as $shortcode) {
            $component_name = is_object($shortcode['component']) ? get_class($shortcode['component']) : 'Unknown';
            $output .= sprintf(
                "Tag: %s | Component: %s | Callback: %s\n",
                $shortcode['hook'],
                $component_name,
                $shortcode['callback']
            );
        }

        if ($return) {
            return $output;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Debug output for development only
        echo '<pre>' . esc_html($output) . '</pre>';
    }
}