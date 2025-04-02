<?php
/*
Plugin Name: Reetech Group Plugin
Description: A plugin for Reetech Group.
Version: 1.0
Author: Your Name
*/


add_action('admin_enqueue_scripts', function() {
    // DataTables Core
    wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css');
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', ['jquery']);
    
    // DataTables Bootstrap Integration
    wp_enqueue_script('datatables-bootstrap', 'https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js', ['datatables']);
    
    // Font Awesome for icons
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
    
    // Your custom datatable.js
    wp_enqueue_script('reetech-datatables', plugins_url('js/datatable.js', __FILE__), ['datatables', 'datatables-bootstrap']);
    
    // Localize script with API settings
    wp_localize_script('reetech-datatables', 'wpApiSettings', [
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ]);
});

// Enqueue CSS and JS files
if (!function_exists('reetech_group_enqueue_assets')) {
    function reetech_group_enqueue_assets() {
        wp_enqueue_style('reetech-css-main', plugins_url('css/module.css', __FILE__));
        wp_enqueue_script('reetech-js-main', plugins_url('javascript/javascript.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('reetech-js-main', 'reetechData', array(
            'pluginUrl' => plugins_url('', __FILE__),
        ));
    }
    add_action('wp_enqueue_scripts', 'reetech_group_enqueue_assets');
}

// Allow custom 'page' parameter in URLs
add_filter('query_vars', function($vars) {
    $vars[] = 'home';
    return $vars;
});

// Template override for our pages
add_action('template_redirect', function() {
    if (get_query_var('home')) {
        // Remove all WordPress theme output
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        remove_all_actions('get_header');
        remove_all_actions('get_footer');
        remove_all_actions('get_sidebar');
        
        // Load our custom template
        include plugin_dir_path(__FILE__) . 'templates/blank-wrapper.php';
        exit;
    }
});



// Shortcode to include HTML content
if (!function_exists('reetech_group_shortcode')) {
    function reetech_group_shortcode($atts) {
        // Template mapping
        $template_mapping = array(
            'edit' => 'edit.html',
            'view' => 'view.html',
            'home' => 'home.html',
            'list' => 'list.html',
        );

       
        // Get the requested page
        $requested_page = get_query_var('home', 'home');
        
        // Determine which file to load
        $file_to_load = isset($template_mapping[$requested_page]) 
                      ? $template_mapping[$requested_page] 
                      : 'edit.html';

        // Get file paths
        $template_dir = plugin_dir_path(__FILE__) . 'templates/';
        $file_path = $template_dir . $file_to_load;
        $header_path = $template_dir . 'header.html';
        $footer_path = $template_dir . 'footer.html';

        // Check if main file exists
        if (!file_exists($file_path)) {
            return '<p>Error: Requested page not found.</p>';
        }

        // Get header
        $header = file_exists($header_path) ? file_get_contents($header_path) : '';
        
        // Get content
        $content = file_get_contents($file_path);
        
        // Get footer
        $footer = file_exists($footer_path) ? file_get_contents($footer_path) : '';

        // Combine all parts
        $output = $header . $content ;//. $footer;
        

        // Remove code tags if any
        $output = preg_replace('/<code[^>]*>(.*?)<\/code>/is', '', $output);

        return $output;
    }
    add_shortcode('reetech_group', 'reetech_group_shortcode');
}

add_action('rest_api_init', function() {
    register_rest_route('reetech-group/v1', '/invoices', array(
        'methods' => 'POST',
        'callback' => 'handle_invoice_submission',
        'permission_callback' => '__return_true' 
        // 'permission_callback' => function() {
        //     return current_user_can('edit_posts');
        // }
    ));
    register_rest_route('reetech-group/v1', '/invoices-summary', array(
        'methods' => 'GET',
        'callback' => 'get_invoices_summary',
        'permission_callback' => '__return_true'
        // 'permission_callback' => function() {
        //     return current_user_can('edit_posts'); // Or appropriate capability
        // }
    ));
});

