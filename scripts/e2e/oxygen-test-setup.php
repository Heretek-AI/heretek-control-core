<?php
/** Select the integration; temporarily opt in to its test-site writes. No page writes. */
if (PHP_SAPI!=='cli') { exit; }
$site=($argv[1]??'')==='test'?'elementor-mcp':'msrplugins';
$_SERVER['HTTP_HOST']=$site.'.test'; $_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/'.$site.'/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php';
(new EMCP_Tools_Admin())->maybe_apply_default_disabled_tools();
if (!EMCP_Tools_Oxygen_Integration::supported()) { exit(1); }
$option='emcp_tools_disabled_tools'; $snapshot='emcp_oxygen_test_disabled_snapshot';
if (($argv[2]??'')==='restore') {
    $saved=get_option($snapshot,null);
    if (is_array($saved)) { update_option($option,$saved); delete_option($snapshot); }
    echo "Original tool choices restored.\n"; exit;
}
update_option(EMCP_Tools_Page_Builders::OPTION,'oxygen');
if ($site==='elementor-mcp') {
    $disabled=(array)get_option($option,array()); add_option($snapshot,$disabled,'',false);
    $writes=array(); foreach (EMCP_Tools_Oxygen_Integration::definitions() as $slug=>$def) { if ($def[3]) { $writes[]='emcp-tools/oxygen-'.$slug; } }
    update_option($option,array_values(array_diff($disabled,$writes)));
}
echo wp_json_encode(array('site'=>home_url(),'selected'=>EMCP_Tools_Page_Builders::selected(),'tabs'=>EMCP_Tools_Admin::visible_platform_tabs(),'tools'=>count(EMCP_Tools_Oxygen_Integration::definitions())));
