<?php

// Plugin class
class danp_shorturls {
  // Add the Bitly Token API setting to the reading settings page
  function register_token_setting() {
    add_action('admin_init', function() {
      // Register the setting so WordPress will process it on saving
      register_setting('reading', 'danpurls-bitly-token', array('type' => 'string', 'description' => 'Your Bitly API Token'));
      // Add a new section to Settings > Reading, defined in "register_token_setting_callback" method below
      add_settings_section(
        'danpurls-bitly-token',
        'DanP Bitly URLs: API Token',
        array(new danp_shorturls,'register_token_setting_callback'),
        'reading'
      );
    });
  }
  // Add the HTML code to Settings > Reading, adds token input box and generate all button
  function register_token_setting_callback($args) {
    // Get current token (if any)
    $current_value = get_option('danpurls-bitly-token','');
    // Output token input
    echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="danpurls-bitly-token">Token</label></th><td><input name="danpurls-bitly-token" type="text" id="danpurls-bitly-token" value="' . $current_value . '"></td></tr></tbody></table>';
    // Output link to guide on how to get token
    echo '<a href="https://support.bitly.com/hc/en-us/articles/230647907-How-do-I-generate-an-OAuth-access-token-for-the-Bitly-API-" target="_blank">How do I get a token?</a><br><br>';
    // Add generate all button
    echo $this->update_all_button();
  }
  // Generate new/ retrieve existing short URL from Bitly -- URL on success, false on failure
  function generate_shorturl($post_id,$url) {
    // Get the saved token from the WordPress database
    $token = get_option('danpurls-bitly-token',false);
    // If statement runs if a token is set and the long URL is provided
    if($token !== false && !empty($url)) {
      // Create post data to send to Bitly API
      $data = array('long_url' => $url);
      // Encode post data as JSON
      $payload = json_encode($data);
      // Create headers to send to API
      $header = array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
      );
      // Retrieve short URL via Bitly API using PHP CURL -- define API URL
      $ch = curl_init('https://api-ssl.bitly.com/v4/bitlinks');
      // Method: POST
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      // Post fields
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      // Return transfer to variable (as opposed to output)
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // Send headers
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      // Fetch JSON result into a variable
      $result = curl_exec($ch);
      // Decode JSON, converting it to an array
      $result = json_decode($result,1);
      // IF statement checks whether a short URL is returned
      if(isset($result['link'])) {
        // Save Bitly shortlink to WordPress database
        $this->save_shortlink($post_id,$result['link']);
        // Return it as well
        return $result['link'];
      }
    }
    // Returns false on failure
    return false;
  }
  // Save shortlink
  function save_shortlink($post_id,$short_url) {
    // Using the post ID and short URL, the Bitly shortlink is saved in the WordPress database against the page/post ID
    add_post_meta($post_id,'danpurls-bitly-url',$short_url,true);
  }
  // Get shortlink
  function get_shortlink($post_id) {
    // Get shortlink from WordPress database using the page/post ID
    $shortlink = get_post_meta( $post_id, 'danpurls-bitly-url', true );
    // If shortlink is not empty, return it, success
    if(!empty($shortlink)) {
      return $shortlink;
    }
    // Returns false on failure
    return false;
  }
  // Update all button
  function update_all_button() {
    // Update all HTML button, links to the page created by "danp_update_all_page" on danp-shorturls-admin.php
    echo '<a class="button" href="' . admin_url() . 'index.php?page=danp-shorturls-update-all">Update all short URLs</a>';
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
