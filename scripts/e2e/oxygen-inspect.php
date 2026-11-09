<?php
/** Read-only installed Oxygen inspection. */
if (PHP_SAPI !== 'cli') { exit; }
$site=($argv[1]??'dev')==='test'?'elementor-mcp':'msrplugins';
$_SERVER['HTTP_HOST']=$site.'.test'; $_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/'.$site.'/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID')); wp_set_current_user((int)$admins[0]);
$type=$argv[2]??'';
if ($type) {
    echo wp_json_encode(array('element'=>\Breakdance\Elements\get_elements_for_builder(array($type)), 'universal'=>\Breakdance\Elements\UniversalControls\getUniversalControls()),JSON_PRETTY_PRINT);
} else {
    echo wp_json_encode(array('site'=>home_url(),'mode'=>defined('BREAKDANCE_MODE')?BREAKDANCE_MODE:null,'version'=>defined('__BREAKDANCE_VERSION')?__BREAKDANCE_VERSION:null,'theme'=>get_template(),'builder'=>EMCP_Tools_Page_Builders::selected(),'breakpoints'=>\Breakdance\Config\Breakpoints\get_breakpoints(),'post_types'=>array(BREAKDANCE_TEMPLATE_POST_TYPE,BREAKDANCE_HEADER_POST_TYPE,BREAKDANCE_FOOTER_POST_TYPE,BREAKDANCE_BLOCK_POST_TYPE),'elements'=>\Breakdance\Elements\get_element_classnames()),JSON_PRETTY_PRINT);
}
