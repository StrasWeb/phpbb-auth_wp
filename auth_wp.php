<?php


if (!defined('IN_PHPBB'))
{
    exit;
}
require_once 'auth_wp_config.php';

global $wp_object_cache, $wpdb, $table_prefix, $hasher;

require_once ABSPATH.'wp-includes/default-constants.php';
require_once ABSPATH.'wp-includes/plugin.php';
require_once ABSPATH.'wp-includes/functions.php';
require_once ABSPATH.'wp-includes/class-wp-error.php';
require_once ABSPATH.'wp-includes/pomo/translations.php';
require_once ABSPATH.'wp-includes/l10n.php';
require_once ABSPATH.'wp-includes/pluggable.php';
require_once ABSPATH.'wp-includes/wp-db.php';
require_once ABSPATH.'wp-includes/load.php';
require_once ABSPATH.'wp-includes/cache.php';
require_once ABSPATH.'wp-includes/capabilities.php';
require_once ABSPATH.'wp-includes/general-template.php';
require_once ABSPATH.'wp-includes/link-template.php';
require_once ABSPATH.'wp-includes/meta.php';
require_once ABSPATH.'wp-includes/class-phpass.php';

if (!function_exists('stripslashes_deep')) {
    /**
     * Navigates through an array and removes slashes from the values.
     *
     * If an array is passed, the array_map() function causes a callback to pass the
     * value back to the function. The slashes from this value will removed.
     *
     * @since 2.0.0
     *
     * @param mixed $value The value to be stripped.
     * @return mixed Stripped value.
     */
    function stripslashes_deep($value) {
        if ( is_array($value) ) {
            $value = array_map('stripslashes_deep', $value);
        } elseif ( is_object($value) ) {
            $vars = get_object_vars( $value );
            foreach ($vars as $key=>$data) {
                $value->{$key} = stripslashes_deep( $data );
            }
        } elseif ( is_string( $value ) ) {
            $value = stripslashes($value);
        }

        return $value;
    }
}

if (!function_exists('wp_unslash')) {
    /**
     * Remove slashes from a string or array of strings.
     *
     * This should be used to remove slashes from data passed to core API that
     * expects data to be unslashed.
     *
     * @since 3.6.0
     *
     * @param string|array $value String or array of strings to unslash.
     * @return string|array Unslashed $value
     */
    function wp_unslash( $value ) {
        return stripslashes_deep( $value );
    }
}

if (!function_exists('untrailingslashit')) {
    /**
     * Removes trailing slash if it exists.
     *
     * The primary use of this is for paths and thus should be used for paths. It is
     * not restricted to paths and offers no specific path support.
     *
     * @since 2.2.0
     *
     * @param string $string What to remove the trailing slash from.
     * @return string String without the trailing slash.
     */
    function untrailingslashit($string) {
        return rtrim($string, '/');
    }
}

