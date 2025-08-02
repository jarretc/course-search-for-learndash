<?php
/**
    * Plugin Name: Course Search for LearnDash
    * Description: Shortcode to search all or currently enrolled courses in LearnDash.
    * Version: 1.0
    * Author: Jarret Cade
    * Author URI: https://orangedotdevelopment.com
    * Plugin URI: https://orangedotdevelopment.com/plugins/course-search-for-learndash/
    * Text Domain: odd-course-search-learndash
    * Domain Path: /languages
    * Requires Plugins: sfwd-lms
    * License: GPLv2 or later
*/

function odd_cs_form_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'enrolled' => 'no',
            'content' => 'no'
        ),
    $atts, 'ld_course_search' );

    ob_start();
    ?>
    <div class="course-search-form-wrapper">
        <form method="POST" action="" class="course-search-form" id="course-search-form">
            <div class="search-input-group">
                <input
                    type="text" 
                    name="search_term" 
                    id="search_term"
                    placeholder="<?php echo sprintf(
                        esc_html__( 'Search %s', 'odd-course-search-learndash' ),
                        LearnDash_Custom_Label::get_label( 'courses' )
                    ); ?>"
                    required
                />
                <input
                    type="hidden"
                    name="atts"
                    id="atts"
                    value="<?php echo esc_attr( json_encode( $atts ) ); ?>"
                />
                <input
                    type="hidden"
                    name="search_nonce"
                    id="search_nonce"
                    value="<?php echo esc_attr( wp_create_nonce('course_search_nonce') ); ?>"
                />
            </div>

            <div id="search-loading" style="display: none;">
                <p><?php echo esc_html__( 'Searching...', 'odd-course-search-learndash' ); ?></p>
            </div>
            
            <div id="ajax-search-results" class="custom-search-results" style="display: none;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ld_course_search', 'odd_cs_form_shortcode');

function odd_cs_search() {

    if (!wp_verify_nonce($_POST['search_nonce'], 'course_search_nonce')) {
        wp_die('Security check failed');
    }

    if ( isset( $_POST['atts'] ) ) {
        $atts = json_decode(stripslashes($_POST['atts']), true );
        $atts = array_map( 'sanitize_text_field',  $atts );
    }

    if ( ! is_array( $atts ) || ! array_key_exists( 'enrolled', $atts ) ) {
        wp_send_json_error();
    }

    $enrolled = $atts['enrolled'];

    if ( $enrolled === 'yes' && is_user_logged_in() ) {
        $args = array(
            'post__in' => learndash_user_get_enrolled_courses( get_current_user_id() ),
            'post_type' => 'sfwd-courses',
            's' => sanitize_text_field($_POST['search_term']),
            'post_status' => 'publish'
        );
    } elseif ( $enrolled === 'no' || ! $enrolled ) {
        $args = array(
            'post_type' => 'sfwd-courses',
            's' => sanitize_text_field($_POST['search_term']),
            'post_status' => 'publish'
        );
    } else {
        wp_send_json_error();
    }

    $query = new WP_Query($args);

    $results = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            if ( isset( $atts['content'] ) && $atts['content'] === 'yes' ) {
                $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words(get_the_content(), 20);
            } else {
                $excerpt = '';
            }

            $results[] = array(
                'title' => get_the_title(),
                'url' => get_permalink(),
                'excerpt' => $excerpt
            );
        }
    }
    
    wp_reset_postdata();

	if ( empty($results) ) {
		wp_send_json_error();
	}

    wp_send_json_success(
        array('results' => $results )
    );
}
add_action('wp_ajax_odd_cs_search', 'odd_cs_search');
add_action('wp_ajax_nopriv_odd_cs_search', 'odd_cs_search');

function enqueue_odd_cs_scripts() {
    wp_enqueue_script('odd-cs-js', plugin_dir_url( __FILE__ ) . '/assets/js/search-results.js', array('jquery'), '1.0', true);
	wp_enqueue_style('odd-cs-style', plugin_dir_url( __FILE__ ) . '/assets/css/style.css', array(), '1.0');
    
    // Localize script for AJAX
    wp_localize_script('odd-cs-js', 'odd_cs_search', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'no_results' => sprintf(
            esc_html__('No %s found.', 'odd-course-search-learndash'),
            LearnDash_Custom_Label::label_to_lower('courses')
        ),
        'invalid_search' => esc_html__('Search failed, please try again.', 'odd-course-search-learndash')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_odd_cs_scripts');