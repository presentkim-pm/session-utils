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

namespace kim\present\utils\session\listener\dispatcher;

use kim\present\utils\session\SessionManager;
use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * Base class for all session-scoped event dispatchers.
 *
 * Holds the listener configuration (eventClass, priority, handleCancelled)
 * shared by all dispatcher types and defines the dispatch contract.
 * Subclasses implement dispatch() to define what happens when the event fires.
 *
 * @see SessionMethodDispatcher
 * @see ManagerMethodDispatcher
 */
abstract readonly class BaseSessionEventDispatcher{

    /**
     * Unique key identifying this dispatcher's listener configuration.
     * Used by SessionEventListenerRegistry to deduplicate PMMP listener registration.
     *
     * Format: {eventClass}|{priority}|{handleCancelled}
     * Example: "pocketmine\event\block\BlockBreakEvent|2|1"
     */
    public string $eventKey;

    /**
     * @param SessionManager              $sessionManager  The manager that owns this dispatcher.
     * @param string                      $eventClass      Fully-qualified event class name to listen for.
     * @param int                         $priority        Event priority. See {@link EventPriority}.
     * @param bool                        $handleCancelled Whether to dispatch even if the event is cancelled.
     * @param string                      $methodName      Name of the method to invoke on dispatch.
     *
     * @phpstan-param class-string<Event> $eventClass      Fully-qualified event class name to listen for.
     */
    public function __construct(
        public SessionManager $sessionManager,
        public string $eventClass,
        public int $priority,
        public bool $handleCancelled,
        public string $methodName
    ){
        if(!is_a($this->eventClass, Event::class, true)){
            throw new \InvalidArgumentException(
                "Expected a class-string of " . Event::class . ", got " . $this->eventClass
            );
        }
        $this->eventKey = $this->eventClass . '|' . $this->priority . '|' . ((int) $this->handleCancelled);
    }

    /**
     * Dispatches the event to the appropriate target.
     *
     * Called by {@link SessionEventListener::onEvent()} after the player has
     * been extracted from the event.
     *
     * @internal
     */
    abstract public function dispatch(Event $event, Player $player) : void;

}
