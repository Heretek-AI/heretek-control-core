<?php
// Temporary test-site write-tool enablement; restore the exact prior selection afterward.
if (home_url() !== 'https://elementor-mcp.test') { WP_CLI::error('Test site only.'); }
$backup='emcp_kirki_test_disabled_backup';
if (($args[0]??'')==='restore') {
    $saved=get_option($backup,null);
    if ($saved!==null) {
        $is_kirki=static fn($name)=>str_starts_with($name,'emcp-tools/kirki-');
        $current=get_option('emcp_tools_disabled_tools',array());
        update_option('emcp_tools_disabled_tools',array_values(array_unique(array_merge(array_filter($current,static fn($name)=>!$is_kirki($name)),array_filter($saved,$is_kirki)))));
        delete_option($backup);
    }
    if(!class_exists('EMCP_Tools_Admin')) { require_once EMCP_TOOLS_DIR.'includes/admin/class-admin.php'; }
    (new EMCP_Tools_Admin())->maybe_apply_default_disabled_tools();
    // Use only for a newly introduced adapter whose tools did not exist before this test.
    if(($args[1]??'')==='new-integration-defaults') {
        $disabled=get_option('emcp_tools_disabled_tools',array());
        foreach(EMCP_Tools_Kirki_Integration::definitions() as $slug=>$definition) { if($definition[3]) { $disabled[]='emcp-tools/kirki-'.$slug; } }
        update_option('emcp_tools_disabled_tools',array_values(array_unique($disabled)));
    }
    WP_CLI::success('Restored test tool choices.'); return;
}
if(get_option($backup,null)===null) { add_option($backup,get_option('emcp_tools_disabled_tools',array()),'',false); }
$disabled=get_option('emcp_tools_disabled_tools',array());
$names=(new EMCP_Tools_Kirki_Integration())->get_ability_names();
update_option('emcp_tools_disabled_tools',array_values(array_diff($disabled,$names)));
WP_CLI::success('Enabled Kirki tools for the local MCP test.');
