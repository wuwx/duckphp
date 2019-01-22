<?php
namespace DNMVCS;
class RouteHookMapAndRewrite
{
	use DNSingleton;
	
	public function replaceRegexUrl($input_url,$template_url,$new_url)
	{
		if(substr($template_url,0,1)!=='~'){	return null; }
		
		
		$input_path=parse_url($input_url,PHP_URL_PATH);
		$input_get=[];
		parse_str(parse_url($input_url,PHP_URL_QUERY),$input_get);
		
		//$template_path=parse_url($template_url,PHP_URL_PATH);
		//$template_get=[];
		parse_str(parse_url($template_url,PHP_URL_QUERY),$template_get);
		$p='/'.str_replace('/','\/',substr($template_url,1)).'/A';
		if(!preg_match($p,$input_path)){ return null; }
		//if(array_diff_assoc($input_get,$template_get)){ return null; }
		
		$new_url=str_replace('$','\\',$new_url);
		$new_url=preg_replace($p,$new_url,$input_path);
		
		$new_path=parse_url($new_url,PHP_URL_PATH);
		$new_get=[];
		parse_str(parse_url($new_url,PHP_URL_QUERY),$new_get);
		
		$get=array_merge($input_get,$new_get);
		$query=$get?'?'.http_build_query($get):'';
		return $new_path.$query;
	}
	public function replaceNormalUrl($input_url,$template_url,$new_url)
	{
		if(substr($template_url,0,1)==='~'){ return null; }
		
		$input_path=parse_url($input_url,PHP_URL_PATH);
		$input_get=[];
		parse_str(parse_url($input_url,PHP_URL_QUERY),$input_get);
		
		$template_path=parse_url($template_url,PHP_URL_PATH);
		$template_get=[];
		parse_str(parse_url($template_url,PHP_URL_QUERY),$template_get);
		
		if(array_diff_assoc($input_get,$template_get)){ return null; }
		
		$new_path=parse_url($new_url,PHP_URL_PATH);
		$new_get=[];
		parse_str(parse_url($new_url,PHP_URL_QUERY),$new_get);
		if($input_path!==$template_path){ return null; }
		
		$get=array_merge($input_get,$new_get);
		$query=$get?'?'.http_build_query($get):'';
		
		return $new_path.$query;
	}
	public function filteRewrite($input_url)
	{
		$rewriteMap=DNMVCS::G()->options['rewrite_map'];
		foreach($rewriteMap as $template_url=>$new_url){
			$ret=$this->replaceNormalUrl($input_url,$template_url,$new_url);
			if($ret!==null){return $ret;}
			$ret=$this->replaceRegexUrl($input_url,$template_url,$new_url);
			if($ret!==null){return $ret;}
		}
		return null;
	}
	protected function matchRoute($pattern_url,$path_info,$route)
	{
		$request_method=$route->request_method;
		$enable_paramters=$route->options['enable_paramters'];
		
		$pattern='/^(([A-Z_]+)\s+)?(~)?\/?(.*)\/?$/';
		$flag=preg_match($pattern,$pattern_url,$m);
		if(!$flag){return false;}
		$method=$m[2];
		$is_regex=$m[3];
		$url=$m[4];
		if($method && $method!==$request_method){return false;}
		if(!$is_regex){
			$params=explode('/',$path_info);
			$url_params=explode('/',$url);
			if(!$enable_paramters){
				return ($url_params===$params)?true:false;
			}
			if($url_params === array_slice($params,0,count($url_params))){
				$route->parameters=array_slice($params,0,count($url_params));
				return true;
			}else{
				return false;
			}
			
		}
		
		$p='/'.str_replace('/','\/',$url).'/';
		$flag=preg_match($p,$path_info,$m);
		
		if(!$flag){return false;}
		array_shift($m);
		$route->parameters=$m;
		return true;
	}
	protected function getRouteHandelByMap($route,$routeMap)
	{
		$path_info=$route->path_info;
		foreach($routeMap as $pattern =>$callback){
			if(!$this->matchRoute($pattern,$path_info,$route)){continue;}
			if(!is_string($callback)){return $callback;}
			if(false!==strpos($callback,'->')){
				list($class,$method)=explode('->',$callback);
				return [new $class(),$method];
			}
			return $callback;
		}
		return null;
	}
	protected function changeRouteUrl($route,$url)
	{
		$path=parse_url($url,PHP_URL_PATH);
		$get=[];
		parse_str(parse_url($url,PHP_URL_QUERY),$input_get);
		$route->path_info=$path;
		DNSuperGlobal::G()->_SERVER['init_get']=DNSuperGlobal::G()->_GET;
		DNSuperGlobal::G()->_GET=$get;
	}
	protected function hookRewrite($route)
	{
		$path_info=$route->path_info;
		
		$uri=DNSuperGlobal::G()->_SERVER['REQUEST_URI'];
		$query=parse_url($uri,PHP_URL_QUERY);
		$query=$query?'?'.$query:'';
		$input_url=$path_info.$query;
		
		$rewriteMap=DNMVCS::G()->options['rewrite_map'];
		foreach($rewriteMap as $template_url=>$new_url){
			$url=$this->replaceNormalUrl($input_url,$template_url,$new_url);
			if($url!==null){
				$this->changeRouteUrl($route,$url);
			}
			$url=$this->replaceRegexUrl($input_url,$template_url,$new_url);
			if($url!==null){
				$this->changeRouteUrl($route,$url);
			}
		}
	}
	protected function hookRouteMap($route)
	{
		$route->callback=$this->getRouteHandelByMap($route,DNMVCS::G()->options['route_map']);
	}
	public function hook($route)
	{
		$this->hookRewrite($route);
		$this->hookRouteMap($route);
	}
}

