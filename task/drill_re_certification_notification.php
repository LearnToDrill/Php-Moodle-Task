<?php

namespace mod_certificate\task;

require_once('pushnotifications.php');
//require('\home\onlin200\public_html\mod\certificate\lang\en\certificate.php');

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
        
        
        $subject     = get_string('recertify', 'mod_certificate', '');
        $emailbody   = get_string('recertify_emailbody', 'mod_certificate', '');
        $msg_payload = get_string('recertify_appnotification', 'mod_certificate', '');
        
        $records = $DB->get_recordset_sql("Select email,notifydate,devicetoken from vw_user_recertification_notify");
        
        
        if (is_null($records) || empty($records)) {
            
            exit;
        } else {
            
            foreach ($records as $id => $student) {
                
                $data = new \stdClass();
                
                $data->email       = $student->email;
                $data->notifydate  = $student->notifydate;
                $data->devicetoken = $student->devicetoken;
                
                //(string)($data->notifydate)
                
                if (time() == time()) {
                    $eol    = PHP_EOL;
                    $header = "From:" . "info@learntodrill.com" . $eol;
                    $header .= "MIME-Version: 1.0" . $eol;
                    $header .= "Return-Path:" . "info@learntodrill.com" . $eol;
                    $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                    
                    if (mail((string) ($data->email), $subject, $emailbody, $header, '-f' . "info@learntodrill.com")) {
                        \pushnotifications::iOS($msg_payload, (string) ($data->devicetoken));

                    } else {
                        
                        exit;
                    }
                }
            }
        }
    }
}