if (!function_exists('sanitize_option')) {
    /**
     * Sanitises various option values based on the nature of the option.
     *
     * This is basically a switch statement which will pass $value through a number
     * of functions depending on the $option.
     *
     * @since 2.0.5
     *
     * @param string $option The name of the option.
     * @param string $value The unsanitised value.
     * @return string Sanitized value.
     */
    function sanitize_option($option, $value) {

        switch ( $option ) {
            case 'admin_email' :
            case 'new_admin_email' :
                $value = sanitize_email( $value );
                if ( ! is_email( $value ) ) {
                    $value = get_option( $option ); // Resets option to stored value in the case of failed sanitization
                    if ( function_exists( 'add_settings_error' ) )
                        add_settings_error( $option, 'invalid_admin_email', __( 'The email address entered did not appear to be a valid email address. Please enter a valid email address.' ) );
                }
                break;

            case 'thumbnail_size_w':
            case 'thumbnail_size_h':
            case 'medium_size_w':
            case 'medium_size_h':
            case 'large_size_w':
            case 'large_size_h':
            case 'mailserver_port':
            case 'comment_max_links':
            case 'page_on_front':
            case 'page_for_posts':
            case 'rss_excerpt_length':
            case 'default_category':
            case 'default_email_category':
            case 'default_link_category':
            case 'close_comments_days_old':
            case 'comments_per_page':
            case 'thread_comments_depth':
            case 'users_can_register':
            case 'start_of_week':
                $value = absint( $value );
                break;

            case 'posts_per_page':
            case 'posts_per_rss':
                $value = (int) $value;
                if ( empty($value) )
                    $value = 1;
                if ( $value < -1 )
                    $value = abs($value);
                break;

            case 'default_ping_status':
            case 'default_comment_status':
                // Options that if not there have 0 value but need to be something like "closed"
                if ( $value == '0' || $value == '')
                    $value = 'closed';
                break;

            case 'blogdescription':
            case 'blogname':
                $value = wp_kses_post( $value );
                $value = esc_html( $value );
                break;

            case 'blog_charset':
                $value = preg_replace('/[^a-zA-Z0-9_-]/', '', $value); // strips slashes
                break;

            case 'blog_public':
                // This is the value if the settings checkbox is not checked on POST. Don't rely on this.
                if ( null === $value )
                    $value = 1;
                else
                    $value = intval( $value );
                break;

            case 'date_format':
            case 'time_format':
            case 'mailserver_url':
            case 'mailserver_login':
            case 'mailserver_pass':
            case 'upload_path':
                $value = strip_tags( $value );
                $value = wp_kses_data( $value );
                break;

            case 'ping_sites':
                $value = explode( "\n", $value );
                $value = array_filter( array_map( 'trim', $value ) );
                $value = array_filter( array_map( 'esc_url_raw', $value ) );
                $value = implode( "\n", $value );
                break;

            case 'gmt_offset':
                $value = preg_replace('/[^0-9:.-]/', '', $value); // strips slashes
                break;

            case 'siteurl':
                if ( (bool)preg_match( '#http(s?)://(.+)#i', $value) ) {
                    $value = esc_url_raw($value);
                } else {
                    $value = get_option( $option ); // Resets option to stored value in the case of failed sanitization
                    if ( function_exists('add_settings_error') )
                        add_settings_error('siteurl', 'invalid_siteurl', __('The WordPress address you entered did not appear to be a valid URL. Please enter a valid URL.'));
                }
                break;

            case 'home':
                if ( (bool)preg_match( '#http(s?)://(.+)#i', $value) ) {
                    $value = esc_url_raw($value);
                } else {
                    $value = get_option( $option ); // Resets option to stored value in the case of failed sanitization
                    if ( function_exists('add_settings_error') )
                        add_settings_error('home', 'invalid_home', __('The Site address you entered did not appear to be a valid URL. Please enter a valid URL.'));
                }
                break;

            case 'WPLANG':
                $allowed = get_available_languages();
                if ( ! in_array( $value, $allowed ) && ! empty( $value ) )
                    $value = get_option( $option );
                break;

            case 'illegal_names':
                if ( ! is_array( $value ) )
                    $value = explode( ' ', $value );

                $value = array_values( array_filter( array_map( 'trim', $value ) ) );

                if ( ! $value )
                    $value = '';
                break;

            case 'limited_email_domains':
            case 'banned_email_domains':
                if ( ! is_array( $value ) )
                    $value = explode( "\n", $value );

                $domains = array_values( array_filter( array_map( 'trim', $value ) ) );
                $value = array();

                foreach ( $domains as $domain ) {
                    if ( ! preg_match( '/(--|\.\.)/', $domain ) && preg_match( '|^([a-zA-Z0-9-\.])+$|', $domain ) )
                        $value[] = $domain;
                }
                if ( ! $value )
                    $value = '';
                break;

            case 'timezone_string':
                $allowed_zones = timezone_identifiers_list();
                if ( ! in_array( $value, $allowed_zones ) && ! empty( $value ) ) {
                    $value = get_option( $option ); // Resets option to stored value in the case of failed sanitization
                    if ( function_exists('add_settings_error') )
                        add_settings_error('timezone_string', 'invalid_timezone_string', __('The timezone you have entered is not valid. Please select a valid timezone.') );
                }
                break;

            case 'permalink_structure':
            case 'category_base':
            case 'tag_base':
                $value = esc_url_raw( $value );
                $value = str_replace( 'http://', '', $value );
                break;

            case 'default_role' :
                if ( ! get_role( $value ) && get_role( 'subscriber' ) )
                    $value = 'subscriber';
                break;
        }

        /**
         * Filter an option value following sanitization.
         *
         * @since 2.3.0
         *
         * @param string $value  The sanitized option value.
         * @param string $option The option name.
         */
        $value = apply_filters( "sanitize_option_{$option}", $value, $option );

        return $value;
    }
}

