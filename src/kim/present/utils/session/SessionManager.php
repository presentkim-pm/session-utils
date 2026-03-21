<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\utils\session;

use kim\present\utils\session\listener\attribute\SessionEventHandler;
use kim\present\utils\session\listener\SessionEventDispatcher;
use kim\present\utils\session\listener\SessionEventListenerRegistry;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Central registry and lifecycle manager for sessions of a specific type.
 *
 * Each SessionManager instance manages sessions of exactly one class type.
 * Lifecycle sessions (implementing LifecycleSession) are automatically created
 * on PlayerJoin and terminated on PlayerQuit. Task sessions support explicit
 * start/timeout/complete lifecycle.
 *
 * On construction, this manager scans the session class for {@link SessionEventHandler}
 * attributes and caches the resulting {@link SessionEventDispatcher} list. Each
 * dispatcher is registered with {@link SessionEventListenerRegistry} once, ensuring
 * no duplicate PMMP listeners are created across multiple managers.
 *
 * @template TPlugin of PluginBase
 * @template TSession of Session
 */
class SessionManager{

    /**
     * @var PluginBase      $plugin The plugin that owns this manager.
     * @phpstan-var TPlugin $plugin The plugin that owns this manager.
     */
    protected readonly PluginBase $plugin;

    /**
     * @var string                         $sessionClass The session class this manager handles.
     * @phpstan-var class-string<TSession> $sessionClass The session class this manager handles.
     */
    protected readonly string $sessionClass;

    /**
     * @var Session[] [player runtime id => session]
     * @phpstan-var array<int, TSession> [player runtime id => session]
     */
    protected array $sessions = [];

    /**
     * Dispatchers collected from the session class at construction time.
     * Registered with SessionEventListenerRegistry on first use.
     *
     * @var list<SessionEventDispatcher>
     */
    protected array $eventBindingList = [];

    /**
     * @param PluginBase                     $plugin       The plugin that owns this manager.
     * @param string                         $sessionClass The session class this manager handles.
     *
     * @phpstan-param TPlugin                $plugin       The plugin that owns this manager.
     * @phpstan-param class-string<TSession> $sessionClass The session class this manager handles.
     */
    public function __construct(
        PluginBase $plugin,
        string $sessionClass
    ){
        $this->plugin = $plugin;
        $this->sessionClass = $sessionClass;
        $this->collectBindings();
        $this->registerBindings();
        $this->registerJoinQuitListeners();
    }

    /**
     * Returns the session class this manager handles.
     *
     * @return class-string<TSession>
     */
    public function getSessionClass() : string{
        return $this->sessionClass;
    }

    /**
     * Retrieves the active session for a player.
     *
     * @param Player|int $playerOrId The player or their runtime ID.
     *
     * @return Session|null The session, or null if none exists.
     *
     * @phpstan-return TSession|null The session, or null if none exists.
     */
    public function getSession(Player|int $playerOrId) : ?Session{
        $id = $playerOrId instanceof Player ? $playerOrId->getId() : $playerOrId;
        return $this->sessions[$id] ?? null;
    }

