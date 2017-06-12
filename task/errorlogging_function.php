<?php

namespace mod_certificate\task;


    function myErrorLog($message = 'no message') {
        $date = date('Y-m-d H:i:s');
        $fp   = fopen('\moodle_errors.log', 'a');
        if (!$fp) {
             throw new \Exception("Could not open log file! Permission error?");
        }
        fwrite($fp, $date . ' ' . $message . "\n");
        fclose($fp);
    }


?>  