// 处理  onefile.php?module=?&act=? 的链接

// basedir  a/b.php/d
class SimpleRouteHook
{
	use DNSingleton;

	public $key_for_action='_r';
	public $key_for_module='';
	public function onURL($url=null)
	{
		if(strlen($url)>0 && '/'==$url{0}){ return $url;};
		
		$key_for_action=$this->key_for_action;
		$key_for_module=$this->key_for_module;
		$get=[];
		$path='';
		$path=DNSuperGlobal::G()->_SERVER['REQUEST_URI'];
		$path_info=DNSuperGlobal::G()->_SERVER['PATH_INFO'];

		
		$path=parse_url($path,PHP_URL_PATH);
		if(strlen($path_info)){
			$path=substr($path,0,0-strlen($path_info));
		}
		if($url===null || $url===''){return $path;}
		////////////////////////////////////
		
		$new_url=RouteHookMapAndRewrite::G()->filteRewrite($url);
		if($new_url){
			$url=$new_url;
			if(strlen($url)>0 && '/'==$url{0}){ return $url;};
		}
		
		$input_path=parse_url($url,PHP_URL_PATH);
		$input_get=[];
		parse_str(parse_url($url,PHP_URL_QUERY),$input_get);
		
		$blocks=explode('/',$input_path);
		if(isset($blocks[0])){
			$basefile=basename(DNSuperGlobal::G()->_SERVER['SCRIPT_FILENAME']);
			if($blocks[0]===$basefile){
				array_shift($blocks);
			}
		}
		
		if($key_for_module){
			$action=array_pop($blocks);
			$module=implode('/',$blocks);
			if($module){
				$get[$key_for_module]=$module;
			}
			$get[$key_for_action]=$action;
		}else{
			$get[$key_for_action]=$input_path;
		}
		$get=array_merge($input_get,$get);
		if($key_for_module && isset($get[$key_for_module]) && $get[$key_for_module]===''){ unset($get[$key_for_module]); }
		$query=$get?'?'.http_build_query($get):'';
		$url=$path.$query;
		
		return $url;
	
	}
	public function hook($route)
	{
		$route->setURLHandler([$this,'onURL']);
		$k=$this->key_for_action;
		$m=$this->key_for_module;
		$module=DNSuperGlobal::G()->_REQUEST[$m]??null;
		$path_info=DNSuperGlobal::G()->_REQUEST[$k]??null;

		$path_info=$module.'/'.$path_info;
		$path_info=ltrim($path_info,'/');
		$route->path_info=$path_info;
		$route->calling_path=$path_info;
	}
}

