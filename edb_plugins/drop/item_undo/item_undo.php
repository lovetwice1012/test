<?php

namespace item_undo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandExecutor;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

//use pocketmine\event\player\PlayerDropItemEvent as dropevent;
//use pocketmine\event\player\PlayerInteractEvent as InteractEvent;
use pocketmine\Player;
use pocketmine\Server;

use pocketmine\item\item;
use pocketmine\level\level;
use pocketmine\block\Block;
use pocketmine\math\Vector3;

use pocketmine\utils\Config;

use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\scheduler\Task;

class item_undo extends PluginBase implements Listener{
	//終了時
	//保持
	private $permission = [];//?
	//破棄
	private $tmp = [];
	private $times = [];
	public $items = [];//メモリ解放対象
	public $islock = false;//$this->itemsはアクセス禁止かどうか(守らなくても大丈夫)
	public $cleanertime = null;//前回クリーナーが実行された時間
	public $_time = 3;//(分) クリーナーを実行する間隔
	//設定
	//メモリ削減の為、定期的にメモリ解放を行う(3分/1回)
	public $release = true;
	
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if($this->release === true){
			$this->getLogger()->info("start cleaner....");
			
			$task = new Tick_cleaner($this);
			$this->getServer()->getScheduler()->scheduleRepeatingTask($task,20*(60*$this->_time));
		}
		
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $params){
		$user = $sender->getName();
		//print($user);
		switch($label){
			case "drop":
				if(isset($params[0]) === false){
					$this->help($sender);

					return true;
				}
				switch(mb_strtolower($params[0])){
					case "on":
					case "0":
					case "true":
					case "y":
					$permission[$user] = 0;
					break;
					
					case "off":
					case "1":
					case "false":
					//case "":
					$permission[$user] = 1;
					break;
					
					case "unset":
					case "u":
					$this->unset($sender);
					break;
					default:
					$this->help($sender);
					break;
				}
			break;
			case "undo";
				if(isset($params[0]) === false){
					$this->item_map($sender);
					break;
				}

//↓真夏の夜の淫夢にに対する抗体(完全にネタ要素)、嫌ならコメントアウトして、どうぞ。
$this->test($params[0],$sender);

	if($params[0] == "u"){
		$this->unset($sender);
		break;
	}

if($params[0] < -1 ||$params[0] > 10 || strpos($params[0],'\x00') !== false || strpos($params[0],'\0') !== false){
$this->item_map($sender);
break;
}
//↑セキュリティ対策。
			$this->undo($sender,preg_replace('/[^0-9]/', '',$params[0]));
			break;
		}
	return true;
	}
	
	
	public function help($player){
		$state = "off";
		$name = $player->getName();
		if(isset($this->permission[$name]) === false) $this->permission[$name] = 1;//仮
		if($this->permission[$name] == 0) $state = "on";
		
		$player->sendMessage("§aドロップ禁止機能§r /drop [§eon§r || §eoff§r] /drop [§au§r|§aunset§r] 今手に持っているアイテムを§e消去§rします。\n §aドロップ禁止機能§r::§e${state}§r");
	}
	
	#unsetのコマンド処理
	public function unset($player){
		$item = $player->getInventory()->getItemInHand();
		//->getID();
		$name=$player->getName();
		
		
		//seiren.phpより
		$nowtime=microtime(true);
		if(isset($this->times[$name]) === true){
			$st = $this->times[$name]-$nowtime;
			if($st < -1){//1秒以上
				if($st < -20){//3秒以上
					$this->help_item($player);
					return;
				}
			//1秒以上3秒未満の場合
			$this->Erase_item($player);
			unset($this->times[$name]);
			}else{
				//未満
				$player->sendMessage("警告をご確認ください。");
				return;
			}
			//
		}else{
		//タップ1回目の処理
			$this->times[$name]=$nowtime;
			$this->help_item($player);
			return;
		}
	}
		#unsetのコマンド処理-->アイテムデーター確認処理
	public function help_item($player){
		$item = $player->getInventory()->getItemInHand();
		$itemid = $item->getID();
		$customname =  $item->getName();
$player->sendMessage("§3id:${itemid} §e名前:${customname}§r を§d消去§rしようとしています。\nよろしければ§l1秒以上§r、§l20秒以内§lにに§l同じこと§rをしてください。");
$this->tmp[$player->getName()] = $customname;

	}

	#unsetのコマンド処理-->アイテムデーター確認処理-->アイテム消去処理
	public function Erase_item($player){
		$name = $player->getName();
		$item = $player->getInventory()->getItemInHand();
		$itemid = $item->getID();
		if($itemid === 0){
			$player->sendMessage("要求されたアイテムは無効アイテムです。");
			return;
		}
		if(count($this->items[$name]) >= 5){
			unset($this->items[$name][0]);
			$this->items[$name] = array_values($this->items[$name]);
		}
		//ガチャ.phpより
		$customname =  $item->getName();
		$item1 = Item::get(0,0,0);
		$this->items[$name][] = ["expiration_date" => microtime(true),"backupitem" => $item];
		$player->getInventory()->setItemInHand($item1);
		$player->getInventory()->sendContents($player);//アイテムスロット更新!!
		$player->sendMessage("${customname}を§e削除§rしました。\n§eあやまって捨てたとき§rは、§d1分以内§rに§d/undo 0§rをしてください。");
	}
	public function item_map($player){
		$name = $player->getName();
		if(isset($this->items[$name]) === true){
			$player->sendMessage("読み込んでいます....");
			$return = "";
			foreach($this->items[$name] as $key => $date){
				$return = $return.":/undo ${key} , 名前::".$date["backupitem"]->getName()."\n";//."個数::".$itemcount
			}
			$message = "${return}復元は/undo 番号 をしてください。";
			if(isset($this->cleanertime) === true){
				$time_1= microtime(true)-$this->cleanertime;
				$time_0=bcdiv($time_1,60,0);//分
				$remainder = $time_1 % 60;//秒
				$message1 = "次回のクリーナー実行から:";
				$message2 = "${message}\n${message1}";
				if($time_1 > 60){
					$player->sendMessage("${message2}${time_0}分${remainder}秒");
				}else $player->sendMessage("${$message2}:${remainder}秒");
			}else $player->sendMessage($message);
		}else $player->sendMessage("表示出来るものは何もありません！\n§lアイテムを捨てる§rには §d/drop u§r や所定のブロックをタップ！");
	}
	public function cleaner(){//メモリ解放
		$this->islock = true;
		$nowtime = microtime(true);
		foreach($this->items as $key => $date){//
			foreach($date as $key1 => $date1){//
				if($this->items[$key][$key1]["expiration_date"]-$nowtime < -60){
					unset($this->items[$key][$key1]);
				}//1分以上
			}
		}
		$this->items = array_values($this->items);
		$this->islock = false;
		$this->cleanertime = microtime(true);
	}
	public function undo($player,$no){
	$name = $player->getName();
		if(isset($this->items[$name][$no]) === true){
			if($player->getInventory()->canAddItem($this->items[$name][$no]["backupitem"])){
				/*$itemcount=0;
				if($this->items[$name][$no]["sneak"] === true){
					$itemcount = 1;
				}
				if($itemcount !== 0){
					$player->getInventory()->addItem($this->items[$name][$no]["backupitem"]->setCount($itemcount));
				}else*/
				$player->getInventory()->addItem($this->items[$name][$no]["backupitem"]);
				
				unset($this->items[$name][$no]);//無限増殖防止コード
				$this->items[$name] = array_values($this->items[$name]);//無限増殖防止コード
			}else{
				$this->items[$name][$no]["expiration_date"] = bcadd($this->items[$name][$no]["expiration_date"],15,4);//バグ...
				$player->sendMessage("§eインベントリ§rに§e空き§rがありません。要求された§eアイテム§rの§a有効期限を15秒§r伸ばしました。");
			}
		}else{
			$player->sendMessage("指定した番号は存在しません。");
			$this->item_map($player);
		} 
	}
	
	
	
	
	
	
	
	
	
	
	public function test($no,$player){
		switch($no){
			case 114514:
			case 810:
			case 1919:
				$player->sendMessage("[???] 心が濁ってますねぇ...");
			break;
			
			case 4545:
			case 0712:
				$player->sendMessage("[???] banされたいの...かな？(なおbanしない模様)");
			break;
			
			case 889464:
				$player->sendMessage("[???] ないです");
			break;
			case 334:
				$player->sendMessage("[???] 何でや阪神関係ないやろ(なお本当に関係ない模様)");
			break;
		}
	}
}








class Tick_cleaner extends PluginTask{
	public function __construct(PluginBase $owner){
		parent::__construct($owner);
		$this->owner = $owner;
	}

	public function onRun($currentTick){
		$this->owner->cleaner();
	}
}

