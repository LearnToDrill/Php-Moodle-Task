<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');


class drill_refresher_period_notification extends \core\task\scheduled_task{
    
    /**
     
     * Get a descriptive name for this task (shown to admins).
     
     *
     
     * @return string
     
     */
    
    public function get_name() {
        
        return get_string('course_refresher_notification', 'mod_certificate');
        
    }
    
    /**
     
     * Run forum cron.
     
     */
    public function execute() {
        
         global $DB;
         
         
          $msg_payload = "";

         $subject = get_string('email_subject_course_expiry', 'mod_certificate','');
         $emailbody = get_string('email_body', 'mod_certificate','');
         $msg_notify = get_string('user_course_refreshperiod', 'mod_certificate','');
         

         $records = $DB->get_recordset_sql ("SELECT DISTINCT fullname AS usercoursename,usr.firstname AS firstname,usr.email AS useremail,usr.deviceid AS deviceid,
                                                FLOOR((FLOOR(mcom.duedate-mcert.cert_timecreated) /learningplancount) +mcert.cert_timecreated) AS notifydate
                                                FROM vw_complan_comptemp_comp AS mcom, vw_user_course_certissue AS mcert,vw_user_notifications AS usrnot,
                                                vw_user_devicedetails  AS usr
                                                WHERE mcom.courseid = mcert.courseid AND mcom.userid = mcert.userid AND usrnot.userid = mcert.userid AND usr.userid=usrnot.userid
                                                ");
          
			 
			  if(is_null($records) || empty($records))
                 {
    	          	 echo "No data !";
    	          	  exit;
                 }
              else
                 {
			 
				foreach ($records as $id => $student) {
				    
				    $data = new \stdClass();      			   	       				 
                   
                    $data->usercoursename = $student->usercoursename;
                    $data->firstname = $student->firstname; 
                    $data->useremail= $student->useremail;
                    $data->deviceid= $student->deviceid;
                    $data->notifydate= $student->notifydate;
                    
                    
                    echo    $msg_payload;
                    print_r($records);
                    
                       if(time() == time())// (string)($data->notifydate)
                         {
                             $eol = PHP_EOL;
                             $header = "From:"."info@learntodrill.com". $eol;
                             $header .= "MIME-Version: 1.0". $eol;
                             $header .= "Return-Path:"."info@learntodrill.com". $eol;
                             $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                             
                                            $find[] = '[Course Name]';
                                            $replace[] = (string)($data->usercoursename);
                                            $find[] = '[Student First Name]';
                                            $replace[] = (string) ($data->firstname);
                                            $email_body = str_replace($find, $replace,  $emailbody);
                         
                              if (mail((string)($data->useremail), $subject, $email_body,  $header, '-f'."info@learntodrill.com"))
                                 {
                                     
                                            $find[] = '[Course Name]';
                                            $replace[] = (string)($data->usercoursename);
                                            $msg_payload = str_replace($find, $replace, $msg_notify);
                                            

                                       \pushnotifications::iOS($msg_payload, (string)($data->deviceid));
                                      
                                      echo "PQR pushnotifications Testing";
                                      
                                 }  
                                     else 
                                	 { 
                                       echo "";
                                	   exit; 
                                     }
                    		    }
        	   	}
            }
   
    }
}