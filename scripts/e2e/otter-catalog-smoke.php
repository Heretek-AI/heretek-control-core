<?php
if(!defined('WP_CLI')) { exit; }
if(!class_exists('EMCP_Tools_Admin')) { require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php'; }
$found=array();
foreach((new EMCP_Tools_Admin())->get_all_tools() as $group) {
    foreach($group['tools'] as $slug=>$tool) {
        if(!str_starts_with($slug,'emcp-tools/otter-')) { continue; }
        if($group['platform']!=='otter' || isset($found[$slug]) || !$tool['available']) { WP_CLI::error('Otter catalog drift.'); }
        $found[$slug]=true;
    }
}
if(count($found)!==19 || !EMCP_Tools_Page_Builders::available('otter')) { WP_CLI::error('Missing Otter tools.'); }
if(!EMCP_Tools_Skill_Catalog::get('emcp-otter')) { WP_CLI::error('Missing injected skill.'); }
WP_CLI::success('19 Otter tools, independent pack and injected skill available.');
