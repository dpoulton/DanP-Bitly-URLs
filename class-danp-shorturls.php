<?php

// Plugin class
class danp_dot_net_dpbu_shorturls {
  // Add the Bitly Token API setting to the reading settings page
  function register_token_setting() {
    add_action('admin_init', function() {
      // Register the setting so WordPress will process it on saving
      register_setting('reading', DANP_DOT_NET_DPBU_TOKEN_SETTING, array('type' => 'string', 'description' => 'Your Bitly API Token'));
      // Add a new section to Settings > Reading, defined in "register_token_setting_callback" method below
      add_settings_section(
        DANP_DOT_NET_DPBU_TOKEN_SETTING,
        'DanP Bitly URLs: API Token',
        array(new danp_dot_net_dpbu_shorturls,'register_token_setting_callback'),
        'reading'
      );
    });
  }
  // Add the HTML code to Settings > Reading, adds token input box and generate all button
  function register_token_setting_callback($args) {
    // Get current token (if any)
    $current_value = get_option(DANP_DOT_NET_DPBU_TOKEN_SETTING,'');
    // Sanitize option value
    $current_value = sanitize_option(DANP_DOT_NET_DPBU_TOKEN_SETTING,$current_value);
    // Output token input
    echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="danpurls-bitly-token">Token</label></th><td><input name="danpurls-bitly-token" type="text" id="danpurls-bitly-token" value="' . $current_value . '"></td></tr></tbody></table>';
    // Output link to guide on how to get token
    echo '<a href="https://support.bitly.com/hc/en-us/articles/230647907-How-do-I-generate-an-OAuth-access-token-for-the-Bitly-API-" target="_blank">How do I get a token?</a><br><br>';
    if(!empty($current_value)) {
      // Update all HTML button, links to the page created by "danp_dot_net_dpbu_update_all_page" on danp-shorturls-admin.php
      echo '<a class="button" href="' . admin_url() . 'index.php?page=danp-shorturls-update-all">Update all short URLs</a>';
    }
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
      'posts_per_page' => -1
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
    // Return number of new short URLs generated, 0 if none
    return $updated;
  }
}

?>
