<?php
class aa_directory_contents {
  protected $root_dir; // Root directory of this object
  protected $dir_tree; // Array representation of the directory structure
  protected $dir_contents; // Array of files contained in root_dir and its children
  private $sub_dirs; // Array of paths to sub directories
  
  function __construct( $dir ) {
    // Store the root directory
    $this->root_dir = $dir;
    // Set the dir tree
    $this->set_dir_tree();
    // Set the dir contents
    $this->set_dir_file_paths();
  }
  
  public function get_dir_contents() {
    return $this->dir_contents;
  }
  
  private function set_dir_file_paths( $path = null, $array = null ) {
    $sub_dirs = null;
    
    if ( ! $path ) {
      $path = $this->root_dir;
    }
    
    if ( ! $array ) {
      $array = $this->dir_tree;
    }
    // Loop through the array and add the directory contents to the 
    foreach( $array as $dir => $child ) {
      if ( null === $child ) { // empty directory
        $this->dir_contents[$path . '/' . $dir]['name'] = $dir;
        $this->dir_contents[$path . '/' . $dir]['type'] = 'dir';
        $this->dir_contents[$path . '/' . $dir]['modified'] = filemtime($path . '/' . $dir);
      } elseif ( is_array( $child ) ) { // directory
        $sub_dirs[$path . '/' . $dir] = $child;
      } else { // file
        $this->dir_contents[$path . '/' . $child]['name'] = $child;
        $this->dir_contents[$path . '/' . $child]['type'] = 'file';
        $this->dir_contents[$path . '/' . $child]['modified'] = filemtime($path . '/' . $child);
      }
    }
    // Loop through any sub directories
    if ( $sub_dirs ) {
      foreach ( $sub_dirs as $new_path => $new_dir_array ) {
        $this->set_dir_file_paths( $new_path, $new_dir_array );
      }
    }
  }
  
  public function get_dir_tree() {
    return $this->dir_tree;
  }
  
  private function set_dir_tree( $dir = null ) {
    $this->dir_tree = $this->build_dir_tree($dir);
  }
  
  private function build_dir_tree( $dir ) {
    if ( ! $dir ) {
      $dir = $this->root_dir;
    }
    $dir_tree = array();
    $sub_dirs = false;
    // Try to open the directory
    if ( $handle = opendir( $dir ) ) {
      // Loop through the directory contents, adding directories and files to the dir_tree array as we go
      while ( ( $file = readdir( $handle ) ) !== false ) {
        if ( '.' != $file && '..' != $file ) { //  Ignore .. and .
          $file_path = $dir . '/' . $file;
          if ( 'file' == filetype( $file_path ) ) { // Check any files to see if they match the criteria
            $dir_tree[] = $file;
          } elseif ( 'dir' == filetype( $file_path ) ) { // Store any sub-directories to investigate
            $dir_tree[$file] = null;
            $sub_dirs[$file] = $file_path; // Directory
          }
        }
      }
      closedir($handle);
      
      $dirs_array = null;
      $files_array = null;
      
      // Sort the array - directories then files
      if ( count( $dir_tree ) ) {
        foreach( $dir_tree as $key => $value ) { // Loop through the array items
          if ( is_array($value) || null === $value ) { // Directories
            $dirs_array[$key] = $value;
            unset( $dir_tree[$key] );
          } else { // Files
            $files_array[$key] = $value;
            unset( $dir_tree[$key] );
          }
        }
      }
      // Sort the directories and files
      if ( $dirs_array ) {
        ksort( $dirs_array );
        foreach ( $dirs_array as $key => $value ) {
          $dir_tree[$key] = $value;
        }
      }
      if ( $files_array ) {
        asort( $files_array );
        // Remove the thumbnails from the files array
        //$files_array = remove_wordpress_thumbnails($files_array);
        foreach ( $files_array as $key => $value ) {
          $dir_tree[$key] = $value;
        }
      }
    }
    
    // If there were any sub-directories returned then iterate through them
    if ( $sub_dirs ) {
      foreach( $sub_dirs as $dir => $path ) {
        // Get any further dirs/files and add them to the results
        $branch = $this->build_dir_tree( $path );
        if ( $branch ) {
          $dir_tree[$dir] = $branch;
        }
      }
    }
    
    return $dir_tree;
    
  }
}
?>
