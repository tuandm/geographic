<?php
/**
 * Dit Database Exception class
 * @author Tuan Duong <duongthaso@gmail.com>
 * @package  Dit
 */
namespace Dit\Database;

class Exception extends \Dit\Exception {

	protected $code = 44;

	public function __construct($code, $error=null)
	{
		$this->code = $code;
		parent::__construct($error);
	}
} // End Dit Database Exception
