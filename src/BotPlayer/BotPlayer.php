<?php
namespace BotPlayer;

use pocketmine\entity\Human;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\{CompoundTag, DoubleTag, FloatTag, ListTag, StringTag};
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\utils\UUID;

class BotPlayer extends PluginBase implements Listener {

    public string $worldName = "world";
    public string $spawnPos = "1474.2 64 484.3";
    public float $moveSpeed = 0.45;
    public string $dir; 

    private array $words = [
        "ru" => [
            "start" => ["слышь", "админ", "чел", "ку", "эй", "сука", "бля", "мамонт", "урод", "кто", "го", "пацаны", "э", "слышьте"],
            "action" => ["грифер", "лох", "пвп", "рейд", "андромеда", "скинь коры", "отсоси", "верни вещи", "читер", "крыса", "софт", "база", "алмазку", "сундук"],
            "end" => ["ебаный", "0", "на спавне", "быстро", "зассал?", "в канаве", "хуйня", "топ", "сосать", "пж", "даун", "чекай", "пидор"]
        ],
        "tr" => [
            "start" => ["lan", "oç", "kanka", "sa", "admin", "beyler", "alo", "sg", "velet", "kim", "gel", "amk", "lanet"],
            "action" => ["pipi", "ananı sikeyim", "grief", "pvp", "hile", "item", "klan", "tokatlandın", "ezik", "set", "tnt", "gel buraya", "maden"],
            "end" => ["siktir git", "helal", "31 çekme", "velet", "gel spawn", "atmam", "çöp", "mali", "bok", "yok", "ebat", "amık"]
        ]
    ];

    public array $prefixes = [
        '§fИгрок', '§bВип', '§dПремиум', '§3Креатив', '§2Модератор',
        '§aАдмин', '§l§eСτрαж', '§l§cПɋτρиαρх', '§l§9Тиρɋн',
        '§l§aИмπϱρατѻρ', '§l§6Аρυсτѻкρаτ', '§l§cМϱτеѻρит', '§l§9Андромеда'
    ];

    private array $availableNicks = [
        "StevePro", "Killer_007", "Mr_Nikita", "Dragon_PvP", "Top_Player", "Mine_Master", "Super_Gamer", "Legend_Play", "Mega_Bot", 
        "Frost_Bite", "Zloy_Krolik", "Pvp_Machine", "FixEye", "Donater_1", "Shadow", "Rider", "Panda_MC", "Keks_PvP", "Wanderer", 
        "GhosT_RidER", "Master_Chef", "Reis_34", "Baba_Pro", "Aslan_YT", "Kral_PvP", "Deli_Yurek", "Rus_Player", "Serega_MC", 
        "Tnt_Lover", "Griefer_228", "Bot_1", "Hacker_Pro", "Pro_Noob", "King_Grief", "Almaz_Top", "Mehmet_TR", "Emre_Pvp", "Mustafa_Pro",
        "Akmurat_20", "Serdar_TM", "Begench_Grief", "Yhlas_Pvp", "Turkmen_Power", "Dovlet_Top", "Batyr_TM", "Grifer_TM", "Turkmen_Guli", "Maksat_Pvp"
    ];

    private array $activeBotData = [];

