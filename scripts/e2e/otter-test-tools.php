<?php
// Test-only process bridge. Content calls still run through the real MCP adapter.
WP_CLI::add_hook('before_wp_load',static function() {
    if(str_replace('\\','/',WP_CLI::get_config('path'))!=='F:/laragon/www/elementor-mcp') { WP_CLI::error('Test site only.'); }
});
WP_CLI::add_wp_hook('plugins_loaded',static function() {
    require_once dirname(__DIR__,2).'/pro/includes/abilities/class-otter-integration.php';
    add_filter('option_emcp_tools_block_packs',static fn($packs)=>array_unique(array_merge((array)$packs,array('otter'))));
    add_filter('default_option_emcp_tools_block_packs',static fn()=>array('otter'));
    add_filter('option_emcp_tools_disabled_tools',static fn($tools)=>array_values(array_filter((array)$tools,static fn($t)=>!str_starts_with($t,'emcp-tools/otter-'))));
    add_action('wp_abilities_api_init',static function() {
        if(!wp_get_ability('emcp-tools/otter-get-context')) { (new EMCP_Tools_Otter_Integration())->register(); }
    },99);
    add_filter('emcp_tools_ability_names',static fn($names)=>array_unique(array_merge($names,(new EMCP_Tools_Otter_Integration())->get_ability_names())),99);
});
