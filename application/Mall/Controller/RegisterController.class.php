<?php
namespace Mall\Controller;
use Common\Api\AlidayuApi;

class RegisterController extends HomeController {
	
    // 前台用户注册
	public function index(){
	    if(sp_is_user_login()){ //已经登录时直接跳到首页
	        redirect(__ROOT__."/");
	    }else{
	        $this->display();
	    }
	}
	
	// 前台用户注册提交
	public function doregister(){
		
    	if(isset($_POST['mobile'])){
    		
    		//手机号注册
    		$this->_do_mobile_register();
    		
    	}
    	/*elseif(isset($_POST['email'])){
    		
    		//邮箱注册
    		$this->_do_email_register();
    		
    	}*/
    	else{
    	    $this->error("注册方式不存在！");
    	}
    	
	}
	
	// 前台用户手机注册
	private function _do_mobile_register(){
	    
        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('mobile', 'require', '手机号不能为空！', 1 ),
            array('mobile','','手机号已被注册！！',0,'unique',3),
        	array('user_nicename','require','姓名不能为空！',1),
        	array('email','require','电子邮箱不能为空！',1),
        	array('email','email','邮箱格式不正确！',1), // 验证email字段格式是否正确
            array('password','require','密码不能为空！',1),
            array('password','6,16',"密码长度至少6位，最多16位！",1,'length',3),
        	array('repassword', 'require', '重复密码不能为空！', 1 ),
        	array('repassword','password','确认密码不正确',0,'confirm'),
        );
        	
	    $users_model=M("Users");
	     
	    if($users_model->validate($rules)->create()===false){
	        $this->error($users_model->getError());
	    }
	    
	    if(!sp_check_mobile_verify_code()){
	        $this->error("手机验证码错误！");
        }
	    
	    $password=I('post.password');
	    $mobile=I('post.mobile');
	    $email=I('post.email');
	    $user_nicename=I('post.user_nicename');
	    
	    $users_model=M("Users");
	    $data=array(
	        'user_login' => '',
	        'user_email' => $email,
	        'mobile' =>$mobile,
	        'user_nicename' =>$user_nicename,
	        'user_pass' => sp_password($password),
	        'last_login_ip' => get_client_ip(0,true),
	        'create_time' => date("Y-m-d H:i:s"),
	        'last_login_time' => date("Y-m-d H:i:s"),
	        'user_status' => 1,
	        "user_type"=>2,//会员
	    );
	    
