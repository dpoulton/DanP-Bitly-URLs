<?php

// Add the token input to Settings > Reading, and generate all button
$danp_shorturls_class->register_token_setting();

// Generate URL on saving post/page
add_action( 'transition_post_status', 'danp_shorturls_on_publish', 10, 3 );
function danp_shorturls_on_publish( $new_status, $old_status, $post ) {
  // If statement runs when user publishes a page or post for the first time only
  if( $new_status === 'publish' && $old_status !== 'publish' && $post->post_type === 'post' && $post->post_type === 'page' ) {
    // Get page/post ID
    $post_id = get_the_ID($post);
    // Get page/post URL
    $url = get_the_permalink($post_id);
    // Generate the shortlink
    $danp_shorturls_class->generate_shorturl($post_id,$url);
  }
}

// Update all shortlinks page -- add page
add_action( 'admin_menu', 'danp_update_all_page' );
function danp_update_all_page() {
    add_dashboard_page(
        'Update All URLs',
        'Update All URLs',
        'manage_options',
        'danp-shorturls-update-all',
        'danp_update_all_page_callback',
        9999
    );
}

// Update all shortlinks page -- the function
function danp_update_all_page_callback() {
  $danp_shorturls_class = new danp_shorturls();
  $danp_updated_shortlinks = number_format($danp_shorturls_class->update_all());
  echo '<div class="wrap"><h1>Update all short URLs</h1><p>Updated URLs: ' . $danp_updated_shortlinks . '</p></div>';
}

// Update all shortlinks page -- hide page
add_action( 'admin_head', function() {
  remove_submenu_page( 'index.php', 'danp-shorturls-update-all' );
} );

?>
