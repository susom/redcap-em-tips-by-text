<?php

namespace Stanford\TipsByText;

/** @var \Stanford\TipsByText\TipsbyText $module */

use REDCap;
use DateTime;


class Participant {
    public $record_id;
    public $sms_number;
    public $sms_start_date;
    public $sms_lang;
    public $sms_last_day_sent;
    //public $sms_do_not_send;
    public $day_number;

    public function __construct($record) {
        global $module;

        $this->record_id = $record[REDCap::getRecordIdField()];
        $this->sms_number = $record[$module->getProjectSetting('sms-phone')];
        $this->sms_start_date = $record[$module->getProjectSetting('sms-start-date')];

        $this->sms_lang = $record[$module->getProjectSetting('sms-lang-field')] == '2' ? 'esp' : 'eng';

        $this->sms_last_day_sent = $record[$module->getProjectSetting('sms-last-day-sent-field')];
        //$this->sms_do_not_send = $record[$module->getProjectSetting('sms-do-not-send-field')];

        $this->day_number = self::calculateDayNumber($this->sms_start_date);
        //$module->emDebug($this);

    }

    public function sendTipForToday($tips) {
        global $module;

        $error = array();

        //check if blank start date
        if (empty($this->sms_start_date)) {
            $error[] = "Missing start date for record id ".$this->record_id;
        }

        //check if missing phone number
        if (empty($this->sms_number)) {
            $error[] = "Missing phone for record id ".$this->record_id;
        }

        $module->emDebug($this->sms_last_day_sent . " vs ".$this->day_number, "NOT SENDING IF TRUE:".($this->sms_last_day_sent < $this->day_number));

        //check that tip today is more recent than one already sent
        if ($this->sms_last_day_sent > $this->day_number) {
            $error[] = "Last received text for Day # {$this->sms_last_day_sent}. So did not send today (Day # {$this->day_number})";
        }

        //if there are no errors so far, go ahead and send the text.

        if (empty($error)) {
            //there can be multiple tips per day (it's an array)
            foreach ($tips as $today_text) {
//            if ('esp'==$sr->language) {
//                $today_text = convertToGSM($today_text);
//            }

                $result = $module->emText($this->sms_number, $today_text);

                if ($result === true) {

                    //update record's last day sent field
                    $this->logLastTextSent();

                    //update the logging tab
                    REDCap::logEvent(
                        "Successfully sent tip by Tips by Text EM",  //action
                        "Sent to " . $this->sms_number . " : " .$today_text,  //changes
                                                NULL, //sql optional
                        $this->record_id //record optional
                    );
                }


            }
        } else {
            //log errors to logEvent
            REDCap::logEvent(
                "Error sending tip from Tips by Text EM",  //action
                implode (",", $error),  //changes
                NULL, //sql optional
                $this->record_id //record optional
            );
        }

    }

    private function logLastTextSent() {
        global $module;

        $event_id = $module->getProjectSetting('sms-field-event');
        $last_sent_field = $module->getProjectSetting('sms-last-day-sent-field');
        $data = array(
            REDCap::getRecordIdField() => $this->record_id,
            'redcap_event_name' => REDCap::getEventNames(true, false,$event_id),
            $last_sent_field => $this->day_number
        );

        REDCap::saveData($data);
        $response = REDCap::saveData('json', json_encode(array($data)));

        if (!empty($response['errors'])) {
            $msg = "Error creating record - ask administrator to review logs: " . json_encode($response);
            REDCap::logEvent(
                "Error saving last record sent by Tips by Text EM",  //action
                $msg,  //changes
                NULL, //sql optional
                $this->record_id //record optional
            );
            $module->emDebug($msg);
            return ($response);
        }

    }

    public static function calculateDayNumber($start_date_str) {
        $date = new DateTime();
        $start_date = new DateTime($start_date_str);
        $interval = $start_date->diff($date)->format('%r%a');
        //if ($interval > -1) $interval++;// Make day 0 into day 1, etc...
	    //13Nov2017: issue where -14 hours was being rounded as -0 days, then incremented. so check for non-neg hours
        $interval_hours = $start_date->diff($date)->format('%r%h');
        if (($interval >=0  ) && ($interval_hours > 0)) {
            $interval++;// Make day 0 into day 1, etc...
        }

        return $interval;

    }


}