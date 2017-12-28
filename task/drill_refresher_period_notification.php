<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');


class drill_refresher_period_notification extends \core\task\scheduled_task
{
    /**
    
    * Get a descriptive name for this task (shown to admins).
    
    *
    
    * @return string
    
    */
    
    public function get_name()
    {
        return get_string('course_refresher_notification', 'mod_certificate');
    }
    
    /**     
     * Run forum cron.     
     */
    public function execute()
    {
        
        global $DB;
        $error_file        = __FILE__;
        $error_functioname = __FUNCTION__;
        $msg_payload       = "";
        
        $subject    = get_string('user_refresher_subject', 'mod_certificate', '');
        $emailbody  = get_string('email_body', 'mod_certificate', '');
        $msg_notify = get_string('user_course_refreshperiod', 'mod_certificate', '');
        
        $records = $DB->get_recordset_sql("SELECT shortname,timestart,firstname,userid,email FROM vw_refresher_course_notification ");
        
        //print_r($records);
        
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            foreach ($records as $id => $student) {
                $data = new \stdClass();
                try {
                    $data->shortname = $student->shortname;
                    $data->firstname = $student->firstname;
                    $data->timestart = $student->timestart;
                    $data->userid    = $student->userid;
                    $data->email     = $student->email;
                    
                    $eol    = PHP_EOL;
                    $header = "From:" . "baiju@emedsim.com" . $eol;
                    $header .= "MIME-Version: 1.0" . $eol;
                    $header .= "Return-Path:" . "baiju@emedsim.com" . $eol;
                    $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                    
                    $find[]     = '[Course Name]';
                    $replace[]  = (string) ($student->shortname);
                    $find[]     = '[Student First Name]';
                    $replace[]  = (string) ($student->firstname);
                    $email_body = str_replace($find, $replace, $emailbody);
                    
                    $find[]     = '[Course Name]';
                    $replace[]  = (string) ($student->shortname);
                    
                    $email_subject = str_replace($find, $replace, $subject);
                    
                    if (mail((string) ($student->email), $email_subject, $email_body, $header, '-f' . "baiju@emedsim.com")) {
                        
                        $pushid = $DB->get_recordset_sql('SELECT pushid FROM mdl_user_devices WHERE userid= ? ', array(
                            $student->userid
                        ));
                        if (is_null($pushid) || empty($pushid)) {
                            exit;
                        } else {
                            foreach ($pushid as $id => $device) {
                                $usr_dev         = new \stdClass();
                                $usr_dev->pushid = $device->pushid;
                                
                                $find[]      = '[Course Name]';
                                $replace[]   = (string) ($student->shortname);
                                $msg_payload = str_replace($find, $replace, $msg_notify);
                                
                                \pushnotifications::iOS($msg_payload, (string) ($device->pushid), (string) ($student->shortname));
                                
                            }
                            
                        }
                    } else {
                        exit;
                    }
                    
                }
                catch (\Exception $e) {
                    myErrorHandler($error_file, $error_functioname, $e->getMessage());
                }
            }
        }
    }
}

/*
VIEW NAME : vw_refresher_course_notification
select distinct `mc`.`shortname` AS `shortname`,`en`.`timestart` AS `timestart`,`usr`.`firstname` AS `firstname`,`usr`.`id` AS `userid`,`usr`.`email` AS `email` 
from ((((`mdl_competency` `mc` join `mdl_course` `c` on((`c`.`shortname` = `mc`.`shortname`)))
join `mdl_enrol` `e` on((`e`.`courseid` = `c`.`id`))) 
join `mdl_user_enrolments` `en` on((`en`.`enrolid` = `e`.`id`))) 
join `mdl_user` `usr` on((`usr`.`id` = `en`.`userid`)))
where ((`mc`.`idnumber` like 'R%') and (`usr`.`deleted` = 0) and (`en`.`timestart` between unix_timestamp((now() - interval 5 minute)) and unix_timestamp(now())))*/