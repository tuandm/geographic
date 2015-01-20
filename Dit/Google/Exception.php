<?php
/**
 * Dit Google Geographic Exception class
 * @author Tuan Duong <duongthaso@gmail.com>
 * @package  Dit
 */
namespace Dit\Google;

class Exception extends \Dit\Exception {

    protected $code = 44;

    public function __construct($code, $error=null)
    {
        $this->code = $code;
        parent::__construct($error);
    }
} // End Dit Google Geographic Exception
