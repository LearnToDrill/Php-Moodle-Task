<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');

class drill_re_certification_notification extends \core\task\scheduled_task
{
    /**
    
    * Get a descriptive name for this task (shown to admins).
    
    *
    
    * @return string
    
    */
    
    public function get_name()
    {
        return get_string('recertification_notification', 'mod_certificate');
    }
    
    /**
    
    * Run forum cron.
    
    */
    public function execute()
    {
        
        global $DB;
        $error_file        = __FILE__;
        $error_functioname = __FUNCTION__;
        
        $subject     = get_string('recertify', 'mod_certificate', '');
        $emailbody   = get_string('recertify_emailbody', 'mod_certificate', '');
        $msg_notify = get_string('recertify_appnotification', 'mod_certificate', '');
        
        $records = $DB->get_recordset_sql("SELECT course,coursename,email,firstname,notifydate,userid FROM vw_user_recertification_notify");
        
         print_r($records);
        
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            
            foreach ($records as $id => $student) {
                
                $data = new \stdClass();
                
                try {
                    $data->course     = $student->course;
                    $data->coursename = $student->coursename;
                    $data->firstname  = $student->firstname;
                    $data->email      = $student->email;
                    $data->notifydate = $student->notifydate;
                    $data->userid     = $student->userid;
                    
                    $eol    = PHP_EOL;
                    $header = "From:" . "baiju@emedsim.com" . $eol;
                    $header .= "MIME-Version: 1.0" . $eol;
                    $header .= "Return-Path:" . "baiju@emedsim.com" . $eol;
                    $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                    
                    $find[]     = '[Course Name]';
                    $replace[]  = (string) ($student->coursename);
                    $find[]     = '[Student First Name]';
                    $replace[]  = (string) ($student->firstname);
                    $email_body = str_replace($find, $replace, $emailbody);
                    
                    if (mail((string) ($student->email), $subject, $emailbody, $header, '-f' . "baiju@emedsim.com")) {
                        
                        $pushid = $DB->get_recordset_sql('SELECT pushid FROM mdl_user_devices WHERE userid= ? ', array(
                            $student->userid
                        ));
                        
                        print_r($pushid);
                        if (is_null($pushid) || empty($pushid)) {
                            exit;
                        } else {
                            foreach ($pushid as $id => $device) {
                                $usr_dev         = new \stdClass();
                                $usr_dev->pushid = $device->pushid;
                                
                                $find[]      = '[Course Name]';
                                $replace[]   = (string) ($student->coursename);
                                $msg_payload = str_replace($find, $replace, $msg_notify);
                                
                                echo $msg_payload;
                                \pushnotifications::iOS($msg_payload, (string) ($device->pushid), (string) ($student->course));
                                echo 'BBBBBBBBBB';
                            }
                        }
                    }
                    
                }
                catch (\Exception $e) {
                    myErrorHandler($error_file, $error_functioname, $e->getMessage());
                }
            }
        }
    }
}
