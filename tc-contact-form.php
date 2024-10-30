<?php
/*
 * Plugin Name: Contact Form by tech-c.net
 * Version: 2.0.1
 * Plugin URI: https://tech-c.net/contact-form-for-wordpress/
 * Description: Plugin that shows a contact form by shortcode.
 * Author: tech-c.net
 * Author URI: http://tech-c.net
 * Copyright: tech-c.net
 * Requires at least: 4.0
 * Tested up to: 4.9.6
 * Donate link: https://tech-c.net/donation.php
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

function tc_contact_form() { 
  $style_url  = WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/tc-contact-form.css';
  $style_file = WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/tc-contact-form.css';
  if (file_exists($style_file)) {
    wp_register_style('tc-contact-form', $style_url);
    wp_enqueue_style('tc-contact-form');
  }
  
  $salutations = array(
    esc_html__('Please select', 'tc-contact-form'),
    esc_html__('Mr.', 'tc-contact-form'),
    esc_html__('Ms.', 'tc-contact-form')
  );
  
  $output = '';
  
  if (isset($_POST['sent'])) {
    
    if ((intval($_POST['salutation']) == 0) || ($_POST['thename'] == '') || (!is_email($_POST['email'])) || ($_POST['text'] == '')) {
      $error_msg = esc_html__('Some details are wrong. Please check the red fields.', 'tc-contact-form');
    } else {
      $error_msg = '';
    }
    
    if ((get_option('tc_contact_form_use_recaptcha') == '1') && ($error_msg == '')) {
      if (isset($_POST['g-recaptcha-response'])) {
        $data = array(
          'secret'   => get_option('tc_contact_form_recaptcha_privatekey'),
          'response' => sanitize_text_field($_POST['g-recaptcha-response']),
          'remoteip' => $_SERVER['REMOTE_ADDR']
        );       
        $remote_post_config = array(
          'timeout' => 10, 
          'body'    => $data
        );        
		    $request = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', $remote_post_config);
		    if (!is_wp_error($request)) {
		      $request_body = wp_remote_retrieve_body($request);
			    if (!$request_body) {
            $error_msg = esc_html__('An error occurred. Your message could not be sent. Please try again later.', 'tc-contact-form');
          } else {
            $jsonResponse = json_decode($request_body, true);
            if ((!isset($jsonResponse['success'])) || ($jsonResponse['success'] != 'true')) {
              $error_msg = esc_html__('The captcha is wrong!', 'tc-contact-form');
            }
          }
        }
      } else {
        $error_msg = esc_html__('An error occurred. Your message could not be sent. Please try again later.', 'tc-contact-form');
      }
    }
    
    if ($error_msg == '') {
      if (get_option('tc_contact_form_subject') != '') {
        $subject = get_option('tc_contact_form_subject');
      } else {
        $subject = 'Contact form at '.$_SERVER['SERVER_NAME'];
      }
      
      $header = array();
      $header[] = 'From: '.$salutations[intval($_POST['salutation'])].' '.sanitize_text_field(stripslashes_deep($_POST['thename'])).' <'.sanitize_email($_POST['email']).'>';
      $header[] = 'X-IP: '.getenv('REMOTE_ADDR');
      if (get_option('tc_contact_form_email_return_path') != '') {
        $header[] = 'Return-Path: <'.get_option('tc_contact_form_email_return_path').'>';
        add_action('phpmailer_init', 'tc_contact_form_returnpath_fix');
        function tc_contact_form_returnpath_fix($phpmailer) {
          $phpmailer->Sender = get_option('tc_contact_form_email_return_path');
        }
      }
      
      $attachments = array();
      $attachments_count = intval(get_option('tc_contact_form_attachment_count'));
      if ($attachments_count > 0) {
        if (!function_exists('wp_handle_upload')) {
          require_once(ABSPATH.'wp-admin/includes/file.php');
        }
        add_filter('upload_dir', 'tc_upload_dir');
        function tc_upload_dir($upload) {
          $upload['subdir'] = '/contact-form'.$upload['subdir'];
          $upload['path']   = $upload['basedir'].$upload['subdir'];
          $upload['url']    = $upload['baseurl'].$upload['subdir'];
          return $upload;
        }
        for ($i = 1; $i <= $attachments_count; $i++) {
          if ($_FILES['attachment'.$i]['name'] != '') {
            $uploadedfile = $_FILES['attachment'.$i];
            $upload_overrides = array(
              'test_form' => false
            );
            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
            $attachments[] = $movefile['file'];
          }
        }
      }
      
      if (wp_mail(get_option('tc_contact_form_email_to'), 
                  $subject, 
                  sanitize_textarea_field(stripslashes_deep($_POST['text'])), 
                  $header, 
                  $attachments) === true) {
        if (count($attachments) > 0) {
          foreach ($attachments as $attachment) {
            unlink($attachment);
          }
          remove_filter('upload_dir', 'tc_upload_dir');
        }
        $new_url = add_query_arg('success', 1, get_permalink());
        wp_redirect($new_url, 303);
        return;
      } else {
        $output .= '<p class="cf_error">'.esc_html__('An error occurred. Your message could not be sent. Please try again later.', 'tc-contact-form').'</p>';
      }
      
      if (count($attachments) > 0) {
        foreach ($attachments as $attachment) {
          unlink($attachment);
        }
        remove_filter('upload_dir', 'tc_upload_dir');
      }
      
    } else {
      $output .= '<p class="cf_error">'.$error_msg.'</p>';
    }
  }
  
  if ($_GET['success'] == '1') {
    $output .= '<p class="cf_success">'.esc_html__('Your message was received.', 'tc-contact-form').'</p>';
  }
  
  $output .= '<form class="cf_form" action="'.get_permalink().'" enctype="multipart/form-data" method="post">';
  
  $output .= '<div class="cf_tbl">';
  $output .= '<div class="cf_row">';
  $output .= '<div class="cf_cell_l">';
  $output .= '<div class="cf_labeldiv"><label for="id_salutation">'.esc_html__('Salutation', 'tc-contact-form').':</label></div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_cell_r">';
  $output .= '<div class="cf_inputdiv">';
  $input_style = 'cf_input';
  if ((isset($_POST['sent'])) && (intval($_POST['salutation']) == 0)) {
    $input_style .= ' cf_input_error';
  }
  $output .= '<select class="'.$input_style.'" id="id_salutation" name="salutation">';
  $i = 0;
  foreach ($salutations as $salutation) {
    if ($salutation != '-') {
      $output .= '<option value="'.$i.'"';
      if ($_POST['salutation'] == $i) {
        $output .= ' selected="selected"';
      }
      $output .= '>'.$salutation.'</option>';
      $i++;      
    }
  }
  $output .= '</select></div>';
  $output .= '</div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_row">';
  $output .= '<div class="cf_cell_l">';
  $output .= '<div class="cf_labeldiv"><label for="id_thename">'.esc_html__('Name', 'tc-contact-form').':</label></div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_cell_r">';
  $output .= '<div class="cf_inputdiv">';
  $input_style = 'cf_input';
  if ((isset($_POST['sent'])) && ($_POST['thename'] == '')) {
    $input_style .= ' cf_input_error';
  }
  $output .= '<input class="'.$input_style.'" id="id_thename" name="thename" type="text" value="'.esc_attr(sanitize_textarea_field(stripslashes_deep($_POST['thename']))).'" />';
  $output .= '</div>';
  $output .= '</div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_row">';
  $output .= '<div class="cf_cell_l">';
  $output .= '<div class="cf_labeldiv"><label for="id_email">'.esc_html__('Email', 'tc-contact-form').':</label></div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_cell_r">';
  $output .= '<div class="cf_inputdiv">';
  $input_style = 'cf_input';
  if ((isset($_POST['sent'])) && (!is_email($_POST['email']))) {
    $input_style .= ' cf_input_error';
  }
  $output .= '<input class="'.$input_style.'" id="id_email" name="email" type="text" value="'.esc_attr(sanitize_textarea_field(stripslashes_deep($_POST['email']))).'" />';
  $output .= '</div>';
  $output .= '</div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_row">';
  $output .= '<div class="cf_cell_l" style="vertical-align:top;">';
  $output .= '<div class="cf_labeldiv"><label for="id_text">'.esc_html__('Message', 'tc-contact-form').':</label></div>';
  $output .= '</div>';
  
  $output .= '<div class="cf_cell_r">';
  $output .= '<div class="cf_inputdiv">';
  $input_style = 'cf_input';
  if ((isset($_POST['sent'])) && ($_POST['text'] == '')) {
    $input_style .= ' cf_input_error';
  }
  $text_rows = intval(get_option('tc_contact_form_text_rows'));
  if ($text_rows < 1)
    $text_rows = 12;
  $output .= '<textarea class="'.$input_style.'" id="id_text" name="text" rows="'.$text_rows.'">'.esc_attr(sanitize_textarea_field(stripslashes_deep($_POST['text']))).'</textarea>';
  $output .= '</div>';
  $output .= '</div>';
  $output .= '</div>';
  
  $attachments_count = intval(get_option('tc_contact_form_attachment_count'));
  for ($i = 1; $i <= $attachments_count; $i++) {
    $output .= '<div class="cf_row">';
    $output .= '<div class="cf_cell_l">';
    $output .= '<div class="cf_labeldiv"><label for="id_attachment'.$i.'">'.esc_html__('Attachment', 'tc-contact-form').':</label></div>';
    $output .= '</div>';
    $output .= '<div class="cf_cell_r">';
    $output .= '<div class="cf_inputdiv">';
    $output .= '<input id="id_attachment'.$i.'" name="attachment'.$i.'" type="file" />';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
  }
  
  if (get_option('tc_contact_form_use_recaptcha') != '') {
    $output .= '<div class="cf_row">';
    $output .= '<div class="cf_cell_l"></div>';
    $output .= '<div class="cf_cell_r"><script src="https://www.google.com/recaptcha/api.js"></script>';
    $output .= '<div class="g-recaptcha" data-callback="enableBtn" data-sitekey="'.get_option('tc_contact_form_recaptcha_sitekey').'" data-size="normal" data-theme="light"></div>';
    $output .= '</div>';
    $output .= '</div>';
  }
  
  $output .= '<div class="cf_row">';
  $output .= '<div class="cf_cell_l"><input name="sent" type="hidden" value="sent" /></div>';
  $output .= '<div class="cf_cell_r">';
  $output .= '<div class="cf_inputdiv"><input id="cf_submitbtn" name="submit" type="submit" value="'.esc_html__('Submit', 'tc-contact-form').'" />';
  $output .= '</div>';
  $output .= '</div>';
  $output .= '</div>';
  
  $output .= '</div>';
  $output .= '</form>';
  
  return $output;
}

function tc_contact_form_options_page() {
  echo '<div class="wrap">';
  echo '<h1>Contact Form by tech-c.net</h1>';
  echo '<p>'.esc_html__('This plugin provides a contact form in order to send messages including file attachments. Optionally, the contact form can be protected with Google-Recaptcha. To show the contact form, simply put the following shortcode in the text at the desired location on the desired page', 'tc-contact-form').':<pre>[tc_contact_form]</pre></p>';
  echo '<p>'.esc_html__('Example', 'tc-contact-form').': <a rel="lytebox" target="_blank" href="'.WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/screenshot-2.jpg">'.esc_html__('Screenshot', 'tc-contact-form').'</a></p>';
  
  echo '<p>'.esc_html__('To see a live sample of the frontend, just visit the following page', 'tc-contact-form').': <a target="_blank" href="https://tech-c.net/contact/">https://tech-c.net/contact/</a></p>';
  echo '<p>'.esc_html__('If you need some changes to this or other plugins, contact me', 'tc-contact-form').': <a target="_blank" href="https://tech-c.net/contact/">https://tech-c.net/contact/</a></p>';
  echo '<p>'.esc_html__('Who like this plugin and has some money left, can also donate', 'tc-contact-form').':</p>';
  
  echo '<p align="center"><a target="_blank" href="https://tech-c.net/donation.php"><img src="'.WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/donate-paypal.png" /></a></p>';
  
  echo '<h2>'.esc_html__('Settings').'</h2>';
  echo '<form method="post" action="options.php">';
  echo settings_fields('tc_contact_form_options_group');
  echo do_settings_sections('tc_contact_form_options_group');
  echo '<table class="form-table">';
  
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Receiver email address', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_email_to" value="'.esc_attr(get_option('tc_contact_form_email_to')).'" />';
  echo '<p class="description">'.esc_html__('Specify the receiver email address. The form data will be send to this email address.', 'tc-contact-form').'</p>';
  echo '</td>';
  echo '</tr>';
  
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Email address for Return-Path', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_email_return_path" value="'.esc_attr(get_option('tc_contact_form_email_return_path')).'" />';
  echo '<p class="description">'.esc_html__('This is optional. If specified, this email address will be used as Return-Path in the email header.', 'tc-contact-form').'</p>';
  echo '</td>';
  echo '</tr>';
  
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Subject', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_subject" value="'.esc_attr(get_option('tc_contact_form_subject')).'" />';
  echo '<p class="description">'.esc_html__('This is optional. If specified, this line will be used as subject of the email. Otherwise, the following subject will be used', 'tc-contact-form').': Contact form at '.$_SERVER['SERVER_NAME'].'</p>';
  echo '</td>';
  echo '</tr>';
  
  $text_rows = intval(get_option('tc_contact_form_text_rows'));
  if ($text_rows < 1)
    $text_rows = 12;
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Text field lines', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_text_rows" value="'.$text_rows.'" />';
  echo '<p class="description">'.esc_html__('Specifies the number of lines of the text field. This value is interpreted different in different browsers. Default value is 12.', 'tc-contact-form').'</p>';
  echo '</td>';
  echo '</tr>';
  
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Attachments', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_attachment_count" value="'.intval(get_option('tc_contact_form_attachment_count')).'" />';
  echo '<p class="description">'.esc_html__('Number of attachments used in the form.', 'tc-contact-form').'</p>';
  echo '</td>';
  echo '</tr>';
  
  if (get_option('tc_contact_form_use_recaptcha') == '1') {
    $Checked = 'checked="checked"';
  } else {
    $Checked = '';
  }
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Use Recaptcha', 'tc-contact-form').':</th>';
  echo '<td><input type="checkbox" name="tc_contact_form_use_recaptcha" value="1" '.$Checked.'" />';
  echo '<p class="description">'.esc_html__('If checked, Googles Recaptcha will be used. Please, also specify the Website-Key and Secret-Key.', 'tc-contact-form').'</p>';
  echo '</td>';
  echo '</tr>';
  
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Recaptcha Website-Key', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_recaptcha_sitekey" value="'.esc_attr(get_option('tc_contact_form_recaptcha_sitekey')).'" />';
  echo '<p class="description">'.esc_html__('The Website-Key you will get from Google', 'tc-contact-form').': <a href="https://www.google.com/recaptcha/admin" target="_blank">https://www.google.com/recaptcha/admin</a></p>';
  echo '</td>';
  echo '</tr>';
  
  echo '<tr valign="top">';
  echo '<th scope="row">'.esc_html__('Recaptcha Secret-Key', 'tc-contact-form').':</th>';
  echo '<td><input type="text" name="tc_contact_form_recaptcha_privatekey" value="'.esc_attr(get_option('tc_contact_form_recaptcha_privatekey')).'" />';
  echo '<p class="description">'.esc_html__('The Secret-Key you will get from Google', 'tc-contact-form').': <a href="https://www.google.com/recaptcha/admin" target="_blank">https://www.google.com/recaptcha/admin</a></p>';
  echo '</td>';
  echo '</tr>';
  
  echo '</table>';
  echo submit_button();
  echo '</form>';
  echo '</div>';
}

function tc_contact_form_register_options_page() {
  add_options_page('Contact Form by tech-c.net', 'Contact Form by tech-c.net', 'manage_options', 'tc_contact_form_options_slug', 'tc_contact_form_options_page');
}

function tc_contact_form_register_settings() {
  register_setting('tc_contact_form_options_group', 'tc_contact_form_email_to');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_email_return_path');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_subject');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_text_rows');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_attachment_count');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_use_recaptcha');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_recaptcha_sitekey');
  register_setting('tc_contact_form_options_group', 'tc_contact_form_recaptcha_privatekey');
}

function tc_contact_form_settings_link($links) {
  $settings_link = '<a href="options-general.php?page=tc_contact_form_options_slug">'.esc_html__('Settings').'</a>';
  array_push($links, $settings_link);
  return $links;
}

function tc_contact_form_textdomain() {
  load_plugin_textdomain('tc-contact-form', false, dirname(plugin_basename(__FILE__)).'/languages/');
}

if (!function_exists('add_action')) {
  echo 'Please enable this plugin from your wp-admin.';
  exit;
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'tc_contact_form_settings_link');

add_shortcode('tc_contact_form', 'tc_contact_form');

if (is_admin()) {
  add_action('admin_menu', 'tc_contact_form_register_options_page');
  add_action('admin_init', 'tc_contact_form_register_settings');
}

add_action('plugins_loaded', 'tc_contact_form_textdomain');

// For testing purpose only
//add_filter('locale', 'tc_contact_form_change_language');
//function tc_contact_form_change_language($locale) {
//  return 'de_DE';
//}
?>