if (!function_exists('sanitize_key')) {
    /**
     * Sanitizes a string key.
     *
     * Keys are used as internal identifiers. Lowercase alphanumeric characters, dashes and underscores are allowed.
     *
     * @since 3.0.0
     *
     * @param string $key String key
     * @return string Sanitized key
     */
    function sanitize_key( $key ) {
        $raw_key = $key;
        $key = strtolower( $key );
        $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

        /**
         * Filter a sanitized key string.
         *
         * @since 3.0.0
         *
         * @param string $key     Sanitized key.
         * @param string $raw_key The key prior to sanitization.
         */
        return apply_filters( 'sanitize_key', $key, $raw_key );
    }
}

if (!function_exists('get_user_meta')) {
    /**
     * Retrieve user meta field for a user.
     *
     * @since 3.0.0
     * @uses get_metadata()
     * @link http://codex.wordpress.org/Function_Reference/get_user_meta
     *
     * @param int $user_id User ID.
     * @param string $key Optional. The meta key to retrieve. By default, returns data for all keys.
     * @param bool $single Whether to return a single value.
     * @return mixed Will be an array if $single is false. Will be value of meta data field if $single
     *  is true.
     */
    function get_user_meta($user_id, $key = '', $single = false) {
        return get_metadata('user', $user_id, $key, $single);
    }
}

if (!function_exists('update_user_caches')) {
    /**
     * Update all user caches
     *
     * @since 3.0.0
     *
     * @param object $user User object to be cached
     */
    function update_user_caches($user) {
        wp_cache_add($user->ID, $user, 'users');
        wp_cache_add($user->user_login, $user->ID, 'userlogins');
        wp_cache_add($user->user_email, $user->ID, 'useremail');
        wp_cache_add($user->user_nicename, $user->ID, 'userslugs');
    }
}

if (!function_exists('urlencode_deep')) {
    /**
     * Navigates through an array and encodes the values to be used in a URL.
     *
     *
     * @since 2.2.0
     *
     * @param array|string $value The array or string to be encoded.
     * @return array|string $value The encoded array (or string from the callback).
     */
    function urlencode_deep($value) {
        $value = is_array($value) ? array_map('urlencode_deep', $value) : urlencode($value);
        return $value;
    }
}

if (!function_exists('wp_parse_str')) {
    /**
     * Parses a string into variables to be stored in an array.
     *
     * Uses {@link http://www.php.net/parse_str parse_str()} and stripslashes if
     * {@link http://www.php.net/magic_quotes magic_quotes_gpc} is on.
     *
     * @since 2.2.1
     *
     * @param string $string The string to be parsed.
     * @param array $array Variables will be stored in this array.
     */
    function wp_parse_str( $string, &$array ) {
        parse_str( $string, $array );
        if ( get_magic_quotes_gpc() )
            $array = stripslashes_deep( $array );
        /**
         * Filter the array of variables derived from a parsed string.
         *
         * @since 2.3.0
         *
         * @param array $array The array populated with variables.
         */
        $array = apply_filters( 'wp_parse_str', $array );
    }
}

