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

		$_picture = explode('/', str_replace(home_url('/'), "", $url ));
		$picture_a = array();
		
		foreach($_picture as $v_ulr) {
			$picture_a[] = rawurlencode($v_ulr);
		}
		
		$_picture = home_url('/').implode('/', $picture_a );

		return $_picture;
	}

	public static function del_symvol($str){

		$tr = array(
			";"=>" ",":"=>" ",">"=>" ","В«"=>" ",
			"В»"=>" ","\""=>" ","@"=>" ","#"=>" ","$"=>" ",
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
