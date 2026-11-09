<?php
// Read-only live check of the real module and admin submenu registration.
if(!defined('WP_CLI')) { exit; }
if(!class_exists('EMCP_Tools_Admin')) { require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php'; }
$module=EMCP_Tools_Modules_Registry::instance()->get('templates');
$method=new ReflectionMethod(EMCP_Tools_Admin::class,'get_submenus');
$method->setAccessible(true);
$menus=$method->invoke(new EMCP_Tools_Admin());
$visible=isset($menus['emcp-tools-templates']);
$expected=$module && $module->is_active() && $module->is_available();
if($visible!==$expected) { WP_CLI::error('Templates submenu does not match its module state.'); }
echo wp_json_encode(array('selected_builder'=>EMCP_Tools_Page_Builders::selected(),'module_active'=>$module?$module->is_active():null,'module_available'=>$module?$module->is_available():null,'templates_menu_visible'=>$visible));
