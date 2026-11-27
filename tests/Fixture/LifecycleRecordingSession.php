<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests\Fixture;

use kim\present\utils\session\LifecycleSession;
use kim\present\utils\session\listener\attribute\SessionEventHandler;
use kim\present\utils\session\Session;
use pocketmine\event\player\PlayerChatEvent;

final class LifecycleRecordingSession extends Session implements LifecycleSession{

    /** @var list<string> */
    public static array $log = [];

    public int $chats = 0;

    protected function onStart() : void{
        self::$log[] = "start";
    }

    protected function onTerminate(string $reason) : void{
        self::$log[] = "onTerminate:$reason";
    }

    #[SessionEventHandler(PlayerChatEvent::class)]
    public function onChat(PlayerChatEvent $event) : void{
        ++$this->chats;
    }
}
