<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-04-11
 * Time: 12:36
 */

namespace Stanford\TipsByText;

/** @var \Stanford\TipsByText\TipsByText $module */

include_once "src/SmsMessages.php";

$sm = new SmsMessages;
$sm->dumpCSV();