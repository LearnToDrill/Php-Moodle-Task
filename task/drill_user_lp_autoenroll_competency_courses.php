<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');
require_once('errorlogging_function.php');



class drill_user_lp_autoenroll_competency_courses extends \core\task\scheduled_task
{
    
    /**
    
    * Get a descriptive name for this task (shown to admins).
    
    *
    
    * @return string
    
    */
    
    public function get_name()
    {
        
        return get_string('autoenroll_user_competency_courses', 'mod_certificate');
        
    }
    
    /**
    
    * Run forum cron.
    
    */
    public function execute()
    {
        
        global $DB, $data;
        
        $msg_payload = "";
        
        $subject    = get_string('email_subject_course_expiry', 'mod_certificate', '');
        $emailbody  = get_string('email_body', 'mod_certificate', '');
        $msg_notify = get_string('user_course_refreshperiod', 'mod_certificate', '');
        
        
        $records = $DB->get_recordset_sql("SELECT userid,firstname,useremail,courseid,usercoursename,enrolid,notifydate,deviceid  FROM vw_autoenroll_user_competency_courses");
        

        if (is_null($records) || empty($records)) {
           
            exit;
        } else {
            
            foreach ($records as $id => $student) {
                
                $data = new \stdClass();
                
                $data->usercoursename = $student->usercoursename;
                $data->firstname      = $student->firstname;
                $data->useremail      = $student->useremail;
                $data->deviceid       = $student->deviceid;
                $data->notifydate     = $student->notifydate;
                
                        $data->status       = 0;
                        $data->enrolid      = $student->enrolid;
                        $data->userid       = $student->userid;
                        $data->timestart    = 'djgh';
                        $data->timeend      = 0;
                        $data->modifierid   = 0;
                        $data->timecreated  = time();
                        $data->timemodified = time();
                        
                     try {
                      if (true == $DB->insert_record('user_enrolments', $data));
                         echo 'Caught :';
                    } catch (Exception $err) {
                        myErrorLog($err->getMessage());
                        echo 'Caught exception: ',  $err->getMessage(), "\n";
                    }
                     
                      
                
                
            /*    if (time() == (string) ($data->notifydate)) {
                    $eol    = PHP_EOL;
                    $header = "From:" . "info@learntodrill.com" . $eol;
                    $header .= "MIME-Version: 1.0" . $eol;
                    $header .= "Return-Path:" . "info@learntodrill.com" . $eol;
                    $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                    
                    $find[]     = '[Course Name]';
                    $replace[]  = (string) ($data->usercoursename);
                    $find[]     = '[Student First Name]';
                    $replace[]  = (string) ($data->firstname);
                    $email_body = str_replace($find, $replace, $emailbody);
                    
                    if (mail((string) ($data->useremail), $subject, $email_body, $header, '-f' . "info@learntodrill.com")) {
                        
                        $find[]      = '[Course Name]';
                        $replace[]   = (string) ($data->usercoursename);
                        $msg_payload = str_replace($find, $replace, $msg_notify);
                        
                        $data->status       = 0;
                        $data->enrolid      = $student->enrolid;
                        $data->userid       = $student->userid;
                        $data->timestart    = 0;
                        $data->timeend      = 0;
                        $data->modifierid   = 0;
                        $data->timecreated  = time();
                        $data->timemodified = time();
                        
                        
                        $DB->insert_record('user_enrolments', $data);
                        
                        \pushnotifications::iOS($msg_payload, (string) ($data->deviceid));
                        
                        
                    }
                    
                    else {
                        
                        exit;
                    }
                }*/
            }
           $records->close();
        }
    }
}
