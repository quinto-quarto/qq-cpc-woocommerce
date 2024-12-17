<?php
namespace QQCPC\Base;

class Activate {
    public static function activate() {
        flush_rewrite_rules();

        $default_options = array(
            'qq_cpc_api_token' => ''
        );

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}
