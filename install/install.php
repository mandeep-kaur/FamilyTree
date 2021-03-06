<?php

/**
 * Script to install vanshavali
 * @package install
 * @author piyush
 */
$mode = @$_GET['mode'];
$sub = @$_GET['sub'];

class install {
    /*
     * main function to install to initiate vanshavali installation
     */

    function install() {
        global $mode, $sub;
        //make sure to run this only if database is not installed
        //So check if database is installed
        if (!empty($config) and file_exists("../config.php")) {
            //its installed! Return to index.php
            header("Location:../index.php");
        }

        //Reached here huh? Installtion will begin now
        //Check mode and perform actions
        switch ($mode) {
            //All the other option will be above ask_database_name as it is also the default


            case "ask_database_name":
            default:
                $this->ask_database_name($mode, $sub);
                break;
        }
    }

    /**
     * Asks for database name from the user where to install vanshavali
     * @param string $mode Describes which phase is currently running
     * @param string $sub Describes which part is running of the phase
     */
    function ask_database_name($mode, $sub) {
        global $template, $db;
        $sub = ($sub == null) ? 1 : $sub;
        if ($sub == 1) {
            $template->header();
            $template->display("install.ask_database_details.tpl");
        } elseif ($sub == 2) {
            $host = $_POST['database_host'];
            $username = $_POST['database_username'];
            $password = $_POST['database_password'];
            $database = $_POST['database_name'];

            if (empty($host) || empty($username) || empty($password) || empty($database)) {
                $template->header();
                $template->assign(array("error" => 1,
                    "message" => "Form not completed. Please complete the form"));
                $template->display("install.ask_database_details.tpl");
                return;
            }

            //Connect to database
            $db->connect($host, $username, $password);

            //Create Database
            $db->query("CREATE DATABASE if not exists $database");

            //Select The given database
            $db->select_db($database);

            //Setup basic database
            $this->setup_database();

            //Now create the config.php file save it
            $file = fopen("config.php", "w+");
            if (!$file) {
                trigger_error("Error opening or creating config.php file", E_USER_ERROR);
            }

            $data = "<?php\n\$config['host']='$host';
                    \n\$config['username']='$username';
                    \n\$config['password']='$password';
                    \n\$config['database']='$database';
                    \n?>";

            $wr = fwrite($file, $data);
            fclose($file);

            //Set file permission to 0644, Never leave this 0
            if (!chmod("config.php", 0644)) {
                //Read and write for the owner and read for everyone else
                trigger_error("Cannot set config.php permissions", E_USER_ERROR);

                //Check if permissions have been successfull applied or not
                if (!is_readable("config.php")) {
                    trigger_error("Wrong config.php permissions. Please give config.php file 644 permission. <br> Use the Following command<br>$ chmod 644 config.php", E_USER_ERROR);
                }
            }

            $template->display("database_success.tpl");
        }
    }

    /**
     * Function to setup the database
     */
    private function setup_database() {
        global $db;
        //Install the tables

        if (!$this->installTables()) {
            trigger_error("Cannot create Tables", E_USER_ERROR);
        }
    }

    private function installTables() {
        global $db;
        $family = $db->query("Create table family (
            id int(11) not null primary key auto_increment,
            family_name mediumtext not null,
            ts int(11) not null )");
        
        
        $member = $db->query("create table member (
            id int(11) null primary key auto_increment,
            membername mediumtext not null,
            username mediumtext default null,
            password mediumtext default null,
            sonof int(11) null default null,
            profilepic varchar(255) default 'common.png',
            dob int(11) default null,
            gender int(1) default 0,
            relationship_status int(11) default 0,
            gaon mediumtext default null,
            related_to int(11) null default null,
            emailid text default null,
            alive int(1) default 0,
            aboutme longtext default null,
            lastlogin int(11) default null,
            joined int(11) default null,
            approved int(1) default 0,
            tokenforact text default null,
            dontshow int(1) default 0,
            family_id int(11) default 1,
            foreign key (family_id) references family(id),
            foreign key (related_to) references member(id) );");

        $feedback = $db->query("create table feedback (
            id int(11) not null primary key auto_increment,
            user_name text not null,
            user_emailid text not null,
            feedback_text text not null,
            seen int(1) default 0 );");

        $joinrequest = $db->query("create table joinrequest (
            id int(11) not null primary key auto_increment,
            formember int(11) not null,
            pic text default null,
            personalmessage text default null,
            emailid text not null,
            tokenforact varchar(20) default null,
            approved int(1) default 0,
            foreign key(formember) references member(id) );");

        $suggested_info = $db->query("create table suggested_info (
            id int(11) not null primary key auto_increment,
            typesuggest mediumtext not null,
            suggested_value text not null,
            suggested_by int(11) not null,
            ts int(11) not null,
            approved int(1) default 0,
            foreign key(suggested_by) references member(id) );");

        $suggest_approved = $db->query("create table suggest_approved (
            id int(11) not null primary key auto_increment,
            suggest_id int(11) not null,
            user_id int(11) not null,
            action int(2) not null,
            foreign key (suggest_id) references suggested_info(id),
            foreign key (user_id) references member(id) );");

        $dasfamily = $db->query("insert into family (family_name,ts) values('Das Family'," . time() . ");");
        //Now the data that we already have
        $memberdata = file_get_contents("member_data.sql");

        $memberdata_sql = $db->query($memberdata);

        return $member && $feedback && $joinrequest && $suggested_info
                && $suggest_approved && $memberdata_sql && $family && $dasfamily;
    }

}

?>
