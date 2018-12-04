<?php
namespace frontend\models;

use yii\db\ActiveRecord;

class Images extends ActiveRecord
{
	//Static functions
	
	public static function tableName()
	{
		return 'images';
	}
	

	//Dynamic functions
	public function delete()
	{
		$server = \Yii::$app->params['servers']['local'];
		exec('rm -r '.$server['path'].$this->path);
		return parent::delete();
	}
	 
	public function getCropUrl($width = 50, $height = null, $blur = 0)
	{
		$height = is_null($height) ? $width : $height;
		
		$new_name = preg_replace('/(\.[a-z1-9]+)$/u', "_crop_w{$width}_h{$height}$1", $this->file_name);
		$server = \Yii::$app->params['servers']['local'];
		
		$server_path  = $server['path'];
		$server_path .= $this->path;
		
		if(!file_exists($server_path.$new_name))
		{
			$image = new \Imagick($server_path.$this->file_name);
			
			/* Трабла с ориентацией у айфонов, костыль */
			$orientation = $image->getImageOrientation();

			switch($orientation) {
				case \Imagick::ORIENTATION_BOTTOMRIGHT: 
					$image->rotateimage("#000", 180); // rotate 180 degrees
				break;
		
				case \Imagick::ORIENTATION_RIGHTTOP:
					$image->rotateimage("#000", 90); // rotate 90 degrees CW
				break;
		
				case \Imagick::ORIENTATION_LEFTBOTTOM: 
					$image->rotateimage("#000", -90); // rotate 90 degrees CCW
				break;
			}
		
			$image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
			/* Трабла с ориентацией у айфонов, костыль END */
			
			$original_width = $image->getImageWidth();
			$original_height = $image->getImageHeight();
				
			$proporciya = $original_width > $original_height ? $original_width / $original_height : $original_height / $original_width;
			$new_proporciya = $width > $height ? $width / $height : $height / $width;
			
			
			if($width == $original_width && $height == $original_height)
			{
				$new_width = $width;
				$new_height = $height; 
				$x = 0;
				$y = 0;
			} elseif($width == $original_width && $original_height > $height) 
			{
				$x = 0;
				$y = $original_height-$height/2;
			
			} elseif($width < $original_width && $original_height == $height)
			{
				$x = $original_width-$width/2;
				$y = 0;
				
			} elseif($width > $height && $original_width > $original_height)
			{
				if($proporciya >= $new_proporciya)
				{
					$new_height = $height;
					$new_width  = null;
					
					$image->thumbnailImage($new_width,$new_height, false);
					$x = ($image->getImageWidth()-$width)/2;
					$y = 0;
				} else {
					$new_height = null;
					$new_width  = $width;
					
					$image->thumbnailImage($new_width,$new_height, false);
					$x = 0;
					$y = ($image->getImageHeight()-$height)/2;
				}
			} elseif($width < $height && $original_width < $original_height)
			{
				if($proporciya >= $new_proporciya)
				{
					$new_height = null;
					$new_width  = $width;
					
					$image->thumbnailImage($new_width,$new_height, false);
					
					$x = 0;
					$y = ($image->getImageHeight()-$height)/2;
				} else {
					$new_height = $height;
					$new_width  = null;
					
					$image->thumbnailImage($new_width,$new_height, false);
					$x = ($image->getImageWidth()-$width)/2;
					$y = 0;
				}
					
			} else {
				$new_width = $width >= $height ? $width*$proporciya : null;
				$new_height = $height > $width ? $height*$proporciya : null;
				
				
				if($blur > 0)
				{
					$image->thumbnailImage(200,null, false);
					$image->resizeImage($new_width,$new_height,\Imagick::FILTER_LANCZOS, $blur);
				} else 
				{
					$image->thumbnailImage($new_width,$new_height, false);
				}
				
				

				$x = ($image->getImageWidth()-$width)/2;
				$y = ($image->getImageHeight()-$height)/2;
			}	
			
			$image->cropImage($width, $height,$x, $y);
			$image->writeImage($server_path.$new_name);
		}
	
		$path  = $server['url'];
		$path .= $this->path;
		$path .= $new_name;
		
		return $path;
	}	

}