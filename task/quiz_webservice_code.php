<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This web services uses quizid as a required parameter.

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . "/lib/filebrowser/file_browser.php");

class local_quiz_external extends external_api {
    
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_quiz_data_parameters() {
        return new external_function_parameters(array(
            'quizid' => new external_value(PARAM_TEXT, 'This will check quiz id')
        ));
    }
    
    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function get_quiz_data($quizid) {
        global $USER;
        global $DB;
        global $CFG;
        $response["question"] = array();
        $result               = array();
        $quest[]              = array();
        $browser              = get_file_browser();
        $context              = context_user::instance($USER->id, IGNORE_MISSING);
        
        
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_quiz_data_parameters(), array(
            'quizid' => $quizid
        ));
        
        $data        = array();
        //Get all question based on quiz ID from tables.
        $quizdetails = $DB->get_recordset_sql('SELECT que.id,que.name,que.questiontext,que.qtype,que.generalfeedback
                                                    FROM mdl_quiz AS qz
                                                    INNER JOIN mdl_quiz_slots as qzs ON qz.id=qzs.quizid
                                                    INNER JOIN mdl_question as que ON que.id=qzs.questionid
                                                    WHERE qz.id = ?', array(
            $quizid
        ));
        
        foreach ($quizdetails as $id => $rec) {
            $quest_id         = $rec->id;
            $name             = $rec->name;
            $q_text           = $rec->questiontext;
            $rem_txtbefore    = substr($q_text, strpos($q_text, "@@/") + 3);
            $get_image_name   = substr($rem_txtbefore, 0, strpos($rem_txtbefore, '"')); // Remove extra strings from both the sides to get the image name. 
            $questiontext     = trim(strip_tags($rec->questiontext)); // Remove all html tags using strip_tags.
            $str_qtext        = preg_replace("~\x{00a0}~siu", "", $questiontext);
            $qtype            = $rec->qtype;
            $explaination     = trim(strip_tags($rec->generalfeedback));
            $str_explaination = preg_replace("~\x{00a0}~siu", " ", $explaination);
            
            // echo $get_image_name;
            
            $itemid     = '';
            $filename   = '';
            $image_data = '';
            // Pass image name to get record from mdl_files.
            
            if ($get_image_name !== '' && isset($get_image_name)) {
                $get_image = $DB->get_records_sql('SELECT itemid,filename FROM {files} WHERE filename = ? AND filearea =? ', array(
                    $get_image_name,
                    'draft'
                ), $limitfrom = 0, $limitnum = 0);
                
                foreach ($get_image as $id => $record) {
                    $q_itemid   = $record->itemid;
                    $q_filename = $record->filename;
                }
                
                $filename  = $q_filename;
                $component = "user"; //if activity: database
                $filearea  = "draft";
                $itemid    = $q_itemid; // ID from table mdl_question (for activity: database) - row in the table where the above $text is stored
                
                if ($fileinfo = $browser->get_file_info($context, $component, $filearea, $itemid, '/', $filename)) {
                    
                    // build a Breadcrumb trail
                    $level  = $fileinfo->get_parent();
                    $params = $fileinfo->get_params();
                    $fs     = get_file_storage();
                    
                    $file = $fs->get_file($params['contextid'], $params['component'], $params['filearea'], $params['itemid'], $params['filepath'], $params['filename']);
                    // Create image location Path
                    if ($file) {
                        $contenthash = $file->get_contenthash();
                        $l1          = $contenthash[0] . $contenthash[1];
                        $l2          = $contenthash[2] . $contenthash[3];
                        $location    = $CFG->dataroot . '/filedir' . '/' . $l1 . '/' . $l2 . '/' . $contenthash;
                        
                        $image      = file_get_contents($location);
                        $image_data = base64_encode($image);
                    }
                }
                
            }
            
            $correct_option_id  = '';
            $get_correct_option = $DB->get_records_sql('SELECT id FROM {question_answers} WHERE question = ? AND 	fraction =? ', array(
                $quest_id,
                1
            ), $limitfrom = 0, $limitnum = 0);
            
            foreach ($get_correct_option as $id => $option) {
                $correct_option_id = $option->id;
            }
            
            $quest["QuestionId"]         = $rec->id;
            $quest["Name"]               = $rec->name;
            $quest["Type"]               = $qtype;
            $quest["Text"]               = $str_qtext;
            $quest["Encoded_Ques_Image"] = $image_data;
            $quest["Explanation"]        = $str_explaination;
            $quest["Options"]            = array();
            $quest["CorrectAnswer"]      = $correct_option_id;
            
            $qz_option = $DB->get_recordset_sql('SELECT DISTINCT qa.answer,qa.id,qa.fraction
                                                            FROM mdl_quiz AS qz
                                                            INNER JOIN mdl_quiz_slots as qzs ON qz.id=qzs.quizid
                                                            INNER JOIN mdl_question as que ON que.id=qzs.questionid
                                                            INNER JOIN mdl_question_answers as qa ON qa.question=que.id
                                                            WHERE que.id=?', array(
                $quest_id
            ));
            
            foreach ($qz_option as $id => $get_option) {
                
                $option_text      = $get_option->answer;
                $remove_txtbefore = substr($option_text, strpos($option_text, "@@/") + 3);
                $get_img_name     = substr($remove_txtbefore, 0, strpos($remove_txtbefore, '"'));
                
                $option   = trim(strip_tags($get_option->answer));
                $str      = preg_replace("~\x{00a0}~siu", "", $option);
                $str      = $option;
                $result[] = $get_option;
                
                $option_itemid     = '';
                $option_filename   = '';
                $option_image_data = '';
                
                if ($get_img_name !== '' && isset($get_img_name)) {
                    
                    $get_image = $DB->get_records_sql('SELECT itemid,filename FROM {files} WHERE filename = ? AND filearea =? ', array(
                        $get_img_name,
                        'draft'
                    ), $limitfrom = 0, $limitnum = 0);
                    
                    foreach ($get_image as $id => $record) {
                        $option_itemid    = $record->itemid;
                        $_option_filename = $record->filename;
                    }
                    
                    $filename  = $option_filename;
                    $component = "user"; //if activity: database
                    $filearea  = "draft";
                    $itemid    = $option_itemid; // ID from table mdl_question (for activity: database) - row in the table where the above $text is stored
                    
                    if ($fileinfo = $browser->get_file_info($context, $component, $filearea, $itemid, '/', $filename)) {
                        
                        // build a Breadcrumb trail
                        $level  = $fileinfo->get_parent();
                        $params = $fileinfo->get_params();
                        $fs     = get_file_storage();
                        
                        $file = $fs->get_file($params['contextid'], $params['component'], $params['filearea'], $params['itemid'], $params['filepath'], $params['filename']);
                        // Create image location Path
                        if ($file) {
                            $contenthash = $file->get_contenthash();
                            $l1          = $contenthash[0] . $contenthash[1];
                            $l2          = $contenthash[2] . $contenthash[3];
                            $location    = $CFG->dataroot . '/filedir' . '/' . $l1 . '/' . $l2 . '/' . $contenthash;
                            
                            $img               = file_get_contents($location);
                            $option_image_data = base64_encode($img);
                        }
                    }
                }
                $options["OptionId"]             = $get_option->id;
                $options["Value"]                = $str;
                $options["Encoded_Option_Image"] = $option_image_data;
                
                array_push($quest["Options"], $options);
            }
            
            array_push($response["question"], $quest);
        }
        
        $quiz_final = json_encode($response);
        
        return $quiz_final;
        
    }
    
    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_quiz_data_returns() {
        return new external_value(PARAM_TEXT, '');
    }
    
}
