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
} 