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
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

class local_quiz_sync_external extends external_api
{
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
   public static function get_sync_data_parameters() {
        return new external_function_parameters(array(
            'userid' => new external_value(PARAM_INT, ' user id'),
            'quizid' => new external_value(PARAM_INT, ' quiz id'),
            
            /*'response' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ),
                    'the data to be saved', VALUE_DEFAULT, array())*/
            
            'response' => new external_multiple_structure(new external_single_structure(array(
                'qId' => new external_value(PARAM_RAW, 'Question instance id'),
                'optionId' => new external_value(PARAM_RAW, 'Option Id Or Text'),
                'qType' => new external_value(PARAM_RAW, 'Qtype')
            )), 'the data to be saved', VALUE_DEFAULT, array())
        ));
    }
    
    /**
     * Returns welcome message
     * @return string welcome message
     */
    public static function get_sync_data($userid, $quizid, $response) {
    
        global $DB;
        global $CFG;
        
        $result = array();
        $warnings = array();
        $params = array();

        $params = array(
            'quizid' => $quizid,
            'userid' => $userid,
            'response' => $response
        );
        
        $params = self::validate_parameters(self::get_sync_data_parameters(), $params);
        $quizobj = quiz::create($quizid,$userid);
        $total_quiz_attempt = $quizobj->get_quiz()->attempts;
        $timenow       = time();
        $accessmanager = $quizobj->get_access_manager($timenow);
            
        list($currentattemptid, $attemptnumber, $lastattempt, $messages, $page) = quiz_validate_new_attempt($quizobj, $accessmanager, $forcenew, -1, false);

           if($lastattempt->state === "inprogress") {
                 $params = array(
                                   'attemptid'=> $lastattempt->id,
                                   'page'=> $lastattempt->currentpage
                                  // 'response' => $response,
                                 );
                                 
                        $result['result']=self::save_attemptdata($params, $response);   
                        
           }
           else {
                   if($total_quiz_attempt === $lastattempt->attempt) {
                       $result['warning']='attempt Not available';
                   }
                   else {
                       $user_attempt_obj = self::create_attempt_and_getobj($quizid, $userid, $attemptnumber, $lastattempt);
                        $params = array(
                                   'attemptid'=> $user_attempt_obj->id,
                                 );
                       $result['result']=self::save_attemptdata($params, $response);
                   }
           }
        print_r($result);
    }
        
