<?php
/*
Plugin Name: Reetech Group Plugin
Description: A plugin for Reetech Group.
Version: 1.0
Author: Your Name
*/

// Enqueue CSS and JS files
if (!function_exists('my_static_files_enqueue')) {
    function my_static_files_enqueue() {
        // Enqueue CSS Files
        wp_enqueue_style('my-static-css-main', plugins_url('css/module.css', __FILE__));

        // Enqueue JavaScript Files
        wp_enqueue_script('my-static-js-main', plugins_url('javascript/javascript.js', __FILE__), array('jquery'), null, true);

        // Localize script to pass data from PHP to JavaScript (optional)
        wp_localize_script('my-static-js-main', 'myStaticData', array(
            'pluginUrl' => plugins_url('', __FILE__), // Pass plugin URL to JS
        ));
    }
    add_action('wp_enqueue_scripts', 'my_static_files_enqueue');
}

// Remove header, footer, and sidebar
function my_custom_template_redirect1() {
    // Check if the URL contains ?edit=123

    


    if (isset($_GET['home'])) {
        // Remove all actions for header, footer, and sidebar
       // remove_all_actions('wp_head');
       // remove_all_actions('wp_footer');
        //remove_all_actions('get_sidebar');

        // Load the edit.html template
        add_filter('template_include', function() {
            return plugin_dir_path(__FILE__) . 'templates/home.html';
        });
    }



   
    if (isset($_GET['edit'])) {
        //Remove all actions for header, footer, and sidebar
       remove_all_actions('wp_head');
       remove_all_actions('wp_footer');
        remove_all_actions('get_sidebar');

        // Load the edit.html template
        add_filter('template_include', function() {
            return plugin_dir_path(__FILE__) . 'templates/edit.html';
        });
    }

    // Check if the URL contains ?view=444
    if (isset($_GET['view'])) {
        // Remove all actions for header, footer, and sidebar
       // remove_all_actions('wp_head');
        //remove_all_actions('wp_footer');
        //remove_all_actions('get_sidebar');

        // Load the view.html template
        add_filter('template_include', function() {
            return plugin_dir_path(__FILE__) . 'templates/view.html';
        });
    }
}
//add_action('template_redirect', 'my_custom_template_redirect');

function my_custom_template_redirect() {
    // Define a mapping of URL parameters to template files
    $template_mapping = array(
        'edit' => 'edit.html', // ?page=edit will load edit.html
        'view' => 'view.html', // ?page=view will load view.html
        'home' => 'home.html', // ?page=home will load home.html
        'list' => 'list.html', // ?page=list will load list.html
    );

    // Check if the 'page' parameter exists in the URL
    if (isset($_GET['page'])) {
        $page = sanitize_text_field($_GET['page']); // Sanitize the input

        // Check if the requested page exists in the mapping
        if (array_key_exists($page, $template_mapping)) {
            // Remove all actions for header, footer, and sidebar (optional)
            remove_all_actions('wp_head');
            remove_all_actions('wp_footer');
            remove_all_actions('get_sidebar');

            // Load the corresponding template file
            add_filter('template_include', function() use ($template_mapping, $page) {
                return plugin_dir_path(__FILE__) . 'templates/' . $template_mapping[$page];
            });
        }
    }
}
//add_action('template_redirect', 'my_custom_template_redirect');

// Shortcode to include HTML content
if (!function_exists('my_static_html_shortcode')) {
    function my_static_html_shortcode($atts) {
        // Extract shortcode attributes
        $atts = shortcode_atts(array(
            'file' => 'home.html', // Default HTML file
        ), $atts);

        // Get the HTML file path
        $file_path = plugin_dir_path(__FILE__) . 'templates/' . $atts['file'];

        // Check if the file exists
        if (file_exists($file_path)) {
            // Output the HTML file content directly (no <code> tags)
            ob_start();
            include $file_path;
            $content = $file_path;// ob_get_clean();

            // Completely remove <code> tags and their content
            $content = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '', $content);

            // Get header content
$header = '';
$header_path = plugin_dir_path(__FILE__) . 'templates/header.html';
if (file_exists($header_path)) {
    $header = file_get_contents($header_path);
}

// Get footer content
$footer = '';
$footer_path =plugin_dir_path(__FILE__) . 'templates/footer.html';
if (file_exists($footer_path)) {
    $footer = file_get_contents($footer_path);
}
$content = $header . $content . $footer;

            return $content; // Return the HTML content as-is
        } else {
            return '<p>Error: HTML file not found.</p>';
        }
    }
    add_shortcode('my_static_html', 'my_static_html_shortcode');
}