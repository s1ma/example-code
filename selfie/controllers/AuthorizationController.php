<?php

namespace frontend\controllers;

use Yii;

use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\filters\Cors;

use yii\web\Controller;
use frontend\models\user\User;
use frontend\models\form\FormAuthSite;
use frontend\models\form\FormRegistrationSite;
use frontend\models\form\Upload;

use linslinYii2\curl;
use TwitterOAuth;

class AuthorizationController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['get'],
                    'checkauth' => ['get', 'options'],
                    'login' => ['post', 'options'],
                    'registration' => ['post'],
                ],
            ],
            'corsFilter' => [
                'class' => Cors::className(),
                'cors'  => [
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => null,
                    'Access-Control-Max-Age' => 3600,
                ],
            ],
        ];
    }
    
    public $enableCsrfValidation = false;
    
    public function actionCheckauth()
    {
        header('Access-Control-Allow-Origin: *'); 
        Yii::$app->response->format = 'json';
        
        $user = User::findOne(['auth_key' => Yii::$app->request->get('auth_key')]);
        if ($user) {
            return ['auth' => true, 'name' => $user->name, 'first_name' => $user->first_name, 'user_avatar' => $user->getAvatarUrl(60)];
        }
        
        return ['auth' => false];
    }
    
    
    
    public function actionRegistration()
    {
        header('Access-Control-Allow-Origin: *'); 
        Yii::$app->response->format = 'json';
        $response = [];
        
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new FormRegistrationSite();
        if ($model->load(Yii::$app->request->post()) && $model->registration() && $model->login()) {
            if(Yii::$app->request->post('phone_api') == 'true' or Yii::$app->request->post('phone_api') == true)
            {
                Yii::$app->user->getIdentity()->setApiAuthKey();
                return ['success' => true, 'auth_key' => Yii::$app->user->getIdentity()->auth_key];
            }
            return $this->goBack();
        } else {
            $response['errors'] = $model->getErrors();
        }
        
        return $response;
    }
    
    public function actionLogin()
    {

        header('Access-Control-Allow-Origin: *');

        Yii::$app->response->format = 'json';
        $response = [];
        
        if (!Yii::$app->user->isGuest && !(Yii::$app->request->post('phone_api') == 'true' or Yii::$app->request->post('phone_api') == true)) {
            return $this->goHome();
        }

        $model = new FormAuthSite();         
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            
            if(Yii::$app->request->post('phone_api') == 'true' or Yii::$app->request->post('phone_api') == true)
            {
                Yii::$app->user->getIdentity()->setApiAuthKey();
                return ['success' => true, 'auth_key' => Yii::$app->user->getIdentity()->auth_key];
            }
            return $this->goBack();
        } else {
            $response['error'] = 'Login/password combination not found.';
        }
        
        return $response;
    }
    
    public function actionLoginvk()
    {
        if(!isset(Yii::$app->request->get()['code']))
        {
            $this->redirect('/');
        }

        $url_get_token  = "https://oauth.vk.com/access_token?";
        $url_get_token .= "client_id=".Yii::$app->params['apiData']['vk']['client_id']."&";
        $url_get_token .= "client_secret=".Yii::$app->params['apiData']['vk']['appSecret']."&";
        $url_get_token .= "code=".Yii::$app->request->get()['code']."&";
        $url_get_token .= "redirect_uri=".Yii::$app->urlManager->createAbsoluteUrl(['/authorization/loginvk']);
        
        $curl = new curl\Curl();
        $response = json_decode($curl->get($url_get_token), true);

        if(is_null($response))
        {
            return $this->redirect('/');
        }
        
        $url = 'https://api.vk.com/method/getProfiles?v=5.0&fields=uid,first_name,last_name,sex,photo_200&access_token='.$response['access_token'];
        $user_data = json_decode($curl->get($url));
        
        if(is_null($user_data) OR isset($user_data->error))
        {
             return $this->redirect('/');
        }

        $userAuthVK = \frontend\models\user\UserAuthVk::findOne(['user_id_vk' => $user_data->response[0]->id]);
        
        if(!$userAuthVK)
        {
            $userAuthVK = new \frontend\models\user\UserAuthVk();
            
            $user = new \frontend\models\user\User();
            $user->first_name = $user_data->response[0]->first_name." ".$user_data->response[0]->last_name;
            $user->last_name = $user_data->response[0]->last_name;
            $user->name =  $user_data->response[0]->id;
            
            if(isset($user_data->response[0]->sex))
            {
                switch($user_data->response[0]->sex)
                {
                    case '1':
                        $user->gender = 'famele';
                    break;
                    case '2':
                        $user->gender = 'male';
                    break;
                }                
            }
            
            while( \frontend\models\user\User::find()->where(['name' => $user->name])->exists() )
            {
                $user->name = $user->name.rand(0, 9);
            }
            
            $user->auth_type = 'vk';
            
            $upload = new Upload();
            $image = $upload->uploadFromUrl($user_data->response[0]->photo_200);
            $user->image_id = $image->image_id;
            $user->role = 'user';
            $user->available_quantity_selfie = 100000;            
            $user->save();


            $userAuthVK->user_id = $user->user_id;
            
        } else {
            $user = \frontend\models\user\User::findOne(['user_id' => $userAuthVK->user_id]);
            $user->first_name = $user_data->response[0]->first_name.$user_data->response[0]->last_name;
            $user->last_name = $user_data->response[0]->last_name;
            
            if(isset($user_data->response[0]->sex))
            {
                switch($user_data->response[0]->sex)
                {
                    case '1':
                        $user->gender = 'famele';
                    break;
                    case '2':
                        $user->gender = 'male';
                    break;
                }                
            }
    
                    
            $upload = new Upload();
            $image = $upload->uploadFromUrl($user_data->response[0]->photo_200);
            $user->image_id = $image->image_id;
            
            $user->save();
        }
        
        
        $userAuthVK->user_id_vk = $user_data->response[0]->id;
        $userAuthVK->access_token = $response['access_token'];
        $userAuthVK->expires_in = $response['expires_in'];
        $userAuthVK->save();
        $userAuthVK->login();
        
        return $this->redirect('/');
    }

    
    public function actionLoginfacebook()
    {
        $url_get_token  = "https://graph.facebook.com/oauth/access_token";
        $url_get_token .= "?client_id=".Yii::$app->params['apiData']['facebook']['appId'];
        $url_get_token .= "&client_secret=".Yii::$app->params['apiData']['facebook']['appSecret'];
        $url_get_token .= "&redirect_uri=".Yii::$app->urlManager->createAbsoluteUrl(['/authorization/loginfacebook']);
        $url_get_token .= "&code=".Yii::$app->request->get()['code'];
        $token_info = null;
        

        $curl = new curl\Curl();
        
        $response = $curl->get($url_get_token);
        $token_info = json_decode($response, true);

        if(!$response or empty($token_info) or !isset($token_info['access_token']))
        {
            return $this->render('/index/error');
        }

        $url = 'https://graph.facebook.com/me?fields=id,first_name,email,last_name,picture.height(160).width(160)&method=GET&format=json&suppress_http_code=1&access_token='.$token_info['access_token'];
        $user_data = json_decode($curl->get($url));
        

        if(is_null($user_data) or isset($user_data->error))
        {
            return $this->render('/index/error');
        }
        
        
        $userAuthFB = \frontend\models\user\UserAuthFb::findOne(['user_id_fb' => $user_data->id]);
        
        if(!$userAuthFB)
        {
            $userAuthFB = new \frontend\models\user\UserAuthFb();
            
            $user = new \frontend\models\user\User();
            $user->first_name = $user_data->first_name.$user_data->last_name;
            $user->last_name = $user_data->last_name;
            $user->name = $user->first_name.$user_data->last_name;
            
            while( \frontend\models\user\User::find()->where(['name' => $user->name])->exists() )
            {
                $user->name = $user->name.rand(0, 9);
            }
            
            $user->email = $user_data->email;
            
            $user->role = 'user';
            $user->available_quantity_selfie = 100000;
            
            $user->auth_type = 'facebook';
            
            $upload = new Upload();
            $image = $upload->uploadFromUrl($user_data->picture->data->url);
            $user->image_id = $image->image_id;
            
            $user->save();
            
            $userAuthFB->user_id = $user->user_id;
            
        } else {
            $user = \frontend\models\user\User::findOne(['user_id' => $userAuthFB->user_id]);
            $user->first_name = $user_data->first_name;
            $user->last_name = $user_data->last_name;
            
            $upload = new Upload();
            $image = $upload->uploadFromUrl($user_data->picture->data->url);
            $user->image_id = $image->image_id;
            
            $user->save();
        }
        

        $userAuthFB->user_id_fb = $user_data->id;
        $userAuthFB->access_token = $token_info['access_token'];

        if(isset($token_info['expires_in']))
        {
            $userAuthFB->expires_in = $token_info['expires_in'];
        }

        $userAuthFB->save();
        $userAuthFB->login();

        $user->setApiAuthKey();
        
        if(!isset(Yii::$app->request->get()['state']))
        {
            die("
            <!doctype html>
            <html>
            <head>
                <meta http-equiv='X-UA-Compatible' content='IE=edge'/>
                <script>window.location = '/mobile/close?".$user->auth_key."';</script>                
            </head>
            <body>
            </body>
            </html>");
        }
        
                
        return $this->redirect(Yii::$app->request->get()['state']);
    }
    
    public function actionLogintwitter()
    {
        session_start();
        if(isset(Yii::$app->request->get()['oauth_token']) && isset(Yii::$app->request->get()['oauth_verifier']))
        {
            $connection = new TwitterOAuth(Yii::$app->params['apiData']['twitter']['appKey'], Yii::$app->params['apiData']['twitter']['appSecret'], $_SESSION['oauth_token'],
$_SESSION['oauth_token_secret']);
    
            $token_credentials = $connection->getAccessToken(Yii::$app->request->get()['oauth_verifier']);
            
            $connection = new TwitterOAuth(Yii::$app->params['apiData']['twitter']['appKey'], Yii::$app->params['apiData']['twitter']['appSecret'], $token_credentials['oauth_token'],
$token_credentials['oauth_token_secret']);
            
            $connection->host = "https://api.twitter.com/1.1/";
            
            $user_data = $connection->get('account/verify_credentials');
            if(isset($user_data->errors))
            {
                return $this->goHome();
            }
            
            $userAuthTwitter = \frontend\models\user\UserAuthTw::findOne(['user_id_twitter' => $user_data->id]);
            if(!$userAuthTwitter)
            {
                $userAuthTwitter = new \frontend\models\user\UserAuthTw();
                
                $user = new \frontend\models\user\User();
                $user->first_name = $user_data->name;
                $user->last_name = $user_data->screen_name;
                $user->auth_type = 'twitter';
                
                            
                $user->name = $user_data->screen_name;
            
                while( \frontend\models\user\User::find()->where(['name' => $user->name])->exists() )
                {
                    $user->name = $user->name.rand(0, 9);
                }
            
                $upload = new Upload();
                $image = $upload->uploadFromUrl(str_replace('_normal', '', $user_data->profile_image_url));
                $user->image_id = $image->image_id;
                
                $user->role = 'user';
                $user->available_quantity_selfie = 100000;
            
                $user->save();
                
                $userAuthTwitter->user_id = $user->user_id;
            } else {
                $user = \frontend\models\user\User::findOne(['user_id' => $userAuthTwitter->user_id]);
                $user->first_name = $user_data->name;
                $user->last_name = $user_data->screen_name;

                $upload = new Upload();
                $image = $upload->uploadFromUrl(str_replace('_normal', '', $user_data->profile_image_url));
                $user->image_id = $image->image_id;

                $user->save();
            }
            
            
            $userAuthTwitter->user_id_twitter = $user_data->id;
            $userAuthTwitter->oauth_token = $token_credentials['oauth_token'];
            $userAuthTwitter->oauth_token_secret = $token_credentials['oauth_token_secret'];
            $userAuthTwitter->save();
            
            $userAuthTwitter->login();
                    
            return $this->goHome();
        }
    
    
        $to = new TwitterOAuth(Yii::$app->params['apiData']['twitter']['appKey'], Yii::$app->params['apiData']['twitter']['appSecret']);
        $tok = $to->getRequestToken(Yii::$app->request->getAbsoluteUrl());
        if(isset($tok['oauth_token']))
        {
            $_SESSION['oauth_token'] = $tok['oauth_token'];
            $_SESSION['oauth_token_secret'] = $tok['oauth_token_secret'];
            
            $request_link = $to->getAuthorizeURL($tok);
            return $this->redirect($request_link);
        } else {
            return $this->goHome();
        }
    }
    
    public function actionLogout()
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }
    
}