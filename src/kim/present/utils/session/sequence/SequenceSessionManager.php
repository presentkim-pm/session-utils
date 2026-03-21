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

namespace kim\present\utils\session\sequence;

use kim\present\utils\session\SessionManager;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * A {@link SessionManager} that supports sequential session progression.
 *
 * Used internally by {@link SessionSequence} to chain steps together.
 * Each instance may have an optional next manager to hand off to when
 * {@link progressNext()} is called, and an optional exhausted callback
 * for when no next manager exists (i.e. the sequence is complete).
 *
 * This class is not intended to be used directly — use {@link SessionSequence} instead.
 *
 * @template TSession of SequenceSession
 * @extends SessionManager<PluginBase, TSession>
 *
 * @internal
 */
class SequenceSessionManager extends SessionManager{

    /**
     * The next manager in the sequence chain, or null if this is the last step.
     *
     * @var SequenceSessionManager<SequenceSession>|null
     */
    private ?SequenceSessionManager $next = null;

    /**
     * Callback invoked when this is the last step and next() is called.
     * Receives the player whose session just completed.
     *
     * @var (\Closure(Player): void)|null
     */
    private ?\Closure $onExhausted = null;

    /**
     * Sets the next manager in the sequence chain.
     *
     * @param SequenceSessionManager<SequenceSession> $next
     */
    public function setNext(SequenceSessionManager $next) : void{
        $this->next = $next;
    }

    /**
     * Sets the callback to invoke when this is the last step and next() is called.
     *
     * @param \Closure(Player): void $callback
     */
    public function setOnExhausted(\Closure $callback) : void{
        $this->onExhausted = $callback;
    }

    /**
     * Advances the sequence for the given player.
     *
     * Terminates the current session, then either:
     * - Creates a session in the next manager, or
     * - Invokes the onExhausted callback if this is the last step.
     *
     * @internal Called by {@link SequenceSession::next()}.
     */
    public function progressNext(Player $player, string $reason) : void{
        $this->removeSession($player, $reason);

        if($this->next !== null){
            $this->next->createSession($player);
        }elseif($this->onExhausted !== null){
            ($this->onExhausted)($player);
        }
    }
}
