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

use pocketmine\event\Event;
use pocketmine\player\Player;

/**
 * Dispatches an event to the method of a player's active session.
 *
 * Looks up the session associated with the given player in the owning
 * SessionManager and invokes the bound method on it. If no active session
 * exists for the player, the dispatch is silently skipped.
 */
final readonly class SessionMethodDispatcher extends BaseSessionEventDispatcher{

    /**
     * @param Event  $event  The event to dispatch.
     * @param Player $player The player whose session should receive the event.
     *
     * @internal Called by {@link SessionEventListener::onEvent()}.
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
