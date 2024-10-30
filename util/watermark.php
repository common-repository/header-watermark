<?php
if ( ! function_exists( 'exif_imagetype' ) ) {
  function exif_imagetype ( $filename ) {
    if ( ( list( $width, $height, $type, $attr ) = getimagesize( $filename ) ) !== false ) {
      return $type;
    }
  return false;
  }
}

// Set the content type header
header( 'content-type: image/jpeg' );

// Check the image and watermark files are from this domain
$error_notice = false;

// Check that the supplied image references are valid
if ( ! preg_match( '/^https?:\/\/' . $_SERVER['SERVER_NAME'] . '\//', $_REQUEST['image'] ) || ! in_array( exif_imagetype( $_REQUEST['image'] ), array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
  $error_notice = 'Invalid image source';
} elseif ( ! preg_match( '/^https?:\/\/' . $_SERVER['SERVER_NAME'] . '\//', $_REQUEST['watermark'] ) || ! in_array( exif_imagetype( $_REQUEST['watermark'] ), array( IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
  $error_notice = 'Invalid watermark source';
}

// If there is an error notice then return it
if ( $error_notice ) {
  // Create a blank image and add some text
  $im = imagecreatetruecolor( 120, 20 );
  $text_color = imagecolorallocate( $im, 233, 14, 91 );
  imagestring( $im, 1, 5, 5, $error_notice, $text_color );

  // Output the image
  imagejpeg( $im );

  // Free up memory
  imagedestroy( $im );
  exit();
}

// Get the watermark image
switch ( exif_imagetype( $_REQUEST['watermark'] ) ) {
  case IMAGETYPE_GIF:
    $watermark = imagecreatefromgif( $_REQUEST['watermark'] );
    break;
  case IMAGETYPE_JPEG:
    $watermark = imagecreatefromjpeg( $_REQUEST['watermark'] );
    break;
  case IMAGETYPE_PNG:
    $watermark = imagecreatefrompng( $_REQUEST['watermark'] );
    break;
  default:
    // Shouldn't get here
}

// Get the watermark dimensions
$watermark_width = imagesx( $watermark );
$watermark_height = imagesy( $watermark );

// Get the image
switch ( exif_imagetype( $_REQUEST['image'] ) ) {
  case IMAGETYPE_GIF:
    $image = imagecreatefromgif( $_REQUEST['image'] );
    break;
  case IMAGETYPE_JPEG:
    $image = imagecreatefromjpeg( $_REQUEST['image'] );
    break;
  case IMAGETYPE_PNG:
    $image = imagecreatefrompng( $_REQUEST['image'] );
    break;
  default:
    // Shouldn't get here
}

$size = getimagesize( $_REQUEST['image'] );
$dest_x = $_REQUEST['left'];
$dest_y = $_REQUEST['top'];
$opacity = $_REQUEST['opacity'];


imagecopymerge( $image, $watermark, $dest_x, $dest_y, 0, 0, $watermark_width, $watermark_height, $opacity );

imagejpeg( $image, Null, 100 );
imagedestroy( $image );
imagedestroy( $watermark );

?>
