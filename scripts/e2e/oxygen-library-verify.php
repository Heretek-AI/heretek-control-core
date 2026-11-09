<?php
/** Read-only native checks against library fixtures authored exclusively over MCP. */
if (PHP_SAPI !== 'cli') { exit; }
$_SERVER['HTTP_HOST']='elementor-mcp.test'; $_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/elementor-mcp/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID')); wp_set_current_user((int)$admins[0]);
$adapter=new EMCP_Tools_Oxygen_Integration(); $checks=array();
$schema=$adapter->execute('get-template-schema',array());
$checks['native_locations_loaded']=!empty($schema['locations']);
foreach (array(2015,2017,2019,2030) as $id) {
    $state=$adapter->execute('get-page',array('post_id'=>$id));
    $checks['disabled_'.$id]=$state['template_settings']['disabled'] === true;
    $checks['location_'.$id]=$state['template_settings']['type'] === 'everywhere';
}
$stale=$adapter->execute('update-template-settings',array('post_id'=>2015,'expected_hash'=>str_repeat('0',64),'settings'=>array('priority'=>11)));
$checks['stale_settings_denied']=is_wp_error($stale) && $stale->get_error_code()==='stale_page';
$invalid=$adapter->execute('update-template-settings',array('post_id'=>2015,'expected_hash'=>str_repeat('0',64),'settings'=>array('type'=>'not-a-location')));
$checks['unknown_location_denied']=is_wp_error($invalid) && $invalid->get_error_code()==='invalid_settings';
$checks['component_renders']=str_contains(\Breakdance\Render\render(2032),'Reusable Oxygen component');
$state=$adapter->execute('get-page',array('post_id'=>2021));
$invalid=$adapter->execute('insert-component',array('post_id'=>2021,'expected_hash'=>$state['content_hash'],'component_id'=>2021));
$checks['recursive_component_denied']=is_wp_error($invalid) && $invalid->get_error_code()==='invalid_component';
$state=$adapter->execute('get-page',array('post_id'=>2001));
$invalid=$adapter->execute('insert-component',array('post_id'=>2001,'expected_hash'=>$state['content_hash'],'component_id'=>2015));
$checks['header_as_component_denied']=is_wp_error($invalid) && $invalid->get_error_code()==='invalid_component';
echo wp_json_encode($checks,JSON_PRETTY_PRINT);
exit(in_array(false,$checks,true)?1:0);
