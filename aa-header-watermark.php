<?php
/*
Plugin Name: Header Watermark
Plugin URI: http://wordpress.org/extend/plugins/header-watermark/
Description: Dynamic watermarked header image generator
Author: Andy Agnew
Author URI: http://wordpress.org/extend/plugins/header-watermark/
Version:  1.0.1
Text Domain: header-watermark
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! function_exists( 'exif_imagetype' ) ) {
  function exif_imagetype ( $filename ) {
    if ( ( list( $width, $height, $type, $attr ) = getimagesize( $filename ) ) !== false ) {
      return $type;
    }
  return false;
  }
}

//echo (delete_option('aa_header_watermark_options')) ? 'yes' : 'no';
require_once( 'includes/class-aa-directory-contents.php' );
require_once( 'includes/class-aa-media-library.php' );
require_once( 'includes/class-aa-paginate.php' );


// Hook for adding the new options
add_action( 'admin_init', 'aa_header_watermark_options_init' );

function aa_header_watermark_options_init() {
  register_setting( 'aa_header_watermark_options_options', 'aa_header_watermark_options', 'aa_header_watermark_options_validate' );
}

// Form input validation function
function aa_header_watermark_options_validate($input) {
  // Get the page number input - if it's different to the referer page number then the request is to chaneg page, so redirect
  if ( isset( $input['wp'] ) ) {
    $requested_page = (int) $input['wp']; // Get the page value - make sure it's an int
    // Unset the page variable - we don't want to save it and don't need it any more
    unset( $input['wp'] );
    // Check if the page has changed
    // Get the referrer page number
    $referrer_url = $_SERVER['HTTP_REFERER'];
    if ( preg_match( '/&wp=[0-9]+/', $referrer_url, $matches) ) {
      // Get the page number
      $referrer_page = (int) str_replace( '&wp=', '', $matches[0] );
    } else {  // If the page number is not present in the query string then page is 1
      $referrer_page = 1;
    }
    // Check if it is the same
    if ( $requested_page != $referrer_page ) { // Redirect the user if the page is different
      // Build the new url
      // If there is a variable in the query string for 'settings-updated' the strip it out
      $referrer_url = str_replace( '&settings-updated=true', '', $referrer_url );
      // If there was a match in the referring query string then replace it
      if ( $matches ) {
        $redirect_url = str_replace( $matches[0], '&wp=' . $requested_page, $referrer_url );
      } else { // Otherwise append the page number
        $redirect_url = $referrer_url . '&wp=' . $requested_page;
      }
      
      // Redirect (don't save)
      header( 'Location: ' . $redirect_url );
      exit;
    }
  }

  // Get the header image width and height
  $input['header_image_width'] = HEADER_IMAGE_WIDTH;
  $input['header_image_height'] = HEADER_IMAGE_HEIGHT;
  
  // Validate the posted values  
  if ( isset( $input['add_watermark'] ) ) {
    $input['add_watermark'] = (bool) $input['add_watermark'];
  } else {
    $input['add_watermark'] = false;
  }
  
  if ( ! isset( $input['watermark_src'] ) || ! preg_match( '/^https?:\/\/' . $_SERVER['SERVER_NAME'] . '\//', $input['watermark_src'] ) || ! in_array( exif_imagetype( $input['watermark_src'] ), array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
    // If the existing_watermark_src is set then us that one
    if ( isset( $input['existing_watermark_src'] ) && preg_match( '/^https?:\/\/' . $_SERVER['SERVER_NAME'] . '\//', $input['existing_watermark_src'] ) && in_array( exif_imagetype( $input['existing_watermark_src'] ), array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
      $input['watermark_src'] = $input['existing_watermark_src'];
    } else {
      $input['watermark_src'] = false;
    }
  }
  // Unset the existing_watermark_src variable
  unset( $input['existing_watermark_src'] );
  
  // If the watermark_src is set then sort out the other related variables
  if ( $input['watermark_src'] ) {
    // Get the wp media library location
    $upload_dir_array = wp_upload_dir();
    
    $upload_dir = $upload_dir_array['basedir'];
    $upload_url = $upload_dir_array['baseurl'];
    
    // Get the filepath to the watermark image
    $watermark_file_path = str_replace( $upload_url, $upload_dir, $input['watermark_src'] );
    // Initialise the watermark dimension variables
    $watermark_size = false;
    $watermark_width = false;
    $watermark_height = false;
    // Get the width and height of the watermark image
    if ( $watermark_file_path ) {
      $watermark_size = getimagesize( $watermark_file_path );
      if ( $watermark_size ) {
        $watermark_width = $watermark_size[0];
        $watermark_height = $watermark_size[1];
      }
    }
    
    if ( isset( $input['watermark_pos_left'] ) ) {
      // Make sure its an int
      $input['watermark_pos_left'] = (int) $input['watermark_pos_left'];

      // Make sure the value is not too big
      if ( $input['watermark_pos_left'] >= $input['header_image_width'] ) {
        $input['watermark_pos_left'] = $input['header_image_width'] - 1;
      } elseif ( $input['watermark_pos_left'] <= -$watermark_width ) { // Or too small
        $input['watermark_pos_left'] = -$watermark_width + 1;
      }
    } else {
      $input['watermark_pos_left'] = 0;
    }
    
    if ( isset( $input['watermark_pos_top']) ) {
      // Make sure its an int
      $input['watermark_pos_top'] = (int) $input['watermark_pos_top'];
      // Make sure the value is not too big
      if ( $input['watermark_pos_top'] >= $input['header_image_height'] ) {
        $input['watermark_pos_top'] = $input['header_image_height'] - 1;
      } else if ( $input['watermark_pos_top'] <= -$watermark_height ) { // Or too small
        $input['watermark_pos_top'] = -$watermark_height + 1;
      }
    } else {
      $input['watermark_pos_top'] = 0;
    }
    
    if ( isset( $input['watermark_opacity'] ) ) {
      // Make sure its an int
      $input['watermark_opacity'] = (int) $input['watermark_opacity'];
      // Make sure the value is not too big
      if ( $input['watermark_opacity'] > 100 ) {
        $input['watermark_opacity'] = 100;
      } else if ( $input['watermark_opacity'] < 1 ) { // Or too small
        $input['watermark_opacity'] = 1;
      }
    } else {
      $input['watermark_opacity'] = 100;
    }
  }
  
  if ( ! isset( $input['random_image'] ) || ( 'random' != $input['random_image'] && 'static' != $input['random_image'] ) ) {
    $input['random_image'] = 'random';
  }
  
  if ( ! isset( $input['header_image_src'] ) || ! preg_match( '/^https?:\/\/' . $_SERVER['SERVER_NAME'] . '\//', $input['header_image_src'] ) || ! in_array( exif_imagetype( $input['header_image_src'] ), array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
    $input['header_image_src'] = false;
  }
  
  if ( isset( $input['random_header_blacklist'] ) && $input['random_header_blacklist'] ) {
    // Load the media library
    $media_library = new aa_media_library();
    // Get all the header urls
    $all_header_images_urls = $media_library->get_images_urls( array( 'width' => $input['header_image_width'], 'height' => $input['header_image_height'] ) );
    // Filter out any that have been added since the form was loaded or are missing from the submitted urls
    foreach( $all_header_images_urls as $url => $attr ) {
      if ( $attr['modified'] > $input['timestamp'] || in_array( $url, $input['random_header_blacklist'] ) ) {
        unset( $all_header_images_urls[$url] );
      }
    }
    
    // If there are any urls left in the all headers array then these should be blacklisted
    if ( $all_header_images_urls ) {
      $blacklist_image_urls = array_keys( $all_header_images_urls );
    } else {
      $blacklist_image_urls = false;
    }
    
    $input['random_header_blacklist'] = $blacklist_image_urls;
    
  } else {
    $input['random_header_blacklist'] = false;
  }
  
  // We don't want to save the timestamp that was submitted
  unset($input['timestamp']);
  
  return $input;
}

// Hook for adding admin menus
add_action( 'admin_menu', 'aa_header_watermark_add_admin' );

function aa_header_watermark_add_admin() {
  // Add the admin page for this plugin
  $aa_header_watermark_admin_page = add_theme_page( 'Header Watermark', 'Header Watermark', 'edit_theme_options', 'aa-header-watermark', 'aa_header_watermark_page' );
  // 
  add_action( "admin_print_scripts-$aa_header_watermark_admin_page", 'aa_header_watermark_admin_head' );
}

function aa_header_watermark_admin_head() {
  echo '<link rel="stylesheet" type="text/css" href="' . plugins_url( 'css/wp-admin.css', __FILE__ ) . '">';
  wp_enqueue_script('aa-header-watermark-script', plugins_url( 'js/wp-admin.js', __FILE__ ), array('jquery') ); 
}

// aa_header_watermark_page() displays the page content for the Header Watermark admin submenu
function aa_header_watermark_page() {

  //must check that the user has the required capability 
  if ( ! current_user_can( 'edit_theme_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'aa-header-watermark' ) );
  }
  
  // Check that we have GD library installed
  if ( ! defined('GD_VERSION') || '2.0.28' > GD_VERSION):
?>
<div>
  <p>
  <?php _e( 'Sorry, you can\'t use this plugin - it requires GD library version 2.0.28 or later.', 'aa-header-watermark' ); ?>
  </p>
</div>
<?php
    exit;
  endif;
  
  // Check for the HEADER_IMAGE_WIDTH and HEADER_IMAGE_HEIGHT constants that we need
  if ( ! defined( 'HEADER_IMAGE_WIDTH' ) || ! defined( 'HEADER_IMAGE_HEIGHT' ) ):
?>
<div>
  <p>
  <?php _e( 'Sorry, you can\'t use this plugin - your theme does not define both HEADER_IMAGE_WIDTH and HEADER_IMAGE_WIDTH constants - plugin doesn\'t know what size images the header images should be.', 'aa-header-watermark' ); ?>
  </p>
</div>
<?php
    exit;
  endif;
  
  // Check that the get_header_image() function returns true
  if ( ! get_header_image() ):
?>
<div>
  <p>
  <?php _e( 'Either your theme does not support header images, or the header image has been removed. If you have the Appearance->Header admin menu, use that to restore original header image then try this page again.', 'aa-header-watermark' ); ?>
  </p>
</div>
<?php
    exit;
  endif;
  
  $header_watermark_options_vals = get_option( 'aa_header_watermark_options' );
  
  // Set the watermark height and width values if they are not already set or have changed
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['header_image_width'] ) || HEADER_IMAGE_WIDTH !== $header_watermark_options_vals['header_image_width'] ) {
    $header_watermark_options_vals['header_image_width'] = HEADER_IMAGE_WIDTH;
  }
  if ( ! $header_watermark_options_vals  || ! isset( $header_watermark_options_vals['header_image_height'] ) || HEADER_IMAGE_HEIGHT !== $header_watermark_options_vals['header_image_height'] ) {
    $header_watermark_options_vals['header_image_height'] = HEADER_IMAGE_HEIGHT;
  }
  
  // Set the other header watermark settings if they've not already been set
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['add_watermark'] ) ) {
    $header_watermark_options_vals['add_watermark'] = false;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['watermark_src'] ) ) {
    $header_watermark_options_vals['watermark_src'] = false;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['watermark_pos_left'] ) || ! $header_watermark_options_vals['watermark_pos_left'] ) {
    $header_watermark_options_vals['watermark_pos_left'] = 0;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['watermark_pos_top'] ) || ! $header_watermark_options_vals['watermark_pos_top'] ) {
    $header_watermark_options_vals['watermark_pos_top'] = 0;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['watermark_opacity'] ) || ! $header_watermark_options_vals['watermark_opacity'] ) {
    $header_watermark_options_vals['watermark_opacity'] = 100;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['random_image'] ) ) {
    $header_watermark_options_vals['random_image'] = false;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['header_image_src'] ) ) {
    $header_watermark_options_vals['header_image_src'] = false;
  }
  
  if ( ! $header_watermark_options_vals || ! isset( $header_watermark_options_vals['random_header_blacklist'] ) ) {
    $header_watermark_options_vals['random_header_blacklist'] = false;
  }
  
  // Load the media library
  $media_library = new aa_media_library();
  
  // Do the pagination stuff
  $order_by = 'modified';
  $order = 'desc';
  $sorted = 'modified';
  if ( isset( $_REQUEST['wob'] ) ) { // wob: watermark order by
    switch( $_REQUEST['wob'] ) {
      case 'filename':
        $order_by = 'name';
        $sorted = 'filename';
        $order = 'asc';
        break;
      case 'date':
        $order_by = 'modified';
        $sorted = 'modified';
        $order = 'desc';
        break;
      default:
        // Do nothing, it's already been set
    }
  }
  
  if ( 'modified' == $order_by ) {
    $url_order_by = 'date';
  } elseif ( 'name' == $order_by ) {
    $url_order_by = 'filename';
  }
  
  if ( isset( $_REQUEST['wo'] ) ) { // wo: watermark order
    if ( 'asc' == $_REQUEST['wo'] ) {
      $order = 'asc';
    } elseif ( 'desc' == $_REQUEST['wo'] ) {
      $order = 'desc';
    }
  }
  
  $watermark_images_urls = $media_library->get_images_urls( array( 'thumbnails' => false, 'sort' => array( 'attr'=>$order_by, 'order'=>$order ) ) );
  $header_images_urls = $media_library->get_images_urls( array( 'width' => $header_watermark_options_vals['header_image_width'], 'height' => $header_watermark_options_vals['header_image_height'] ) );
  
  // If there are no suitable header images found, then notify the user that the plugin will have no affect
  if ( ! $header_images_urls ):
?>
<div>
  <p>
  <?php printf( __( 'There are no images in your Media Library of the correct size (%d x %d pixels) - the plugin will have no effect.', 'aa-header-watermark' ), HEADER_IMAGE_WIDTH, HEADER_IMAGE_HEIGHT ); ?>
  </p>
</div>
<?php
    exit;
  endif;

  // Get the page
  $current_page = null;
  if ( isset( $_REQUEST['wp'] ) ) {
    $current_page = (int) $_REQUEST['wp'];
  }

  if ( ! $current_page ) {
    $current_page = 1;
  }

  if ( $watermark_images_urls && $current_page ) {
    $paginated_images_urls = aa_paginate::get_page_contents( $watermark_images_urls, 10, $current_page );
    $watermark_images_urls = $paginated_images_urls['records'];
  }
  
  // Form
?>
<?php
 // If the settings have been updated then say so
 if ( isset( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] ):
?>
<div class="updated"><p><strong><?php _e( 'Settings saved.', 'aa-header-watermark' ); ?></strong></p></div>
<?php endif; ?>
<div class="wrap">
  <h2><?php _e( 'Header Watermark', 'aa-header-watermark' ); ?></h2>
  <form class="aa-form" name="aa_form" method="post" action="options.php">
    <?php settings_fields( 'aa_header_watermark_options_options' ); ?>
    <ol>
      <?php if ( $watermark_images_urls ): ?>
        <li>
          <h3><?php _e( 'Watermark', 'aa-header-watermark' );?></h3>
          <input type="checkbox" id="add-watermark" name="aa_header_watermark_options[add_watermark]" value="true" <?php echo ( $header_watermark_options_vals['add_watermark'] ) ? 'checked="checked" ' : ''; ?>><label for="add-watermark">Use Watermark Image</label>
        </li>
        <li id="watermark-image-table">
          <fieldset>
            <ol>
              <li>
                <div class="tablenav top">
                  <div class="alignleft">
                    <legend><?php _e( 'watermark Image', 'aa-header-watermark' ); ?></legend>
                  </div>
                  <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $paginated_images_urls['total_records']; ?> items</span>
                    <span class="pagination-links">
                      <a class="first-page<?php echo ( 1 == $current_page ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>" title="Go to the first page">«</a>
                      <a class="prev-page<?php echo ( 1 == $current_page ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>&wp=<?php echo ( $paginated_images_urls['pages']['previous'] ) ? $paginated_images_urls['pages']['previous'] : 1; ?>" title="Go to the previous page">‹</a>
                      <span class="paging-input"><input class="current-page" type="text" size="2" value="<?php echo $current_page; ?>" name="aa_header_watermark_options[wp]" title="Current page"> of <span class="total-pages"><?php echo $paginated_images_urls['pages']['total']; ?></span></span>
                      <a class="next-page<?php echo ( $current_page == $paginated_images_urls['pages']['total'] ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>&wp=<?php echo ( $paginated_images_urls['pages']['next'] ) ? $paginated_images_urls['pages']['next'] : $paginated_images_urls['pages']['total']; ?>" title="Go to the next page">›</a>
                      <a class="last-page<?php echo ( $current_page == $paginated_images_urls['pages']['total'] ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>&wp=<?php echo $paginated_images_urls['pages']['total']; ?>" title="Go to the last page">»</a>
                    </span>
                  </div>
                  <br class="clear">
                </div>
                  <table class="wp-list-table widefat fixed media" cellspacing="0">
                    <thead>
                      <tr>
                        <th id="cb" class="manage-column column-cb check-column" style="" scope="col"></th>
                        <th id="icon" class="manage-column column-icon" style="" scope="col"></th>
                        <th id="filename" class="manage-column column-title <?php echo ( 'filename' == $sorted ) ? 'sorted' : 'sortable'; ?> <?php echo $order; ?>" style="" scope="col">
                          <a href="?page=aa-header-watermark&wob=filename&wo=<?php echo ( 'name' == $order_by && 'asc' == $order ) ? 'desc' : 'asc'; ?>">
                            <span><?php _e("File Name", 'aa-header-watermark' );?></span>
                            <span class="sorting-indicator"></span>
                          </a>
                        </th>
                        <th id="modified" class="manage-column column-date <?php echo ( 'modified' == $sorted ) ? 'sorted' : 'sortable'; ?> <?php echo $order; ?>" style="" scope="col">
                            <a href="?page=aa-header-watermark&wob=date&wo=<?php echo ( 'modified' == $order_by && 'desc' == $order ) ? 'asc' : 'desc'; ?>">
                            <span><?php _e("Date", 'aa-header-watermark' );?></span>
                            <span class="sorting-indicator"></span>
                            </a>
                        </th>
                      </tr>
                    </thead>
                    <tfoot>
                      <tr>
                        <th id="cb" class="manage-column column-cb check-column" style="" scope="col"></th>
                        <th id="icon" class="manage-column column-icon" style="" scope="col"></th>
                        <th id="filename" class="manage-column column-title <?php echo ( 'filename' == $sorted ) ? 'sorted' : 'sortable'; ?> <?php echo $order; ?>" style="" scope="col">
                          <a href="?page=aa-header-watermark&wob=filename&wo=<?php echo ( 'name' == $order_by && 'asc' == $order ) ? 'desc' : 'asc'; ?>">
                            <span><?php _e("File Name", 'aa-header-watermark' );?></span>
                            <span class="sorting-indicator"></span>
                          </a>
                        </th>
                        <th id="modified" class="manage-column column-date <?php echo ( 'modified' == $sorted ) ? 'sorted' : 'sortable'; ?> <?php echo $order; ?>" style="" scope="col">
                            <a href="?page=aa-header-watermark&wob=date&wo=<?php echo ( 'modified' == $order_by && 'desc' == $order ) ? 'asc' : 'desc'; ?>">
                            <span><?php _e("Date", 'aa-header-watermark' );?></span>
                            <span class="sorting-indicator"></span>
                            </a>
                        </th>
                      </tr>
                    </tfoot>
                    <tbody id="the-list">
                      <?php $img_count = 1; ?>
                      <?php
                        $watermark_selected_class = ''; // Set the css selected class to empty
                        $watermark_printed = false; // Initiate the watermark printed flag
                        foreach ( $watermark_images_urls as $url => $attr ):
                          if ( $header_watermark_options_vals['watermark_src'] == $url ) {
                            $watermark_selected_class = ' selected';
                            $watermark_printed = true;
                          }
                        ?>
                        <tr class="<?php echo ( $img_count % 2 ) ? 'alternate ' : ''; ?>author-other status-inherit<?php echo ( $header_watermark_options_vals['watermark_src'] == $url ) ? ' selected' : '' ; ?>" valign="top">
                          <th class="check-column" scope="row">
                            <input id="watermark-src-<?php echo $img_count; ?>" type="radio" name="aa_header_watermark_options[watermark_src]" <?php echo ( $header_watermark_options_vals['watermark_src'] == $url ) ? 'checked="checked" ' : '' ; ?>value="<?php echo $url; ?>" />
                          </th>
                          <td class="column-icon media-icon">
                            <img src="<?php echo $attr['150x150']; ?>" width="60" height="60" alt="<?php echo $attr['name']; ?>" />
                          </td>
                          <td>
                            <label for="watermark-src-<?php echo $img_count; ?>"><?php echo $attr['name']; ?></label>
                          </td>
                          <td>
                            <?php echo date( 'Y/m/d', $attr['modified'] ); ?>
                          </td>
                        </tr>
                        <?php $img_count++; ?>
                        <?php $watermark_selected_class = ''; // Reset the watermark selected flag ?>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                  <?php
                    // If the watermark printed flag is still false then print a hidden field to contain the existing value
                    if ( ! $watermark_printed && $header_watermark_options_vals['watermark_src'] ) {
                  ?>
                  <input id="existing-watermark-src" type="hidden" name="aa_header_watermark_options[existing_watermark_src]" value="<?php echo $header_watermark_options_vals['watermark_src']; ?>" />
                  <?php
                    }
                  ?>
                  <div class="tablenav bottom">
                    <div class="tablenav-pages">
                      <span class="displaying-num"><?php echo $paginated_images_urls['total_records']; ?> items</span>
                      <span class="pagination-links">
                        <a class="first-page<?php echo ( 1 == $current_page ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>" title="<?php _e( 'Go to the first page', 'aa-header-watermark' ) ?>">«</a>
                        <a class="prev-page<?php echo ( 1 == $current_page ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>&wp=<?php echo ( $paginated_images_urls['pages']['previous'] ) ? $paginated_images_urls['pages']['previous'] : 1; ?>" title="<?php _e( 'Go to the previous page', 'aa-header-watermark' ) ?>">‹</a>
                        <span class="paging-input"><?php echo $current_page; ?> of <span class="total-pages"><?php echo $paginated_images_urls['pages']['total']; ?></span></span>
                        <a class="next-page<?php echo ( $current_page == $paginated_images_urls['pages']['total'] ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>&wp=<?php echo ( $paginated_images_urls['pages']['next'] ) ? $paginated_images_urls['pages']['next'] : $paginated_images_urls['pages']['total']; ?>" title="<?php _e( 'Go to the next page', 'aa-header-watermark' ) ?>">›</a>
                        <a class="last-page<?php echo ( $current_page == $paginated_images_urls['pages']['total'] ) ? ' disabled' : ''; ?>" href="?page=aa-header-watermark&wob=<?php echo $url_order_by; ?>&wo=<?php echo $order; ?>&wp=<?php echo $paginated_images_urls['pages']['total']; ?>" title="<?php _e( 'Go to the last page', 'aa-header-watermark' ) ?>">»</a>
                      </span>
                    </div>
                  </div>
              </li>
              <li>
                <label for="watermark-pos-left"><?php _e( 'Watermark Left Position', 'aa-header-watermark' ); ?></label>
                <input type="text" id="watermark-pos-left" name="aa_header_watermark_options[watermark_pos_left]" value="<?php echo $header_watermark_options_vals['watermark_pos_left']; ?>" size="4">
                <label for="watermark-pos-left"><?php _e( 'pixels', 'aa-header-watermark' ); ?></label>
              </li>
              <li>
                <label for="watermark-pos-top"><?php _e( 'Watermark Top Position', 'aa-header-watermark' ); ?></label>
                <input type="text" id="watermark-top-left" name="aa_header_watermark_options[watermark_pos_top]" value="<?php echo $header_watermark_options_vals['watermark_pos_top']; ?>" size="4">
                <label for="watermark-pos-top"><?php _e( 'pixels', 'aa-header-watermark' ); ?></label>
              </li>
              <li>
                <label for="watermark-opacity"><?php _e( 'Watermark Opacity', 'aa-header-watermark' ); ?></label>
                <input type="text" id="watermark-opacity" name="aa_header_watermark_options[watermark_opacity]" value="<?php echo $header_watermark_options_vals['watermark_opacity']; ?>" size="3">
                <label for="watermark-opacity">%</label>
              </li>
            </ol>
          </fieldset>
        </li>
      <?php endif; ?>
      <?php if ( $header_images_urls ): ?>
        <li>
          <h3><?php _e( 'Header Image(s)', 'aa-header-watermark' );?></h3>
          <input id="aa-timestamp" name="aa_header_watermark_options[timestamp]" type="hidden" value="<?php echo time(); ?>" />
          <ol>
            <li>
              <input type="radio" id="random-image-random" class="random-image-radio" name="aa_header_watermark_options[random_image]" value="random" <?php echo ( 'random' == $header_watermark_options_vals['random_image'] || ! $header_watermark_options_vals['random_image'] ) ? 'checked="checked" ' : ''; ?>><label for="random-image-random"><?php _e( 'Use Random Image', 'aa-header-watermark' ) ?></label>
            </li>
            <li>
              <input type="radio" id="random-image-static" class="random-image-radio" name="aa_header_watermark_options[random_image]" value="static" <?php echo ( 'static' == $header_watermark_options_vals['random_image'] ) ? 'checked="checked" ' : ''; ?>><label for="random-image-static"><?php _e( 'Use Static Image', 'aa-header-watermark' ) ?></label>
            </li>
            <li id="random-header-table">
              <fieldset>
                <legend><?php _e( 'Random Header Image(s)', 'aa-header-watermark' );?></legend>
                <ol class="available-headers">
                  <?php $header_count = 0; ?>
                  <?php foreach ( $header_images_urls as $url => $attr ): ?>
                    <li class="default-header">
                      <input id="random-header-blacklist-<?php echo $header_count; ?>" type="checkbox" value="<?php echo $url; ?>" name="aa_header_watermark_options[random_header_blacklist][]"<?php echo ( ! is_array( $header_watermark_options_vals['random_header_blacklist'] ) || ! in_array( $url, $header_watermark_options_vals['random_header_blacklist'] ) ) ? ' checked="checked" ' : ''; ?>>
                        <label for="random-header-blacklist-<?php echo $header_count; ?>">
                        <img width="230" title="<?php echo $attr['name']; ?>" alt="<?php echo $attr['name']; ?>" src="<?php echo $url; ?>">
                      </label>
                    </li>
                    <?php $header_count++; ?>
                  <?php endforeach; ?>
                </ol>
              </fieldset>
            </li>
            <li id="static-header-table">
              <fieldset>
                <legend><?php _e('Select Header Image', 'aa-header-watermark' );?></legend>
                <ol class="available-headers">
                  <?php $header_count = 0; ?>
                  <?php foreach ( $header_images_urls as $url => $attr ):?>
                    <li class="default-header">
                      <input id="static-header-<?php echo $header_count; ?>" type="radio" value="<?php echo $url; ?>" name="aa_header_watermark_options[header_image_src]"<?php echo ( $header_watermark_options_vals['header_image_src'] == $url || ( ! $header_watermark_options_vals['header_image_src'] && $header_count == 0 ) ) ? ' checked="checked" ' : ''; ?>>
                        <label for="static-header-<?php echo $header_count; ?>">
                        <img width="230" title="<?php echo $attr['name']; ?>" alt="<?php echo $attr['name']; ?>" src="<?php echo $url; ?>">
                      </label>
                    </li>
                    <?php $header_count++; ?>
                  <?php endforeach; ?>
                </ol>
              </fieldset>
            </li>
          </ol>
        </li>
      <?php endif; ?>
      <li class="submit">
        <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ) ?>" />
      </li>
    </ol>
  </form>
</div>
<?php
}

if ( defined('GD_VERSION') && '2.0.28' <= GD_VERSION ) {
  $header_watermark_options = get_option( 'aa_header_watermark_options' );

  if ( $header_watermark_options['header_image_width'] && $header_watermark_options['header_image_height'] ) {
    $watermark = false;
    if ( $header_watermark_options['add_watermark'] ) {
      $watermark['img'] = $header_watermark_options['watermark_src'];
      $watermark['left'] = $header_watermark_options['watermark_pos_left'];
      $watermark['top'] = $header_watermark_options['watermark_pos_top'];
      $watermark['opacity'] = $header_watermark_options['watermark_opacity'];
    }

    $header_image_type = $header_watermark_options['random_image'];
    $header_image_src = $header_watermark_options['header_image_src'];
    $header_image_blacklist = $header_watermark_options['random_header_blacklist'];

    // Get the possible image files                      
    $aa_media_library = new aa_media_library();
    $image_urls = $aa_media_library->get_images_urls( array( 'width'=>$header_watermark_options['header_image_width'], 'height'=>$header_watermark_options['header_image_height'] ) );
    // Add the default header images if there are any
    // We need the Custom_Image_Header class for this next bit
    if ( ! class_exists( 'Custom_Image_Header' ) ) {
      $admin_url = admin_url();
      require_once( ABSPATH . 'wp-admin/custom-header.php' );
    }

    // If images were found select a random/static header image
    if ( $image_urls ) {
      // If the random image option is selected use a random image, otherwise use the chosen static one
      if ( ! $header_image_type || $header_image_type == 'random' || ! isset( $header_watermark_options['header_image_src'] ) || ! $header_watermark_options['header_image_src'] ) {
        // Extract the images that are on the blacklist
        if ( $header_image_blacklist ) {
          foreach ( $header_image_blacklist as $blacklist_url ) {
            if ( array_key_exists( $blacklist_url, $image_urls ) ) {
              unset( $image_urls[$blacklist_url] );
            }
          }
        }

        $random_image = array_rand( $image_urls );
        $header_image = $random_image;
      } elseif ('static' == $header_image_type ) {
        $header_image = $header_image_src;
      }
      
      // If watermarking is enabled, fetch the watermarked image
      if ( $watermark && $watermark['img'] ) {
        // Get the watermarked image
        $header_image = plugins_url() . '/header-watermark/util/watermark.php?image=' . $header_image . '&watermark=' . $watermark['img'] . '&left=' . $watermark['left'] . '&top=' . $watermark['top'] . '&opacity=' . $watermark['opacity'];
      }
      
      // Set the header image
      if ( ! defined( 'HEADER_IMAGE' ) ) {
        define( 'HEADER_IMAGE', $header_image );
      }
    }
  }
}
?>
