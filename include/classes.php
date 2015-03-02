<?php
/**
 * Functions for db-schema-compare
 *
 * @author      Chris Tate-Davies (chris@tatedavies.com)
 * @since       21/08/2014
 */

class classes {

    protected $_connection;
    protected $_field_type;
    protected $_field_default;
    protected $_field_name;

    protected function get_primary_key($table) {

    }

    protected function does_field_exist($field_name) {

    }

    public function set_connection($_connection) {
        $this->_connection  = $_connection;
        return $this;
    }

    public function get_connection(){
        return $this->_connection;
    }
    
    public function eval_field_default_should_be_enclosed_in_quotes($field_type) {
        
        //which fields do we want to wrap with quotes?
        $allowed_types  = array("char", "tinytext", "mediumtext", "longtext", "varchar", "text", "datetime", "date", "time");
        
        //remove anything up to a ( symbol
        if (strpos($field_type, "(") !== FALSE) {
            
            //get upto that point
            $field_type     = substr($field_type, 0, strpos($field_type, "("));
        }
        
        // is it in the array?
        return in_array(strtolower($field_type), $allowed_types);
    }
} 