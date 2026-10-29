<?php

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_capability')){
    function ifcf7_sdt_capability(){
        return apply_filters('ifcf7_sdt_capability', 'edit_others_posts');
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_can_export')){
    /**
     * Downloading personal data is off unless a hook does say otherwise. Return current_user_can()
     * of anything to hand it over to some users only.
     */
    function ifcf7_sdt_can_export(){
        if(!current_user_can(ifcf7_sdt_capability())){
            return false;
        }
        return (bool) apply_filters('ifcf7_sdt_export', false);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_search')){
    /**
     * The search box looking for a text in every column at once. Off unless a hook does say
     * otherwise: it is a LIKE '%...%' over the post fields plus an EXISTS over every searchable
     * meta key, which no index can help with. The search builders do stay there either way.
     */
    function ifcf7_sdt_search(){
        return (bool) apply_filters('ifcf7_sdt_search', false);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_select2')){
    /**
     * Searchable value dropdowns in the search builders.
     */
    function ifcf7_sdt_select2(){
        return (bool) apply_filters('ifcf7_sdt_select2', true);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_columns')){
    function ifcf7_sdt_columns($form_ids = []){
        return IFCF7_SDT\Columns::get_columns($form_ids);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_load_admin_textdomain')){
    /**
     * Reuses the strings WordPress already translates for its own admin.
     */
    function ifcf7_sdt_load_admin_textdomain(){
        $locale = determine_locale();
        $mofile = WP_LANG_DIR . '/admin-' . $locale . '.mo';
        if(file_exists($mofile)){
            load_textdomain('default', $mofile, $locale);
        }
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_menu_slug')){
    /**
     * The top level menu of Contact Form 7: "wpcf7-dashboard" since 6.2, "wpcf7" before that, which
     * is still there, as the submenu of the contact forms. Compared to 6.2-dev so the betas and
     * release candidates of 6.2, which do carry the new menu already, are told apart from 6.1.
     */
    function ifcf7_sdt_menu_slug(){
        if(defined('WPCF7_VERSION') and version_compare(WPCF7_VERSION, '6.2-dev', '>=')){
            return 'wpcf7-dashboard';
        }
        return 'wpcf7';
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_meta_keys')){
    function ifcf7_sdt_meta_keys(){
        return IFCF7_SDT\Columns::get_meta_keys();
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_query_args')){
    /**
     * The WP_Query arguments a DataTables request (search, searchBuilder, order, paging) does mean.
     */
    function ifcf7_sdt_query_args($params = [], $form_ids = []){
        return IFCF7_SDT\Query::get_args($params, $form_ids);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_render_table')){
    function ifcf7_sdt_render_table($form_ids = [], $bootstrap = false){
        IFCF7_SDT\Page::enqueue($bootstrap);
        return IFCF7_SDT\Page::render_table($form_ids);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ifcf7_sdt_url')){
    function ifcf7_sdt_url($args = []){
        return IFCF7_SDT\Page::get_url($args);
    }
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