if (!function_exists('remove_accents')) {
    /**
     * Converts all accent characters to ASCII characters.
     *
     * If there are no accent characters, then the string given is just returned.
     *
     * @since 1.2.1
     *
     * @param string $string Text that might have accent characters
     * @return string Filtered string with replaced "nice" characters.
     */
    function remove_accents($string) {
        if ( !preg_match('/[\x80-\xff]/', $string) )
            return $string;

        if (seems_utf8($string)) {
            $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(194).chr(170) => 'a', chr(194).chr(186) => 'o',
            chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
            chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
            chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
            chr(195).chr(134) => 'AE',chr(195).chr(135) => 'C',
            chr(195).chr(136) => 'E', chr(195).chr(137) => 'E',
            chr(195).chr(138) => 'E', chr(195).chr(139) => 'E',
            chr(195).chr(140) => 'I', chr(195).chr(141) => 'I',
            chr(195).chr(142) => 'I', chr(195).chr(143) => 'I',
            chr(195).chr(144) => 'D', chr(195).chr(145) => 'N',
            chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
            chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
            chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
            chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
            chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
            chr(195).chr(158) => 'TH',chr(195).chr(159) => 's',
            chr(195).chr(160) => 'a', chr(195).chr(161) => 'a',
            chr(195).chr(162) => 'a', chr(195).chr(163) => 'a',
            chr(195).chr(164) => 'a', chr(195).chr(165) => 'a',
            chr(195).chr(166) => 'ae',chr(195).chr(167) => 'c',
            chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
            chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
            chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
            chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
            chr(195).chr(176) => 'd', chr(195).chr(177) => 'n',
            chr(195).chr(178) => 'o', chr(195).chr(179) => 'o',
            chr(195).chr(180) => 'o', chr(195).chr(181) => 'o',
            chr(195).chr(182) => 'o', chr(195).chr(184) => 'o',
            chr(195).chr(185) => 'u', chr(195).chr(186) => 'u',
            chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
            chr(195).chr(189) => 'y', chr(195).chr(190) => 'th',
            chr(195).chr(191) => 'y', chr(195).chr(152) => 'O',
            // Decompositions for Latin Extended-A
            chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
            chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
            chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
            chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
            chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
            chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
            chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
            chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
            chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
            chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
            chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
            chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
            chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
            chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
            chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
            chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
            chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
            chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
            chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
            chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
            chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
            chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
            chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
            chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
            chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
            chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
            chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
            chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
            chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
            chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
            chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
            chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
            chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
            chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
            chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
            chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
            chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
            chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
            chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
            chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
            chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
            chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
            chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
            chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
            chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
            chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
            chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
            chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
            chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
            chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
            chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
            chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
            chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
            chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
            chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
            chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
            chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
            chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
            chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
            chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
            chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
            chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
            chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
            chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
            // Decompositions for Latin Extended-B
            chr(200).chr(152) => 'S', chr(200).chr(153) => 's',
            chr(200).chr(154) => 'T', chr(200).chr(155) => 't',
            // Euro Sign
            chr(226).chr(130).chr(172) => 'E',
            // GBP (Pound) Sign
            chr(194).chr(163) => '',
            // Vowels with diacritic (Vietnamese)
            // unmarked
            chr(198).chr(160) => 'O', chr(198).chr(161) => 'o',
            chr(198).chr(175) => 'U', chr(198).chr(176) => 'u',
            // grave accent
            chr(225).chr(186).chr(166) => 'A', chr(225).chr(186).chr(167) => 'a',
            chr(225).chr(186).chr(176) => 'A', chr(225).chr(186).chr(177) => 'a',
            chr(225).chr(187).chr(128) => 'E', chr(225).chr(187).chr(129) => 'e',
            chr(225).chr(187).chr(146) => 'O', chr(225).chr(187).chr(147) => 'o',
            chr(225).chr(187).chr(156) => 'O', chr(225).chr(187).chr(157) => 'o',
            chr(225).chr(187).chr(170) => 'U', chr(225).chr(187).chr(171) => 'u',
            chr(225).chr(187).chr(178) => 'Y', chr(225).chr(187).chr(179) => 'y',
            // hook
            chr(225).chr(186).chr(162) => 'A', chr(225).chr(186).chr(163) => 'a',
            chr(225).chr(186).chr(168) => 'A', chr(225).chr(186).chr(169) => 'a',
            chr(225).chr(186).chr(178) => 'A', chr(225).chr(186).chr(179) => 'a',
            chr(225).chr(186).chr(186) => 'E', chr(225).chr(186).chr(187) => 'e',
            chr(225).chr(187).chr(130) => 'E', chr(225).chr(187).chr(131) => 'e',
            chr(225).chr(187).chr(136) => 'I', chr(225).chr(187).chr(137) => 'i',
            chr(225).chr(187).chr(142) => 'O', chr(225).chr(187).chr(143) => 'o',
            chr(225).chr(187).chr(148) => 'O', chr(225).chr(187).chr(149) => 'o',
            chr(225).chr(187).chr(158) => 'O', chr(225).chr(187).chr(159) => 'o',
            chr(225).chr(187).chr(166) => 'U', chr(225).chr(187).chr(167) => 'u',
            chr(225).chr(187).chr(172) => 'U', chr(225).chr(187).chr(173) => 'u',
            chr(225).chr(187).chr(182) => 'Y', chr(225).chr(187).chr(183) => 'y',
            // tilde
            chr(225).chr(186).chr(170) => 'A', chr(225).chr(186).chr(171) => 'a',
            chr(225).chr(186).chr(180) => 'A', chr(225).chr(186).chr(181) => 'a',
            chr(225).chr(186).chr(188) => 'E', chr(225).chr(186).chr(189) => 'e',
            chr(225).chr(187).chr(132) => 'E', chr(225).chr(187).chr(133) => 'e',
            chr(225).chr(187).chr(150) => 'O', chr(225).chr(187).chr(151) => 'o',
            chr(225).chr(187).chr(160) => 'O', chr(225).chr(187).chr(161) => 'o',
            chr(225).chr(187).chr(174) => 'U', chr(225).chr(187).chr(175) => 'u',
            chr(225).chr(187).chr(184) => 'Y', chr(225).chr(187).chr(185) => 'y',
            // acute accent
            chr(225).chr(186).chr(164) => 'A', chr(225).chr(186).chr(165) => 'a',
            chr(225).chr(186).chr(174) => 'A', chr(225).chr(186).chr(175) => 'a',
            chr(225).chr(186).chr(190) => 'E', chr(225).chr(186).chr(191) => 'e',
            chr(225).chr(187).chr(144) => 'O', chr(225).chr(187).chr(145) => 'o',
            chr(225).chr(187).chr(154) => 'O', chr(225).chr(187).chr(155) => 'o',
            chr(225).chr(187).chr(168) => 'U', chr(225).chr(187).chr(169) => 'u',
            // dot below
            chr(225).chr(186).chr(160) => 'A', chr(225).chr(186).chr(161) => 'a',
            chr(225).chr(186).chr(172) => 'A', chr(225).chr(186).chr(173) => 'a',
            chr(225).chr(186).chr(182) => 'A', chr(225).chr(186).chr(183) => 'a',
            chr(225).chr(186).chr(184) => 'E', chr(225).chr(186).chr(185) => 'e',
            chr(225).chr(187).chr(134) => 'E', chr(225).chr(187).chr(135) => 'e',
            chr(225).chr(187).chr(138) => 'I', chr(225).chr(187).chr(139) => 'i',
            chr(225).chr(187).chr(140) => 'O', chr(225).chr(187).chr(141) => 'o',
            chr(225).chr(187).chr(152) => 'O', chr(225).chr(187).chr(153) => 'o',
            chr(225).chr(187).chr(162) => 'O', chr(225).chr(187).chr(163) => 'o',
            chr(225).chr(187).chr(164) => 'U', chr(225).chr(187).chr(165) => 'u',
            chr(225).chr(187).chr(176) => 'U', chr(225).chr(187).chr(177) => 'u',
            chr(225).chr(187).chr(180) => 'Y', chr(225).chr(187).chr(181) => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin)
            chr(201).chr(145) => 'a',
            // macron
            chr(199).chr(149) => 'U', chr(199).chr(150) => 'u',
            // acute accent
            chr(199).chr(151) => 'U', chr(199).chr(152) => 'u',
            // caron
            chr(199).chr(141) => 'A', chr(199).chr(142) => 'a',
            chr(199).chr(143) => 'I', chr(199).chr(144) => 'i',
            chr(199).chr(145) => 'O', chr(199).chr(146) => 'o',
            chr(199).chr(147) => 'U', chr(199).chr(148) => 'u',
            chr(199).chr(153) => 'U', chr(199).chr(154) => 'u',
            // grave accent
            chr(199).chr(155) => 'U', chr(199).chr(156) => 'u',
            );

            // Used for locale-specific rules
            $locale = get_locale();

            if ( 'de_DE' == $locale ) {
                $chars[ chr(195).chr(132) ] = 'Ae';
                $chars[ chr(195).chr(164) ] = 'ae';
                $chars[ chr(195).chr(150) ] = 'Oe';
                $chars[ chr(195).chr(182) ] = 'oe';
                $chars[ chr(195).chr(156) ] = 'Ue';
                $chars[ chr(195).chr(188) ] = 'ue';
                $chars[ chr(195).chr(159) ] = 'ss';
            } elseif ( 'da_DK' === $locale ) {
                $chars[ chr(195).chr(134) ] = 'Ae';
                $chars[ chr(195).chr(166) ] = 'ae';
                $chars[ chr(195).chr(152) ] = 'Oe';
                $chars[ chr(195).chr(184) ] = 'oe';
                $chars[ chr(195).chr(133) ] = 'Aa';
                $chars[ chr(195).chr(165) ] = 'aa';
            }

            $string = strtr($string, $chars);
        } else {
            // Assume ISO-8859-1 if not UTF-8
            $chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
                .chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
                .chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
                .chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
                .chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
                .chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
                .chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
                .chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
                .chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
                .chr(252).chr(253).chr(255);

            $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

            $string = strtr($string, $chars['in'], $chars['out']);
            $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
            $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
            $string = str_replace($double_chars['in'], $double_chars['out'], $string);
        }

        return $string;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    /**
     * Properly strip all HTML tags including script and style
     *
     * @since 2.9.0
     *
     * @param string $string String containing HTML tags
     * @param bool $remove_breaks optional Whether to remove left over line breaks and white space chars
     * @return string The processed string.
     */
    function wp_strip_all_tags($string, $remove_breaks = false) {
        $string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
        $string = strip_tags($string);

        if ( $remove_breaks )
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);

        return trim( $string );
    }
}

