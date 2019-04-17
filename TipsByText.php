<?php
namespace Stanford\TipsByText;

use REDCap;
use Message;
use ExternalModules\ExternalModules;


include "emLoggerTrait.php";
include_once "src/SmsMessages.php";
include_once "src/Participant.php";

class TipsByText extends \ExternalModules\AbstractExternalModule {
    use emLoggerTrait;

    function sendDailyTip() {
        // Load all of the tips by text messaging configuration (this is done once
        // per execution and saved for iterating across each record)
        $sm = new SmsMessages;
        $sm->dumpCSV();

        $sms_event_id = $this->getProjectSetting('sms-field-event');

        //get records and iterate over to see what needs to be sent
        $get_fields = array(
            REDCap::getRecordIdField(),
            $this->getProjectSetting('sms-phone'),
            $this->getProjectSetting('sms-start-date'),
            $this->getProjectSetting('sms-lang-field'),
            $this->getProjectSetting('sms-last-day-sent-field'),
            $this->getProjectSetting('sms-do-not-send-field')
        );
        $records = REDCap::getData('array', NULL, $get_fields, $sms_event_id);

        foreach ($records as $record_id => $record) {
            $candidate = $record[$sms_event_id];

            //skip if inactive
            if ($candidate[$this->getProjectSetting('sms-do-not-send-field')] > 0) {
                continue;
            }

            $participant = new Participant($candidate);
            $this->emDebug("=====================", $participant);


            //if there is a text for today, send it
            if ($day_text = $sm->getSmsForDay($participant->day_number, $participant->sms_lang)) {
                $this->emDebug("Sending ". $participant->day_number . " in ". $participant->sms_lang);
                $participant->sendTipForToday($day_text);
            }
        }
    }



