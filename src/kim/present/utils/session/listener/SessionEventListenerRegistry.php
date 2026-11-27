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

use kim\present\utils\session\listener\dispatcher\BaseSessionEventDispatcher;
use pocketmine\event\HandlerListManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

/**
 * Central registry for session-scoped event listeners.
 *
 * This singleton ensures that only one {@link SessionEventListener} exists per
 * unique (plugin, eventKey) pair, where eventKey is eventClass + priority + handleCancelled.
 * When multiple SessionManagers of the same plugin register handlers for the same event
 * configuration, the registry prevents duplicate PMMP listener registration by attaching
 * dispatchers to the existing listener instead of creating a new one.
 *
 * Listeners are never shared between plugins: PMMP unregisters a plugin's listeners when
 * it is disabled, so a listener shared across plugins would silently stop serving the
 * other plugins' sessions. This matters when the library is installed through Composer
 * and a single copy of the classes is used by several plugins.
 *
 * Intended to be used only by SessionManager. Direct use from plugin code is
 * discouraged.
 *
 * @see BaseSessionEventDispatcher
 * @see SessionEventListener
 */
final class SessionEventListenerRegistry{
    use SingletonTrait;

    /**
     * Active listeners keyed by "{spl_object_id(plugin)}|{eventKey}".
     * An entry is removed when its listener has no remaining dispatchers.
     *
     * @var array<string, SessionEventListener>
     */
    private array $listeners = [];

    /**
     * Registry key of the listener each attached dispatcher belongs to, keyed by spl_object_id of the dispatcher.
     *
     * @var array<int, string>
     */
    private array $bindingKeys = [];

    /**
     * Attaches a dispatcher to the registry.
     *
     * If a listener for the plugin and the dispatcher's eventKey already exists, the dispatcher
     * is attached to it. Otherwise, a new {@link SessionEventListener} is created,
     * the dispatcher is attached, and the listener is registered with PMMP under the given plugin.
     *
     * @param BaseSessionEventDispatcher $binding The dispatcher to attach.
     * @param PluginBase                 $plugin  The plugin that owns the dispatcher and the PMMP listener.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function attachBinding(BaseSessionEventDispatcher $binding, PluginBase $plugin) : void{
        $key = spl_object_id($plugin) . '|' . $binding->eventKey;
        $bindingId = spl_object_id($binding);
        if(isset($this->bindingKeys[$bindingId])){
            return; // Already attached.
        }

        if(isset($this->listeners[$key])){
            $this->listeners[$key]->attachBinding($binding);
            $this->bindingKeys[$bindingId] = $key;
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
        $this->listeners[$key] = $listener;
        $this->bindingKeys[$bindingId] = $key;
    }

    /**
     * Detaches a dispatcher from the registry.
     *
     * Removes the dispatcher from its corresponding listener. If no dispatchers
     * remain on the listener after detachment, the listener is unregistered from
     * PMMP and removed from the registry.
     *
     * @param BaseSessionEventDispatcher $binding The dispatcher to detach.
     */
    public function detachBinding(BaseSessionEventDispatcher $binding) : void{
        $bindingId = spl_object_id($binding);
        $key = $this->bindingKeys[$bindingId] ?? null;
        if($key === null){
            return;
        }
        unset($this->bindingKeys[$bindingId]);

        $listener = $this->listeners[$key] ?? null;
        if($listener === null){
            return;
        }

        $listener->detachBinding($binding);

        // Unregister from PMMP and clean up if no dispatchers remain.
        if(!$listener->hasDispatchers()){
            $registeredListener = $listener->getRegisteredListener();
            if($registeredListener !== null){
                HandlerListManager::global()->unregisterAll($registeredListener);
            }
            unset($this->listeners[$key]);
        }
    }

}
