<?php
//include the database config
include('include/config.inc.php');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Database Comparison</title>
        <style>
            #code-box {
                padding: 5px;
                border: 1pt #999 solid;
                background-color: #ccc;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <h1>MySQL Database Comparison</h2>

        <h2>Comparing <?php echo $COMPARE_DB; ?> against <?php echo $TEMPLATE_DB; ?></h2>
        <?php
        /**
         * Database template comparison by Chris Tate-Davies <chris@tatedavies.com>
         * 
         * Update config.inc.php to have your database/user/password/names
         * This is for comparing a master (template) db against another
         * */

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

                        //then we want to update it
                        $result_sql .= trim("ALTER TABLE {$comparison_name} ADD COLUMN {$template_field_name} {$template_field_type} {$template_field_default} {$template_field_extras}") . ";<br/>";

                        //if the key is valid
                        if ($template_field_key !== "") {
                            $result_sql .= "ALTER TABLE {$comparison_name} ADD INDEX {$template_field_name};<br/>";
                        }
                    }
                    
                    
                    
                    if ($field_exists_in_compare && (
                            $field_type_is_different ||
                            $field_default_is_different)) {
                        
                        //then we want to update it
                        $result_sql .= trim("ALTER TABLE {$comparison_name} CHANGE  {$template_field_name} {$template_field_name} {$template_field_type} {$template_field_default} {$template_field_extras}") . ";<br/>";

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
            }
        }
        echo "<hr><div id=\"code-box\"><pre>$result_sql</pre></div>";
        ?>
    </body>
</html>