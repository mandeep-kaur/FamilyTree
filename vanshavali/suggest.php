<?php

/**
 * Suggestion Class is used to add, remove or edit suggestions
 * Basically a Class to operate on suggested_info and suggest_approval
 *
 * @author piyush
 */
require_once 'member_operation_suggest.php';

class suggest extends member_operation_suggest {

    public $id, $suggested_value, $typesuggest, $suggestedby;

    function __construct($suggestid) {
        global $db;
        $this->id = $suggestid;
        $row = $db->get("select * from suggested_info where id=$suggestid");
        //$this->suggested_value = json_decode($row['suggested_value'], TRUE);
        $this->typesuggest = $row['typesuggest'];
        $this->suggestedby = $row['suggested_by'];

        //if typesuggest is remove then suggested value is in json else not
        if ($row['typesuggest'] == "remove") {
            $this->suggested_value = $row['suggested_value'];
        } else {
            $this->suggested_value = json_decode($row['suggested_value'], true);
        }
    }

    function approve() {
        global $db, $user;
        if (!$db->get("Insert into suggest_approved(suggest_id,user_id,action) values($this->id, 
                " . $user->user['id'] . ",1)")) {
            return false;
        }

        //Check if suggestion has crossed 50% Mark
        $this->check_decision();
        return true;
    }

    function reject() {
        //Rejects the $id provided in the constructor
        global $db, $user;
        if (!$db->get("Insert into suggest_approved (suggest_id,user_id,action) values
            ($this->id,".$user->user[0].",0)")) {
            return false;
        }

        //Check if suggestion has crossed 50% mark
        $this->check_decision();
        return TRUE;
    }

    function dontknow() {
        //Marks suggestion as don'tknow
        global $db, $user;
        if (!$db->get("Insert into suggest_approved (suggest_id,user_id,action)
            values($this->id,".$user->user[0].",2)")) {
            return false;
        }

        //Check if suggestion has crossed 50% mark
        $this->check_decision();
        return true;
    }

    private function checkpercent() {
        global $db;

        //Get all Rejections, Approvals, Dontknow's
        $query = $db->query("select * from suggest_approved where suggest_id=" . $this->id);
        $row2 = $db->get('select count(*) as totaluser from member where username!="" and password!=""');
        $total = mysql_num_rows($query);
        $noapproved = 0;
        $norejected = 0;
        $nodontknow = 0;

        //Count the no of approvals/Rejections
        while ($row = $db->fetch($query)) {
            switch (intval($row['action'])) {
                case 0:$norejected++;
                    break;
                case 1:$noapproved++;
                    break;
                case 2:$nodontknow++;
                    break;
                default:
                    break;
            }
        }
        $noapproved = ($noapproved / $total) * 100;
        $nodontknow = ($nodontknow / $total) * 100;
        $norejected = ($norejected / $total) * 100;

        //If approved>50 then accept the suggestion
        //if rejected>50 then reject the suggestion
        //if donknow>50 then even i don't know what to do
        if ($total == $row2['totaluser']) {
            return array($noapproved, $norejected, $nodontknow);
        } else {
            return false;
        }
    }

    private function check_decision() {
        $percent = $this->checkpercent();

        if ($percent) {
            if ($percent[0] > 50) {
                //Almost half the people have agreed, So lets add it permanently..
                $this->apply();
            } else if ($percent[1] > 50) {
                //More than half of the people have rejected it, So lets remove the suggestion
                $this->apply();
            } else if ($percent[2] > 50) {
                //More than half of the people don't know about it
                //So we have no choice lets approve this suggestion
                $this->apply();
            }
        }
    }

    private function apply() {
        global $vanshavali, $db;

        //Check if suggested_value was JSON or not
        if (is_array($this->suggested_value)) {
            $member = $vanshavali->getmember($this->suggested_value['id']);
        } else {
            $member = $vanshavali->getmember($this->suggested_value);
        }


        //We have the member to be edited. Now apply the given operation
        switch ($this->typesuggest) {
            case "child":
                $member->add_son($this->suggested_value['name'], $this->suggested_value['gender']);
                break;
            case "remove":
                $member->remove();
                break;
            case "edit":
                $member->edit($this->suggested_value['name'], $this->suggested_value['gender'], $this->suggested_value['relationship'], $this->suggested_value['dob'], $this->suggested_value['alive']);
                break;
        }

        //Now delete all the suggestion approvals as they are of no use
        $this->approval_delete();

        //Now mark the suggestion as applied So that it can be used in future
        $db->get("update suggested_info set approved=1 where id=$this->id");
    }

    function approval_delete() {
        global $db;

        if ($db->get("Delete from suggest_approved where suggest_id=$this->id")) {
            return TRUE;
        } else {
            return false;
        }
    }

}

?>
