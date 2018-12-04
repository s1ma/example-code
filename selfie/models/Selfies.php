<?php
namespace frontend\models;

use yii\db\ActiveRecord;
use frontend\models\user\User;

class Selfies extends ActiveRecord
{
	private static $users = [];
	private $location;
	private $comments_items;
	public $is_like;
	public $is_dislike;

	//Static functions
	public static function tableName()
	{
		return 'selfies';
	}
	
	//Dynamic functions
	public function isLike()
	{
		return !is_null($this->is_like);
	}
	public function isDislike()
	{
		return !is_null($this->is_dislike);
	}
	
	public function getImage()
	{
		return Images::findOne(['image_id' => $this->image_id]);
	}
	
	public function setTop($from, $to, $type, $number)
	{
		$top = new Tops();
		$top->selfie_id = $this->selfie_id;
		$top->user_id = $this->user_id;
		$top->date_start = $from;
		$top->date_end = $to;
		$top->type = $type;
		$top->number = $number;
		$top->save();
		
		$user = $this->getUser();
		$user->__set('top_'.$type, 1);
		$user->save();
		
		$this->__set('top_'.$type, 1);
		$this->save();
	}
	
	public function getUser()
	{
		if(!isset(self::$users[$this->user_id]))
		{
			self::$users[$this->user_id] = User::findOne(['user_id' => $this->user_id]);
		}
		return self::$users[$this->user_id];
	}
	
	public function getLocation()
	{
		if(!is_null($this->location_id))
		{
			if(!isset($this->location))
			{
				$this->location = Locations::findOne(['location_id' => $this->location_id]);
			}
			return $this->location;
		}
		return false;
	}
	
	public function getComments()
	{
		if(!isset($this->comments_items))
		{
			$this->comments_items = Comments::find()->where(['selfie_id' => $this->selfie_id, 'parent_comment_id' => null])->all();
		}
		return $this->comments_items;
	}
	
	public function getCountComments()
	{
		return $this->comments;
	}
	
	public function getCountLikes()
	{
		return $this->likes;
	}
	
	public function getUserWhoLikes($start = 0, $limit = 5)
	{
		if($this->getCountLikes() > 0)
		{
			$users = User::find()->leftJoin('likes', 'likes.user_id = users.user_id')->where(['likes.selfie_id' => $this->selfie_id]);
			return ['count' => $users->count(), 'users' => $users->offset($start)->limit($limit)->all()];
		} 
		
		return [];
	}
	public function lastThreeLikers()
	{
		$response = [];
		if($this->getCountLikes() > 0)
		{
			$users = User::find()->leftJoin('likes', 'likes.user_id = users.user_id')->where(['likes.selfie_id' => $this->selfie_id])->limit(3);
			foreach($users->all() as $user)
			{
				$response[] = $user->name;
			}
		} 
		
		return $response;
	}
	
	
	public function getCountDislikes()
	{
		return $this->dislikes;
	}
	
	public function getCountViews()
	{
		return $this->views;
	}
	
	public function isTop()
	{
		if($this->top_day != 0 || $this->top_week != 0 || $this->top_month != 0)
		{
			return true;
		}
		
		return false;
	}
	
	public function getTopType()
	{
		if($this->isTop())
		{
			switch(true)
			{
				case $this->top_month != 0:
					return 'month';
				break;
				case $this->top_week != 0:
					return 'week';
				break;
				case $this->top_day != 0:
					return 'day';
				break;
			}
		}
		return false;
	}
	
	public function getArrayData($user_id = null)
	{
		$top = $this->isTop() ? $this->getTopType() : null;
		return [
				'image_url' => $this->getImage()->getCropUrl(100),
				'image_url_360' => $this->getImage()->getCropUrl(360),
				'user_selfie_id' => $this->user_selfie_id,
				'isLike' => boolval(\frontend\models\Likes::findOne(['user_id' => $user_id, 'selfie_id' => $this->selfie_id])),
				'user_avatar' => $this->getUser()->getAvatarUrl(26),
				'first_name' => $this->getUser()->first_name,
				'name' => $this->getUser()->name,
				'type' => $top ? $top : null,
		];
	}
}