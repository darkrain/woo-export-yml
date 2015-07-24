<?php


class WooExportYmlApi
{
	
	
	private $id;			// ID плагина, для нескольких вариантов yml выгрузки
	private $name;			// Имя типа выгрузки

	private $currentpage; 	// Текущая страница выгрузки
	private $isdeliver;		// Управление доставкой, включена или нет
	private $isstore;		// Наличие точки продаж 
	private $iscpa; 		// Покупка на маркете
	private $bid;			// Цена ставки
	private $isbid;			// Управление ставками, включено или нет
	private $isexportattr;	// Выгружать свойства, да или нет
	private $vendors; 		// Определена ли таксономия бренда
	private $salesNote; 	// Заметка к продаже
	private $isMakeYml; 	// Определяет, выгрузился ли до конца товар
	private $debug;			// Включает дебаг при выгрузке
	private $isgroupidattr; // Добавление атрибута group_id к вариативным товарам

	function __construct( $export_id, $settings )
	{

	
		$this->id 				= $export_id;
		$this->shellPrefix 		= $export_id;


		$this->currentpage		= ( get_option($this->id.'_page') ) ? get_option($this->id.'_page') : 1;
		$this->pages			= ( get_option($this->id.'_pages') ) ? get_option($this->id.'_pages') : 1;
		$this->isMakeYml		= false;
		$this->debug 			= false;
		$this->posts 			= get_option($this->id.'_get_ids');
		$this->md5offer			= array();

		$def_settings = array(
			'isdeliver' 		=> false,
			'isexportattr' 		=> false,
			'isexporpictures' 	=> false,
			'ispickup' 			=> 'false',
			'isstore' 			=> 'false',
			'cpa' 				=> false,
			'isgroupidattr'     => false,
			'bid' 				=> false,
			'isbid' 			=> false,
			'vendors' 			=> false,
			'salesNote' 		=> ''
		);

		foreach( $def_settings as $set => $val ){
			
			if( isset( $settings[ $set ] ) )
				$this->{$set} = $settings[ $set ];
			else
				$this->{$set} = $val;
		
		}
		
		add_action('init', array( $this, 'init') );

		
		if( isset($_GET['tab']) && $_GET['tab'] == $this->id and  isset( $_REQUEST['save'] ) ){
			$this->action_unlock();
		}

		
		$this->unitTests = new WooExportYmlUnitTests( $this );
	}


	public function add_htaccess_rule( $rules ){

	    $rules .= "RewriteRule ^".$this->id.".xml index.php\n";
	    $rules .= "RewriteRule ^".$this->id.".xml.gz index.php\n";

	    return $rules;
	}


	/*
		Инициализация	
	*/
	public function init(){


		add_filter('mod_rewrite_rules', array($this, 'add_htaccess_rule'));

		add_action( "added_post_meta", array($this, 'generateOffer'), 10, 2); 
		add_action( 'updated_postmeta', array($this, 'generateOffer'), 10, 2);
		add_action( 'wp_insert_post', array($this, 'wp_insert_post'), 1, 2);
		add_action( 'set_object_terms', array($this, 'set_object_terms'), 1);

		add_action( 'wp_ajax_'.$this->id.'_ajaxUpdateOffers', array( $this, 'ajaxUpdateOffers' ) );

		$this->shell();
		$this->getYmlAction();
	}


