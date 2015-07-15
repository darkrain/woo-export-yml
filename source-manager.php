<?php


class WooExportYmlSourceManager {

	public function __construct( $id ) {

		$this->id = $id;

		add_action( 'wp_ajax_add_yml_source', array( $this, 'ajax_add_source' ) );
		add_action( 'wp_ajax_del_yml_source', array( $this, 'ajax_del_source' ) );

	}

	public function addSource( $name ){


		if( empty( $name ) )
			return false;

		$sources = (array)get_option($this->id.'_sources');

		$key = $this->transliterate( $name );

		if( $key != 'yandex_market' && !isset( $sources[ $key ] ) ){

			$sources[ $key ] = $name;
			update_option( $this->id.'_sources', $sources );
			
			return true;
		
		}else
			return false;
		
	}


	public function deleteSource( $key ){

		$sources = (array)get_option($this->id.'_sources');

		if( isset( $sources[ $key ] ) ){

			unset( $sources[ $key ] );
			update_option( $this->id.'_sources', $sources );
			
			return true;
		
		}else
			return false;

	}

	public function ajax_add_source(){

		if( $this->addSource( $_POST['name'] ) ){
			echo die( array( 'code' => 'success' ) );
		}else{
			echo die( array( 'code' => 'failed' ) );
		}

	}


	public function ajax_del_source(){

		if( $this->deleteSource( $_POST['key'] ) ){
			echo die( array( 'code' => 'success' ) );
		}else{
			echo die( array( 'code' => 'failed' ) );
		}

	}

	public function get_sources(){

		$def_sources = array(
			'yandex_market' => 'Яндекс.Маркет'
		);

		$sources = (array)get_option($this->id.'_sources');


		$out_sources =  array_merge( $def_sources, $sources );

		foreach( $out_sources as $key => $name ){
			if( empty( $name ) )
				unset( $out_sources[ $key ] );
		}

		return $out_sources;

	}


	public function transliterate( $str ) {
	
	    $trans = array("а"=>"a","б"=>"b","в"=>"v","г"=>"g","д"=>"d","е"=>"e",
			"ё"=>"yo","ж"=>"j","з"=>"z","и"=>"i","й"=>"i","к"=>"k","л"=>"l",
			"м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r","с"=>"s","т"=>"t",
			"у"=>"y","ф"=>"f","х"=>"h","ц"=>"c","ч"=>"ch", "ш"=>"sh","щ"=>"shh",
			"ы"=>"i","э"=>"e","ю"=>"u","я"=>"ya","ї"=>"i","'"=>"","ь"=>"","Ь"=>"",
			"ъ"=>"","Ъ"=>"","і"=>"i","А"=>"A","Б"=>"B","В"=>"V","Г"=>"G","Д"=>"D",
			"Е"=>"E", "Ё"=>"Yo","Ж"=>"J","З"=>"Z","И"=>"I","Й"=>"I","К"=>"K", "Л"=>"L",
			"М"=>"M","Н"=>"N","О"=>"O","П"=>"P", "Р"=>"R","С"=>"S","Т"=>"T","У"=>"Y",
			"Ф"=>"F", "Х"=>"H","Ц"=>"C","Ч"=>"Ch","Ш"=>"Sh","Щ"=>"Sh", "Ы"=>"I","Э"=>"E",
			"Ю"=>"U","Я"=>"Ya","Ї"=>"I","І"=>"I");

	    $res = str_replace(" ","-",strtr(strtolower($str),$trans));
	    $res = preg_replace("|[^a-zA-Z0-9-]|","",$res);
	    return $res;
	}


}

new WooExportYmlSourceManager('export_yml');