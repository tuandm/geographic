<?php

namespace Dit\Database;

class Exception extends \Dit\Exception {

	protected $code = 44;

	public function __construct($code, $error=null)
	{
		$this->code = $code;
		parent::__construct($error);
	}
} // End Dit Database Exception
