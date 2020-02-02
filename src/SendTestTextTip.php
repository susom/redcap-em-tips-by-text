<?php

namespace Stanford\TipsByText;
/** @var \Stanford\TipsByText\TipsByText $module */

//require_once __DIR__ . '/../vendor/autoload.php';

use Plugin;
use REDCap;
//use BenMorel\GsmCharsetConverter\Converter;



include_once "SmsMessages.php";

if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case "send":
            $phone = $_POST["phone"];
            $language = $_POST["language"];
            $encoding = $_POST["encoding"];
            $tip_number = $_POST["tip_number"];

            $module->emDebug("lang: ". $language);
            $module->emDebug("encoding: ". $encoding);
            $sm = new SmsMessages;

            $day_text = $sm->getSmsForDay($tip_number, $language);

            if ('esp'== $language) {
                //wiat for php 7.1
                //$converter = new Converter();
                if ('gsm' == $encoding) {
                    //$day_text = $module->convertToGSM(current($day_text));
                    //$day_text = $converter->convertUtf8ToGsm(current($day_text), false, '?'); // Hell?
                    $day_text = $module->convertUtf8ToGsm(current($day_text), false, '?'); // Hell?
                    $module->emDebug("CONVERTED TO GSM: ", $day_text);
                }
                if ('gsm0338' == $encoding) {


                    //$day_text = $converter->convertUtf8ToGsm(current($day_text), true, '?'); // Hello
                    $day_text = $module->convertUtf8ToGsm(current($day_text), true, null); // Hello
                    $module->emDebug($day_text);
                    exit;
                }
                if ('gsm_cleanup' == $encoding) {
                    //$day_text = $module->convertToGSM(current($day_text));
                    //$day_text = $converter->cleanUpUtf8String(current($day_text), false, '?'); // Hell?
                    $day_text = $module->cleanUpUtf8String(current($day_text), false, '?'); // Hell?
                    $module->emDebug("CONVERTED TO GSM: ", $day_text);
                }
                if ('gsm0338_cleanup' == $encoding) {


                    //$day_text = $converter->cleanUpUtf8String(current($day_text), true, '?'); // Hello
                    $day_text = $module->cleanUpUtf8String(current($day_text), true, '?'); // Hello
                    $module->emDebug($day_text);

                }
                if ('gsm0338_test' == $encoding) {

                    $day_text = $module->checkGSM(current($day_text));
                    $module->emDebug($day_text);

                }
                if ('gsm_tbt' == $encoding) {

                    //$day_text = $module->convertTBT(current($day_text));

                    $day_text = "Let's test \u00f6\u00e4\u00fc \u00e9\u00e0\u00e8 \u05d0\u05d9\u05df \u05ea\u05de\u05d9\u05db\u05d4 \u05d1\u05e2\u05d1\u05e8\u05d9\u05ea";
                    $module->emDebug("SENDING: ". $day_text);


                }
            }

            $status = $module->emText($phone, $day_text);

            if ($status === true) {
                $result = array(
                    'result' => 'success',
                    'tip'    => $day_text);
                $msg = "TESTING: Text sent to $phone: ".current($day_text);
                REDCap::logEvent("TipsByText Module", //action
                                 $msg);
            } else {
                $msg = "Error sending test text sent to $phone:".current($day_text) . " ERROR: $status ";
                REDCap::logEvent("TipsByText Module", $msg);
                $result = array(
                    'result' => 'fail',
                    'error' => $status);
            }

            header('Content-Type: application/json');
            print json_encode($result);
            exit();
            break;
        default:
            Plugin::log($_POST, "Unknown Action in Save");
            print "Unknown action";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>TipsByText Tester</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

    <!-- Favicon -->
    <link rel="icon" type="image/png"
          href="<?php print $module->getUrl("favicon/stanford_favicon.ico", false, true) ?>">
</head>
<body>

<div class="container">
    <div class="jumbotron">
        <h2>Send Tip Text</h2>
    </div>
      <div class="form-group">
          <label for="usr">Phone Number:</label>
          <input type="text" class="form-control" id="usr" name="phone_number">
      </div>
      <div class="form-group">
          <label for="usr">Tip Number:</label>
          <input type="text" class="form-control" id="usr" name="tip_number">
      </div>
    <div class="form-group">
        <label for="sel1">Select language:</label>
        <select class="form-control" id="lang">
            <option value="esp">Send text in Spanish</option>
            <option value="eng">Send text in English</option>
        </select>
    </div>
    <div class="form-group">
        <label for="sel1">Substitute special characters?:</label>
        <div class="jumbotron">
            <p>Nowadays, most online SMS gateways accept UTF-8 as input; however, some of them do not provide a way to force a message to be sent in the GSM charset.</p>

            <p>As a result, you may end up with extra charges caused by your SMS being sent in Unicode (UCS-2) format, causing the segmentation of messages in multiple parts, just because your SMS message contains an unforeseen accented character or emoji.</p>

            <p>The options with CLEANUP sends a UTF-8 string that contains only characters that can be safely converted to the GSM charset.</p>
        </div>
        <select class="form-control" id="encoding">
            <option value="none">No substitution</option>
            <option value="gsm_tbt">Convert to GSM TBT list</option>
            <option value="gsm">Convert to GSM encoding (without transliteration)</option>
            <option value="gsm0338">Convert to GSM 03.38 encoding (with transliteration)</option>
            <option value="gsm_cleanup">Convert to GSM encoding with CLEANUP (without transliteration)</option>
            <option value="gsm0338_cleanup">Convert to GSM 03.38 encoding with CLEANUP (with transliteration)</option>
            <option value="gsm0338_test">Check to see if all characters are GSM</option>
        </select>
    </div>


      <button class="btn btn-primary" name="submit" onclick="submit()">SEND TEXT</button>

</div>

</body>
</html>

<script type="text/javascript">

    function submit() {

        var saveBtn = $('button[name="submit"]');
        var phone = $('input[name="phone_number"]');
        //var language = $('input[name="language"]');
        var language = $('#lang');
        var encoding = $('#encoding');
        var tip_number = $('input[name="tip_number"]');

        var data = {
            "action"     : "send",
            "phone"      : phone.val(),
            "language"   : language.val(),
            "encoding"   : encoding.val(),
            "tip_number" : tip_number.val()
        };
        $.ajax({
            method: 'POST',
            data: data,
            dataType: "json"
        })
            .done(function (data) {
                if (data.result === 'success') {
                    alert("Text was successfully sent to "+phone.val() + " TIP: "+data.tip);
                    phone.val('');
                    language.val('');
                    tip_number.val('');
                } else {
                    // an error occurred
                    console.log(data);
                    alert("Error texting to " +phone.val()+" \n\nERROR: " + data.error);
                }

            })
            .fail(function (data) {
                console.log(data);
                alert(data.result + "Unable to send <br><br>" + data.error + data.message );
            })
            .always(function () {
                saveBtn.prop('disabled', false);
            });
    };


</script>