if (!function_exists('sanitize_user')) {
    /**
     * Sanitizes a username, stripping out unsafe characters.
     *
     * Removes tags, octets, entities, and if strict is enabled, will only keep
     * alphanumeric, _, space, ., -, @. After sanitizing, it passes the username,
     * raw username (the username in the parameter), and the value of $strict as
     * parameters for the 'sanitize_user' filter.
     *
     * @since 2.0.0
     *
     * @param string $username The username to be sanitized.
     * @param bool $strict If set limits $username to specific characters. Default false.
     * @return string The sanitized username, after passing through filters.
     */
    function sanitize_user( $username, $strict = false ) {
        $raw_username = $username;
        $username = wp_strip_all_tags( $username );
        $username = remove_accents( $username );
        // Kill octets
        $username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );
        $username = preg_replace( '/&.+?;/', '', $username ); // Kill entities

        // If strict, reduce to ASCII for max portability.
        if ( $strict )
            $username = preg_replace( '|[^a-z0-9 _.\-@]|i', '', $username );

        $username = trim( $username );
        // Consolidate contiguous whitespace
        $username = preg_replace( '|\s+|', ' ', $username );

        /**
         * Filter a sanitized username string.
         *
         * @since 2.0.1
         *
         * @param string $username     Sanitized username.
         * @param string $raw_username The username prior to sanitization.
         * @param bool   $strict       Whether to limit the sanitization to specific characters. Default false.
         */
        return apply_filters( 'sanitize_user', $username, $raw_username, $strict );
    }
}

