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

/**
 * Built-in terminate reasons used by this library.
 *
 * Custom reasons are allowed — these constants exist to avoid typos and
 * provide consistent semantics across plugins using this library.
 *
 * Usage:
 * ```php
 * $manager->removeSession($player, SessionTerminateReasons::PLAYER_QUIT);
 * ```
 */
interface SessionTerminateReasons{

    /** Session was terminated explicitly by plugin code. */
    public const MANUAL = "manual";

    /** Session failed to initialize correctly and was terminated before becoming active. */
    public const START_FAILED = "start_failed";

    /** Session was terminated because the player disconnected from the server. */
    public const PLAYER_QUIT = "player_quit";

    /** Session was terminated because the owning plugin was disabled. */
    public const PLUGIN_DISABLE = "plugin_disable";

    /** Session reached its intended end state successfully. */
    public const COMPLETED = "completed";

    /** Session was abandoned before reaching its end state. */
    public const CANCELLED = "cancelled";

    /** Session exceeded its allotted time and was forcibly ended. */
    public const TIMEOUT = "timeout";

    /** Session was terminated to be restarted fresh. */
    public const RESTART = "restart";

    /** Session was terminated due to server maintenance. */
    public const MAINTENANCE = "maintenance";

}
