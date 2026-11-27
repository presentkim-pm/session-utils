<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests;

use kim\present\utils\session\listener\SessionEventListenerRegistry;
use kim\present\utils\session\tests\Fixture\FailingStartSession;
use kim\present\utils\session\tests\Fixture\FakePlugin;
use kim\present\utils\session\tests\Fixture\LifecycleRecordingSession;
use kim\present\utils\session\tests\Fixture\RecordingSession;
use kim\present\utils\session\tests\Fixture\StepSession;
use PHPUnit\Framework\TestCase;
use pocketmine\entity\Entity;
use pocketmine\event\HandlerListManager;
use pocketmine\player\Player;

abstract class SessionTestCase extends TestCase{

    private int $nextPlayerId = 1;

    protected function setUp() : void{
        $this->resetState();
    }

    protected function tearDown() : void{
        $this->resetState();
    }

    private function resetState() : void{
        HandlerListManager::global()->unregisterAll();
        SessionEventListenerRegistry::reset();
        RecordingSession::$log = [];
        FailingStartSession::$log = [];
        LifecycleRecordingSession::$log = [];
        StepSession::$log = [];
    }

    protected function makePlugin() : FakePlugin{
        return new FakePlugin(fn(string $class) => $this->createStub($class));
    }

    protected function makePlayer(bool $online = true) : Player{
        $player = $this->createStub(Player::class);
        $id = $this->nextPlayerId++;
        $player->method("getId")->willReturn($id);
        $player->method("getName")->willReturn("Player$id");
        $player->method("isOnline")->willReturn($online);

        // Player::__destruct() needs these; without them the garbage collector crashes PHP at shutdown.
        (new \ReflectionProperty(Player::class, "logger"))->setValue($player, $this->createStub(\Logger::class));
        (new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
        return $player;
    }
}
