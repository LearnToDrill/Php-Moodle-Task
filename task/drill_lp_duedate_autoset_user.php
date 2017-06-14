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
        return get_string('autoduedatesetforuserlp', 'mod_certificate');
    }
    /**    
     * Run forum cron.    
     */
    public function execute()
    {
        global $DB;
        $records = $DB->get_recordset_sql("SELECT userid,name,duedate FROM vw_duedate_autosetuser");
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            foreach ($records as $id => $data) {
                $setduedate   = strtotime('duedate');
                $userid       = $data->userid;
                $name         = $data->name;
                $getduedate   = $data->duedate;
                $timemodified = time();
                $setduedate   = strtotime($getduedate);
                
                $DB->execute("UPDATE {competency_plan} SET duedate= '{$setduedate}' , timemodified='{$timemodified}' WHERE userid = '{$userid}' AND name= '{$name}'");
            }
        }
    }
}