<?php
/*
Plugin Name: Simple Count
Description: Adds a dashboard widget displaying a pie chart of visitor origins by country and captures visitor data.
Version: 1.3
Author: emaech
*/

// Create a custom table to store visitor data
function simple_count_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'simple_count';
    $charset_collate = $wpdb->get_charset_collate();

    // Change fingerprint length to 32 to fit the MD5 hash size
    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        country VARCHAR(100) NOT NULL,
        visit_time DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        fingerprint VARCHAR(32) NOT NULL,  
        PRIMARY KEY (id),
        UNIQUE KEY fingerprint (fingerprint)  
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'simple_count_create_table');

function simple_count_capture_data_admin() {
	// Do nothing for now. I'm not interested in capturing my own sessions.
	// Leaving this in for possible future expansion.
}
	
// Capture visitor data on page load
function simple_count_capture_data() {

    // Include the country codes file
    include( plugin_dir_path( __FILE__ ) . 'country-codes.php');
    include( plugin_dir_path( __FILE__ ) . 'bot-keywords.php');


    // Check for bots and scrapers
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    foreach ($bot_keywords as $bot_keyword) {
        if (stripos($user_agent, $bot_keyword) !== false) {
            exit(); // Don't log bots
        }
    }
	

    // Check for Cloudflare geolocation header
    $country_code  = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : 'Unknown';
	$country = isset($country_map[$country_code]) ? $country_map[$country_code] : 'Unknown';
	
    // Generate a unique fingerprint based on the user's browser and screen details
    //$fingerprint = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR'] . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'default'));
	$fingerprint = md5(
		$_SERVER['HTTP_USER_AGENT'] . 
		$_SERVER['REMOTE_ADDR'] . 
		($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'default') . 
		date('Y-m-d') // Add the current date in 'YYYY-MM-DD' format
	);

    // Check if this fingerprint has already been recorded
    global $wpdb;
    $table_name = $wpdb->prefix . 'simple_count';
    $existing_entry = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE fingerprint = %s", $fingerprint));

    if ($existing_entry > 0) {
        return; // If the fingerprint already exists, don't track this visitor again
    }

    // Insert the data into the database
    $wpdb->insert($table_name, ['country' => $country, 'fingerprint' => $fingerprint]);
	
}


// Enqueue scripts and styles for Chart.js
function simple_count_enqueue_scripts($hook) {
    if ($hook !== 'index.php') return;
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
	wp_enqueue_style('simple_count-style', plugin_dir_url(__FILE__) . 'simple-count.css');
	 
}
add_action('admin_enqueue_scripts', 'simple_count_enqueue_scripts');

// Add the dashboard widget
function simple_count_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'simple_count_widget',
        'Visitors by Country',
        'simple_count_display_widget'
    );
}
add_action('wp_dashboard_setup', 'simple_count_add_dashboard_widget');

