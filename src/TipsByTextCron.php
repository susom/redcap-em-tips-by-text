<?php
/** @var \Stanford\TipsByText\TipsByText $module */

echo "------- Starting Tips By Text Cron $project_id-------";

$module->emLog("------- Starting Tips By Text Cron $project_id-------");
$module->sendDailyTip();