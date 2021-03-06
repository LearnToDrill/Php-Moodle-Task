<?php

namespace mod_certificate\task;

class drill_set_moodle_theme_for_user extends \core\task\scheduled_task
{
     /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    
    public function get_name()
    {
        return get_string('set_moodle_theme_for_user', 'mod_certificate');
    }
    
    /**
     * Run forum cron.
     */
    public function execute()
    {
        global $DB;
        $error_file        = __FILE__;
        $error_functioname = __FUNCTION__;
        
        $records = $DB->get_recordset_sql("SELECT id,email,theme FROM mdl_user WHERE theme = ' ' AND timecreated >
            (SELECT max(usr.timecreated) FROM mdl_user as usr WHERE theme <> ' ' LIMIT 1 )
            ");
        
        if (is_null($records) || empty($records)) {
            exit;
        } else {
            foreach ($records as $id => $data) {
                
                $id                  = $data->id;
                $email               = $data->email;
                $get_email_extension = substr((string) $email, strpos($email, "@") + 1);
                $timemodified        = time();
                
                switch ($get_email_extension) {
                    
                    case "chevron.com":
                        $theme = 'aardvark';
                        break;
                    default:
                        $theme = 'magazine';
                }
                try {
                    $DB->execute("UPDATE {user} SET theme= '{$theme}' , timemodified='{$timemodified}' WHERE id = '{$id}' AND email= '{$email}'");
                }
                catch (\Exception $e) {
                    myErrorHandler($error_file, $error_functioname, $e->getMessage());
                }
            }
            $records->close();
        }
    }
}
