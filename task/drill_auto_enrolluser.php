<?php
namespace mod_certificate\task;
require_once('pushnotifications.php');


class drill_auto_enrolluser extends \core\task\scheduled_task
{
   
    /**
    
    * Get a descriptive name for this task (shown to admins).
    
    *
    
    * @return string
    
    */
    
    public function get_name()
    {
        
        return get_string('auto_enrollment_learning_plan', 'mod_certificate');
        
    }
    
    /**
    
    * Run forum cron.
    
    */
    public function execute()
    {
        
        global $DB;
        $error_file        = __FILE__;
        $error_functioname = __FUNCTION__;
        $name              = "";
        $description       = "";
        $descriptionformat = "";
        $userid            = "";
        $templateid        = "";
        $timecreated       = "";
        $timemodified      = 0;
        $origtemplateid    = NULL;
        $status            = 0;
        $duedate           = 0;
        $reviewerid        = NULL;
        
        $subject   = get_string('prepartion_subject', 'mod_certificate', '');
        $emailbody = get_string('prepartion_emailbody', 'mod_certificate', '');
        // Get New enrolled user in the Main course of a LP
        $data = $DB->get_recordset_sql("SELECT name,description,descriptionformat,userid,templateid,useremail FROM vw_new_enrolled_user_lp");
        
        print_r($data);
        
        if (is_null($data) || empty($data)) {
            exit;
        } else {
            foreach ($data as $id => $rec) {
                
                $record = new \stdClass();
                try {
                    $record->name              = $rec->name;
                    $record->description       = $rec->description;
                    $record->descriptionformat = $rec->descriptionformat;
                    $record->userid            = $rec->userid;
                    $record->origtemplateid    = $origtemplateid;
                    $record->templateid        = $rec->templateid;
                    $record->status            = 1;
                    $record->duedate           = $duedate;
                    $record->reviewerid        = $reviewerid;
                    $record->timecreated       = time();
                    $record->timemodified      = 0;
                    $record->usermodified      = $userid;
                    
                    $getinfo = $DB->record_exists_sql('SELECT name FROM mdl_competency_plan WHERE userid = ? AND name =? ', array(
                        $rec->userid,
                        $rec->name
                    ));
                    echo 'GETINFO';
                    print_r($getinfo);
                    
                    if ($getinfo == 0) {
                        
                        $DB->insert_record('competency_plan', $record);
                        // Get admin token
                        $admintoken = $DB->get_recordset_sql('SELECT token FROM mdl_external_tokens et INNER JOIN mdl_user usr ON usr.id=et.userid
                                                          WHERE et.privatetoken IS NULL AND usr.username= ?  ', array(
                            'admin'
                        ));
                        
                          print_r($admintoken);                                 
                        if (is_null($admintoken) || empty($admintoken)) {
                            exit;
                        } else {
                            foreach ($admintoken as $id => $token) {
                                
                                $usr_token = new \stdClass();
                                
                                $usr_token->token = $token->token;
                            }
                            
                        }
                        // This query will get the Preparation course linked with the Learning Plan
                        $enroldetails = $DB->get_recordset_sql('SELECT cp.id AS lpid,cp.userid AS userid,cp.templateid AS templateid,mct.competencyid AS competencyid,
                                                                     mc.idnumber AS idnumber,mcc.courseid AS courseid,me.id AS enrolid,usr.email AS email,c.fullname AS coursename,usr.firstname AS username
                                                                     FROM mdl_competency_plan cp
                                                                     INNER JOIN mdl_competency_templatecomp mct on cp.templateid = mct.templateid
                                                                     INNER JOIN mdl_competency mc on mc.id = mct.competencyid
                                                                     INNER JOIN mdl_competency_coursecomp mcc on mcc.competencyid = mct.competencyid
                                                                     INNER JOIN mdl_enrol me on me.courseid = mcc.courseid
                                                                     INNER JOIN mdl_user usr on cp.userid = usr.id
                                                                     INNER JOIN mdl_course c on mcc.courseid = c.id
                                                    WHERE me.status = ? AND cp.templateid = ? AND cp.userid = ? AND mc.idnumber like "P%" ', array(
                            0,
                            $rec->templateid,
                            $rec->userid
                        ));
                      
                        print_r($enroldetails);
                        
                        if (is_null($enroldetails) || empty($enroldetails)) {
                            exit;
                        } else {
                            foreach ($enroldetails as $id => $record) {
                                
                                $usr_rec = new \stdClass();
                
                                $usr_rec->courseid     = $record->courseid;
                                $usr_rec->enrolid      = $record->enrolid;
                                $usr_rec->userid       = $record->userid;
                                $usr_rec->email        = $record->email;
                                $usr_rec->username     = $record->username;
                                $usr_rec->coursename   = $record->coursename;
                                $usr_rec->timestart    = time();
                                
                                // For enrolling the user we are calling the web service which enrol the user in Preparation Course.
                                
                                $curl_post_data = "enrolments[0][roleid]=5&enrolments[0][userid]=$record->userid&enrolments[0][courseid]=$record->courseid&enrolments[0][timestart]=$usr_rec->timestart";
                                print_r($curl_post_data);
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_URL, "http://onlinedrillingcourse.com/webservice/rest/server.php?wstoken=" . $token->token . "&wsfunction=enrol_manual_enrol_users&moodlewsrestformat=json&");
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_POST, true);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
                                $curl_response = curl_exec($curl);
                                curl_close($curl);
                                
                                $eol    = PHP_EOL;
                                $header = "From:" . "baiju@emedsim.com" . $eol;
                                $header .= "MIME-Version: 1.0" . $eol;
                                $header .= "Return-Path:" . "baiju@emedsim.com" . $eol;
                                $header .= "Content-Type: text/html; charset=\"iso-8859-1\"" . $eol;
                                
                                $find[]     = '[Course Name]';
                                $replace[]  = (string) ($record->coursename);
                                $find[]     = '[Student First Name]';
                                $replace[]  = (string) ($record->username);
                                $email_body = str_replace($find, $replace, $emailbody);
                                // Email is sent using Php email function.
                                if (mail((string) ($record->email), $subject, $email_body, $header, '-f' . "baiju@emedsim.com")) {
                                    
                                    echo 'BBBBBBBBBBBBB';
                                    // $DB->insert_record('user_enrolments', $usr_rec);// Not required
                                    
                                } else {
                                    exit;
                                }
                                
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