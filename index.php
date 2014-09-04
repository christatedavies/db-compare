<?php
//include the database config
include("include/config.inc.php");
include("include/classes.php");

if (isset($_POST["master"])) {
    $TEMPLATE_DB    = $_POST["master"];
}

if (isset($_POST["compare"])) {
    $COMPARE_DB     = $_POST["compare"];
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Database Comparison</title>
        <link type="text/css" rel="stylesheet" href="dbcompare.css" media="screen" />
        <script type="text/javascript" src="dbcompare.js"></script>
    </head>
    <body>
        <h1>MySQL Database Comparison</h1>

        <h2>Comparing <?php echo $COMPARE_DB; ?> against <?php echo $TEMPLATE_DB; ?></h2>
        <?php
        /**
         * Database template comparison by Chris Tate-Davies <chris@tatedavies.com>
         * 
         * Update config.inc.php to have your database/user/password/names
         * This is for comparing a master (template) db against another
         * */

//iclude the schema class
        $tools      = new classes();

//turn off error reporting
        error_reporting(0);

        $template_db = new PDO($MAIN_DB_TYPE . ':host=' . $MAIN_DB_HOST . ';dbname=' . $TEMPLATE_DB, $MAIN_DB_USER, $MAIN_DB_PASS);
        $compare_db  = new PDO($MAIN_DB_TYPE . ':host=' . $MAIN_DB_HOST . ';dbname=' . $COMPARE_DB, $MAIN_DB_USER, $MAIN_DB_PASS);

//this is where we will save the string to run the SQL
        $result_sql = "";

//show table syntax
        $t_statement = $template_db->prepare("SHOW TABLES");
        $c_statement = $compare_db->prepare("SHOW TABLES");

//get the tables from the relavent databases
        $t_statement->execute();
        $t_tables = $t_statement->fetchAll();

        $c_statement->execute();
        $c_tables = $c_statement->fetchAll();

        $tables_added = array();
        $fields_added = array();
        
//keep a record of the table and fields
        $return_data  = array();

//loop through each table in the template
        foreach ($t_tables as $template_table) {
            $template_name = $template_table[0];

            //default a "new" flag to false
            $table_exists_in_compare = FALSE;

            //loop through each table in the comparison to see if its there
            foreach ($c_tables as $compare_table) {

                $comparison_name = $compare_table[0];

                //if it exists
                if ($comparison_name === $template_name) {

                    //set the flag thay this table lives here
                    $table_exists_in_compare = TRUE;

                    //get out!
                    break;
                }
            }

            //get the fields from this template db
            $tf_statement = $template_db->prepare("DESCRIBE " . $template_name . ";");
            $tf_statement->execute();
            $tf_field_list = $tf_statement->fetchAll();

            if ($table_exists_in_compare) {
                //and do the same for the source table
                $cf_statement = $compare_db->prepare("DESCRIBE " . $comparison_name . ";");
                $cf_statement->execute();
                $cf_field_list = $cf_statement->fetchAll();

                //loop through the fields in the template table
                foreach ($tf_field_list as $template_field) {

                    $template_field_name        = trim($template_field["Field"]);
                    $template_field_type        = trim(strtoupper($template_field["Type"]));
                    $template_field_default     = $template_field["Default"];

                    //if its a special field? autoincrement for instance
                    $template_field_extras      = strtoupper($template_field["Extra"]);

                    //do we have a key on this field?
                    $template_field_key         = $template_field["Key"];

                    //default the flag
                    $field_exists_in_compare    = FALSE;
                    $field_type_is_different    = TRUE;
                    $field_default_is_different = TRUE;

                    $added                      = FALSE;
                    $field_found                = FALSE;
                    
                    //check that the field exists?
                    foreach ($cf_field_list as $compare_field) {

                        $compare_field_name     = trim($compare_field["Field"]);
                        $compare_field_type     = trim(strtoupper($compare_field["Type"]));
                        $compare_field_default  = $compare_field["Default"];

                        //is it there?
                        if ($compare_field_name === $template_field_name) {

                            //set that flag
                            $field_exists_in_compare    = TRUE;
                            $field_found                = TRUE;
                        }
                        
                        if ($field_found) {
                            break;
                        }
                    }

                    //is the type the same? (this incldes length)
                    if ($compare_field_type === $template_field_type) {

                        $field_type_is_different = FALSE;
                    }

                    //is the default the same?
                    if ($compare_field_default === $template_field_default) {

                        $field_default_is_different = FALSE;
                    }

                    //so if the field isn't there, or the other details are different?
                    if (!$field_exists_in_compare) {

                        //if we have a default
                        if (strlen(trim($template_field_default))) {
                            
                            $template_field_default     = " DEFAULT {$template_field_default}";
                            
                        }
                        
                        //then we want to update it
                        $result_sql .= trim("ALTER TABLE {$comparison_name} ADD COLUMN {$template_field_name} {$template_field_type} {$template_field_default} {$template_field_extras}") . ";<br/>";

                        $return_data[$comparison_name]["ADDITIONS"][]  = "{$template_field_name} {$template_field_type} {$template_field_default} {$template_field_extras}";
                        
                        //if the key is valid
                        if ($template_field_key !== "") {
                            $result_sql .= "ALTER TABLE {$comparison_name} ADD INDEX {$template_field_name};<br/>";
                            $return_data[$comparison_name]["INDEXES"]   = $template_field_name;
                        }
                    }
                    
                    
                    
                    if ($field_exists_in_compare && (
                            $field_type_is_different ||
                            $field_default_is_different)) {
                        
                        //then we want to update it
                        $result_sql .= trim("ALTER TABLE {$comparison_name} CHANGE  {$template_field_name} {$template_field_name} {$template_field_type} {$template_field_default} {$template_field_extras}") . ";<br/>";
                        $return_data[$comparison_name]["CHANGES"][] = "{$template_field_name} {$template_field_name} {$template_field_type} {$template_field_default} {$template_field_extras}";
                    }
                }

            }
            if (!$table_exists_in_compare) {

                $tf_statement = $template_db->prepare("SHOW CREATE TABLE " . $template_name . ";");
                $tf_statement->execute();
                $tf_create_table = $tf_statement->fetchAll();

                $temp = $tf_create_table[0][1];

                //remove the last bit. we want the db engine to handle it
                $bracket_pos = strlen($temp) - strlen(strrchr($temp, ")"));
                $result_sql .= substr($temp, 0, $bracket_pos + 1) . ";<br/>";
                $return_data["NEWTABLES"][]    = substr($temp, 0, $bracket_pos + 1);
            }
        }
        
        
        echo "<hr><div id=\"code-box\"><pre>";
        foreach ($return_data as $item => $changes) {
            if ($item == "NEWTABLES") {
                foreach ($items as $new_table) {
                    echo $new_table."<br/>";
                }
            } else {
                //this is a table update
                $table_name         = $item;
                $additions_string   = "";
                $changes_string     = "";
                $index_string       = "";
                foreach ($changes as $type => $change) {
                    
                    if ($type == "ADDITIONS") {
                        foreach ($change as $index => $change_str) {
                            $additions_string   .= " \nADD COLUMN " . $change_str . ",";
                        }
                    } elseif ($type == "CHANGES") {
                        foreach ($change as $index => $change_str) {
                            $changes_string     .= " \nCHANGE " . $change_str . ",";
                        }
                    } elseif ($type == "INDEXES") {
                        foreach ($change as $index => $change_str) {
                            $index_string       .= " \nAND INDEX " . $change_str . ",";
                        }
                    }
                    
                }
                //trim off the ends
                $additions_string   = substr($additions_string, 0, strlen($additions_string) - 1);
                $changes_string   = substr($changes_string, 0, strlen($changes_string) - 1);
                $index_string   = substr($index_string, 0, strlen($index_string) - 1);
                
                echo "\nALTER TABLE {$table_name} ";
                if (strlen($additions_string)) {
                    echo $additions_string;
                }
                if (strlen($changes_string)) {
                    if (strlen($additions_string)) {
                        echo ",";
                    }
                    echo $changes_string;
                }
                if (strlen($index_string)) {
                    if (strlen($additions_string) || strlen($change_string)) {
                        echo ",";
                    }
                    echo $index_string;
                }
                echo ";<br/>";
            }
        }
        echo "</pre></div>";
        ?>

    <div id="database-config">
        <form id="form-submit" action="index.php" method="post">
            <div class="form-label">Master Database</div>
            <div class="form-input"><input type="text" name="master" value="<?php echo $TEMPLATE_DB;?>" /></div>
            <div class="form-label">Compare Database</div>
            <div class="form-input"><input type="text" name="compare" value="<?php echo $COMPARE_DB;?>" /></div>
            <div class="form-buttons">
                <p>
                    <a id="re-compare-button" class="button-link">Compare</a>
                </p>
            </div>
        </form>
    </div>

    <div id="about-me">
        <a href="http://www.tatedavies.com">Chris Tate-Davies - 03/Sep/2014</a>
    </div>
    </body>
</html>