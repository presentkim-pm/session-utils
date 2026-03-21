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

use kim\present\utils\session\Session;
use kim\present\utils\session\SessionTerminateReasons;

/**
 * Base class for sessions that participate in a {@link SessionSequence}.
 *
 * Extends {@link Session} with a single additional method, {@link next()},
 * which advances the sequence to the next step. This separation ensures
 * that sequence progression is only available to sessions that are
 * explicitly designed to be part of a sequence.
 *
 * Classes extending this must NOT implement {@link LifecycleSession} —
 * lifecycle is managed entirely by the owning {@link SessionSequence}.
 *
 * Usage:
 * ```php
 * final class TutorialStep1Session extends SequenceSession{
 *
 *     protected function onStart() : void{
 *         $this->getPlayer()->sendMessage("Step 1: Break a block.");
 *     }
 *
 *     protected function onTerminate(string $reason) : void{}
 *
 *     #[SessionEventHandler(BlockBreakEvent::class)]
 *     public function onBlockBreak(BlockBreakEvent $event) : void{
 *         $this->next(); // Terminates this session and starts the next step.
 *     }
 * }
 * ```
 *
 * @see SessionSequence
 * @extends Session<SequenceSessionManager>
 */
abstract class SequenceSession extends Session{

    /**
     * Terminates this session and advances the sequence to the next step.
     *
     * If this is the last session in the sequence, the sequence's
     * onComplete callback is invoked instead.
     *
     * @param string $reason Termination reason forwarded to {@link onTerminate()}.
     */
    final protected function next(string $reason = SessionTerminateReasons::COMPLETED) : void{
        $this->sessionManager->progressNext($this->getPlayer(), $reason);
    }

}