	    $result = $users_model->add($data);
	    if($result){
	        //注册成功页面跳转
	        $data['id']=$result;
	        session('user',$data);
	        $this->success("注册成功！",__ROOT__."/");
	        
	    }else{
	        $this->error("注册失败！",U("Mall/Register/index"));
	    }
	}
	
	public function send_mobile_code(){
		$mobile = I('mobile');
		if(!$mobile){
			$this->error("手机号为空！");
		}
		$result = sp_get_mobile_code($mobile);
		if($result){
			$mobile_code = session('mobile_code');
			sp_mobile_code_log($mobile,$mobile_code['code'],$mobile_code['expire_time']);
			$rel = $this->send_messages($result, $mobile);
			if($rel['result']['success']=='true'){
				$this->success("发送成功！");
			}else{
				$this->error($rel['err_str']);
			}
		}
		else{
			$this->error("发送验证码失败！");
		}
	}
	
	/**
	 * 发送短信
	 */
	private function send_messages($code,$mobile){
		$aldy=new AlidayuApi();
	
		$json=json_encode(array('product'=>'黑金杰尼商城','time'=>date("H:i:s",time()),'code'=>"$code",'expiry_date'=>'10分钟'));
		$config=array(
				'appkey'         => C('SMS_APP_KEY'),
				'secretKey'      => C('SMS_APP_SECRET'),
				'Extend'         => '',
				'SmsType'        => 'normal',
				'SmsFreeSignName'=> C('SMS_FFREE_SIGN_NAME'),
				'SmsParam'       => $json,
				'RecNum'         => $mobile,
				'SmsTemplateCode'=> C('SMS_TEMPLATE_CODE'),
		);
	
		$rel=$aldy->do_send($config);
		
		return $rel;
	}
	
	// 前台用户邮件注册
	private function _do_email_register(){
	   
        if(!sp_check_verify_code()){
            $this->error("验证码错误！");
        }
        
        $rules = array(
            //array(验证字段,验证规则,错误提示,验证条件,附加规则,验证时间)
            array('email', 'require', '邮箱不能为空！', 1 ),
            array('password','require','密码不能为空！',1),
            array('password','5,20',"密码长度至少5位，最多20位！",1,'length',3),
            array('repassword', 'require', '重复密码不能为空！', 1 ),
            array('repassword','password','确认密码不正确',0,'confirm'),
            array('email','email','邮箱格式不正确！',1), // 验证email字段格式是否正确
            	
        );
	     
	    $users_model=M("Users");
	     
	    if($users_model->validate($rules)->create()===false){
	        $this->error($users_model->getError());
	    }
	     
	    $password=I('post.password');
	    $email=I('post.email');
	    $username=str_replace(array(".","@"), "_",$email);
	    //用户名需过滤的字符的正则
	    $stripChar = '?<*.>\'"';
	    if(preg_match('/['.$stripChar.']/is', $username)==1){
	        $this->error('用户名中包含'.$stripChar.'等非法字符！');
	    }
	     
// 	    $banned_usernames=explode(",", sp_get_cmf_settings("banned_usernames"));
	     
// 	    if(in_array($username, $banned_usernames)){
// 	        $this->error("此用户名禁止使用！");
// 	    }
	    
	    $where['user_login']=$username;
	    $where['user_email']=$email;
	    $where['_logic'] = 'OR';
	    
	    $ucenter_syn=C("UCENTER_ENABLED");
	    $uc_checkemail=1;
	    $uc_checkusername=1;
	    if($ucenter_syn){
	        include UC_CLIENT_ROOT."client.php";
	        $uc_checkemail=uc_user_checkemail($email);
	        $uc_checkusername=uc_user_checkname($username);
	    }
	     
	    $users_model=M("Users");
	    $result = $users_model->where($where)->count();
	    if($result || $uc_checkemail<0 || $uc_checkusername<0){
	        $this->error("用户名或者该邮箱已经存在！");
	    }else{
	        $uc_register=true;
	        if($ucenter_syn){
	             
	            $uc_uid=uc_user_register($username,$password,$email);
	            //exit($uc_uid);
	            if($uc_uid<0){
	                $uc_register=false;
	            }
	        }
	        if($uc_register){
	            $need_email_active=C("SP_MEMBER_EMAIL_ACTIVE");
	            $data=array(
	                'user_login' => $username,
	                'user_email' => $email,
	                'user_nicename' =>$username,
	                'user_pass' => sp_password($password),
	                'last_login_ip' => get_client_ip(0,true),
	                'create_time' => date("Y-m-d H:i:s"),
	                'last_login_time' => date("Y-m-d H:i:s"),
	                'user_status' => $need_email_active?2:1,
	                "user_type"=>2,//会员
	            );
	            $rst = $users_model->add($data);
	            if($rst){
	                //注册成功页面跳转
	                $data['id']=$rst;
	                session('user',$data);
	                	
	                //发送激活邮件
	                if($need_email_active){
	                    $this->_send_to_active();
	                    session('user',null);
	                    $this->success("注册成功，激活后才能使用！",U("Mall/Login/index"));
	                }else {
	                    $this->success("注册成功！",__ROOT__."/");
	                }
	                	
	            }else{
	                $this->error("注册失败！",U("Mall/Register/index"));
	            }
	             
	        }else{
	            $this->error("注册失败！",U("Mall/Register/index"));
	        }
	         
	    }
	}
	
	// 前台用户邮件注册激活
	public function active(){
		$hash=I("get.hash","");
		if(empty($hash)){
			$this->error("激活码不存在");
		}
		
		$users_model=M("Users");
		$find_user=$users_model->where(array("user_activation_key"=>$hash))->find();
		
		if($find_user){
			$result=$users_model->where(array("user_activation_key"=>$hash))->save(array("user_activation_key"=>"","user_status"=>1));
			
			if($result){
				$find_user['user_status']=1;
				session('user',$find_user);
				$this->success("用户激活成功，正在登录中...",__ROOT__."/");
			}else{
				$this->error("用户激活失败!",U("Mall/Login/index"));
			}
		}else{
			$this->error("用户激活失败，激活码无效！",U("Mall/Login/index"));
		}
		
		
	}
	
	
}