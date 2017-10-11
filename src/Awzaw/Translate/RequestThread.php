<?php

namespace Awzaw\Translate;

use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;

class RequestThread extends AsyncTask{

	private $playerName;
	private $totranslate;
	private $accesstoken;
	private $translation;
	private $toLanguage;
	private $error;

	public function __construct($accesstoken, $playerName, $totranslate, $toLanguage){
		$this->totranslate = $totranslate;
		$this->playerName = $playerName;
		$this->accesstoken = $accesstoken;
		$this->toLanguage = $toLanguage;
	}

	public function onRun(){
		try{

			$params = "text=" . urlencode($this->totranslate) . "&to=" . $this->toLanguage . "&appId=Bearer+" . $this->accesstoken;
			$translateUrl = "http://api.microsofttranslator.com/v2/Http.svc/Translate?$params";
			$curlResponse = $this->curlRequest($translateUrl);
			$this->translation = strip_tags($curlResponse);

		}catch(\RuntimeException $e){
			$this->error = true;
		}
	}

	public function onCompletion(Server $server){

		$server->getPluginManager()->getPlugin("Translate")->sayTranslated($this->playerName, $this->totranslate, $this->translation, $this->error);
		array_splice($server->getPluginManager()->getPlugin("Translate")->queue, array_search($this->playerName, $server->getPluginManager()->getPlugin("Translate")->queue, true), 1);
	}

	public function curlRequest($url) : string{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$curlResponse = curl_exec($ch);
		curl_close($ch);
		return $curlResponse;
	}


}
