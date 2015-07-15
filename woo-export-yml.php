<?php
/*
   Plugin Name: YML API Export for Woocommerce
   Plugin URI: http://progerlab.ru
   Description: Апи для экспорта товаров с Woocommerce в YML
   Version: 3.1
   Author: Ivantsov Mikhail
   Author URI: mailto:m@progerlab.ru
*/

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ){



	class WooExportYmlInit
	{
		

		function __construct()
		{

			$this->id 					= 'export_yml';
			$this->sourcesExportApis 	= array();


			require_once( dirname(__FILE__). '/unittests.php' );
			require_once( dirname(__FILE__). '/WooExportYmlFunctions.php' );
			require_once( dirname(__FILE__). '/api.php' );
			require_once( dirname(__FILE__). '/source-manager.php' );

			add_filter( 'woocommerce_get_settings_pages', array( $this,  'add_settings' ) );
			add_action( 'wp_ajax_yml_send_log', array( $this, 'send_log' ) );
			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_vendor_field' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'add_vendor_field_save' ) );


			$this->sources = new WooExportYmlSourceManager( $this->id );
			$this->init_sources_apis();
			

		}

		public function init_sources_apis(){

			foreach( $this->sources->get_sources() as $key => $name ){


					$bid 					= get_option($key.'_bid');
					$vendors 				= get_option($key.'_vendors');
					
					$settings = array(
						'isdeliver' 		=> ( get_option($key.'_isdeliver') == 'yes' ) ? true : false,
						'isexportattr' 		=> ( get_option($key.'_isexportattr') == 'yes' ) ? true : false,
						'isexporpictures' 	=> ( get_option($key.'_isexportpictures') == 'yes' ) ? true : false,
						'ispickup' 			=> ( get_option($key.'_ispickup') == 'yes' ) ? 'true' : 'false',
						'isstore' 			=> ( get_option($key.'_isstore') == 'yes' ) ? 'true' : 'false',
						'cpa' 				=> ( get_option($key.'_cpa') == 'yes' ) ? true : false,
						'isgroupidattr'  => ( get_option($key.'_isgroupidattr') == 'yes' ) ? true : false,
						'bid' 				=> ( !empty( $bid ) ) ? $bid : false,
						'isbid' 			=> ( get_option($key.'_isbid') == 'yes' ) ? true : false,
						'vendors' 			=> ( !empty( $vendors ) ) ? ( $vendors == 'false' ) ? false : $vendors : false,
						'salesNote' 		=> get_option($key.'_sales_note')
					);

					$this->sourcesExportApis[ $key ] = new WooExportYmlApi( $key, $settings );

			}

		}

		public function send_log(){

			$logs 		= glob( dirname(__FILE__) . '/*.log' );
			$logLinks 	= array();

			foreach ($logs as $file) {
				$logLinks[]	= str_replace(dirname(__FILE__), plugins_url('', __FILE__), $file);
			}

			wp_mail( 
				'm@progerlab.ru', 
				'Problem with plugin WooExportYml', 
				'Domain: '.home_url()."\n\nLogs:\n".implode("\n",$logLinks),  
				'From: Problem with plugin WooExportYml <info@'.str_replace('http://', '', home_url() ).'>' . "\r\n"
			);

			die("ok");
		}
		
		public function add_settings( $settings ){

			$settings[] = include( dirname(__FILE__). '/settings.php' );	
			
			new WC_Settings_Export_YML( $this->id , $this->sources );

			return $settings;
		} 

		static function activate(){

			wp_mail( 
				'm@progerlab.ru', 
				'Install plugin WooExportYml', 
				'Domain: '.home_url(),  
				'From: Activation Plugin WooExportYml <info@'.str_replace('http://', '', home_url() ).'>' . "\r\n"
			);
		}

		/*
			Создает дополнительное поле Бренд, для товара. Обязателен для выгрузки
		*/
		public function add_vendor_field(){
			global $woocommerce, $post;
		  
			echo '<div class="options_group">';
			woocommerce_wp_text_input( 
				array( 
					'id'                => '_vendor', 
					'label'             => 'Производитель', 
					'placeholder'       => '', 
					'description'       => 'Если не заполенено, то не учавствует в выгрузке маркета',
					'type'              => 'text', 
				) 
			);
			echo '</div>';
		}


		/*
			Сохраняет дополнительное поле Бренд, для товара. 
		*/
		public function add_vendor_field_save( $post_id )
		{
			$field_name = '_vendor';
			$vendor     = $_POST[ $field_name ];
			update_post_meta( $post_id, $field_name, $vendor );
		}

	} $WooExportYmlInit = new WooExportYmlInit();

	register_activation_hook( __FILE__, array( $WooExportYmlInit, 'activate' ) );	

}