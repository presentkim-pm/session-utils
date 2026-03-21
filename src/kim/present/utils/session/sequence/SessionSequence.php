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

use kim\present\utils\session\LifecycleSession;
use kim\present\utils\session\SessionTerminateReasons;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * Manages an ordered progression of {@link SequenceSession} subclasses for a player.
 *
 * Internally creates one {@link SequenceSessionManager} per step and chains them
 * together. The player moves through the steps by calling {@link SequenceSession::next()}
 * inside each session.
 *
 * Sessions registered in a sequence must NOT implement {@link LifecycleSession} —
 * automatic creation on join would bypass the sequence's controlled entry points
 * and cause unpredictable behavior. This constraint is enforced at construction time.
 *
 * Usage:
 * ```php
 * $sequence = new SessionSequence($plugin,
 *     TutorialStep1Session::class,
 *     TutorialStep2Session::class,
 *     TutorialStep3Session::class,
 * );
 *
 * $sequence->onComplete(function(Player $player) : void{
 *     $player->sendMessage("Tutorial complete!");
 * });
 *
 * // Start from the first step
 * $sequence->start($player);
 *
 * // Resume from a saved step (by 0-based index)
 * $sequence->startFrom($player, 2);
 *
 * // Resume from a saved step (by class name)
 * $sequence->startFrom($player, TutorialStep3Session::class);
 * ```
 */
final class SessionSequence{

    /**
     * One manager per step, in progression order.
     *
     * @var SequenceSessionManager[]
     */
    private array $managers = [];

    /**
     * Callback invoked when the last session in the sequence calls next().
     *
     * @var (\Closure(Player): void)|null
     */
    private ?\Closure $completeCallback = null;

    /**
     * @param PluginBase                    $plugin               The plugin that owns this sequence.
     * @param class-string<SequenceSession> ...$sessionClasses    Session classes in progression order.
     *                                                            Must not implement {@link LifecycleSession}.
     *
     * @throws \InvalidArgumentException If no session classes are provided,
     *                                   or if any class does not extend {@link SequenceSession},
     *                                   or if any class implements {@link LifecycleSession}.
     */
    public function __construct(
        private readonly PluginBase $plugin,
        string ...$sessionClasses,
    ){
        if(count($sessionClasses) === 0){
            throw new \InvalidArgumentException(
                "SessionSequence requires at least one session class."
            );
        }

        foreach($sessionClasses as $sessionClass){
            if(!is_a($sessionClass, SequenceSession::class, true)){
                throw new \InvalidArgumentException(
                    "$sessionClass must extend " . SequenceSession::class . " to be used in a SessionSequence."
                );
            }
            if(is_a($sessionClass, LifecycleSession::class, true)){
                throw new \InvalidArgumentException(
                    "$sessionClass must not implement " . LifecycleSession::class . ". " .
                    "SessionSequence manages its own lifecycle — automatic creation on join would cause unexpected behavior."
                );
            }
            $this->managers[] = new SequenceSessionManager($this->plugin, $sessionClass);
        }

        $this->chainManagers();
    }

    /**
     * Registers a callback invoked when the last session in the sequence calls next().
     *
     * @param \Closure(Player): void $callback
     */
    public function onComplete(\Closure $callback) : self{
        $this->completeCallback = $callback;
        // Update the last manager's exhausted callback in case this is called after construction.
        $last = $this->managers[count($this->managers) - 1];
        $last->setOnExhausted($callback);
        return $this;
    }

    /**
     * Starts the sequence from the first step for the given player.
     *
     * @param Player $player The player to start the sequence for.
     */
    public function start(Player $player) : void{
        $this->managers[0]->createSession($player);
    }

    /**
     * Starts the sequence from a specific step for the given player.
     *
     * @param Player           $player The player to start the sequence for.
     * @param int|class-string $step   Step index (0-based) or fully-qualified session class name.
     *
     * @throws \InvalidArgumentException If the step index is out of range or the class is not in the sequence.
     */
    public function startFrom(Player $player, int|string $step) : void{
        $this->managers[$this->resolveIndex($step)]->createSession($player);
    }

    /**
     * Terminates all active sessions across all steps.
     *
     * @param string $reason Termination reason. See {@link SessionTerminateReasons}.
     */
    public function terminateAll(string $reason = SessionTerminateReasons::MANUAL) : void{
        foreach($this->managers as $manager){
            $manager->terminateAll($reason);
        }
    }

    /**
     * Returns the session class name managed at the given step index.
     *
     * @param int $index 0-based step index.
     *
     * @return class-string<SequenceSession>|null The session class, or null if out of range.
     */
    public function getSessionClassAt(int $index) : ?string{
        return isset($this->managers[$index]) ? $this->managers[$index]->getSessionClass() : null;
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    /**
     * Chains managers together in order and sets the exhausted callback on the last one.
     */
    private function chainManagers() : void{
        $lastIndex = count($this->managers) - 1;
        foreach($this->managers as $i => $manager){
            if($i < $lastIndex){
                $manager->setNext($this->managers[$i + 1]);
            }else{
                $manager->setOnExhausted(function(Player $player) : void{
                    if($this->completeCallback !== null){
                        ($this->completeCallback)($player);
                    }
                });
            }
        }
    }

    /**
     * Resolves a step specifier to a manager array index.
     *
     * @param int|string $step Index (0-based) or session class name.
     *
     * @return int Resolved index.
     *
     * @throws \InvalidArgumentException If the step cannot be resolved.
     */
    private function resolveIndex(int|string $step) : int{
        if(is_int($step)){
            if(!isset($this->managers[$step])){
                throw new \InvalidArgumentException(
                    "Step index $step is out of range (0–" . (count($this->managers) - 1) . ")."
                );
            }
            return $step;
        }

        foreach($this->managers as $i => $manager){
            if($manager->getSessionClass() === $step){
                return $i;
            }
        }

        throw new \InvalidArgumentException(
            "Session class $step is not registered in this sequence."
        );
    }
}
