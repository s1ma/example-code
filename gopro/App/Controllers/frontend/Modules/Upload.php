<?php
use Frame\Core\EntityManager;
use Frame\Core\Config;

use App\Controllers\frontend\Modules\AppController as AppController;

final class moduleUpload extends AppController
{	
	public function executeFace()
	{
		if($this->request->isPost())
		{
			if(!is_null($this->request->post('youtube_link')) && $this->request->post('ac') == 'getInfo')
			{
				return $this->getInfoFromYouTube();
			} elseif($this->request->post('ac') == 'download' && !is_null($this->request->post('video_id'))) {
				return $this->downloadFromYouTube();
			}
			
			if($this->request->post('item_id'))
			{
				$item = EntityManager::findOneBy('Items', array('item_id' => $this->request->post('item_id'), 'user_id' => $this->security->getUser()->getId()));
				
				if(!$item)
				{
					throw new Exception('Not found item.');
				}
				
				$item->setCategory($this->request->post('category'));
				$item->setLocation($this->request->post('location'));
				$item->setTags($this->request->post('tags'));
				$item->setPreview($this->request->post('preview_id'));
				
				return $this->render('frontend/Pages/upload_video/success.tpl', array('item_id' => $this->request->post('item_id')));
				
			}
			
			if(!empty($_FILES['item']['name']))
			{
				$item = EntityManager::create('Items');
				$data = $item->uploadFromPost('item'); 
				
				if(isset($data['error']))
				{
					return $this->jsonResponse(array('success' => false, 'error' => $data['error']));
				}
				
				$prev_url = array();
				
				switch(true)
				{
					case (in_array($data['mime'], Config::get('project', 'item', 'validateFileMimetype', 'images'))):
						$item->type = 'image';
						$item->image_id = $item->uploadImageFromPath($data['path'].$data['name']);
						$item->size = $data['size'];
					break;
					case (in_array($data['mime'], Config::get('project', 'item', 'validateFileMimetype', 'videos'))):
						$item->type = 'video';
						$video = $item->uploadVideoFromPath($data['path'].$data['name']);
						$item->video_id = $video->video_id;
						$item->size = $data['size'];
						
						$previews = $video->getImagesToPrview();

						foreach($previews as $prev)
						{
							$image = EntityManager::findOneBy('Images', array('image_id' => $prev));
							if($image)
							{
								$prev_url[] = array('image_id' => $prev, 'url' => $image->getCropImageLink(153, 85));
							}
							
						}
						
						$item->image_id = array_shift($previews);
					break;
				}
				
				$item->user_id = $this->security->getUser()->getId();
				$item->dt_update = time();
				
				$item->item_id = $item->save();
				
				return $this->jsonResponse(array('success' => true, 'item_id' => $item->item_id, 'prev_urls' => $prev_url, 'is_video' => is_numeric($item->video_id)));
			}
		}

		$categories = EntityManager::find('Categories', array('top' => 0))->fetch();

		return $this->render('frontend/Pages/upload_video/step1.tpl', array('categories' => $categories));		
	}
	
	
	public function getInfoFromYouTube()
	{
	
		$url = parse_url($this->request->post('youtube_link'));
		parse_str($url['query'], $query_array);

		if(!isset($query_array['v']))
		{
			return $this->jsonResponse(array('success' => false, 'error' => 'Link fail!'));
		}
		
		$my_video_info = 'http://www.youtube.com/get_video_info?&video_id='.$query_array['v'].'&asv=3&el=detailpage&hl=en_US';
		$my_video_info = $this->curlGet($my_video_info);
		
		parse_str($my_video_info, $data);
		if(is_null($data['title']) || is_null($data['thumbnail_url'] || is_null($data['video_id'])))
		{
			return $this->jsonResponse(array('success' => false, 'error' => "Don't parse data"));
		}

		return $this->jsonResponse(array('success' => true, 'title' => $data['title'],'video_id' => $data['video_id'], 'thumbnail_url' => $data['thumbnail_url']));
	}
	
	public function downloadFromYouTube()
	{

		$my_video_info = 'http://www.youtube.com/get_video_info?&video_id='.$this->request->post('video_id').'&asv=3&el=detailpage&hl=en_US';
		$my_video_info = $this->curlGet($my_video_info);
		
		parse_str($my_video_info, $data);

		if(!isset($data['url_encoded_fmt_stream_map']))
		{
			return $this->jsonResponse(array('success' => false, 'error' => "Don't parse data"));
		}
		
		$my_formats_array = explode(',',$data['url_encoded_fmt_stream_map']);
		
		if(!isset($my_formats_array))
		{
			return $this->jsonResponse(array('success' => false, 'error' => "Don't parse data"));
		}
		
		$video_data = array();
		
		foreach($my_formats_array as $format)
		{
			parse_str($format, $format_array);
			
			switch(true)
			{
				case is_numeric(stripos($format_array['type'], 'video/mp4')):
					if(isset($video_data['mp4']))
					{
						if($format_array['quality'] == 'medium' && $video_data['mp4']['quality'] != 'medium')
						{
							$video_data['mp4']['quality'] = $format_array['quality'];
							$video_data['mp4']['url'] = $format_array['url'];
						}
					} else {
						$video_data['mp4']['quality'] = $format_array['quality'];
						$video_data['mp4']['url'] = $format_array['url'];
					}
				break;
				case is_numeric(stripos($format_array['type'], 'video/webm')):
					if(isset($video_data['webm']))
					{
						if($format_array['quality'] == 'medium' && $video_data['webm']['quality'] != 'medium')
						{
							$video_data['webm']['quality'] = $format_array['quality'];
							$video_data['webm']['url'] = $format_array['url'];
						}
					} else {
						$video_data['webm']['quality'] = $format_array['quality'];
						$video_data['webm']['url'] = $format_array['url'];
					}
				break;
			}
		}
		
		if(empty($video_data))
		{
			return $this->jsonResponse(array('success' => false, 'error' => "Don't found formats"));	
		}
		
		$video_url = isset($video_data['mp4']) ? $video_data['mp4']['url'] : array_pop($video_data)['url'];
		
		$item = EntityManager::create('Items');
		$data = $item->uploadFromUrl($video_url, $data['title']); 
		
		if(!$data)
		{
			return $this->jsonResponse(array('success' => false, 'error' => "Don't upload"));	
		}

		$item->type = 'video';
		$video = $item->uploadVideoFromPath($data['path'].$data['name']);
		$item->video_id = $video->get('video_id');
		$item->size = $data['size'];

		$item->user_id = $this->security->getUser()->getId();
		$item->dt_update = time();
		
		$previews = $video->getImagesToPrview();
	
		foreach($previews as $prev)
		{
			$image = EntityManager::findOneBy('Images', array('image_id' => $prev));
			if($image)
			{
				$prev_url[] = array('image_id' => $prev, 'url' => $image->getCropImageLink(153, 85));
			}
		}
		
		$item->image_id = array_shift($previews);
		$item->item_id = $item->save();

		return $this->jsonResponse(array('success' => true, 'size' => $data['size'],'prev_urls' => $prev_url, 'item_id' => $item->item_id));
	}
	
	public function curlGet($URL) {
	    $ch = curl_init();
	    $timeout = 3;
	    curl_setopt( $ch , CURLOPT_URL , $URL );
	    curl_setopt( $ch , CURLOPT_RETURNTRANSFER , 1 );
	    curl_setopt( $ch , CURLOPT_CONNECTTIMEOUT , $timeout );

	    $tmp = curl_exec( $ch );
	    curl_close( $ch );
	    return $tmp;
	}  
}