class BasePathModeRouteHook // not working.
{
	use DNSingleton;

	public $key_for_action='_r';
	public $key_for_module='';
	public function onURL($url=null)
	{
		if(strlen($url)>0 && '/'==$url{0}){ return $url;};
		
		return $url;	
	}
	// abc/d/e.php/g/h?act=z  abc/d/e/g
	public function hook($route)
	{
		$route->setURLHandler([$this,'onURL']);
		$route->path_info=$path_info;
		$route->calling_path=$path_info;
	}
}

class ProjectCommonAutoloader
{
	use DNSingleton;
	protected $path_common;
	public function init($options)
	{
		$this->path_common=isset($options['fullpath_project_share_common'])??'';
		return $this;
	}
	public function run()
	{
		spl_autoload_register(function($class){
			if(strpos($class,'\\')!==false){ return; }
			$path_common=$this->path_common;
			if(!$path_common);return;
			$flag=preg_match('/Common(Service|Model)$/',$class,$m);
			if(!$flag){return;}
			$file=$path_common.'/'.$class.'.php';
			if (!$file || !file_exists($file)) {return;}
			require $file;
		});
	}
}
class ProjectCommonConfiger extends DNConfiger
{
	public $fullpath_config_common;

	public function init($path,$options)
	{
		$this->fullpath_config_common=isset($options['fullpath_config_common'])??'';
		return parent::init($path,$options);
	}
	protected function loadFile($basename,$checkfile=true)
	{	
		$common_config=[];
		if($this->fullpath_config_common){
			$file=$this->fullpath_config_common.$basename.'.php';
			if(is_file($file)){
				$common_config=(function($file){return include($file);})($file);
			}
		}
		$ret=parent::loadFile($basename,$checkfile);
		$ret=array_merge($common_config,$ret);
		return $ret;
	}
	
}
class FunctionDispatcher 
{
	use DNSingleton;
	
	protected $path_info;
	public $prefix='action_';
	public $default_callback='action_index';
	public function hook($route)
	{
		$this->path_info=$route->path_info;
		$flag=$this->runRoute();
		if($flag){
			$route->stopRunDefaultHandler();
		}
	}
	public function runRoute()
	{
		$route=DNRoute::G();
		$post=($route->request_method==='POST')?$route->options['prefix_post']:'';
		$callback=$this->prefix.$post.$this->path_info;
		
		$path_info=$this->path_info?:'index';
		$prefix=str_replace('\\','/',$this->prefix);
		$fullpath=$prefix.$path_info;
		$blocks=explode('/',$fullpath);
		$method=array_pop($blocks);
		$classname=implode('\\',$blocks);
		if($classname){
			if(class_exists($classname)){
			
				$class=new $classname();
				$method=$post?$post.$method:$method;
				$callback=[$class,$method];
				
			}else{
				$callback=null;
			}
		}else{
			$method=$post?$post.$path_info:$path_info;
			$method=$this->prefix.$method;
			$callback=$method;
			if(!is_callable($callback)){
				$callback=null;
			}
			
		}
		if($callback){
			($callback)();
			return true;
		}
		if(is_callable($this->default_callback)){
			($this->default_callback)();
			return true;
		}else{
			//($route->the404Handler)();
			return false;
		}
	}
}
class FunctionView extends DNView
{
	public $prefix='view_';
	public $head_callback;
	public $foot_callback;
	
	private $callback;
	
