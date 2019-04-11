<?php

namespace Stanford\TipsByText;

/** @var \Stanford\TipsByText\TipsByText $module */

$url = $module->getUrl('src/TipsByTextCron.php', true, true);
echo "<br><br>This is the Cron Link: <br>".$url;

