<?php

namespace mod_certificate\task;
require_once('pushnotifications.php');

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
        /*global $DB, $data;
        $msg_payload       = "";
        $error_file        = __FILE__;
        $error_functioname = __FUNCTION__;
        
        $records = $DB->get_recordset_sql("SELECT userid,firstname,useremail,courseid,usercoursename,enrolid,refresherStartDate,refresherCourseEndDate FROM vw_autoenroll_user_competency_courses");
        echo 'AAAAA';
        print_r($records);
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            foreach ($records as $id => $student) {
                $data = new \stdClass();
                
                $data->userid                 = $student->userid;
                $data->usercoursename         = $student->usercoursename;
                $data->firstname              = $student->firstname;
                $data->useremail              = $student->useremail;
                $data->enrolid                = $student->enrolid;
                $data->courseid               = $student->courseid;
                $data->refresherstartdate     = $student->refresherstartdate;
                $data->refreshercourseenddate = $student->refreshercourseenddate;
                
                $getinfo = $DB->record_exists_sql('SELECT enrolid FROM mdl_user_enrolments WHERE userid = ? AND enrolid =? ', array(
                    $student->userid,
                    $student->enrolid
                ));
                
                if ($getinfo == 0) {
                    
                    try {
                        $data->status       = 0;
                        $data->enrolid      = $student->enrolid;
                        $data->userid       = $student->userid;
                        $data->timestart    = $student->refresherstartdate;
                        $data->timeend      = $student->refreshercourseenddate;
                        $data->modifierid   = 2;
                        $data->timecreated  = time();
                        $data->timemodified = time();
                        
                        $DB->insert_record('user_enrolments', $data);
                    }
                    catch (\Exception $e) {
                        myErrorHandler($error_file, $error_functioname, $e->getMessage());
                    }
                    
                }
            }
            $records->close();
        }*/
    }
}