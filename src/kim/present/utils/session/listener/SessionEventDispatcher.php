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

namespace kim\present\utils\session\listener;

use kim\present\utils\session\SessionManager;
use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * Represents a single session-scoped event handler binding.
 *
 * Holds the full configuration needed to route a PMMP event to the correct
 * session method. Created by SessionManager during attribute scanning and
 * attached to a SessionEventListener via SessionEventListenerRegistry.
 *
 * Each instance corresponds to one #[SessionEventHandler] attribute
 * declaration on a Session subclass method.
 */
final readonly class SessionEventDispatcher{

    /**
     * Unique key identifying this dispatcher's listener configuration.
     * Used by SessionEventListenerRegistry to deduplicate PMMP listener registration.
     *
     * Format: {eventClass}|{priority}|{handleCancelled}
     * Example: "pocketmine\event\block\BlockBreakEvent|2|1"
     */
    public string $eventKey;

    /**
     * @param SessionManager              $sessionManager  The manager that owns the target sessions.
     * @param string                      $eventClass      Fully-qualified event class name to listen for.
     * @param int                         $priority        Event priority. See {@link EventPriority}.
     * @param bool                        $handleCancelled Whether to dispatch even if the event is cancelled.
     * @param string                      $methodName      Name of the method to invoke on the session instance.
     *
     * @phpstan-param class-string<Event> $eventClass      Fully-qualified event class name to listen for.
     */
    public function __construct(
        public SessionManager $sessionManager,
        public string $eventClass,
        public int $priority,
        public bool $handleCancelled,
        public string $methodName,
    ){
        if(!is_a($this->eventClass, Event::class, true)){
            throw new \InvalidArgumentException(
                "Expected a class-string of " . Event::class . ", got " . $this->eventClass
            );
        }
        $this->eventKey = $this->eventClass . '|' . $this->priority . '|' . ((int) $this->handleCancelled);
    }

    /**
     * Dispatches the event to the target player's session.
     *
     * Retrieves the session associated with the given player from the session
     * manager and invokes the bound method on it. If no session exists for
     * the player, the dispatch is silently skipped.
     *
     * @param Event  $event  The event to dispatch.
     * @param Player $player The player extracted from the event.
     *
     * @internal Called by SessionEventListener::onEvent().
     *
     */
    public function dispatch(Event $event, Player $player) : void{
        $session = $this->sessionManager->getSession($player);
        if($session === null){
            // No active session for this player, nothing to dispatch.
            return;
        }

        $session->{$this->methodName}($event);
    }

}