    public function onEnable() : void {
        $this->dir = $this->getDataFolder() . "skin/";
        if(!file_exists($this->dir)) @mkdir($this->dir, 0777, true);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void {
            for($i = 0; $i < 10; $i++){
                $this->spawnBot();
            }
            $this->getLogger()->info("§a[BotPlayer] Стартовые 14 ботов загружены!");
        }), 10);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            if(count($this->activeBotData) < 28) {
                $this->spawnBot();
            }
        }), 2400);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach($this->activeBotData as $id => $data) {
                if($data['entity'] instanceof Human && !$data['entity']->isClosed()) {
                    $this->broadcastTabUpdate($data['entity'], PlayerListPacket::TYPE_ADD);
                }
            }
        }), 200); 

        $this->startChatLoop();
    }

    private function generateSmartMessage(string $lang) : string {
        $s = $this->words[$lang]["start"];
        $a = $this->words[$lang]["action"];
        $e = $this->words[$lang]["end"];
        return $s[array_rand($s)] . " " . $a[array_rand($a)] . " " . $e[array_rand($e)];
    }

    private function startChatLoop() : void {
        $delay = mt_rand(100, 300); 
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() : void {
            if(!empty($this->activeBotData)) {
                $id = array_rand($this->activeBotData);
                if(isset($this->activeBotData[$id])) {
                    $lang = $this->activeBotData[$id]['lang']; 
                    $this->sendBotMessage($id, $this->generateSmartMessage($lang));
                }
            }
            $this->startChatLoop();
        }), $delay);
    }

    private function sendBotMessage(int $id, string $message) : void {
        if(!isset($this->activeBotData[$id])) return;
        $data = $this->activeBotData[$id];
        $prefix = $data['prefix'];
        $textColor = "§7"; 
        
        if(strpos($prefix, 'Сτрαж') !== false) $textColor = "§l§e";
        elseif(strpos($prefix, 'Пɋτρиαρх') !== false) $textColor = "§l§c";
        elseif(strpos($prefix, 'Тиρɋн') !== false) $textColor = "§l§9";
        elseif(strpos($prefix, 'Имπϱρατѻρ') !== false) $textColor = "§l§a";
        elseif(strpos($prefix, 'Аρυсτѻкρаτ') !== false) $textColor = "§l§6";
        elseif(strpos($prefix, 'Мϱτеѻρит') !== false) $textColor = "§l§c";
        elseif(strpos($prefix, 'Андромеда') !== false) $textColor = "§l§9";
        
        $msg = "§cG §7| {$prefix} §r§7| §f{$data['base']} §6>> {$textColor}{$message}";
        $this->getServer()->broadcastMessage($msg);
    }

    public function spawnBot() : void {
        $level = $this->getServer()->getLevelByName($this->worldName);
        if($level === null || empty($this->availableNicks)) return;
        
        $coords = explode(' ', $this->spawnPos);
        $files = array_diff(scandir($this->dir), ['..', '.']);
        if(empty($files)) return;
        
        $skinData = @file_get_contents($this->dir . $files[array_rand($files)]);
        if(!$skinData) return;

        $nickKey = array_rand($this->availableNicks);
        $baseNick = $this->availableNicks[$nickKey];
        unset($this->availableNicks[$nickKey]);
        $prefix = $this->prefixes[array_rand($this->prefixes)];
        
        $botLang = (mt_rand(1, 100) <= 70) ? "tr" : "ru";

        $nbt = new CompoundTag("", [
            "Pos" => new ListTag("Pos", [new DoubleTag("", (float)$coords[0]), new DoubleTag("", (float)$coords[1]), new DoubleTag("", (float)$coords[2])]),
            "Rotation" => new ListTag("Rotation", [new FloatTag("", (float)mt_rand(0, 360)), new FloatTag("", 0.0)]),
            "Skin" => new CompoundTag("Skin", ["Data" => new StringTag("Data", $skinData), "Name" => new StringTag("Name", "Standard_Custom")])
        ]);

        $bot = new Human($level, $nbt);
        $bot->setNameTag("§l§l" . $prefix . " §f" . $baseNick);
        $bot->setNameTagAlwaysVisible(true);
        $bot->spawnToAll();

        $this->activeBotData[$bot->getId()] = [
            "base" => $baseNick, "prefix" => $prefix, "uuid" => UUID::fromRandom(), 
            "skin" => $bot->getSkin(), "entity" => $bot, "lang" => $botLang
        ];
        
        $this->broadcastTabUpdate($bot, PlayerListPacket::TYPE_ADD);
        $this->runBotAI($bot, new Vector3((float)$coords[0], (float)$coords[1], (float)$coords[2]), (float)mt_rand(0, 360));
    }

    private function broadcastTabUpdate(Human $bot, int $type) : void {
        if(!isset($this->activeBotData[$bot->getId()])) return;
        $data = $this->activeBotData[$bot->getId()];
        
        $pk = new PlayerListPacket();
        $pk->type = $type;
        $entry = new PlayerListEntry();
        $entry->uuid = $data["uuid"];
        $entry->entityUniqueId = $bot->getId();
        $entry->username = "§l§l" . $data['prefix'] . " §f" . $data['base'];
        $entry->skin = $data["skin"];
        $pk->entries[] = $entry;
        
        foreach($this->getServer()->getOnlinePlayers() as $player) $player->sendDataPacket($pk);
    }

    public function runBotAI(Human $bot, Vector3 $spawn, float $yaw) : void {
        if($bot->isClosed()) return;

        $dirVec = $bot->getDirectionVector();
        $frontBlock = $bot->getLevel()->getBlock($bot->add($dirVec->x * 1.2, 0.5, $dirVec->z * 1.2));
        if(!$frontBlock->isTransparent() && $bot->isOnGround()) $bot->jump();
        
        if($bot->distance($spawn) > 20) {
            $angle = atan2($spawn->z - $bot->z, $spawn->x - $bot->x);
            $yaw = (float)(rad2deg($angle) - 90);
        }
        
        $bot->setRotation($yaw, 0);
        $x = -sin(deg2rad($yaw)) * $this->moveSpeed;
        $z = cos(deg2rad($yaw)) * $this->moveSpeed;
        $bot->setMotion(new Vector3($x, $bot->getMotion()->getY(), $z));
        
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($bot, $spawn, $yaw) : void {
            if(!$bot->isClosed()) $this->runBotAI($bot, $spawn, $yaw);
        }), 4);
    }

    public function onJoin(PlayerJoinEvent $ev) : void {
        $p = $ev->getPlayer();
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($p) : void {
            if(!$p->isOnline()) return;
            foreach($this->activeBotData as $id => $data) {
                $pk = new PlayerListPacket();
                $pk->type = PlayerListPacket::TYPE_ADD;
                $entry = new PlayerListEntry();
                $entry->uuid = $data["uuid"];
                $entry->entityUniqueId = $id;
                $entry->username = "§l§l" . $data['prefix'] . " §f" . $data['base'];
                $entry->skin = $data["skin"];
                $pk->entries[] = $entry;
                $p->sendDataPacket($pk);
            }
        }), 40);
    }

    public function onQuery(QueryRegenerateEvent $ev) : void {
        $ev->setPlayerCount(count($this->getServer()->getOnlinePlayers()) + count($this->activeBotData));
    }

    public function onDamage(EntityDamageEvent $ev) : void {
        if($ev->getEntity() instanceof Human && isset($this->activeBotData[$ev->getEntity()->getId()])) {
            $ev->setCancelled();
        }
    }
}