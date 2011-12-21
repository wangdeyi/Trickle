<?php
/**
 * class ModelDataWrapper
 * 
 * data object wrapper
 *
 * @author kim
 * @since 2011-11-28
 * @version 1.0.0
 * 
 */
class ModelDataWrapper implements arrayaccess
{
    /**
     * @var mixed
     */
    private $_data = array();

    /**
     * construct
     * 
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->_data = $data;
    }

    /**
     * set array element
     * 
     * @see ArrayAccess::offsetSet()
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_data[] = $value;
        } else {
            $this->_data[$offset] = $value;
        }
    }

    /**
     * check if array element exists
     * 
     * @see ArrayAccess::offsetExists()
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    /**
     * unset array element by offset
     * 
     * @see ArrayAccess::offsetUnset()
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->_data[$offset]);
    }

    /**
     * get array element by offset
     *  
     * @see ArrayAccess::offsetGet()
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return isset($this->_data[$offset]) ? $this->_data[$offset] : null;
    }

    /**
     * when try to get properties : $obj->property_1
     * 
     * @todo to throw exceptions instead of return empty string?
     * @param mixed $key
     * @return mixed
     */
    public function __get($key)
    {
        if (isset($this->_data[$key])) {
            return $this->_data[$key];
        } else {
            return '';
        }
    }

    /**
     * when try to set properties : $obj->property_1 = $value_1
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function __set($key, $value)
    {
        if (isset($this->_data[$key])) {
            $this->_data[$key] = $value;
        }
    }

    /**
     * check if property exists
     *
     * @param mixed $key
     * @return mixed
     */
    public function __isset($key)
    {
        return isset($this->_data[$key]);
    }
    
    /**
     * unset the property's value
     * 
     * @param mixed $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->_data[$key]);
    }

}