function handle_invoice_submission(WP_REST_Request $request) {
    global $wpdb;
    
    // Get and validate the data
    $parameters = $request->get_json_params();
    
    if (empty($parameters)) {
        return new WP_Error('invalid_data', 'Invalid invoice data', array('status' => 400));
    }
    
    // Process the invoice data
    $invoice_data = array(
        'from_name' => sanitize_text_field($parameters['from']['name'] ?? ''),
        'from_address' => sanitize_textarea_field($parameters['from']['address'] ?? ''),
        'to_id' => intval($parameters['to']['id'] ?? 0),
        'to_name' => sanitize_text_field($parameters['to']['name'] ?? ''),
        'to_address' => sanitize_textarea_field($parameters['to']['address'] ?? ''),
        'invoice_number' => sanitize_text_field($parameters['invoice_number'] ?? ''),
        'invoice_date' => sanitize_text_field($parameters['invoice_date'] ?? ''),
        'due_date' => sanitize_text_field($parameters['due_date'] ?? ''),
        'subtotal' => floatval($parameters['subtotal'] ?? 0),
        'total' => floatval($parameters['total'] ?? 0),
        'status' => 'pending',
        'created_at' => current_time('mysql')
    );
    
    // Save to database with error handling
    $table_name = $wpdb->prefix . 'invoices'; // Note: wp_ is automatically prefixed
    
    $result = $wpdb->insert($table_name, $invoice_data);
    
    if ($result === false) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Database error: ' . $wpdb->last_error,
            'query' => $wpdb->last_query
        ], 500);
    }
    
    $invoice_id = $wpdb->insert_id;
    
    // Save line items if invoice was created successfully
    if ($invoice_id && !empty($parameters['items'])) {
        $items_table = $wpdb->prefix . 'invoice_items';
        
        foreach ($parameters['items'] as $item) {
            $item_result = $wpdb->insert($items_table, array(
                'invoice_id' => $invoice_id,
                'description' => sanitize_textarea_field($item['description'] ?? ''),
                'unit_price' => floatval($item['unit_price'] ?? 0),
                'quantity' => floatval($item['quantity'] ?? 0),
                'tax_rate' => sanitize_text_field($item['tax_rate'] ?? ''),
                'amount' => floatval($item['amount'] ?? 0)
            ));
            
            if ($item_result === false) {
                // Log item insertion error but don't fail the whole request
                error_log('Failed to insert invoice item: ' . $wpdb->last_error);
            }
        }
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'invoice_id' => $invoice_id,
        'message' => 'Invoice created successfully',
        'data' => $invoice_data // For debugging
    ), 200);
}

function get_invoices_summary(WP_REST_Request $request) {
    global $wpdb;
    
    // Get DataTables parameters
    $per_page = $request->get_param('per_page') ?: 10;
    $page = $request->get_param('page') ?: 1;
    $search = $request->get_param('search');
    $orderby = $request->get_param('orderby') ?: 'invoice_date';
    $order = $request->get_param('order') ?: 'DESC';
    
    // Validate order direction
    $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';
    
    // Validate orderby field
    $allowed_columns = ['invoice_number', 'customer', 'date', 'total', 'balance', 'status'];
    $orderby = in_array($orderby, $allowed_columns) ? $orderby : 'invoice_date';
    
    // Map orderby to actual database columns
    $column_mapping = [
        'customer' => 'to_name',
        'date' => 'invoice_date',
        'balance' => 'balance_due'
    ];
    $orderby = $column_mapping[$orderby] ?? $orderby;
    
    // Base query
    $table_name = $wpdb->prefix . 'invoices';
    $query = "SELECT 
                id,
                invoice_number,
                to_name AS customer,
                invoice_date AS date,
                total,
                balance_due AS balance,
                status
              FROM $table_name";
    
    // Where clauses
    $where_clauses = [];
    $query_params = [];
    
    // Search filter
    if ($search) {
        $where_clauses[] = "(invoice_number LIKE %s OR to_name LIKE %s)";
        $query_params[] = '%' . $wpdb->esc_like($search) . '%';
        $query_params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    // Additional filters
    if ($request->get_param('status')) {
        $where_clauses[] = "status = %s";
        $query_params[] = sanitize_text_field($request->get_param('status'));
    }
    
    if ($request->get_param('date_from')) {
        $where_clauses[] = "invoice_date >= %s";
        $query_params[] = sanitize_text_field($request->get_param('date_from'));
    }
    
    if ($request->get_param('date_to')) {
        $where_clauses[] = "invoice_date <= %s";
        $query_params[] = sanitize_text_field($request->get_param('date_to'));
    }
    
    // Combine where clauses
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Add sorting
    $query .= " ORDER BY $orderby $order";
    
    // Add pagination
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;
    
    // Prepare and execute
    if (!empty($query_params)) {
        $query = $wpdb->prepare($query, $query_params);
    }
    
    $invoices = $wpdb->get_results($query);
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM $table_name";
    if (!empty($where_clauses)) {
        $count_query .= " WHERE " . implode(" AND ", $where_clauses);
        $count_query = $wpdb->prepare($count_query, array_slice($query_params, 0, count($query_params) - 2));
    }
    $total_items = $wpdb->get_var($count_query);
    
    // Format response
    return new WP_REST_Response([
        'success' => true,
        'data' => $invoices,
        'pagination' => [
            'total_items' => (int)$total_items,
            'per_page' => (int)$per_page,
            'current_page' => (int)$page,
            'total_pages' => ceil($total_items / $per_page)
        ]
    ], 200);
}