<?php
// Process-local source adapter bridge. No persistent integration/tool options change.
WP_CLI::add_hook('before_wp_load',static function() {
    if(str_replace('\\','/',WP_CLI::get_config('path'))!=='F:/laragon/www/elementor-mcp') { WP_CLI::error('Test site only.'); }
});
WP_CLI::add_wp_hook('plugins_loaded',static function() {
    require_once dirname(__DIR__,2).'/pro/includes/abilities/class-visual-composer-integration.php';
    add_filter('option_emcp_tools_page_builder',static fn()=>'visual-composer');
    add_filter('option_emcp_tools_disabled_tools',static fn($tools)=>array_values(array_filter((array)$tools,static fn($t)=>!str_starts_with($t,'emcp-tools/visual-composer-'))));
    add_action('wp_abilities_api_init',static function() { (new EMCP_Tools_Visual_Composer_Integration())->register(); },5);
    add_filter('emcp_tools_ability_names',static fn($names)=>array_merge($names,(new EMCP_Tools_Visual_Composer_Integration())->get_ability_names()),5);
});
