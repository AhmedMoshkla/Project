<?php
/*
| -----------------------------------------------------
| PRODUCT NAME: 	Onzwo
| -----------------------------------------------------
| AUTHOR:			ONZWO.COM
| -----------------------------------------------------
| EMAIL:			info@ONZWO.COM
| -----------------------------------------------------
| COPYRIGHT:		RESERVED BY ONZWO.COM
| -----------------------------------------------------
| WEBSITE:			http://ONZWO.COM
| -----------------------------------------------------
*/
class ModelSetting extends Model 
{
	public function get($id = 1)
	{
		$statement = $this->db->prepare("SELECT *  FROM `settings` WHERE `id` = ?");
		$statement->execute(array($id));
		$result = $statement->fetch(PDO::FETCH_ASSOC);
		return $result;
	}

	public function getSMSSetting($type)
	{
		$statement = $this->db->prepare("SELECT *  FROM `sms_setting` WHERE `type` = ?");
		$statement->execute(array($type));
		return $statement->fetch(PDO::FETCH_ASSOC);
		
	}

	public function isUpdateAvailable()
	{
		$setting = $this->get();
		return $setting['is_update_available'];

	}
}