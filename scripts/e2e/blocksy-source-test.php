<?php
/** Test-only WP-CLI bridge: load new source adapters without deploying plugin files. */
WP_CLI::add_hook('before_wp_load', function () {
    if (str_replace('\\','/',WP_CLI::get_config('path')) !== 'F:/laragon/www/elementor-mcp') {
        WP_CLI::error('Blocksy source bridge is restricted to the local test site.');
    }
});
WP_CLI::add_wp_hook('plugins_loaded', function () {
    foreach(array('theme','content') as $part) {
        require_once dirname(__DIR__,2).'/pro/includes/abilities/class-blocksy-'.$part.'-integration.php';
    }
    add_action('wp_abilities_api_init',function() {
        (new EMCP_Tools_Blocksy_Theme_Integration())->register();
        (new EMCP_Tools_Blocksy_Content_Integration())->register();
    },5);
    add_filter('emcp_tools_ability_names',function($names) {
        return array_merge($names,(new EMCP_Tools_Blocksy_Theme_Integration())->get_ability_names(),(new EMCP_Tools_Blocksy_Content_Integration())->get_ability_names());
    },5);
    add_filter('option_emcp_tools_disabled_tools', function($tools) {
        return array_values(array_filter((array)$tools,static fn($tool)=>!str_starts_with($tool,'emcp-tools/blocksy-')));
    });
},100);
