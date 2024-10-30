<?php
class aa_paginate {
  private static $page;
  private static $records_per_page;
  private static $selected_records;
  private static $total_records;
  private static $total_pages;
  private static $page_nav;
  private static $pages;
  private static $pagination;
  
  public static function get_page_contents( array $input_array, $records_per_page = null, $page = null ) {

    // Set the total records value
    self::$total_records = count( $input_array );
    // Set the records per page property
    self::$records_per_page = ( (int) $records_per_page > 0 ) ? (int) $records_per_page : self::$total_pages;
    // Set the total records value
    self::$total_pages = ceil( self::$total_records / self::$records_per_page );
    
    // CHECK???
    if ( self::$total_records ) {
      // Set the page property
      self::$page = ( (int) $page > 0 ) ? (int) $page : self::$page = 1;
      if ( self::$page > self::$total_pages ) {
        self::$page = self::$total_pages;
      }
      
      // Calculate the records included in this selection
      $end = self::$records_per_page * self::$page;
      $start = $end - ( self::$records_per_page - 1 );
      if ( $end > self::$total_records ) {
        $end = self::$total_records;
      }
      
      // Loop through the array and collect the records that are included
      $array_count = 1;
      foreach ( $input_array as $key => $value ) {
        if ( $array_count >= $start && $array_count <= $end ) {
          self::$selected_records[$key] = $value;
        } elseif ( $array_count > $end ) {
          break;
        }
        $array_count++;
      }
      
      // Set the page nav items
      // Previous
      if ( self::$page > 1 ) {
        self::$page_nav['previous'] = self::$page - 1;
      } else {
        self::$page_nav['previous'] = null;
      }
      // Next
      if ( self::$page < self::$total_pages ) {
        self::$page_nav['next'] = self::$page + 1;
      } else {
        self::$page_nav['next'] = null;
      }
      // Pages
      
      for ( $i = 1; $i <= self::$total_pages; $i++ ) {
        if ( $i == self::$page ) {
          self::$page_nav[$i] = 'current';
        } else {
          self::$page_nav[$i] = $i;
        }
      }
      self::$page_nav['total'] = self::$total_pages;
      
      // build the self::$pages array ready to return
      self::$pagination['records'] = self::$selected_records;
      self::$pagination['total_records'] = self::$total_records;
      self::$pagination['pages'] = self::$page_nav;
      
      return self::$pagination;
      
    } else {
      return false;
    }
  }
}
?>
