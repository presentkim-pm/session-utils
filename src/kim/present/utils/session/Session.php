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

use pocketmine\player\Player;

/**
 * Base implementation shared by all session types.
 *
 * @template TManager of SessionManager
 */
abstract class Session{
    private bool $active = false;

    public function __construct(
        /** @phpstan-var TManager $sessionManager */
        protected readonly SessionManager $sessionManager,
        protected readonly Player $player
    ){}

    final public function getPlayer() : Player{
        return $this->player;
    }

    final public function isActive() : bool{
        return $this->active;
    }

    final public function start() : void{
        if($this->active){
            return;
        }

        $this->active = true;
        $this->onStart();
    }

    /**
     * Terminates this session. Calling it on an already terminated session is a no-op.
     *
     * The termination always runs in the same order: {@link onTerminate()} first, then the
     * manager's onSessionTerminated callback, each exactly once.
     */
    final public function terminate(string $reason = SessionTerminateReasons::MANUAL) : void{
        if(!$this->active){
            return;
        }

        $this->sessionManager->removeSession($this, $reason);

        // The manager did not know this session (e.g. it never made it into the manager). Finish it here.
        $this->finalizeTermination($reason);
    }

    /**
     * Marks this session as terminated and calls {@link onTerminate()}. Does nothing if already terminated.
     *
     * @internal Called by {@link SessionManager}; use {@link terminate()} instead.
     */
    final public function finalizeTermination(string $reason) : void{
        if(!$this->active){
            return;
        }

        $this->active = false;
        $this->onTerminate($reason);
    }

    abstract protected function onStart() : void;

    abstract protected function onTerminate(string $reason) : void;
}
