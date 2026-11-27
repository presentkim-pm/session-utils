<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests\Fixture;

use kim\present\utils\session\listener\attribute\SessionEventHandler;
use kim\present\utils\session\Session;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;

/** Task session that records its lifecycle into a shared log. */
class RecordingSession extends Session{

    /** @var list<string> */
    public static array $log = [];

    /** @var list<string> */
    public array $events = [];

    public bool $failOnStart = false;

    public bool $failOnTerminate = false;

    protected function onStart() : void{
        self::$log[] = "start";
        if($this->failOnStart){
            throw new \RuntimeException("start boom");
        }
    }

    protected function onTerminate(string $reason) : void{
        self::$log[] = "onTerminate:$reason";
        if($this->failOnTerminate){
            throw new \RuntimeException("terminate boom");
        }
    }

    #[SessionEventHandler(PlayerChatEvent::class)]
    public function onChat(PlayerChatEvent $event) : void{
        $this->events[] = "chat";
    }

    /** Not annotated: must never be dispatched. */
    public function onOther(BlockBreakEvent $event) : void{
        $this->events[] = "other";
    }
}
