
<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');

class drill_usercourse_expiration_notification extends \core\task\scheduled_task
{
    /**    
     * Get a descriptive name for this task (shown to admins).   
     *  
     * @return string    
     */
    
    public function get_name()
    {
        return get_string('user_course_expiration_notification', 'mod_certificate');
    }
    
    /**   
     * Run forum cron.   
     */
    public function execute()
    {
        global $DB;
        $msg_payload = "";
        
        $subject    = get_string('email_subject_course_expiry', 'mod_certificate', '');
        $emailbody  = get_string('course_expiry_emailbody', 'mod_certificate', '');
        $msg_notify = get_string('course_expiry_notification', 'mod_certificate', '');
        
        $records = $DB->get_recordset_sql("SELECT courseid,coursename,useremail,firstname,notifydate,userid FROM vw_user_course_expiration_notification ");
        print_r($records);
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            foreach ($records as $id => $student) {
                $data = new \stdClass();
                try {
                    
                    $data->courseid   = $student->courseid;
                    $data->coursename = $student->coursename;
                    $data->useremail  = $student->useremail;
                    $data->firstname  = $student->firstname;
                    $data->notifydate = $student->notifydate;
                    $data->userid     = $student->userid;
                    
                        $eol    = PHP_EOL;
                        $header = "From:" . "baiju@emedsim.com" . $eol;
                        $header .= "MIME-Version: 1.0" . $eol;
                        $header .= "Return-Path:" . "info@learntodrill.com" . $eol;
                        $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                        
                        $find[]     = '[Course Name]';
                        $replace[]  = (string) ($student->coursename);
                        $find[]     = '[Student First Name]';
                        $replace[]  = (string) ($student->firstname);
                        $email_body = str_replace($find, $replace, $emailbody);
                        
                        $find[]     = '[Course Name]';
                        $replace[]  = (string) ($student->coursename);
                        $email_subject = str_replace($find, $replace, $subject);
                         
                        if (mail((string) ($student->useremail), $email_subject, $email_body, $header, '-f' . "baiju@emedsim.com")) {
                            
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
                                $replace[]   = (string) ($student->firstname);
                                $msg_payload = str_replace($find, $replace, $msg_notify);
                                
                                echo $msg_payload;
                                \pushnotifications::iOS($msg_payload, (string) ($device->pushid), (string) ($student->coursename));
                                echo 'BBBBBBBBBB';
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

/*select `c`.`id` AS `courseid`,`c`.`fullname` AS `coursename`,`mu`.`email` AS `useremail`,`mu`.`firstname` AS `firstname`,
`e`.`id` AS `enrolid`,`mu`.`id` AS `userid`,(floor(`ue`.`timeend`) - (((3 * 24) * 60) * 60)) AS `Notifydate` 
from (((`mdl_user_enrolments` `ue`
join `mdl_enrol` `e` on((`ue`.`enrolid` = `e`.`id`))) 
join `mdl_course` `c` on((`c`.`id` = `e`.`courseid`))) 
join `mdl_user` `mu` on((`mu`.`id` = `ue`.`userid`))) 
where ((`mu`.`deleted` = 0) and ((floor(`ue`.`timeend`) - (3 * 60)) 
between unix_timestamp((now() - interval 5 minute)) and unix_timestamp(now())))*/
