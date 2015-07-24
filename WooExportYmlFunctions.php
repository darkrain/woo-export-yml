<?php



class WooExportYmlFunctions
{



	public static function substr( $text, $max, $removelastword = true ){
		
		$text = mb_substr($text, 0, $max );

		if( $removelastword ){
			$text = explode('. ', $text );
			unset( $text[ count( $text ) -1 ] );
			$text = implode('. ', $text );
		}

		return $text;
	}

	public static function sanitize( $url ){

		if( empty( $url  ) )
			return false;

		$_p = explode('/', str_replace(home_url('/'), "", $url ));
		$_a = array();
		
		foreach($_p as $v_ulr) {
			$_a[] = rawurlencode($v_ulr);
		}
		
		$_u = home_url('/').implode('/', $picture_a );

		return $_u;
	}

	public static function del_symvol($str){

		$tr = array(
			";"=>" ",":"=>" ",">"=>" ","«"=>" ",
			"»"=>" ","\""=>" ","@"=>" ","#"=>" ","$"=>" ",
			"*" => " ", "%" => " ", "&" => " "
	 	);

		return strtr($str,$tr);
	}

	public function print_gzencode_output( $filename ){ 

	    $contents = ob_get_clean(); 

		header('Content-Disposition: attachment; filename="'.$filename.'"');
	    header('Content-Encoding: gzip'); 
	    $contents = gzencode($contents, 9); 
	    print($contents); 

	} 

}
