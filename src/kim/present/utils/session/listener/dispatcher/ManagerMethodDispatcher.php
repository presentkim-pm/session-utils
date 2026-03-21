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
 * Dispatches an event directly to a SessionManager method.
 *
 * Used for manager-level lifecycle handling such as PlayerJoinEvent and
 * PlayerQuitEvent, where the target is the manager itself rather than
 * a per-player session instance.
 *
 * The bound method is invoked with the fired event as its only argument.
 */
final readonly class ManagerMethodDispatcher extends BaseSessionEventDispatcher{

    /**
     * @param Event  $event  The event to dispatch.
     * @param Player $player The player extracted from the event (unused directly;
     *                       the manager method receives the event and resolves
     *                       the player itself if needed).
     *
     * @internal Called by {@link SessionEventListener::onEvent()}.
     */
    public function dispatch(Event $event, Player $player) : void{
        $this->sessionManager->{$this->methodName}($event);
    }

}