    /**
     * Creates a new session for a player.
     *
     * If a session already exists for the player, the existing session is returned.
     * On instantiation failure, the error is logged and null is returned.
     *
     * @param Player $player  The player to create a session for.
     * @param mixed  ...$args Additional arguments forwarded to the session constructor.
     *
     * @return Session|null The created or existing session, or null on failure.
     *
     * @phpstan-return TSession|null The created or existing session, or null on failure.
     */
    public function createSession(Player $player, mixed ...$args) : ?Session{
        $playerId = $player->getId();
        if(isset($this->sessions[$playerId])){
            return $this->sessions[$playerId];
        }

        try{
            $session = $this->instantiateSession($player, ...$args);
            $this->sessions[$playerId] = $session;
            $session->start();
            return $session;
        }catch(Throwable $e){
            unset($this->sessions[$playerId]);
            $this->plugin->getLogger()->error("$this->sessionClass: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Returns all active sessions.
     *
     * @return Session[]
     * @phpstan-return list<TSession>
     */
    public function getAllSessions() : array{
        return array_values($this->sessions);
    }

    /**
     * Removes and terminates a session.
     *
     * Accepts either a Session instance or a Player. If no session exists for
     * the given player, the call is silently ignored.
     *
     * @param Session|Player          $session The session or player to remove.
     * @param string                  $reason  Termination reason. See {@link SessionTerminateReasons}.
     *
     * @phpstan-param TSession|Player $session The session or player to remove.
     */
    public function removeSession(Session|Player $session, string $reason = SessionTerminateReasons::MANUAL) : void{
        if($session instanceof Player){
            $playerId = $session->getId();
            if(isset($this->sessions[$playerId])){
                $session = $this->sessions[$playerId];
            }else{
                return;
            }
        }elseif(!isset($this->sessions[$session->getPlayer()->getId()])){
            return;
        }

        // Unregister from sessions map first to prevent re-entry from terminate().
        unset($this->sessions[$session->getPlayer()->getId()]);

        try{
            if($session->isActive()){
                $session->terminate($reason);
            }
        }catch(Throwable $e){
            $this->plugin->getLogger()->error(
                "Session terminate() failed [$session::class]: " . $e->getMessage()
            );
        }
    }

    /**
     * Terminates and removes all active sessions.
     *
     * @param string $reason Termination reason. See {@link SessionTerminateReasons}.
     *
     * @return int Number of sessions terminated.
     */
    public function terminateAll(string $reason = SessionTerminateReasons::MANUAL) : int{
        $sessions = array_values($this->sessions);
        foreach($sessions as $session){
            $this->removeSession($session, $reason);
        }
        return count($sessions);
    }

    /**
     * Instantiates a new session of the managed class.
     *
     * @param Player $player  The player to associate with the session.
     * @param mixed  ...$args Additional constructor arguments.
     *
     * @return Session
     *
     * @phpstan-return TSession
     */
    private function instantiateSession(Player $player, mixed ...$args) : Session{
        return new ($this->sessionClass)($this, $player, ...$args);
    }

    /**
     * Registers PlayerJoin and PlayerQuit listeners for automatic session lifecycle.
     *
     * PlayerJoin registration is skipped if the session class does not implement
     * {@link LifecycleSession}, as those sessions are created manually.
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function registerJoinQuitListeners() : void{
        $pluginManager = Server::getInstance()->getPluginManager();

        if(is_a($this->sessionClass, LifecycleSession::class, true)){
            $pluginManager->registerEvent(
                PlayerJoinEvent::class,
                fn(PlayerJoinEvent $event) => $this->createSession($event->getPlayer()),
                EventPriority::HIGHEST,
                $this->plugin
            );
        }

        $pluginManager->registerEvent(
            PlayerQuitEvent::class,
            function(PlayerQuitEvent $event) : void{
                $session = $this->sessions[$event->getPlayer()->getId()] ?? null;
                if($session !== null){
                    $this->removeSession($session, SessionTerminateReasons::PLAYER_QUIT);
                }
            },
            EventPriority::HIGHEST,
            $this->plugin
        );
    }

    /**
     * Scans the session class for {@link SessionEventHandler} attributes and
     * populates {@link $eventBindingList}.
     *
     * Invalid event classes or handler signatures are logged as warnings and skipped.
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function collectBindings() : void{
        $this->eventBindingList = [];

        $reflection = new ReflectionClass($this->sessionClass);
        foreach($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method){
            foreach($method->getAttributes(SessionEventHandler::class) as $attribute){
                $methodName = $method->getName();

                /** @var SessionEventHandler $handler */
                $handler = $attribute->newInstance();

                if(!is_a($handler->eventClass, Event::class, true)){
                    $this->plugin->getLogger()->warning(
                        "Ignored invalid event class on $this->sessionClass::$methodName"
                    );
                    continue;
                }
                if(!$this->isValidHandlerMethod($method)){
                    $this->plugin->getLogger()->warning(
                        "Ignored invalid handler signature on $this->sessionClass::$methodName"
                    );
                    continue;
                }

                $this->eventBindingList[] = new SessionEventDispatcher(
                    sessionManager: $this,
                    eventClass: $handler->eventClass,
                    priority: $handler->priority,
                    handleCancelled: $handler->handleCancelled,
                    methodName: $methodName,
                );
            }
        }
    }

    /**
     * Registers all collected dispatchers with {@link SessionEventListenerRegistry}.
     *
     * Called once after {@link collectBindings()} during construction.
     */
    private function registerBindings() : void{
        $registry = SessionEventListenerRegistry::getInstance();
        foreach($this->eventBindingList as $binding){
            $registry->attachBinding($binding, $this->plugin);
        }
    }

    /**
     * Validates that a method has exactly one non-nullable Event parameter.
     *
     * @param ReflectionMethod $method The method to validate.
     *
     * @return bool True if the method signature is a valid event handler.
     */
    private function isValidHandlerMethod(ReflectionMethod $method) : bool{
        $parameters = $method->getParameters();
        if(count($parameters) !== 1){
            return false;
        }

        $parameterType = $parameters[0]->getType();
        if($parameterType === null || $parameterType->allowsNull()){
            return false;
        }
        if(!method_exists($parameterType, "getName")){
            return false;
        }

        /** @var mixed $parameterType */
        $typeName = $parameterType->getName();
        return is_string($typeName) && is_a($typeName, Event::class, true);
    }

}
