<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests\Fixture;

use pocketmine\event\Event;
use pocketmine\event\HandlerListManager;
use pocketmine\event\plugin\PluginDisableEvent;
use pocketmine\event\RegisteredListener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginManager;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;

/**
 * A mocked plugin with a mocked server, plugin manager and logger.
 *
 * The plugin manager's registerEvent() registers a real RegisteredListener in the global handler lists,
 * so events fired with Event::call() reach the handlers exactly as in a running server, and
 * unregistering (HandlerListManager) works for real.
 */
final class FakePlugin{

    /** @var list<Player> */
    public array $onlinePlayers = [];

    /** @var list<array{event: string, plugin: PluginBase, listener: RegisteredListener}> */
    public array $registrations = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<\Throwable> */
    public array $exceptions = [];

    public readonly PluginBase $plugin;

    /** @param \Closure(class-string): \PHPUnit\Framework\MockObject\Stub $createMock */
    public function __construct(\Closure $createMock){
        $this->plugin = $createMock(PluginBase::class);

        $logger = $createMock(\AttachableLogger::class);
        $logger->method("error")->willReturnCallback(function(string $message) : void{
            $this->errors[] = $message;
        });
        $logger->method("logException")->willReturnCallback(function(\Throwable $e) : void{
            $this->exceptions[] = $e;
        });
        $this->plugin->method("getLogger")->willReturn($logger);

        $pluginManager = $createMock(PluginManager::class);
        $pluginManager->method("registerEvent")->willReturnCallback(
            function(string $event, \Closure $handler, int $priority, $plugin, bool $handleCancelled = false) : RegisteredListener{
                $listener = new RegisteredListener($handler, $priority, $plugin, $handleCancelled, new TimingsHandler("test"));
                HandlerListManager::global()->getListFor($event)->register($listener);
                $this->registrations[] = ["event" => $event, "plugin" => $plugin, "listener" => $listener];
                return $listener;
            }
        );

        $server = $createMock(Server::class);
        $server->method("getPluginManager")->willReturn($pluginManager);
        $server->method("getOnlinePlayers")->willReturnCallback(fn() => $this->onlinePlayers);

        // PluginBase::getServer() is final and reads a promoted private property.
        $property = new \ReflectionProperty(PluginBase::class, "server");
        $property->setValue($this->plugin, $server);
    }

    /** Number of registerEvent() calls for an event class. */
    public function countRegistrations(string $eventClass) : int{
        return count(array_filter($this->registrations, static fn(array $r) => $r["event"] === $eventClass));
    }

    /** Mimics PluginManager::disablePlugin(): fires the event, then unregisters the plugin's listeners. */
    public function disable() : void{
        (new PluginDisableEvent($this->plugin))->call();
        HandlerListManager::global()->unregisterAll($this->plugin);
    }

    public function fire(Event $event) : void{
        $event->call();
    }
}
