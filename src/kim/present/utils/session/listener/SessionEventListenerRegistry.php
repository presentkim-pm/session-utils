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

use pocketmine\event\HandlerListManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

/**
 * Central registry for session-scoped event listeners.
 *
 * This singleton ensures that only one {@link SessionEventListener} exists per
 * unique eventKey (eventClass + priority + handleCancelled). When multiple
 * SessionManagers register handlers for the same event configuration, the registry
 * prevents duplicate PMMP listener registration by attaching dispatchers to the
 * existing listener instead of creating a new one.
 *
 * Intended to be used only by SessionManager. Direct use from plugin code is
 * discouraged.
 *
 * @see SessionEventDispatcher
 * @see SessionEventListener
 */
final class SessionEventListenerRegistry{
    use SingletonTrait;

    /**
     * Active listeners keyed by eventKey.
     * An entry is removed when its listener has no remaining dispatchers.
     *
     * @var array<string, SessionEventListener>
     */
    private array $listeners = [];

    /**
     * Attaches a dispatcher to the registry.
     *
     * If a listener for the dispatcher's eventKey already exists, the dispatcher
     * is attached to it. Otherwise, a new {@link SessionEventListener} is created,
     * the dispatcher is attached, and the listener is registered with PMMP.
     *
     * @param SessionEventDispatcher $binding The dispatcher to attach.
     * @param PluginBase             $plugin  The plugin used for PMMP listener registration.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function attachBinding(SessionEventDispatcher $binding, PluginBase $plugin) : void{
        if(isset($this->listeners[$binding->eventKey])){
            $this->listeners[$binding->eventKey]->attachBinding($binding);
            return;
        }

        $listener = new SessionEventListener();
        $listener->attachBinding($binding);

        /** @noinspection PhpUnhandledExceptionInspection */
        $registeredListener = $plugin->getServer()->getPluginManager()->registerEvent(
            $binding->eventClass,
            $listener->onEvent(...),
            $binding->priority,
            $plugin,
            $binding->handleCancelled
        );
        $listener->setRegisteredListener($registeredListener);
        $this->listeners[$binding->eventKey] = $listener;
    }

    /**
     * Detaches a dispatcher from the registry.
     *
     * Removes the dispatcher from its corresponding listener. If no dispatchers
     * remain on the listener after detachment, the listener is unregistered from
     * PMMP and removed from the registry.
     *
     * @param SessionEventDispatcher $binding The dispatcher to detach.
     */
    public function detachBinding(SessionEventDispatcher $binding) : void{
        $listener = $this->listeners[$binding->eventKey] ?? null;
        if($listener === null){
            return;
        }

        $listener->detachBinding($binding);

        // Unregister from PMMP and clean up if no dispatchers remain.
        if(!$listener->hasDispatchers()){
            $registeredListener = $listener->getRegisteredListener();
            if($registeredListener !== null){
                HandlerListManager::global()->unregisterAll($listener->getRegisteredListener());
            }
            unset($this->listeners[$binding->eventKey]);
        }
    }

}
