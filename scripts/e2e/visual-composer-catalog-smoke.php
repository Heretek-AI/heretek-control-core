<?php
if(!defined('WP_CLI')) { exit; }
if(!class_exists('EMCP_Tools_Admin')) { require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php'; }
$all=(new EMCP_Tools_Admin())->get_all_tools(); $found=array();
foreach($all as $category) { foreach($category['tools'] as $slug=>$tool) {
    if(!str_starts_with($slug,'emcp-tools/visual-composer-')) { continue; }
    if($category['platform']!=='visual-composer' || isset($found[$slug]) || !$tool['available']) { WP_CLI::error('Incorrect tool placement or native availability.'); }
    $found[$slug]=true;
} }
if(count($found)!==16 || !EMCP_Tools_Page_Builders::available('visual-composer')) { WP_CLI::error('Visual Composer catalog incomplete.'); }
if(!EMCP_Tools_Skill_Catalog::get('emcp-visual-composer')) { WP_CLI::error('Bundled runtime skill missing.'); }
WP_CLI::success('Sixteen Visual Composer tools, standalone selection and runtime skill available.');
