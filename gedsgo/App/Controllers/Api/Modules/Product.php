<?php

use Frame\Core\EntityManager;
use Frame\Core\Router;
use Frame\Library\Session;

use App\Controllers\Api\Modules\ApiController as ApiController;


final class moduleProduct extends ApiController
{
	
	public function executeGetCity() 
	{
		$city_records = EntityManager::find('City');
		foreach($city_records->fetch() as $city) 
		{
			$response['rows'][] = array('city_id' => $city->city_id,'city_name' => $city->city_name);
		}
		if(!empty($response['rows']))
		{
			$response['success'] = true;
		} else {
			$response['success'] = false;
			$response['error'] = 'Not found city.';
		}
		
		return $this->responseJson($response);
	}
	
	
	public function executeGetCategory() 
	{
		if( !$this->request->get('city_id') or !is_numeric($this->request->get('city_id'))) {
			$response['success'] = false;
			$response['error'] = 'City not correct.';
			return $this->responseJson($response);
		}
		
		$category_records = array();
		$city_id = $this->request->get('city_id');

		$category_records = EntityManager::find('Place');
		$category_records->select('DISTINCT `category`.`category_id`, `category`.`category_name`, `category`.`category_color`');
		$category_records->leftJoin('`product_records`','`product_records`.`place_id` = `place`.`place_id`');
		$category_records->leftJoin('`product`','`product_records`.`product_id` = `product`.`product_id`');
		$category_records->leftJoin('`category`','`product`.`category_id` = `category`.`category_id`');
		$category_records->andWhere("`place`.`city_id` = '$city_id'");
		$category_records = $category_records->fetch();
		
		foreach($category_records as $category) 
		{
			$response['rows'][] = array('category_id' => $category->category_id,'category_name' => $category->category_name,'category_color' => $category->category_color);
		}
		if(!empty($response['rows']))
		{
			$response['success'] = true;
		} else {
			$response['success'] = false;
			$response['error'] = 'Not found category.';
		}
		
		return $this->responseJson($response);
	}
	
	
	public function executeGetProduct() 
	{
		session_start();

		function knuth_shuffle(&$arr){
			for($i=count($arr)-1;$i>0;$i--){
				$rnd = mt_rand(0,$i);
				list($arr[$i], $arr[$rnd]) = array($arr[$rnd], $arr[$i]);
			}
		}
		
		if( !$this->request->get('city_id') or !is_numeric($this->request->get('city_id')) ) 
		{
			$response['success'] = false;
			$response['error'] = 'City not correct.';
			return $this->responseJson($response);
		}
		
		if( $this->request->get('limit') &&  !is_numeric($this->request->get('limit')))
		{
			$response['success'] = false;
			$response['error'] = 'Limit not correct.';
			return $this->responseJson($response);
		}
		
		if( $this->request->get('start') &&  !is_numeric($this->request->get('start')))
		{
			$response['success'] = false;
			$response['error'] = 'Start not correct.';
			return $this->responseJson($response);
		}
		
		$top_city = !$this->request->get('category_id') ? true : false;
		$top_category = $this->request->get('category_id') && !$this->request->get('product_name') ? true : false;
		
		$response['limit'] = $this->request->get('limit') ? $this->request->get('limit') : 30;
		$response['start'] = $this->request->get('start') ? $this->request->get('start') : 0;
		
		$product_records = EntityManager::find('Place');
		$product_records->select('
									SQL_CALC_FOUND_ROWS
									`product`.`product_name`,
									`category`.`category_color`,
									`product_records`.`id`,
									`product_records`.`product_description`,
									`product_records`.`product_purchase`,
									`product_records`.`product_price`,
									`product_records`.`top_priority`,
									`product_records`.`product_photo`,
									`place`.`place_id`,
									`place`.`place_name`,
									`place`.`place_description`,
									`place`.`place_address`,
									`place`.`place_phone`,
									`place`.`place_working_time`,
									`place`.`place_lan`,
									`place`.`place_lat`,
									`place`.`place_logo`,
									sum(`comment`.`rating`) as `comment_sum_rating`,
									count(`comment`.`rating`) as `comment_rating_count`,
									count(`comment`) as `comment_count`
								');
		$product_records->leftJoin('`product_records`','`product_records`.`place_id` = `place`.`place_id`');
		$product_records->leftJoin('`product`','`product`.`product_id` = `product_records`.`product_id`');
		$product_records->leftJoin('`category`','`category`.`category_id` = `product`.`category_id`');
		$product_records->leftJoin('`comment`','`comment`.`product_records_id` = `product_records`.`id`');
		
		
		if($top_city) 
		{
			$top = 'city'.$this->request->get('city_id');
			$product_records->andWhere("`product_records`.`product_top_city` = '1'");
		}
		
		
		if($top_category) {
		
			
			if(!is_numeric($this->request->get('category_id')) ) 
			{
				$response['success'] = false;
				$response['error'] = 'Category not correct.';
				return $this->responseJson($response);
			}
			$top = 'city'.$this->request->get('city_id').'category'.$this->request->get('category_id');
					
			$category_id = $this->request->get('category_id');

			$product_records->andWhere("`product`.`category_id` = '$category_id'");
			$product_records->andWhere("`product_records`.`product_top_category` = '1'");
		}
		
		if($this->request->get('product_name')) {
		
			$top = false;
		
			$category_id = $this->request->get('category_id');
			$product_name = $this->request->get('product_name');
			
			$product_records->andWhere("`product`.`category_id` = '$category_id'");
			$product_records->andWhere("`product`.`product_name` like '%$product_name%'");
		}
		
		$city_id = $this->request->get('city_id');
		$product_records->andWhere("`place`.`city_id` = '$city_id'");

		$product_records->groupBy('`product_records`.`id`');
		$product_records->orderBy('`product_records`.`product_price`', 'ASC');
		
		if(!$top)
		{
			$product_records->start(intval($response['start']));
			$product_records->limit(intval($response['limit']));
		}
		
		if(Session::getInstance()->get($top))
		{
			$response['rows'] = Session::getInstance()->get($top);
		} else {
			$product_records = $product_records->fetch();
			$product_records_count = EntityManager::find('Product');
			$product_records_count->select('FOUND_ROWS() as count #');
			$product_records_count = $product_records_count->fetch();
			$product_records_count = $product_records_count[0]->count;
	
			$response['count'] = $product_records_count;
			$response['rows'] = array();
			
			$url_image = Router::getInstance()->getUrl('files');
			foreach($product_records as $product_record) 
			{
				$product_photo = null;
				if(is_numeric($product_record->product_photo))
				{
					$image = EntityManager::findOneBy('Image', array('image_id' => $product_record->product_photo));
					$product_photo = $url_image.$image->image_name;
				}
				
				$place_logo = null;
				if(is_numeric($product_record->place_logo))
				{
					$image = EntityManager::findOneBy('Image', array('image_id' => $product_record->place_logo));
					$place_logo = $url_image.$image->image_name;
				}
			
				$response['rows'][] = array(
											'product_id' => $product_record->id,
											'category_color' => $product_record->category_color,
											'product_name' => $product_record->product_name,
											'product_description' => $product_record->product_description,
											'product_purchase' => $product_record->product_purchase,
											'product_price' => $product_record->product_price,
											'top_priority' => $product_record->top_priority,
											'product_photo' => $product_photo,
											'place_id' => $product_record->place_id,
											'place_name' => $product_record->place_name,
											'place_description' => $product_record->place_description,
											'place_address' => $product_record->place_address,
											'place_phone' => $product_record->place_phone,
											'place_working_time' => $product_record->place_working_time,
											'place_lan' => $product_record->place_lan,
											'place_lat' => $product_record->place_lat,
											'place_logo' => $place_logo,
											'comment_sum_rating' => $product_record->comment_sum_rating,
											'comment_rating_count' => $product_record->comment_rating_count,
											'comment_count' => $product_record->comment_count
											);
			}
			
			if($top)
			{
				knuth_shuffle($response['rows']);
				$tmp = Array();
				foreach($response['rows'] as &$ma)
				{   
				    $tmp[] = &$ma["top_priority"];
				}     
				    
				array_multisort($tmp, $response['rows'],SORT_NUMERIC, SORT_DESC); 

				Session::getInstance()->set($top, $response['rows']);	
			}

		}
		
		if($top)
		{
			$response['count'] = count($response['rows']);
			$response['rows'] = array_slice($response['rows'], $response['start'], $response['limit']);
			
			
		}
				
		if(!empty($response['rows']))
		{
			$response['success'] = true;
		} else {
			$response['success'] = false;
			$response['error'] = 'Not found product.';
		}

		return $this->responseJson($response);
	}


	public function executeGetStaticProduct() 
	{
		if( !$this->request->get('product_id')  && !$this->request->get('place_id')) 
		{
			$response['success'] = false;
			$response['error'] = 'Product id not correct.';
			return $this->responseJson($response);
		}
		
		if( $this->request->get('limit') &&  !is_numeric($this->request->get('limit')))
		{
			$response['success'] = false;
			$response['error'] = 'Limit not correct.';
			return $this->responseJson($response);
		}
		
		if( $this->request->get('start') &&  !is_numeric($this->request->get('start')))
		{
			$response['success'] = false;
			$response['error'] = 'Start not correct.';
			return $this->responseJson($response);
		}
		
		$response['limit'] = $this->request->get('limit') ? $this->request->get('limit') : 30;
		$response['start'] = $this->request->get('start') ? $this->request->get('start') : 0;
		
		
		$product_records = EntityManager::find('Place');
		$product_records->select('
									SQL_CALC_FOUND_ROWS
									`product`.`product_name`,
									`product_records`.`id`,
									`product_records`.`product_description`,
									`product_records`.`product_purchase`,
									`product_records`.`product_price`,
									`product_records`.`top_priority`,
									`product_records`.`product_photo`,
									`place`.`place_name`,
									`place`.`place_id`,
									`place`.`place_description`,
									`place`.`place_address`,
									`place`.`place_phone`,
									`place`.`place_working_time`,
									`place`.`place_lan`,
									`place`.`place_lat`,
									`place`.`place_logo`,
									sum(`comment`.`rating`) as `comment_sum_rating`,
									count(`comment`.`rating`) as `comment_rating_count`,
									count(`comment`) as `comment_count`
								');
		$product_records->leftJoin('`product_records`','`product_records`.`place_id` = `place`.`place_id`');
		$product_records->leftJoin('`product`','`product`.`product_id` = `product_records`.`product_id`');
		$product_records->leftJoin('`comment`','`comment`.`product_records_id` = `product_records`.`id`');
		
		if( $this->request->get('place_id') ) 
		{
			$product_records->andWhere("`place`.`place_id` = {$this->request->get('place_id')}");
			
		} else 
		{
			$products_ids = explode(',', $this->request->get('product_id'));
			foreach($products_ids as $products_id)
			{
				$products_ids_true[] = intval($products_id);
				
			}
			$product_records->andWhere("`product_records`.`id` IN (".implode(',',$products_ids_true).")");
		}
		
		$product_records->start($response['start']);
		$product_records->limit($response['limit']);
		
		$product_records->groupBy('`product_records`.`id`');
		$product_records->orderBy('`product_records`.`id`', 'DESC');
		
		$product_records = $product_records->fetch();
		$product_records_count = EntityManager::find('Product');
		$product_records_count->select('FOUND_ROWS() as count #');
		$product_records_count = $product_records_count->fetch();
		$product_records_count = $product_records_count[0]->count;

		$response['count'] = $product_records_count;
		$response['rows'] = array();
		
		$url_image = Router::getInstance()->getUrl('files');
		foreach($product_records as $product_record) 
		{
			
			$product_photo = null;
			if(is_numeric($product_record->product_photo))
			{
				$image = EntityManager::findOneBy('Image', array('image_id' => $product_record->product_photo));
				$product_photo = $url_image.$image->image_name;
			}
			
			$place_logo = null;
			if(is_numeric($product_record->place_logo))
			{
				$image = EntityManager::findOneBy('Image', array('image_id' => $product_record->place_logo));
				$place_logo = $url_image.$image->image_name;
			}
		
			$response['rows'][] = array(
										'product_id' => $product_record->id,
										'product_name' => $product_record->product_name,
										'product_description' => $product_record->product_description,
										'product_purchase' => $product_record->product_purchase,
										'product_price' => $product_record->product_price,
										'top_priority' => $product_record->top_priority,
										'product_photo' => $product_photo,
										'place_id' => $product_record->place_id,
										'place_name' => $product_record->place_name,
										'place_description' => $product_record->place_description,
										'place_address' => $product_record->place_address,
										'place_phone' => $product_record->place_phone,
										'place_working_time' => $product_record->place_working_time,
										'place_lan' => $product_record->place_lan,
										'place_lat' => $product_record->place_lat,
										'place_logo' => $place_logo,
										'comment_sum_rating' => $product_record->comment_sum_rating,
										'comment_rating_count' => $product_record->comment_rating_count,
										'comment_count' => $product_record->comment_count
										);
			
		}	
		if(!empty($response['rows']))
		{
			$response['success'] = true;
		} else {
			$response['success'] = false;
			$response['error'] = 'Not found product.';
		}
		
		return $this->responseJson($response);
	}
	
	
	public function executeGetSuggest() 
	{

		if( !$this->request->get('q') ) {
			$response['success'] = false;
			$response['error'] = 'Query is empty.';
			return $this->responseJson($response);
		}
		
		$response['start'] = $this->request->get('start') && is_numeric($this->request->get('start')) ? $this->request->get('start') : 0; 
		$response['limit'] = $this->request->get('limit') && is_numeric($this->request->get('limit')) ? $this->request->get('limit') : 10; 
		
		$suggest_records = EntityManager::find('Product');
		$suggest_records->select('SQL_CALC_FOUND_ROWS `product_name`');
		$suggest_records->rightJoin('`product_records`','`product_records`.`product_id` = `product`.`product_id`');
		$suggest_records->start($response['start']);
		$suggest_records->limit($response['limit']);
		$suggest_records->andWhere("`product_name` like '%".$this->request->get('q')."%'");
		
		$response['success'] = true;
		$rows = $suggest_records->fetch();

		foreach($rows as $row)
		{
			$response['rows'][] = array('product_name' => $row->product_name);
		}
		
		$suggest_records_count = EntityManager::find('Product');
		$suggest_records_count->select('FOUND_ROWS() as count #');
		$suggest_records_count = $suggest_records_count->fetch();
		$response['count'] = $suggest_records_count[0]->count;
		
		return $this->responseJson($response);
	}
	
}