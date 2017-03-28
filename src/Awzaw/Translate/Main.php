<?php

namespace Awzaw\Translate;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

    private $apikey;
    private $debug = false;
    public $queue = [];
    private $accessToken;
    private $toLanguage;
    public $enabled = [];
    private $languages;
    public $asp;
    private $allowConsole;

    public function onEnable() {

        if(file_exists($this->getDataFolder() . "config.yml")) {
            $c = $this->getConfig()->getAll();
            $this->toLanguage = $c["translate-to"] ?? "en";
            $this->allowConsole = $c["allow-console"] === "true" ? true : false;
            $this->languages = $c["languages"] ?? "ar, de, en, fi, fr, he, ht, hi, id, it, ja, ko, , ms, ur, es, ru, zh-CHS, zh-CHT";
            $this->testStr = $c["test-string"] ?? "Bonjour tout le monde!";
            if(isset($c["API-Key"])) {
                if(trim($c["API-Key"]) != "") {
                    $this->apikey = trim($c["API-Key"]);
                } else {
                    $this->getLogger()->info("Missing Windows AZURE API Key");
                }
            }
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->asp = $this->getServer()->getPluginManager()->getPlugin("AntiSpamPro");
        if(!$this->asp) {
            $this->getLogger()->info("Unable to find AntiSpamPro");
        }
        try {

            $this->accessToken = $accessToken = $this->getToken($this->apikey);

            $params = "text=" . urlencode($this->testStr) . "&to=" . $this->toLanguage . "&appId=Bearer+" . $accessToken;
            $translateUrl = "http://api.microsofttranslator.com/v2/Http.svc/Translate?$params";
            $curlResponse = $this->curlRequest($translateUrl);

            $translated = strip_tags($curlResponse);
            $this->getLogger()->info("Translation Working... " . $this->testStr . " : " . $translated);

        } catch(Exception $e) {
            $this->getLogger()->info("Exception: " . $e->getMessage() . PHP_EOL);
        }

    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch(strtolower($command->getName())) {
            case "translate" || "tra":
                if (!$this->allowConsole && $sender instanceof ConsoleCommandSender) {
                    $this->getLogger()->info("Enable allow-console in Translate config.yml to translate say on console");
                    return true;
                }
                if((isset($args[0])) && (strtolower($args[0]) === "list" || strtolower($args[0]) === "help")) {
                    $sender->sendMessage(TEXTFORMAT::GREEN . $this->languages);
                    return true;
                }
                $lang = "";
                if(isset($args[0]) && isset($this->enabled[strtolower($sender->getName())])) {
                    $lang = $args[0];
                    $this->enabled[strtolower($sender->getName())] = ["name" => strtolower($sender->getName()), "lang" => $lang];
                    $sender->sendMessage(TEXTFORMAT::GREEN . "Translation language set to: $lang");
                    return true;
                }

                if(isset($this->enabled[strtolower($sender->getName())])) {
                    unset($this->enabled[strtolower($sender->getName())]);
                } else {
                    $lang = isset($args[0]) ? $args[0] : $this->toLanguage;
                    echo "Enabled for: " . $sender->getName() . "\n";
                    $this->enabled[strtolower($sender->getName())] = ["name" => strtolower($sender->getName()), "lang" => $lang];
                }

                if(isset($this->enabled[strtolower($sender->getName())])) {
                    $sender->sendMessage(TEXTFORMAT::GREEN . strtolower($sender->getName()) . " started $lang translation. Stop with /translate");
                } else {
                    $sender->sendMessage(TEXTFORMAT::RED . "You stopped translation. Start with /translate");
                }
                return true;

            default:
                break;

        }
        return true;
    }


    public function onPlayerChat(PlayerChatEvent $event) {
        if($event->isCancelled()) return;
        if(!isset($this->enabled[strtolower($event->getPlayer()->getName())])) {
            return;
        }
        $message = $event->getMessage();
        if(!isset($message) || $message == "") {
            return;
        }
        $event->setCancelled(true);
        if(isset($this->queue[strtolower($event->getPlayer()->getName())])) {
            $event->getPlayer()->sendMessage("[Translate] Please wait for the last translation to finish");
            return;
        }

        $this->queue[] = strtolower($event->getPlayer()->getName());
        $query = new RequestThread($this->accessToken, strtolower($event->getPlayer()->getName()), $message, $this->enabled[strtolower($event->getPlayer()->getName())]["lang"]);
        $this->getServer()->getScheduler()->scheduleAsyncTask($query);
    }

    public function onServerCommand(ServerCommandEvent $event) {
        if($event->isCancelled()) return;
        if(!isset($this->enabled[strtolower($event->getSender()->getName())])) {
            return;
        }
        $name = $event->getSender()->getName();
        $message = $event->getCommand();
        if(!isset($message) || substr($message, 0, 4) !== "say ") {
            return;
        }
        if(isset($this->queue[strtolower($name)])) {
            $event->getPlayer()->sendMessage("[Translate] Please wait for the last translation to finish");
            return;
        }
        $event->setCancelled(true);
        $message = substr($message, 4);
        $this->queue[] = strtolower($name);
        $query = new RequestThread($this->accessToken, strtolower($name), $message, $this->enabled[strtolower($name)]["lang"]);
        $this->getServer()->getScheduler()->scheduleAsyncTask($query);
    }


    public function onQuit(PlayerQuitEvent $e) {
        if(isset($this->queue[strtolower($e->getPlayer()->getName())])) {
            unset($this->queue[strtolower($e->getPlayer()->getName())]);
        }
        if(isset($this->enabled[strtolower($e->getPlayer()->getName())])) {
            unset($this->enabled[strtolower($e->getPlayer()->getName())]);
        }
    }

    public function sayTranslated($player, $totranslate, $translation, $error) {

        if($error || strpos($translation, "token has expired") > 0) {
            // Get a new Token
            $this->getLogger()->info("Fetching a new Translation Token");
            $this->accessToken = $accessToken = $this->getToken($this->apikey);
            // Try again
            $this->queue[] = $player;
            $query = new RequestThread($this->accessToken, $player, $totranslate, $this->enabled[$player]["lang"]);
            $this->getServer()->getScheduler()->scheduleAsyncTask($query);
            return;
        }

        if(strpos($translation, "toMessage") > 0) {
            $this->getServer()->getPlayerExact($player)->sendMessage(TEXTFORMAT::RED . "Invalid From Language: /translate list shows all languages");
            $this->getLogger()->info($translation);

            if(isset($this->enabled[$player])) {
                unset($this->enabled[$player]);
                $this->getServer()->getPlayerExact($player)->sendMessage(TEXTFORMAT::RED . "Translation stopped. Start with /translate");
            }
            return;
        }
        if(strpos($translation, "fromMessage") > 0) {
            $translation = $totranslate;
        }
        if($this->asp && $this->asp->getProfanityFilter() && $this->asp->getProfanityFilter()->hasProfanity($translation)) {
            $this->getServer()->getPlayerExact($player)->sendMessage(TEXTFORMAT::RED . "No Swearing");
            return;
        }
        if($this->getServer()->getPlayerExact($player) instanceof Player) {
            $this->getServer()->broadcastMessage("<" . $this->getServer()->getPlayerExact($player)->getName() . "> : " . $translation);
        } elseif($player == "console") {
            $this->getServer()->broadcastMessage("[SERVER] : " . $translation);
        } else {
            $this->getLogger()->info($translation);
        }
        return;
    }

    function getToken($azure_key) {
        $ch = curl_init();

        if($this->debug) {
            ob_start();
            $out = fopen('php://output', 'w');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $out);
        }

        $url = 'https://api.cognitive.microsoft.com/sts/v1.0/issueToken';
        $data_string = json_encode('{body}');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                'Ocp-Apim-Subscription-Key: ' . $azure_key
            ]
        );
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $strResponse = curl_exec($ch);
        curl_close($ch);

        if($this->debug) {
            fclose($out);
            $debug = ob_get_clean();
            echo $debug;
        }
        return $strResponse;
    }

    function curlRequest($url) {
        $ch = curl_init();

        if($this->debug) {
            ob_start();
            $out = fopen('php://output', 'w');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $out);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $curlResponse = curl_exec($ch);
        curl_close($ch);

        if($this->debug) {
            fclose($out);
            $debug = ob_get_clean();
            echo $debug;
        }

        return $curlResponse;
    }

}


