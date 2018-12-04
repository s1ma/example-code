<?php
namespace App\Model\Admin;

use Frame\Core\Object as Object;
use Frame\Core\Config as Config;
use Frame\Core\EntityManager;
use Frame\Core\Db;

final class Domains extends Object
{
	public function getRecords($type = null)
	{
		$array = ['domain_id' => $this->id, 'delete' => 0];
		if($type)
		{
			$array['type'] = $type;
		}
		return EntityManager::find('Records', $array)->fetch();
	}
	
	public function remove_domain()
	{
		foreach($this->getRecords() as $record)
		{
			$record->delete = 1;
			$record->save();
		}
		
		return EntityManager::delete('Domains', array('id' => $this->id));
	}
	
	public function save()
	{
		$db = Db::getInstance('master');
		
		if(isset($this->changes['nsid']) && isset($this->data['nsid']) && $this->changes['nsid'] != $this->data['nsid'])
		{
			$db->query("UPDATE `dns-records` SET `nsid` = '{$this->changes['nsid']}' where `domain_id` = '{$this->data['id']}'");
		}
		
		$id = parent::save();
		$this->id = isset($this->id) ? $this->id : $id;
		
		$nsservers = EntityManager::find('NsServers', array('nsid' => $this->nsid, 'user_id' => $this->user_id))->fetch();
		$soa_content = "{$nsservers[0]->get('name')} hostmaster.{$this->name} ".date('YmdH')." 10800 15 604800 300";
		$soa = $db->getOne("select * from `dns-records` where `domain_id` = :domain_id and `type` = 'SOA'", array('domain_id' => $this->id), '\App\Model\Admin\Records');
		
		
		if($soa)
		{
			$soa->nsid = $this->nsid;
			$soa->content = $soa_content;
			$soa->save();
		} else {
			$soa_query = "({$this->user_id}, '{$this->nsid}', {$this->id}, '{$this->name}',  'SOA', '{$soa_content}', 3600, 0)";	
			$db->query("
						INSERT INTO 
								`dns-records` 
									(`user_id`, `nsid`, `domain_id`, `name`, `type`, `content`, `ttl`, `prio`)
								VALUES
								{$soa_query}
					   ");
		}
		
		$set_nsservers = EntityManager::find('Records', array('domain_id' => $this->id, 'type' => 'NS'))->fetch();
		
		if($set_nsservers)
		{
			foreach($set_nsservers as $set_ns)
			{
				if(count($nsservers) > 0)
				{
					$nsserver = array_shift($nsservers);
					
					$set_ns->nsid = $this->nsid;
					$set_ns->content = $nsserver->get('name');
					$set_ns->delete = 0;
					$set_ns->save();
				} else {
					$set_ns->delete = 1;
					$set_ns->save();
				}
			}
		}
		
		if(count($nsservers) > 0)
		{
			foreach($nsservers as $nsserver)
			{
				$ns = "({$this->user_id}, '{$this->nsid}', {$this->id}, '{$this->name}',  'NS', '{$nsserver->get('name')}', 3600, 0)";
				
				$db->query("
					INSERT INTO 
							`dns-records` 
								(`user_id`, `nsid`, `domain_id`, `name`, `type`, `content`, `ttl`, `prio`)
							VALUES
								{$ns}
				");
			}
		}

		return $id;
	}
} 