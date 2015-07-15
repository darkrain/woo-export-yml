<?php

class WooExportYmlUnitTests
{

	function __construct( $ymlApi )
	{
		$this->ymlApi 		= $ymlApi;
		$this->blackList 	= array(
			'__construct','process'
		);

		
	}

	public function process(){

		$this->ymlApi->debugOn();

		$checks = get_class_methods($this);

		foreach ($checks as $check) {
			if( !in_array( $check, $this->blackList ) ){

				$check_result = $this->{$check}();

				if( $check_result !== false ){
					$this->ymlApi->bread('test:'.$check.':fail, '.$check_result );
				}
			}
		}

		$this->ymlApi->debugOff();
	}

	public function checkProcess(){

		$this->ymlApi->inProcessSet('yes');

		if( !$this->ymlApi->inProcess() )
			return -1;

		$this->ymlApi->inProcessSet('no');

		if( $this->ymlApi->inProcess() )
			return -2;
		

		return true;
	}

}