if (!function_exists('wp_authenticate_cookie')) {
    /**
     * Authenticate the user using the WordPress auth cookie.
     */
    function wp_authenticate_cookie($user, $username, $password) {
        if ( is_a($user, 'WP_User') ) { return $user; }

        if ( empty($username) && empty($password) ) {
            $user_id = wp_validate_auth_cookie();
            if ( $user_id )
                return new WP_User($user_id);

            global $auth_secure_cookie;

            if ( $auth_secure_cookie )
                $auth_cookie = SECURE_AUTH_COOKIE;
            else
                $auth_cookie = AUTH_COOKIE;

            if ( !empty($_COOKIE[$auth_cookie]) )
                return new WP_Error('expired_session', __('Please log in again.'));

            // If the cookie is not set, be silent.
        }

        return $user;
    }
}

if (!function_exists('wp_authenticate_username_password')) {
    /**
     * Authenticate the user using the username and password.
     */
    add_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
    function wp_authenticate_username_password($user, $username, $password) {
        if ( is_a($user, 'WP_User') ) { return $user; }

        if ( empty($username) || empty($password) ) {
            if ( is_wp_error( $user ) )
                return $user;

            $error = new WP_Error();

            if ( empty($username) )
                $error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));

            if ( empty($password) )
                $error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));

            return $error;
        }

        $user = get_user_by('login', $username);

        if ( !$user )
            return new WP_Error( 'invalid_username', sprintf( __( '<strong>ERROR</strong>: Invalid username. <a href="%s" title="Password Lost and Found">Lost your password</a>?' ), wp_lostpassword_url() ) );

        $user = apply_filters('wp_authenticate_user', $user, $password);
        if ( is_wp_error($user) )
            return $user;

        if ( !wp_check_password($password, $user->user_pass, $user->ID) )
            return new WP_Error( 'incorrect_password', sprintf( __( '<strong>ERROR</strong>: The password you entered for the username <strong>%1$s</strong> is incorrect. <a href="%2$s" title="Password Lost and Found">Lost your password</a>?' ),
            $username, wp_lostpassword_url() ) );

        return $user;
    }
}

