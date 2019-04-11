<?php

namespace Stanford\TipsByText;
/** @var \Stanford\TipsByText\TipsByText $module */

use Plugin;
use REDCap;

include_once "SmsMessages.php";

if (!empty($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case "send":
            $phone = $_POST["phone"];
            $language = $_POST["language"];
            $tip_number = $_POST["tip_number"];

            $sm = new SmsMessages;

            $day_text = $sm->getSmsForDay($tip_number, $language);
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
          <label for="usr">Language (esp / eng):</label>
          <input type="text" class="form-control" id="usr" name="language">
      </div>


      <button class="btn btn-primary" name="submit" onclick="submit()">SEND TEXT</button>

</div>

</body>
</html>

<script type="text/javascript">

    function submit() {

        var saveBtn = $('button[name="submit"]');
        var phone = $('input[name="phone_number"]');
        var language = $('input[name="language"]');
        var tip_number = $('input[name="tip_number"]');

        var data = {
            "action"     : "send",
            "phone"      : phone.val(),
            "language"   : language.val(),
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
