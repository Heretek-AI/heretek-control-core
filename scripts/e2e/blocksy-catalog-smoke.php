<?php
/** Read-only verification against the development checkout's complete registration. */
if(!class_exists('EMCP_Tools_Admin')) { require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php'; }
$categories=(new EMCP_Tools_Admin())->get_all_tools(); $found=array();
foreach($categories as $category) {
    foreach($category['tools'] as $slug=>$tool) {
        if(!str_starts_with($slug,'emcp-tools/blocksy-')) { continue; }
        $expected=str_starts_with($slug,'emcp-tools/blocksy-blocks-')?'blocksy-blocks':(str_starts_with($slug,'emcp-tools/blocksy-theme-')?'themes':'plugins');
        if($category['platform']!==$expected || isset($found[$slug])) { WP_CLI::error('Incorrect or duplicate Blocksy tool placement: '.$slug); }
        $found[$slug]=$expected;
    }
}
if(count($found)!==16) { WP_CLI::error('Expected sixteen Blocksy tools including compatibility dispatchers.'); }
$names=EMCP_Tools_Plugin::instance()->get_active_ability_names();
foreach(array('blocksy-theme-read','blocksy-content-read','blocksy-extensions-read') as $slug) {
    if(!in_array('emcp-tools/'.$slug,$names,true)) { WP_CLI::error('Missing registered read tool: '.$slug); }
}
WP_CLI::success('Sixteen Blocksy tools have distinct ownership; theme and Companion reads register.');
WP_CLI::log('Blocks toggle: '.(EMCP_Tools_Page_Builders::enabled('blocksy-blocks')?'on':'off'));
