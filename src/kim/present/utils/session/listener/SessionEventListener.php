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

use pocketmine\event\Cancellable;
use pocketmine\event\Event;
use pocketmine\event\RegisteredListener;
use pocketmine\player\Player;

/**
 * PMMP event listener that routes events to registered session dispatchers.
 *
 * One instance is created per unique eventKey (eventClass + priority + handleCancelled)
 * and registered with PocketMine-MP exactly once via SessionEventListenerRegistry.
 * Multiple SessionManagers subscribing to the same eventKey share this single listener.
 *
 * When an event is fired, this listener:
 * 1. Extracts the player from the event
 * 2. Iterates registered dispatchers in attachment order
 * 3. Stops remaining dispatchers if handleCancelled is false and the event
 *    was cancelled mid-dispatch by a preceding dispatcher
 */
final class SessionEventListener{

    /**
     * Dispatchers attached to this listener, keyed by spl_object_id.
     * Each dispatcher corresponds to one #[SessionEventHandler] binding
     * from a Session subclass.
     *
     * @var array<int, SessionEventDispatcher>
     */
    private array $eventDispatchers = [];

    private ?RegisteredListener $registeredListener = null;

    /**
     * Handles the PMMP event and dispatches it to relevant sessions.
     *
     * This method is called by PocketMine-MP's event system and routes the event
     * to all registered dispatchers in attachment order. Each dispatcher forwards
     * the event to the corresponding player's session if one exists.
     *
     * If handleCancelled is false and the event becomes cancelled mid-dispatch
     * (e.g. by a preceding dispatcher's session handler), remaining dispatchers
     * are skipped entirely.
     *
     * @param Event $event The event dispatched by PocketMine-MP.
     */
    public function onEvent(Event $event) : void{
        $player = $this->extractPlayer($event);
        if($player === null){
            // Event does not have an associated player, nothing to dispatch.
            return;
        }

        foreach($this->eventDispatchers as $dispatcher){
            // Skip remaining dispatchers if the event was cancelled mid-dispatch
            // and this listener does not handle cancelled events.
            if(!$dispatcher->handleCancelled && $event instanceof Cancellable && $event->isCancelled()){
                return;
            }

            $dispatcher->dispatch($event, $player);
        }
    }

    /**
     * Attaches a dispatcher to this listener.
     *
     * Called by SessionEventListenerRegistry when a SessionManager subscribes
     * to an eventKey that already has a registered listener, avoiding duplicate
     * PMMP listener registration.
     *
     * @param SessionEventDispatcher $binding The dispatcher to attach.
     */
    public function attachBinding(SessionEventDispatcher $binding) : void{
        $this->eventDispatchers[spl_object_id($binding)] = $binding;
    }

    /**
     * Detaches a dispatcher from this listener.
     *
     * Called by SessionEventListenerRegistry when a SessionManager unsubscribes.
     * If no dispatchers remain after detachment, the caller is responsible for
     * unregistering this listener from PMMP.
     *
     * @param SessionEventDispatcher $binding The dispatcher to detach.
     */
    public function detachBinding(SessionEventDispatcher $binding) : void{
        unset($this->eventDispatchers[spl_object_id($binding)]);
    }

    /**
     * Returns whether this listener has any dispatchers attached.
     *
     * Used by SessionEventListenerRegistry to determine whether this listener
     * should be unregistered from PMMP after a detachment.
     */
    public function hasDispatchers() : bool{
        return $this->eventDispatchers !== [];
    }

    public function setRegisteredListener(RegisteredListener $registeredListener) : void{
        $this->registeredListener = $registeredListener;
    }

    public function getRegisteredListener() : ?RegisteredListener{
        return $this->registeredListener;
    }

    /**
     * Extracts the player from an event.
     *
     * Checks for getPlayer() first (most player-related events),
     * then falls back to getEntity() for entity events where the entity is a player.
     *
     * @param Event $event The event to extract the player from.
     *
     * @return Player|null The player if found, null otherwise.
     */
    private function extractPlayer(Event $event) : ?Player{
        if(method_exists($event, "getPlayer")){
            $player = $event->getPlayer();
            if($player instanceof Player){
                return $player;
            }
        }

        if(method_exists($event, "getEntity")){
            $entity = $event->getEntity();
            if($entity instanceof Player){
                return $entity;
            }
        }

        return null;
    }

}