if (!function_exists('wp_signon')) {
    /**
     * Authenticate user with remember capability.
     *
     * The credentials is an array that has 'user_login', 'user_password', and
     * 'remember' indices. If the credentials is not given, then the log in form
     * will be assumed and used if set.
     *
     * The various authentication cookies will be set by this function and will be
     * set for a longer period depending on if the 'remember' credential is set to
     * true.
     *
     * @since 2.5.0
     *
     * @param array $credentials Optional. User info in order to sign on.
     * @param bool $secure_cookie Optional. Whether to use secure cookie.
     * @return object Either WP_Error on failure, or WP_User on success.
     */
    function wp_signon( $credentials = '', $secure_cookie = '' ) {
        if ( empty($credentials) ) {
            if ( ! empty($_POST['log']) )
                $credentials['user_login'] = $_POST['log'];
            if ( ! empty($_POST['pwd']) )
                $credentials['user_password'] = $_POST['pwd'];
            if ( ! empty($_POST['rememberme']) )
                $credentials['remember'] = $_POST['rememberme'];
        }
        
        if ( !empty($credentials['remember']) )
            $credentials['remember'] = true;
        else
            $credentials['remember'] = false;

        // TODO do we deprecate the wp_authentication action?
        do_action_ref_array('wp_authenticate', array(&$credentials['user_login'], &$credentials['user_password']));

        if ( '' === $secure_cookie )
            $secure_cookie = is_ssl();

        $secure_cookie = apply_filters('secure_signon_cookie', $secure_cookie, $credentials);

        global $auth_secure_cookie; // XXX ugly hack to pass this to wp_authenticate_cookie
        $auth_secure_cookie = $secure_cookie;

        add_filter('authenticate', 'wp_authenticate_cookie', 30, 3);

        $user = wp_authenticate($credentials['user_login'], $credentials['user_password']);

        if ( is_wp_error($user) ) {
            if ( $user->get_error_codes() == array('empty_username', 'empty_password') ) {
                $user = new WP_Error('', '');
            }

            return $user;
        }
        wp_set_auth_cookie($user->ID, $credentials['remember'], $secure_cookie);

        do_action('wp_login', $user->user_login, $user);
        return $user;
    }
}

$table_prefix  = 'wp_';
$wp_object_cache= new WP_Object_Cache();
$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
wp_set_wpdb_vars();
wp_initial_constants();
wp_plugin_directory_constants();
wp_cookie_constants();

function logout_wp()
{
    wp_logout();
}

function login_wp($username, $password)
{
    global $db, $hasher;

    $user = wp_signon(array('user_login'=>$username, 'user_password'=>$password, 'remember'=>true));

    if ( is_a($user, 'WP_User') ) {
        $sql = 'SELECT user_id, username, user_password, user_passchg, user_pass_convert, user_email, user_type, user_login_attempts
        FROM ' . USERS_TABLE . "
        WHERE username = '" . $db->sql_escape($username) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        
        if ($row) {
            return array(
                'status'        => LOGIN_SUCCESS,
                'error_msg'     => false,
                'user_row'      => $row
            );
        } else {
            return array(
                'status'        => LOGIN_SUCCESS_CREATE_PROFILE,
                'error_msg'     => false,
                'user_row'      => $row
            );
        }
    } else {
        $errors = array_keys($user->errors);
        
        if ($errors[0] == 'incorrect_password') {
            return array(
                'status'    => LOGIN_ERROR_PASSWORD,
                'error_msg' => 'LOGIN_ERROR_PASSWORD',
                'user_row'  => array('user_id' => ANONYMOUS),
            );
        } else if ($errors[0] == 'empty_password') {
            return array(
                'status'    => LOGIN_ERROR_PASSWORD,
                'error_msg' => 'NO_PASSWORD_SUPPLIED',
                'user_row'  => array('user_id' => ANONYMOUS),
            );
        } else if ($errors[0] == 'invalid_username') {
            return array(
                'status'    => LOGIN_ERROR_USERNAME,
                'error_msg' => 'LOGIN_ERROR_USERNAME',
                'user_row'  => array('user_id' => ANONYMOUS),
            );
        } else {
            return array(
                'status'    =>  LOGIN_ERROR_EXTERNAL_AUTH ,
                'error_msg' => 'LOGIN_ERROR_EXTERNAL_AUTH',
                'user_row'  => array('user_id' => ANONYMOUS),
            );
        }
    }
    
}
