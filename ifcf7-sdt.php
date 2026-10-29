<?php
/**
 * Plugin Name: IFCF7 Submissions to DataTables
 * Plugin URI: https://github.com/ifwp/ifcf7-sdt
 * Description: Browse, filter and export IFCF7 submissions with jQuery DataTables (server-side processing).
 * Version: 6.10.29
 * Requires at least: 5.0
 * Requires PHP: 5.6
 * Author: Luis del Cid
 * Author URI: http://luisdelcid.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ifcf7-sdt
 */

defined('ABSPATH') or die('Hi there! I\'m just a plugin, not much I can do when called directly.');
register_activation_hook(__FILE__, function(){
	update_option('ifcf7_sdt_flush_rewrite_rules', 1);
});
register_deactivation_hook(__FILE__, function(){
	delete_option('ifcf7_sdt_flush_rewrite_rules');
	delete_option('rewrite_rules'); // Rebuilt on the next request, this one still has our rule registered.
	delete_transient('ifcf7_sdt_authors');
	delete_transient('ifcf7_sdt_meta_keys');
});
add_action('admin_notices', function(){
	if(did_action('ifwp_plugins_loaded')){
		return;
	}
	echo '<div class="notice notice-error"><p><strong>' . esc_html__('Error:') . '</strong> ' . sprintf(esc_html__('The %s plugin is required.', 'contact-form-7'), '<strong>IFWP</strong>') . '</p></div>';
});
add_action('ifwp_plugins_loaded', function(){
	ifwp_build_update_checker('https://github.com/ifwp/ifcf7-sdt', __FILE__, 'ifcf7-sdt');
	if(!defined('WPCF7_VERSION')){
		return; // IFCF7 already asks for Contact Form 7.
	}
	if(!class_exists('IFCF7\Storage')){
		ifwp_add_admin_notice('<strong>' . __('Error:') . '</strong> ' . sprintf(__('The %s plugin is required.', 'contact-form-7'), '<strong>IFCF7</strong>'), 'error');
		return;
	}
	ifwp_plugin_load('IFCF7_SDT');
// Priority 11, and not the default 10, on purpose. WordPress does sort active_plugins, and a
// hyphen (45) sorts before a slash (47): "ifcf7-sdt/ifcf7-sdt.php" is loaded before
// "ifcf7/ifcf7.php". At the same priority this callback would run first, before IFCF7 had loaded
// its own classes, and the check above would report it missing while being perfectly installed.
}, 11);
