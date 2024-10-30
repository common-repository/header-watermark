jQuery(window).load(function () {
  display_watermark_images();
  display_header_images();
  jQuery('#add-watermark').change(function() {
    display_watermark_images();
  });
  jQuery('input:radio.random-image-radio').change(function() {
    display_header_images();
  });
});

function display_watermark_images() {
  if (jQuery('#add-watermark').attr('checked')) {
    jQuery('#watermark-image-table').css('display','block');
  } else {
    jQuery('#watermark-image-table').css('display','none');
  }
}

function display_header_images() {
  if (jQuery('input:radio.random-image-radio:checked').val() == 'random') {
    jQuery('#random-header-table').css('display','block');
    jQuery('#static-header-table').css('display','none');
  } else if (jQuery('input:radio.random-image-radio:checked').val() == 'static') {
    jQuery('#static-header-table').css('display','block');
    jQuery('#random-header-table').css('display','none');
  }
}
