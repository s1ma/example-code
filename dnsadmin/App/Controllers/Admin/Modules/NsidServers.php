<?php

use Frame\Core\EntityManager;
use Frame\Core\Router;


use App\Controllers\Admin\Modules\AppController as AppController;


final class moduleNsidServers extends AppController
{

	public function executeIndex()
	{
		if($this->request->get('nsid'))
		{
			$this->nsidPrimary();
		} elseif($this->request->get('sid'))
		{
			$this->sidPrimary();
		} else
		{
			$this->show404();
		}

	}

	private function nsidPrimary()
	{
		$url = $this->request->getUrl(array('nsid' => $this->request->get('nsid')));
		$this->nsid = $this->request->get('nsid');

		$nsserver = EntityManager::findOneBy('NsServers', array('nsid' => $this->nsid, 'user_id' => $this->security->getUser()->user_id));

		if(!$nsserver)
		{
			$this->show404();
		}

		if($this->request->isPost())
		{

			$nsserver->sids = $this->request->post('sids');
			$nsserver->saveSids();

			$response = array();
			$response['eval'] = sprintf('layoutList.showAlertSuccess("Nsid -> Sid edited. Go to <a href=\"%s\">NS Servers List</a>");', Router::getUrl('ns-servers'));

			return $this->jsonResponse($response);
		}

		$usedSids = $nsserver->getSids();
		$usedSidsArray = array();

		foreach($usedSids as $usedSid)
		{
			$usedSidsArray[] = "'".$usedSid->sid."'";
		}

		$usedSidsArray = !empty($usedSidsArray) ? implode(',', $usedSidsArray) : "''";
		$servers = EntityManager::find('Servers', array('user_id' => $this->security->getUser()->user_id))->andWhere("`sid` not in ({$usedSidsArray})");

		return $this->render( 'Admin/Templates/Pages/NsidServers/nsid.tpl', array('url' => $url,'nsserver' => $nsserver, 'servers' => $servers->fetch(), 'serversUse' => $usedSids) );

	}


	private function sidPrimary()
	{
		$url = $this->request->getUrl(array('sid' => $this->request->get('sid')));
		$this->sid = $this->request->get('sid');

		$server = EntityManager::findOneBy('Servers', array('sid' => $this->sid, 'user_id' => $this->security->getUser()->user_id));

		if(!$server)
		{
			$this->show404();
		}

		if($this->request->isPost())
		{

			$server->nsids = $this->request->post('nsids');
			$server->saveNsids();

			$response = array();
			$response['eval'] = sprintf('layoutList.showAlertSuccess("Sid -> nsid edited. Go to <a href=\"%s\">Servers List</a>");', Router::getUrl('servers'));

			return $this->jsonResponse($response);
		}


		$usedNsids = $server->getNsids();
		$usedNsidsArray = array();

		foreach($usedNsids as $usedNsid)
		{
			$usedNsidsArray[] = "'".$usedNsid->nsid."'";
		}

		$usedNsidsArray = !empty($usedNsidsArray) ? implode(',', $usedNsidsArray) : "''";
		$nsservers = EntityManager::find('NsServers', array('user_id' => $this->security->getUser()->user_id))->andWhere("`nsid` not in ({$usedNsidsArray})")->groupBy("`nsid`");

		return $this->render( 'Admin/Templates/Pages/NsidServers/sid.tpl', array('url' => $url,'server' => $server,'nsserversUse' => $usedNsids,'nsservers' => $nsservers->fetch()) );
	}

}