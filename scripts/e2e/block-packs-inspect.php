<?php
if(PHP_SAPI!=='cli') { exit; }
$site=($argv[1]??'dev')==='test'?'elementor-mcp':'msrplugins';
$_SERVER['HTTP_HOST']=$site.'.test'; $_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/'.$site.'/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php';
$rows=array();
foreach(EMCP_Tools_Page_Builders::block_packs() as $id=>$pack) { $rows[$id]=array('available'=>EMCP_Tools_Page_Builders::available($id),'enabled'=>EMCP_Tools_Page_Builders::enabled($id)); }
echo wp_json_encode(array('site'=>home_url(),'theme'=>get_template(),'packs'=>$rows,'tabs'=>EMCP_Tools_Admin::visible_platform_tabs()),JSON_PRETTY_PRINT);
if($site==='elementor-mcp') {
    $admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID')); wp_set_current_user((int)$admins[0]);
    $checks=array();
    $off=static fn()=>array('kadence-blocks','generateblocks');
    add_filter('pre_option_emcp_tools_block_packs',$off);
    $checks['spectra_disabled']=is_wp_error((new EMCP_Tools_Block_Pack_Integration('spectra'))->execute('get-context',array()));
    $checks['kadence_independent']=!is_wp_error((new EMCP_Tools_Block_Pack_Integration('kadence-blocks'))->execute('get-context',array()));
    $checks['generateblocks_independent']=!is_wp_error((new EMCP_Tools_Block_Pack_Integration('generateblocks'))->execute('get-context',array()));
    $checks['gutenberg_enabled']=EMCP_Tools_Page_Builders::enabled('gutenberg');
    remove_filter('pre_option_emcp_tools_block_packs',$off);
    wp_set_current_user(0);
    foreach(array_keys($rows) as $id) { $checks[$id.'_anonymous_denied']=is_wp_error((new EMCP_Tools_Block_Pack_Integration($id))->execute('get-context',array())); }
    echo "\n".wp_json_encode($checks,JSON_PRETTY_PRINT);
    exit(in_array(false,$checks,true)?1:0);
}
