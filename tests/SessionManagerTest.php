<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests;

use kim\present\utils\session\listener\SessionEventListenerRegistry;
use kim\present\utils\session\SessionManager;
use kim\present\utils\session\SessionTerminateReasons;
use kim\present\utils\session\tests\Fixture\FailingStartSession;
use kim\present\utils\session\tests\Fixture\RecordingSession;
use PHPUnit\Framework\Attributes\CoversClass;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\chat\StandardChatFormatter;

#[CoversClass(SessionManager::class)]
final class SessionManagerTest extends SessionTestCase{

    private function chat(\pocketmine\player\Player $player) : PlayerChatEvent{
        return new PlayerChatEvent($player, "hi", [], new StandardChatFormatter("{%0}"));
    }

    public function testCreateGetRemove() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $player = $this->makePlayer();

        self::assertSame(RecordingSession::class, $manager->getSessionClass());
        self::assertNull($manager->getSession($player));

        $session = $manager->createSession($player);
        self::assertInstanceOf(RecordingSession::class, $session);
        self::assertTrue($session->isActive());
        self::assertSame($player, $session->getPlayer());
        self::assertSame($session, $manager->getSession($player));
        self::assertSame($session, $manager->getSession($player->getId()));
        self::assertSame([$session], $manager->getAllSessions());

        // Creating again returns the existing session without restarting it.
        self::assertSame($session, $manager->createSession($player));
        self::assertSame(["start"], RecordingSession::$log);

        $manager->removeSession($player, SessionTerminateReasons::CANCELLED);
        self::assertFalse($session->isActive());
        self::assertNull($manager->getSession($player));
        self::assertSame([], $manager->getAllSessions());
        self::assertSame(["start", "onTerminate:cancelled"], RecordingSession::$log);

