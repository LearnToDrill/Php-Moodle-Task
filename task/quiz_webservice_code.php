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
//NOTE: Only JPEG TYPE image is supported by Moodle

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . "/lib/filebrowser/file_browser.php");

class local_quiz_external extends external_api
{
    
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_quiz_data_parameters()
    {
        return new external_function_parameters(array(
            'quizid' => new external_value(PARAM_RAW, 'This will check quiz id')
        ));
    }
    
    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function get_quiz_data($quizid)
    {
        global $USER;
        global $DB;
        global $CFG;
        $response     = array();
        $questiondata = array();
        $result       = array();
        $quest        = array();
        $browser      = get_file_browser();
        $context      = context_user::instance($USER->id, IGNORE_MISSING);
        
        
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
        
        foreach ($quizdetails as $id => $rec) { //Loop through quiz
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
            
            
            $q_itemid   = '';
            $q_filename = '';
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
            
            if($qtype === "truefalse"){
                
                 $get_correct_option = $DB->get_records_sql('SELECT id,answer FROM {question_answers} WHERE question = ? AND     fraction =? ', array(
                 $quest_id,
                 1
                 ), $limitfrom = 0, $limitnum = 0);
                 
                 foreach ($get_correct_option as $id => $option) {
                     
                    $correct_option_id = $option->answer;
                }
                
                if($correct_option_id =='True'){
					
					$correct_option_id = 1;
					}
					else{
					$correct_option_id = 0;
					}
                
            }else{

            $get_correct_option = $DB->get_records_sql('SELECT id FROM {question_answers} WHERE question = ? AND fraction =? ', array(
                $quest_id,
                1
            ), $limitfrom = 0, $limitnum = 0);
            
            foreach ($get_correct_option as $id => $option) {
                $correct_option_id = $option->id;
            }
            }
            $quest["QuestionId"]         = $rec->id;
            $quest["Name"]               = $rec->name;
            $quest["QType"]              = $qtype;
            $quest["Text"]               = $str_qtext;
            $quest["Encoded_Ques_Image"] = $image_data;
            $quest["Explaination"]       = $str_explaination;
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
            
           // print_r($qz_option);
            foreach ($qz_option as $id => $get_option) {
                
                $option_text      = $get_option->answer;
                $remove_txtbefore = substr($option_text, strpos($option_text, "@@/") + 3);
                $img_name         = substr($remove_txtbefore, 0, strpos($remove_txtbefore, '"'));
                
                $option           = trim(strip_tags($get_option->answer));
                $str              = preg_replace("~\x{00a0}~siu", "", $option);
                $str_option_value = $option;
                $result[]         = $get_option;
                
                $option_itemid     = '';
                $option_filename   = '';
                $option_image_data = '';
                
                if ($img_name !== '' && isset($img_name)) {
                    
                    $get_image = $DB->get_records_sql('SELECT itemid,filename FROM {files} WHERE filename = ? AND filearea =? ', array(
                        $img_name,
                        'draft'
                    ), $limitfrom = 0, $limitnum = 0);
                    
                    foreach ($get_image as $id => $record) {
                        $option_itemid   = $record->itemid;
                        $option_filename = $record->filename;
                    }
                    
                    $filename  = $option_filename;
                    $component = "user"; //if activity: database
                    $filearea  = "draft";
                    $item_id   = $option_itemid;
                    
                    if ($fileinfo = $browser->get_file_info($context, $component, $filearea, $item_id, '/', $filename)) {
                        
                        // build a Breadcrumb trail
                        $level  = $fileinfo->get_parent();
                        $params = $fileinfo->get_params();
                        $fs     = get_file_storage();
                        
                        $files = $fs->get_file($params['contextid'], $params['component'], $params['filearea'], $params['itemid'], $params['filepath'], $params['filename']);
                        // Create image location Path
                        
                        if ($files) {
                            $contenthash = $files->get_contenthash();
                            $l1          = $contenthash[0] . $contenthash[1];
                            $l2          = $contenthash[2] . $contenthash[3];
                            $location    = $CFG->dataroot . '/filedir' . '/' . $l1 . '/' . $l2 . '/' . $contenthash;
                            
                            $img               = file_get_contents($location);
                            $option_image_data = base64_encode($img);
                            
                        }
                        
                    }
               
                }
                    $options["OptionId"]             = $get_option->id;
                    $options["Value"]                = $str_option_value;
                    $options["Encoded_Option_Image"] = $option_image_data;
                    
                if ($qtype === "truefalse") {
                    
                    if ($str_option_value === "True") {
                        $option_data = 1;
                    } else {
                        $option_data = 0;
                    }
                    $options["OptionId"] = $option_data;
                    $options["Value"]    = $str_option_value;
                    
                } 
                 array_push($quest["Options"], $options);
            }
            
            array_push($questiondata, $quest);
        }
        if ($DB->record_exists_sql('SELECT * FROM {quiz_sections} where quizid=? AND shufflequestions=1', array(
            $quizid
        ))) {
            $response['Shuffle'] = 1;
        } else {
            $response['Shuffle'] = 0;
        }
        $response['Questions'] = $questiondata;
        return $response;
    }
    
    /**
     * Returns description of method result value
     * @return external_description
     */
    // public static function get_quiz_data_returns() {
    //     return new external_value(PARAM_TEXT, 'Pass request parameter quizID and in response, you will receive questions with its options along with the correct answer for each question and its explanation');
    // }
    
    public static function get_quiz_data_returns()
    {
        
        return new external_single_structure(array(
            'Questions' => new external_multiple_structure(new external_single_structure(array(
                'QuestionId' => new external_value(PARAM_RAW, 'Question ID'),
                'Name' => new external_value(PARAM_RAW, 'Question Name'),
                'QType' => new external_value(PARAM_RAW, 'Question Type.'),
                'Text' => new external_value(PARAM_RAW, 'Question Text.'),
                'Encoded_Ques_Image' => new external_value(PARAM_RAW, 'Encoded Image '),
                'Explaination' => new external_value(PARAM_RAW, 'Explaination to the question.'),
                'CorrectAnswer' => new external_value(PARAM_RAW, 'Correct Answer'),
                'Options' => new external_multiple_structure(new external_single_structure(array(
                    'OptionId' => new external_value(PARAM_RAW, 'OptionID'),
                    'Value' => new external_value(PARAM_RAW, 'Option value'),
                    'Encoded_Option_Image' => new external_value(PARAM_RAW, 'Encoded Image')
                )), VALUE_DEFAULT, array())
            )), VALUE_DEFAULT, array()),
            'Shuffle' => new external_value(PARAM_INT, 'Question Shuffle')
        ), VALUE_DEFAULT, array());
    }
}