// Display the dashboard widget
function simple_count_display_widget() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'simple_count';

    // Default to last 7 days if no date range is selected
    $date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : 'last_7_days';

    // Adjust SQL query based on date filter
    $date_condition = '';
	
	// Get WordPress timezone
	$timezone = new DateTimeZone(wp_timezone_string());

	// Get the current date and time in WordPress timezone
	$current_time = new DateTime('now', $timezone);

	// Define the date conditions for each filter
	$date_conditions = [
		'yesterday' => sprintf(
			"WHERE visit_time >= '%s' AND visit_time < '%s'",
			(clone $current_time)->modify('-1 day')->format('Y-m-d 00:00:00'),
			(clone $current_time)->format('Y-m-d 00:00:00')
		),
		'last_7_days' => sprintf(
			"WHERE visit_time >= '%s'",
			(clone $current_time)->modify('-6 days')->format('Y-m-d 00:00:00') // Include today
		),
		'last_30_days' => sprintf(
			"WHERE visit_time >= '%s'",
			(clone $current_time)->modify('-30 days')->format('Y-m-d 00:00:00') // Include today
		),
		'last_60_days' => sprintf(
			"WHERE visit_time >= '%s'",
			(clone $current_time)->modify('-60 days')->format('Y-m-d 00:00:00') // Adjust range
		),
			'last_90_days' => sprintf(
			"WHERE visit_time >= '%s'",
			(clone $current_time)->modify('-90 days')->format('Y-m-d 00:00:00') // Adjust range
		),
		'today' => sprintf(
			"WHERE visit_time >= '%s'",
			(clone $current_time)->format('Y-m-d 00:00:00')
		),
	];

	// Use the selected filter or default to 'today'
	$date_condition = $date_conditions[$date_filter] ?? $date_conditions['today'];


    // Fetch visitor data based on date range
    $results = $wpdb->get_results("SELECT country, COUNT(*) as count FROM $table_name $date_condition GROUP BY country ORDER BY count DESC, country ASC LIMIT 5", ARRAY_A);

    $visitor_data = [];
    $other_count = 0;

    foreach ($results as $row) {
    // Trim country name to 15 characters
		$country_name = strlen($row['country']) > 15 ? substr($row['country'], 0, 15) : $row['country'];
		$visitor_data[$country_name] = $row['count'];
	}

    $remaining_results = $wpdb->get_results("SELECT COUNT(*) as count FROM $table_name $date_condition AND country NOT IN (SELECT country FROM (SELECT country FROM $table_name GROUP BY country ORDER BY COUNT(*) DESC LIMIT 5) as top_countries)", ARRAY_A);

	// Lump everyone else into a single bucket.	
    if (!empty($remaining_results)) {
        $other_count = $remaining_results[0]['count'];
    }

    if ($other_count > 0) {
        $visitor_data['Other'] = $other_count;
    }

	// If no visitor data exists, add a dummy entry with 'No Visitors' and count 1
    if (empty($visitor_data)) {
        $visitor_data['No Visitors'] = 1;
    }
	
    $labels = json_encode(array_keys($visitor_data));
    $values = json_encode(array_values($visitor_data));

    // Add the select list for filtering
    echo '
    <div id="dateFilterWrapper">
        <!--label for="dateFilter">Select Date Range: </label-->
        <select id="dateFilter" onchange="updateChartData()">
            <option value="today" ' . ($date_filter == 'today' ? 'selected' : '') . '>Today</option>
            <option value="yesterday" ' . ($date_filter == 'yesterday' ? 'selected' : '') . '>Yesterday</option>
            <option value="last_7_days" ' . ($date_filter == 'last_7_days' ? 'selected' : '') . '>Last 7 Days</option>
            <option value="last_30_days" ' . ($date_filter == 'last_30_days' ? 'selected' : '') . '>Last 30 Days</option>
            <option value="last_90_days" ' . ($date_filter == 'last_90_days' ? 'selected' : '') . '>Last 90 Days</option>
        </select>
    </div>';
 
	 
    echo '<canvas id="visitorchart" width="400" height="400"></canvas>';



    // Inline script to update the chart when the select list changes
    $inline_script = "
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById('visitorchart').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: $labels,
                datasets: [{
                    data: $values,
backgroundColor: [
  '#ed701b', '#eed758', '#1abc9e', '#0990c2', '#d73b50', '#435274'
]
                }]
            },
		options: {
			responsive: true, // Keep the responsive option
			plugins: {
			  legend: {
				position: 'bottom', // Position the legend on the left
				labels: {
				  usePointStyle: true,
				}
			  }
			}
		}
        });

        // Function to update chart data
        window.updateChartData = function() {
            var selectedRange = document.getElementById('dateFilter').value;
            window.location.href = window.location.pathname + '?date_filter=' + selectedRange;
        };
    });
    ";
    wp_add_inline_script('chartjs', $inline_script);
}




function enqueue_simple_count_script() {
   // Enqueue the script
   wp_enqueue_script(
      'simple_count', // Handle for the script
      plugin_dir_url(__FILE__) . 'simple-count.js', // Path to the script
      array('jquery'), // Dependencies (make sure jQuery is loaded first)
      null, // Version (null means it will use the plugin version or none)
      true // Load the script in the footer
   );

   // Localize script to add the AJAX URL
   wp_localize_script('simple_count', 'simpleChartParams', array(
      'ajax_url' => admin_url('admin-ajax.php'), // AJAX URL
   ));
}
add_action('wp_enqueue_scripts', 'enqueue_simple_count_script');

add_action('wp_ajax_capture_country', 'simple_count_capture_data_admin');
add_action('wp_ajax_nopriv_capture_country', 'simple_count_capture_data');





// Clean up the table on plugin uninstall
function simple_count_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'simple_count';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'simple_count_uninstall');
