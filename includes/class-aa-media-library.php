<?php
class aa_media_library extends aa_directory_contents {

  private $filtered_images = false;
  private $images = false;
  private $image_types = array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG );
  private $upload_dir;
  private $upload_url;
  
  function __construct() {
    // Get the wp media library location
    $upload_dir_array = wp_upload_dir();
    
    $this->upload_dir = $upload_dir_array['basedir'];
    $this->upload_url = $upload_dir_array['baseurl'];
    
    // Construct the directory tree
    parent::__construct( $this->upload_dir );
    
    // Get the images
    $this->set_images_file_paths();
    
  }
  
  /*
   * $options:
   * thumbnails
   * width
   * height
   * sort
   *  attr = url/filename/date
   *  order = asc/desc
   */
  public function get_images_file_paths( array $options = null ) {
    if ( $options ) {
      $this->filtered_images = $this->images;
      
      // Strip out wp generated thumbnails, if the thumbnails option is set to false
      if ( array_key_exists( 'thumbnails', $options ) && false === $options['thumbnails'] ) {
        $this->filtered_images = $this->strip_wordpress_thumbnails($this->filtered_images);
      }
      
      // Filter images by width and/or height
      if ( array_key_exists( 'width', $options ) || array_key_exists( 'height', $options ) ) {
        // Loop through the images
        foreach ( $this->filtered_images as $url => $info ) {
          $img_info = getimagesize( $url ); //  Get the image details
          if ( array_key_exists( 'width', $options ) ) { // Check for the width option and match if it is set
            if ( $img_info[0] !== (int) $options['width'] ) {
              unset( $this->filtered_images[$url] );
            }
          }
          
          if ( array_key_exists( 'height', $options ) ) { // Check for the height option and match if it is set
            if ( $img_info[1] !== (int) $options['height'] ) {
              unset( $this->filtered_images[$url] );
            }
          }
        }
      }

      // Sort the images
      if ( array_key_exists( 'sort', $options ) && is_array( $options['sort'] ) ) {
        if ( array_key_exists( 'attr', $options['sort'] ) ) {
          // Sort by url desc (no need to order by url asc)
          if ( 'url' == $options['sort']['attr'] && array_key_exists( 'order', $options['sort'] ) && 'desc' == $options['sort']['order'] ) {
            krsort( $this->filtered_images );
          } elseif ( ( 'name' == $options['sort']['attr'] || 'modified' == $options['sort']['attr'] ) && array_key_exists( 'order', $options['sort'] ) ) {
            if ( 'asc' == $options['sort']['order'] ) {
              $sort_order = 'asc';
            } elseif ( $options['sort']['order'] == 'desc' ) {
              $sort_order = 'desc';
            }
            $this->filtered_images = $this->sort_images_by_attrs( $this->filtered_images, $options['sort']['attr'], $sort_order );
          }
        }
      }

      return $this->filtered_images;
      
    } else { // Return the unaltered images array
      return $this->images;
    }
  }
  
  public function get_images_urls( array $options = null ) {
    $images_urls = false;
    // Loop through the filepaths and replace with the urls
    if ( $this->get_images_file_paths( $options ) ) {
      foreach ( $this->get_images_file_paths( $options ) as $file_path => $info ) {
        $url = str_replace( $this->upload_dir, $this->upload_url, $file_path );
        $images_urls[$url] = $info;
        // Process the thumbnails too
        foreach ( $info as $name => $value ) {
          preg_match( '/^([0-9]+x[0-9]+)$/', $name, $matches );
          if ( $matches ) {
            $images_urls[$url][$name] = str_replace( $this->upload_dir, $this->upload_url, $value );
          }
        }
      }
    }
    
    return $images_urls;
  }
  
  private function set_images_file_paths() {
    // Clean out any directories and non-image files
    foreach ( $this->dir_contents as $file_url => $file_info ) {
      // Check the url target is a file and an allowed image type
      if ( 'file' == $file_info['type'] && in_array( exif_imagetype( $file_url ), $this->image_types ) ) {
        $this->images[$file_url] =  $file_info;
      }
    }
  }
  
  private function sort_images_by_attrs( $array, $attr, $order = 'asc' ) {
    $result = array();
       
    $values = array();
    foreach ( $array as $id => $value ) {
      $values[$id] = isset( $value[$attr] ) ? strtolower( $value[$attr] ) : '';
    }
       
    if ( 'asc' === $order ) {
      asort( $values );
    } else {
      arsort( $values );
    }
       
    foreach ( $values as $key => $value ) {
      $result[$key] = $array[$key];
    }
       
    return $result;
  }
  
  private function strip_wordpress_thumbnails( array $image_array ) {
    $filtered_image_array = null;
    $thumbnails = null;
    // Loop through the array items - if there is another array item with the same filename minus the -###x### then discard it.
    foreach ( $image_array as $file_url => $file_info ) {
      $matches = false;
      // Check for matches to the wordpress thumbnail file format and then check if the original filename exists in the array
      preg_match( '/.*(-[0-9]+x[0-9]+)\.([a-z]|[0-9])+$/', $file_url, $matches );
      if ( $matches ) { // If we got a match then check if the original file is in the array
        $original_file = str_replace( $matches[1], '', $file_url );
        if ( array_key_exists( $original_file,$image_array ) ) {
          $thumbnails[$original_file][substr( $matches[1], 1 )] = $file_url;
          continue;
        }
      } elseif ( strpos( $file_url, '/midsize-' ) ) {
        $thumbnails[str_replace( '/midsize-', '', $file_url )]['midsize'] = $file_url;
        continue;
      } elseif ( strpos( $file_url, '/cropped-' ) ) {
        $thumbnails[str_replace( '/cropped-', '', $file_url )]['cropped'] = $file_url;
        continue;
      }
      $filtered_image_array[$file_url] = $file_info;
    }
    
    // Attach the thumbnail images
    foreach ( $filtered_image_array as $url => $info ) {
      if ( array_key_exists( $url, $thumbnails ) ) {
        $filtered_image_array[$url] = array_merge( $filtered_image_array[$url], $thumbnails[$url] );
        //var_dump($thumbnails[$url]);
      }
    }
    
    return $filtered_image_array;
  }
}
?>