	public function init($path)
	{
		$ret=parent::init($path);
		$options=DNMVCS::G()->options;
		$this->head_callback=$options['function_view_head']??'';
		$this->foot_callback=$options['function_view_foot']??'';
		return $ret;
	}
	public function _Show($data=[],$view)
	{
		$this->view=$view;
		$this->data=array_merge($this->data,$data);
		$data=null;
		$view=null;
		extract($this->data);
		
		if(isset($this->before_show_handler)){
			($this->before_show_handler)($data,$this->view);
		}
		$this->prepareFiles();
		
		
		if($this->head_callback){
			if(is_callable($this->head_callback)){
				($this->head_callback)($this->data);
			}
		}else{
			if($this->head_file){
				$this->head_file=rtrim($this->head_file,'.php').'.php';
				include($this->path.$this->head_file);
			}
		}
		
		$this->callback=$this->prefix.$this->view;
		if(is_callable($this->callback)){
			($this->callback)($this->data);
		}else{
			include($this->view_file);
		}
		
		if($this->head_callback){
			if(is_callable($this->foot_callback)){
				($this->foot_callback)($this->data);
			}
		}else{
			if($this->foot_file){
				$this->foot_file=rtrim($this->foot_file,'.php').'.php';
				include($this->path.$this->foot_file);
			}
		}
	}
	public function _ShowBlock($view,$data=null)
	{
		$this->view=$view;
		$this->data=array_merge($this->data,$data);
		$data=null;
		$view=null;
		extract($this->data);
		
		$this->callback=$this->prefix.$this->view;
		if(is_callable($this->callback)){
			($this->callback)($this->data);
		}else{
			include($this->view_file);
		}
	}
}
class FacadeBase
{
	use DNSingleton;
	
	public static function __callStatic($name, $arguments) 
	{
		$callback=self::G()->getFacadeCallback(static::class,$name);
		$ret=call_user_func_array($callback, $arguments);
		return $ret;
	}
	public function getFacadeCallback($class,$name)
	{
		$dn=DNMVCS::G();
		$ext=$dn->options['ext'];
		$map=$ext['facade_map']??[];
		
		
		$namespace=$dn->options['namespace'];
		$class=$namespace.'\\'. substr($class,strlen($namespace.'\\Facade\\'));
		foreach($map as $k=>$v){
			if($k===$class){
				$object=call_user_func([$v,'G']);
				return [$object,$name];
			}
		}
		
		$object=call_user_func([$class,'G']);
		return [$object,$name];
	}
}
class DNMVCSExt
{
	use DNSingleton;
	use DNDI;
	