    function create_attempt_and_getobj($quizid, $userid, $attemptnumber, $lastattempt) { 
        $quizobj = quiz::create($quizid,$userid);
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);
        return $attempt;
    }
    
    function save_attemptdata($params, $appUserData) {
        
        
        global $DB;
        // Add a page, required by validate_attempt.
        list($attemptobj, $messages) = self::validate_attempt($params);
     
        // Create the $_POST object required by the question engine.
        $arr_data_to_process = self::getUserResponsewithParsedData($appUserData, $attemptobj);
        
        
        $transaction = $DB->start_delegated_transaction();
        
        $_POST = array();
        foreach ($arr_data_to_process as $elements) {
            foreach ($elements as $key => $value) {
             $_POST[$key] = $value;
            }
            $timenow = time();
            $attemptobj->set_offline_modified_time($timenow);
            $attemptobj->process_auto_save($timenow);
            
            
            
        }
        $transaction->allow_commit();
        
        return self::finishedAttempt($params);
    }
    
    function finishedAttempt($params) {
        
        $timenow = time();
        list($attemptobj, $messages) = self::validate_attempt($params, false);
        $finished = $attemptobj->process_attempt($timenow, true);
        echo $messages;
        return $finished;
    }
   
    function getUserResponsewithParsedData($arrayofAppdata, $attemptobj) {
        
        $slots = $attemptobj->get_slots();
        $uniqId = $attemptobj->get_attempt()->uniqueid;
        $userResponse = [];
        
        foreach ($slots as $slotno) {
            
            $moodleQid = self::getQuestionIdFromSlot($slotno, $attemptobj);
            
                foreach ($arrayofAppdata as $element) {
               
                    $questionId = $element['qId'];
                    
                    if ($moodleQid == $questionId) {
    
                        $sequencecheck = $attemptobj->get_question_attempt($slotno)->get_sequence_check_count();
                        $qType = $element['qType'];
                        $optionIdOrvalue = $element['optionId'];
                
                            if ($qType == "TrueFalse" || $qType == "InputType") {  //Actual Qtype Keys pass here if tue false 0 false 1 true (Moodle standard)
                                $response = self::prepareQuesAttempt($slotno, $uniqId, $sequencecheck, $optionIdOrvalue);
                                array_push($userResponse, $response);
                            }
                            else {
                                $qa = $attemptobj->get_question_attempt($slotno)->get_full_qa();
                                $arr_option_orders =  $attemptobj->get_question_attempt($slotno)->get_question()->get_order($qa);
                                $answerPositionValue = self::getOptionPosition($optionIdOrvalue, $arr_option_orders);
                                $response = self::prepareQuesAttempt($slotno, $uniqId, $sequencecheck, $answerPositionValue);
                                array_push($userResponse, $response);
                }
            }
          }
        }
       return $userResponse;
    }
    
    function prepareQuesAttempt($slot, $uniqId, $sequencecheck, $answerValue) {
       $questionData = array("slots"=>$slot, self::prpareSequencecheck($slot, $uniqId)=>$sequencecheck, self::prepareAnswer($slot, $uniqId)=>$answerValue);
        return $questionData;
    }
    
    function getQuestionIdFromSlot($slotno, $attemptobj) {
        return $attemptobj->get_question_attempt($slotno)->get_question()->id;
    }
    
    function prpareSequencecheck($slotno, $uniqId) {
        $name = "q". $uniqId .":". $slotno ."_:sequencecheck";
        return $name;
    }
    
    function prepareAnswer($slotno, $uniqId) {
        $name = "q". $uniqId .":". $slotno ."_answer";
        return $name;
    }
   
    function getOptionPosition($optionId, $arr_options) { // from optionId
        $value = array_search($optionId, $arr_options);
        return $value;
    }
    
    /**
     * Utility function for validating a given attempt
     *
     * @param  array $params array of parameters including the attemptid and preflight data
     * @param  bool $checkaccessrules whether to check the quiz access rules or not
     * @param  bool $failifoverdue whether to return error if the attempt is overdue
     * @return  array containing the attempt object and access messages
     * @throws moodle_quiz_exception
     * @since  Moodle 3.1
     */
    protected static function validate_attempt($params, $checkaccessrules = true, $failifoverdue = true) {
        global $USER;

        $attemptobj = quiz_attempt::create($params['attemptid']);

        $context = context_module::instance($attemptobj->get_cm()->id);
        self::validate_context($context);

        // Check that this attempt belongs to this user.
        if ($attemptobj->get_userid() != $USER->id) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
        }

        // General capabilities check.
        $ispreviewuser = $attemptobj->is_preview_user();
        if (!$ispreviewuser) {
            $attemptobj->require_capability('mod/quiz:attempt');
        }

        // Check the access rules.
        $accessmanager = $attemptobj->get_access_manager(time());
        $messages = array();
        if ($checkaccessrules) {
            // If the attempt is now overdue, or abandoned, deal with that.
            $attemptobj->handle_if_time_expired(time(), true);

            $messages = $accessmanager->prevent_access();
            if (!$ispreviewuser && $messages) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'attempterror');
            }
        }

        // Attempt closed?.
        if ($attemptobj->is_finished()) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'attemptalreadyclosed');
        } else if ($failifoverdue && $attemptobj->get_state() == quiz_attempt::OVERDUE) {
            throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'stateoverdue');
        }

        // User submitted data (like the quiz password).
        if ($accessmanager->is_preflight_check_required($attemptobj->get_attemptid())) {
            $provideddata = array();
            foreach ($params['preflightdata'] as $data) {
                $provideddata[$data['name']] = $data['value'];
            }

            $errors = $accessmanager->validate_preflight_check($provideddata, [], $params['attemptid']);
            if (!empty($errors)) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), array_shift($errors));
            }
            // Pre-flight check passed.
            $accessmanager->notify_preflight_check_passed($params['attemptid']);
        }

        if (isset($params['page'])) {
            // Check if the page is out of range.
            if ($params['page'] != $attemptobj->force_page_number_into_range($params['page'])) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'Invalid page number');
            }

            // Prevent out of sequence access.
            if (!$attemptobj->check_page_access($params['page'])) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'Out of sequence access');
            }

            // Check slots.
            $slots = $attemptobj->get_slots($params['page']);

            if (empty($slots)) {
                throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'noquestionsfound');
            }
        }
        return array($attemptobj, $messages);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_sync_data_returns()
    {
        return new external_value(PARAM_TEXT, 'The welcome message + user first name');
    }
}


