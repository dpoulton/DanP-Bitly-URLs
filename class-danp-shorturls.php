<?php

// Plugin class
class danp_dot_net_dpbu_shorturls {
  // Add the Bitly Token API setting to the reading settings page
  function register_token_setting() {
    add_action('admin_init', function() {
      // Register the setting so WordPress will process it on saving
      register_setting('reading', DANP_DOT_NET_DPBU_TOKEN_SETTING, array('type' => 'string', 'description' => 'Your Bitly API Token', 'sanitize_callback' => array($this,'check_token')));
      // Add a new section to Settings > Reading, defined in "register_token_setting_callback" method below
      add_settings_section(
        DANP_DOT_NET_DPBU_TOKEN_SETTING,
        'DanP Bitly URLs: API Token',
        array(new danp_dot_net_dpbu_shorturls,'register_token_setting_callback'),
        'reading'
      );
    });
  }
  // Check token
  function check_token($token) {
    // Create JSON payload
    $payload = array('long_url' => 'https://dan-p.net');
    // Convert PHP array to JSON
    $json_payload = json_encode($payload);
    // Set HTTP headers
    $headers = array (
      'Host' => 'api-ssl.bitly.com',
      'Authorization' => 'Bearer ' . $token,
      'Content-Type' => 'application/json'
    );
    // Use WordPress HTTP API to retrieve JSON response from Bitly
    $response = wp_remote_post( 'https://api-ssl.bitly.com/v4/shorten' , array(
        'method'      => 'POST',
        'headers'     => $headers,
        'body'        => $json_payload
        )
    );
    // Successfully retrieved API
    if($response['response']['code'] === 200 && isset($response['body'])) {
      // Convert JSON response to PHP array
      $response['body'] = json_decode($response['body'],1);
      // If Bitly link is returned by API
      if(isset($response['body']['link'])) {
        // Return valid token, saves as a setting
        return $token;
      }
    }
    return 'Token failed';
  }
  // Add the HTML code to Settings > Reading, adds token input box and generate all button
  function register_token_setting_callback($args) {
    // Get current token (if any)
    $current_value = get_option(DANP_DOT_NET_DPBU_TOKEN_SETTING,'Enter token');
    // Sanitize option value
    $current_value = sanitize_option(DANP_DOT_NET_DPBU_TOKEN_SETTING,$current_value);
    echo '<div style="background: white; padding: 0 15px 7px">';
    // Output token input
    echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="danpurls-bitly-token">Token</label></th><td><input name="danpurls-bitly-token" type="text" id="danpurls-bitly-token" value="' . $current_value . '"></td></tr></tbody></table>';
    // Output link to guide on how to get token
    echo '<br><a href="https://dan-p.net/wordpress-plugins/danp-bitly-urls" target="_blank">Getting Started Guide</a> | ';
    echo '<a href="https://support.bitly.com/hc/en-us/articles/230647907-How-do-I-generate-an-OAuth-access-token-for-the-Bitly-API-" target="_blank">How do I get a token?</a><br><br>';
    // Tokens can't contain spaces, blank token default value "Enter token", bad token default value "Token failed", (both contain a space)
    if(substr_count($current_value,' ') > 0) {
      echo '<p>Enter a valid token for the \'Update all URLs\' button to appear here.</p>';
    }
    else {
      // Get the last time the API was called
      $last_run = get_option(DANP_DOT_NET_DPBU_OPTION_LAST_RUN,false);
      if($last_run !== false) {
        $last_run_calc = abs(date('U') - $last_run); // difference between Unix timestamps
        $last_run_calc = round($last_run_calc / 60); // in minutes
        // If the API was last called over 2 minutes ago - avoid breaking rate limiting
        if($last_run_calc > 2) {
          $last_run = false; // for the IF statement below
        }
      }
      if(!empty($current_value) && $last_run === false) {
        // Update all HTML button, links to the page created by "danp_dot_net_dpbu_update_all_page" on danp-shorturls-admin.php
        echo '<p><a class="button" href="' . admin_url() . 'index.php?page=danp-shorturls-update-all" style="margin-right: 20px">Update all short URLs</a>(limited to 100 URLs at a time, at least one minute apart, button is hidden when over Bitly\'s API limit)</p>';
      }
      else {
        // Rate limiting
        echo '<p>You can get Bitly URLs for all pages/posts again in two minutes.</p>';
      }
    }
    echo '</div>';
  }
  // Generate new/ retrieve existing short URL from Bitly -- URL on success, false on failure
  function generate_shorturl($post_id,$url) {
    // Get the saved token from the WordPress database
    $token = get_option(DANP_DOT_NET_DPBU_TOKEN_SETTING,false);
    $token = sanitize_option(DANP_DOT_NET_DPBU_TOKEN_SETTING,$token);
    // If statement runs if a token is set and the long URL is provided
    if($token !== false && !empty($url)) {
      // Create JSON payload
      $payload = array(
        'long_url' => $url
      );
      // Convert PHP array to JSON
      $json_payload = json_encode($payload);
      // Set HTTP headers
      $headers = array (
        'Host' => 'api-ssl.bitly.com',
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json'
      );
      // Use WordPress HTTP API to retrieve JSON response from Bitly
      $response = wp_remote_post( 'https://api-ssl.bitly.com/v4/shorten' , array(
          'method'      => 'POST',
          'headers'     => $headers,
          'body'        => $json_payload
          )
      );
      // Successfully retrieved API
      if($response['response']['code'] === 200 && isset($response['body'])) {
        // Convert JSON response to PHP array
        $response['body'] = json_decode($response['body'],1);
        // If Bitly link is returned by API
        if(isset($response['body']['link'])) {
          // Save to WordPress database
          $this->save_shortlink($post_id,$response['body']['link']);
          // Return the link -- success!
          return $response['body']['link'];
        }
      }
    }
    // Returns false on failure
    return false;
  }
  // Save shortlink
  function save_shortlink($post_id,$short_url) {
    // Sanitize variable just in case
    $short_url = esc_url_raw($short_url);
    $short_url = sanitize_meta(DANP_DOT_NET_DPBU_META_KEY, $short_url, 'post');
    // Using the post ID and short URL, the Bitly shortlink is saved in the WordPress database against the page/post ID
    add_post_meta($post_id,DANP_DOT_NET_DPBU_META_KEY,$short_url,true);
  }
  // Get shortlink
  function get_shortlink($post_id) {
    // Get shortlink from WordPress database using the page/post ID
    $shortlink = get_post_meta( $post_id, DANP_DOT_NET_DPBU_META_KEY, true );
    // Escape HTML because value will be echo'd by other functions
    $shortlink = esc_html($shortlink);
    $shortlink = esc_url_raw($shortlink);
    // If shortlink is not empty, return it, success
    if(!empty($shortlink)) {
      return $shortlink;
    }
    // Returns false on failure
    return false;
  }
  // Update all function
  function update_all() {
    // Fetch all pages and posts
    $the_query = new WP_Query(array(
      'post_type' => array('post', 'page'),
      'posts_per_page' => 99 // limited to 100 per minute
    ));
    // Initiate a counter
    $updated = 0;
    // If the query for all pages and posts has results, carry on
    if ( $the_query->have_posts() ) {
      // Loop through each page/post
      while ( $the_query->have_posts() ) {
        $the_query->the_post();
        // Page/post ID
        $id = get_the_ID();
        // Page/post long URL
        $url = get_the_permalink();
        // Skip pages/posts which already have Bitly shortlinks in the WordPress database
        if($this->get_shortlink($id) !== false) {
          continue;
        }
        // Attempt to generate a new Bitly shortlink -- $test = false on failure
        $test = $this->generate_shorturl($id,$url);
        // IF statement runs if URL is successfully generated
        if($test !== false) {
          // Increment counter
          $updated++;
        }
      }
    }
    // Restore original Post data
    wp_reset_postdata();
    // Store last ran option
    update_option(DANP_DOT_NET_DPBU_OPTION_LAST_RUN,date('U'));
    // Return number of new short URLs generated, 0 if none
    return $updated;
  }
}

?>
