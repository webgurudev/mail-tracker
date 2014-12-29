<?php

use OAuth\OAuth2\Token\StdOAuth2Token;

class HomeController extends BaseController {

  protected $layout = 'layouts.master';

  public function index(){
    if (Auth:: check()) {
      return $this->showUsers();
    } else {
      return $this->showLogin();
    }
  }

  private function showLogin(){
    $this->layout->content = View::make('login');
  }

  public function doLogin(){
    if (Auth::attempt(Input::only('email', 'password'))) {
      return Redirect::to('/');
    } else {
      return Redirect::to('/')->with('error', 'Invalid email/password combination')->withInput();
    }
  }

  public function logout(){
    Auth::logout();
    return Redirect::to('/');
  }

  private function showUsers(){
    $users = User::all();
    $this->layout->content = View::make('index', array('users' => $users));
  }

  public function showUser(){

    $user = Auth::user();
    $currentTime = time();

    if(!empty($user->access_token) && $currentTime < $user->end_of_life_token){
      return $this->getUsersData($user, $user->access_token);
    } else if(Input::get('code')){
      return $this->saveGoogleToken($user);
    } else {
      return $this->getGoogleToken();
    }
  }

  private function getUsersData($user, $token){

    $userEmail = Input::get('email');
    $specificDate = Input::get('date');
    $previousDate = Input::get('previous-date');

    $reportsDate = array();

    if(!empty($specificDate)){
      $reportDate = date('Y-m-d', strtotime($specificDate));
      $reportsDate[] = $reportDate;
      $usageData = array();
      $usageData[] = $this->getDataFromGoogle($user, $userEmail, $reportDate);
    } else if(!empty($previousDate)){
      $reportsDate = array();
      for ($i=1; $i<8; $i++) {
        $reportDate = date('Y-m-d', strtotime($previousDate . '-' . $i . ' days'));
        $reportsDate[] = $reportDate;
        $usageData[] = $this->getDataFromGoogle($user, $userEmail, $reportDate);
      }
    } else {
      $reportsDate = array();
      for ($i=2; $i<9; $i++) {
        $reportDate = date('Y-m-d', strtotime('-' . $i . ' days'));
        $reportsDate[] = $reportDate;
        $usageData[] = $this->getDataFromGoogle($user, $userEmail, $reportDate);
      }
    }

    if(!empty($specificDate) || !empty($previousDate)){
      return View::make('user-usage-data', array(
        'reportsDate' => $reportsDate,
        'user' => $userEmail,
        'usageReports' => $usageData
      ));
    } else {
      $this->layout->content = View::make('user-activity', array(
        'reportsDate' => $reportsDate,
        'user' => $userEmail,
        'usageReports' => $usageData
      ));      
    }
  }

  private function getDataFromGoogle($user, $email, $date){
   
    $consumer = $this->buildConsumer($user);
    // Send a request with it
    $result = json_decode( $consumer->request('https://www.googleapis.com/admin/reports/v1/usage/users/' . $email . '/dates/' . $date . '?'  
      . 'parameters=gmail:num_emails_exchanged,'
      . 'gmail:num_emails_received,'
      . 'gmail:num_emails_sent,'
      . 'gmail:num_spam_emails_received'), true);
    return $result['usageReports'][0]['parameters'];
  }

  private function getGoogleToken(){
    $googleService = OAuth::consumer('Google');
    $googleService->setAccessType('offline');
    // get googleService authorization
    $url = $googleService->getAuthorizationUri();
    // return to google login url
    return Redirect::to( (string)$url );
  }

  private function saveGoogleToken($user){
    $code = Input::get('code');
    $googleService = OAuth::consumer('Google');
    $token = $googleService->requestAccessToken($code);
    $user->update(array(
      'access_token' => $token->getAccessToken(),
      'refresh_token' => $token->getRefreshToken(),
      'end_of_life_token' => $token->getEndOfLife()
    ));
    return Redirect::to('/');//$this->getUsersData($user, $token);
  }

  private function buildConsumer($user){
    $token = new StdOAuth2Token($user->access_token);
    $consumer = OAuth::consumer('Google');
    $consumer->getStorage()->storeAccessToken("Google", $token);
    return $consumer;
  }

}
