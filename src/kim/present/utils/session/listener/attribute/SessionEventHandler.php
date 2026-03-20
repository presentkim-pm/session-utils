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

namespace kim\present\utils\session\listener\attribute;

use Attribute;
use pocketmine\event\Event;
use pocketmine\event\EventPriority;

/**
 * Marks a method as a session-scoped event handler.
 *
 * The method must be public and accept exactly one non-null parameter
 * which is a subclass of pocketmine\event\Event.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class SessionEventHandler{

    /**
     * @param class-string                $eventClass
     *
     * @phpstan-param class-string<Event> $eventClass
     */
    public function __construct(
        public string $eventClass,
        public int $priority = EventPriority::NORMAL,
        public bool $handleCancelled = true
    ){}

}

