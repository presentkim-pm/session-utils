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
use kim\present\utils\session\listener\dispatcher\BaseSessionEventDispatcher;
use kim\present\utils\session\listener\dispatcher\ManagerMethodDispatcher;
use kim\present\utils\session\listener\dispatcher\SessionMethodDispatcher;
use kim\present\utils\session\listener\SessionEventListenerRegistry;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Utils;
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
 * attributes and caches the resulting {@link BaseSessionEventDispatcher} list. Each
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
     * @var list<BaseSessionEventDispatcher>
     */
    protected array $eventBindingList = [];

    /**
     * Callback invoked after a session is created and started.
     *
     * @var (\Closure(TSession): void)|null
     */
    private ?\Closure $onSessionCreated = null;

    /**
     * Callback invoked after a session is terminated and removed.
     *
     * @var (\Closure(TSession, string): void)|null
     */
    private ?\Closure $onSessionTerminated = null;

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
        $this->collectSessionLifecycleBindings();
        $this->collectSessionEventBindings();
        $this->registerEventBindings();
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

            if($this->onSessionCreated !== null){
                ($this->onSessionCreated)($session);
            }
            return $session;
        }catch(Throwable $e){
            unset($this->sessions[$playerId]);
            $this->plugin->getLogger()->error("$this->sessionClass: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the active session for a player, throwing if none exists.
     *
     * @param Player|int $playerOrId The player or their runtime ID.
     *
     * @return TSession
     *
     * @throws \RuntimeException If no session exists for the given player.
     */
    public function getSessionOrThrow(Player|int $playerOrId) : Session{
        $session = $this->getSession($playerOrId);
        if($session === null){
            $id = $playerOrId instanceof Player ? $playerOrId->getName() : $playerOrId;
            throw new \RuntimeException("No active session found for player '$id'.");
        }
        return $session;
    }

    /**
     * Retrieves the active session for a player, creating one if none exists.
     *
     * If a session already exists, it is returned as-is without calling start() again.
     * If creation fails, null is returned and the error is logged.
     *
     * @param Player $player The player to get or create a session for.
     *
     * @return TSession|null The existing or newly created session, or null on failure.
     */
    public function getOrCreateSession(Player $player) : ?Session{
        return $this->getSession($player) ?? $this->createSession($player);
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

        if($this->onSessionTerminated !== null){
            ($this->onSessionTerminated)($session, $reason);
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
     * Registers a callback invoked after a session is created and started.
     *
     * @param \Closure(TSession): void $callback
     */
    public function onSessionCreated(\Closure $callback) : self{
        Utils::validateCallableSignature(function(Session $session){}, $callback);
        $this->onSessionCreated = $callback;
        return $this;
    }

    /**
     * Registers a callback invoked after a session is terminated and removed.
     *
     * @param \Closure(TSession, string): void $callback
     */
    public function onSessionTerminated(\Closure $callback) : self{
        Utils::validateCallableSignature(function(Session $session, string $reason){}, $callback);
        $this->onSessionTerminated = $callback;
        return $this;
    }

    /** @internal Called by ManagerMethodDispatcher. */
    public function onPlayerJoin(PlayerJoinEvent $event) : void{
        $this->createSession($event->getPlayer());
    }

    /** @internal Called by ManagerMethodDispatcher. */
    public function onPlayerQuit(PlayerQuitEvent $event) : void{
        $this->removeSession($event->getPlayer(), SessionTerminateReasons::PLAYER_QUIT);
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
     * Collects lifecycle dispatchers for PlayerJoin and PlayerQuit events
     * and appends them to {@link $eventBindingList}.
     *
     * PlayerJoin dispatcher registration is skipped if the session class
     * does not implement {@link LifecycleSession}, as those sessions are
     * created manually via {@link createSession()}.
     *
     * The collected dispatchers are registered with {@link SessionEventListenerRegistry}
     * in {@link registerEventBindings()}.
     */
    private function collectSessionLifecycleBindings() : void{
        if(is_a($this->sessionClass, LifecycleSession::class, true)){
            $this->eventBindingList[] = new ManagerMethodDispatcher(
                sessionManager: $this,
                eventClass: PlayerJoinEvent::class,
                priority: EventPriority::HIGHEST,
                handleCancelled: false,
                methodName: "onPlayerJoin",/** @see self::onPlayerJoin() */
            );
        }

        $this->eventBindingList[] = new ManagerMethodDispatcher(
            sessionManager: $this,
            eventClass: PlayerQuitEvent::class,
            priority: EventPriority::HIGHEST,
            handleCancelled: false,
            methodName: "onPlayerQuit",/** @see self::onPlayerQuit() */
        );
    }

    /**
     * Scans the session class for {@link SessionEventHandler} attributes and
     * appends the resulting dispatchers to {@link $eventBindingList}.
     *
     * Each public method annotated with {@link SessionEventHandler} is validated
     * for a correct handler signature (exactly one non-nullable {@link Event}
     * subclass parameter). Invalid event classes or signatures are logged as
     * warnings and skipped.
     *
     * The collected dispatchers are registered with {@link SessionEventListenerRegistry}
     * in {@link registerEventBindings()}.
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function collectSessionEventBindings() : void{
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

                $this->eventBindingList[] = new SessionMethodDispatcher(
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
     * Called once after {@link collectSessionLifecycleBindings()} and
     * {@link collectSessionEventBindings()} during construction.
     */
    private function registerEventBindings() : void{
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