	const DEFAULT_OPTIONS_EX=[
			'key_for_action'=>null,
				'key_for_module'=>null,
			
			'use_function_view'=>false,
				'function_view_head'=>'view_header',
				'function_view_foot'=>'view_footer',
			'use_function_dispatch'=>false,
			'use_common_configer'=>false,
				'fullpath_project_share_common'=>'',
			'use_common_autoloader'=>false,
				'fullpath_config_common'=>'',
			'use_strict_db'=>false,
			
			'use_facade'=>false,
			'facade_map'=>[],
			
			'session_auto_start'=>false,
			'session_name'=>'DNSESSION',
		];
	protected $has_enableFacade=false;
	public function afterInit($dn)
	{
		$dn=DNMVCS::G();
		$ext_options=$dn->options['ext'];
		
		$options=array_merge(self::DEFAULT_OPTIONS_EX,$ext_options);
		
		if($options['use_common_autoloader']){
			ProjectCommonAutoloader::G()->init($options)->run();
		}
		
		if($options['use_common_configer']){
			$dn->initConfiger(DNConfiger::G(ProjectCommonConfiger::G()));
			$dn->isDev=DNConfiger::G()->_Setting('is_dev')??$dn->isDev;
			// 可能要调整测试状态
		}
		if($options['use_function_view']){
			$dn->initView(DNView::G(FunctionView::G()));
		}
		if($options['use_strict_db']){
			DNDBManager::G()->setBeforeGetDBHandler([static::G(),'checkDBPermission']);
		}
		
		if($options['key_for_action']){
			SimpleRouteHook::G()->key_for_action=$options['key_for_action'];
			SimpleRouteHook::G()->key_for_module=$options['key_for_module'];
			DNRoute::G()->addRouteHook([SimpleRouteHook::G(),'hook']);
		}
		if($options['use_function_dispatch']){
			DNRoute::G()->addRouteHook([FunctionDispatcher::G(),'hook']);
		}
		if($options['session_auto_start']){
			DNMVCS::session_start(['name'=>$options['session_name']]);
		}
		
		if($options['use_facade']){
			$this->enableFacade();
		}
		
		
	}
	protected function enableFacade()
	{
		if($this->has_enableFacade){return;}
		$this->has_enableFacade=true;
		spl_autoload_register(function($class){
			if(!isset(DNMVCS::G()->options['namespace'])){ return; }
			$prefix=DNMVCS::G()->options['namespace'].'\\Facade\\';
			if(substr($class,0,strlen($prefix))!==$prefix){ return; }
			
			$blocks=explode('\\',$class);
			$basename=array_pop($blocks);
			$namespace=implode('\\',$blocks);
			
			$code="namespace $namespace{ class $basename extends \\DNMVCS\\FacadeBase{} }";
			eval($code);
		});
	}
	public function checkDBPermission()
	{
		if(!DNMVCS::Developing()){return;}
		
		$backtrace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,10);
		$caller_class='';
		foreach($backtrace as $i=>$v){
			if($v['class']===DNMVCS::class){
				$caller_class=$backtrace[$i+1]['class'];
				break;
			}
		}
		$namespace=DNMVCS::G()->options['namespace'];
		$namespace_controller=DNMVCS::G()->options['namespace_controller'];
		$default_controller_class=DNMVCS::G()->options['default_controller_class'];
		$namespace_controller.='\\';
		do{
			if($caller_class==$default_controller_class){
				DNMVCS::ThrowOn(true,"DB Can not Call By Controller");
			}
			if(substr($caller_class,0,strlen($namespace_controller))==$namespace_controller){
				DNMVCS::ThrowOn(true,"DB Can not Call By Controller");
			}
			if(substr($caller_class,0,strlen("$namespace\\Service\\"))=="$namespace\\Service\\"){
				DNMVCS::ThrowOn(true,"DB Can not Call By Service");
			}
			if(substr($caller_class,0-strlen("Service"))=="Service"){
				DNMVCS::ThrowOn(true,"DB Can not Call By Service");
			}
		}while(false);
	}
	public function _RecordsetUrl(&$data,$cols_map=[])
	{
		//need more quickly;
		if($data===[]){return $data;}
		if($cols_map===[]){return $data;}
		$keys=array_keys($data[0]);
		array_walk($keys,function(&$val,$k){$val='{'.$val.'}';});
		foreach($data as &$v){
			foreach($cols_map as $k=>$r){
				$values=array_values($v);
				$v[$k]=DNMVCS::URL(str_replace($keys,$values,$r));
				
			}
		}
		unset($v);
		return $data;
	}
	public function _RecordsetH(&$data,$cols=[])
	{
		if($data===[]){return $data;}
		$cols=is_array($cols)?$cols:array($cols);
		if($cols===[]){
			$cols=array_keys($data[0]);
		}
		foreach($data as &$v){
			foreach($cols as $k){
				$v[$k]=DNMVCS::H( $v[$k], ENT_QUOTES );
			}
		}
		return $data;
	}
	public function _ExitJson($ret)
	{
		DNMVCS::header('Content-Type:text/json');
		DNMVCS::G()->onBeforeShow([],'');
		echo json_encode($ret,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
		DNMVCS::exit_system();
	}
	public function _ExitRedirect($url,$only_in_site=true)
	{
		if($only_in_site && parse_url($url,PHP_URL_HOST)){
			//something  wrong
			DNMVCS::exit_system();
		}
		DNMVCS::header('location: '.$url);
		DNMVCS::header('',true,302);
		DNMVCS::G()->onBeforeShow([],'');
		DNMVCS::exit_system();
	}
	public function dealMapAndRewrite($route)
	{
		$route->addRouteHook([RouteHookMapAndRewrite::G(),'hook'],true); 
	}
}
//mysqldump -uroot -p123456 DnSample -d --opt --skip-dump-date --skip-comments | sed 's/ AUTO_INCREMENT=[0-9]*\b//g' >../data/database.sql

