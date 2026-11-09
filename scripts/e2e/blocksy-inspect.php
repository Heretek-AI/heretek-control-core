<?php
/** Read-only native Blocksy API probe. Run with wp eval-file. */
$blocks = array();
foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block ) {
    if ( str_starts_with( $name, 'blocksy/' ) ) { $blocks[$name] = count($block->attributes); }
}
$manager = function_exists('blc_get_ext') && function_exists('blocksy_manager') ? blocksy_manager() : null;
$extensions = class_exists('Blocksy\\ExtensionsManager') ? new \Blocksy\ExtensionsManager() : null;
echo wp_json_encode(array(
    'site'=>home_url(), 'theme'=>get_template(), 'version'=>wp_get_theme('blocksy')->get('Version'),
    'emcp_source'=>defined('EMCP_TOOLS_DIR')?EMCP_TOOLS_DIR:null,
    'new_integration'=>class_exists('EMCP_Tools_Blocksy_Content_Integration'),
    'companion'=>defined('BLOCKSY_VERSION')?BLOCKSY_VERSION:null,
    'blocks'=>$blocks, 'content_blocks'=>post_type_exists('ct_content_block'),
    'extensions'=>$extensions ? array_keys($extensions->get_preliminary_exts_info()) : array(),
    'active_extensions'=>get_option('blocksy_active_extensions',array()),
    'theme_mod_keys'=>array_keys((array)get_theme_mods()),
    'content_block_meta'=>get_registered_meta_keys('post','ct_content_block'),
), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