        // Removing again is silently ignored.
        $manager->removeSession($player);
        self::assertCount(2, RecordingSession::$log);
    }

    public function testGetOrCreateAndGetSessionOrThrow() : void{
        $manager = new SessionManager($this->makePlugin()->plugin, RecordingSession::class);
        $player = $this->makePlayer();

        try{
            $manager->getSessionOrThrow($player);
            self::fail("Expected exception");
        }catch(\RuntimeException $e){
            self::assertStringContainsString("Player1", $e->getMessage());
        }

        $session = $manager->getOrCreateSession($player);
        self::assertNotNull($session);
        self::assertSame($session, $manager->getOrCreateSession($player));
        self::assertSame($session, $manager->getSessionOrThrow($player));
        self::assertSame($session, $manager->getSessionOrThrow($player->getId()));
        self::assertSame(["start"], RecordingSession::$log);
    }

    public function testTerminateAll() : void{
        $manager = new SessionManager($this->makePlugin()->plugin, RecordingSession::class);
        $a = $manager->createSession($this->makePlayer());
        $b = $manager->createSession($this->makePlayer());

        self::assertSame(2, $manager->terminateAll(SessionTerminateReasons::MAINTENANCE));
        self::assertFalse($a->isActive());
        self::assertFalse($b->isActive());
        self::assertSame([], $manager->getAllSessions());
        self::assertSame(0, $manager->terminateAll());
    }

    public function testTerminateOrderIsConsistentAndCallbackRunsOnce() : void{
        foreach(["self", "manager"] as $path){
            RecordingSession::$log = [];
            $manager = new SessionManager($this->makePlugin()->plugin, RecordingSession::class);
            $manager->onSessionTerminated(function(RecordingSession $s, string $reason) : void{
                RecordingSession::$log[] = "callback:$reason";
            });
            $player = $this->makePlayer();
            $session = $manager->createSession($player);

            if($path === "self"){
                $session->terminate(SessionTerminateReasons::TIMEOUT);
                $session->terminate(SessionTerminateReasons::TIMEOUT); // no-op
                $reason = "timeout";
            }else{
                $manager->removeSession($session, SessionTerminateReasons::CANCELLED);
                $session->terminate(); // no-op
                $reason = "cancelled";
            }

            self::assertSame(["start", "onTerminate:$reason", "callback:$reason"], RecordingSession::$log, $path);
            self::assertFalse($session->isActive());
            self::assertNull($manager->getSession($player));
        }
    }

    public function testOnTerminateFailureIsLoggedAndCallbackStillRuns() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $called = 0;
        $manager->onSessionTerminated(function() use (&$called) : void{
            ++$called;
        });
        $session = $manager->createSession($this->makePlayer());
        $session->failOnTerminate = true;

        $manager->removeSession($session);

        self::assertSame(1, $called);
        self::assertFalse($session->isActive());
        self::assertCount(1, $fake->errors);
        // Used to crash with "Object could not be converted to string".
        self::assertStringContainsString(RecordingSession::class, $fake->errors[0]);
        self::assertStringContainsString("terminate boom", $fake->errors[0]);
        self::assertCount(1, $fake->exceptions);
    }

    public function testStaleSessionDoesNotRemoveNewerSession() : void{
        $manager = new SessionManager($this->makePlugin()->plugin, RecordingSession::class);
        $player = $this->makePlayer();
        $old = $manager->createSession($player);
        $manager->removeSession($old);
        $new = $manager->createSession($player);
        self::assertNotSame($old, $new);

        $manager->removeSession($old);
        $old->terminate();

        self::assertSame($new, $manager->getSession($player));
        self::assertTrue($new->isActive());
    }

    public function testStartFailure() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, FailingStartSession::class);
        $created = 0;
        $terminated = 0;
        $manager->onSessionCreated(function() use (&$created) : void{
            ++$created;
        });
        $manager->onSessionTerminated(function() use (&$terminated) : void{
            ++$terminated;
        });
        $player = $this->makePlayer();

        self::assertNull($manager->createSession($player));

        self::assertNull($manager->getSession($player));
        self::assertSame(["start", "onTerminate:" . SessionTerminateReasons::START_FAILED], FailingStartSession::$log);
        self::assertSame(0, $created);
        self::assertSame(0, $terminated);
        self::assertCount(1, $fake->errors);
        self::assertCount(1, $fake->exceptions);
        self::assertSame("start boom", $fake->exceptions[0]->getMessage());
    }

    public function testInstantiationFailureReturnsNull() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $player = $this->makePlayer();

        // Extra constructor arguments are rejected by Session::__construct only for unknown named parameters.
        self::assertNull($manager->createSession($player, unknown: 1));
        self::assertNull($manager->getSession($player));
        self::assertCount(1, $fake->errors);
    }

    public function testCallbacksAcceptSubclassTypedClosures() : void{
        $manager = new SessionManager($this->makePlugin()->plugin, RecordingSession::class);
        $seen = [];
        self::assertSame($manager, $manager->onSessionCreated(function(RecordingSession $s) use (&$seen) : void{
            $seen[] = "created";
        }));
        self::assertSame($manager, $manager->onSessionTerminated(function(RecordingSession $s, string $reason) use (&$seen) : void{
            $seen[] = "terminated:$reason";
        }));

        $manager->createSession($this->makePlayer());
        $manager->terminateAll(SessionTerminateReasons::COMPLETED);

        self::assertSame(["created", "terminated:completed"], $seen);
    }

    public function testEventsAreDispatchedOnlyToSessionsOfThePlayerAndAnnotatedMethods() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $withSession = $this->makePlayer();
        $without = $this->makePlayer();
        $session = $manager->createSession($withSession);

        $fake->fire($this->chat($withSession));
        $fake->fire($this->chat($without));
        $fake->fire(new BlockBreakEvent($withSession, $this->createStub(\pocketmine\block\Block::class), $this->createStub(\pocketmine\item\Item::class), true, []));

        self::assertSame(["chat"], $session->events);
        self::assertNull($manager->getSession($without));
    }

    public function testQuitTerminatesSessionButTaskSessionsAreNotCreatedOnJoin() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $player = $this->makePlayer();
        $session = $manager->createSession($player);

        $fake->fire(new PlayerQuitEvent($player, "bye", "quit"));

        self::assertFalse($session->isActive());
        self::assertSame(["start", "onTerminate:" . SessionTerminateReasons::PLAYER_QUIT], RecordingSession::$log);
        // Task sessions do not listen to PlayerJoinEvent.
        self::assertSame(0, $fake->countRegistrations(\pocketmine\event\player\PlayerJoinEvent::class));
    }

    public function testCloseTerminatesDetachesAndRefusesNewSessions() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $player = $this->makePlayer();
        $session = $manager->createSession($player);

        $manager->close();
        $manager->close(); // idempotent

        self::assertTrue($manager->isClosed());
        self::assertFalse($session->isActive());
        self::assertSame(["start", "onTerminate:" . SessionTerminateReasons::PLUGIN_DISABLE], RecordingSession::$log);
        self::assertSame([], $manager->getAllSessions());

        // Detached from events.
        $fake->fire($this->chat($player));
        self::assertSame([], $session->events);

        $this->expectException(\LogicException::class);
        $manager->createSession($player);
    }

    public function testCloseWithCustomReason() : void{
        $manager = new SessionManager($this->makePlugin()->plugin, RecordingSession::class);
        $manager->createSession($this->makePlayer());
        $manager->close(SessionTerminateReasons::MAINTENANCE);
        self::assertSame(["start", "onTerminate:maintenance"], RecordingSession::$log);
    }

    public function testPluginDisableClosesManagerOfThatPluginOnly() : void{
        $first = $this->makePlugin();
        $second = $this->makePlugin();
        $m1 = new SessionManager($first->plugin, RecordingSession::class);
        $m2 = new SessionManager($second->plugin, RecordingSession::class);
        $s1 = $m1->createSession($this->makePlayer());
        $s2 = $m2->createSession($this->makePlayer());

        $first->disable();

        self::assertTrue($m1->isClosed());
        self::assertFalse($s1->isActive());
        self::assertFalse($m2->isClosed());
        self::assertTrue($s2->isActive());
    }

    public function testSharedEventKeyGetsSeparateListenersPerPlugin() : void{
        $first = $this->makePlugin();
        $second = $this->makePlugin();
        $m1 = new SessionManager($first->plugin, RecordingSession::class);
        $m2 = new SessionManager($second->plugin, RecordingSession::class);
        $p1 = $this->makePlayer();
        $p2 = $this->makePlayer();
        $s1 = $m1->createSession($p1);
        $s2 = $m2->createSession($p2);

        self::assertSame(1, $first->countRegistrations(PlayerChatEvent::class));
        self::assertSame(1, $second->countRegistrations(PlayerChatEvent::class));

        // Disabling the plugin that registered first must not break the other plugin's sessions.
        $first->disable();
        $this->assertFalse($s1->isActive());
        $second->fire($this->chat($p2));
        self::assertSame(["chat"], $s2->events);

        // Two managers of the SAME plugin still share a single PMMP listener.
        $m3 = new SessionManager($second->plugin, RecordingSession::class);
        self::assertSame(1, $second->countRegistrations(PlayerChatEvent::class));
        $m3->close();
        $second->fire($this->chat($p2));
        self::assertSame(["chat", "chat"], $s2->events);
    }

    public function testClosingOneManagerKeepsSharedListenerOfSiblingAlive() : void{
        $fake = $this->makePlugin();
        $m1 = new SessionManager($fake->plugin, RecordingSession::class);
        $m2 = new SessionManager($fake->plugin, RecordingSession::class);
        $player = $this->makePlayer();
        $s2 = $m2->createSession($player);

        $m1->close();
        $fake->fire($this->chat($player));

        self::assertSame(["chat"], $s2->events);
    }

    public function testRegistryIsEmptyAfterCloseAndResettable() : void{
        $fake = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);
        $manager->close();
        // Re-attaching after a close works from a clean registry state.
        $again = new SessionManager($fake->plugin, RecordingSession::class);
        self::assertSame(2, $fake->countRegistrations(PlayerChatEvent::class));
        $again->close();
        self::assertNotNull(SessionEventListenerRegistry::getInstance());
    }

    public function testDisableEventOfAnotherPluginIsIgnored() : void{
        $fake = $this->makePlugin();
        $other = $this->makePlugin();
        $manager = new SessionManager($fake->plugin, RecordingSession::class);

        (new PluginDisableEvent($other->plugin))->call();

        self::assertFalse($manager->isClosed());
    }
}
