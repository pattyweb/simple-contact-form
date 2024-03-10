<?php
/*
 * Plugin Name:       Simple Contact Form
 * Description:       Simple contact form for WordPress.
 * Version:           1.0.0
 * Author:            Patricia Rodrigues
 * Author URI:        https://pattyweb.com.br/
 * Text Domain:       simple-contact-form
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!session_id()) {
    session_start();
}

class SimpleContactForm {
    public function __construct(){
        $this->load_textdomain();
        
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));
        add_shortcode('contact-form', array($this, 'load_shortcode'));
        add_action('wp_footer', array($this, 'load_scripts'));
        add_action('wp_ajax_custom_contact_form_handler', array($this, 'handle_contact_form'));
        add_action('wp_ajax_nopriv_custom_contact_form_handler', array($this, 'handle_contact_form'));
        add_action('init', array($this, 'create_custom_post_type'));
    }

    public function load_textdomain() {
        load_plugin_textdomain('simple-contact-form', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    

    function create_custom_post_type() {
        $labels = array(
            'name'          => _x('Contact Form Submissions', 'post type general name', 'simple-contact-form'),
            'singular_name' => _x('Contact Form Submission', 'post type singular name', 'simple-contact-form'),
        );
    
        $args = array(
            'labels'      => $labels,
            'menu_icon'   => 'dashicons-media-text',
            'public'      => false,
            'show_ui'     => true,
            'supports'    => array('title', 'editor'),
        );
    
        register_post_type('simple_contact_form', $args);
    }
    

    public function load_assets(){
        wp_enqueue_style('simple-contact-form', plugin_dir_url(__FILE__) . 'css/simple-contact-form.css', array(), 1, 'all');
        wp_enqueue_style('simple-contact-form-bootstrap', plugin_dir_url(__FILE__) . 'css/bootstrap.min.css', array(), 1, 'all');

        // Localize the script with new data
        $ajax_object = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom_contact_form_nonce')
        );
        wp_localize_script('simple-contact-form', 'ajax_object', $ajax_object);
    }

    public function load_shortcode() { 
         ob_start();
    ?>
    <form id="simple_contact_form_id" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" role="form">

        <?php wp_nonce_field('custom_contact_form_nonce', 'custom_contact_form_nonce'); ?>
        <input type="hidden" name="action" value="custom_contact_form_handler">

        <div class="mb-3">
        <label for="name" class="form-label"><?php _e('Name', 'simple-contact-form'); ?></label>
            <input type="text" class="form-control" id="name" name="name" required>
            <div class="validation-message" id="name-validation-message"></div>
        </div>

        <div class="mb-3">
        <label for="email" class="form-label"><?php _e('Email address', 'simple-contact-form'); ?></label>
            <input type="email" class="form-control" id="email" name="email" aria-describedby="emailHelp" required>
            <div class="validation-message" id="email-validation-message"></div>
        </div>

        <div class="mb-3">
        <label for="phone" class="form-label"><?php _e('Phone', 'simple-contact-form'); ?></label>
            <input type="tel" class="form-control" id="phone" name="phone" required>
            <div class="validation-message" id="phone-validation-message"></div>
        </div>

        <div class="mb-3">
            <label for=""message" class="form-label"><?php _e('Message', 'simple-contact-form'); ?></label>
            <textarea class="form-control" id="message" name="message" rows="3" required></textarea>
            <div class="validation-message" id="message-validation-message"></div>
        </div>
        <!-- Add captcha to the form -->
        <?php $this->generate_captcha(); ?>

            <button type="submit" class="btn btn-primary"><?php _e('Submit', 'simple-contact-form'); ?></button>
            </form>
    <div class="my-3">
        <div class="loading"></div>
        <div class="error-message"></div>
        <div class="sent-message"></div>
    </div>
    <?php
    return ob_get_clean();
    }

    // Add this function to your SimpleContactForm class
    public function generate_captcha() {
        $num1 = mt_rand(1, 10);
        $num2 = mt_rand(1, 10);
        $result = $num1 + $num2;

        // Store the result in a session variable for later verification
        $_SESSION['captcha_result'] = $result;

         // Display the captcha in your form
         echo '<div class="mb-3">';
         echo '<label for="captcha" class="form-label">' . __('CAPTCHA: What is', 'simple-contact-form') . ' ' . $num1 . ' + ' . $num2 . '?</label>';
         echo '<input type="text" class="form-control" id="captcha" name="captcha" required>';
         echo '<div class="validation-message" id="captcha-validation-message"></div>';
         echo '</div>';
    }

    public function load_scripts() { ?>
        <script>
            (function ($) {
                $(document).ready(function () {
                    $('#simple_contact_form_id').submit(function (event) {
                        event.preventDefault();
                        var form = $(this);
                        var formData = new FormData(form[0]);

                        // Clear previous validation messages
                        form.find('.validation-message').text('');

                        $.ajax({
                            type: form.attr('method'),
                            url: ajax_object.ajax_url,
                            data: formData,
                            contentType: false,
                            processData: false,
                            beforeSend: function () {
                                form.find('.loading').show();
                                form.find('.error-message, .sent-message').hide();
                            },
                            success: function (response) {
                                form.find('.loading').hide();
                                if (response.success) {
                                    var successMessage = $('<div class="pb-2 pt-2 form-message" id="success-message"></div>');
                                    successMessage.text(response.data.message);
                                    $('.my-3').append(successMessage);

                                    form.find('input, textarea').val('');

                                    // Add code to handle fading out messages
                                    setTimeout(function () {
                                        successMessage.fadeOut(function () {
                                            $(this).remove();
                                        });
                                    }, 5000);
                                } else {
                                    // Handle validation messages
                                    if (response.data.validation_messages) {
                                        $.each(response.data.validation_messages, function (field, message) {
                                            addValidationMessage(field, message);
                                        });
                                    }

                                    // Handle captcha-related messages
                                    if (response.data.message.includes('Captcha')) {
                                        var captchaMessage = $('<div class="pb-2 pt-2 form-message" id="captcha-message"></div>');
                                        captchaMessage.text(response.data.message);
                                        $('.my-3').append(captchaMessage);

                                        // Add code to handle fading out messages
                                        setTimeout(function () {
                                            captchaMessage.fadeOut(function () {
                                                $(this).remove();
                                            });
                                        }, 5000);
                                    } else {
                                        var errorMessage = $('<div class="pb-2 pt-2 form-message" id="error-message"></div>');
                                        errorMessage.text(response.data.message);
                                        $('.my-3').append(errorMessage);

                                        // Add code to handle fading out messages
                                        setTimeout(function () {
                                            errorMessage.fadeOut(function () {
                                                $(this).remove();
                                            });
                                        }, 5000);
                                    }
                                }
                            }
                        });
                    });
                });

                // Add validation messages
                function addValidationMessage(field, message) {
                    var validationMessage = $('#' + field + '-validation-message');
                    validationMessage.text(message);
                    validationMessage.show();
                }

            })(jQuery);
        </script>
    <?php } 

    


    public function handle_contact_form() {
        // Check nonce
        if (!isset($_POST['custom_contact_form_nonce']) || !wp_verify_nonce($_POST['custom_contact_form_nonce'], 'custom_contact_form_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
    
        // Initialize the $error array
        $error = array();
    
        // Validate Captcha
        $captcha_result = isset($_POST['captcha']) ? intval($_POST['captcha']) : 0;
        if (!isset($_SESSION['captcha_result']) || $captcha_result !== $_SESSION['captcha_result']) {
            wp_send_json_error(array('message' => __('Validation failed, captcha wrong!', 'simple-contact-form'), 'validation_messages' => $error));
        }
    
        // Process form data
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
    
        // Validate Name
        if (strlen($name) < 3) {
            wp_send_json_error(array('message' => __('Name: Please enter at least 3 characters.', 'simple-contact-form'), 'validation_messages' => $error));
        } elseif (strlen($name) > 20) {
            wp_send_json_error(array('message' => __('Name: Maximum length is 20 characters.', 'simple-contact-form'), 'validation_messages' => $error));
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
            wp_send_json_error(array('message' => __('Name: Only characters are allowed (no digits or special characters).', 'simple-contact-form'), 'validation_messages' => $error));
        }

        if ($email == '') {
            wp_send_json_error(array('message' => __('Email: Please enter email.', 'simple-contact-form'), 'validation_messages' => $error));
        } elseif (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", $email)) {
            wp_send_json_error(array('message' => __('Email: Enter a valid email address.', 'simple-contact-form'), 'validation_messages' => $error));
        }

        // Validate Phone
        if (strlen($phone) != 10) {
            //$error[] = 'Phone: Please enter a 10-digit mobile number without a country code.';
            wp_send_json_error(array('message' => __('Phone: Please enter a 10-digit mobile number without a country code.', 'simple-contact-form'), 'validation_messages' => $error));
        }

        // Validate Message
        if (strlen($message) == 0) {
            //$error[] = 'Message: Please enter your message.';
            wp_send_json_error(array('message' => __('Message: Please enter your message.', 'simple-contact-form'), 'validation_messages' => $error));
        }
    
        // If there are validation errors, send an error response
        if (!empty($error)) {
            wp_send_json_error(array('message' => __('Validation failed', 'simple-contact-form'), 'validation_messages' => $error));
        }
    
        // Insert data into the database
        $post_content = sprintf(
            __('Name: %s<br>Email: %s<br>Phone: %s<br>Message: %s', 'simple-contact-form'),
            esc_html($name),
            esc_html($email),
            esc_html($phone),
            esc_html($message)
        );
        
        $post_id = wp_insert_post(array(
            'post_title'   => $name,
            'post_content' => $post_content,
            'post_type'    => 'simple_contact_form',
            'post_status'  => 'publish',
        ));
        
    
        // Add custom fields (if needed)
        update_post_meta($post_id, '_contact_email', $email);
        update_post_meta($post_id, '_contact_phone', $phone);
    
        if ($post_id) {
            // Send email to admin
            $to = get_option('admin_email');
            $subject = __('New Contact Form Submission', 'simple-contact-form');
            $headers = array('Content-Type: text/html; charset=UTF-8');
        
            // Include form data in the email body
            $message_body = sprintf(
                __('Name: %s<br>Email: %s<br>Phone: %s<br>Message: %s', 'simple-contact-form'),
                esc_html($name),
                esc_html($email),
                esc_html($phone),
                esc_html($message)
            );
        
            wp_mail($to, $subject, $message_body, $headers);
        
            // Clear the captcha result from the session after successful submission
            unset($_SESSION['captcha_result']);
        
            wp_send_json_success(array('message' => __('Form submitted successfully', 'simple-contact-form')));
        } else {
            wp_send_json_error(array('message' => __('Error submitting form', 'simple-contact-form')));
        }
        
    }
    


}

new SimpleContactForm();
