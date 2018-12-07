<?php
namespace DNMVCS;

class RouteHookSuperGlobal
{
	use DNSingleton;
	public function hook($route)
	{
		$path=DNMVCS::G()->options['path'];
		if(!SuperGlobal::SERVER('DOCUMENT_ROOT')){
			SuperGlobalSet::SetSERVER('DOCUMENT_ROOT',$path.'www');
		
		}
		if(!SuperGlobal::SERVER('SCRIPT_FILENAME')){
			SuperGlobalSet::SetSERVER('SCRIPT_FILENAME',$path.'www/index.php');
		}
		$route->script_filename=SuperGlobal::SERVER('SCRIPT_FILENAME')??'';
		$route->document_root=SuperGlobal::SERVER('DOCUMENT_ROOT')??'';
		$route->request_method=SuperGlobal::SERVER('REQUEST_METHOD')??'';
		$route->path_info=SuperGlobal::SERVER('PATH_INFO')??'';
		
		$route->path_info=ltrim($route->path_info,'/');
	}
}