	public function getYmlAction(){

		if ( get_option('permalink_structure') != '' ) { 

			$url = parse_url( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

			if( $url['path'] == '/'.$this->id.'.xml' ){
				$this->getYml();
				die;
			}

			if( $url['path'] == '/'.$this->id.'.xml.gz' ){
				$this->getYml(true);
				die;
			}

		}else{

			if( isset( $_GET[$this->id.'_export'] ) ){

				$gzip = ( isset( $_GET['gzip'] ) ) ? true : false;
				$this->getYml($gzip);
				die;

			}

		}

	}


	public function bread( $text ){


		if( is_string( $text ) )
			$result =  $text."\n";

		elseif( is_array( $text ) or is_object( $text ) )
			$result = print_r($text, true)."\n";

		$this->bread[] = $result;		

		if( $this->debug )
			echo $result;
		
		
		if( date('j') == '1' ){
			unlink( dirname(__FILE__)."/".$this->id."_process.log" );
		}

		file_put_contents(dirname(__FILE__)."/".$this->id."_process.log", $result, FILE_APPEND );
			
	}

	/*
		Метод позволяет узнать запущен ли процесс выгрузки
	*/
	final public function inProcess(){

		$inProcess = get_option($this->id.'_in_process');

		if( empty( $inProcess ) ){
			update_option($this->id.'_in_process', 'no');
			return false;
		}

		if( $inProcess == 'no' )
			return false;
		else
			return true;

	}

	/*
		Позволяет установить статус процесса экспорта.
		Используется на странице настроек. Показывает статус выполнения.
	*/
	final public function inProcessSet( $set ){

		if( in_array( $set, array('yes','no' ) ) )
			update_option($this->id.'_in_process', $set);

	}


	/*
		Устанавливает страницу выгрузки
	*/
	final public function setPage( $page ){
		$this->currentpage = $page;
		update_option($this->id.'_page', $page );
	}


	/*
		Включает вывод отладочной информации
	*/
	public function debugOn(){
		$this->debug = true;
	}


	/*
		Выключает вывод отладочной информации
	*/
	public function debugOff(){
		$this->debug = false;
	}

	/*
		Блокирует выгрузку для того чтобы если случайно включится второй процесс выгрузки одновременно, 
		то второй процесс завершится при проверке методом isLock()
	*/
	final public function exportLock(){
		update_option($this->id .'_lock', true );
	}

	/*
		Разблокирует выгрузку, противоположность вышестоящему методу 
	*/
	final public function exportUnlock(){
		update_option($this->id .'_lock', false );
	}

	/*
		Проверяет заблокирована ли выгрузка
	*/
	final public function isLock(){
		return get_option( $this->id .'_lock' );
	}

	/*
		Выгружает используемые категории в YML формате
	*/
	final public function renderCats(){

		$get_terms = $this->getRelationsTax();


		if( !empty( $get_terms['product_cat'] ) )
			if( in_array('all', $get_terms['product_cat'] ) )
				$terms = get_terms( 'product_cat' );
			else
				$terms = get_terms( 'product_cat', array('include' => $get_terms['product_cat'] ) );
		else
			$terms = get_terms( 'product_cat' );	

		if( !empty( $terms ) ){

			$yml = '<categories>'."\n";

			foreach ( $terms as $key => $cat ) {

				$parent = ( $cat->parent ) ? 'parentId="'.$cat->parent.'"' : '';

				$yml .= "\t\t".'<category id="'.$cat->term_id.'" '.$parent.'>'.$cat->name.'</category>'."\n";
			}

			$yml .= '</categories>'."\n";
			
			$yml = apply_filters( $this->id.'_render_cats', $yml );

			return $yml;

		}
	}

	/*
		Выгружает валюту в YML формате
	*/
	final public function renderCurrency(){

		$yml = '<currency id="'.apply_filters( 'woocommerce_currency', get_option('woocommerce_currency') ).'" rate="1"/>';

		$yml = apply_filters( $this->id.'_render_currency', $yml );

		return $yml;
	}


	/*
		Получает свойства товара
	*/
	final public function getProductAttributes( $product ){


		$attributes = $product->get_attributes();
		$out_attr 	= '';

		foreach ( $attributes as $key => $attribute ){

			if (  $attribute['is_taxonomy'] && ! taxonomy_exists( $attribute['name'] )  ) 
				continue;

			$name = wc_attribute_label( $attribute['name'] );

			if ( $attribute['is_taxonomy'] ) {

				if ( $product->product_type == 'variation' && array_key_exists('attribute_'.$attribute['name'], $product->variation_data) ){

					$value = apply_filters( 'woocommerce_attribute', $product->variation_data['attribute_'.$attribute['name']]);

				}

				if ( $product->product_type != 'variation' || empty($value) ){
					$values = wc_get_product_terms( $product->id, $attribute['name'], array( 'fields' => 'names' ) );
					$value = apply_filters( 'woocommerce_attribute', wptexturize( implode( ', ', $values ) ) , $attribute, $values );
				}

			} else {

				if ( $product->product_type == 'variation' && array_key_exists('attribute_'.$attribute['name'], $product->variation_data) ){

					$value = apply_filters( 'woocommerce_attribute', $product->variation_data['attribute_'.$attribute['name']]);

				} else {
					// Convert pipes to commas and display values
					$values = array_map( 'trim', explode( WC_DELIMITER, $attribute['value'] ) );
					$value = apply_filters( 'woocommerce_attribute',  wptexturize( implode( ', ', $values ) ) , $attribute, $values );
				}
			}

			if( !empty( $value ) and !empty( $name ) ){
				$out_attr .= '<param name="'.$name.'">'.$value.'</param>'."\n";
			}

		}

		$out_attr = apply_filters( $this->id.'_export_attributes', $out_attr, $product, $attributes );

		return $out_attr;

	}

	/*
		Получает изображения товара
	*/
	final public function getImagesProduct( $product ){


		$images = array();

		if ($product->product_type == 'variation' && method_exists( $product, 'get_image_id'  ) ){
			$image_id = $product->get_image_id();
		} else {
			$image_id = get_post_thumbnail_id( $product->id );
		}

		$general_image = WooExportYmlFunctions::sanitize( wp_get_attachment_url( $image_id ) );



		if( !empty( $general_image ) )
			$images[] = $general_image;

		if( $this->isexporpictures ){

			$ids 	= $product->get_gallery_attachment_ids();		

			if( !empty( $ids ) ){

				foreach ($ids as $id) {

					$image = wp_get_attachment_image_src( $id , 'full');

					if( !empty( $image[0] ) )

					$images[] = WooExportYmlFunctions::sanitize( $image[0] );

				}

			}
		}

		return $images;

	}

	/*
		Установка параметров для выгрузки Offer
	*/
	final public function setOfferParams( $product ){

		$terms = wp_get_post_terms( $product->id, 'product_cat' );

		if( !empty( $terms ) )
			$cat  = $terms[0]->term_id;
		else{
			$this->bread('cat not set id='.$product->id );
			return false;
		}

		$excerpt 		= trim( $product->post->post_excerpt );
		$description 	= ( !empty( $excerpt  ) ) ? $excerpt : $product->post->post_content;
		$description 	= WooExportYmlFunctions::substr($description, 500, false );

		if( $this->vendors == false )
			$vendor = get_post_meta($product->id ,'_vendor', true );

		else{

			$terms = wp_get_post_terms( $product->id, $this->vendors );

			if( !is_wp_error( $terms ) )
			if( !empty( $terms[0] ) )
				$vendor  = $terms[0]->name;
			
		}

		if( empty( $vendor ) )
			$vendor = get_option( $this->id.'_def_vendor' );
		
		if( empty( $vendor ) )
			$vendor = 'none';


		$pictures = $this->getImagesProduct( $product );

		if( empty( $pictures ) )
			return false;



		$params = array(
			'url' 			=> WooExportYmlFunctions::sanitize(urldecode( esc_attr($product->get_permalink()) )),
			'price' 		=> $product->get_price(),
			'currencyId'	=> 'RUR',
			'categoryId' 	=> $cat,
			'picture' 		=> $pictures,
			'store'			=> ( $this->isdeliver and !$this->cpa  ) ? $this->isstore : '',
			'pickup'		=> ( $this->isdeliver and !$this->cpa  ) ? $this->ispickup : '',
			'delivery'		=> ( $this->isdeliver and !$this->cpa  ) ? 'true' : '',
			'vendor'		=> $vendor,
			'model' 		=> WooExportYmlFunctions::del_symvol( strip_tags( $product->post->post_title ) ),
			'description' 	=> WooExportYmlFunctions::del_symvol( strip_tags( $description ) ),
			'sales_notes'	=> ( !empty( $this->salesNote ) ) ? WooExportYmlFunctions::substr( $this->salesNote, 50, false ) : '',
			'cpa' 			=> ( $this->cpa ) ? $this->cpa : '',
		);


		$params = apply_filters( $this->id.'_set_offer_params', $params, $product );

		if( empty( $params['vendor'] ) ){
			$this->bread('vendor not set id='.$product->id );
			return false;
		}

		if( empty( $params['model'] ) ){
			$this->bread('model not set id='.$product->id );
			return false;
		}


		if( $params['price'] == 0 )
			return false;


		// на всякий случай, если кто то переопределил через фильтр.
		$params['sales_notes'] = WooExportYmlFunctions::substr( $params['sales_notes'], 50, false );

		return $params;
	}


	/*
		Выгружает часть товаров. Часть это кол-во товаров на странице, определяется параметром $perpage в makeQuery
	*/
	final public function renderPartOffers(){

		$products = $this->makeQuery();

		if( $products->post_count == $products->found_posts )
			$this->isMakeYml = true;

		if( $products->have_posts() ){

			$this->bread('found posts');

			while ( $products->have_posts() ){

				$products->the_post();
				$product = get_product($products->post->ID);
				
				if ( $product->product_type == 'simple' ||  $product->product_type == 'variation'){

					//не нужно включать вариации, у которых нет отличий от основного товара и других вариаций
					if ($product->product_type == 'variation'){
					
						if (!$this->checkVariationUniqueness($product)){
				
							delete_post_meta($product->variation_id, $this->id.'_yml_offer');
							$this->bread('WARNING: skipping product variation ID '.$product->variation_id.' (product ID '.$product->id.') — variation has no unique attributes');
							continue;
				
						}
					
					}
				
					$this->renderPartOffer( $product );
				
				}

			}
			
			wp_reset_postdata();

			$this->setPage( $this->currentpage+1 );

		}else{
			$this->bread( 'no have posts' );
			$this->isMakeYml = true;
		}
		
		
		
	}

	final public function renderPartOffer( $product ){

		$param = $this->setOfferParams( $product );

		if ($product->product_type == 'variation'){
			$product_id = $product->variation_id;
		}else
			$product_id = $product->id;


		if( !empty( $param ) ){

			$available  = ( $product->is_in_stock() == 'instock' ) ? "true" : "false";
			$available 	= apply_filters( $this->id.'_set_offer_param_available', $available, $product );

			if( $this->isbid == true)
				$bid = ( $this->bid ) ? 'bid="'.$this->bid.'"' : '';
			else
				$bid = "";

			$offer .= '<offer id="'.$product_id.'" type="vendor.model" available="'.$available.'" '.$bid;

			if ($product->product_type == 'variation' && $this->isgroupidattr && isset($product->parent->id) )
				$offer.= ' group_id="' . $product->parent->id . '"';
			
			
			$offer .= '>'."\n";

			foreach ($param as $key => $value){

				if( !empty( $value ) ){

					if( is_array( $value ) ){
					
						foreach ($value as $values) {
							$offer .= "<$key>".$values."</$key>\n";
						}
					
					}else{
						$offer .= "<$key>".$value."</$key>\n";
					}

				}
			
			}

			if( $this->isexportattr ){
				$offer .= $this->getProductAttributes( $product );
			}

			$offer .= '</offer>'."\n";

			if( !empty( $offer ) ){

				$md5offer = md5( $offer );

				if( !in_array( $md5offer, $this->md5offer ) ){



					$this->md5offer[] = $md5offer;
					
					update_post_meta( $product_id, $this->id.'_yml_offer', $offer );

					return true;
				
				}
			}else{
				update_post_meta( $product_id, $this->id.'_yml_offer', '' );
				return false;
			}

		}else{
			update_post_meta( $product_id, $this->id.'_yml_offer', '' );
			return false;
		}	
	}

	/*
		Проверяет, отличается ли вариация товара от остальных
	*/
	final public function checkVariationUniqueness($variation){

		$product = get_product( $variation->id );
		
		if ( ! is_object($product) || !($product instanceof WC_Product_Variable) )
			return false;

		if (method_exists($product, 'get_children'))
			$children = $product->get_children();
		else 
			return false;

		$differs 		= false;
		$pairs_differ 	= array();
		
		foreach ($children as $_id){

			$_variation = get_product($_id);

			if ( $_variation->variation_id == $variation->variation_id )
				continue;

		
			$pair_differs = false;
		
			foreach ( $variation->variation_data as $attr => $value ){
				
				foreach ( $_variation->variation_data as $attr_compare => $value_compare ){
			
					if ( $attr === $attr_compare && $value !== $value_compare ){
						$pair_differs = true;
						break;
					}
			
				} 	
			
				if ( $pair_differs )
					break;
			
			}
			
			$pairs_differ[] = $pair_differs;

		}

		$differs = in_array(false, $pairs_differ) ? false : true;

		return $differs;

	}

	final public function getShellArg(){

		/* В версии php ниже 5.3 не работают ключи с длинным именем, поэтому просто забиваем на эту версию */
		$shell_arg = @getopt("",array( "wooexportyml_".$this->shellPrefix."::","debug::","unlock::",'fullexport::',"unittests::"));

		if( empty( $shell_arg ) )
			$shell_arg = array();
		else
			$shell_arg = array_keys( $shell_arg );
		

		return $shell_arg;
	}

	/*
		Интерфейс для shell оболочки
		Ключи:
		--wooexportyml - Основной и обязательный, без него выгрузка не будет работать.
		--debug        - вывод отладочной информации
		--unlock       - разблокирует выгрузку если произошел сбой во время процесса и выгрузка будет начата сначала при следующем запуске
		--fullexport   - Экспорт без учета времени последней выгрузки, а так же будет произведена выгрузка сразу всех товаров, а не партиями
	*/
	public function shell(){

		global $wpdb;

		$shell_arg = $this->getShellArg();


		if( in_array('wooexportyml_'.$this->shellPrefix, $shell_arg  ) ){


			if( in_array('unlock', $shell_arg ) ){
				$this->action_unlock();
				die;
			}

			if( in_array('debug', $shell_arg ) ){
				$this->debugOn();
			}

			if( in_array( 'unittests', $shell_arg ) ){

				$this->unitTests->process();

				die;
			}


			$this->action_fullexport();
			die;
		}
	}

	/*
		Действие, которые разблокирует выгрузку
	*/
	public function action_unlock(){
		
		$this->inProcessSet( 'no' );
		$this->setPage( 1 );
		$this->exportUnlock();

	}

	/*
		Экспорт без учета времени последней выгрузки, а так же будет произведена выгрузка сразу всех товаров, а не партиями
	*/
	public function action_fullexport(){
		
		$this->inProcessSet( 'no' );
		$this->setPage( 1 );
		$this->exportUnlock();

		while ( !$this->isMakeYml )
			$this->export();

	}

	/*
		Основная функция экспорта, содержит основную логику экспорта
	*/
	public function export(){


		if( !$this->isLock() ){

			$this->exportLock();

			if(  $this->inProcess() ){

				$this->bread('in process');
			
				$this->renderPartOffers();
			
			}else{

				$this->bread('not in process');
			

				$this->bread('check time true');

				$this->inProcessSet('yes');
				$this->renderPartOffers();
			
			}

			if( $this->isMakeYml ){

				$this->bread('is ismakeyml true');
			
				$this->inProcessSet('no');
				$this->setPage(1);
			}

			$this->exportUnlock();
		}else{
			$this->bread('process is lock');
		}
	}


	/*
		Получает заголовки YML фаила
	*/
	final public function renderHead( $arg ){

		extract( $arg );

		echo '<?xml version="1.0" encoding="utf-8"?>

		<!DOCTYPE yml_catalog SYSTEM "shops.dtd">
		<yml_catalog date="'.date("Y-m-d H:i").'">
			<shop>
				<name>'.$name.'</name>
				<company>'.$desc.'</company>
				<url>'.$siteurl.'</url>
				<currencies>
					'.$this->renderCurrency().'
				</currencies>
				'.$this->renderCats().'
				<offers>
		';

	}



	/*
		Закрывает YML фаил
	*/
	final public function renderFooter(){
		
		echo '
				</offers>
			</shop>
		</yml_catalog>
		';

	}

	final public function renderOffers(){
		
		global $wpdb;

		$ids = $this->getIdsForExport();
		$ids = implode(',',$ids->posts);

		$offers = $wpdb->get_results("select DISTINCT meta_value, post_id from {$wpdb->prefix}postmeta where meta_key='".$this->id."_yml_offer' and post_id in ($ids)");
		
		foreach ($offers as $offer ) 
			echo apply_filters($this->id.'_renderOffers',  $offer->meta_value, $offer->post_id );
		
	}


	/*
		Отдает Yml
	*/
	final public function getYml($gzip = false){

		if( $gzip ){

			header('content-type: application/gzip');
			ob_start();
		
		}else
			header ("Content-Type:text/xml; charset=utf-8");
		


		$arg = array(
			'name' 		=> ( get_option($this->id.'_title') ) ? get_option($this->id.'_title') : get_option('blogname'),
			'desc' 		=> ( get_option($this->id.'_desc') ) ? get_option($this->id.'_desc') : get_option('blogdescription'),
			'siteurl' 	=> esc_attr(site_url()),
			'this' 		=> $this,
		);

		$arg = apply_filters( $this->id.'_make_yml_arg', $arg );
		
		$this->renderHead( $arg );
		$this->renderOffers( $parts );
		$this->renderFooter();

		if( $gzip )
			WooExportYmlFunctions::print_gzencode_output( $this->id.'.xml.gz');

	}


	final public function getIdsForExport(){

		$this->bread('Generate ids');

		$args = array(
			'posts_per_page' 	=> -1, 
			'post_status' 		=> 'publish',
			'post_type' 		=> array('product', 'product_variation'),
			'fields' 			=> 'ids',
			'tax_query' => array(
				array(
					'taxonomy' => 'product_type',
					'field' => 'slug',
					'terms' => array('simple', 'product_variation'),
				)
			),
			'meta_query' => array(
				array(
					'key' => '_price',
					'value' => '0',
					'compare' => '>',
				),
			)
		);


		$relations = $this->getRelationsTax();

		foreach ($relations as $tax => $terms) {

			if( !empty( $terms ) ){

				if( !in_array( 'all', $terms ) )
					$args['tax_query'][] = array(
						'taxonomy' => $tax,
						'field' => 'term_id',
						'terms' => $terms
					);

				else if( $tax == 'product_cat' and in_array( 'all', $terms ) ){

					$get_terms = get_terms( $tax );
					$terms = array();

					foreach( $get_terms as $term )
						$terms[] = $term->term_id;

					$args['tax_query'][] = array(
						'taxonomy' => $tax,
						'field' => 'term_id',
						'terms' => $terms
					);					
				}

			}
		}



		$args = apply_filters( $this->id . '_make_query_get_ids', $args );
		$products_ids  = new WP_Query( $args );

		$variations_ids = $this->getVariationsIds();

		$ids = new WP_Query();
		$ids->posts = array_merge( $products_ids->posts, $variations_ids->posts );
		$ids->post_count = $products_ids->post_count + $variations_ids->post_count;

		return $ids;
	}

	final public function getVariationsIds(){

		$args = array(
			'posts_per_page' 	=> -1, 
			'post_status' 		=> 'publish',
			'post_type' 		=> array('product_variation'),
			'fields' 			=> 'ids',
			'meta_query' => array(
				array(
					'key' => '_price',
					'value' => '0',
					'compare' => '>',
				),
			)
		);

		return new WP_Query($args);
	}

	/*
		Создает запрос для выгрузки
	*/
	final public function makeQuery(){

		if( $this->currentpage == 1 ){

			$ids = $this->getIdsForExport();
			$this->posts = $ids->posts;
			update_option($this->id.'_get_ids', $this->posts );
			

		}


		$this->bread( 'Current page - ' . $this->currentpage );

		$shell_arg = $this->getShellArg();

		$perpage = ( in_array( 'wooexportyml', $shell_arg ) ) ? 500 : 150;

		$args = array(
			'post__in' 			=> (array)$this->posts,
			'posts_per_page' 	=> $perpage, 
			'paged' 			=> $this->currentpage,
			'post_type' 		=> array('product', 'product_variation'),
		);

		// Когда всего 200 товаров, нет смысла выгружать партиями.
		if( (int)$get_ids->found_posts >= 200 ){
			$args['posts_per_page'] == 200;
		}

		$args = apply_filters( $this->id . '_make_query_get_products', $args );

		$query = new WP_Query( $args );
		update_option( $this->id . '_pages', $query->max_num_pages );

		return $query;
		
	}


	/*
		Получает список таксономий
	*/
	final public function getRelationsTax(){
		
		$tax = get_taxonomies( array('object_type' => array('product') ), 'objects' );

		$relations = array();

		foreach ($tax as $key => $tax_val) {

			if( $key == 'product_type' )
				continue;

			if( strripos($key, 'pa_') !== false )
				continue;

			$relations[$key] = get_option($this->id.'_tax_'.$key );


		}

		if( !isset( $relations['product_cat'] ) )
			$relations['product_cat'] = array();



		$options = get_option( $this->id.'_filters' );

		if( !empty( $options ) ){
			foreach ($options as $key => $value) {
				if( in_array('notfiltered', $value) )
					continue;

				$relations[$key] = $value;
			}
		}

		return $relations;

	}





	public function ajaxUpdateOffers(){

		if( $_POST['unlock'] == 'yes' ){
			$this->action_unlock();
		}
		
		$this->export();

		echo json_encode(array( 'ismakeyml' => $this->isMakeYml, 'bread' => $this->bread ));
		die;
	}

	final public function generateOffer( $meta_id, $post_id ){

		$product = get_product ( $post_id );
		$this->renderPartOffer( $product );
	}

	final public function wp_insert_post( $post_id, $post ){

		if( $post->post_type == 'product'){

			$product = get_product( $post_id );

			$this->renderPartOffer( $product );
		}

	}

	final public function set_object_terms( $post_id ){

		$post = get_post( $post_id );

		if( $post->post_type == 'product' ){

			$product = get_product( $post_id );

			$this->renderPartOffer( $product );
		}

	}

}
