<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);
include_once(dirname(__FILE__).'/../../unfraud.php');

 class AdminUnfraudController extends ModuleAdminController
{
    public function __construct()
    {
        $this->className = 'Unfraud';
        $this->lang = true;
        $this->display = 'view';
        $this->bootstrap = false;
        $this->colorOnBackground = false;
        $this->context = Context::getContext();

        parent::__construct();
    }

     public function initContent()
     {
         $email = Configuration::get(Unfraud::UNFRAUD_EMAIL);
         $password = Configuration::get(Unfraud::UNFRAUD_PASSWORD);
         $apikey = Configuration::get(Unfraud::UNFRAUD_APIKEY);
         if (!$apikey || !$email || !$password)
         {
             $this->content = $this->module->translate("You need to add API KEY, EMAIL and PASSWORD in plugin configuration.");
         }
         else
         {
             $contents = json_decode(file_get_contents(Unfraud::LOGIN_API_URL.'&email='.$email.'&password='.$password),true);
             if($contents["status"]=="logged"){
                 $this->content = '<iframe src="'.Unfraud::LOGIN_URL.'?e='.$email.'&p='.$password.'&t='.$apikey.'" width="100%" height="1000" style="border:1px lightGray solid;" frameborder="0"></iframe>';
             }
             else{
                 $this->content = $this->module->translate("Your user credentials are incorrect. Please change your EMAIL and PASSWORD in plugin configuration.");
             }
         }

         $this->content .= "<style type=\"text/css\">.nobootstrap{ padding:0 !important }</style>";
         $this->show_toolbar = false;

         $this->context->smarty->assign(array(
             'content' => $this->content
         ));
     }


}