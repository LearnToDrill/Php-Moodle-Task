<?php

namespace mod_certificate\task;

class drill_lp_duedate_autoset_user extends \core\task\scheduled_task
{
    /**
    
    * Get a descriptive name for this task (shown to admins).
    
    *
    
    * @return string
    
    */
    
    public function get_name()
    {
        
        return get_string('auto_duedate_set_for_user_lp', 'mod_certificate');
        
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
        
        $subject    = get_string('email_subject_course_expiry', 'mod_certificate', '');
        $emailbody  = get_string('email_body', 'mod_certificate', '');
        $msg_notify = get_string('user_course_refreshperiod', 'mod_certificate', '');
        
        $records = $DB->get_recordset_sql("SELECT userid,name,duedate,learningplanid,cerissuedate FROM vw_duedate_autosetuser");
        
         print_r($records);
         echo 'LEARNINGPLAN';
        
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            foreach ($records as $id => $data) {
                //OLD  $setduedate   = strtotime('duedate');
                $userid         = $data->userid;
                $name           = $data->name;
                $learningplanid = $data->learningplanid;
                //OLD  $getduedate   = $data->duedate;
                $timemodified   = time();
                $issuedate     = $data->cerissuedate; 
                $setduedate     = $data->duedate; //New
                //OLD $setduedate   = strtotime($getduedate);
                
                try {
                     $DB->execute("UPDATE {competency_plan} SET duedate= '{$setduedate}' , timemodified='{$timemodified}' WHERE userid = '{$userid}' AND name= '{$name}'");
                    //R-TYPE refresher course to be enrolled for a user
                    $enroldetail = $DB->get_recordset_sql('SELECT DISTINCT cp.id AS id,cp.userid AS userid,cp.templateid AS templateid,mct.competencyid AS competencyid,
                                                            mcc.courseid AS courseid,me.id AS enrolid,substr(mc.idnumber,2) AS idnumber,usr.email,c.shortname AS coursename
                                                             FROM mdl_competency_plan cp
                                                             INNER JOIN mdl_competency_templatecomp mct ON cp.templateid = mct.templateid
                                                             INNER JOIN mdl_competency mc ON mc.id = mct.competencyid 
                                                             INNER JOIN mdl_competency_coursecomp mcc ON mc.id = mcc.competencyid
                                                             INNER JOIN mdl_enrol me ON me.courseid = mcc.courseid
                                                             INNER JOIN mdl_user usr ON usr.id = cp.userid
                                                             INNER JOIN mdl_course c ON c.id = mcc.courseid
                                                             WHERE  usr.deleted = ? AND me.status = ? AND me.enrol = ? AND cp.userid = ? AND cp.templateid = ? AND  mc.idnumber like "R%" ', array(
                        0,
                        0,
                       'manual',
                        $data->userid,
                        $data->learningplanid
                    ));
                     // Get total refresher course COUNT linked with the Learning Plan 
                    print_r($enroldetail);
                    if (is_null($enroldetail) || empty($enroldetail)) {
                        exit;
                    } else {
                        $starttime = time();
                        $total_learningplan_duration = ($setduedate - $data->cerissuedate);
                        
                         $getRefresherCount = $DB->count_records_sql(' SELECT COUNT(cp.id)
                                                                     FROM mdl_competency cp
                                                                     INNER JOIN mdl_competency_templatecomp ct ON  cp.id = ct.competencyid
                                                                     WHERE ct.templateid = ?  AND cp.idnumber like  "R%" ', array(
                        $data->learningplanid
                    ));
                        print_r ($getRefresherCount);
                        echo 'getRefresherCount';
                        $refresherCoursePeriod = round($total_learningplan_duration/$getRefresherCount);
                        
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
                        // Enroll user into Refresher Courses
                        foreach ($enroldetail as $id => $record) {
                            
                            $usr_rec = new \stdClass();
                            
                            $usr_rec->enrolid    = $record->enrolid;
                            $usr_rec->userid     = $record->userid;
                            $usr_rec->email      = $record->email;
                            $usr_rec->courseid   = $record->courseid;
                            $usr_rec->coursename = $record->coursename;
                            $usr_rec->idnumber    = $record->idnumber;
                         
                            $usr_rec->timestart = $starttime + ($refresherCoursePeriod * ($usr_rec->idnumber - 1));
                            $usr_rec->timeend = $usr_rec->timestart + $refresherCoursePeriod;
                            
                            $getinfo = $DB->record_exists_sql('SELECT userid FROM mdl_user_enrolments WHERE userid = ? AND enrolid = ? ' , array ($record->userid,$record->enrolid)); 

                            if($getinfo == 0)
                            {
                                        
                                $curl_post_data = "enrolments[0][roleid]=5&enrolments[0][userid]=$record->userid&enrolments[0][courseid]=$record->courseid&enrolments[0][timestart]=$usr_rec->timestart&enrolments[0][timeend]=$usr_rec->timeend";
                               
                                //echo $curl_post_data;
                               
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_URL, "http://onlinedrillingcourse.com/webservice/rest/server.php?wstoken=" . $token->token . "&wsfunction=enrol_manual_enrol_users&moodlewsrestformat=json&");
                               
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_POST, true);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
                                $curl_response = curl_exec($curl);
                                curl_close($curl);

                              echo 'Time Start: ' . $usr_rec->timestart . "     Time End: " . $usr_rec->timeend . "<BR>";
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