    /**
     * Cron method for initiating sending of tips
     */
    function sendCronTips() {

        $this->emDebug("STARTING TIPS BY TEXT CRON");
        //* 1) Determine projects that are using this EM
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        //get the noAuth api endpoint for Cron job.
        $url = $this->getUrl('src/TipsByTextCron.php', true, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];

            //check scheduled hour of send
            $scheduled_hour = $this->getProjectSetting('send-time', $pid);
            $current_hour = date('H');
            $this->emDebug("project $pid scheduled at this hour $scheduled_hour vs current hour: $current_hour");

            //if not hour, continue
            if ($scheduled_hour != $current_hour) continue;

            $this_url = $url . '&pid=' . $pid;
            $this->emDebug("CRON URL IS " . $this_url);

            $resp = http_get($this_url);
            //$this->cronAttendanceReport($pid);
            //$this->emDebug($resp, "DEBUG", "cron for tips by text");

        }
    }

    /**
     * USED FOR TESTING SPANISH TEXT / CAN DELETE
     *
     * @param $phone
     * @param $start
     * @param $end
     */
    function sendTips($phone, $start, $end) {
        $this->emDebug("sending tips", $phone, $start, $end);

        $sm = new SmsMessages;
        $foo = "EL DR. DICE: Después de la cena, pida a su hijo/a que haga una pose chistosa. Diga: Había un niño/a que (describa la pose). Añadan partes de la historia juntos!";


        for ($i=$start; $i<=$end; $i++) {

            $day_text = $sm->getSmsForDay($i, 'esp');
            //$day_text = $foo;
            $day_text = trim(replaceNBSP(strip_tags(str_replace(array("\r\n", "\n", "\t"), array(" ", " ", " "), label_decode($day_text)))));
            $this->emDebug($day_text);
            if ($day_text) {

                foreach ($day_text as $today_text) {

                    $a = mb_detect_encoding($today_text, 'UTF-8');
                    $b = mb_detect_encoding($today_text);
                    $c = self::utf8_to_gsm0338($today_text);
                    $d = self::countGsm0338Length($today_text);


                    $converted = $this->convertToGSM($today_text);

                    $this->emDebug("text for $i: ", $today_text, $converted, $a, $b,  $c, $d);
                    //$this->emText($phone, $d );
                    //$this->emText($phone, $converted );

                }
            }
        }

    }


	/**
     * USED FOR TESTING SPANISH TEXT / CAN DELETE*
     *
	 * Encode an UTF-8 string into GSM 03.38
	 * Since UTF-8 is largely ASCII compatible, and GSM 03.38 is somewhat compatible, unnecessary conversions are removed.
	 * Specials chars such as € can be encoded by using an escape char \x1B in front of a backwards compatible (similar) char.
	 * UTF-8 chars which doesn't have a GSM 03.38 equivalent is replaced with a question mark.
	 * UTF-8 continuation bytes (\x08-\xBF) are replaced when encountered in their valid places, but
	 * any continuation bytes outside of a valid UTF-8 sequence is not processed.
	 *
	 * @param string $string
	 * @return string
	 */
	public function utf8_to_gsm0338($string)
	{

        $dict = array(
			'@' => "\x00", '£' => "\x01", '$' => "\x02", '¥' => "\x03", 'è' => "\x04", 'é' => "\x05", 'ù' => "\x06", 'ì' => "\x07", 'ò' => "\x08", 'Ç' => "\x09", 'Ø' => "\x0B", 'ø' => "\x0C", 'Å' => "\x0E", 'å' => "\x0F",
			'Δ' => "\x10", '_' => "\x11", 'Φ' => "\x12", 'Γ' => "\x13", 'Λ' => "\x14", 'Ω' => "\x15", 'Π' => "\x16", 'Ψ' => "\x17", 'Σ' => "\x18", 'Θ' => "\x19", 'Ξ' => "\x1A", 'Æ' => "\x1C", 'æ' => "\x1D", 'ß' => "\x1E", 'É' => "\x1F",
			// all \x2? removed
			// all \x3? removed
			// all \x4? removed
			'Ä' => "\x5B", 'Ö' => "\x5C", 'Ñ' => "\x5D", 'Ü' => "\x5E", '§' => "\x5F",
			'¿' => "\x60",
			'ä' => "\x7B", 'ö' => "\x7C", 'ñ' => "\x7D", 'ü' => "\x7E", 'à' => "\x7F",
			'^' => "\x1B\x14", '{' => "\x1B\x28", '}' => "\x1B\x29", '\\' => "\x1B\x2F", '[' => "\x1B\x3C", '~' => "\x1B\x3D", ']' => "\x1B\x3E", '|' => "\x1B\x40", '€' => "\x1B\x65"
		);
		$converted = strtr($string, $dict);


		// Replace unconverted UTF-8 chars from codepages U+0080-U+07FF, U+0080-U+FFFF and U+010000-U+10FFFF with a single ?
		$replaced =  preg_replace('/([\\xC0-\\xDF].)|([\\xE0-\\xEF]..)|([\\xF0-\\xFF]...)/m','?',$converted);
        $this->emDebug($string, $converted, $replaced);  exit;

        return $replaced;

	}

	/**
     * USED FOR TESTING SPANISH TEXT / CAN DELETE
     *
	 * Count the number of GSM 03.38 chars a conversion would contain.
	 * It's about 3 times faster to count than convert and do strlen() if conversion is not required.
	 *
	 * @param string $utf8String
	 * @return integer
	 */
	public static function countGsm0338Length($utf8String)
	{
		$len = mb_strlen($utf8String,'utf-8');
		$len += preg_match_all('/[\\^{}\\\~€|\\[\\]]/mu',$utf8String,$m);
		return $len;
	}


    function convertToGSM( $utf8_string ) {

    //global $characterMap;
    $characterMap = array(
        'Á' => '&Aacute;',   //	&#193;	Capital A-acute
        'á' => '&aacute;',    //	&#225;	Lowercase a-acute
        'É' => '&Eacute;',    //	&#201;	Capital E-acute
        'é' => '&eacute;',    //	&#233;	Lowercase e-acute
        'Í' => '&Iacute;',    //	&#205;	Capital I-acute
        'í' => '&Iacute;',    //	&#237;	Lowercase i-acute
        'Ñ' => '&Ntilde;',    //	&#209;	Capital N-tilde
        'ñ' => '&ntilde;',    //	&#241;	Lowercase n-tilde
        'Ó' => '&Oacute;',    //	&#211;	Capital O-acute
        'ó' => '&oacute;',    //	&#243;	Lowercase o-acute
        'Ú' => '&Uacute;',    //	&#218;	Capital U-acute
        'ú' => '&uacute;',    //	&#250;	Lowercase u-acute
        'Ü' => '&Uuml;',      //	&#220;	Capital U-umlaut
        'ü' => '&uuml;',      //	&#252;	Lowercase u-umlaut
        '«' => '&laquo;',     //	&#171;	Left angle quotes
        '»' => '&raquo;',     //	&#187;	Right angle quotes
        '¿' => '&iquest;',    //	&#191;	Inverted question mark
        '¡' => '&iexcl;',     //	&#161;	Inverted exclamation point
        '€' => '&#128;'       //	        Euro
    );


        //these characters in Monica's file are different then the previous set
        $characterMap2 = array(
            '/Á/' => '&Aacute;',   //	&#193;	Capital A-acute
            '/á/' => '&aacute;',    //	&#225;	Lowercase a-acute
            '/É/' => '&Eacute;',    //	&#201;	Capital E-acute
            '/é/' => '&eacute;',    //	&#233;	Lowercase e-acute
            '/Í/' => '&Iacute;',    //	&#205;	Capital I-acute
            '/í/' => '&Iacute;',    //	&#237;	Lowercase i-acute
            '/Ñ/' => '&Ntilde;',    //	&#209;	Capital N-tilde
            '/ñ/' => '&ntilde;',    //	&#241;	Lowercase n-tilde
            '/Ó/' => '&Oacute;',    //	&#211;	Capital O-acute
            '/ó/' => '&oacute;',    //	&#243;	Lowercase o-acute
            '/Ú/' => '&Uacute;',    //	&#218;	Capital U-acute
            '/ú/' => '&uacute;',    //	&#250;	Lowercase u-acute
            '/¡/' => '&iexcl;',     //	&#161;	Inverted exclamation point
            '/¿/' => '&iquest;'     //	&#191;	Inverted question mark
        );


        //these characters in Monica's file are different then the previous set
        $characterMap3 = array(
            "/Á/" => "&Aacute;",   //	&#193;	Capital A-acute
            "/á/" => "&aacute;",    //	&#225;	Lowercase a-acute
            "/É/" => "&Eacute;",    //	&#201;	Capital E-acute
            "/é/" => "&eacute;",    //	&#233;	Lowercase e-acute
            "/Í/" => "&Iacute;",    //	&#205;	Capital I-acute
            "/í/" => "&Iacute;",    //	&#237;	Lowercase i-acute
            "/Ñ/" => "&Ntilde;",    //	&#209;	Capital N-tilde
            "/ñ/" => "&ntilde;",    //	&#241;	Lowercase n-tilde
            "/Ó/" => "&Oacute;",    //	&#211;	Capital O-acute
            "/ó/" => "&oacute;",    //	&#243;	Lowercase o-acute
            "/Ú/" => "&Uacute;",    //	&#218;	Capital U-acute
            "/ú/" => "&uacute;",    //	&#250;	Lowercase u-acute
            "/¡/" => "&iexcl;",     //	&#161;	Inverted exclamation point
            "/¿/" => "&iquest;"     //	&#191;	Inverted question mark
        );

        $patterns        = array_keys($characterMap2);
        $replacements    = array_values($characterMap2);
        $convertedString = preg_replace($patterns, $replacements, $utf8_string);
    //$this->emDebug($patterns, "patterns");
    //$this->emDebug($replacements, "replacements");
    $this->emDebug($convertedString, "converted string");

        return $convertedString;
    }


    /**
     * Delete the Twilio back-end and front-end log of a given SMS (will try every second for up to 30 seconds)
     * @param $sid
     * @return bool
     */
    public function deleteLogForSMS($sid)
    {
        // Delete the log of this SMS (try every second for up to 30 seconds)
        for ($i = 0; $i < 30; $i++) {
            // Pause for 1 second to allow SMS to get delivered to carrier
            if ($i > 0) sleep(1);
            // Has it been delivered yet? If not, wait another second.
            $log = $this->client->account->sms_messages->get($sid);

            //print "<pre>Log $i: " . print_r($log, true) . "</pre>";
            if ($log->status != 'delivered') continue;
            // Yes, it was delivered, so delete the log of it being sent.
            $this->client->account->messages->delete($sid);
            return true;
        }
        // Failed
        return false;
    }


    /**
     * Convert phone nubmer to E.164 format before handing off to Twilio
     * @param $phoneNumber
     * @return mixed|string
     */
    public static function formatNumber($phoneNumber)
    {
        // If number contains an extension (denoted by a comma between the number and extension), then separate here and add later
        $phoneExtension = "";
        if (strpos($phoneNumber, ",") !== false) {
            list ($phoneNumber, $phoneExtension) = explode(",", $phoneNumber, 2);
        }
        // Remove all non-numerals
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);
        // Prepend number with + for international use cases
        $phoneNumber = (isPhoneUS($phoneNumber) ? "+1" : "+") . $phoneNumber;
        // If has an extension, re-add it
        if ($phoneExtension != "") $phoneNumber .= ",$phoneExtension";
        // Return formatted number
        return $phoneNumber;
    }

    /**
     * The filter in the REDCap::getData expects the phone number to be in
     * this format (###) ###-####
     *
     * @param $number
     * @return
     */
    public static function formatToREDCapNumber($number)
    {
        $formatted = preg_replace('~.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*~', '($1) $2-$3', $number);
        return trim($formatted);

    }

    public function findRecordByPhone($phone, $phone_field, $phone_field_event) {

        $this->emDebug("Locate record for this phone: ".$phone);
        $get_fields = array(
            REDCap::getRecordIdField(),
            $phone_field
        );
        $event_name = REDCap::getEventNames(true, false, $phone_field_event);
        $filter = "[" . $event_name . "][" .$phone_field . "] = '$phone'";


        $records = REDCap::getData('array', null, $get_fields, null, null, false, false, false, $filter);
        //$this->emDebug($filter, $records, $project_id, $pid, $filter, $event_name);

        // return record_id or false
        reset($records);
        $first_key = key($records);
        return ($first_key);
    }


    function checkNonGSM($str) {
        $this->emDebug("CHECKING ". $str);


        $re = '/[^A-Za-z0-9 \\\\r\\\\n@£$¥èéùìòÇØøÅå\\\\u0394_\\\\u03A6\\\\u0393\\\\u039B\\\\u03A9\\\\u03A0\\\\u03A8\\\\u03A3\\\\u0398\\\\u039EÆæßÉ!\"#$%&amp;\'()*+,.\/:;&lt;=&gt;?¡ÄÖÑÜ§¿äöñüà^{}\\\\\\\\\\\\[~\\\\]|\\\\u20AC]*/m';
        $re = '/[^A-Za-z0-9 \\\\r\\\\n@£$¥èéùìòÇØøÅåEÆæßÉ!\"#$%&amp;\'()*+,.\/:;&lt;=&gt;?¡ÄÖÑÜ§¿äöñüà^{}\\\\[~\\\\]]*/m';

$str = 'EL DR. DICE: Mientras baña a su hijo, hágale preguntas sobre las partes de su cuerpo. ¿Dónde están los codos? ¿Qué hacen?';

$re = '/[^A-Za-z0-9 \\\\r\\\\n@£$¥èéùìòÇØøÅåEÆæßÉ!\"#$%&amp;\'()*+,.\/:;&lt;=&gt;?¡ÄÖÑÜ§¿äöñüà^{}\\\\[~\\\\]]*/';
$str = 'EL DR. DICE: Mientras baña a su hijo, hágale preguntas sobre las partes de su cuerpo. ¿Dónde están los codos? ¿Qué hacen?';
        $matches = array();
        preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
        $this->emDebug("MATCHES!! ", $matches);
// Print the entire match result
//var_dump($matches);
return $matches;
    }





  /*******************************************************************************************************************/
    /* EXTERNAL MODULES METHODS                                                                                                    */
    /***************************************************************************************************************** */



    function emText($number, $text) {
        global $module;

        $emTexter = ExternalModules::getModuleInstance('twilio_utility');
        //$this->emDebug($emTexter);
        $text_status = $emTexter->emSendSms($number, $text);
        return $text_status;
    }

}