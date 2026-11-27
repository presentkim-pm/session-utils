<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests;

use kim\present\utils\session\LifecycleSession;
use kim\present\utils\session\SessionManager;
use kim\present\utils\session\SessionTerminateReasons;
use kim\present\utils\session\tests\Fixture\LifecycleRecordingSession;
use PHPUnit\Framework\Attributes\CoversClass;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\chat\StandardChatFormatter;

#[CoversClass(SessionManager::class)]
#[CoversClass(LifecycleSession::class)]
final class LifecycleSessionTest extends SessionTestCase{

    public function testSessionIsCreatedOnJoinAndTerminatedOnQuit() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, LifecycleRecordingSession::class);
        $player = $this->makePlayer();

        $fake->fire(new PlayerJoinEvent($player, "hello"));
        $session = $manager->getSession($player);
        self::assertInstanceOf(LifecycleRecordingSession::class, $session);
        self::assertTrue($session->isActive());

        $fake->fire(new PlayerChatEvent($player, "hi", [], new StandardChatFormatter("{%0}")));
        self::assertSame(1, $session->chats);

        $fake->fire(new PlayerQuitEvent($player, "bye", "quit"));
        self::assertFalse($session->isActive());
        self::assertNull($manager->getSession($player));
        self::assertSame(["start", "onTerminate:" . SessionTerminateReasons::PLAYER_QUIT], LifecycleRecordingSession::$log);
    }

    public function testSessionsAreCreatedForPlayersAlreadyOnline() : void{
        $fake = $this->makePlugin();
        $online = $this->makePlayer();
        $leaving = $this->makePlayer(false);
        $fake->onlinePlayers = [$online, $leaving];

        $manager = new SessionManager($fake->plugin, LifecycleRecordingSession::class);

        self::assertNotNull($manager->getSession($online));
        self::assertNull($manager->getSession($leaving));
        self::assertCount(1, $manager->getAllSessions());
    }

    public function testNoSessionsForAlreadyOnlinePlayersWhenNotLifecycle() : void{
        $fake = $this->makePlugin();
        $fake->onlinePlayers = [$this->makePlayer()];

        $manager = new SessionManager($fake->plugin, \kim\present\utils\session\tests\Fixture\RecordingSession::class);

        self::assertSame([], $manager->getAllSessions());
    }

    public function testJoinAfterCloseCreatesNothing() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, LifecycleRecordingSession::class);
        $manager->close();

        $player = $this->makePlayer();
        $fake->fire(new PlayerJoinEvent($player, "hello"));

        self::assertNull($manager->getSession($player));
    }

    public function testClosingManagerOnPluginDisableTerminatesLifecycleSessions() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, LifecycleRecordingSession::class);
        $player = $this->makePlayer();
        $fake->fire(new PlayerJoinEvent($player, "hello"));

        $fake->disable();

        self::assertSame([], $manager->getAllSessions());
        self::assertSame(["start", "onTerminate:" . SessionTerminateReasons::PLUGIN_DISABLE], LifecycleRecordingSession::$log);
    }
}
