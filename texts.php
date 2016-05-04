<?php

define('stacksight_logs_title', 'Include Logs');
$logs_text = <<<HTML
    <div>Real time logging information of warnings and errors</div>
HTML;
define('stacksight_logs_text', $logs_text);


define('stacksight_health_title', 'Include Health');
$health_text = <<<HTML
    <div>Build a complete health profile</div>
HTML;
define('stacksight_health_text', $health_text);


define('stacksight_inventory_title', 'Include Inventory');
$inventory_text = <<<HTML
    <div>Collects the list of plugins, modules and packagesy</div>
HTML;
define('stacksight_inventory_text', $inventory_text);

define('stacksight_events_title', 'Include Events');
if((defined('STACKSIGHT_DEPENDENCY_AAL') && STACKSIGHT_DEPENDENCY_AAL === true) && (defined('STACKSIGHT_ACTIVE_AAL') && STACKSIGHT_ACTIVE_AAL === true)){
    $events_text =  <<<HTML
    <div>Watch users and application events at real time</div>
HTML;
} elseif(defined('STACKSIGHT_ACTIVE_AAL') && STACKSIGHT_ACTIVE_AAL === false){
    $events_text = <<<HTML
            <div class="code-red">If you want events enable, please activate <strong>Activity Log plugin</strong>.</div>
HTML;
} else{
    $events_text = <<<HTML
    <div class="code-red">If you want events enable, please install and activate <a href="https://wordpress.org/plugins/aryo-activity-log/" target="_blank">Activity Log plugin</a>.</div>
HTML;
}

define('stacksight_events_text', $events_text);

define('stacksight_updates_title', 'Include Updates');
$updates_text = <<<HTML
    <div>Show aviliabilty of new software updates</div>
HTML;
define('stacksight_updates_text', $updates_text);