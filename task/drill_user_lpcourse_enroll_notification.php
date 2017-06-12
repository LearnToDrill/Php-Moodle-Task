<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');


class drill_user_lpcourse_enroll_notification extends \core\task\scheduled_task{
    
    /**
     
     * Get a descriptive name for this task (shown to admins).
     
     *
     
     * @return string
     
     */
    
    public function get_name() {
        
        return get_string('user_lpcourse_enroll_notification', 'mod_certificate');
        
    }
    
    /**
     
     * Run forum cron.
     
     */
    public function execute() {
        
         global $DB;
         
         
       $msg_payload = "";

         $subject = get_string('email_subject_course_expiry', 'mod_certificate','');
         $emailbody = get_string('email_body', 'mod_certificate','');
        // $msg_notify = get_string('course_expiry_notification', 'mod_certificate','');
         

         $records = $DB->get_recordset_sql ("SELECT coursename,deviceid,useremail,firstname,notifydate FROM vw_user_course_enrollment_notification where deviceid='edcd1a5de5676d56a804de745a14786fb425c8e76037e30b67bd5bbb59b94c50' ");
          
			 
			  if(is_null($records) || empty($records))
                 {
    	          	 echo "No data !";
    	          	  exit;
                 }
              else
                 {
			 
				foreach ($records as $id => $student) {
				    
				    $data = new \stdClass();      			   	       				 
                   
                    $data->coursename = $student->coursename;
                    $data->deviceid = $student->deviceid; 
                    $data->useremail= $student->useremail;
                    $data->firstname= $student->firstname;
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
                                            $replace[] = (string)($data->coursename);
                                            $find[] = '[Student First Name]';
                                            $replace[] = (string) ($data->firstname);
                                            $email_body = str_replace($find, $replace,  $emailbody);
                         
                              if (mail((string)($data->useremail), $subject, $email_body,  $header, '-f'."info@learntodrill.com"))
                                 {
                                     
                                            $find[] = '[Course Name]';
                                            $replace[] = (string)($data->coursename);
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